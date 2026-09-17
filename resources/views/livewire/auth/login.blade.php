@use('Filament\Facades\Filament')

<div>
    <x-portal.auth-shell>
        <h1 id="login-title" class="text-h1 tracking-tight text-ink">{{ __('portal.login.title') }}</h1>
        <p class="mt-1 text-small text-ink-muted">{{ __('portal.login.subtitle') }}</p>

        <form
            wire:submit="login"
            novalidate
            aria-labelledby="login-title"
            class="mt-6 space-y-4"
            x-data
            x-on:form-invalid.window="$nextTick(() => document.getElementById('error-summary-title')?.focus())"
        >
            {{-- R-3.3: ringkasan error menerima fokus, tiap butir tertaut ke field --}}
            @if ($showErrorSummary && $errors->any())
                <div class="rounded-control bg-danger/5 px-4 py-3 ring-1 ring-inset ring-danger/40" aria-labelledby="error-summary-title">
                    <h2 id="error-summary-title" tabindex="-1" class="sipma-focus flex items-center gap-2 rounded-chip text-small font-medium text-danger">
                        @svg('heroicon-m-exclamation-circle', 'h-5 w-5 shrink-0', ['aria-hidden' => 'true'])
                        {{ __('portal.login.error_summary') }}
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

            <x-portal.field
                name="email"
                type="email"
                :label="__('portal.login.email')"
                autocomplete="email"
            />

            <x-portal.field
                name="password"
                type="password"
                :label="__('portal.login.password')"
                autocomplete="current-password"
                revealable
            />

            <div class="flex flex-wrap items-center justify-between gap-x-4">
                <label for="form-remember" class="flex min-h-11 cursor-pointer items-center gap-3">
                    <input id="form-remember" type="checkbox" wire:model="form.remember" class="sipma-checkbox">
                    <span class="text-small text-ink-muted">{{ __('portal.login.remember') }}</span>
                </label>

                <a href="{{ Filament::getPanel('app')->getRequestPasswordResetUrl() }}" class="sipma-link text-small">
                    {{ __('portal.login.forgot') }}
                </a>
            </div>

            <button type="submit" class="sipma-button w-full" wire:loading.attr="disabled" wire:target="login">
                <span wire:loading.remove wire:target="login">{{ __('portal.login.submit') }}</span>
                <span wire:loading wire:target="login">{{ __('portal.login.submitting') }}</span>
            </button>
        </form>

        <p class="mt-6 text-center text-small text-ink-muted">
            {{ __('portal.login.no_account') }}
            <a href="{{ route('register') }}" class="sipma-link">{{ __('portal.login.register') }}</a>
        </p>
    </x-portal.auth-shell>
</div>
