<?php

namespace App\Filament\Admin\Pages;

use App\Enums\NumberSegmentType;
use App\Enums\NumberSeparator;
use App\Enums\NumberType;
use App\Models\NumberFormat;
use App\Support\Numbering;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

/**
 * Sistem → Penomoran (keputusan 7 Oktober 2026): Super Admin menyusun nomor LOA, pendaftaran,
 * MOU, dan kwitansi dari atribut berurutan (teks, nomor urut, tahun, bulan) dan satu pemisah.
 *
 * Satu jenis nomor diedit dalam satu waktu (kartu jenis di atas). Susunan keempat jenis tetap
 * dipegang di halaman, jadi berpindah kartu tidak membuang perubahan; Simpan hanya menyimpan
 * jenis yang sedang dibuka. Perubahan hanya berlaku untuk nomor berikutnya.
 */
class NumberingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'lucide-hash';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'settings/numbering';

    protected static string $view = 'filament.admin.pages.numbering-settings';

    #[Url(as: 'type')]
    public string $active = 'application';

    /** @var array<string, array{separator: string, segments: list<array<string, mixed>>}> */
    public array $formats = [];

    /** @var array<string, array{separator: string, segments: list<array<string, mixed>>}> Susunan tersimpan, untuk tanda "belum disimpan" */
    public array $saved = [];

    public static function getNavigationGroup(): ?string
    {
        return __('admin.groups.system');
    }

    public static function getNavigationLabel(): string
    {
        return __('numbering.title');
    }

    /**
     * Hanya Super Admin — Admin Fakultas tidak melihat menu dan mendapat 403 lewat URL.
     */
    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->isSuperAdmin() ?? false;
    }

    public function getTitle(): string
    {
        return __('numbering.title');
    }

    public function getSubheading(): ?string
    {
        return __('numbering.description');
    }

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [__('admin.groups.system'), __('numbering.title')];
    }

    public function mount(): void
    {
        foreach (NumberType::cases() as $type) {
            $format = NumberFormat::for($type);
            $this->formats[$type->value] = self::state($format->separator->value, $format->segments);
        }

        $this->saved = $this->formats;
        $this->active = NumberType::tryFrom($this->active)?->value ?? NumberType::Application->value;
    }

    // ── Aksi kepala halaman (Simpan ada di kaki kartu, mengikuti alur atas → bawah) ──

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('simulate')
                ->label(__('numbering.simulate'))
                ->icon('lucide-flask-conical')
                ->color('gray')
                ->modalHeading(fn (): string => __('numbering.simulation.heading', ['type' => $this->activeType()->getLabel()]))
                ->modalDescription(__('numbering.simulation.description'))
                ->modalContent(fn () => view('filament.admin.pages.numbering-simulation', [
                    'rows' => $this->isComplete() ? Numbering::upcoming($this->activeType(), $this->draft(), 5) : [],
                ]))
                ->modalWidth(MaxWidth::Medium)
                ->modalSubmitAction(false)
                ->modalFooterActionsAlignment(Alignment::End)
                ->modalCancelActionLabel(__('numbering.simulation.close')),
        ];
    }

    // ── Penyuntingan susunan jenis aktif ──────────────────────────

    public function selectType(string $type): void
    {
        $this->active = NumberType::from($type)->value;
        $this->resetErrorBag();
    }

    public function setSeparator(string $separator): void
    {
        $this->formats[$this->active]['separator'] = NumberSeparator::from($separator)->value;
    }

    public function addSegment(string $type): void
    {
        $segmentType = NumberSegmentType::from($type);
        $segments = $this->formats[$this->active]['segments'];

        if (count($segments) >= NumberFormat::MAX_SEGMENTS || ($segmentType === NumberSegmentType::Sequence && $this->hasSequence())) {
            return;
        }

        $this->formats[$this->active]['segments'][] = $segmentType->defaults();
    }

    public function removeSegment(int $index): void
    {
        $segments = $this->formats[$this->active]['segments'];
        unset($segments[$index]);
        $this->formats[$this->active]['segments'] = array_values($segments);
        $this->resetErrorBag();
    }

    public function moveSegment(int $index, int $direction): void
    {
        $segments = $this->formats[$this->active]['segments'];
        $target = $index + ($direction < 0 ? -1 : 1);

        if (! isset($segments[$index], $segments[$target])) {
            return;
        }

        [$segments[$index], $segments[$target]] = [$segments[$target], $segments[$index]];
        $this->formats[$this->active]['segments'] = $segments;
        $this->resetErrorBag();
    }

    /**
     * Kembalikan jenis aktif ke susunan bawaan (seeder); baru tersimpan setelah Simpan.
     */
    public function resetToDefault(): void
    {
        $default = $this->activeType()->defaultFormat();
        $this->formats[$this->active] = self::state($default['separator']->value, $default['segments']);
        $this->resetErrorBag();
    }

    /**
     * Tombol Simpan di kaki kartu: periksa isian dulu (galat tampil di tempatnya), baru buka
     * konfirmasi yang memperlihatkan nomor berikutnya sebelum dan sesudah perubahan.
     */
    public function confirmSave(): void
    {
        $this->validateActive();
        $this->mountAction('save');
    }

    public function saveAction(): Action
    {
        return Action::make('save')
            ->requiresConfirmation()
            ->icon('lucide-save')
            ->modalIcon('lucide-save')
            ->modalHeading(fn (): string => __('numbering.confirm.heading', ['type' => __("numbering.types.{$this->active}.short")]))
            ->modalDescription(fn (): HtmlString => new HtmlString(__('numbering.confirm.body', [
                'old' => '<b class="sipma-num-mono">'.e(Numbering::preview($this->activeType())).'</b>',
                'new' => '<b class="sipma-num-mono is-new">'.e($this->previewNumber($this->active) ?? '—').'</b>',
            ])))
            ->modalSubmitActionLabel(__('numbering.confirm.submit'))
            ->modalCancelActionLabel(__('numbering.confirm.cancel'))
            ->action(fn () => $this->save());
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $type = $this->activeType();
        $segments = $this->validateActive();

        NumberFormat::query()->updateOrCreate(['type' => $type], [
            'separator' => $this->formats[$type->value]['separator'],
            'segments' => $segments,
        ]);

        $this->formats[$type->value] = self::state($this->formats[$type->value]['separator'], $segments);
        $this->saved[$type->value] = $this->formats[$type->value];

        Notification::make()
            ->success()
            ->title(__('numbering.saved', ['type' => $type->getLabel()]))
            ->body(__('numbering.saved_body'))
            ->send();
    }

    /**
     * Validasi susunan jenis aktif; mengembalikan atribut yang sudah dirapikan.
     *
     * @return list<array<string, mixed>>
     */
    private function validateActive(): array
    {
        abort_unless(static::canAccess(), 403);

        $type = $this->activeType();
        $this->validate(...$this->rules($type));

        $segments = NumberFormat::normalize($this->formats[$type->value]['segments']);
        $key = "formats.{$type->value}.segments";

        if (collect($segments)->where('type', NumberSegmentType::Sequence->value)->count() !== 1) {
            throw ValidationException::withMessages([$key => __('numbering.errors.sequence_count')]);
        }

        if (mb_strlen(Numbering::preview($type, $this->draft())) > 100) {
            throw ValidationException::withMessages([$key => __('numbering.errors.too_long')]);
        }

        return $segments;
    }

    // ── Data untuk view ───────────────────────────────────────────

    public function activeType(): NumberType
    {
        return NumberType::from($this->active);
    }

    public function hasSequence(?string $type = null): bool
    {
        return collect($this->formats[$type ?? $this->active]['segments'] ?? [])
            ->contains('type', NumberSegmentType::Sequence->value);
    }

    public function isDirty(string $type): bool
    {
        return self::state($this->formats[$type]['separator'], $this->formats[$type]['segments'])
            !== self::state($this->saved[$type]['separator'], $this->saved[$type]['segments']);
    }

    /**
     * Susunan cukup lengkap untuk dipratinjau: tepat satu nomor urut dan tidak ada teks kosong.
     */
    public function isComplete(?string $type = null): bool
    {
        $segments = collect($this->formats[$type ?? $this->active]['segments'] ?? []);

        return $segments->isNotEmpty()
            && $segments->where('type', NumberSegmentType::Sequence->value)->count() === 1
            && $segments->every(fn (array $s): bool => $s['type'] !== NumberSegmentType::Text->value || filled(trim((string) ($s['value'] ?? ''))));
    }

    public function previewNumber(string $type): ?string
    {
        return $this->isComplete($type) ? Numbering::preview(NumberType::from($type), $this->draft($type)) : null;
    }

    /**
     * Contoh hasil satu atribut, mis. tahun 4 digit → 2026, nomor urut 4 digit → 0009.
     *
     * @param  array<string, mixed>  $segment
     */
    public function segmentExample(array $segment): string
    {
        $value = $segment['type'] === NumberSegmentType::Sequence->value && $this->isComplete()
            ? Numbering::upcoming($this->activeType(), $this->draft())[0]['value']
            : max(1, (int) ($segment['starts_at'] ?? 1));

        return Numbering::part($segment, $value, now());
    }

    public function lastIssued(): ?string
    {
        $type = $this->activeType();
        [$table, $column] = $type->column();

        // Diurutkan menurut waktu terbit, bukan waktu ubah terakhir
        return DB::table($table)
            ->whereNotNull($column)
            ->orderByDesc($type->issuedAtColumn())
            ->orderByDesc($column)
            ->value($column);
    }

    // ── Bantuan ───────────────────────────────────────────────────

    private function draft(?string $type = null): NumberFormat
    {
        $type ??= $this->active;

        return new NumberFormat([
            'type' => $type,
            'separator' => NumberSeparator::from($this->formats[$type]['separator']),
            'segments' => NumberFormat::normalize($this->formats[$type]['segments']),
        ]);
    }

    /**
     * @param  iterable<array<string, mixed>>  $segments
     * @return array{separator: string, segments: list<array<string, mixed>>}
     */
    private static function state(string $separator, iterable $segments): array
    {
        return ['separator' => $separator, 'segments' => NumberFormat::normalize($segments)];
    }

    /**
     * Aturan validasi jenis aktif; isian tiap atribut mengikuti tipenya.
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>, 2: array<string, string>}
     */
    private function rules(NumberType $type): array
    {
        $base = "formats.{$type->value}";
        $rules = [
            "{$base}.separator" => ['required', Rule::enum(NumberSeparator::class)],
            "{$base}.segments" => ['required', 'array', 'min:1', 'max:'.NumberFormat::MAX_SEGMENTS],
        ];
        $attributes = [];

        foreach ($this->formats[$type->value]['segments'] as $i => $segment) {
            $key = "{$base}.segments.{$i}";
            $rules["{$key}.type"] = ['required', Rule::enum(NumberSegmentType::class)];

            $fields = match (NumberSegmentType::tryFrom((string) ($segment['type'] ?? ''))) {
                NumberSegmentType::Text => ['value' => ['required', 'string', 'max:30']],
                NumberSegmentType::Sequence => [
                    'length' => ['required', 'integer', 'between:1,8'],
                    'reset' => ['required', Rule::in(NumberFormat::RESETS)],
                    'starts_at' => ['required', 'integer', 'between:1,99999999'],
                ],
                NumberSegmentType::Year => ['digits' => ['required', Rule::in([2, 4])]],
                NumberSegmentType::Month => ['style' => ['required', Rule::in(NumberFormat::MONTH_STYLES)]],
                default => [],
            };

            foreach ($fields as $field => $fieldRules) {
                $rules["{$key}.{$field}"] = $fieldRules;
                $attributes["{$key}.{$field}"] = __("numbering.fields.{$field}");
            }
        }

        return [$rules, [], $attributes];
    }
}
