<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WalletWebhookRequest;
use App\Models\User;
use App\Support\WalletBalanceWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/v1/wallet-webhooks
 *
 * KadiApi pushes a signed event here whenever a customer's wallet balance changes, so this site
 * can show it within seconds instead of waiting on the 30s wire:poll. Every response is 200: a
 * bad signature or malformed body already got a 401/422 before reaching here, and everything
 * else (unmapped customer, stale event, duplicate delivery) is a legitimate no-op that KadiApi
 * must not retry.
 */
class WalletWebhookController extends Controller
{
    private const SEEN_TTL_HOURS = 24;

    public function __construct(private WalletBalanceWriter $writer) {}

    public function __invoke(WalletWebhookRequest $request): JsonResponse
    {
        $data = $request->validated();
        $eventId = $data['event_id'];
        $seenKey = "kadi-webhook:seen:{$eventId}";

        if (Cache::has($seenKey)) {
            return response()->json(['status' => 'ok']);
        }

        Cache::put($seenKey, true, now()->addHours(self::SEEN_TTL_HOURS));

        $users = User::query()->where('linked_id', $data['customer_id'])->get();

        if ($users->count() !== 1) {
            Log::warning('Wallet webhook: customer_id did not map to exactly one user', [
                'event_id' => $eventId,
                'customer_id' => $data['customer_id'],
                'matched' => $users->count(),
            ]);

            return response()->json(['status' => 'ok']);
        }

        /** @var User $user */
        $user = $users->first();

        // Version-guarded: a stale or repeated balance is dropped there.
        $this->writer->write($user, (float) $data['balance'], (int) $data['balance_version']);

        return response()->json(['status' => 'ok']);
    }
}
