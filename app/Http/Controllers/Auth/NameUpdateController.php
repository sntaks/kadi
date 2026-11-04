<?php

namespace App\Http\Controllers\Auth;

use App\Concerns\ProfileValidationRules;
use App\Http\Controllers\Controller;
use App\Services\KadiAccountSync;
use App\Support\AuthView;
use App\Support\PlayerName;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The page a player is sent to when their name breaks the naming rules (see EnsureNameIsValid).
 */
class NameUpdateController extends Controller
{
    use ProfileValidationRules;

    public function show(Request $request): View|RedirectResponse
    {
        if (! PlayerName::isForced()) {
            return redirect()->route('home');
        }

        return view(AuthView::name('update-name'), ['suggestion' => PlayerName::suggest($request->user()->name)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => $this->nameRules()]);

        $user = $request->user();
        $previousEmail = $user->email;

        $user->name = $validated['name'];
        PlayerName::stampChange($user);
        $user->save();

        app(KadiAccountSync::class)->syncName($user, $previousEmail);
        PlayerName::clearIfResolved($user);

        return redirect()->intended(route('home'));
    }
}
