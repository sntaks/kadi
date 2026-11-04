{{--
    A Night table form field for the sign-in forms: label, input, optional hint, and the field's error.
    `form` (login, join, forgot): the sheet holds all three forms, so a field shows an error only when its
    own form was the one sent.
--}}
@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null, 'id' => null, 'form' => null])
@php
    $id ??= 'f-'.$name.'-'.\Illuminate\Support\Str::random(4);
    $sent = session('signin_form');
    $error = ($form === null || $sent === null || $sent === $form) ? $errors->first($name) : null;
@endphp
<div class="nt-field">
    <label for="{{ $id }}" class="text-[13px] font-semibold text-soft">{{ $label }}</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" @if ($value !== null) value="{{ $value }}" @endif
           @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
           {{ $attributes }}>
    @if ($hint)<span class="nt-hint">{{ $hint }}</span>@endif
    @if ($error)<span id="{{ $id }}-error" class="nt-error" role="alert">{{ $error }}</span>@endif
</div>
