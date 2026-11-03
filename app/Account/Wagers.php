<?php

namespace App\Account;

use App\Games\GameCatalogue;
use App\Models\User;
use App\Services\KadiApiService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * A player's slot spins and scratch cards (`GET customers/{id}/wagers`), presented for /activity.
 * Cached for 30 seconds per player, game and page; a failure is reported as such, never as an empty list.
 */
class Wagers
{
    public const PER_PAGE = 20;

    public function __construct(private readonly KadiApiService $api, private readonly GameCatalogue $catalogue) {}

    /**
     * @return array{items: list<array<string, mixed>>, page: int, last_page: int}|null null when KadiApi could not be read
     */
    public function page(User $user, string $game, int $page = 1): ?array
    {
        if (! $user->linked_id) {
            return ['items' => [], 'page' => 1, 'last_page' => 1];
        }

        $page = max(1, $page);

        try {
            $data = Cache::remember("kadi.wagers.{$user->id}.{$game}.{$page}", 30, fn () => $this->api->wagers((int) $user->linked_id, $game, $page, self::PER_PAGE));
        } catch (\Throwable $e) {
            Log::warning('Activity: wagers unavailable', ['user' => $user->id, 'game' => $game, 'error' => class_basename($e)]);

            return null;
        }

        $titles = $this->catalogue->titles()->keyBy('code');

        return [
            'items' => array_map(fn (array $wager) => $this->present($wager, $titles->get((string) ($wager['product'] ?? ''))?->name), $data['items']),
            'page' => (int) ($data['pagination']['page'] ?? $page),
            'last_page' => max(1, (int) ($data['pagination']['last_page'] ?? 1)),
        ];
    }

    /** @param  array<string, mixed>  $wager @return array<string, mixed> */
    private function present(array $wager, ?string $name): array
    {
        $status = (string) ($wager['status'] ?? 'open');
        $stake = (float) ($wager['stake'] ?? 0);
        $payout = (float) ($wager['payout'] ?? 0) + (float) ($wager['bonus'] ?? 0);

        return [
            'reference' => (string) ($wager['reference'] ?? ''),
            'name' => $name ?? (string) ($wager['product'] ?? 'Game'),
            'placed_at' => isset($wager['placed_at']) ? CarbonImmutable::parse($wager['placed_at']) : null,
            'stake' => $stake,
            'free_bet' => ($wager['funding'] ?? null) === 'free_bet',
            'status' => $status,
            'payout' => $payout,
            'tax' => (float) ($wager['tax'] ?? 0),
            'outcome' => match ($status) {
                'won' => 'Won',
                'lost' => 'No win',
                'void' => 'Void, stake returned',
                default => 'Open',
            },
        ];
    }
}
