{{--
    Atur posisi foto profil (seperti Instagram): geser untuk memusatkan wajah, zoom dengan slider
    atau scroll. Yang disimpan hanya fokusnya ({x, y, zoom, ratio}); berkas foto tidak dipotong.
    Rumus ukuran/letak sama dengan App\Support\AvatarFocus::style() — ubah keduanya bersamaan.
--}}
<div
    data-focus="{{ json_encode($focus) }}"
    x-data="{
        x: 0.5, y: 0.5, zoom: 1, ratio: 1, ready: false, saving: false, drag: null,
        maxZoom: {{ \App\Support\AvatarFocus::MAX_ZOOM }},
        reset() {
            const f = JSON.parse(this.$el.dataset.focus || 'null');
            this.x = f?.x ?? 0.5;
            this.y = f?.y ?? 0.5;
            this.zoom = f?.zoom ?? 1;
            this.saving = false;
            this.clamp();
        },
        loaded(img) {
            this.ratio = img.naturalWidth / img.naturalHeight;
            this.ready = true;
            this.clamp();
        },
        get w() { return (this.ratio >= 1 ? this.ratio : 1) * 100 * this.zoom },
        get h() { return (this.ratio >= 1 ? 1 : 1 / this.ratio) * 100 * this.zoom },
        clamp() {
            this.zoom = Math.min(Math.max(+this.zoom, 1), this.maxZoom);
            this.x = Math.min(Math.max(this.x, 50 / this.w), 1 - 50 / this.w);
            this.y = Math.min(Math.max(this.y, 50 / this.h), 1 - 50 / this.h);
        },
        get style() {
            return `width:${this.w}%;height:${this.h}%;left:${50 - this.x * this.w}%;top:${50 - this.y * this.h}%`;
        },
        start(e) {
            this.drag = { px: e.clientX, py: e.clientY, x: this.x, y: this.y };
            e.currentTarget.setPointerCapture(e.pointerId);
        },
        move(e) {
            if (! this.drag) return;
            const frame = this.$refs.frame.getBoundingClientRect().width;
            this.x = this.drag.x - (e.clientX - this.drag.px) / (frame * this.w / 100);
            this.y = this.drag.y - (e.clientY - this.drag.py) / (frame * this.h / 100);
            this.clamp();
        },
        nudge(dx, dy) {
            this.x += dx * 0.02 / this.zoom;
            this.y += dy * 0.02 / this.zoom;
            this.clamp();
        },
        wheel(e) {
            this.zoom = +this.zoom - e.deltaY * 0.002;
            this.clamp();
        },
        save() {
            this.saving = true;
            $wire.saveAvatarFocus(this.x, this.y, +this.zoom, this.ratio);
        },
    }"
    x-on:open-modal.window="if ($event.detail.id === 'avatar-editor') reset()"
    {{-- Foto baru tersimpan meski jendela ditutup tanpa Simpan: muat ulang agar sidebar ikut berganti --}}
    x-on:modal-closed.window="if ($event.detail.id === 'avatar-editor' && ! saving) window.location.reload()"
>
    <x-filament::modal id="avatar-editor" width="md" :close-by-clicking-away="false" :footer-actions-alignment="\Filament\Support\Enums\Alignment::End">
        <x-slot name="heading">{{ __('profile.photo.adjust') }}</x-slot>
        <x-slot name="description">{{ __('profile.photo.adjust_desc') }}</x-slot>

        <div class="sipma-avatar-editor">
            <div
                class="sipma-avatar-editor-stage"
                x-on:pointerdown="start($event)"
                x-on:pointermove="move($event)"
                x-on:pointerup="drag = null"
                x-on:pointercancel="drag = null"
                x-on:wheel.prevent="wheel($event)"
                x-on:keydown.arrow-left.prevent="nudge(-1, 0)"
                x-on:keydown.arrow-right.prevent="nudge(1, 0)"
                x-on:keydown.arrow-up.prevent="nudge(0, -1)"
                x-on:keydown.arrow-down.prevent="nudge(0, 1)"
                x-bind:class="{ 'is-dragging': drag }"
                tabindex="0"
                role="application"
                aria-label="{{ __('profile.photo.stage_label') }}"
            >
                <div class="sipma-avatar-editor-frame" x-ref="frame">
                    @if ($src)
                        <img src="{{ $src }}" wire:key="avatar-editor-{{ md5($src) }}" alt="" draggable="false" x-on:load="loaded($el)" x-init="$el.complete && $el.naturalWidth && loaded($el)" x-bind:style="style" x-show="ready">
                    @endif
                </div>
                <x-filament::loading-indicator class="sipma-avatar-editor-busy" x-show="! ready" />
            </div>

            <div class="sipma-avatar-editor-zoom">
                <x-filament::icon icon="lucide-zoom-out" class="h-4 w-4" />
                <input
                    type="range"
                    min="1"
                    x-bind:max="maxZoom"
                    step="0.01"
                    x-model="zoom"
                    x-on:input="clamp()"
                    aria-label="{{ __('profile.photo.zoom') }}"
                >
                <x-filament::icon icon="lucide-zoom-in" class="h-4 w-4" />
            </div>

            <div class="sipma-avatar-editor-preview">
                <span class="sipma-avatar-editor-preview-label">{{ __('profile.photo.preview') }}</span>
                @foreach (['xl', 'lg', 'sm'] as $size)
                    <span class="sipma-avatar sipma-avatar-photo sipma-avatar-{{ $size }}">
                        @if ($src)
                            <img src="{{ $src }}" alt="" x-bind:style="style" x-show="ready">
                        @endif
                    </span>
                @endforeach
            </div>
        </div>

        <x-slot name="footerActions">
            {{-- Rata kanan dirender terbalik oleh Filament: yang pertama tampil paling kanan --}}
            <x-filament::button x-on:click="save()" x-bind:disabled="! ready || saving">
                {{ __('profile.photo.save') }}
            </x-filament::button>
            <x-filament::button color="gray" x-on:click="zoom = 1; x = 0.5; y = 0.5; clamp()">
                {{ __('profile.photo.center') }}
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</div>
