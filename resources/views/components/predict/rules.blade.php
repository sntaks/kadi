{{-- What decides the market (shown once: imported markets repeat it as their criteria), when, stakes and sources. --}}
@props(['market', 'criteria' => null])
<section {{ $attributes->class('nt-panel nt-pm-text') }} aria-label="Market rules">
    <h2 class="nt-pm-h2">Market rules</h2>
    @if ($market->description)<p>{!! nl2br(e($market->description)) !!}</p>@endif
    @if ($criteria)<p>{!! nl2br(e($criteria)) !!}</p>@endif
    <dl class="nt-pm-facts">
        @if ($market->resolutionDeadlineAt)<div><dt>Decided by</dt><dd class="nt-num">{{ $market->resolutionDeadlineAt->timezone($market->timezone)->format('D j M Y') }}</dd></div>@endif
        @if ($market->minStake !== null && $market->maxStake !== null)<div><dt>Stake</dt><dd class="nt-num">{{ \App\Predict\Format::kes($market->minStake) }} to {{ \App\Predict\Format::kes($market->maxStake) }}</dd></div>@endif
        @if ($market->resolutionSource)
            <div><dt>Source</dt><dd>
                @if ($market->resolutionSourceUrl())
                    <a href="{{ $market->resolutionSourceUrl() }}" target="_blank" rel="noopener nofollow">Polymarket <x-site.icon name="external" /></a>
                @else
                    {{ $market->resolutionSource }}
                @endif
            </dd></div>
        @endif
    </dl>
    @if ($market->externalUrl)
        <a href="{{ $market->externalUrl }}" target="_blank" rel="noopener nofollow" class="nt-pm-ext">View this market on Polymarket <x-site.icon name="external" /></a>
    @endif
</section>
