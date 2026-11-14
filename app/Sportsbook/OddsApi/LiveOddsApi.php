<?php

namespace App\Sportsbook\OddsApi;

use App\Sportsbook\Models\OddsApiCall;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Calls The Odds API. Paid calls pass the credit budget first and are never retried, because a retry spends again.
 * Every call, free or paid, is written to the odds_api_calls ledger with the quota headers it returned; paid calls
 * also with their purpose, which each purpose's monthly allowance is counted from.
 */
class LiveOddsApi implements OddsApi
{
    public function __construct(
        private readonly CreditBudget $budget,
        private readonly string $apiKey,
        private readonly string $baseUrl,
    ) {}

    public static function fromConfig(CreditBudget $budget): self
    {
        return new self(
            budget: $budget,
            apiKey: (string) config('services.odds_api.key'),
            baseUrl: (string) config('services.odds_api.base_url'),
        );
    }

    public function sports(): array
    {
        /** @var list<array{key: string, group: string, title: string, description: string, active: bool, has_outrights: bool}> */
        return $this->send('sports', null, fn (PendingRequest $http): Response => $this->retryingFreeCall($http)->get('/sports'));
    }

    public function events(string $sportKey): array
    {
        /** @var list<array{id: string, sport_key: string, commence_time: string, home_team: string, away_team: string}> */
        return $this->send('events', $sportKey, fn (PendingRequest $http): Response => $this->retryingFreeCall($http)
            ->get("/sports/{$sportKey}/events", ['dateFormat' => 'iso']));
    }

    /**
     * One paid call per book source (markets with their own bookmakers are bought separately, at the same cost per
     * market), merged into one list of events. The first call holds the main markets and its failure (or refusal)
     * throws as before. A later call failing only leaves its markets out: their existing prices go stale on their
     * own, and the main prices already paid for are still applied.
     */
    public function odds(string $sportKey, array $markets, OddsApiPurpose $purpose = OddsApiPurpose::PreMatch): array
    {
        $events = [];

        foreach (OddsApiSource::groupMarkets($markets, $sportKey) as $index => ['source' => $source, 'markets' => $group]) {
            /** @var list<array{id: string, sport_key: string, commence_time: string, home_team: string, away_team: string, bookmakers: list<array{key: string, markets: list<array{key: string, outcomes: list<array{name: string, price: float|int, point?: float|int}>}>}>}> $answer */
            $buy = fn (): array => $this->budget->spend(count($group) * $source->creditsPerMarket(), fn (): array => $this->send('odds', $sportKey, fn (PendingRequest $http): Response => $http->get("/sports/{$sportKey}/odds", [
                ...$source->query(),
                'markets' => implode(',', $group),
                'oddsFormat' => 'decimal',
                'dateFormat' => 'iso',
            ]), $purpose), $purpose);

            try {
                $answer = $buy();
            } catch (Throwable $exception) {
                if ($index === 0) {
                    throw $exception;
                }

                Log::warning("Odds API {$sportKey} [".implode(',', $group).'] not bought: '.class_basename($exception));

                continue;
            }

            $events = self::mergeEvents($events, $answer);
        }

        return array_values($events);
    }

    /**
     * Adds one answer's bookmakers and markets to the events gathered so far, keyed by event id.
     *
     * @param  array<string, array{id: string, sport_key: string, commence_time: string, home_team: string, away_team: string, bookmakers: list<array{key: string, markets: list<array{key: string, outcomes: list<array{name: string, price: float|int, point?: float|int}>}>}>}>  $events
     * @param  list<array{id: string, sport_key: string, commence_time: string, home_team: string, away_team: string, bookmakers: list<array{key: string, markets: list<array{key: string, outcomes: list<array{name: string, price: float|int, point?: float|int}>}>}>}>  $answer
     * @return array<string, array{id: string, sport_key: string, commence_time: string, home_team: string, away_team: string, bookmakers: list<array{key: string, markets: list<array{key: string, outcomes: list<array{name: string, price: float|int, point?: float|int}>}>}>}>
     */
    private static function mergeEvents(array $events, array $answer): array
    {
        foreach ($answer as $event) {
            if (! isset($events[$event['id']])) {
                $events[$event['id']] = $event;

                continue;
            }

            $bookmakers = $events[$event['id']]['bookmakers'];

            foreach ($event['bookmakers'] as $bookmaker) {
                $index = array_search($bookmaker['key'], array_column($bookmakers, 'key'), true);

                if ($index === false) {
                    $bookmakers[] = $bookmaker;
                } else {
                    $bookmakers[$index]['markets'] = [...$bookmakers[$index]['markets'], ...$bookmaker['markets']];
                }
            }

            $events[$event['id']]['bookmakers'] = $bookmakers;
        }

        return $events;
    }

