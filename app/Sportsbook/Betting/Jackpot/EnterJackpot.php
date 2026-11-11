<?php

namespace App\Sportsbook\Betting\Jackpot;

use App\Activity\PlayerActivity;
use App\Models\User;
use App\Sportsbook\Enums\JackpotEntryStatus;
use App\Sportsbook\Models\Jackpot;
use App\Sportsbook\Models\JackpotEntry;
use App\Sportsbook\Wagers\WagerPlacement;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Enters the player into a jackpot with one pick (1, X or 2) for every match, for the jackpot's entry price. The
 * price is a Kadi API wager (product sportsbook_jackpot), placed the same way as a bet: saved as pending, then sent;
 * open, rejected, or still pending until it is looked up (WagerPlacement).
 *
 * Entries close when the first match kicks off. The jackpot row is share-locked while the entry is written, so an
 * entry cannot slip in while the jackpot is being cancelled or settled. A player may enter the same jackpot more than
 * once. An entry key makes it safe to submit twice: the second submission returns the entry the first one made.
 */
class EnterJackpot
{
    /** No 0/O, 1/I/L: references are read out to support. */
    private const REFERENCE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** "J" then seven characters, so support can tell an entry from a bet at a glance. */
    private const REFERENCE_LENGTH = 7;

    public function __construct(private WagerPlacement $placement) {}

    /**
     * @param  array<int|string, mixed>  $picks  Jackpot match id => "1", "X" or "2".
     *
     * @throws JackpotEntryRefused
     */
    public function handle(User $user, Jackpot $jackpot, array $picks, ?string $clientKey = null, ?string $ip = null): JackpotEntry
    {
        if (($refusal = $this->placement->refusalFor($user)) !== null) {
            throw self::refused($refusal);
        }

        if ($clientKey !== null && ($entered = JackpotEntry::query()->where('user_id', $user->id)->where('client_key', $clientKey)->first()) !== null) {
            return $entered;
        }

        $token = $this->placement->token($user, $ip);

        if (is_array($token)) {
            throw self::refused($token);
        }

        try {
            $entry = DB::transaction(fn (): JackpotEntry => $this->save($user, $jackpot, $picks, $clientKey));
        } catch (UniqueConstraintViolationException $e) {
            // The same entry submitted twice at once: the other request made it.
            $entered = $clientKey === null ? null : JackpotEntry::query()->where('user_id', $user->id)->where('client_key', $clientKey)->first();

            return $entered ?? throw $e;
        }

        $entry = $this->placement->place($user, $entry, $token, $ip);

        if ($entry instanceof JackpotEntry && $entry->status === JackpotEntryStatus::Rejected) {
            throw self::refused(WagerPlacement::refusal((string) $entry->reject_reason));
        }

        /** @var JackpotEntry $entry */
        app(PlayerActivity::class)->record('jackpot_entered', $user, ['status' => $entry->status->value], $entry);

        return $entry;
    }

    /**
     * @param  array{0: string, 1: string}  $refusal
     */
    private static function refused(array $refusal): JackpotEntryRefused
    {
        return new JackpotEntryRefused(match ($refusal[0]) {
            WagerPlacement::REFUSED_NOT_ALLOWED => JackpotEntryRefused::NOT_ALLOWED,
            WagerPlacement::REFUSED_FUNDS => JackpotEntryRefused::INSUFFICIENT_FUNDS,
            default => JackpotEntryRefused::WALLET,
        }, $refusal[1]);
    }

    /**
     * @param  array<int|string, mixed>  $picks
     *
     * @throws JackpotEntryRefused
     */
    private function save(User $user, Jackpot $jackpot, array $picks, ?string $clientKey): JackpotEntry
    {
        $jackpot = Jackpot::query()->whereKey($jackpot->id)->sharedLock()->firstOrFail();

        if (! $jackpot->isTakingEntries()) {
            throw new JackpotEntryRefused(JackpotEntryRefused::CLOSED, __('Entries for this jackpot have closed.'));
        }

        $matches = $jackpot->jackpotFixtures()->get();
        $chosen = [];

        foreach ($matches as $match) {
            $pick = $picks[$match->id] ?? null;
            $pick = is_string($pick) ? strtoupper(trim($pick)) : null;

            if (! in_array($pick, ['1', 'X', '2'], true)) {
                throw new JackpotEntryRefused(JackpotEntryRefused::PICKS, __('Pick 1, X or 2 for every match.'));
            }

            $chosen[$match->id] = $pick;
        }

        if (count($picks) !== count($chosen)) {
            throw new JackpotEntryRefused(JackpotEntryRefused::PICKS, __('Pick 1, X or 2 for every match.'));
        }

        $entry = JackpotEntry::create([
            'jackpot_id' => $jackpot->id,
            'user_id' => $user->id,
            'reference' => $this->reference(),
            'wager_reference' => (string) Str::uuid(),
            'status' => JackpotEntryStatus::Pending,
            'stake' => $jackpot->entry_price,
            'client_key' => $clientKey,
            'placed_at' => now('UTC'),
        ]);

        $entry->picks()->createMany(collect($chosen)->map(fn (string $pick, int $matchId): array => [
            'jackpot_fixture_id' => $matchId,
            'pick' => $pick,
        ])->values()->all());

        return $entry;
    }

    private function reference(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $reference = 'J';

            for ($i = 0; $i < self::REFERENCE_LENGTH; $i++) {
                $reference .= self::REFERENCE_ALPHABET[random_int(0, strlen(self::REFERENCE_ALPHABET) - 1)];
            }

            if (! JackpotEntry::query()->where('reference', $reference)->exists()) {
                return $reference;
            }
        }

        throw new RuntimeException('Could not generate a unique jackpot entry reference.');
    }
}
