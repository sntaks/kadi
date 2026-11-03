<div x-data="{ open: false }" @keydown.escape.window="open = false">
    {{-- Bell trigger --}}
    @if ($variant === 'shell')
        <button
            type="button"
            wire:click="show"
            x-on:click="open = true"
            class="nt-icon-btn"
            aria-label="{{ $unreadCount > 0 ? __('Notifications, :count new', ['count' => $unreadLabel]) : __('Notifications') }}"
            :aria-expanded="open"
        >
            <x-site.icon name="bell" />
            @if ($unreadCount > 0)
                <span class="nt-count-badge" aria-hidden="true">{{ $unreadLabel }}</span>
            @endif
        </button>
    @else
    <button
        type="button"
        wire:click="show"
        x-on:click="open = true"
        class="relative flex h-10 w-10 items-center justify-center rounded-full border border-yellow-800/30 text-[#f5f5f0]/70 transition hover:border-[#f5c542]/50 hover:text-[#f5c542]"
        aria-label="{{ __('Notifications') }}"
        :aria-expanded="open"
    >
        <flux:icon.bell variant="outline" class="size-5" />
        @if ($unreadCount > 0)
            <span
                class="absolute -top-1 -right-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-[#f5c542] px-1 text-[10px] font-bold text-black"
            >{{ $unreadLabel }}</span>
        @endif
    </button>

    @endif

    {{-- Backdrop + slide-over panel — teleported to body to escape stacking context --}}
    <template x-teleport="body">
        <div x-cloak x-show="open" class="fixed inset-0 z-[100]">
            <div
                x-show="open"
                x-transition:enter="transition-opacity ease-linear duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition-opacity ease-linear duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                x-on:click="open = false; $wire.hide()"
                class="absolute inset-0 bg-black/60 backdrop-blur-sm"
            ></div>

            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="translate-x-full"
                role="dialog"
                aria-modal="true"
                @if ($variant === 'shell') x-trap.inert.noscroll="open" @endif
                aria-label="{{ __('Notifications') }}"
                @class([
                    'absolute inset-y-0 right-0 flex w-full max-w-sm flex-col shadow-2xl',
                    'nt-bell-panel' => $variant === 'shell',
                    'border-l border-yellow-800/40 bg-[#111111]' => $variant !== 'shell',
                ])
            >
                {{-- Panel header --}}
                <div class="flex h-16 flex-shrink-0 items-center justify-between border-b border-yellow-800/20 px-5">
                    <h2 @class(['nt-bell-title' => $variant === 'shell', 'text-lg font-bold text-[#f5f5f0]' => $variant !== 'shell'])
                        @if ($variant !== 'shell') style="font-family: 'Cinzel', serif;" @endif>
                        {{ __('Notifications') }}
                    </h2>
                    <button
                        type="button"
                        x-on:click="open = false; $wire.hide()"
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-[#6b6b6b] transition hover:bg-[#1a1a1a] hover:text-[#f5c542]"
                        aria-label="{{ __('Close notifications') }}"
                    >
                        <flux:icon.x-mark variant="outline" class="size-5" />
                    </button>
                </div>

                {{-- Notification list --}}
                <div class="flex-1 divide-y divide-yellow-800/10 overflow-y-auto">
                    @if ($notifications->isEmpty())
                        <div class="flex h-full flex-col items-center justify-center gap-3 px-6 py-12 text-center">
                            <flux:icon.check-badge variant="outline" class="size-10 text-[#6b6b6b]" />
                            <p class="text-sm text-[#6b6b6b]">{{ __('You are all caught up.') }}</p>
                        </div>
                    @else
                        @foreach ($notifications as $notification)
                            <div
                                wire:key="notification-{{ $notification->id }}"
                                @class([
                                    'relative flex items-start gap-3 px-5 py-4 transition',
                                    'bg-[#161616]/60' => $notification->read_at === null,
                                ])
                            >
                                {{-- Unread dot --}}
                                <span
                                    class="mt-2 size-2 flex-shrink-0 rounded-full {{
                                        $notification->read_at === null ? 'bg-[#f5c542]' : 'bg-transparent'
                                    }}"
                                    aria-hidden="true"
                                ></span>

                                {{-- Content --}}
                                <div class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium {{ $notification->read_at === null ? 'text-[#f5f5f0]' : 'text-[#f5f5f0]/60' }}">
                                        {{ $notification->data['change'] ?? __('Notification') }}
                                    </span>
                                    <span class="mt-1 block text-xs text-[#6b6b6b]">
                                        {{ $notification->created_at->diffForHumans() }}
                                    </span>
                                </div>

                                {{-- Actions --}}
                                <div class="flex items-center gap-1">
                                    @if ($notification->read_at === null)
                                        <button
                                            type="button"
                                            wire:click="markAsRead('{{ $notification->id }}')"
                                            x-on:click="$wire.markAsRead('{{ $notification->id }}')"
                                            class="flex h-7 w-7 items-center justify-center rounded-md text-[#6b6b6b] transition hover:bg-[#1a1a1a] hover:text-[#f5c542]"
                                            title="{{ __('Mark as read') }}"
                                        >
                                            <flux:icon.check variant="outline" class="size-4" />
                                        </button>
                                    @endif
                                    <button
                                        type="button"
                                        wire:click="clear('{{ $notification->id }}')"
                                        x-on:click="$wire.clear('{{ $notification->id }}')"
                                        class="flex h-7 w-7 items-center justify-center rounded-md text-[#6b6b6b] transition hover:bg-[#1a1a1a] hover:text-red-400"
                                        title="{{ __('Clear') }}"
                                    >
                                        <flux:icon.x-mark variant="outline" class="size-4" />
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

                {{-- Panel footer --}}
                @if (! $notifications->isEmpty())
                <div class="flex-shrink-0 border-t border-yellow-800/20 px-5 py-3">
                    <div class="flex items-center gap-4">
                        <button
                            type="button"
                            wire:click="markAllAsRead"
                            wire:loading.attr="disabled"
                            wire:target="markAllAsRead"
                            class="text-sm font-semibold text-[#f5c542] transition hover:text-[#ffde74] disabled:opacity-50"
                        >
                            {{ __('Mark all as read') }}
                        </button>
                        <span class="text-[#6b6b6b]">|</span>
                        <button
                            type="button"
                            wire:click="clearAll"
                            wire:loading.attr="disabled"
                            wire:target="clearAll"
                            class="text-sm font-semibold text-[#6b6b6b] transition hover:text-red-400 disabled:opacity-50"
                        >
                            {{ __('Clear all') }}
                        </button>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </template>
</div>