    public function eventOdds(string $sportKey, string $eventId, array $markets, OddsApiPurpose $purpose = OddsApiPurpose::PerEvent): ?array
    {
        $source = OddsApiSource::forEventOdds();

        try {
            /** @var array<string, mixed> $event An empty answer when the match has no odds. */
            $event = $this->budget->spend(count($markets) * $source->creditsPerMarket(), fn (): array => $this->send('event-odds', $sportKey, fn (PendingRequest $http): Response => $http->get("/sports/{$sportKey}/events/{$eventId}/odds", [
                ...$source->query(),
                'markets' => implode(',', $markets),
                'oddsFormat' => 'decimal',
                'dateFormat' => 'iso',
            ]), $purpose), $purpose);
        } catch (RequestException $exception) {
            // The match is no longer in the feed (finished, moved or removed): nothing to buy, and nothing was spent.
            if ($exception->response->status() === 404) {
                return null;
            }

            throw $exception;
        }

        if (! isset($event['id'])) {
            return null;
        }

        /** @var array{id: string, sport_key: string, commence_time: string, home_team: string, away_team: string, bookmakers: list<array{key: string, markets: list<array{key: string, outcomes: list<array{name: string, price: float|int, point?: float|int, description?: string}>}>}>} $event */
        return $event;
    }

    public function scores(string $sportKey, int $daysFrom, OddsApiPurpose $purpose = OddsApiPurpose::Scores): array
    {
        /** @var list<array{id: string, sport_key: string, commence_time: string, completed: bool, home_team: string, away_team: string, scores: list<array{name: string, score: string}>|null, last_update: string|null}> */
        return $this->budget->spend($daysFrom > 0 ? 2 : 1, fn (): array => $this->send('scores', $sportKey, fn (PendingRequest $http): Response => $http->get("/sports/{$sportKey}/scores", array_filter([
            'daysFrom' => $daysFrom > 0 ? $daysFrom : null,
            'dateFormat' => 'iso',
        ])), $purpose), $purpose);
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     * @return array<int, mixed>
     */
    private function send(string $endpoint, ?string $sportKey, callable $request, ?OddsApiPurpose $purpose = null): array
    {
        $http = Http::baseUrl($this->baseUrl)
            ->withQueryParameters(['apiKey' => $this->apiKey])
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(20);

        try {
            $response = $request($http);
        } catch (Throwable $exception) {
            $response = $exception instanceof RequestException ? $exception->response : null;
            $this->record($endpoint, $sportKey, $purpose, $response, $exception);

            throw $exception;
        }

        $this->record($endpoint, $sportKey, $purpose, $response);
        $response->throw();

        $data = $response->json();

        return is_array($data) ? $data : [];
    }

    /**
     * Free endpoints may be retried on connection failures, rate limits and server errors.
     */
    private function retryingFreeCall(PendingRequest $http): PendingRequest
    {
        return $http->retry([500, 2000], 0, fn (Throwable $exception): bool => $exception instanceof ConnectionException
            || ($exception instanceof RequestException
                && ($exception->response->serverError() || $exception->response->status() === 429)));
    }

    private function record(string $endpoint, ?string $sportKey, ?OddsApiPurpose $purpose, ?Response $response, ?Throwable $exception = null): void
    {
        OddsApiCall::create([
            'endpoint' => $endpoint,
            'sport_key' => $sportKey,
            'purpose' => $purpose?->value,
            'credits_spent' => (int) ($response?->header('x-requests-last') ?: 0),
            'credits_remaining' => $this->headerAsInt($response, 'x-requests-remaining'),
            'credits_used' => $this->headerAsInt($response, 'x-requests-used'),
            'http_status' => $response?->status(),
            'succeeded' => $exception === null && $response !== null && $response->successful(),
            'error' => $exception !== null ? mb_substr(class_basename($exception).': '.$this->redact($exception->getMessage()), 0, 255) : null,
            'created_at' => now('UTC'),
        ]);
    }

    /**
     * Connection errors can quote the request URL, which carries the API key.
     */
    private function redact(string $message): string
    {
        return $this->apiKey === '' ? $message : str_replace($this->apiKey, '[redacted]', $message);
    }

    private function headerAsInt(?Response $response, string $header): ?int
    {
        $value = $response?->header($header);

        return is_numeric($value) ? (int) $value : null;
    }
}
