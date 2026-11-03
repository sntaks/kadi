<?php

namespace App\Livewire\Account;

use App\Account\ResponsiblePlay as ResponsiblePlayState;
use App\Facades\KadiApi;
use App\Jobs\RevokeGameSessions;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * /responsible-play (plan Phase 6): the player's deposit limits and breaks, which KadiApi keeps for every
 * game. A limit cut applies at once, a raise or removal waits a day; a break can be made longer but never
 * shorter or ended early. The site never decides: it asks KadiApi and shows what it says.
 */
class ResponsiblePlay extends Component
{
    public bool $loaded = false;

    public string $period = 'day';

    public string $limitAmount = '';

    public ?string $limitError = null;

    public ?string $limitSaved = null;

    public string $breakChoice = '';

    public bool $confirmingBreak = false;

    public ?string $breakError = null;

    public ?string $breakStarted = null;

    /** Changes with every new submission, so a retry of the same one reuses its Idempotency-Key. */
    public int $attempt = 0;

    public function load(): void
    {
        $this->loaded = true;
    }

    public function saveLimit(ResponsiblePlayState $state): void
    {
        $this->limitError = null;
        $this->limitSaved = null;
        $user = $this->player();
        $periods = config('kadi.responsible_play.limit_periods');

        if (! array_key_exists($this->period, $periods) || ! $user->linked_id) {
            $this->limitError = 'Choose daily, weekly or monthly.';

            return;
        }

        $amount = trim($this->limitAmount) === '' ? null : $this->limitAmount;
        $min = (int) config('kadi.responsible_play.limit_min');
        $max = (int) config('kadi.responsible_play.limit_max');

        if ($amount !== null && (! ctype_digit($amount) || (int) $amount < $min || (int) $amount > $max)) {
            $this->limitError = 'Enter whole shillings between KES '.number_format($min).' and KES '.number_format($max).', or leave it empty to remove the limit.';

            return;
        }

        if (! $this->allowed($user)) {
            $this->limitError = 'Too many changes in a short time. Wait a minute and try again.';

            return;
        }

        try {
            $limit = KadiApi::setDepositLimit((int) $user->linked_id, $this->period, $amount === null ? null : (int) $amount, $this->key($user, 'limit', $this->period, (string) $amount));
        } catch (RequestException $e) {
            $this->limitError = $e->response->status() === 422 ? ((string) $e->response->json('message') ?: "That limit wasn't accepted.") : "We couldn't save your limit. Try again.";

            return;
        } catch (\Throwable $e) {
            Log::warning('Responsible play: deposit limit not saved', ['user' => $user->id, 'error' => class_basename($e)]);
            $this->limitError = "We couldn't save your limit. Try again.";

            return;
        }

        $state->forget($user);
        $this->attempt++;
        $this->limitAmount = '';
        $label = strtolower($periods[$this->period]);
        $pendingFrom = is_string($limit['pending_from'] ?? null) ? CarbonImmutable::parse($limit['pending_from'])->timezone(config('app.timezone'))->format('j M, H:i') : null;

        $this->limitSaved = $pendingFrom
            ? "Saved. Your {$label} limit changes on {$pendingFrom}: a raise or removal always waits a day."
            : "Saved. Your {$label} limit applies now.";
    }

    public function reviewBreak(): void
    {
        $this->breakError = null;

        if ($this->breakOption() === null) {
            $this->breakError = 'Choose how long your break lasts.';

            return;
        }

        $this->confirmingBreak = true;
    }

    public function cancelBreak(): void
    {
        $this->confirmingBreak = false;
    }

    public function startBreak(ResponsiblePlayState $state): void
    {
        $this->breakError = null;
        $user = $this->player();
        $option = $this->breakOption();

        if ($option === null || ! $user->linked_id) {
            $this->confirmingBreak = false;

            return;
        }

        if (! $this->allowed($user)) {
            $this->breakError = 'Too many changes in a short time. Wait a minute and try again.';

            return;
        }

        [$type, $duration] = $option;

        try {
            $break = KadiApi::startBreak((int) $user->linked_id, $type, $duration, $this->key($user, 'break', $type, $duration));
        } catch (RequestException $e) {
            $this->confirmingBreak = false;
            $this->breakError = $e->response->json('code') === 'exclusion_shorter'
                ? 'Your current break already lasts longer. A break can be made longer, never shorter.'
                : "We couldn't start your break. Try again.";

            return;
        } catch (\Throwable $e) {
            Log::warning('Responsible play: break not started', ['user' => $user->id, 'error' => class_basename($e)]);
            $this->confirmingBreak = false;
            $this->breakError = "We couldn't start your break. Try again.";

            return;
        }

        $state->forget($user);
        $this->attempt++;
        $this->confirmingBreak = false;
        $this->breakChoice = '';

        // Close any game the player still has open: the break starts now, not when the session ends.
        RevokeGameSessions::dispatch((int) $user->linked_id);

        $endsAt = is_string($break['ends_at'] ?? null) ? CarbonImmutable::parse($break['ends_at'])->timezone(config('app.timezone'))->format('j F Y, H:i') : null;
        $this->breakStarted = $endsAt ? "Your break has started. It ends on {$endsAt}." : 'Your break has started.';
    }

    /** @return array{0: string, 1: string}|null [type, duration] for the chosen "type:duration" */
    public function breakOption(): ?array
    {
        [$type, $duration] = array_pad(explode(':', $this->breakChoice, 2), 2, '');

        return isset(config('kadi.responsible_play.breaks')[$type][$duration]) ? [$type, $duration] : null;
    }

    public function render(ResponsiblePlayState $state): View
    {
        $user = $this->player();
        $current = $this->loaded ? $state->state($user) : null;
        $option = $this->breakOption();

        return view('livewire.account.responsible-play', [
            'current' => $current,
            'linked' => (bool) $user->linked_id,
            'breakEndsAt' => $this->loaded ? $state->breakEndsAt($user)?->timezone(config('app.timezone')) : null,
            'limits' => collect($current['deposit_limits'] ?? [])->keyBy('period'),
            'periods' => config('kadi.responsible_play.limit_periods'),
            'breaks' => config('kadi.responsible_play.breaks'),
            'chosen' => $option ? config("kadi.responsible_play.breaks.{$option[0]}.{$option[1]}") : null,
            'chosenIsSelfExclusion' => ($option[0] ?? null) === 'self_exclusion',
        ])
            ->layout('layouts.app')
            ->title('Responsible play · Kadi');
    }

    /** Same submission (same body, no success since) = same key; anything else = a new key. */
    private function key(User $user, string ...$parts): string
    {
        return hash('sha256', implode('|', [$user->id, $this->attempt, ...$parts]));
    }

    private function allowed(User $user): bool
    {
        return RateLimiter::attempt('responsible-play:'.$user->id, 10, fn () => true, 60);
    }

    private function player(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
