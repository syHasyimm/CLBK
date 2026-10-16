# Produksi MySQL 8.4

Pilihan produksi saat ini adalah database MySQL 8.4 baru pada resource Coolify terpisah. Jalur pemasangan untuk pemula ada di [DEPLOY.md](../DEPLOY.md). `.env` development dan suite regresi SQLite tidak diubah menjadi koneksi produksi.

## Schema dan deployment

- Dockerfile memasang `pdo_mysql` dan klien MySQL. Default image adalah `APP_ENV=production`, `APP_DEBUG=false`, `DB_CONNECTION=mysql`; credentials hanya melalui runtime env.
- Host database memakai hostname Internal URL resource MySQL pada destination yang sama. Data MySQL persisten pada `/var/lib/mysql`; file aplikasi pada `/data`, arsip pada `/backups`.
- Pilihan Hostinger memakai folder backup lokal tersendiri. Dockerfile/template Coolify menetapkan `BACKUP_REQUIRE_SEPARATE_DISK=false`, sehingga `/data` dan `/backups` boleh pada disk yang sama dengan folder sumber berbeda. Gunakan `true` bila memakai disk backup terpisah. Mount persisten, direktori privat, izin file, serta verifikasi/rekonsiliasi backup tetap berlaku. Tidak ada pengiriman ke luar VPS otomatis.
- Pada instalasi awal, `php artisan clbk:mysql-initialize` memeriksa isi tabel serta izin trigger sebelum memanggil `migrate --force` untuk memuat `database/schema/mysql-schema.sql`. Baseline mencakup 40 tabel asal, mutex transaksi, foreign key, CHECK, index unik bersyarat dan 107 trigger bisnis. Nama migrasi historis tercatat sehingga migrasi SQLite tidak dijalankan ulang. Perintah dapat mengulang import yang terhenti hanya jika seluruh tabel dan riwayat migrasi kosong, selain baris mutex bawaan. Data yang sudah ada atau riwayat migrasi parsial ditolak tanpa import ulang; schema lengkap tidak dimuat ulang.
- Akun aplikasi memerlukan hak pada database aplikasinya, termasuk CREATE/ALTER/INDEX/TRIGGER. Saat binary log aktif, administrator menjalankan `SET PERSIST log_bin_trust_function_creators=ON` sebelum migrasi pertama. Aplikasi tidak memakai root.
- Baseline ditujukan untuk instalasi MySQL kosong. Jangan memakai rollback/refresh migrasi historis SQLite pada MySQL. Perubahan schema berikutnya harus melalui migrasi baru yang mendukung MySQL; jaga baseline dan pengujian kedua driver saat melakukan pembaruan.
- Startup menunggu koneksi sampai 60 detik. `clbk:database-ready --serve-maintenance` mengaktifkan maintenance untuk schema/trigger yang belum lengkap sambil mengizinkan Nginx/FPM dan terminal berjalan untuk pemulihan. `clbk:database-ready` biasa tetap gagal jika guard hilang, dan akses usaha tetap tertutup. Kegagalan koneksi/versi database tetap menghentikan startup. Startup tidak melakukan migrasi, membuat key, seed, atau membuka maintenance otomatis.

## Transaksi dan laporan

`WriteTransaction` mengambil `SELECT ... FOR UPDATE` pada satu baris `database_write_locks` sebelum callback membaca saldo/data usaha. Ini mempertahankan serialisasi penulisan yang sebelumnya disediakan SQLite BEGIN IMMEDIATE. Konsekuensinya, penulisan transaksi usaha masih berjalan bergantian; pergantian engine bukan klaim peningkatan throughput. Konflik MySQL 1205/1213 dicoba ulang maksimal tiga kali, dan perubahan yang mungkin sudah commit tidak diulang setelah kegagalan afterCommit.

MySQL memakai REPEATABLE READ dan strict mode. CHECK dan trigger mempertahankan snapshot harga, rekonsiliasi, histori append-only, serta batas saldo. Index unik bersyarat dipetakan ke functional unique indexes. Query laporan menyesuaikan DATEDIFF, GROUP_CONCAT, tanggal pembalikan WIB, dan ekstraksi teks JSON.

