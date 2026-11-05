<?php

namespace App\Http\Controllers\Auth;

use App\Concerns\ConsentValidationRules;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConsentController extends Controller
{
    use ConsentValidationRules;

    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasCurrentConsent()) {
            return redirect()->route('home');
        }

        return view('auth.consent');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate($this->consentRules(), $this->consentMessages());

        $request->user()->recordConsent();
        $request->session()->forget('consent.required');

        return redirect()->intended(route('home'));
    }
}
