<?php

namespace App\Livewire\Predict;

use App\Models\User;
use App\Predict\Data\PredictBet;
use App\Predict\Markets;
use App\Predict\MyBets as Bets;
use App\Predict\PendingAttempt;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * /predictions/bets: the player's prediction bets, Open and Settled, newest first, with "Load more". A bet whose
 * outcome is still unknown shows at the top in its own slip ("We're checking your bet", "Try again" with the same
 * reference). Markets open in the prediction sheet.
 */
class MyBets extends Component
{
    /** The most pages one visit loads (20 bets each). */
    public const MAX_PAGES = 25;

    #[Url(except: 'open')]
    public string $tab = 'open';

    #[Locked]
    public int $pages = 1;

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, Bets::TABS, true) ? $tab : 'open';
        $this->pages = 1;
    }

    public function loadMore(): void
    {
        $this->pages = min($this->pages + 1, self::MAX_PAGES);
    }

    public function render(Bets $myBets): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $this->tab = in_array($this->tab, Bets::TABS, true) ? $this->tab : 'open';
        $bets = [];
        $first = null;
        $lastPage = 1;

        for ($page = 1; $page <= $this->pages; $page++) {
            $result = $myBets->page($user, $this->tab, $page);
            $first ??= $result;

            if (! $result['available']) {
                break;
            }

            $lastPage = $result['last_page'];

            foreach ($result['bets'] as $bet) {
                $bets[$bet->id] ??= $bet;
            }

            if ($page >= $lastPage) {
                break;
            }
        }

        $this->pages = min($this->pages, max(1, $lastPage));
        $bets = array_values($bets);

        return view('livewire.predict.my-bets', [
            'bets' => $bets,
            'total' => $first['total'],
            'available' => $first['available'],
            'more' => $this->pages < $lastPage && $this->pages < self::MAX_PAGES,
            'labels' => Markets::cachedLabels(array_map(fn (PredictBet $bet) => $bet->marketId, $bets)),
            'pending' => PendingAttempt::get($user),
        ])
            ->layout('layouts.site', ['shell' => 'app', 'noindex' => true, 'page' => 'predictions', 'predictPanel' => true])
            ->title('My prediction bets · Kadi');
    }
}
