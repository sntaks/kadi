<?php

namespace App\Livewire;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

class NotificationsBell extends Component
{
    public bool $open = false;

    /** `legacy` for the old layouts, `shell` for the site shell (Night table trigger and panel). */
    #[Locked]
    public string $variant = 'legacy';

    public function show(): void
    {
        $this->open = true;
    }

    public function hide(): void
    {
        $this->open = false;
    }

    public function markAllAsRead(): void
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function clearAll(): void
    {
        Auth::user()->notifications()->delete();
    }

    public function markAsRead(string $id): void
    {
        $notification = Auth::user()->notifications()->whereKey($id)->first();

        if ($notification && $notification->read_at === null) {
            $notification->markAsRead();
        }
    }

    public function clear(string $id): void
    {
        Auth::user()->notifications()->whereKey($id)->delete();
    }

    public function render(): Factory|View
    {
        $notifications = Auth::user()
            ->notifications()
            ->latest()
            ->take(20)
            ->get();

        $unreadCount = Auth::user()->unreadNotifications()->count();

        return view('livewire.notifications-bell', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'unreadLabel' => $unreadCount > 99 ? '99+' : (string) $unreadCount,
        ]);
    }
}
