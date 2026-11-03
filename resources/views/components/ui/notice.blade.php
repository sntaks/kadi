{{-- Full-width notice strip under the top bar (phone not confirmed, e-mail not verified). Optional `action` slot. --}}
<div role="status" {{ $attributes->class('nt-notice') }}>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="7" y="2.5" width="10" height="19" rx="2.5"/><path d="M11 18.5h2"/></svg>
    <span>{{ $slot }}</span>
    {{ $action ?? '' }}
</div>
