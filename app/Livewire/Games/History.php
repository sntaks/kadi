<?php

namespace App\Livewire\Games;

use App\Services\GameDisputeService;
use App\Support\PlayedGame;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The Kadi tab of /activity: the player's latest games, tournaments and jackpots. A lost game, or a lost tournament/jackpot round
 * against a known opponent, can be reported once, within 3 minutes of playing (kadi.game_disputes.report_window_minutes). Games load after the first paint (wire:init) so a
 * slow KadiApi never blocks the page.
 */
class History extends Component
{
    /** Shown for an item KadiApi says is already under an open complaint (409) this session. */
    public const ALREADY_REPORTED = 'already_reported';

    #[Url(except: PlayedGame::GAME)]
    public string $tab = PlayedGame::GAME;

    /** @var array<string, list<array<string, mixed>>> */
    public array $games = [];

    /** @var array<string, string> report key => under_review | reported | already_reported */
    public array $reportStates = [];

    public bool $loaded = false;

    public bool $loadFailed = false;

    public bool $showReportModal = false;

    public ?string $reportKey = null;

    public string $reason = '';

    public string $otherReason = '';

    public string $description = '';

    public ?string $reportError = null;

    public ?string $successMessage = null;

    public ?string $noticeMessage = null;

    public function mount(): void
    {
        if (! array_key_exists($this->tab, PlayedGame::LABELS)) {
            $this->tab = PlayedGame::GAME;
        }
    }

    public function load(GameDisputeService $disputes): void
    {
        $this->fetch($disputes, fresh: false);
    }

    /** Bypasses the short cache, so it is limited per player to spare KadiApi. */
    public function refresh(GameDisputeService $disputes): void
    {
        $fresh = RateLimiter::attempt('game-history-refresh:'.auth()->id(), 6, fn () => true, 60);

        $this->fetch($disputes, fresh: (bool) $fresh);
    }

    public function setTab(string $tab): void
    {
        if (array_key_exists($tab, PlayedGame::LABELS)) {
            $this->tab = $tab;
        }
    }

    public function openReport(string $key): void
    {
        $this->successMessage = null;
        $this->noticeMessage = null;

        if (! isset($this->reportables[$key]) || isset($this->reportStates[$key])) {
            return;
        }

        if (! PlayedGame::reportWindowOpen($this->reportables[$key]['report_expires_at'])) {
            $this->noticeMessage = GameDisputeService::windowClosedMessage();

            return;
        }

        $this->resetValidation();
        $this->reset('reason', 'otherReason', 'description', 'reportError');
        $this->reportKey = $key;
        $this->showReportModal = true;
    }

    public function submitReport(GameDisputeService $disputes): void
    {
        $this->reportError = null;
        $isOther = $this->reason === GameDisputeService::OTHER_REASON;

        $this->validate([
            'reason' => ['required', 'string', Rule::in(GameDisputeService::REASONS)],
            'otherReason' => [Rule::requiredIf($isOther), 'nullable', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [
            'reason.required' => 'Choose a reason.',
            'reason.in' => 'Choose a reason from the list.',
            'otherReason.required' => 'Tell us the reason.',
            'otherReason.min' => 'The reason must be at least 3 characters.',
        ]);

        $key = $this->reportKey;

        if ($key === null) {
            $this->showReportModal = false;

            return;
        }

        $result = $disputes->file(auth()->user(), $key, $isOther ? $this->otherReason : $this->reason, $this->description);

        if ($result->isAlreadyReported()) {
            $this->reportStates[$key] = self::ALREADY_REPORTED;
            $this->closeReport();
            $this->noticeMessage = $result->message;

            return;
        }

        if (! $result->succeeded()) {
            $this->reportError = $result->message;
            $this->showApiErrors($result->errors, $isOther);

            return;
        }

        $this->closeReport();
        $this->reportStates = $disputes->reportStates(auth()->user(), $this->games);
        $this->reportStates[$key] ??= GameDisputeService::REPORTED;
        $this->successMessage = 'Complaint submitted. Any winnings from it are on hold while our team reviews it.'
            .($result->reference() ? ' Reference: '.$result->reference() : '');
    }

    /** @return array<string, array<string, mixed>> */
    #[Computed]
    public function reportables(): array
    {
        return PlayedGame::reportables($this->games);
    }

    #[Computed]
    public function reporting(): ?array
    {
        return $this->reportKey ? ($this->reportables[$this->reportKey] ?? null) : null;
    }

    /** @return list<string> */
    #[Computed]
    public function reasons(): array
    {
        return GameDisputeService::REASONS;
    }

    private function fetch(GameDisputeService $disputes, bool $fresh): void
    {
        try {
            $this->games = $disputes->recentGames(auth()->user(), $fresh);
            $this->reportStates = array_merge(
                $disputes->reportStates(auth()->user(), $this->games),
                array_filter($this->reportStates, fn ($state) => $state === self::ALREADY_REPORTED),
            );
            $this->loadFailed = false;
        } catch (\Throwable $e) {
            Log::warning('Game history: could not load played games for user '.auth()->id().': '.$e->getMessage());
            $this->loadFailed = true;
        }

        unset($this->reportables);
        $this->loaded = true;
    }

    private function closeReport(): void
    {
        $this->showReportModal = false;
        $this->reset('reportKey', 'reason', 'otherReason', 'description', 'reportError');
    }

    /**
     * KadiApi validation errors go next to the matching input; anything else joins the form message.
     *
     * @param  array<string, string>  $errors
     */
    private function showApiErrors(array $errors, bool $isOther): void
    {
        foreach ($errors as $field => $message) {
            match ($field) {
                'reason' => $this->addError($isOther ? 'otherReason' : 'reason', $message),
                'description' => $this->addError('description', $message),
                default => $this->reportError = trim(($this->reportError ?? '').' '.$message),
            };
        }
    }

    public function render(): Factory|\Illuminate\Contracts\View\View|View
    {
        return view('livewire.games.history', [
            'tabs' => PlayedGame::LABELS,
            'reportWindow' => PlayedGame::reportWindowLabel(),
        ]);
    }
}
