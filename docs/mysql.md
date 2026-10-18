# MySQL lokal

Aplikasi menggunakan MySQL **8.4**, InnoDB, strict mode, charset `utf8mb4`, collation `utf8mb4_0900_bin`, dan isolasi `REPEATABLE READ`.

Uang dan gram disimpan sebagai `BIGINT`. Validasi request/domain menolak pecahan gram sebelum penulisan; pengujian juga memeriksa batas nilai dan rekonsiliasi di database. MySQL membulatkan input pecahan saat konversi ke tipe integer, sehingga penolakan pecahan harus diperiksa pada input aplikasi.

## Persiapan

Administrator membuat database aplikasi dan pengujian secara terpisah:

```sql
CREATE DATABASE clbk CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_bin;
CREATE DATABASE clbk_mysql_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_bin;
CREATE USER 'clbk'@'localhost' IDENTIFIED BY 'ganti-password-aplikasi';
GRANT ALL ON clbk.* TO 'clbk'@'localhost';
CREATE USER 'clbk_test'@'localhost' IDENTIFIED BY 'ganti-password-pengujian';
GRANT ALL ON clbk_mysql_test.* TO 'clbk_test'@'localhost';
```

Sesuaikan host akun jika koneksi Windows ke WSL memakai alamat host berbeda. Jika binary log aktif, administrator menjalankan:

```sql
SET PERSIST log_bin_trust_function_creators=ON;
```

Isi `.env` untuk aplikasi dan `.env.testing` untuk pengujian. Jangan menyimpan kredensial dalam Git. Setelah mengubah `.env`, jalankan `php artisan config:clear`.

## Skema awal

Seluruh definisi berada di `database/migrations/`, tanpa memuat schema dump SQL:

- `2026_10_18_000000_*`: pemeriksaan MySQL 8.4 dan persyaratan binary log untuk pemasangan trigger sebelum membuat tabel.
- `2026_10_18_000001_*` sampai `000040_*`: satu migration per tabel menggunakan `Schema::create`, termasuk kolom, indeks, dan check constraint. Tipe uang/gram, nullability, default, dan collation tetap sama.
- `2026_10_18_000100_*`: foreign key setelah semua tabel tersedia. Urutan ini menangani relasi dua arah `users` dan `vehicles` tanpa mematikan foreign key checks.
- `2026_10_18_000200_*` sampai `000222_*`: trigger dikelompokkan per tabel. SQL khusus MySQL untuk check constraint, expression index, dan trigger ditulis langsung di migration PHP; tidak ada file SQL eksternal atau proses impor melalui CLI.

Untuk database produksi baru, dari direktori proyek jalankan:

```sh
php artisan migrate --force
php artisan db:seed --force
php artisan clbk:bootstrap-user
php artisan clbk:database-ready
php artisan up
```

Di lokal, `--force` boleh dihilangkan. `php artisan db:seed` menjalankan `DatabaseSeeder`: data dasar, tiga akun contoh, lalu data uji filter tanggal pada `local`/`testing`. Pada produksi, hanya data dasar yang dibuat; akun awal dibuat melalui `clbk:bootstrap-user`.

Untuk database lama yang telah mencatat `2026_10_17_000001_create_clbk_schema`, buat backup lebih dahulu, lalu jalankan `php artisan down`, `php artisan migrate --force`, `php artisan clbk:database-ready`, dan `php artisan up` secara berurutan hanya jika langkah sebelumnya berhasil. Migration baru memverifikasi fingerprint struktur lama (kolom, indeks, foreign key, check constraint, dan trigger) lalu mencatat migration per tabel tanpa membuat ulang tabel atau mengubah data. Riwayat migration lama tetap disimpan. Jika struktur lama berbeda atau tidak lengkap, migrasi berhenti; jangan menghapus riwayat atau menggunakan `migrate:fresh` untuk melewati pemeriksaan.

Rollback skema awal ditolak agar tabel usaha tidak terhapus. `migrate:fresh` hanya digunakan pada database tes yang boleh dihapus. Perubahan skema selanjutnya harus ditulis sebagai migrasi baru.

`clbk:mysql-initialize` adalah alat pemulihan instalasi kosong, bukan pengganti pemanggilan migration normal. Perintah mempertahankan maintenance dan menolak pemulihan ulang pada database yang memiliki data usaha. Jika belum ada riwayat migration, tabel kosong sisa instalasi gagal dapat dibuat ulang; jika sebagian migration per tabel sudah tercatat, hanya migration yang tersisa yang dijalankan. Schema lengkap tidak diubah. `clbk:database-ready` memeriksa versi, kelengkapan schema/trigger, dan akun awal. `clbk:doctor --json` memeriksa integritas, backup, kapasitas, dan scheduler.

Backup mencatat fingerprint file migration. Setelah berpindah ke migration per tabel, buat backup baru. Untuk memulihkan arsip dari versi lama, gunakan versi source yang sama dengan arsip tersebut terlebih dahulu, lalu jalankan prosedur migrasi di atas. Pemeriksaan kecocokan backup tetap aktif.

## Pengujian

`phpunit.xml` menjalankan seluruh suite pada `clbk_mysql_test`. `tests/mysql-bootstrap.php` membaca `.env.testing`, mengizinkan penggantian kredensial melalui environment shell, menolak host nonlokal/nama database lain, dan memakai folder storage sementara. Database aplikasi `clbk` tidak dipakai oleh suite.

Untuk workspace Windows ini, MySQL WSL dijalankan dengan `scripts/mysql-local.sh`. Data server berada di luar proyek dan tidak dihapus oleh pembersihan source.
