# Hasil implementasi tahap 0

Tanggal pemeriksaan: 9 Oktober 2026. Acuan: tahap 0 pada `prd.md`. Catatan ini merekam hasil fondasi; perkembangan sesudahnya terdapat di [hasil tahap 1](tahap-1.md).

## Fondasi web yang tersedia

- Laravel 13.35.0 dengan Breeze 2.4.2 dan Inertia Laravel 2.0.28.
- React 18.3.1, Inertia React 2.3.28, Vite 7.3.7, Tailwind 4.3.3, dan komponen shadcn yang tersimpan di source proyek.
- Login/logout berbahasa Indonesia, pemulihan kata sandi, profil, dan pergantian kata sandi.
- Tidak ada pendaftaran publik atau penghapusan akun melalui profil.
- Pembuatan akun awal melalui `php artisan clbk:bootstrap-user`, tanpa kata sandi bawaan.
- Layout ruang kerja, halaman awal dashboard tanpa statistik contoh, serta status koneksi server.
- SQLite dengan foreign key, WAL, FULL, busy timeout 5 detik, dan BEGIN IMMEDIATE.
- Koordinator transaksi tulis dengan percobaan ulang yang terbatas.
- Konfigurasi UTC untuk timestamp dan WIB untuk tanggal operasional.
- Panduan setup, lockfile dependensi, dan struktur folder mengikuti fondasi PRD.

PHP minimum proyek adalah 8.4 karena integrasi transaksi IMMEDIATE pada framework yang terpasang memakai jalur PHP 8.4+. Runtime yang diperiksa: PHP 8.4.25 dan Node.js 24.19.0.

## Hasil pemeriksaan web

| Pemeriksaan | Hasil |
| --- | --- |
| Build TypeScript + Vite | Lolos |
| Format frontend | Lolos |
| Format PHP/Pint | Lolos |
| Validasi Composer | Lolos |
| Pengujian otomatis | 40 lolos, 117 assertions |
| Login browser dengan akun uji | Berhasil membuka dashboard |
| Profil browser | Formulir tampil, tidak ada kesalahan console saat diperiksa |
| Logout browser | Berhasil kembali ke halaman masuk |
| Layout browser komputer pada ukuran 1280 × 820 | Tampil dengan baik |
| Kebersihan data uji | Akun uji browser sudah dihapus |

Pengujian otomatis meliputi kredensial salah, batas percobaan login, halaman yang memerlukan autentikasi, pemulihan kata sandi, perubahan profil/kata sandi, penolakan pendaftaran publik, penolakan penghapusan akun, akun awal, pemeriksaan CSRF yang diaktifkan secara nyata, serta pergantian ID sesi setelah login.

Pengujian SQLite memakai dua koneksi terhadap berkas sementara. Hasilnya membuktikan lock diperoleh sebelum membaca saldo, penulis kedua membaca hasil commit terbaru, rollback melepas lock, savepoint dan afterCommit bekerja, retry ketika BEGIN sibuk berjalan, batas retry tidak mengubah saldo, dan kegagalan afterCommit tidak mengulang perubahan yang sudah commit.

Temuan kompatibilitas Breeze sudah diselesaikan: tipe Node scaffold diperbarui agar sesuai dengan Vite 7; integrasi CSS menggunakan Tailwind 4 melalui Vite; komponen shadcn menggunakan alias utilitas proyek; input shadcn disesuaikan agar ref formulir bekerja pada React 18.

Screenshot halaman masuk: [login.jpg](screenshots/login.jpg).

## Batas tahap

Pada akhir pekerjaan fondasi, role dan modul master belum tersedia. Keduanya sekarang ditangani pada tahap 1. Timbangan, penjualan, pembayaran, gaji, pencatatan pengeluaran, dan laporan bisnis mengikuti tahap berikutnya. Aturan berat manual penjualan pabrik dan penjualan sortir tetap mengikuti PRD untuk tahap modul terkait.
