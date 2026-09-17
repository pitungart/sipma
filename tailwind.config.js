/**
 * Token SIPMA dari .agents/sipma-desing-rules.md v3.0 (Lampiran B) untuk portal Blade/Livewire.
 * Panel Filament memakai App\Filament\Support\SipmaTheme.
 * Jarak memakai skala bawaan Tailwind (1, 2, 3, 4, 6, 8, 12, 16, 24 = R-2.10).
 *
 * @type {import('tailwindcss').Config}
 */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './app/Livewire/**/*.php',
    ],
    theme: {
        extend: {
            colors: {
                primary: {
                    50: '#F4F5FB', 100: '#E5E7F5', 200: '#C8CCEA', 300: '#8D9AE7',
                    400: '#4E62DA', 500: '#283DBD', 600: '#203197', 700: '#1C2A82',
                    800: '#192676', 900: '#141E5D', 950: '#0C133B',
                    DEFAULT: '#192676', fg: '#FFFFFF',
                },
                pending: '#2B3DAB',
                success: { DEFAULT: '#15803D', fg: '#FFFFFF' },
                danger: { DEFAULT: '#B91C1C', fg: '#FFFFFF' },
                canvas: '#FFFFFF',
                sunken: '#FAFAFA',
                muted: '#F4F4F5',
                ink: { DEFAULT: '#18181B', muted: '#52525B', subtle: '#71717A' },
                line: { DEFAULT: '#E4E4E7', interactive: '#71717A' },
                // §2.5: emas KUI hanya sebagai aksen dekoratif non-teks di halaman autentikasi
                gold: { DEFAULT: '#F7E051' },
            },
            fontFamily: {
                sans: ['Inter', 'system-ui', 'sans-serif'],
            },
            fontSize: {
                caption: ['13px', { lineHeight: '1.5' }],
                small: ['14px', { lineHeight: '1.5' }],
                body: ['16px', { lineHeight: '1.6' }],
                h3: ['16px', { lineHeight: '1.4', fontWeight: '500' }],
                h2: ['20px', { lineHeight: '1.35', fontWeight: '500' }],
                h1: ['24px', { lineHeight: '1.3', fontWeight: '600' }],
                display: ['32px', { lineHeight: '1.2', fontWeight: '600' }],
            },
            borderRadius: {
                chip: '6px',
                control: '10px',
                card: '12px',
            },
            boxShadow: {
                card: '0 1px 2px rgb(0 0 0 / 0.05)',
            },
        },
    },
    plugins: [],
};
