{{--
    Invite & Earn in the account area (plan Phase 6): App\Livewire\Referrals\Index, its rules unchanged
    (docs/referrals.md). KadiApi owns the ledger and payouts.
--}}
@php
    $kes = fn ($amount) => 'KES '.number_format((float) $amount, 2);
    $when = fn (?string $at) => $at ? \Illuminate\Support\Carbon::parse($at)->timezone(config('app.timezone'))->format('j M Y') : '';
    $shareText = $share
        ? __('Join me on Kadi and play cards online. Sign up with my code :code: :link (18+ only)', ['code' => $share['code'], 'link' => $share['link']])
        : '';
    $tone = ['pending_verification' => 'warn', 'verified' => 'neutral', 'deposited' => 'ok', 'pending' => 'warn', 'processing' => 'neutral', 'completed' => 'ok', 'failed' => 'hot'];
@endphp
<x-account.frame active="referrals">
    <div class="nt-stack" wire:init="load">
        @if ($successMessage)<p class="nt-ok" role="status" data-test="referral-success">{{ $successMessage }}</p>@endif
        @if ($noticeMessage)<p class="nt-hint" role="status" data-test="referral-notice">{{ $noticeMessage }}</p>@endif

        <x-ui.page-head title="Invite & Earn"
            :description="'Earn KES 10 when a friend you invite confirms their e-mail and phone, and another KES 10 on their first deposit. Withdraw to M-Pesa from KES '.$this->minimumWithdrawal().'.'">
            <x-slot:actions>
                <x-ui.button variant="ghost" size="sm" wire:click="refresh" wire:loading.attr="disabled" wire:target="refresh, load">
                    <span wire:loading.remove wire:target="refresh">Refresh</span><span wire:loading wire:target="refresh">Refreshing…</span>
                </x-ui.button>
            </x-slot:actions>
        </x-ui.page-head>

        @if (! $loaded)
            <x-skeleton.region :label="__('Loading your referrals…')" class="nt-stack">
                <div class="nt-area">
                    <div class="nt-panel nt-card-pad"><x-skeleton class="mb-4 h-6 w-32 rounded" /><x-skeleton class="h-9 w-full rounded" /></div>
                    <div class="nt-panel nt-card-pad"><x-skeleton class="mb-4 h-6 w-36 rounded" /><x-skeleton class="h-9 w-32 rounded" /></div>
                </div>
                <div class="nt-panel nt-card-pad"><x-skeleton.rows :count="3" /></div>
            </x-skeleton.region>
        @elseif (! auth()->user()->linked_id)
            <div class="nt-panel nt-card-pad"><p class="nt-hint">Your account is still being set up. Check back in a few minutes.</p></div>
        @else
            <div class="nt-area">
                {{-- Code, link, share, QR --}}
                <section class="nt-panel nt-card-pad" aria-labelledby="referral-share-title">
                    <h2 id="referral-share-title">Your invite</h2>
                    @if ($share)
                        <div class="mt-4 flex flex-col gap-6 sm:flex-row"
                             x-data="{
                                copied: null,
                                copy(text, what) { navigator.clipboard?.writeText(text).then(() => { this.copied = what; setTimeout(() => this.copied = null, 2000) }); },
                                async shareLink() {
                                    if (navigator.share) { try { await navigator.share({ title: 'Kadi', text: @js($shareText) }); } catch (e) {} }
                                    else { this.copy(@js($shareText), 'text'); }
                                },
                                downloadQr() {
                                    const img = new Image();
                                    img.onload = () => {
                                        const canvas = document.createElement('canvas');
                                        canvas.width = canvas.height = 640;
                                        const ctx = canvas.getContext('2d');
                                        ctx.fillStyle = '#ffffff';
                                        ctx.fillRect(0, 0, 640, 640);
                                        ctx.drawImage(img, 0, 0, 640, 640);
                                        const a = document.createElement('a');
                                        a.href = canvas.toDataURL('image/png');
                                        a.download = 'kadi-invite-{{ $share['code'] }}.png';
                                        a.click();
                                    };
                                    img.src = @js($share['qr_code']);
                                },
                             }">
                            <div class="grid flex-1 gap-4">
                                <div>
                                    <span class="nt-hint">Code</span>
                                    <div class="mt-1 flex flex-wrap items-center gap-3">
                                        <span class="nt-num break-all text-2xl font-extrabold tracking-widest text-gold" data-test="referral-code">{{ $share['code'] }}</span>
                                        <x-ui.button variant="quiet" size="sm" x-on:click="copy(@js($share['code']), 'code')">
                                            <span x-show="copied !== 'code'">Copy</span><span x-show="copied === 'code'" x-cloak>Copied</span>
                                        </x-ui.button>
                                    </div>
                                </div>
                                <div class="nt-field">
                                    <label for="referral-link" class="text-[13px] font-semibold text-soft">Link</label>
                                    <div class="flex flex-col gap-2 min-[380px]:flex-row">
                                        <input id="referral-link" type="text" readonly value="{{ $share['link'] }}" x-on:focus="$el.select()" class="min-w-0 flex-1">
                                        <x-ui.button x-on:click="copy(@js($share['link']), 'link')">
                                            <span x-show="copied !== 'link'">Copy link</span><span x-show="copied === 'link'" x-cloak>Copied</span>
                                        </x-ui.button>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <x-ui.button variant="ghost" size="sm" x-on:click="shareLink()">Share</x-ui.button>
                                    <x-ui.button variant="ghost" size="sm" :href="'https://wa.me/?text='.rawurlencode($shareText)" target="_blank" rel="noopener">WhatsApp</x-ui.button>
                                    <x-ui.button variant="ghost" size="sm" :href="'sms:?&body='.rawurlencode($shareText)">SMS</x-ui.button>
                                </div>
                                <p class="nt-hint" x-show="copied === 'text'" x-cloak>Invite text copied. Paste it anywhere.</p>
                            </div>
                            <div class="flex shrink-0 flex-col items-center gap-2">
                                <img src="{{ $share['qr_code'] }}" alt="QR code for your invite link" width="160" height="160" class="size-40 rounded-control bg-white p-2" data-test="referral-qr">
                                <x-ui.button variant="quiet" size="sm" x-on:click="downloadQr()">Download QR</x-ui.button>
                            </div>
                        </div>
                    @else
                        <p class="nt-error mt-3">Your invite code isn't available right now. Try again shortly.</p>
                    @endif
                </section>

                {{-- Referral wallet and withdrawal --}}
                <section class="nt-panel nt-card-pad" aria-labelledby="referral-wallet-title">
                    <h2 id="referral-wallet-title">Referral wallet</h2>
                    @if ($wallet)
                        <p class="nt-num mt-3 mb-0 text-3xl font-extrabold text-gold" data-test="referral-balance">KES {{ \App\Support\WalletAmount::format($wallet['balance'] ?? 0) }}</p>
                        <p class="nt-hint">Total earned {{ $kes($wallet['total_earned'] ?? 0) }}</p>

                        @if ($confirmingWithdraw)
                            <div class="nt-deposit-state">
                                <p>Withdraw <b class="text-gold">KES {{ number_format((int) $withdrawAmount) }}</b> to your M-Pesa number <b>{{ \App\Services\TextSmsService::mask(auth()->user()->phone) }}</b>?</p>
                                <div class="mt-3 flex gap-2">
                                    <x-ui.button class="flex-1" wire:click="confirmWithdraw" wire:loading.attr="disabled" wire:target="confirmWithdraw" data-test="referral-confirm-withdraw">
                                        <span wire:loading.remove wire:target="confirmWithdraw">Confirm</span><span wire:loading wire:target="confirmWithdraw">Sending…</span>
                                    </x-ui.button>
                                    <x-ui.button variant="ghost" class="flex-1" wire:click="cancelWithdraw" wire:loading.attr="disabled" wire:target="confirmWithdraw">Cancel</x-ui.button>
                                </div>
                            </div>
                        @else
                            <form wire:submit="requestWithdraw" class="mt-5 grid gap-3">
                                <label class="nt-field">
                                    <span>Withdraw to M-Pesa (KES)</span>
                                    <input type="text" inputmode="numeric" wire:model="withdrawAmount" placeholder="{{ $this->minimumWithdrawal() }}" autocomplete="off">
                                </label>
                                <x-ui.button type="submit" block wire:loading.attr="disabled" wire:target="requestWithdraw"
                                    :disabled="! ($wallet['withdrawable'] ?? false) || $this->withdrawableBalance() < $this->minimumWithdrawal()">Withdraw</x-ui.button>
                                <p class="nt-hint">At least KES {{ $this->minimumWithdrawal() }}, whole shillings.</p>
                            </form>
                        @endif
                        @if ($withdrawError)<p class="nt-error mt-3" role="alert" data-test="referral-withdraw-error">{{ $withdrawError }}</p>@endif
                    @else
                        <p class="nt-error mt-3">We couldn't load your referral wallet right now.</p>
                    @endif
                </section>
            </div>

            {{-- Stats and the referral list --}}
            <section class="nt-panel" aria-labelledby="referral-stats-title">
                <div class="nt-list-head">
                    <h2 id="referral-stats-title">Your referrals</h2>
                    <div class="nt-tabs" role="tablist" aria-label="Show">
                        @foreach (['' => 'All'] + $statuses as $key => $label)
                            <button type="button" role="tab" class="nt-tab" aria-selected="{{ $status === $key ? 'true' : 'false' }}"
                                    wire:click="setStatus('{{ $key }}')" wire:key="status-{{ $key ?: 'all' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="px-[22px] max-[640px]:px-4">
                    @if ($stats)
                        <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6" data-test="referral-stats">
                            @foreach ([
                                ['Invited', $stats['referrals']['total'] ?? 0],
                                ['Verified', $stats['referrals']['verified'] ?? 0],
                                ['Deposited', $stats['referrals']['deposited'] ?? 0],
                                ['Pending', $stats['referrals']['pending_verification'] ?? 0],
                                ['Earned', $kes($stats['earned']['total'] ?? 0)],
                                ['This month', $kes($stats['earned']['this_month'] ?? 0)],
                            ] as [$label, $value])
                                <div class="nt-stat rounded-control bg-felt-2 p-3" wire:key="stat-{{ $loop->index }}"><dt><span>{{ $label }}</span></dt><dd class="m-0"><b class="nt-num text-[18px]">{{ $value }}</b></dd></div>
                            @endforeach
                        </dl>
                        <p class="nt-hint mt-3">
                            Today {{ $stats['referrals']['today'] ?? 0 }} · this week {{ $stats['referrals']['this_week'] ?? 0 }} · this month {{ $stats['referrals']['this_month'] ?? 0 }}.
                            Sign-up bonuses {{ $kes($stats['earned']['signup'] ?? 0) }}, first-deposit bonuses {{ $kes($stats['earned']['first_deposit'] ?? 0) }}.
                        </p>
                    @elseif ($statsFailed)
                        <p class="nt-error">We couldn't load your stats right now.</p>
                    @endif
                </div>

                @if ($referralsFailed)
                    <p class="nt-error px-[22px] py-4">We couldn't load your referrals right now.</p>
                @elseif ($referrals === [])
                    <div class="nt-empty px-[22px]"><b>No referrals yet</b>Share your link to get started.</div>
                @else
                    <ul class="nt-list mt-3" data-test="referral-list">
                        @foreach ($referrals as $referral)
                            <li class="nt-row" wire:key="referral-{{ $referral['id'] ?? $loop->index }}">
                                <span class="nt-row-icon" aria-hidden="true"><x-site.icon name="invite" /></span>
                                <span class="min-w-0">
                                    <b>{{ $referral['referred_name'] ?? 'Player' }}</b>
                                    <span class="nt-meta">{{ $referral['referred_phone'] ?? '' }} · joined {{ $when($referral['created_at'] ?? null) }}
                                        · <x-ui.pill :tone="$tone[$referral['status'] ?? ''] ?? 'neutral'">{{ $statuses[$referral['status'] ?? ''] ?? ucfirst((string) ($referral['status'] ?? '')) }}</x-ui.pill></span>
                                </span>
                                <span class="nt-trail nt-num text-gold">{{ $kes($referral['earned'] ?? 0) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @if (($referralsMeta['last_page'] ?? 1) > 1)
                        <div class="nt-list-foot">
                            <span>Page {{ $page }} of {{ $referralsMeta['last_page'] }}</span>
                            <span class="flex gap-2">
                                <x-ui.button variant="ghost" size="sm" wire:click="goToPage({{ $page - 1 }})" :disabled="$page <= 1">Previous</x-ui.button>
                                <x-ui.button variant="ghost" size="sm" wire:click="goToPage({{ $page + 1 }})" :disabled="$page >= ($referralsMeta['last_page'] ?? 1)">Next</x-ui.button>
                            </span>
                        </div>
                    @endif
                @endif
            </section>

            <div class="grid gap-[18px] lg:grid-cols-2">
                <section class="nt-panel nt-card-pad" aria-labelledby="referral-bonuses-title">
                    <h2 id="referral-bonuses-title">Bonuses</h2>
                    @php($bonuses = array_values(array_filter((array) ($wallet['bonuses'] ?? []), 'is_array')))
                    @if ($bonuses === [])
                        <p class="nt-hint mt-2">No bonuses yet.</p>
                    @else
                        <ul class="nt-list mt-2">
                            @foreach ($bonuses as $bonus)
                                <li class="flex justify-between gap-3 border-t border-line py-2 text-sm text-soft" wire:key="bonus-{{ $bonus['id'] ?? $loop->index }}">
                                    <span class="min-w-0">{{ ($bonus['milestone'] ?? '') === 'first_deposit' ? 'First deposit' : 'Sign-up' }} · {{ $bonus['referred_name'] ?? 'Player' }} <span class="nt-hint">{{ $when($bonus['created_at'] ?? null) }}</span></span>
                                    <span class="nt-num shrink-0 font-bold text-win">+{{ $kes($bonus['amount'] ?? 0) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                <section class="nt-panel nt-card-pad" aria-labelledby="referral-withdrawals-title">
                    <h2 id="referral-withdrawals-title">Withdrawals</h2>
                    @if ($withdrawalsFailed)
                        <p class="nt-error mt-2">We couldn't load your withdrawals right now.</p>
                    @elseif ($withdrawals === [])
                        <p class="nt-hint mt-2">No withdrawals yet.</p>
                    @else
                        <ul class="nt-list mt-2" data-test="referral-withdrawals">
                            @foreach ($withdrawals as $withdrawal)
                                <li class="flex justify-between gap-3 border-t border-line py-2 text-sm text-soft" wire:key="withdrawal-{{ $withdrawal['id'] ?? $loop->index }}">
                                    <span class="min-w-0">{{ $kes($withdrawal['amount'] ?? 0) }} <span class="nt-hint">{{ $when($withdrawal['created_at'] ?? null) }}@if (! empty($withdrawal['mpesa_receipt'])) · {{ $withdrawal['mpesa_receipt'] }}@endif</span></span>
                                    <x-ui.pill :tone="$tone[$withdrawal['status'] ?? ''] ?? 'neutral'">{{ $withdrawalStatuses[$withdrawal['status'] ?? ''] ?? ucfirst((string) ($withdrawal['status'] ?? '')) }}</x-ui.pill>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>
        @endif
    </div>
</x-account.frame>
