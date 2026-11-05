{{-- The "Report" button for a reportable game or round while its report window is open, or its report state once reported. --}}
@props(['reportKey' => null, 'state' => null, 'labels' => [], 'expiresAt' => null])

@php
    $windowOpen = $reportKey && \App\Support\PlayedGame::reportWindowOpen($expiresAt);
    $deadline = $expiresAt ? \Illuminate\Support\Carbon::parse($expiresAt) : null;
    // Counted from the server's clock, so a wrong phone clock cannot open or close the window early.
    $secondsLeft = $windowOpen ? max(0, (int) now()->diffInSeconds($deadline, false)) : 0;
@endphp

@if ($state)
    <span class="rounded-full border border-amber-600/60 bg-amber-900/30 px-2.5 py-1 text-xs text-amber-400">
        {{ $labels[$state] ?? 'Reported' }}
    </span>
@elseif ($windowOpen)
    {{-- The window is short, so the time left counts down live; the server checks again on open and on filing. --}}
    <span
        x-data="{
            left: {{ $secondsLeft }},
            timer: null,
            get label() {
                if (this.left >= 3600) return @js($deadline->diffForHumans(['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 1]).' left');
                return Math.floor(this.left / 60) + ':' + String(this.left % 60).padStart(2, '0') + ' left';
            },
        }"
        x-init="timer = setInterval(() => { left > 0 ? left-- : clearInterval(timer) }, 1000)"
        x-on:remove="clearInterval(timer)"
        class="flex items-center gap-2"
        data-seconds-left="{{ $secondsLeft }}"
    >
        <span x-show="left > 0" class="flex items-center gap-2">
            <span class="text-xs tabular-nums text-ash" title="You can report until {{ $deadline->format('j M Y, H:i:s') }}" x-text="label">
                {{ $deadline->diffForHumans(['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 1]) }} left
            </span>
            <button
                type="button"
                wire:click="openReport('{{ $reportKey }}')"
                class="rounded-full border border-line px-3 py-1 text-xs font-semibold text-ivory/80 transition hover:border-gold/60 hover:text-gold"
            >
                Report
            </button>
        </span>
        <span x-show="left <= 0" x-cloak class="text-xs text-ash">Reporting closed</span>
    </span>
@elseif ($reportKey)
    <span class="text-xs text-ash" @if ($deadline) title="Reporting closed {{ $deadline->format('j M Y, H:i') }}" @endif>
        Reporting closed
    </span>
@endif
