<?php

namespace App\Livewire\Legal;

use App\Support\LegalDocument;
use Illuminate\Contracts\View\Factory;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Terms & Conditions | Kadi')]
class Terms extends Component
{
    public function render(): \Illuminate\Contracts\View\View|Factory|View
    {
        return view('livewire.legal.document', [
            'document' => LegalDocument::load('terms'),
            'current' => 'terms',
            'tagline' => 'The rules of the house, in full. Start with the short version, or jump to any section.',
            // Plain-language pointers only; they must never promise more than the section they link to. Icons are Hugeicons names.
            'keyPoints' => [
                ['icon' => 'id-verified', 'section' => 2, 'title' => '18+ only', 'text' => 'You must be at least 18 and pass the identity and age checks we ask for.'],
                ['icon' => 'ticket-01', 'section' => 4, 'title' => 'One ticket, one try', 'text' => 'Completed ticket purchases are final. Tickets cannot be cashed out and do not guarantee a win.'],
                ['icon' => 'time-quarter-pass', 'section' => 7, 'title' => 'Up to 3-minute checks', 'text' => 'Tournament progress and withdrawals may be checked for up to 3 minutes. M-Pesa may take longer to arrive.'],
                ['icon' => 'wifi-01', 'section' => 12, 'title' => 'Stay connected', 'text' => 'A game keeps going if you disconnect. Miss 6 turns and it ends. A lost connection does not reverse a result.'],
                ['icon' => 'bug-01', 'section' => 9, 'title' => 'Something went wrong?', 'text' => 'Report it. Only the amount in question is held while we check; the rest of your wallet stays available.'],
                ['icon' => 'ban', 'section' => 13, 'title' => 'Play fair', 'text' => 'No cheating, bots, collusion or offensive names and pictures. Breaking the rules can close your account.'],
                ['icon' => 'money-receive-02', 'section' => 15, 'title' => 'Your winnings', 'text' => 'Valid winnings are paid within the deadlines set by law and are never held indefinitely.'],
                ['icon' => 'justice-scale-01', 'section' => 22, 'title' => 'Disagree with a decision?', 'text' => 'Ask us for a review first, then mediation and arbitration. Court is the last resort.'],
            ],
        ])->layoutData([
            'description' => 'Kadi Online Terms & Conditions: eligibility, tickets, tournaments, withdrawals, fair play and how disputes are resolved.',
            'page' => 'terms',
        ]);
    }
}
