# SIPMA — Sistem Informasi Penerimaan Mahasiswa Asing Non-Degree

Aplikasi web **Kantor Urusan Internasional (KUI) Universitas Udayana** untuk mengelola penerimaan mahasiswa asing program *non-degree*, mulai dari **pendaftaran → verifikasi dokumen & pembayaran → penerbitan Letter of Acceptance (LOA)**.

SIPMA menggantikan proses manual yang selama ini tersebar di email, WhatsApp, Google Sheets, dan Google Drive. Data diinput sekali oleh pendaftar atau agen, lalu dipakai di seluruh alur tanpa diketik ulang. Proyek ini dikembangkan sebagai bagian dari penelitian tesis; rancangan arsitekturnya didokumentasikan terpisah (lihat [Dokumen rancangan](#dokumen-rancangan)).

---

## Daftar isi

- [Status pengembangan](#status-pengembangan)
- [Peran dan panel](#peran-dan-panel)
- [Teknologi](#teknologi)
- [Menjalankan secara lokal](#menjalankan-secara-lokal)
- [Menjalankan tes](#menjalankan-tes)
- [Struktur kode penting](#struktur-kode-penting)
- [Dokumen rancangan](#dokumen-rancangan)
- [Catatan penting](#catatan-penting)

---

## Status pengembangan

Proyek berada di **Fase 1** roadmap (pendaftaran → LOA). Fondasi dan alur autentikasi sudah jadi; modul bisnis inti belum dibangun.

**Sudah tersedia**

- Fondasi Laravel 11 + Filament 3 dengan tiga panel (`/admin`, `/agent`, `/app`)
- Skema basis data sesuai ERD: fakultas, program, agen, MOU, mahasiswa, dokumen, pembayaran, LOA, status visa, dan activity log — seluruh ID memakai UUID
- Hak akses berbasis role: enum `UserRole`, Policy per model, dan scope query per role
- Halaman **daftar** (`/register`) untuk mahasiswa mandiri dan agen mitra, dengan verifikasi email
- Halaman **login terpadu** (`/login`) untuk semua role, lalu diarahkan ke panel masing-masing
- Antarmuka dwibahasa **English / Bahasa Indonesia** (mengikuti bahasa browser, bisa diganti manual)
- Tema visual SIPMA (warna, font Inter) untuk portal dan panel Filament

**Belum dibangun**

- Kelola fakultas, program, dan admin fakultas di panel admin
- Profil agen dan unggah/verifikasi MOU
- Formulir pendaftaran mahasiswa (data personal, pilih program, unggah dokumen, bukti bayar, submit)
- Verifikasi dokumen dan pembayaran, catatan revisi per dokumen
- Unggah dan unduh LOA
- Notifikasi perubahan status dan dashboard statistik

---

## Peran dan panel

| Role | Panel | Yang dilakukan (rancangan) |
| --- | --- | --- |
| Super Admin (KUI) | `/admin` | Mengelola seluruh data, memverifikasi dokumen, pembayaran, dan MOU, mengunggah LOA |
| Admin Fakultas | `/admin` | Mengelola program di fakultasnya; melihat mahasiswa di program tersebut (read-only) |
| Agen Mitra | `/agent` | Mengunggah MOU; setelah disetujui, mendaftarkan dan memantau banyak mahasiswa |
| Mahasiswa | `/app` | Mendaftar mandiri, mengunggah dokumen, memantau status, mengunduh LOA |

Semua role masuk lewat **`/login`**. Halaman login bawaan tiap panel hanya meneruskan ke sana. Pendaftaran akun hanya tersedia untuk mahasiswa dan agen; akun admin dibuat oleh Super Admin.

---

## Teknologi

| Komponen | Versi |
| --- | --- |
| PHP | 8.2+ |
| Laravel | 11 |
| Filament | 3 (panel admin, agen, mahasiswa) |
| Livewire + Alpine.js | 3 (halaman portal: daftar & login) |
| Tailwind CSS | 3 (build dengan Vite) |
| Basis data | MySQL 8 (target server: MySQL 5.7) |
| Activity log | spatie/laravel-activitylog |

---

## Menjalankan secara lokal

### Prasyarat

- PHP **8.2** atau lebih baru, dengan ekstensi `intl`, `zip`, `pdo_mysql`, `gd`
- Composer 2
- Node.js 20+ dan npm
- MySQL

### Langkah

1. **Pasang dependensi**

   ```bash
   composer install
   npm install
   ```

2. **Siapkan file environment**

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   Sesuaikan nilai berikut di `.env` (nilai bawaan di `.env.example` belum diubah):

   ```dotenv
   APP_NAME=SIPMA
   APP_TIMEZONE=Asia/Makassar
   FILESYSTEM_DISK=public

   DB_CONNECTION=mysql
   DB_DATABASE=sipma
   DB_USERNAME=root
   DB_PASSWORD=
   ```

3. **Buat database, jalankan migrasi dan seeder**

   ```bash
   mysql -u root -e "CREATE DATABASE sipma"
   php artisan migrate --seed
   php artisan storage:link
   ```

4. **Build aset frontend**

   ```bash
   npm run build      # sekali build
   # atau
   npm run dev        # mode pengembangan dengan hot reload
   ```

5. **Jalankan aplikasi dan antrean**

   ```bash
   php artisan serve
   php artisan queue:work   # di terminal lain — email verifikasi dikirim lewat antrean
   ```

   Buka <http://localhost:8000/register> atau <http://localhost:8000/login>.

### Akun awal

Seeder membuat satu akun Super Admin:

| Email | Password |
| --- | --- |
| `superadmin@sipma.test` | `password` |

> Ganti password ini sebelum aplikasi dipakai di luar lingkungan lokal.

Selama pengembangan `MAIL_MAILER=log`, sehingga email (termasuk tautan verifikasi) tidak benar-benar terkirim, melainkan tercatat di `storage/logs/laravel.log`.

---

## Menjalankan tes

```bash
php artisan test
```

Tes memakai SQLite in-memory (diatur di `phpunit.xml`), jadi tidak menyentuh database MySQL lokal. Cakupan saat ini: pendaftaran, login terpadu, verifikasi email, dan pemilihan bahasa.

---

## Struktur kode penting

```
app/
├── Enums/                    # UserRole dan status (mahasiswa, dokumen, pembayaran, MOU, LOA, visa)
├── Filament/
│   ├── Auth/                 # Halaman login/daftar panel yang meneruskan ke portal
│   └── Support/SipmaTheme.php  # Warna, font, dan logo untuk semua panel
├── Http/
│   ├── Controllers/SwitchLocaleController.php
│   └── Middleware/SetLocale.php
├── Livewire/
│   ├── Auth/                 # Halaman /register dan /login
│   └── Forms/                # Validasi & logika form daftar dan login
├── Models/                   # Model sesuai ERD (UUID, cast enum, relasi, scope per role)
├── Policies/                 # Hak akses per model dan role
├── Providers/Filament/       # Konfigurasi panel admin, agent, app
└── Support/Locale.php        # Penentuan bahasa aktif
lang/{en,id}/portal.php       # Teks antarmuka portal dalam dua bahasa
resources/views/
├── components/portal/        # Kerangka halaman autentikasi & komponen field
└── livewire/auth/            # View daftar dan login
```

---

## Dokumen rancangan

Dokumen rancangan disimpan di folder `.agents/` pada mesin pengembang dan **tidak ikut di repository** (folder ini di-ignore). Mintalah salinannya ke pemilik proyek.

| Dokumen | Isi |
| --- | --- |
| `tobe-design.md` | Rancangan to-be: BPMN as-is & to-be, ERD, use case, diagram komponen, topologi, matriks Zachman, dan roadmap implementasi |
| `sipma-desing-rules.md` | Aturan desain antarmuka: token warna, tipografi, aksesibilitas (WCAG 2.1 AA), pola formulir, dan pengecualian untuk halaman autentikasi |

---

## Catatan penting

- **Keamanan Laravel 11.** Laravel 11 sudah tidak menerima patch keamanan; `composer audit` melaporkan beberapa kerentanan yang hanya diperbaiki di Laravel 12. Sebaiknya naik versi sebelum dipasang di server produksi.
- **Logo universitas.** `public/images/logo-unud.png` diunduh dari situs resmi unud.ac.id. Jika KUI memiliki berkas logo resmi (idealnya SVG), simpan sebagai `public/images/logo-unud.svg` dan berkas itu otomatis dipakai.
- **Lupa kata sandi** saat ini memakai halaman bawaan Filament (panel `/app`) yang tampilannya belum mengikuti desain portal.
- **Perbedaan dengan dokumen to-be.** `tobe-design.md` merancang login terpisah per panel; implementasi memakai satu login terpadu di `/login`. Panelnya tetap terpisah.
