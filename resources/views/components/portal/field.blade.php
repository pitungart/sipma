@props([
    'name',
    'label',
    'type' => 'text',
    'autocomplete' => null,
    'hint' => null,
    // Tombol ikon mata untuk menampilkan/menyembunyikan isi field password
    'revealable' => false,
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

    <div class="relative mt-1" @if ($revealable) x-data="{ revealed: false }" @endif>
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $type }}"
            @if ($revealable) x-bind:type="revealed ? 'text' : 'password'" @endif
            wire:model.blur="{{ $model }}"
            @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            aria-invalid="{{ $hasError ? 'true' : 'false' }}"
            {{ $attributes->class(['sipma-input', 'pe-11' => $revealable, 'sipma-input-invalid' => $hasError]) }}
        >

        @if ($revealable)
            {{-- R-3.10: target sentuh 44px; label & aria-pressed mengikuti status --}}
            <button
                type="button"
                class="sipma-focus absolute inset-y-0 end-0 grid w-11 place-items-center rounded-control text-ink-muted hover:text-ink"
                aria-controls="{{ $id }}"
                aria-label="{{ __('portal.password.show') }}"
                aria-pressed="false"
                x-on:click="revealed = ! revealed"
                x-bind:aria-pressed="revealed.toString()"
                x-bind:aria-label="revealed ? @js(__('portal.password.hide')) : @js(__('portal.password.show'))"
            >
                <span x-show="! revealed">@svg('heroicon-o-eye', 'h-5 w-5', ['aria-hidden' => 'true'])</span>
                <span x-show="revealed" x-cloak>@svg('heroicon-o-eye-slash', 'h-5 w-5', ['aria-hidden' => 'true'])</span>
            </button>
        @endif
    </div>

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
