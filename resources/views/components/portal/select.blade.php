{{--
    Select portal: dropdown kustom (bukan <select> bawaan browser), seragam dengan select
    panel Filament. Kolom cari otomatis muncul bila opsi > 10 (mis. 250 negara).
    Pola ARIA combobox + listbox: panah atas/bawah memindah sorotan, Enter memilih, Esc menutup.
    Nilai disinkronkan ke Livewire saat dipilih (setara wire:model.live → validasi langsung).
--}}
@props([
    'name',
    'label',
    'options' => [],
    'placeholder' => null,
    'hint' => null,
])

@php
    $model = 'form.'.$name;
    $id = 'form-'.$name;
    $hintId = $id.'-hint';
    $errorId = $id.'-error';
    $listId = $id.'-listbox';
    $hasError = $errors->has($model);
    $describedBy = trim(($hint ? $hintId : '').' '.($hasError ? $errorId : ''));
    $items = collect($options)->map(fn ($text, $value): array => ['value' => (string) $value, 'label' => (string) $text])->values();
@endphp

<div>
    <label for="{{ $id }}" class="block text-small font-medium text-ink">{{ $label }}</label>

    <div
        x-data="{
            options: @js($items),
            value: $wire.entangle(@js($model)).live,
            searchable: @js($items->count() > 10),
            open: false,
            query: '',
            active: 0,
            get filtered() {
                const q = this.query.trim().toLowerCase();
                return q === '' ? this.options : this.options.filter(o => o.label.toLowerCase().includes(q));
            },
            get selected() {
                return this.options.find(o => o.value === String(this.value ?? '')) ?? null;
            },
            show() {
                this.open = true;
                this.query = '';
                this.active = Math.max(0, this.filtered.findIndex(o => o.value === String(this.value ?? '')));
                this.$nextTick(() => { this.searchable ? this.$refs.search.focus() : this.$refs.list.focus(); this.reveal(); });
            },
            close(refocus = false) {
                this.open = false;
                if (refocus) this.$refs.button.focus();
            },
            move(step) {
                if (! this.filtered.length) return;
                this.active = (this.active + step + this.filtered.length) % this.filtered.length;
                this.reveal();
            },
            reveal() {
                this.$nextTick(() => this.$refs.list.querySelector('[data-active]')?.scrollIntoView({ block: 'nearest' }));
            },
            choose(option) {
                if (option) this.value = option.value;
                this.close(true);
            },
        }"
        x-on:click.outside="close()"
        x-on:keydown.escape="if (open) { $event.stopPropagation(); close(true) }"
        class="relative mt-1"
    >
        <button
            type="button"
            id="{{ $id }}"
            x-ref="button"
            role="combobox"
            aria-haspopup="listbox"
            aria-controls="{{ $listId }}"
            x-bind:aria-expanded="open.toString()"
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            aria-invalid="{{ $hasError ? 'true' : 'false' }}"
            x-on:click="open ? close() : show()"
            x-on:keydown.arrow-down.prevent="show()"
            x-on:keydown.arrow-up.prevent="show()"
            {{ $attributes->class(['sipma-select flex items-center text-start', 'sipma-input-invalid' => $hasError]) }}
        >
            <span class="truncate" x-bind:class="selected ? 'text-ink' : 'text-ink-subtle'" x-text="selected?.label ?? @js($placeholder ?? '')">{{ $placeholder }}</span>
        </button>

        <div
            x-show="open"
            x-cloak
            x-transition.opacity.duration.100ms
            class="sipma-select-panel"
        >
            <template x-if="searchable">
                <div class="border-b border-line p-2">
                    <input
                        type="text"
                        x-ref="search"
                        x-model="query"
                        x-on:input="active = 0"
                        x-on:keydown.arrow-down.prevent="move(1)"
                        x-on:keydown.arrow-up.prevent="move(-1)"
                        x-on:keydown.enter.prevent="choose(filtered[active])"
                        x-on:keydown.tab="close()"
                        role="searchbox"
                        aria-controls="{{ $listId }}"
                        x-bind:aria-activedescendant="filtered[active] ? @js($id.'-opt-') + filtered[active].value : null"
                        placeholder="{{ __('portal.select.search') }}"
                        aria-label="{{ __('portal.select.search') }}"
                        autocomplete="off"
                        class="sipma-input h-10"
                    >
                </div>
            </template>

            <ul
                id="{{ $listId }}"
                x-ref="list"
                role="listbox"
                tabindex="-1"
                aria-labelledby="{{ $id }}"
                x-on:keydown.arrow-down.prevent="move(1)"
                x-on:keydown.arrow-up.prevent="move(-1)"
                x-on:keydown.enter.prevent="choose(filtered[active])"
                x-on:keydown.tab="close()"
                x-bind:aria-activedescendant="filtered[active] ? @js($id.'-opt-') + filtered[active].value : null"
                class="max-h-64 overflow-y-auto p-1 focus:outline-none"
            >
                <template x-for="(option, index) in filtered" x-bind:key="option.value">
                    <li
                        role="option"
                        x-bind:id="@js($id.'-opt-') + option.value"
                        x-bind:aria-selected="(option.value === String(value ?? '')).toString()"
                        x-bind:data-active="index === active ? '' : null"
                        x-on:click="choose(option)"
                        x-on:mousemove="active = index"
                        class="sipma-select-option"
                    >
                        <span class="truncate" x-text="option.label"></span>
                        <svg x-show="option.value === String(value ?? '')" class="h-4 w-4 shrink-0 text-primary" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 10 3.5 3.5L15 7" /></svg>
                    </li>
                </template>

                <li x-show="filtered.length === 0" class="px-3 py-2 text-small text-ink-muted">{{ __('portal.select.empty') }}</li>
            </ul>
        </div>
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
