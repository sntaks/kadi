<?php

namespace App\Livewire;

use App\Concerns\ProfileValidationRules;
use App\Facades\KadiApi;
use App\Services\KadiAccountSync;
use App\Services\TextSmsService;
use App\Support\PhoneOtp;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The phone modal: collects a missing number and confirms it with an SMS code (PhoneOtp).
 * A verified phone is required before deposits, coin purchases, withdrawals and referral payouts,
 * because the money is charged to / paid to this number.
 *
 * A number can be changed only while it is unverified (a typo at sign-up must not lock a player
 * out). Once verified it is set for good, like before.
 */
class PhoneRequired extends Component
{
    use ProfileValidationRules;

    public bool $show = false;

    /** What the phone is needed for: deposit | withdraw | referral | verify. Drives the copy. */
    public string $purpose = 'verify';

    /** phone: enter a number; code: enter the SMS code. */
    public string $step = 'phone';

    public string $phone = '';

    public string $code = '';

    public ?string $notice = null;

    public int $resendIn = 0;

    public const PURPOSES = ['deposit', 'withdraw', 'referral', 'verify'];

    public function mount(): void
    {
        // Never auto-opens on page load: only on the 'open-phone-required' event.
        $this->show = false;
    }

    /**
     * Opens the modal, but only if the player has no verified phone yet. Dispatched from wherever a
     * verified phone is required (deposit, Buy Coins, withdraw, referral payouts, the dashboard banner).
     */
    #[On('open-phone-required')]
    public function open(string $purpose = 'verify'): void
    {
        $user = auth()->user();

        if (! $user || $user->hasVerifiedPhone()) {
            return;
        }

        $this->purpose = in_array($purpose, self::PURPOSES, true) ? $purpose : 'verify';
        $this->resetErrorBag();
        $this->code = '';
        $this->notice = null;
        $this->step = empty($user->phone) ? 'phone' : 'code';
        $this->phone = '';
        $this->resendIn = app(PhoneOtp::class)->secondsUntilResend($user);
        $this->show = true;
    }

    public function close(): void
    {
        $this->show = false;
    }

    /** "Wrong number?": only while the stored number is unverified. */
    public function changeNumber(): void
    {
        if (auth()->user()->hasVerifiedPhone()) {
            $this->show = false;

            return;
        }

        $this->resetErrorBag();
        $this->notice = null;
        $this->step = 'phone';
    }

    public function save(): void
    {
        $user = auth()->user();

        // A verified phone is the M-Pesa payout number: it is never overwritten here.
        if ($user->hasVerifiedPhone()) {
            $this->show = false;

            return;
        }

        $this->validate(['phone' => $this->phoneRules($user->id)]);

        $previous = $user->phone;

        // 1. Local users table (the mutator stores 254XXXXXXXXX and clears phone_verified_at on a change)
        $user->update(['phone' => $this->phone]);

        if ($previous !== $user->phone) {
            app(PhoneOtp::class)->forget($user);

            // 2. KadiApi (normalised again by the service; it never receives a `+`)
            if ($user->linked_id) {
                try {
                    KadiApi::updateCustomer($user->linked_id, ['phone_no' => $user->phone]);
                    // Bust the cached profile so the profile page reflects the new phone
                    Cache::forget("kadi.customer.{$user->id}");
                } catch (\Throwable $e) {
                    Log::error("KadiApi phone update failed for user {$user->id}: ".$e->getMessage());
                }
            }

            // 3. Kadi database (skips numbers held by another account; never throws)
            app(KadiAccountSync::class)->syncPhone($user);
        }

        $this->phone = '';
        $this->step = 'code';
        $this->sendCode();
    }

    public function sendCode(): void
    {
        $user = auth()->user();
        $this->resetErrorBag('code');

        if ($user->hasVerifiedPhone()) {
            $this->show = false;

            return;
        }

        $otp = app(PhoneOtp::class);
        $status = $otp->send($user, request()->ip());
        $this->resendIn = $otp->secondsUntilResend($user);

        $this->notice = match ($status) {
            PhoneOtp::SENT => __('We sent a code to :phone.', ['phone' => TextSmsService::mask($user->phone)]),
            PhoneOtp::COOLDOWN => __('Please wait a moment before asking for another code.'),
            PhoneOtp::LIMITED => __('Too many codes requested. Please try again in an hour.'),
            default => __('We could not send the code right now. Please try again shortly.'),
        };
    }

    public function verify(): void
    {
        $user = auth()->user();

        $this->validate(['code' => ['required', 'string', 'regex:/^\s*\d{'.config('kadi.phone_otp.length').'}\s*$/']], [
            'code.regex' => __('Enter the :length-digit code from the SMS.', ['length' => config('kadi.phone_otp.length')]),
        ]);

        $status = app(PhoneOtp::class)->verify($user, $this->code);

        if ($status === PhoneOtp::VERIFIED) {
            $this->show = false;
            $this->code = '';
            $this->notice = null;
            $this->dispatch('phone-verified');
            // Existing listeners (the deposit flow) resume on this.
            $this->dispatch('phone-saved');

            return;
        }

        $this->code = '';
        $this->addError('code', match ($status) {
            PhoneOtp::INVALID => __('That code is not right. Please check the SMS and try again.'),
            PhoneOtp::TOO_MANY => __('Too many wrong codes. Please ask for a new one.'),
            default => __('This code has expired. Please ask for a new one.'),
        });
    }

    public function render(): Factory|\Illuminate\Contracts\View\View|View
    {
        return view('livewire.phone-required');
    }
}
