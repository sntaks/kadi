<?php

use Illuminate\Support\Facades\Route;

/*
 * The old Flux settings pages are gone (plan Phase 6). Their addresses still work: each sends the
 * player to the matching tab of /account. Names are kept so any old link still resolves.
 */
$settingsTab = fn (string $tab) => fn () => redirect()->to(route('account').'#'.$tab);

Route::middleware(['auth'])->group(function () use ($settingsTab) {
    Route::get('settings', $settingsTab('profile'));
    Route::get('settings/profile', $settingsTab('profile'))->name('profile.edit');
    Route::get('settings/appearance', $settingsTab('profile'))->name('appearance.edit');
    Route::get('settings/security', $settingsTab('security'))->name('security.edit');
});
