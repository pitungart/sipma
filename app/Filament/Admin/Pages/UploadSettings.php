<?php

namespace App\Filament\Admin\Pages;

use App\Enums\DocumentType;
use App\Filament\Support\FormModal;
use App\Models\FileTemplate;
use App\Support\FileTemplates;
use App\Support\UploadLimits;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/**
 * Sistem → Unggahan berkas (keputusan 26 Oktober 2026): Super Admin mengatur batas ukuran
 * tiap jenis dokumen pendaftar, bukti pembayaran, dan berkas MOU agen tanpa mengubah kode.
 * Satu baris per berkas: pilihan cepat (UploadLimits::PRESETS) atau Custom dalam KB, lalu template
 * berkasnya (unggah / unduh / ganti / hapus, langsung tersimpan tanpa tombol Simpan). Bukti
 * pembayaran tidak punya template. Batas server PHP ditampilkan karena berkas di atasnya ditolak
 * server sebelum sampai ke aplikasi.
 *
 * @property Form $form
 */
class UploadSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'lucide-file-up';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'settings/uploads';

    protected static string $view = 'filament.admin.pages.upload-settings';

    private const CUSTOM = 'custom';

    /** @var array<string, array{preset: string, kb: int|string|null}> */
    public array $data = [];

    /** @var array<string, FileTemplate>|null Template tersimpan, dibaca sekali per request */
    private ?array $templates = null;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.groups.system');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.uploads.title');
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
        return __('admin.uploads.title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.uploads.description');
    }

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [__('admin.groups.system'), __('admin.uploads.title')];
    }

    public function mount(): void
    {
        // Nilai di luar pilihan cepat (mis. 1500 KB) tampil sebagai Custom
        $this->form->fill(collect(UploadLimits::all())->map(fn (int $kb): array => [
            'preset' => in_array($kb, UploadLimits::PRESETS, true) ? (string) $kb : self::CUSTOM,
            'kb' => $kb,
        ])->all());
    }

    public function form(Form $form): Form
    {
        $documents = array_map(
            fn (DocumentType $type): Grid => $this->limitRow($type->value, $type->getLabel(), $this->documentHint($type), withTemplate: true),
            DocumentType::cases(),
        );

        return $form
            ->statePath('data')
            ->schema([
                Section::make(__('admin.uploads.sections.documents'))
                    ->description(__('admin.uploads.sections.documents_hint'))
                    ->schema($documents),
                Section::make(__('admin.uploads.sections.other'))
                    ->description(__('admin.uploads.sections.other_hint'))
                    ->schema([
                        $this->limitRow(UploadLimits::PAYMENT_PROOF, __('admin.uploads.fields.payment_proof'), __('admin.uploads.hints.payment_proof')),
                        $this->limitRow(UploadLimits::MOU, __('admin.uploads.fields.mou'), __('admin.uploads.hints.mou'), withTemplate: true),
                    ]),
            ]);
    }

    /**
     * Satu baris berkas: nama + keterangan, pilihan ukuran (dan isian Custom), lalu template.
     */
    private function limitRow(string $name, string $label, string $hint, bool $withTemplate = false): Grid
    {
        return Grid::make(['default' => 1, 'md' => 12])
            ->statePath($name)
            ->extraAttributes(['class' => 'sipma-limit-row'])
            ->schema([
                Placeholder::make('info')
                    ->hiddenLabel()
                    ->content(new HtmlString('<span class="sipma-limit-name">'.e($label).'</span><span class="sipma-limit-hint">'.e($hint).'</span>'))
                    ->columnSpan(['md' => 4]),
                Grid::make(2)
                    ->columnSpan(['md' => 4])
                    ->schema([
                        Select::make('preset')
                            ->label(__('admin.uploads.size_of', ['name' => $label]))
                            ->hiddenLabel()
                            ->options(collect(UploadLimits::PRESETS)
                                ->mapWithKeys(fn (int $kb): array => [(string) $kb => UploadLimits::label($kb)])
                                ->put(self::CUSTOM, __('admin.uploads.custom'))
                                ->all())
                            ->selectablePlaceholder(false)
                            ->live()
                            ->required(),
                        TextInput::make('kb')
                            ->label(__('admin.uploads.custom_of', ['name' => $label]))
                            ->hiddenLabel()
                            ->visible(fn (Get $get): bool => $get('preset') === self::CUSTOM)
                            ->helperText(fn (?string $state): ?string => is_numeric($state) ? '= '.UploadLimits::label((int) $state) : null)
                            ->numeric()
                            ->integer()
                            ->minValue(UploadLimits::MIN_KB)
                            ->maxValue(UploadLimits::MAX_KB)
                            ->suffix('KB')
                            ->live(onBlur: true)
                            ->required(),
                    ]),
                Group::make($withTemplate ? $this->templateCell($name, $label) : [
                    Placeholder::make('template')
                        ->hiddenLabel()
                        ->content(new HtmlString('<span class="sipma-limit-hint">'.e(__('admin.uploads.templates.not_used')).'</span>')),
                ])
                    ->extraAttributes(['class' => 'sipma-limit-template'])
                    ->columnSpan(['md' => 4]),
            ]);
    }

    /**
     * Kolom template: berkas yang berlaku (atau "belum ada"), lalu Unggah/Ganti, Unduh, Hapus.
     *
     * @return list<Component>
     */
    private function templateCell(string $name, string $label): array
    {
        $template = fn (): ?FileTemplate => $this->templates()[$name] ?? null;
        // MOU selalu bisa diunduh: template bawaan dipakai selama belum ada unggahan
        $downloadable = fn (): bool => $template() !== null || $name === FileTemplates::MOU;

        return [
            Placeholder::make('template')
                ->hiddenLabel()
                ->content(fn (): HtmlString => $this->templateStatus($template(), $name)),
            Actions::make([
                FormModal::apply(FormAction::make('uploadTemplate'))
                    ->label(fn (): string => $template() ? __('admin.uploads.templates.replace') : __('admin.uploads.templates.upload'))
                    ->icon(fn (): string => $template() ? 'lucide-refresh-cw' : 'lucide-upload')
                    ->color(fn (): string => $template() ? 'gray' : 'primary')
                    ->size('sm')
                    ->modalHeading(fn (): string => ($template() ? __('admin.uploads.templates.replace') : __('admin.uploads.templates.upload')).' · '.$label)
                    ->modalDescription(fn (): string => $template()
                        ? __('admin.uploads.templates.replace_hint', ['file' => $template()->original_name])
                        : __('admin.uploads.templates.upload_hint'))
                    ->modalIcon('lucide-file-up')
                    ->modalWidth(MaxWidth::Large)
                    ->modalSubmitActionLabel(__('admin.master.save'))
                    ->form([
                        FileUpload::make('file')
                            ->label(__('admin.uploads.templates.file'))
                            ->helperText(__('admin.uploads.templates.rules', ['max' => UploadLimits::label(FileTemplates::MAX_KB)]))
                            ->acceptedFileTypes(FileTemplates::MIME_TYPES)
                            ->maxSize(FileTemplates::MAX_KB)
                            ->storeFiles(false) // FileTemplates menyimpannya di disk privat
                            ->required(),
                    ])
                    ->action(function (array $data, FormAction $action) use ($name): void {
                        abort_unless(static::canAccess(), 403);

                        try {
                            FileTemplates::store($name, $data['file'], Filament::auth()->user());
                        } catch (ValidationException $e) {
                            Notification::make()->danger()->title($e->validator->errors()->first())->send();
                            $action->halt();
                        }

                        $this->templates = null;
                        Notification::make()->success()->title(__('admin.uploads.templates.saved'))->send();
                    }),
                FormAction::make('downloadTemplate')
                    ->label(__('admin.uploads.templates.download'))
                    ->icon('lucide-download')
                    ->color('gray')
                    ->size('sm')
                    ->visible($downloadable)
                    ->url(fn (): string => FileTemplates::url($name)),
                FormAction::make('deleteTemplate')
                    ->label(__('admin.uploads.templates.delete'))
                    ->icon('lucide-trash-2')
                    ->color('danger')
                    ->size('sm')
                    ->link()
                    ->visible(fn (): bool => $template() !== null)
                    ->requiresConfirmation()
                    ->modalHeading(__('admin.uploads.templates.delete_heading', ['name' => $label]))
                    ->modalDescription(fn (): string => $name === FileTemplates::MOU
                        ? __('admin.uploads.templates.delete_mou_hint')
                        : __('admin.uploads.templates.delete_hint'))
                    ->modalSubmitActionLabel(__('admin.uploads.templates.delete'))
                    ->action(function () use ($name): void {
                        abort_unless(static::canAccess(), 403);

                        FileTemplates::delete($name);
                        $this->templates = null;
                        Notification::make()->success()->title(__('admin.uploads.templates.deleted'))->send();
                    }),
            ]),
        ];
    }

    private function templateStatus(?FileTemplate $template, string $name): HtmlString
    {
        if ($template === null) {
            $text = $name === FileTemplates::MOU ? __('admin.uploads.templates.mou_fallback') : __('admin.uploads.templates.none');

            return new HtmlString('<span class="sipma-limit-file is-empty">'.e($text).'</span>');
        }

        $meta = mb_strtoupper(pathinfo($template->file_path, PATHINFO_EXTENSION)).' · '
            .UploadLimits::label((int) ceil($template->file_size / 1024)).' · '
            .__('admin.uploads.templates.updated', ['date' => $template->updated_at->translatedFormat('j M Y')]);

        return new HtmlString('<span class="sipma-limit-file" title="'.e($template->original_name).'">'.e($template->original_name).'</span><span class="sipma-limit-hint">'.e($meta).'</span>');
    }

    /**
     * @return array<string, FileTemplate>
     */
    private function templates(): array
    {
        return $this->templates ??= FileTemplates::all();
    }

    /**
     * Keterangan baris dokumen: format yang diterima, dan "wajib" untuk dokumen wajib.
     */
    private function documentHint(DocumentType $type): string
    {
        $formats = mb_strtoupper(implode(', ', array_unique(array_map(
            fn (string $ext): string => $ext === 'jpeg' ? 'jpg' : $ext,
            $type->extensions(),
        ))));

        return $type->isRequired() ? $formats.' · '.__('admin.uploads.required') : $formats;
    }

    /**
     * Batas yang berlaku per nama, dari pilihan cepat atau isian Custom.
     *
     * @param  array<string, array{preset?: string, kb?: int|string|null}>  $rows
     * @return array<string, int>
     */
    private static function limitsFrom(array $rows): array
    {
        return collect($rows)
            ->map(fn (array $row): ?int => ($row['preset'] ?? null) === self::CUSTOM
                ? (is_numeric($row['kb'] ?? null) ? (int) $row['kb'] : null)
                : (is_numeric($row['preset'] ?? null) ? (int) $row['preset'] : null))
            ->filter()
            ->all();
    }

    /**
     * @return list<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')->label(__('admin.master.save'))->submit('save')->keyBindings(['mod+s']),
        ];
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        UploadLimits::save(self::limitsFrom($this->form->getState()));

        Notification::make()->success()->title(__('admin.uploads.saved'))->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $server = UploadLimits::serverMaxKb();

        return [
            'server' => $server,
            // Isian yang melebihi batas server (dihitung dari isian di layar, belum tentu tersimpan)
            'overServer' => collect(self::limitsFrom($this->data))
                ->filter(fn (int $kb): bool => $kb > $server)
                ->keys()
                ->map(fn (string $name): string => DocumentType::tryFrom($name)?->getLabel() ?? __("admin.uploads.fields.{$name}"))
                ->when($server < FileTemplates::MAX_KB, fn ($names) => $names->push(__('admin.uploads.templates.title')))
                ->values(),
        ];
    }
}
