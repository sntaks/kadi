<?php

namespace App\Sportsbook\Betting\Jackpot;

use App\Sportsbook\Enums\JackpotEntryStatus;
use App\Sportsbook\Enums\JackpotStatus;
use App\Sportsbook\Models\Jackpot;
use App\Sportsbook\Wagers\WagerOps;
use Illuminate\Support\Facades\DB;

/**
 * Undoes a jackpot's settlement so it can settle again after a corrected result: a reverse op of the current version
 * for every settled entry takes its prize back (the API allows a balance below zero), and the jackpot and its
 * entries are open again under the next settlement version.
 */
class ReopenJackpot
{
    public function __construct(private WagerOps $ops) {}

    public function handle(Jackpot $jackpot): Jackpot
    {
        return DB::transaction(function () use ($jackpot): Jackpot {
            $jackpot = Jackpot::query()->whereKey($jackpot->id)->lockForUpdate()->firstOrFail();

            if ($jackpot->status !== JackpotStatus::Settled) {
                return $jackpot;
            }

            $entries = $jackpot->entries()->whereIn('status', [JackpotEntryStatus::Won, JackpotEntryStatus::Lost])->lockForUpdate()->get();

            foreach ($entries as $entry) {
                $this->ops->reverse($entry, $jackpot->settlement_version, 'Result corrected');
            }

            $this->ops->sendAfterCommit();

            $jackpot->entries()->whereKey($entries->modelKeys())->update([
                'status' => JackpotEntryStatus::Open,
                'correct_count' => null,
                'prize' => null,
                'tax' => null,
                'settled_at' => null,
            ]);

            $jackpot->update([
                'status' => JackpotStatus::Open,
                'settled_at' => null,
                'settlement_version' => $jackpot->settlement_version + 1,
            ]);

            return $jackpot;
        });
    }
}
