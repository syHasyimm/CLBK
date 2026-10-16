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

Migrasi `2026_10_17_000001_create_clbk_schema.php` memasang `database/schema/clbk.sql` melalui PDO, termasuk trigger. Migrasi hanya boleh berjalan pada database tanpa data usaha dan tidak bergantung pada klien CLI MySQL. Setelah migrasi, jalankan `php artisan db:seed` dan buat akun manajer melalui `clbk:bootstrap-user` atau akun contoh melalui `RoleUserSeeder`.

Rollback skema awal ditolak agar tabel usaha tidak terhapus. `migrate:fresh` hanya digunakan pada database tes yang boleh dihapus. Perubahan skema selanjutnya harus ditulis sebagai migrasi baru.

`clbk:mysql-initialize` membantu melanjutkan instalasi kosong yang terhenti dan mempertahankan maintenance. `clbk:database-ready` memeriksa versi, kelengkapan schema/trigger, dan akun awal. `clbk:doctor --json` memeriksa integritas, backup, kapasitas, dan scheduler.

## Pengujian

`phpunit.xml` menjalankan seluruh suite pada `clbk_mysql_test`. `tests/mysql-bootstrap.php` membaca `.env.testing`, mengizinkan penggantian kredensial melalui environment shell, menolak host nonlokal/nama database lain, dan memakai folder storage sementara. Database aplikasi `clbk` tidak dipakai oleh suite.

Untuk workspace Windows ini, MySQL WSL dijalankan dengan `scripts/mysql-local.sh`. Data server berada di luar proyek dan tidak dihapus oleh pembersihan source.
