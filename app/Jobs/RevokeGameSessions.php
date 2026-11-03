<?php

namespace App\Jobs;

use App\Services\KadiApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * Ends a player's game sessions at the Kadi API (`player-tokens/revoke`), so every
 * game they had open (slots, scratch cards, later the sportsbook) stops accepting
 * their player token. Repeating it is harmless, so it retries.
 *
 * Carries the KadiApi customer id only. Keep the nested limits:
 * timeout 60s < worker --timeout 90s < retry_after 120s.
 */
class RevokeGameSessions implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public int $timeout = 60;

    public function __construct(public int $customerId, public ?string $game = null) {}

    public function handle(KadiApiService $api): void
    {
        try {
            $api->revokePlayerTokens($this->customerId, $this->game);
        } catch (RequestException $e) {
            // 404: the customer or game is gone, so there is nothing left to end.
            if ($e->response->status() === 404) {
                return;
            }

            Log::warning('Ending game sessions failed', ['status' => $e->response->status(), 'attempt' => $this->attempts()]);

            throw $e;
        } catch (ConnectionException $e) {
            Log::warning('Ending game sessions failed: Kadi API unreachable', ['attempt' => $this->attempts()]);

            throw $e;
        }
    }
}
