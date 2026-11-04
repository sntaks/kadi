<?php

namespace App\Sportsbook\Betting\BookingCodes;

use App\Models\User;
use App\Sportsbook\Betting\BettableSelections;
use App\Sportsbook\Models\BookingCode;
use App\Sportsbook\Models\Selection;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;
use RuntimeException;

/**
 * Saves a bet slip as a short code anyone can load. Only selections that can be bet on right now are kept, one per
 * match, and the code expires when the first of those matches kicks off.
 */
class CreateBookingCode
{
    /**
     * No 0/O, 1/I/L: codes are read aloud and typed on phones.
     */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private const LENGTH = 6;

    public function __construct(private readonly BettableSelections $bettableSelections) {}

    /**
     * @param  list<int>  $selectionIds
     *
     * @throws InvalidArgumentException When none of the selections can be bet on.
     */
    public function handle(array $selectionIds, ?User $user = null): BookingCode
    {
        $selections = $this->bettableSelections->find(array_slice(array_values(array_unique($selectionIds)), 0, (int) config('sportsbook.limits.max_selections')))
            ->filter(fn (Selection $selection): bool => $this->bettableSelections->unavailableReason($selection) === null)
            ->unique(fn (Selection $selection): int => $selection->market->fixture_id);

        if ($selections->isEmpty()) {
            throw new InvalidArgumentException(__('Add at least one selection that is open for betting.'));
        }

        $expiresAt = $selections->min(fn (Selection $selection) => $selection->market->fixture->kicks_off_at);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return BookingCode::create([
                    'code' => $this->generateCode(),
                    'selection_ids' => $selections->keys()->values()->all(),
                    'created_by_user_id' => $user?->id,
                    'expires_at' => $expiresAt,
                ]);
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        throw new RuntimeException('Could not generate a unique booking code.');
    }

    private function generateCode(): string
    {
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }
}
