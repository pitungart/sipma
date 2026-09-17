@use('App\Support\Locale')

{{-- Pemilih bahasa di panel Filament memakai komponen bawaan Filament (R-4.3) --}}
<div class="flex justify-end">
<x-filament::dropdown placement="bottom-end">
    <x-slot name="trigger">
        <x-filament::icon-button
            icon="heroicon-m-language"
            color="gray"
            :label="__('portal.language.label')"
        />
    </x-slot>

    <x-filament::dropdown.list>
        @foreach (Locale::SUPPORTED as $code)
            <x-filament::dropdown.list.item
                tag="a"
                :href="route('locale.switch', $code)"
                :icon="app()->getLocale() === $code ? 'heroicon-m-check' : null"
            >
                <span lang="{{ $code }}">{{ __('portal.language.'.$code) }}</span>
            </x-filament::dropdown.list.item>
        @endforeach
    </x-filament::dropdown.list>
</x-filament::dropdown>
</div>
