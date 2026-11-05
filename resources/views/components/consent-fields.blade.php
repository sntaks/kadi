@php
    $minAge = config('kadi.min_age');
    $box = 'mt-0.5 h-4 w-4 shrink-0 accent-gold';
    $text = 'flex items-start gap-3 text-sm text-soft';
    $link = 'text-gold underline';
    $error = 'nt-error -mt-1';
@endphp

<div class="flex flex-col gap-3">
    <label class="{{ $text }}">
        <input type="checkbox" name="age_confirmed" value="1" required
               @checked(old('age_confirmed'))
               class="{{ $box }}" />
        <span>{{ __('I confirm that I am :age years or older.', ['age' => $minAge]) }}</span>
    </label>
    @error('age_confirmed')
        <p class="{{ $error }}" role="alert">{{ $message }}</p>
    @enderror

    <label class="{{ $text }}">
        <input type="checkbox" name="terms" value="1" required
               @checked(old('terms'))
               class="{{ $box }}" />
        <span>
            {{ __('I accept the') }}
            <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener" class="{{ $link }}">{{ __('Terms & Conditions') }}</a>
            {{ __('and') }}
            <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener" class="{{ $link }}">{{ __('Privacy Policy') }}</a>.
        </span>
    </label>
    @error('terms')
        <p class="{{ $error }}" role="alert">{{ $message }}</p>
    @enderror
</div>
