<?php

namespace App\Livewire\Auth;

use App\Promotions\PromoCode;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Optional signup-bonus promo code on sign-up (email form and the Google consent page). Sits inside
 * the plain Fortify form: the input is named promo_code, so the form posts it as usual. While the
 * player types it asks KadiApi (GET promo-codes/lookup) whether the code is usable, but an invalid
 * code never blocks sign-up. Pre-filled from a promo link (?promo=, kept by CapturePromoCode).
 */
class PromoCodeField extends Component
{
    public string $code = '';

    /** Server-side validation message from the form post (the session error bag is not shared). */
    public ?string $error = null;

    /** valid | invalid | null (not checked, or we could not tell). */
    public ?string $status = null;

    public ?string $expiresAt = null;

    /**
     * @param  string|null  $initial  the code typed before a failed submit; passed in because a lazy field
     *                                (the sign-in sheet) mounts in a later request, after the old input is gone
     */
    public function mount(?string $error = null, ?string $initial = null): void
    {
        $this->error = $error;
        $this->code = request()->hasSession()
            ? (string) ($initial ?? old('promo_code') ?? PromoCode::pending(request()) ?? '')
            : '';

        if ($this->code !== '') {
            $this->check();
        }
    }

    public function updatedCode(): void
    {
        $this->error = null;
        $this->check();
    }

    private function check(): void
    {
        $this->status = null;
        $this->expiresAt = null;

        $code = PromoCode::normalize($this->code);

        if ($code === null) {
            return;
        }

        $result = PromoCode::lookup($code, (string) request()->ip());

        if ($result === null) {
            return;
        }

        $this->status = $result['valid'] ? 'valid' : 'invalid';

        if ($result['valid'] && ! empty($result['expires_at'])) {
            try {
                $this->expiresAt = CarbonImmutable::parse($result['expires_at'])->setTimezone('Africa/Nairobi')->format('j M Y, H:i');
            } catch (\Throwable) {
                $this->expiresAt = null;
            }
        }
    }

    public function render(): View
    {
        return view('livewire.auth.promo-code-field', [
            'bonus' => (int) config('kadi.promotions.signup_bonus_amount'),
        ]);
    }
}
