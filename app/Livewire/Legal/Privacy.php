<?php

namespace App\Livewire\Legal;

use App\Support\LegalDocument;
use Illuminate\Contracts\View\Factory;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Privacy Policy | Kadi')]
class Privacy extends Component
{
    public function render(): \Illuminate\Contracts\View\View|Factory|View
    {
        return view('livewire.legal.document', [
            'document' => LegalDocument::load('privacy'),
            'current' => 'privacy',
            'tagline' => 'What we collect, why we need it, who sees it and what you can ask us to do with it.',
            // Plain-language pointers only; they must never promise more than the section they link to. Icons are Hugeicons names.
            'keyPoints' => [
                ['icon' => 'clipboard-list', 'section' => 3, 'title' => 'What we collect', 'text' => 'Your account details, game history, M-Pesa transactions, phone number, IP address and device information.'],
                ['icon' => 'lock-password', 'section' => 3, 'title' => 'Not your M-Pesa PIN', 'text' => 'We do not intentionally collect or store M-Pesa PINs. Never share yours with anyone.'],
                ['icon' => 'banknote-x', 'section' => 6, 'title' => 'Never sold', 'text' => 'We do not sell your personal information to advertisers.'],
                ['icon' => 'user-group', 'section' => 6, 'title' => 'Who sees it', 'text' => 'Only providers that help run Kadi (such as hosting, M-Pesa and SMS), and authorities when the law requires.'],
                ['icon' => 'message-notification-01', 'section' => 8, 'title' => 'Messages from us', 'text' => 'We contact you about your account, payments and security. You can opt out of marketing messages.'],
                ['icon' => 'user-account', 'section' => 10, 'title' => 'Your rights', 'text' => 'Ask to see, correct or delete your data. Some records, such as payments, must be kept by law.'],
                ['icon' => 'security-check', 'section' => 11, 'title' => 'Keeping it safe', 'text' => 'We protect your data and act quickly if a breach happens. Report anything suspicious on your account.'],
                ['icon' => 'complaint', 'section' => 9, 'title' => 'Not happy?', 'text' => 'Contact us, or complain to the Office of the Data Protection Commissioner of Kenya.'],
            ],
        ])->layoutData([
            'description' => 'Kadi Online Privacy Policy: what personal information we collect, how we use and share it, how long we keep it and your rights under Kenya\'s Data Protection Act.',
            'page' => 'privacy',
        ]);
    }
}