Kolasi biner mempertahankan pembandingan case-sensitive pada enum/index unik. Grammar query MySQL menjaga pencarian LIKE tetap tidak membedakan huruf besar-kecil dan menganggap backslash literal, sehingga pencarian pengguna tetap bekerja. Key cache/lock memuat sampai 512 karakter agar key pembatas login yang berisi email panjang dan IPv6 tidak terpotong.

## Backup dan restore

Backup membaca MySQL dalam satu snapshot transaction lalu menyimpan semua baris ke wadah SQLite privat di ZIP. Tidak ada service SQLite produksi. Wadah tersebut mempertahankan format arsip, pemeriksaan integritas/FK, rekonsiliasi metrik dan bukti privat yang sudah ada. HMAC menandatangani driver dan fingerprint baseline MySQL selain file migrasi.

Restore mensyaratkan maintenance dan worker/web dihentikan. Pada MySQL baru, jalankan initializer dahulu tanpa seed/bootstrap. Pada MySQL yang berisi data, safety backup harus berhasil. Isi tabel diganti dalam transaksi, FK/hash/metrik direkonsiliasi sebelum commit, trigger dikembalikan dalam finally, sesi/token lama dicabut, dan maintenance tetap aktif. DDL pelepasan/pembuatan trigger tidak atomik; jika proses terbunuh, perbaiki guard atau pulihkan ke resource baru sebelum membuka aplikasi. Pemeriksaan readiness normal menolak guard yang hilang; startup hanya menyediakan maintenance dan terminal untuk pemulihan.

Arsip SQLite lama tidak dikonversi otomatis ke MySQL. Pengguna memilih database awal kosong pada perubahan ini.

## Pengujian lokal

Suite standar tetap menggunakan SQLite sementara. Suite MySQL memerlukan server MySQL **8.4**, database kosong khusus bernama **clbk_mysql_test**, dan klien `mysql` pada PATH. Bootstrap suite menolak hostname nonlokal atau nama database operasional karena tes menghapus tabel. Gunakan pengguna khusus tes dengan hak hanya pada database tersebut; aktifkan pengaturan trigger seperti produksi.

Contoh PowerShell, setelah database tes dibuat (password hanya dimasukkan pada prompt):

```powershell
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3306'
$env:DB_USERNAME = 'clbk_test'
$testCredential = Get-Credential -UserName 'clbk_test' -Message 'Password database MySQL khusus pengujian'
$env:DB_PASSWORD = $testCredential.GetNetworkCredential().Password
php vendor/bin/phpunit --configuration phpunit.mysql.xml
Remove-Item Env:DB_PASSWORD
```

Suite MySQL mencakup autentikasi, otorisasi, alur satu hari kerja, timbang/penjualan langsung, pembayaran, laporan, constraint/trigger, row lock, startup maintenance, serta backup/restore. Suite standar tetap memeriksa migrasi historis SQLite dan konkurensi berbasis file. Pengujian ini tidak menggantikan build image serta uji domain/SMTP/storage pada Coolify.

## Hasil verifikasi 16 Oktober 2026

- MySQL 8.4.8 pada filesystem Linux yang membedakan huruf besar/kecil, PHP 8.5.4: **190 tes, 5.218 assertion, seluruhnya lulus**. Sebanyak 252 file PHP/SQL salinan uji cocok byte-per-byte dengan workspace.
- PHP 8.4.25 Windows dengan MySQL 8.4.8: pemeriksaan constraint, row lock, readiness, pencarian, rate limiter, serta backup/restore berhasil. Uji backup terakhir mencakup bukti privat, deskripsi 1.000 karakter, ID yang sudah dihapus, pencabutan sesi, dan pemasangan kembali trigger. Jalur restore gagal juga diuji.
- Regresi SQLite pada PHP 8.4.25: **354 tes, 8.637 assertion lulus**; delapan tes khusus MySQL dilewati. Sesudah koreksi path Inertia, 12 tes laporan/1.537 assertion juga lulus pada SQLite.
- Build frontend, TypeScript, Pint, sintaks shell startup, serta `git diff --check` lulus.
- Path pengujian Inertia menggunakan `resources/js/pages`, sesuai nama folder sebenarnya pada Linux.

Docker Engine tidak tersedia untuk build/run image dalam sesi ini. Resource MySQL/Coolify produksi belum dibuat atau diubah dari sesi ini; build image, env rahasia, jaringan privat, domain/SMTP, mount disk nyata, dan penerimaan pengguna tetap perlu dijalankan mengikuti DEPLOY.md.
