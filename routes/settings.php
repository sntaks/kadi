<?php

use App\Http\Controllers\MovedPageController;
use Illuminate\Support\Facades\Route;

/*
 * The old Flux settings pages are gone (plan Phase 6). Their addresses still work: each sends the
 * player to the matching tab of /account. Names are kept so any old link still resolves.
 */
Route::middleware(['auth'])->group(function () {
    Route::get('settings', MovedPageController::class)->defaults('to', 'account')->defaults('tab', 'profile');
    Route::get('settings/profile', MovedPageController::class)->defaults('to', 'account')->defaults('tab', 'profile')->name('profile.edit');
    Route::get('settings/appearance', MovedPageController::class)->defaults('to', 'account')->defaults('tab', 'profile')->name('appearance.edit');
    Route::get('settings/security', MovedPageController::class)->defaults('to', 'account')->defaults('tab', 'security')->name('security.edit');
});
