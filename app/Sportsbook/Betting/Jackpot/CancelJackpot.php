<?php

namespace App\Sportsbook\Betting\Jackpot;

use App\Models\User;
use App\Sportsbook\Enums\JackpotEntryStatus;
use App\Sportsbook\Enums\JackpotStatus;
use App\Sportsbook\Models\Jackpot;
use App\Sportsbook\Support\StaffActor;
use App\Sportsbook\Wagers\WagerOps;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Calls a jackpot off and gives every entry its price back, through a void settle op per entry: by staff, or by
 * settlement when more matches were void than the jackpot allows. A settled jackpot is corrected through results
 * instead, never cancelled, and a cancelled one is final. Entries still being checked must be resolved first.
 */
class CancelJackpot
{
    public function __construct(private WagerOps $ops) {}

    /**
     * @throws ValidationException
     */
    public function handle(Jackpot $jackpot, string $reason, User|StaffActor|null $admin = null): Jackpot
    {
        return DB::transaction(function () use ($jackpot, $reason, $admin): Jackpot {
            $jackpot = Jackpot::query()->whereKey($jackpot->id)->lockForUpdate()->firstOrFail();

            if (! in_array($jackpot->status, [JackpotStatus::Draft, JackpotStatus::Open], true)) {
                throw ValidationException::withMessages(['jackpot' => __('Only a draft or open jackpot can be cancelled.')]);
            }

            if ($jackpot->entries()->where('status', JackpotEntryStatus::Pending)->exists()) {
                throw ValidationException::withMessages(['jackpot' => __('Some entries are still being checked with the wallet. Try again in a minute.')]);
            }

            $refunded = 0;

            foreach ($jackpot->entries()->where('status', JackpotEntryStatus::Open)->lockForUpdate()->get() as $entry) {
                $entry->update(['status' => JackpotEntryStatus::Refunded, 'prize' => $entry->stake, 'settled_at' => now('UTC')]);
                $this->ops->settle($entry, $jackpot->settlement_version, 'void', 0, reason: 'Jackpot cancelled');
                $refunded++;
            }

            $this->ops->sendAfterCommit();
            $jackpot->update(['status' => JackpotStatus::Cancelled, 'cancel_reason' => mb_substr($reason, 0, 255), 'settled_at' => now('UTC')]);

            activity('settlement')
                ->causedBy(StaffActor::causer($admin))
                ->performedOn($jackpot)
                ->event('jackpot-cancelled')
                ->withProperties(StaffActor::with($admin, ['jackpot' => $jackpot->name, 'reason' => $reason, 'entries_refunded' => $refunded]))
                ->log('Jackpot cancelled');

            return $jackpot;
        });
    }
}
