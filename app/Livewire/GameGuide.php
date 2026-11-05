<?php

namespace App\Livewire;

use Illuminate\Contracts\View\Factory;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

// The help pages read in the site shell's padded reading column.
#[Layout('layouts.site', ['shell' => 'app', 'noindex' => false])]
#[Title('Kadi Game Modes, Tournaments & Prizes | Kadi Online')]
class GameGuide extends Component
{
    public function render(): Factory|View
    {
        return view('livewire.help.game-guide')
            ->layoutData([
                'description' => 'Every way to play Kadi online: 2, 3 and 4-player matches, tournaments and jackpots. See stakes, multipliers and prize pools before you play the Kadi game.',
                'page' => 'game-guide',
            ]);
    }
}
