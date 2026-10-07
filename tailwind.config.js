/**
 * Token SIPMA dari agents/sipma-desing-rules.md v5.0 untuk portal Blade/Livewire.
 * Panel Filament memakai App\Filament\Support\SipmaTheme + resources/css/filament-tokens.css.
 * Jarak memakai skala bawaan Tailwind (1, 2, 3, 4, 6, 8, 12, 16, 24 = R-2.10).
 *
 * @type {import('tailwindcss').Config}
 */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        // storage/framework/views sengaja tidak dipindai: isinya hasil compile view yang sumbernya
        // sudah tercakup di bawah, dan setelah panel Filament dibuka ia menyeret kelas Filament
        // ke dalam CSS portal (49 KB → 78 KB) padahal Filament punya CSS sendiri.
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './app/Livewire/**/*.php',
    ],
    theme: {
        extend: {
            colors: {
                /*
                 * Indigo dipakai berbeda menurut perannya:
                 * DEFAULT (#6466E9) hanya sebagai isi bidang/grafik dengan teks putih (4,55:1);
                 * sebagai teks ia gagal di kanvas, jadi link memakai #283DBD dan judul/brand #192676.
                 */
                primary: {
                    50: '#F6F6FE', 100: '#ECEDFC', 200: '#D8D9FA', 300: '#BEBFF6',
                    400: '#9697F0', 500: '#7A7BEC', 600: '#6466E9', 700: '#5456C4',
                    800: '#44459E', 900: '#36377E', 950: '#22234F',
                    DEFAULT: '#6466E9', // isi bidang & ring — sama dengan stop 600 Filament
                    hover: '#5456C4',
                    link: '#283DBD', // teks tautan: #6466E9 gagal AA di atas kanvas
                    dark: '#192676', // teks brand & judul
                    subtle: '#F6F6FE',
                    fg: '#FFFFFF',
                },
                // Pasangan bg + teks status wajib dipakai bersama (R-1.8) dan selalu berlabel teks (R-1.9)
                pending: { DEFAULT: '#2B3DAB', bg: '#EEF2FF', text: '#1E3A8A' },
                success: { DEFAULT: '#15803D', bg: '#F0FDF4', text: '#166534', fg: '#FFFFFF' },
                danger: { DEFAULT: '#B91C1C', bg: '#FEF2F2', text: '#991B1B', fg: '#FFFFFF' },
                draft: { bg: '#FAFAFA', text: '#52525B' },
                canvas: '#F9F9FC', // latar halaman panel; kartu putih mengambang di atasnya
                card: '#FFFFFF',
                muted: '#F4F4F5',
                ink: { DEFAULT: '#18181B', muted: '#52525B', subtle: '#71717A' },
                // line.DEFAULT dekoratif saja (1,22:1); batas komponen interaktif memakai line.interactive
                line: { DEFAULT: '#E4E4E7', interactive: '#71717A' },
                // §2.5: emas KUI hanya sebagai aksen dekoratif non-teks di halaman autentikasi
                gold: { DEFAULT: '#F7E051' },
            },
            fontFamily: {
                sans: ['"DM Sans"', 'system-ui', 'sans-serif'],
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
                chip: '999px', // hanya label status kecil
                control: '8px', // tombol, input, select
                card: '10px',
            },
            boxShadow: {
                // Dinamai "raised", bukan "card": Tailwind juga membentuk utility warna bayangan
                // dari setiap nama warna, sehingga shadow-card akan bertabrakan dengan warna card.
                raised: '0 1px 3px rgb(0 0 0 / 0.08)', // hanya pada kartu
            },
        },
    },
    plugins: [],
};
