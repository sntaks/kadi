<?php

namespace App\Livewire;

use App\Support\NewLook;
use Illuminate\Contracts\View\Factory;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

// The help pages read in the padded reading shell in the new look (layouts.guest passes `shell` on).
#[Layout('layouts.guest', ['shell' => 'app', 'noindex' => false])]
#[Title('Kadi Rules — How to Play the Kadi Card Game | Kadi Online')]
class Rules extends Component
{
    public function render(): Factory|\Illuminate\Contracts\View\View|View
    {
        return view(NewLook::view('livewire.help.rules', 'livewire.rules'))
            ->layoutData([
                'description' => 'Kadi rules explained: how to play Kadi, deal the cards, chain combos, use Aces, Jokers and question cards, and win by calling Kadi. A complete guide for 2–4 players.',
                'page' => 'rules',
            ]);
    }
}
