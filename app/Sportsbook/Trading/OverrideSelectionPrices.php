<?php

namespace App\Sportsbook\Trading;

use App\Models\User;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Support\StaffActor;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Sets or clears admin price overrides on a market's selections. An override replaces the feed price for players
 * until it is cleared; odds syncs keep updating the feed price underneath it.
 */
class OverrideSelectionPrices
{
    public const MINIMUM_PRICE = 1.01;

    public const MAXIMUM_PRICE = 1000.0;

    /**
     * @param  array<int, string|null>  $prices  Selection id => override price, or null to clear that override.
     */
    public function handle(Market $market, User|StaffActor $admin, array $prices, string $reason): void
    {
        DB::transaction(function () use ($market, $admin, $prices, $reason): void {
            $changes = [];

            foreach ($market->selections as $selection) {
                if (! array_key_exists($selection->id, $prices)) {
                    continue;
                }

                $newOverride = $this->normalise($prices[$selection->id]);

                if ($newOverride === $selection->override_price) {
                    continue;
                }

                $offeredBefore = $selection->price();

                $selection->update([
                    'override_price' => $newOverride,
                    'override_reason' => $newOverride === null ? null : $reason,
                    'override_by_admin_id' => $newOverride === null || ! $admin instanceof User ? null : $admin->id,
                    'override_by_staff' => $newOverride === null ? null : StaffActor::labelOf($admin),
                ]);

                $selection->refresh()->recordOfferedPriceChange($offeredBefore);
                $changes[$selection->name] = ['from' => $offeredBefore, 'to' => $selection->price()];
            }

            if ($changes === []) {
                return;
            }

            activity('odds')
                ->causedBy(StaffActor::causer($admin))
                ->performedOn($market)
                ->event('prices-overridden')
                ->withProperties(StaffActor::with($admin, ['fixture' => $market->fixture->name(), 'market' => $market->name(), 'reason' => $reason, 'prices' => $changes]))
                ->log('Prices overridden');
        });
    }

    private function normalise(?string $price): ?string
    {
        if ($price === null || trim($price) === '') {
            return null;
        }

        if (! is_numeric($price) || (float) $price < self::MINIMUM_PRICE || (float) $price > self::MAXIMUM_PRICE) {
            throw new InvalidArgumentException('Override prices must be between '.self::MINIMUM_PRICE.' and '.self::MAXIMUM_PRICE.'.');
        }

        return number_format((float) $price, 2, '.', '');
    }
}
