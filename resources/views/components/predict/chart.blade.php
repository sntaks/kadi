{{--
    The YES chance over time, 0 to 100%, as an inline SVG line drawn on the server (no chart library). `points` come
    from App\Predict\Markets::history() (oldest first); `current` is the chance now, added as the last point so the
    line ends where the buttons are. live.js moves that last point when the feed broadcasts a new figure. The time
    axis is in the market's timezone.
--}}
@props(['points', 'current' => null, 'now', 'marketId' => null, 'timezone' => 'Africa/Nairobi'])
@php
    $width = 600;
    $height = 200;
    $series = collect($points)->map(fn ($p) => ['t' => \Carbon\CarbonImmutable::parse($p['at'])->getTimestamp(), 'y' => $p['yes']]);

    if ($current !== null) {
        $series->push(['t' => max($now->getTimestamp(), (int) $series->max('t')), 'y' => $current]);
    }

    $first = (int) $series->min('t');
    $span = max(1, (int) $series->max('t') - $first);
    $xy = $series->map(fn ($p) => [
        round(($p['t'] - $first) / $span * $width, 1),
        round((1 - min(1, max(0, $p['y']))) * $height, 1),
    ]);

    // A single point draws as a flat line across.
    if ($xy->count() === 1) {
        $xy->prepend([0, $xy[0][1]]);
    }

    $line = $xy->map(fn ($p) => $p[0].','.$p[1])->implode(' ');
    $area = '0,'.$height.' '.$line.' '.$width.','.$height;
    $last = $xy->last();
    $from = \App\Predict\Format::percent($series->first()['y'] ?? null);
    $to = \App\Predict\Format::percent($series->last()['y'] ?? null);
    // Four times along the bottom: hours within two days, dates beyond.
    $format = $span < 2 * 86400 ? 'H:i' : 'j M';
    $ticks = $series->count() > 1 && $span > 60
        ? collect([0, 1 / 3, 2 / 3, 1])->map(fn ($f) => [
            'left' => round($f * 100, 2),
            'label' => \Carbon\CarbonImmutable::createFromTimestamp((int) round($first + $f * $span))->timezone($timezone)->format($format),
        ])
        : collect();
@endphp
@if ($series->isEmpty())
    <p {{ $attributes->class('nt-pm-chart-empty') }}>No price history yet.</p>
@else
    <figure {{ $attributes->class('nt-pm-chart') }} data-predict-chart data-market="{{ $marketId }}" data-height="{{ $height }}">
        <div class="nt-pm-plot">
            <svg viewBox="0 0 {{ $width }} {{ $height }}" preserveAspectRatio="none" role="img"
                 aria-label="Chance of Yes over time: from {{ $from }} to {{ $to }}">
                @foreach ([0, 0.5, 1] as $grid)
                    <line x1="0" x2="{{ $width }}" y1="{{ $grid * $height }}" y2="{{ $grid * $height }}" class="nt-pm-gridline" vector-effect="non-scaling-stroke" />
                @endforeach
                <polygon points="{{ $area }}" class="nt-pm-area" />
                <polyline points="{{ $line }}" class="nt-pm-line" data-predict-line vector-effect="non-scaling-stroke" />
            </svg>
            <span class="nt-pm-dot" data-predict-dot style="left: {{ $last[0] / $width * 100 }}%; top: {{ $last[1] / $height * 100 }}%"></span>
        </div>
        <div class="nt-pm-axis" aria-hidden="true"><span>100%</span><span>50%</span><span>0%</span></div>
        @if ($ticks->isNotEmpty())
            <div class="nt-pm-times nt-num" aria-hidden="true">
                @foreach ($ticks as $tick)
                    <span style="left: {{ $tick['left'] }}%">{{ $tick['label'] }}</span>
                @endforeach
            </div>
        @endif
    </figure>
@endif
