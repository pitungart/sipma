<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\ActivityDescriber;
use App\Support\AvatarFocus;
use App\Support\NotificationPreferences;
use App\Support\PrivateFiles;
use App\Support\UserSessions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\EditProfile;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Profil saya (semua panel): kartu identitas + hak akses di kiri, tab Informasi pribadi,
 * Keamanan, Preferensi, dan Aktivitas di kanan. Hanya memakai data akun yang memang ada;
 * biodata mahasiswa & data perusahaan agen tetap diubah di halamannya masing-masing.
 *
 * @property Form $form
 * @property Form $passwordForm
 */
class Profile extends EditProfile
{
    use WithFileUploads;

    /** Batas foto profil (KB); PHP lokal membatasi unggahan 2 MB. */
    public const PHOTO_MAX_KB = 2048;

    protected static string $view = 'filament.pages.profile';

    /** @var array<string, mixed>|null */
    public ?array $passwordData = [];

    public ?TemporaryUploadedFile $photo = null;

    public static function getLabel(): string
    {
        return __('profile.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('profile.title');
    }

    public function getSubheading(): ?string
    {
        return __('profile.subheading');
    }

    public function getUser(): User
    {
        /** @var User */
        return parent::getUser();
    }

    /**
     * @return array<int|string, string|Form>
     */
    protected function getForms(): array
    {
        return [
            'form' => $this->form($this->makeForm()
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name')
                            ->label(__('profile.info.name'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('email')
                            ->label(__('profile.info.email'))
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->live(onBlur: true),
                        // Mengganti email login wajib membuktikan pemilik akun
                        TextInput::make('current_password')
                            ->label(__('profile.info.current_password'))
                            ->helperText(__('profile.info.current_password_hint'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->currentPassword()
                            ->visible(fn (Get $get): bool => $get('email') !== $this->getUser()->email)
                            ->dehydrated(false),
                        TextInput::make('role_label')
                            ->label(__('profile.info.role'))
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText(__('profile.info.managed')),
                        TextInput::make('unit_label')
                            ->label(__('profile.info.unit'))
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (): bool => $this->getUser()->panelId() === 'admin'),
                    ]),
                ])
                ->operation('edit')
                ->model($this->getUser())
                ->statePath('data')),
            'passwordForm' => $this->makeForm()
                ->schema([
                    TextInput::make('current_password')
                        ->label(__('profile.security.current_password'))
                        ->password()
                        ->revealable()
                        ->required()
                        ->currentPassword()
                        ->autocomplete('current-password')
                        ->columnSpanFull(),
                    TextInput::make('password')
                        ->label(__('profile.security.new_password'))
                        ->password()
                        ->revealable()
                        ->required()
                        ->rule(Password::min(8))
                        ->different('current_password')
                        ->same('password_confirmation')
                        ->autocomplete('new-password')
                        ->extraInputAttributes(['x-on:input' => 'password = $event.target.value']),
                    TextInput::make('password_confirmation')
                        ->label(__('profile.security.confirm_password'))
                        ->password()
                        ->revealable()
                        ->required()
                        ->autocomplete('new-password'),
                ])
                ->columns(2)
                ->statePath('passwordData'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $user = $this->getUser();

        return [
            'name' => $user->name,
            'email' => $user->email,
            'role_label' => $this->roleLabel(),
            'unit_label' => $this->unitLabel(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        $emailChanged = $data['email'] !== $record->email;

        $record->fill(['name' => $data['name'], 'email' => $data['email']]);

        // Panel dengan verifikasi email (agen & mahasiswa): alamat baru harus diverifikasi lagi
        if ($emailChanged && Filament::getCurrentPanel()?->hasEmailVerification()) {
            $record->email_verified_at = null;
        }

        $record->save();

        if ($emailChanged && $record->email_verified_at === null) {
            $record->sendEmailVerificationNotification();
        }

        return $record;
    }

    protected function afterSave(): void
    {
        $this->fillForm();
        $this->dispatch('profile-saved');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('profile.info.saved');
    }

    public function resetProfileForm(): void
    {
        $this->resetValidation();
        $this->fillForm();
        $this->dispatch('profile-saved');
    }

    /**
     * Ganti kata sandi. Sesi lain dikeluarkan (AuthenticateSession juga menolak hash lama),
     * sesi ini tetap masuk karena hash barunya disimpan ke session.
     */
    public function updatePassword(): void
    {
        $data = $this->passwordForm->getState();
        $user = $this->getUser();

        $user->forceFill(['password' => Hash::make($data['password'])])->save();
        session()->put('password_hash_'.Filament::getAuthGuard(), $user->getAuthPassword());
        UserSessions::revokeOthers($user);

        $this->passwordForm->fill();
        $this->dispatch('password-updated');

        Notification::make()->success()->title(__('profile.security.password_updated'))->send();
    }

    public function updatedPhoto(): void
    {
        abort_unless($this->canUploadPhoto(), 403);

        $this->validate(
            ['photo' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::PHOTO_MAX_KB]],
            attributes: ['photo' => __('profile.photo.attribute')],
        );

        $user = $this->getUser();
        $old = $user->avatar_path;

        $user->forceFill([
            'avatar_path' => PrivateFiles::store($this->photo, "users/{$user->getKey()}/avatar")['file_path'],
            'avatar_focus' => null,
        ])->save();
        PrivateFiles::delete($old);

        $this->photo = null;

        // Langsung atur posisi wajah di dalam lingkaran (seperti Instagram)
        $this->dispatch('open-modal', id: 'avatar-editor');
    }

    /**
     * Simpan posisi foto di dalam lingkaran (berkasnya tidak dipotong; lihat AvatarFocus).
     */
    public function saveAvatarFocus(float $x, float $y, float $zoom, float $ratio): void
    {
        $user = $this->getUser();
        abort_if(blank($user->avatar_path), 422);

        $user->forceFill(['avatar_focus' => AvatarFocus::normalize(compact('x', 'y', 'zoom', 'ratio'))])->save();

        Notification::make()->success()->title(__('profile.photo.updated'))->send();
        // Muat ulang agar avatar di sidebar & menu profil (di luar komponen ini) ikut berganti
        $this->redirect(static::getUrl(), navigate: false);
    }

    public function removePhoto(): void
    {
        abort_unless($this->canUploadPhoto(), 403);

        $user = $this->getUser();
        PrivateFiles::delete($user->avatar_path);
        $user->forceFill(['avatar_path' => null, 'avatar_focus' => null])->save();

        Notification::make()->success()->title(__('profile.photo.removed'))->send();
        $this->redirect(static::getUrl(), navigate: false);
    }

    public function revokeSession(string $sessionId): void
    {
        UserSessions::revoke($this->getUser(), $sessionId);

        Notification::make()->success()->title(__('profile.security.session_revoked'))->send();
    }

    public function revokeOtherSessions(): void
    {
        UserSessions::revokeOthers($this->getUser());

        Notification::make()->success()->title(__('profile.security.others_revoked'))->send();
    }

    public function toggleNotification(string $key): void
    {
        abort_unless(NotificationPreferences::mailEnabled(), 403);

        $user = $this->getUser();
        $current = NotificationPreferences::for($user)[$key] ?? null;
        abort_if($current === null, 422);

        NotificationPreferences::set($user, $key, ! $current);
    }

    /**
     * Admin & agen mengunggah foto sendiri; foto mahasiswa adalah Pas Foto 3×4 pendaftaran.
     */
    public function canUploadPhoto(): bool
    {
        return $this->getUser()->role !== UserRole::Student;
    }

    /**
     * Tingkat akses ("Super Admin"), bukan label peran sistem: label Super Admin di sistem adalah
     * "Kantor Urusan Internasional", yang sudah tampil sebagai unit kerja.
     */
    public function roleLabel(): string
    {
        return __('profile.card.levels.'.$this->getUser()->role->value);
    }

    /**
     * Super Admin = KUI; Admin Fakultas = fakultasnya.
     */
    public function unitLabel(): string
    {
        $user = $this->getUser();

        return $user->isSuperAdmin() || $user->faculty === null
            ? __('portal.brand.office_name')
            : $user->faculty->name;
    }

    /**
     * Hak akses = menu yang benar-benar bisa dibuka (navigasi Filament sudah disaring Policy).
     *
     * @return list<array{label: string, icon: ?string}>
     */
    public function accessItems(): array
    {
        $home = rtrim(Filament::getUrl(), '/');

        return collect(Filament::getNavigation())
            ->flatMap(fn ($group) => $group->getItems())
            ->reject(fn (NavigationItem $item): bool => rtrim((string) $item->getUrl(), '/') === $home)
            ->map(fn (NavigationItem $item): array => ['label' => (string) $item->getLabel(), 'icon' => $item->getIcon()])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = $this->getUser();

        return [
            'user' => $user,
            'unit' => $this->unitLabel(),
            'access' => $this->accessItems(),
            'sessions' => UserSessions::for($user),
            'preferences' => NotificationPreferences::for($user),
            'mailEnabled' => NotificationPreferences::mailEnabled(),
            'activity' => ActivityDescriber::forCauser($user),
        ];
    }
}
