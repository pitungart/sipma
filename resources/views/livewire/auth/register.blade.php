@use('App\Enums\UserRole')

@php
    $roleIcons = [
        UserRole::Student->value => 'heroicon-o-academic-cap',
        UserRole::Agent->value => 'heroicon-o-building-office-2',
    ];
@endphp

<div>
    <x-portal.auth-shell>
        <h1 id="register-title" class="text-h1 tracking-tight text-ink">{{ __('portal.register.title') }}</h1>

        <form
            wire:submit="register"
            novalidate
            aria-labelledby="register-title"
            class="mt-6 space-y-4"
            x-data
            x-on:form-invalid.window="$nextTick(() => document.getElementById('error-summary-title')?.focus())"
        >
            {{-- R-3.3: ringkasan error menerima fokus, tiap butir tertaut ke field --}}
            @if ($showErrorSummary && $errors->any())
                <div class="rounded-control bg-danger/5 px-4 py-3 ring-1 ring-inset ring-danger/40" aria-labelledby="error-summary-title">
                    <h2 id="error-summary-title" tabindex="-1" class="sipma-focus flex items-center gap-2 rounded-chip text-small font-medium text-danger">
                        @svg('heroicon-m-exclamation-circle', 'h-5 w-5 shrink-0', ['aria-hidden' => 'true'])
                        {{ __('portal.register.error_summary') }}
                    </h2>

                    <ul class="mt-2 list-disc space-y-1 ps-8 text-small text-danger">
                        @foreach ($errors->messages() as $field => $messages)
                            @php($target = str_replace('.', '-', $field))
                            <li>
                                <a
                                    href="#{{ $target }}"
                                    x-on:click.prevent="document.getElementById(@js($target))?.focus()"
                                    class="sipma-focus rounded-chip underline decoration-danger/40 underline-offset-4 hover:decoration-danger"
                                >{{ $messages[0] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <fieldset @error('form.role') aria-describedby="form-role-error" @enderror>
                <legend class="text-small font-medium text-ink">{{ __('portal.register.role_legend') }}</legend>

                {{--
                    Segmented control: opsi aktif berlatar primary dengan teks putih (13,29:1).
                    Urutan mengikuti $roleIcons (mahasiswa dulu). Ikon centang tetap sebagai penanda non-warna (R-1.9).
                --}}
                <div class="mt-1 grid grid-cols-2 gap-1 rounded-control bg-muted p-1">
                    @foreach ($roleIcons as $roleValue => $roleIcon)
                        @php($role = UserRole::from($roleValue))

                        <label class="group relative flex min-h-11 cursor-pointer items-center gap-1.5 rounded-chip px-2 text-small font-medium text-ink-muted transition-colors sm:gap-2 sm:px-3 [&:not(:has(:checked))]:hover:text-ink has-[:checked]:bg-primary has-[:checked]:text-primary-fg has-[:checked]:shadow-card has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary has-[:focus-visible]:ring-offset-2">
                            <input
                                type="radio"
                                id="{{ $role === UserRole::Student ? 'form-role' : 'form-role-'.$role->value }}"
                                name="role"
                                value="{{ $role->value }}"
                                wire:model.live="form.role"
                                class="sr-only"
                            >

                            {{-- Ikon role disembunyikan di layar sempit agar label tidak terpotong; ikon centang tetap (R-1.9) --}}
                            @svg($roleIcon, 'hidden h-5 w-5 shrink-0 sm:block', ['aria-hidden' => 'true'])

                            <span class="min-w-0 truncate">{{ __('portal.register.roles.'.$role->value.'.title') }}</span>

                            @svg('heroicon-m-check-circle', 'ms-auto hidden h-4 w-4 shrink-0 text-primary-fg group-has-[:checked]:block', ['aria-hidden' => 'true'])
                        </label>
                    @endforeach
                </div>

                <div aria-live="polite">
                    @error('form.role')
                        <x-portal.error id="form-role-error" :message="$message" />
                    @enderror
                </div>
            </fieldset>

            <x-portal.field
                name="name"
                :label="$form->isAgent() ? __('portal.register.fields.contact_name') : __('portal.register.fields.name')"
                autocomplete="name"
                :hint="$form->isAgent() ? null : __('portal.register.fields.name_hint')"
            />

            <x-portal.field
                name="email"
                type="email"
                :label="__('portal.register.fields.email')"
                autocomplete="email"
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-portal.field
                    name="password"
                    type="password"
                    :label="__('portal.register.fields.password')"
                    autocomplete="new-password"
                    :hint="__('portal.register.fields.password_hint')"
                    revealable
                />

                <x-portal.field
                    name="password_confirmation"
                    type="password"
                    :label="__('portal.register.fields.password_confirmation')"
                    autocomplete="new-password"
                    revealable
                />
            </div>

            {{-- R-2.9: pengelompokan ditandai satu garis tipis, bukan kotak --}}
            @if ($form->isAgent())
                <div class="space-y-4 border-t border-line pt-4" wire:key="agency-fields">
                    <div>
                        <h2 class="text-h3 text-ink">{{ __('portal.register.agency.title') }}</h2>
                        <p class="mt-1 text-small text-ink-muted">{{ __('portal.register.agency.text') }}</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-portal.field name="agency_name" :label="__('portal.register.fields.agency_name')" autocomplete="organization" />
                        <x-portal.field name="country" :label="__('portal.register.fields.country')" autocomplete="country-name" />
                    </div>
                </div>
            @endif

            <div>
                <label for="form-consent" class="flex min-h-11 cursor-pointer items-start gap-3">
                    <input
                        id="form-consent"
                        type="checkbox"
                        wire:model.live="form.consent"
                        @error('form.consent') aria-invalid="true" aria-describedby="form-consent-error" @enderror
                        class="sipma-checkbox"
                    >
                    <span class="text-small text-ink-muted">{{ __('portal.register.consent') }}</span>
                </label>

                <div aria-live="polite">
                    @error('form.consent')
                        <x-portal.error id="form-consent-error" :message="$message" />
                    @enderror
                </div>
            </div>

            <button type="submit" class="sipma-button w-full" wire:loading.attr="disabled" wire:target="register">
                <span wire:loading.remove wire:target="register">{{ __('portal.register.submit') }}</span>
                <span wire:loading wire:target="register">{{ __('portal.register.submitting') }}</span>
            </button>
        </form>

        {{-- Login terpadu untuk semua role --}}
        <p class="mt-6 text-center text-small text-ink-muted">
            {{ __('portal.register.have_account') }}
            <a href="{{ route('login') }}" class="sipma-link">{{ __('portal.register.login') }}</a>
        </p>
    </x-portal.auth-shell>
</div>
