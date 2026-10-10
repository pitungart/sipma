<?php

namespace App\Filament\Agent\Pages;

use App\Filament\Agent\Concerns\InteractsWithAgent;
use App\Filament\Support\FormModal;
use App\Models\Mou;
use App\Workflow\AgentOnboarding;
use App\Workflow\MouWorkflow;
use App\Workflow\WorkflowException;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * UC-13 Upload MOU (BPMN A4/A5): status MOU terbaru, unduh template, unggah / unggah ulang
 * setelah ditolak, dan riwayat semua unggahan. Keputusan ada di KUI (UC-25, panel admin).
 */
class ManageMou extends Page
{
    use InteractsWithAgent;

    /**
     * Template sementara (dummy) — ganti berkas ini dengan template resmi dari KUI.
     */
    public const TEMPLATE_PATH = 'templates/mou-template.pdf';

    protected static ?string $navigationIcon = 'lucide-file-signature';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'mou';

    protected static string $view = 'filament.agent.pages.mou';

    public static function getNavigationLabel(): string
    {
        return __('agent.mou.title');
    }

    /**
     * Penanda "perlu tindakan": merah bila ditolak, kuning bila belum diunggah.
     */
    public static function getNavigationBadge(): ?string
    {
        $stage = AgentOnboarding::for(Filament::auth()->user()?->agent)->stage();

        return in_array($stage, [AgentOnboarding::MOU, AgentOnboarding::REJECTED], true) ? '!' : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return AgentOnboarding::for(Filament::auth()->user()?->agent)->stage() === AgentOnboarding::REJECTED ? 'danger' : 'warning';
    }

    public function getTitle(): string
    {
        return __('agent.mou.title');
    }

    public function getSubheading(): ?string
    {
        return __('agent.mou.subheading');
    }

    /**
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('template')
                ->label(__('agent.mou.actions.template'))
                ->icon('lucide-download')
                ->color('gray')
                ->visible(fn (): bool => ! $this->onboarding()->agent?->hasApprovedMou())
                ->action(fn (): BinaryFileResponse => response()->download(
                    resource_path(self::TEMPLATE_PATH),
                    'MOU-template-KUI-Udayana.pdf',
                )),

            Action::make('completeProfile')
                ->label(__('agent.mou.actions.complete_profile'))
                ->icon('lucide-building-2')
                ->visible(fn (): bool => $this->onboarding()->stage() === AgentOnboarding::PROFILE)
                ->url(CompanyProfile::getUrl()),

            FormModal::apply(Action::make('upload'))
                ->label(fn (): string => $this->onboarding()->stage() === AgentOnboarding::REJECTED
                    ? __('agent.mou.actions.reupload')
                    : __('agent.mou.actions.upload'))
                ->icon('lucide-upload')
                ->visible(fn (): bool => $this->onboarding()->canUploadMou())
                ->modalHeading(fn (): string => $this->onboarding()->stage() === AgentOnboarding::REJECTED
                    ? __('agent.mou.actions.reupload')
                    : __('agent.mou.actions.upload'))
                ->modalDescription(__('agent.mou.upload_modal'))
                ->modalIcon('lucide-upload')
                ->modalWidth(MaxWidth::Large)
                ->modalSubmitActionLabel(__('agent.mou.actions.submit'))
                ->form([
                    FileUpload::make('file')
                        ->label(__('agent.mou.file'))
                        ->helperText(implode(' ', __('agent.mou.rules.items')))
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(MouWorkflow::MAX_FILE_SIZE_KB)
                        ->storeFiles(false) // MouWorkflow menyimpannya di disk privat
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $agent = $this->agent();
                    abort_if($agent === null, 403);
                    Gate::authorize('create', Mou::class);

                    try {
                        app(MouWorkflow::class)->submit($agent, $data['file']);
                    } catch (WorkflowException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title(__('agent.mou.submitted'))
                        ->body(__('agent.mou.submitted_body'))
                        ->send();
                }),
        ];
    }

    /**
     * Pratinjau MOU di modal; argumen dari browser selalu dicari di dalam MOU milik agen ini.
     */
    public function previewMouAction(): Action
    {
        return Action::make('previewMou')
            ->label(__('agent.mou.actions.preview'))
            ->icon('lucide-eye')
            ->color('gray')
            ->size('sm')
            ->modalHeading(fn (array $arguments): string => __('agent.mou.history_item', [
                'date' => $this->mou($arguments)->created_at->translatedFormat('j M Y'),
            ]))
            ->modalWidth(MaxWidth::FiveExtraLarge)
            ->modalContent(fn (array $arguments) => view('filament.admin.applicants.preview', [
                'url' => route('files.mou', $this->mou($arguments)),
                'image' => false,
                'title' => 'MOU',
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('admin.applicant.actions.close'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $onboarding = $this->onboarding();

        return [
            'onboarding' => $onboarding,
            'stage' => $onboarding->stage(),
            'latest' => $onboarding->latestMou,
            'history' => $this->history(),
        ];
    }

    /**
     * @return Collection<int, Mou>
     */
    private function history(): Collection
    {
        return $this->agent()?->mous()->latest()->orderByDesc('id')->get() ?? new Collection;
    }

    private function mou(array $arguments): Mou
    {
        $mou = $this->agent()?->mous()->find($arguments['mou'] ?? null);
        abort_if($mou === null, 404);

        return $mou;
    }
}
