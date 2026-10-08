# Hasil implementasi tahap 0

Tanggal pemeriksaan: 9 Oktober 2026. Acuan: tahap 0 pada `prd.md`.

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
| Layout browser pada ukuran jendela Tauri 1280 × 820 | Tampil dengan baik |
| Kebersihan data uji | Akun uji browser sudah dihapus |

Pengujian otomatis meliputi kredensial salah, batas percobaan login, halaman yang memerlukan autentikasi, pemulihan kata sandi, perubahan profil/kata sandi, penolakan pendaftaran publik, penolakan penghapusan akun, akun awal, pemeriksaan CSRF yang diaktifkan secara nyata, serta pergantian ID sesi setelah login.

Pengujian SQLite memakai dua koneksi terhadap berkas sementara. Hasilnya membuktikan lock diperoleh sebelum membaca saldo, penulis kedua membaca hasil commit terbaru, rollback melepas lock, savepoint dan afterCommit bekerja, retry ketika BEGIN sibuk berjalan, batas retry tidak mengubah saldo, dan kegagalan afterCommit tidak mengulang perubahan yang sudah commit.

Temuan kompatibilitas Breeze sudah diselesaikan: tipe Node scaffold diperbarui agar sesuai dengan Vite 7; integrasi CSS menggunakan Tailwind 4 melalui Vite; komponen shadcn menggunakan alias utilitas proyek; input shadcn disesuaikan agar ref formulir bekerja pada React 18.

Screenshot halaman masuk: [login.jpg](screenshots/login.jpg).

## Desktop

Source Tauri 2.12.1, CLI, Cargo.lock, icon desktop, konfigurasi URL runtime, validasi HTTPS/origin, pembatasan navigasi, penolakan jendela baru, dan halaman kegagalan koneksi sudah disiapkan. Konten remote tidak memperoleh capability native.

Rust 1.99.0, rustfmt, Build Tools 2022, Windows SDK, dan WebView2 155.0.4283.45 tersedia. Pemasangan compiler standar sempat gagal karena ruang drive C; komponen MSVC minimum berhasil dipasang di `.tools/vs-buildtools` pada drive D.

| Pemeriksaan desktop | Hasil |
| --- | --- |
| Prasyarat dari `tauri info` | Tersedia |
| Format Rust | Lolos |
| `cargo test --locked` | 3 lolos |
| `tauri build --debug` | Berhasil membuat executable Windows |
| Interaksi login/logout dan sesi dalam WebView | Menunggu pemeriksaan manual |

Executable terdapat di `desktop/src-tauri/target/debug/clbk-toke-desktop.exe`. Pengujian Rust memeriksa validasi HTTPS/localhost, penolakan kredensial/path/query pada alamat server, dan pembatasan origin termasuk host palsu serta perubahan port/skema.

Kontrol interaksi aplikasi Windows tidak tersedia pada sesi otomasi ini. Karena itu koneksi WebView, login/logout desktop, persistensi cookie setelah aplikasi dibuka ulang, CSRF dalam WebView, dan halaman kegagalan koneksi runtime belum dinyatakan terverifikasi. **Tahap 0 belum ditutup sepenuhnya sampai pemeriksaan manual desktop selesai.** Kode fondasi web dan executable desktop sudah dapat ditinjau; tahap 1 belum dimulai.

## Batas tahap

Tahap 1 belum dimulai. Belum ada role, CRUD konsumen/anggota/mobil/pabrik, timbangan, penjualan, pembayaran, gaji, pengeluaran, atau laporan bisnis. Aturan berat manual penjualan pabrik dan penjualan sortir tetap mengikuti PRD untuk tahap modul terkait.
