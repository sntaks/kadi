<?php

use Illuminate\Support\Facades\Route;

/*
 * The old Flux settings pages are gone (plan Phase 6). Their addresses still work: each sends the
 * player to the matching tab of the account page (/account in the new look, /profile before it).
 * Names are kept so any old link still resolves.
 */
$settingsTab = fn (string $tab) => fn () => redirect()->to(
    route(config('platform.ui_v2') ? 'account' : 'profile').'#'.(config('platform.ui_v2') ? $tab : ($tab === 'profile' ? 'info' : $tab))
);

Route::middleware(['auth'])->group(function () use ($settingsTab) {
    Route::get('settings', $settingsTab('profile'));
    Route::get('settings/profile', $settingsTab('profile'))->name('profile.edit');
    Route::get('settings/appearance', $settingsTab('profile'))->name('appearance.edit');
    Route::get('settings/security', $settingsTab('security'))->name('security.edit');
});
