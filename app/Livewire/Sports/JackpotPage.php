<?php

namespace App\Livewire\Sports;

use App\Models\User;
use App\Sportsbook\Betting\Jackpot\EnterJackpot;
use App\Sportsbook\Betting\Jackpot\JackpotEntryRefused;
use App\Sportsbook\Enums\JackpotEntryStatus;
use App\Sportsbook\Models\Jackpot;
use App\Sportsbook\Models\JackpotEntry;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * /sports/jackpot: the jackpot taking entries. Picks live in the browser until the player enters; the server checks
 * every pick, the deadline and (through the Kadi API) the balance when the entry is made. An entry still being
 * checked with the wallet shows as such and is looked up again.
 *
 * @property-read ?Jackpot $jackpot
 * @property-read ?CarbonImmutable $closesAt
 * @property-read bool $isTakingEntries
 * @property-read ?JackpotEntry $enteredEntry
 * @property-read int $myEntryCount
 */
class JackpotPage extends Component
{
    /** One per entry: submitting the same picks twice finds the entry already made instead of paying again. */
    #[Locked]
    public string $entryKey = '';

    #[Locked]
    public ?int $enteredEntryId = null;

    #[Locked]
    public ?string $refusal = null;

    public function mount(): void
    {
        $this->entryKey = (string) Str::uuid();
    }

    /**
     * @param  array<int|string, mixed>  $picks  Jackpot match id => "1", "X" or "2".
     */
    public function enter(array $picks, EnterJackpot $enterJackpot): void
    {
        $user = auth()->user();
        $jackpot = $this->jackpot;

        if (! $user instanceof User || $jackpot === null) {
            return;
        }

        $this->resetErrorBag('entry');
        $this->refusal = null;

        try {
            $entry = $enterJackpot->handle($user, $jackpot, $picks, $this->entryKey, request()->ip());
        } catch (JackpotEntryRefused $e) {
            $this->refusal = $e->reason;
            $this->addError('entry', $e->getMessage());

            return;
        }

        $this->enteredEntryId = $entry->id;
        $this->entryKey = (string) Str::uuid();
        unset($this->myEntryCount, $this->enteredEntry);
        $this->dispatch('jackpot-entered');
        $this->trackEntered($entry);
        $this->dispatch('wallet-refreshed');
    }

    public function enterAgain(): void
    {
        $this->enteredEntryId = null;
    }

    #[Computed]
    public function jackpot(): ?Jackpot
    {
        return Jackpot::query()->current()->with('jackpotFixtures')->first();
    }

    #[Computed]
    public function closesAt(): ?CarbonImmutable
    {
        return $this->jackpot?->closesAt();
    }

    #[Computed]
    public function isTakingEntries(): bool
    {
        return $this->closesAt?->isFuture() ?? false;
    }

    #[Computed]
    public function enteredEntry(): ?JackpotEntry
    {
        return $this->enteredEntryId === null
            ? null
            : JackpotEntry::query()->whereKey($this->enteredEntryId)->where('user_id', auth()->id())->first();
    }

    /** Polled while the entry is being checked with the wallet. */
    public function refreshEntry(): void
    {
        unset($this->enteredEntry);
        $this->trackEntered($this->enteredEntry);
    }

    /** Analytics, once the wallet has taken the entry (a pending one counts when the check confirms it). */
    private function trackEntered(?JackpotEntry $entry): void
    {
        if ($entry?->status === JackpotEntryStatus::Open) {
            $this->dispatch('kadi-track', name: 'bet_placed', params: ['product' => 'sportsbook_jackpot', 'bet_type' => 'jackpot']);
        }
    }

    #[Computed]
    public function myEntryCount(): int
    {
        return $this->jackpot === null || ! auth()->check()
            ? 0
            : $this->jackpot->entries()->where('user_id', auth()->id())->whereNotIn('status', [JackpotEntryStatus::Rejected])->count();
    }

    public function render(): View
    {
        return view('livewire.sports.jackpot')
            ->layout('layouts.site', ['shell' => 'app', 'rail' => true, 'noindex' => true, 'page' => 'sports', 'og' => 'og-sports.jpg'])
            ->title('Sports jackpot · Kadi');
    }
}
