<?php

namespace App\Livewire;

use App\Sportsbook\Models\FreeBet;
use App\Sportsbook\Promotions\MultibetBonus;
use App\Support\SportsPages;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

/**
 * /promotions (plan Phase 11): every offer in one place, for guests and players alike. Nothing is invented: the
 * welcome bonus is KadiApi's (the amount here is display copy, docs/promotions.md), Invite & Earn is KadiApi's own
 * programme (no amounts quoted), and the multibet bonus tiers are the ones MultibetBonus pays. Each offer shows only
 * while it can be taken up: the promo switch, the referral switch, and for sports both the sports pages and betting
 * (PLATFORM_SPORTS_BETTING), or the page would promote bets nobody can place.
 */
class Promotions extends Component
{
    public function render(): View
    {
        $user = auth()->user();
        $sports = SportsPages::on() && (bool) config('platform.sports_betting');
        $multibet = $this->multibetTiers();

        return view('livewire.promotions', [
            'welcome' => (bool) config('kadi.promotions.enabled'),
            'welcomeAmount' => (int) config('kadi.promotions.signup_bonus_amount'),
            'invite' => (bool) config('kadi.referrals.enabled') && Route::has('referrals'),
            'sports' => $sports,
            'multibet' => $multibet,
            'multibetMinPrice' => (string) config('sportsbook.promotions.multibet_bonus.min_price'),
            'freeBets' => $sports && $user !== null
                ? FreeBet::query()->where('user_id', $user->getAuthIdentifier())->usable()->get()
                : new Collection,
            'guest' => $user === null,
        ])
            ->layout('layouts.site', [
                'shell' => 'app',
                'noindex' => false,
                'page' => 'promotions',
                'og' => 'og-promotions.jpg',
                'description' => 'Every Kadi offer in one place: the welcome bonus, Invite & Earn, the multibet bonus and free bets.',
            ])
            ->title('Promotions · Kadi');
    }

    /**
     * The multibet bonus as "from N winning legs: P%", in leg order.
     *
     * @return list<array{legs: int, percent: int}>
     */
    private function multibetTiers(): array
    {
        $tiers = MultibetBonus::tiers();

        return array_map(fn (int $legs, int $percent): array => ['legs' => $legs, 'percent' => $percent], array_keys($tiers), array_values($tiers));
    }
}
