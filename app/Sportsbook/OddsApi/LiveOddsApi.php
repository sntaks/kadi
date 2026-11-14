<?php

namespace App\Sportsbook\OddsApi;

use App\Sportsbook\Models\OddsApiCall;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
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
        private readonly OddsApiSource $source,
    ) {}

    public static function fromConfig(CreditBudget $budget): self
    {
        return new self(
            budget: $budget,
            apiKey: (string) config('services.odds_api.key'),
            baseUrl: (string) config('services.odds_api.base_url'),
            source: OddsApiSource::fromConfig(),
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

    public function odds(string $sportKey, array $markets, OddsApiPurpose $purpose = OddsApiPurpose::PreMatch): array
    {
        /** @var list<array{id: string, sport_key: string, commence_time: string, home_team: string, away_team: string, bookmakers: list<array{key: string, markets: list<array{key: string, outcomes: list<array{name: string, price: float|int, point?: float|int}>}>}>}> */
        return $this->budget->spend(count($markets) * $this->source->creditsPerMarket(), fn (): array => $this->send('odds', $sportKey, fn (PendingRequest $http): Response => $http->get("/sports/{$sportKey}/odds", [
            ...$this->source->query(),
            'markets' => implode(',', $markets),
            'oddsFormat' => 'decimal',
            'dateFormat' => 'iso',
        ]), $purpose), $purpose);
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
