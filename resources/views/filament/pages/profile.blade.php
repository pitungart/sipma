{{--
    Profil saya (App\Filament\Pages\Profile): kartu identitas + hak akses di kiri, kartu bertab di kanan.
    Tab aktif disimpan di ?tab= agar bisa ditautkan dan bertahan saat halaman dimuat ulang.
--}}
@php
    use App\Support\Locale;

    $tabs = [
        'info' => ['label' => __('profile.tabs.info'), 'icon' => 'lucide-user-round'],
        'security' => ['label' => __('profile.tabs.security'), 'icon' => 'lucide-lock'],
        'preferences' => ['label' => __('profile.tabs.preferences'), 'icon' => 'lucide-sliders-horizontal'],
        'activity' => ['label' => __('profile.tabs.activity'), 'icon' => 'lucide-activity'],
    ];
    $avatarUrl = $user->avatarUrl();
@endphp

<x-filament-panels::page class="sipma-profile">
    <div class="sipma-profile-grid">
        <aside class="sipma-profile-side">
            {{-- Identitas --}}
            <section class="sipma-card sipma-profile-card">
                <div class="sipma-profile-avatar">
                    <x-sipma.avatar :name="$user->name" :id="$user->getKey()" :src="$avatarUrl" :focus="$user->avatar_focus" size="xl" />

                    <div class="sipma-profile-avatar-busy" wire:loading.flex wire:target="photo, removePhoto">
                        <x-filament::loading-indicator class="h-6 w-6" />
                        <span class="sr-only">{{ __('profile.photo.uploading') }}</span>
                    </div>

                    @if ($this->canUploadPhoto())
                        <input
                            type="file"
                            id="profile-photo-input"
                            class="sr-only"
                            accept="image/jpeg,image/png,image/webp"
                            wire:model="photo"
                            tabindex="-1"
                            aria-hidden="true"
                        >

                        <x-filament::dropdown placement="bottom-start" teleport>
                            <x-slot name="trigger">
                                <button
                                    type="button"
                                    class="sipma-profile-camera"
                                    aria-label="{{ $avatarUrl ? __('profile.photo.change') : __('profile.photo.upload') }}"
                                    title="{{ $avatarUrl ? __('profile.photo.change') : __('profile.photo.upload') }}"
                                >
                                    <x-filament::icon icon="lucide-camera" class="h-4 w-4" />
                                </button>
                            </x-slot>

                            <x-filament::dropdown.list>
                                <x-filament::dropdown.list.item
                                    icon="lucide-image-up"
                                    x-on:click="document.getElementById('profile-photo-input').click(); close()"
                                >
                                    {{ $avatarUrl ? __('profile.photo.change') : __('profile.photo.upload') }}
                                </x-filament::dropdown.list.item>

                                @if ($avatarUrl)
                                    <x-filament::dropdown.list.item
                                        icon="lucide-move"
                                        x-on:click="$dispatch('open-modal', { id: 'avatar-editor' }); close()"
                                    >
                                        {{ __('profile.photo.adjust') }}
                                    </x-filament::dropdown.list.item>

                                    <x-filament::dropdown.list.item
                                        icon="lucide-trash-2"
                                        color="danger"
                                        wire:click="removePhoto"
                                        wire:confirm="{{ __('profile.photo.remove_confirm') }}"
                                    >
                                        {{ __('profile.photo.remove') }}
                                    </x-filament::dropdown.list.item>
                                @endif
                            </x-filament::dropdown.list>

                            <p class="sipma-profile-photo-hint">{{ __('profile.photo.hint') }}</p>
                        </x-filament::dropdown>
                    @endif
                </div>

                {{-- Selalu ada (juga sebelum foto pertama) agar bisa dibuka langsung setelah unggah --}}
                @if ($this->canUploadPhoto())
                    @include('filament.pages.partials.avatar-editor', ['src' => $avatarUrl, 'focus' => $user->avatar_focus])
                @endif

                @error('photo')
                    <p class="sipma-profile-error" role="alert">{{ $message }}</p>
                @enderror

                <h2 class="sipma-profile-name">{{ $user->name }}</h2>
                <p class="sipma-profile-sub">{{ $unit }}</p>

                <div class="sipma-profile-badges">
                    <span class="sipma-status sipma-tone-primary">{{ $this->roleLabel() }}</span>
                    <span class="sipma-status sipma-tone-success"><span class="sipma-status-dot"></span>{{ __('profile.card.active') }}</span>
                </div>

                <ul class="sipma-profile-facts">
                    <li>
                        <x-filament::icon icon="lucide-mail" class="sipma-profile-fact-icon" />
                        <span class="sipma-profile-fact-text">{{ $user->email }}</span>
                    </li>
                    <li>
                        <x-filament::icon icon="lucide-languages" class="sipma-profile-fact-icon" />
                        <span>{{ __('portal.language.'.app()->getLocale()) }}</span>
                    </li>
                    <li>
                        <x-filament::icon icon="lucide-calendar" class="sipma-profile-fact-icon" />
                        <span>{{ __('profile.card.joined', ['date' => $user->created_at->translatedFormat('F Y')]) }}</span>
                    </li>
                </ul>
            </section>

            {{-- Hak akses: menu yang lolos Policy di panel ini --}}
            @if (count($access))
                <section class="sipma-card">
                    <div class="sipma-card-head">
                        <div>
                            <h3 class="sipma-card-title">{{ __('profile.card.access') }}</h3>
                            <p class="sipma-card-desc">{{ __('profile.card.access_desc') }}</p>
                        </div>
                    </div>
                    <ul class="sipma-profile-chips">
                        @foreach ($access as $item)
                            <li class="sipma-profile-chip">
                                @if ($item['icon'])
                                    <x-filament::icon :icon="$item['icon']" class="sipma-profile-chip-icon" />
                                @endif
                                {{ $item['label'] }}
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </aside>

        <div
            class="sipma-card sipma-clip"
            x-data="{
                tab: (() => { const t = new URLSearchParams(location.search).get('tab'); return @js(array_keys($tabs)).includes(t) ? t : 'info' })(),
                show(key) {
                    this.tab = key;
                    const url = new URL(location.href);
                    key === 'info' ? url.searchParams.delete('tab') : url.searchParams.set('tab', key);
                    history.replaceState(history.state, '', url);
                },
            }"
        >
            <nav class="sipma-master-tabs" role="tablist">
                @foreach ($tabs as $key => $tab)
                    <button
                        type="button"
                        role="tab"
                        id="profile-tab-{{ $key }}"
                        aria-controls="profile-panel-{{ $key }}"
                        class="sipma-master-tab"
                        x-on:click="show(@js($key))"
                        x-bind:class="{ 'is-active': tab === @js($key) }"
                        x-bind:aria-selected="(tab === @js($key)).toString()"
                    >
                        <x-filament::icon :icon="$tab['icon']" class="sipma-master-tab-icon" />
                        <span>{{ $tab['label'] }}</span>
                    </button>
                @endforeach
            </nav>

            {{-- Informasi pribadi --}}
            <section
                id="profile-panel-info"
                role="tabpanel"
                aria-labelledby="profile-tab-info"
                x-show="tab === 'info'"
                x-data="{ dirty: false }"
                x-on:input="dirty = true"
                x-on:profile-saved.window="dirty = false"
            >
                <div class="sipma-card-head sipma-card-head-ruled sipma-profile-head">
                    <div>
                        <h3 class="sipma-card-title">{{ __('profile.info.title') }}</h3>
                        <p class="sipma-card-desc">{{ __('profile.info.desc') }}</p>
                    </div>
                </div>

                <form wire:submit="save">
                    <div class="sipma-section">
                        {{ $this->form }}
                    </div>

                    <div class="sipma-profile-foot">
                        <span class="sipma-profile-foot-status" x-text="dirty ? @js(__('profile.info.dirty')) : @js(__('profile.info.clean'))">{{ __('profile.info.clean') }}</span>
                        <div class="sipma-profile-foot-actions">
                            <x-filament::button color="gray" wire:click="resetProfileForm" x-bind:disabled="! dirty">
                                {{ __('profile.info.cancel') }}
                            </x-filament::button>
                            <x-filament::button type="submit" wire:target="save" x-bind:disabled="! dirty">
                                {{ __('profile.info.save') }}
                            </x-filament::button>
                        </div>
                    </div>
                </form>
            </section>

            {{-- Keamanan --}}
            <section id="profile-panel-security" role="tabpanel" aria-labelledby="profile-tab-security" x-show="tab === 'security'" x-cloak>
                <div class="sipma-card-head sipma-card-head-ruled sipma-profile-head">
                    <div>
                        <h3 class="sipma-card-title">{{ __('profile.security.password_title') }}</h3>
                        <p class="sipma-card-desc">{{ __('profile.security.password_desc') }}</p>
                    </div>
                </div>

                <form
                    wire:submit="updatePassword"
                    class="sipma-section"
                    x-data="{
                        password: '',
                        get score() {
                            const p = this.password;
                            if (! p) return 0;
                            let s = p.length >= 8 ? 1 : 0;
                            if (/[a-z]/.test(p) && /[A-Z]/.test(p)) s++;
                            if (/\d/.test(p)) s++;
                            if (/[^A-Za-z0-9]/.test(p)) s++;
                            return Math.max(1, s);
                        },
                        levels: @js(__('profile.security.strength_levels')),
                    }"
                    x-on:password-updated.window="password = ''"
                >
                    {{ $this->passwordForm }}

                    <div class="sipma-profile-password-foot">
                        <div class="sipma-profile-strength" x-bind:data-score="score">
                            <span class="sipma-profile-strength-bars" aria-hidden="true">
                                <i></i><i></i><i></i><i></i>
                            </span>
                            <span class="sipma-profile-strength-label" aria-live="polite">
                                {{ __('profile.security.strength') }}<template x-if="score > 0"><span x-text="': ' + levels[score - 1]"></span></template>
                            </span>
                        </div>

                        <x-filament::button type="submit" wire:target="updatePassword">
                            {{ __('profile.security.update_password') }}
                        </x-filament::button>
                    </div>
                </form>

                <div class="sipma-card-head sipma-profile-head sipma-profile-head-top">
                    <div>
                        <h3 class="sipma-card-title">{{ __('profile.security.sessions_title') }}</h3>
                        <p class="sipma-card-desc">{{ __('profile.security.sessions_desc') }}</p>
                    </div>
                    @if ($sessions->count() > 1)
                        <x-filament::button
                            color="gray"
                            size="sm"
                            icon="lucide-log-out"
                            wire:click="revokeOtherSessions"
                            wire:confirm="{{ __('profile.security.revoke_others_confirm') }}"
                        >
                            {{ __('profile.security.revoke_others') }}
                        </x-filament::button>
                    @endif
                </div>

                <div class="sipma-section sipma-profile-sessions">
                    @forelse ($sessions as $session)
                        <div class="sipma-row" wire:key="session-{{ $session['id'] }}">
                            <span class="sipma-tile sipma-tone-{{ $session['current'] ? 'success' : 'gray' }}">
                                <x-filament::icon :icon="match ($session['device']) { 'mobile' => 'lucide-smartphone', 'tablet' => 'lucide-tablet', default => 'lucide-monitor' }" class="sipma-tile-icon" />
                            </span>
                            <div class="sipma-row-main">
                                <div class="sipma-row-title">
                                    {{ $session['browser'] }} · {{ $session['platform'] }}
                                    @if ($session['current'])
                                        <span class="sipma-status sipma-tone-success">{{ __('profile.security.this_device') }}</span>
                                    @endif
                                </div>
                                <div class="sipma-row-meta">
                                    @if ($session['ip'])
                                        <span class="sipma-mono">{{ $session['ip'] }}</span> ·
                                    @endif
                                    {{ $session['current'] ? __('profile.security.active_now') : __('profile.security.last_active', ['time' => $session['last_active']->diffForHumans()]) }}
                                </div>
                            </div>
                            @unless ($session['current'])
                                <x-filament::button
                                    color="danger"
                                    outlined
                                    size="sm"
                                    wire:click="revokeSession(@js($session['id']))"
                                    wire:confirm="{{ __('profile.security.revoke_confirm') }}"
                                >
                                    {{ __('profile.security.revoke') }}
                                </x-filament::button>
                            @endunless
                        </div>
                    @empty
                        <p class="sipma-row-meta">{{ __('profile.security.no_sessions') }}</p>
                    @endforelse
                </div>
            </section>

            {{-- Preferensi --}}
            <section id="profile-panel-preferences" role="tabpanel" aria-labelledby="profile-tab-preferences" x-show="tab === 'preferences'" x-cloak>
                <div class="sipma-card-head sipma-card-head-ruled sipma-profile-head">
                    <div>
                        <h3 class="sipma-card-title">{{ __('profile.preferences.notifications_title') }}</h3>
                        <p class="sipma-card-desc">{{ __('profile.preferences.notifications_desc') }}</p>
                    </div>
                </div>

                <div class="sipma-section sipma-profile-prefs">
                    @if (! $mailEnabled && count($preferences))
                        <x-sipma.notice tone="warning" icon="lucide-mail-x">{{ __('profile.preferences.mail_disabled') }}</x-sipma.notice>
                    @endif

                    @forelse ($preferences as $key => $enabled)
                        @php
                            [$label, $description] = __('profile.preferences.types.'.$key);
                        @endphp
                        <div class="sipma-profile-pref" wire:key="pref-{{ $key }}">
                            <div class="sipma-row-main">
                                <div class="sipma-row-title" id="pref-{{ $key }}-label">{{ $label }}</div>
                                <div class="sipma-row-meta" id="pref-{{ $key }}-desc">{{ $description }}</div>
                            </div>
                            <button
                                type="button"
                                role="switch"
                                class="sipma-profile-switch"
                                aria-checked="{{ $enabled ? 'true' : 'false' }}"
                                aria-labelledby="pref-{{ $key }}-label"
                                aria-describedby="pref-{{ $key }}-desc"
                                @disabled(! $mailEnabled)
                                wire:click="toggleNotification(@js($key))"
                                wire:loading.attr="disabled"
                                wire:target="toggleNotification"
                            ><span></span></button>
                        </div>
                    @empty
                        <p class="sipma-row-meta">{{ __('profile.preferences.none') }}</p>
                    @endforelse
                </div>

                <div class="sipma-card-head sipma-profile-head sipma-profile-head-top">
                    <h3 class="sipma-card-title">{{ __('profile.preferences.display_title') }}</h3>
                </div>

                <div class="sipma-section sipma-profile-display">
                    <div>
                        <p class="sipma-profile-label" id="pref-language">{{ __('profile.preferences.language') }}</p>
                        <div class="sipma-segment sipma-segment-lg" role="group" aria-labelledby="pref-language">
                            @foreach (Locale::SUPPORTED as $code)
                                <a
                                    href="{{ route('locale.switch', $code) }}"
                                    hreflang="{{ $code }}"
                                    lang="{{ $code }}"
                                    @class(['sipma-segment-btn', 'is-active' => app()->getLocale() === $code])
                                    @if (app()->getLocale() === $code) aria-current="true" @endif
                                >{{ __('portal.language.'.$code) }}</a>
                            @endforeach
                        </div>
                    </div>

                    @if (filament()->hasDarkMode() && ! filament()->hasDarkModeForced())
                        <div x-data="{ mode: localStorage.getItem('theme') ?? @js(filament()->getDefaultThemeMode()->value) }" x-on:theme-changed.window="mode = $event.detail">
                            <p class="sipma-profile-label" id="pref-theme">{{ __('profile.preferences.theme') }}</p>
                            <div class="sipma-segment sipma-segment-lg" role="group" aria-labelledby="pref-theme">
                                @foreach (__('profile.preferences.themes') as $value => $label)
                                    <button
                                        type="button"
                                        class="sipma-segment-btn"
                                        x-bind:class="{ 'is-active': mode === @js($value) }"
                                        x-bind:aria-pressed="(mode === @js($value)).toString()"
                                        x-on:click="$dispatch('theme-changed', @js($value))"
                                    >{{ $label }}</button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </section>

            {{-- Aktivitas --}}
            <section id="profile-panel-activity" role="tabpanel" aria-labelledby="profile-tab-activity" x-show="tab === 'activity'" x-cloak>
                <div class="sipma-card-head sipma-card-head-ruled sipma-profile-head">
                    <div>
                        <h3 class="sipma-card-title">{{ __('profile.activity.title') }}</h3>
                        <p class="sipma-card-desc">{{ __('profile.activity.desc') }}</p>
                    </div>
                </div>

                <div class="sipma-section">
                    @if ($activity->isEmpty())
                        <x-sipma.empty icon="lucide-activity" :title="__('profile.activity.empty')" :description="__('profile.activity.empty_desc')" />
                    @else
                        <x-sipma.activity-timeline :items="$activity" with-date />
                    @endif
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
