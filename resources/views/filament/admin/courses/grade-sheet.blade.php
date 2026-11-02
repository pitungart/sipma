{{-- Lembar nilai per MK: satu pilihan nilai per mahasiswa, disimpan sekaligus --}}
<x-filament-panels::page>
    @if ($this->rows()->isEmpty())
        <section class="sipma-card sipma-section">
            <x-sipma.empty icon="lucide-clipboard-pen" :title="__('admin.student_course.sheet.empty')" :description="__('admin.student_course.sheet.empty_hint')" />
        </section>
    @else
        <form wire:submit="save" class="sipma-card sipma-section">
            {{ $this->form }}

            <div class="sipma-actions-inline sipma-sheet-actions">
                <x-filament::button tag="a" :href="\App\Filament\Admin\Resources\CourseResource::getUrl()" color="gray">
                    {{ __('admin.master.cancel') }}
                </x-filament::button>
                <x-filament::button type="submit">{{ __('admin.master.save') }}</x-filament::button>
            </div>
        </form>
    @endif
</x-filament-panels::page>
