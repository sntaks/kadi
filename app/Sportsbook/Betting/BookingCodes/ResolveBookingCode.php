<?php

namespace App\Sportsbook\Betting\BookingCodes;

use App\Sportsbook\Betting\BettableSelections;
use App\Sportsbook\Models\BookingCode;
use App\Sportsbook\Models\Selection;
use InvalidArgumentException;

/**
 * Turns a booking code back into bet slip picks, keeping only selections that can still be bet on.
 */
class ResolveBookingCode
{
    public function __construct(private readonly BettableSelections $bettableSelections) {}

    /**
     * @return array{picks: list<array{fixture_id: int, selection_id: int, price: string}>, unavailable: list<string>}
     *
     * @throws InvalidArgumentException When the code does not exist or has expired.
     */
    public function handle(string $code): array
    {
        $bookingCode = BookingCode::firstWhere('code', strtoupper(trim($code)));

        if ($bookingCode === null) {
            throw new InvalidArgumentException(__('That booking code does not exist.'));
        }

        if ($bookingCode->hasExpired()) {
            throw new InvalidArgumentException(__('That booking code has expired because a match on it has started.'));
        }

        $picks = [];
        $unavailable = [];

        foreach ($this->bettableSelections->find($bookingCode->selection_ids) as $selection) {
            if ($this->bettableSelections->unavailableReason($selection) !== null) {
                $unavailable[] = $this->describe($selection);

                continue;
            }

            $picks[] = ['fixture_id' => $selection->market->fixture_id, 'selection_id' => $selection->id, 'price' => (string) $selection->price()];
        }

        return ['picks' => $picks, 'unavailable' => $unavailable];
    }

    private function describe(Selection $selection): string
    {
        return "{$selection->market->fixture->name()}: {$selection->market->name()} {$selection->name}";
    }
}
