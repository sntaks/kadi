<?php

namespace App\Livewire;

use App\Support\Faqs;
use App\Support\NewLook;
use Illuminate\Contracts\View\Factory;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

// The help pages read in the padded reading shell in the new look (layouts.guest passes `shell` on).
#[Layout('layouts.guest', ['shell' => 'app', 'noindex' => false])]
#[Title('Kadi FAQ — Questions About Playing Kadi Online | Kadi Online')]
class Faq extends Component
{
    public function render(): Factory|View
    {
        return view(NewLook::view('livewire.help.faq', 'livewire.faq'), ['faqs' => Faqs::all()])
            ->layoutData([
                'description' => 'Answers to common questions about Kadi: what it is, how to play Kadi online, the rules, game modes, M-Pesa deposits and player names.',
                'page' => 'faq',
            ]);
    }
}
