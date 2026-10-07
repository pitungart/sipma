@php
    use App\Enums\NumberSegmentType;
    use App\Enums\NumberSeparator;
    use App\Enums\NumberType;
    use App\Models\NumberFormat;

    $type = $this->activeType();
    $format = $this->formats[$type->value];
    $preview = $this->previewNumber($type->value);
    $last = $this->lastIssued();
    $base = "formats.{$type->value}";
    $dirty = $this->isDirty($type->value);
    $short = __("numbering.types.{$type->value}.short");
@endphp

<x-filament-panels::page>
    {{-- Jenis nomor: kartu pilihan, masing-masing dengan pratinjau kecil --}}
    <div class="sipma-num-types" role="tablist" aria-label="{{ __('numbering.types_label') }}">
        @foreach (NumberType::cases() as $option)
            @php $isActive = $option === $type; @endphp
            <button
                type="button"
                role="tab"
                aria-selected="{{ $isActive ? 'true' : 'false' }}"
                wire:click="selectType('{{ $option->value }}')"
                @class(['sipma-card sipma-num-type', 'is-active' => $isActive])
            >
                <span class="sipma-tile sipma-tone-primary"><x-filament::icon :icon="$option->icon()" class="sipma-tile-icon" /></span>
                <span class="sipma-min-0">
                    <span class="sipma-num-type-label">{{ $option->getLabel() }}</span>
                    <span class="sipma-num-type-preview">{{ $this->previewNumber($option->value) ?? '—' }}</span>
                </span>
                @if ($this->isDirty($option->value))
                    <span class="sipma-num-type-dirty" title="{{ __('numbering.unsaved') }}"><span class="sr-only">{{ __('numbering.unsaved') }}</span></span>
                @endif
            </button>
        @endforeach
    </div>

    <section class="sipma-card sipma-clip" wire:key="editor-{{ $type->value }}">
        {{-- Kepala kartu (gelap): jenis + kapan terbit di kiri, pratinjau langsung di kanan --}}
        <div class="sipma-num-head">
            {{-- Latar abstrak: shape berwarna yang ditumpuk lalu diburamkan (dekoratif) --}}
            <div class="sipma-num-aura" aria-hidden="true">
                @foreach (range(1, 9) as $shape)
                    <span class="sipma-num-shape is-{{ $shape }}"></span>
                @endforeach
            </div>

            <div class="sipma-min-0">
                <div class="sipma-num-head-meta">
                    <span class="sipma-num-head-badge">
                        <x-filament::icon :icon="$type->icon()" class="sipma-num-head-badge-icon" />
                        {{ \Illuminate\Support\Str::ucfirst($short) }}
                    </span>
                    <span class="sipma-num-head-when">{{ $type->issuedWhen() }}</span>
                </div>
                <h2 class="sipma-num-head-title">{{ $type->getLabel() }}</h2>
            </div>

            <div class="sipma-num-live">
                <div class="sipma-num-live-top">
                    <span>{{ __('numbering.preview_title') }}</span>
                    <span class="sipma-num-live-badge"><span class="sipma-num-live-dot"></span>{{ __('numbering.live') }}</span>
                </div>
                <div @class(['sipma-num-live-number', 'is-empty' => ! $preview])>{{ $preview ?? __('numbering.incomplete') }}</div>
                <div class="sipma-num-live-foot">
                    <span>{{ __('numbering.fields.last') }}</span>
                    <span @class(['sipma-num-live-last', 'is-none' => ! $last])>{{ $last ?? __('numbering.none') }}</span>
                </div>
            </div>
        </div>

        {{-- Pemisah --}}
        <div class="sipma-num-block">
            <div class="sipma-num-block-head">
                <div>
                    <h3 class="sipma-card-title">{{ __('numbering.fields.separator') }}</h3>
                    <p class="sipma-card-desc">{{ __('numbering.separator_hint') }}</p>
                </div>
            </div>
            <div class="sipma-num-seps" role="radiogroup" aria-label="{{ __('numbering.fields.separator') }}">
                @foreach (NumberSeparator::cases() as $separator)
                    @php $checked = $format['separator'] === $separator->value; @endphp
                    <button
                        type="button"
                        role="radio"
                        aria-checked="{{ $checked ? 'true' : 'false' }}"
                        wire:click="setSeparator('{{ $separator->value }}')"
                        @class(['sipma-num-sep', 'is-active' => $checked])
                    >
                        <span class="sipma-num-sep-glyph">{{ match ($separator) { NumberSeparator::Space => '␣', NumberSeparator::None => '∅', default => $separator->character() } }}</span>
                        {{ $separator->getLabel() }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Atribut --}}
        <div class="sipma-num-block">
            <div class="sipma-num-block-head">
                <div>
                    <h3 class="sipma-card-title">{{ __('numbering.fields.segments') }}</h3>
                    <p class="sipma-card-desc">{{ __('numbering.fields.segments_hint') }}</p>
                </div>
                <div class="sipma-num-tools">
                    <x-filament::button color="gray" size="sm" icon="lucide-rotate-ccw" wire:click="resetToDefault">
                        {{ __('numbering.reset_default') }}
                    </x-filament::button>

                    <x-filament::dropdown placement="bottom-end">
                        <x-slot name="trigger">
                            <x-filament::button size="sm" icon="lucide-plus" :disabled="count($format['segments']) >= NumberFormat::MAX_SEGMENTS">
                                {{ __('numbering.add_segment') }}
                            </x-filament::button>
                        </x-slot>

                        <x-filament::dropdown.list>
                            @foreach (NumberSegmentType::cases() as $segmentType)
                                @php $taken = $segmentType === NumberSegmentType::Sequence && $this->hasSequence(); @endphp
                                <x-filament::dropdown.list.item
                                    :icon="$segmentType->icon()"
                                    :icon-color="$segmentType->tone()"
                                    :disabled="$taken"
                                    :tooltip="$taken ? __('numbering.sequence_taken') : null"
                                    wire:click="addSegment('{{ $segmentType->value }}')"
                                    x-on:click="close"
                                >
                                    {{ $segmentType->getLabel() }}
                                </x-filament::dropdown.list.item>
                            @endforeach
                        </x-filament::dropdown.list>
                    </x-filament::dropdown>
                </div>
            </div>

            <div class="sipma-alert sipma-tone-warning sipma-num-rules" role="note">
                <x-filament::icon icon="lucide-triangle-alert" class="sipma-alert-icon" />
                <div>
                    <b>{{ __('numbering.rules.title') }}</b>
                    <ul>
                        @foreach (__('numbering.rules.items') as $rule)
                            <li>{{ $rule }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>

            @if (blank($format['segments']))
                <div class="sipma-num-empty">{{ __('numbering.no_segments') }}</div>
            @else
                <ol class="sipma-num-rows">
                    @foreach ($format['segments'] as $i => $segment)
                        @php
                            $segmentType = NumberSegmentType::from($segment['type']);
                            $key = "{$base}.segments.{$i}";
                            $example = $this->segmentExample($segment);
                        @endphp
                        <li class="sipma-num-row" wire:key="{{ $type->value }}-{{ $i }}-{{ $segment['type'] }}">
                            <span class="sipma-num-index">{{ $i + 1 }}</span>

                            <div class="sipma-num-kind sipma-tone-{{ $segmentType->tone() }}">
                                <span class="sipma-tile sipma-tile-sm"><x-filament::icon :icon="$segmentType->icon()" class="sipma-tile-icon" /></span>
                                <div class="sipma-min-0">
                                    <div class="sipma-num-kind-name">{{ $segmentType->getLabel() }}</div>
                                    <div class="sipma-num-kind-example">{{ $example !== '' ? $example : '…' }}</div>
                                </div>
                            </div>

                            <div class="sipma-num-fields">
                                @switch($segmentType)
                                    @case(NumberSegmentType::Text)
                                        <label class="sipma-num-field is-wide">
                                            <span class="sr-only">{{ __('numbering.fields.value') }}</span>
                                            <x-filament::input.wrapper :valid="! $errors->has($key.'.value')">
                                                <x-filament::input type="text" maxlength="30" placeholder="{{ __('numbering.text_placeholder') }}" wire:model.live.debounce.400ms="{{ $key }}.value" />
                                            </x-filament::input.wrapper>
                                        </label>
                                        @break

                                    @case(NumberSegmentType::Sequence)
                                        <label class="sipma-num-field">
                                            <span class="sr-only">{{ __('numbering.fields.length') }}</span>
                                            <x-filament::input.wrapper>
                                                <x-filament::input.select wire:model.live="{{ $key }}.length">
                                                    @foreach (range(1, 8) as $n)
                                                        <option value="{{ $n }}">{{ __('numbering.fields.digits', ['n' => $n, 'example' => str_pad('1', $n, '0', STR_PAD_LEFT)]) }}</option>
                                                    @endforeach
                                                </x-filament::input.select>
                                            </x-filament::input.wrapper>
                                        </label>
                                        <label class="sipma-num-field">
                                            <span class="sr-only">{{ __('numbering.fields.reset') }}</span>
                                            <x-filament::input.wrapper>
                                                <x-filament::input.select wire:model.live="{{ $key }}.reset">
                                                    @foreach (NumberFormat::RESETS as $reset)
                                                        <option value="{{ $reset }}">{{ __("numbering.resets.{$reset}") }}</option>
                                                    @endforeach
                                                </x-filament::input.select>
                                            </x-filament::input.wrapper>
                                        </label>
                                        <label class="sipma-num-field is-narrow" title="{{ __('numbering.fields.starts_at_hint') }}">
                                            <span class="sr-only">{{ __('numbering.fields.starts_at') }}</span>
                                            <x-filament::input.wrapper :prefix="__('numbering.fields.starts_at')" :valid="! $errors->has($key.'.starts_at')">
                                                <x-filament::input type="number" min="1" wire:model.live.debounce.400ms="{{ $key }}.starts_at" />
                                            </x-filament::input.wrapper>
                                        </label>
                                        @break

                                    @case(NumberSegmentType::Year)
                                        <label class="sipma-num-field">
                                            <span class="sr-only">{{ __('numbering.fields.digits_label') }}</span>
                                            <x-filament::input.wrapper>
                                                <x-filament::input.select wire:model.live="{{ $key }}.digits">
                                                    @foreach ([4 => 'Y', 2 => 'y'] as $digits => $pattern)
                                                        <option value="{{ $digits }}">{{ __("numbering.year_formats.{$digits}", ['example' => now()->format($pattern)]) }}</option>
                                                    @endforeach
                                                </x-filament::input.select>
                                            </x-filament::input.wrapper>
                                        </label>
                                        @break

                                    @case(NumberSegmentType::Month)
                                        <label class="sipma-num-field">
                                            <span class="sr-only">{{ __('numbering.fields.style') }}</span>
                                            <x-filament::input.wrapper>
                                                <x-filament::input.select wire:model.live="{{ $key }}.style">
                                                    @foreach (NumberFormat::MONTH_STYLES as $style)
                                                        <option value="{{ $style }}">{{ __("numbering.month_formats.{$style}", ['example' => \App\Support\Numbering::part(['type' => 'month', 'style' => $style], 1, now())]) }}</option>
                                                    @endforeach
                                                </x-filament::input.select>
                                            </x-filament::input.wrapper>
                                        </label>
                                        @break
                                @endswitch

                                @foreach (['value', 'length', 'reset', 'starts_at', 'digits', 'style'] as $field)
                                    @error($key.'.'.$field)
                                        <p class="sipma-num-error" role="alert">{{ $message }}</p>
                                    @enderror
                                @endforeach
                            </div>

                            <div class="sipma-num-actions">
                                @foreach ([['up', 'lucide-arrow-up', $i === 0, "moveSegment({$i}, -1)"], ['down', 'lucide-arrow-down', $loop->last, "moveSegment({$i}, 1)"], ['remove', 'lucide-trash-2', false, "removeSegment({$i})"]] as [$act, $icon, $off, $call])
                                    @php $label = __("numbering.{$act}"); @endphp
                                    <button
                                        type="button"
                                        @class(['sipma-num-act', 'is-danger' => $act === 'remove'])
                                        wire:click="{{ $call }}"
                                        aria-label="{{ $label }}"
                                        x-tooltip="{ content: @js($label), theme: $store.theme }"
                                        @disabled($off)
                                    >
                                        <x-filament::icon :icon="$icon" class="sipma-num-act-icon" />
                                    </button>
                                @endforeach
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif

            @error($base.'.segments')
                <p class="sipma-num-error is-block" role="alert">{{ $message }}</p>
            @enderror
        </div>

        {{-- Kaki kartu: status simpan + Simpan, langkah terakhir setelah menyusun --}}
        <div @class(['sipma-num-foot', 'is-dirty' => $dirty])>
            <p class="sipma-num-foot-status" aria-live="polite">
                <x-filament::icon :icon="$dirty ? 'lucide-circle-dot' : 'lucide-circle-check'" class="sipma-num-foot-icon" />
                {{ $dirty ? __('numbering.status_dirty', ['type' => $short]) : __('numbering.status_clean', ['type' => $short]) }}
            </p>
            <x-filament::button
                wire:click="confirmSave"
                :icon="$dirty ? 'lucide-save' : 'lucide-check'"
                :color="$dirty ? 'primary' : 'gray'"
                :disabled="! $dirty"
            >
                {{ $dirty ? __('numbering.save_type', ['type' => $short]) : __('numbering.saved_state') }}
            </x-filament::button>
        </div>
    </section>

    {{-- Yang perlu diketahui sebelum mengubah format --}}
    <div class="sipma-card sipma-num-facts">
        @foreach (['issued' => ['lucide-shield-check', 'success'], 'realtime' => ['lucide-zap', 'info'], 'paper' => ['lucide-notebook-pen', 'primary']] as $fact => [$icon, $tone])
            <div class="sipma-num-fact">
                <span class="sipma-tile sipma-tone-{{ $tone }}"><x-filament::icon :icon="$icon" class="sipma-tile-icon" /></span>
                <div>
                    <div class="sipma-num-fact-title">{{ __("numbering.facts.{$fact}.title") }}</div>
                    <p class="sipma-num-fact-body">{{ __("numbering.facts.{$fact}.body") }}</p>
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
