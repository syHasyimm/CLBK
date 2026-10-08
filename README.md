# CLBK TOKE

Sistem pengelolaan usaha sawit menggunakan Laravel 13, Breeze, React + Inertia, SQLite, Tailwind 4, shadcn/ui, dan klien desktop Tauri 2.

Implementasi saat ini terbatas pada **tahap 0 — fondasi**. Login, profil, komponen UI bersama, dan pengelolaan transaksi SQLite tersedia. Role, data master, timbangan, penjualan, pembayaran, penggajian, dan laporan mengikuti tahap berikutnya di [PRD](prd.md). Dashboard saat ini merupakan halaman awal tanpa angka transaksi contoh.

## Menjalankan web

Prasyarat: PHP **8.4+** dengan `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `fileinfo`, dan `curl`; Composer 2; Node.js 22.12+ atau 24.

```powershell
composer setup
php artisan clbk:bootstrap-user
composer dev
```

Buka <http://127.0.0.1:8000>. Perintah akun awal meminta nama, email, dan kata sandi melalui terminal. Kata sandi disembunyikan saat diisi. Perintah ini hanya membuat satu akun ketika tabel pengguna masih kosong. Tidak ada kata sandi bawaan dan tidak ada pendaftaran publik. Penetapan role dilakukan pada tahap 1.

`composer setup` memasang dependensi, menyiapkan `.env` dan database yang belum tersedia, menjalankan migrasi, lalu membuat build frontend. Pengulangan setup mempertahankan APP_KEY dan database yang sudah ada. Perintah ini untuk development lokal; deployment produksi akan disiapkan pada tahap deployment.

Untuk menjalankan aset hasil build tanpa Vite:

```powershell
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Pengaturan server dan database disimpan di `.env`. SQLite hanya dibuka oleh Laravel pada satu server bersama. Desktop dan browser mengakses server tersebut melalui HTTP(S); keduanya tidak membuka berkas database secara langsung.

Email pengaturan ulang kata sandi memakai `MAIL_MAILER=log` pada development. Tautannya tercatat di `storage/logs/laravel.log`; pengiriman email nyata memerlukan konfigurasi mail server pada deployment.

## Struktur fondasi

```text
app/
  Console/Commands/BootstrapUser.php
  Http/Controllers/Auth/           # Autentikasi Breeze
  Http/Requests/                   # Validasi request
  Http/Middleware/                 # Integrasi Inertia
  Models/User.php
  Support/Database/                # Koordinator transaksi tulis
config/
  clbk.php                        # Zona waktu operasional WIB
  database.php                    # SQLite WAL + IMMEDIATE
resources/
  css/app.css                     # Tailwind 4 dan token tema
  js/
    components/ui/                # Komponen shadcn milik proyek
    components/common/            # Komponen lintas halaman
    hooks/                        # Status koneksi server
    features/profile/components/  # Formulir profil dan kata sandi
    layouts/                      # Layout masuk dan ruang kerja
    lib/                          # Utilitas UI
    pages/Auth/                   # Login dan pemulihan akun
    pages/Profile/                # Profil dan kata sandi
    types/                        # Tipe data bersama
  views/app.blade.php
lang/id/                          # Pesan autentikasi dan validasi
desktop/                          # Klien Tauri untuk server bersama
scripts/prepare-environment.php
tests/Feature/                    # Autentikasi, sesi, dan konkurensi SQLite
docs/tahap-0.md                    # Catatan hasil pemeriksaan
prd.md                            # Acuan implementasi modul berikutnya
```

Folder domain bisnis pada PRD ditambahkan ketika tahap modul terkait dimulai.

## Komponen UI bersama

Komponen dasar berada di `resources/js/components/ui`: `Button`, `Input`, `Card`, `Label`, `Badge`, dan `Separator`. Wrapper formulir Breeze memakai komponen dasar yang sama. Warna, ukuran radius, dan font dikelola melalui token CSS di `resources/css/app.css`. Alias `@/` menunjuk ke `resources/js/` dan konfigurasi shadcn berada di `components.json`.

Jika menambahkan komponen, gunakan alias proyek dari konfigurasi tersebut. Fondasi menggunakan Tailwind 4 melalui plugin Vite; jangan menambahkan kembali konfigurasi PostCSS/Tailwind 3.

## Transaksi SQLite

Konfigurasi koneksi menetapkan foreign key aktif, `journal_mode=WAL`, `synchronous=FULL`, waktu tunggu lock 5 detik, dan `transaction_mode=IMMEDIATE`. PHP 8.4+ diperlukan untuk jalur transaksi ini dalam framework yang terpasang.

Gunakan `App\Support\Database\WriteTransaction` pada batas terluar use case yang mengubah saldo, stok, atau pembayaran:

```php
$result = $transactions->run(function ($connection) {
    // Baca ulang saldo dan lakukan seluruh perubahan melalui koneksi ini.
    // BEGIN IMMEDIATE sudah memperoleh write lock sebelum callback berjalan.
    return $resultOfDatabaseChanges;
});
```

Contoh tersebut menunjukkan kontrak pemanggilan; modul bisnis belum diimplementasikan. Koordinator menjalankan commit/rollback melalui Laravel dan mencoba ulang `SQLITE_BUSY`/`SQLITE_LOCKED` maksimal tiga kali. Ini juga mencakup konflik saat `BEGIN`, yang tidak dicoba ulang oleh `DB::transaction(..., 3)` pada framework yang diuji. Callback tidak boleh melakukan efek eksternal yang tidak dapat diulang. Gunakan `afterCommit` untuk efek di luar transaksi; kegagalan setelah callback selesai tidak mengulang perubahan yang mungkin telah commit. Konflik yang tetap terjadi menghasilkan `WriteConflict`; modul terkait harus menampilkan pesan coba lagi dan memakai idempotensi sesuai PRD.

Koordinator tidak menerima pemanggilan di dalam transaksi yang sudah aktif. Gabungkan seluruh perubahan dalam satu use case dan satu koneksi. Database harus berada di disk lokal server; jangan letakkan SQLite di folder jaringan yang dibuka beberapa komputer.

Timestamp aplikasi tetap UTC. Zona waktu untuk tanggal operasional adalah `Asia/Jakarta` dari `config/clbk.php`.

## Desktop

Lihat [panduan desktop](desktop/README.md). Kode Tauri, CLI, konfigurasi origin server, pembatasan navigasi, dan halaman kegagalan koneksi tersedia. Build executable debug berhasil dan tiga pengujian Rust lolos. Pengujian sesi serta interaksi langsung di WebView masih perlu dilakukan secara manual; statusnya dicatat di [hasil tahap 0](docs/tahap-0.md).

## Pemeriksaan

```powershell
npm run build
npm run format:check
composer test
vendor\bin\pint --test
composer validate --strict
```

Jalankan build sebelum pengujian HTTP karena halaman Inertia menggunakan manifest Vite. Pengujian konkurensi memakai dua koneksi terhadap SQLite sementara berbasis berkas, bukan hanya database memory.

Lockfile Composer, npm web, dan npm desktop disertakan. `.env`, database operasional, cache dependensi, dan hasil build tidak dimasukkan ke version control.
