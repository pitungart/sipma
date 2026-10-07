@props([
    'name',
    'label',
    'options' => [],
    'placeholder' => null,
    'autocomplete' => null,
    'hint' => null,
])

@php
    $model = 'form.'.$name;
    $id = 'form-'.$name;
    $hintId = $id.'-hint';
    $errorId = $id.'-error';
    $hasError = $errors->has($model);
    $describedBy = trim(($hint ? $hintId : '').' '.($hasError ? $errorId : ''));
@endphp

<div>
    <label for="{{ $id }}" class="block text-small font-medium text-ink">{{ $label }}</label>

    <select
        id="{{ $id }}"
        name="{{ $name }}"
        wire:model.blur="{{ $model }}"
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        aria-invalid="{{ $hasError ? 'true' : 'false' }}"
        {{ $attributes->class(['sipma-select mt-1', 'sipma-input-invalid' => $hasError]) }}
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach ($options as $value => $text)
            <option value="{{ $value }}">{{ $text }}</option>
        @endforeach
    </select>

    @if ($hint)
        <p id="{{ $hintId }}" class="mt-1 text-caption text-ink-muted">{{ $hint }}</p>
    @endif

    {{-- R-3.5: live region selalu ada agar pesan baru diumumkan pembaca layar --}}
    <div aria-live="polite">
        @error($model)
            <x-portal.error :id="$errorId" :message="$message" />
        @enderror
    </div>
</div>
