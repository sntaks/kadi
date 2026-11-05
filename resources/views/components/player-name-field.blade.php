{{--
    Player name input with live client-side validation. Mirrors App\Support\PlayerName (4-12 chars;
    letters, digits, single spaces and _ ! - only); the server re-checks everything. Extra attributes (wire:model,
    autofocus...) go to the input. Pass `locked` to show the field read-only with a reason.
--}}
@props([
    'name' => 'name',
    'label' => null,
    'value' => '',
    'locked' => false,
    'lockedMessage' => null,
    'placeholder' => null,
])

@php
    $min = \App\Support\PlayerName::MIN;
    $max = \App\Support\PlayerName::MAX;
    $pattern = '[A-Za-z0-9_!\-]+( [A-Za-z0-9_!\-]+)*';
@endphp

<div
    x-data="{
        v: @js((string) $value),
        get error() {
            if (this.v === '') return '';
            if (! /^([A-Za-z0-9_!-]+( [A-Za-z0-9_!-]+)*)?$/.test(this.v)) return @js(\App\Support\PlayerName::CHARS_MESSAGE);
            if (this.v.length < {{ $min }} || this.v.length > {{ $max }}) return @js(\App\Support\PlayerName::LENGTH_MESSAGE);
            return '';
        },
    }"
>
    <flux:input
        :name="$name"
        :label="$label ?? __('Player name')"
        :value="$value"
        :placeholder="$placeholder"
        :disabled="$locked"
        type="text"
        required
        minlength="{{ $min }}"
        maxlength="{{ $max }}"
        :pattern="$pattern"
        title="{{ __(':min to :max characters: letters, numbers, spaces and _ ! -', ['min' => $min, 'max' => $max]) }}"
        autocomplete="nickname"
        autocapitalize="off"
        spellcheck="false"
        x-on:input="v = $event.target.value"
        x-bind:aria-invalid="error !== ''"
        {{ $attributes }}
    />

    @if ($locked)
        <p class="mt-1 text-xs text-ash">{{ $lockedMessage }}</p>
    @else
        <p x-show="error === ''" class="mt-1 text-xs text-ash">
            {{ __(':min to :max characters. Letters, numbers, spaces and _ ! - only; no emojis.', ['min' => $min, 'max' => $max]) }}
        </p>
        <p x-show="error !== ''" x-text="error" x-cloak role="alert" class="mt-1 text-xs text-hot"></p>
    @endif
</div>
