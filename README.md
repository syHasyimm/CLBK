# CLBK TOKE

Aplikasi web pengelolaan usaha sawit menggunakan Laravel 13, React + Inertia, Tailwind 4, dan **MySQL 8.4**. Aplikasi dibuka melalui browser komputer atau ponsel.

## Development lokal

Prasyarat: PHP 8.4+ dengan `pdo_mysql`, `zip`, `mbstring`, `openssl`, `fileinfo`, dan `curl`; Composer 2; Node.js 22.12+ atau 24; serta MySQL 8.4. Klien `mysql` tidak diperlukan oleh migrasi aplikasi.

1. Siapkan database `clbk` dengan charset `utf8mb4` dan collation `utf8mb4_0900_bin`. Beri pengguna aplikasi hak pada database tersebut, termasuk pembuatan tabel dan trigger.
2. Salin `.env.example` menjadi `.env`, lalu isi `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD`.
3. Jalankan:

```powershell
composer setup
composer dev
```

`composer setup` memasang dependensi, menyiapkan APP_KEY bila belum ada, menjalankan migrasi dan seed awal, lalu membangun frontend. Pengulangan mempertahankan APP_KEY dan data yang sudah ada. Seed awal menyediakan kas usaha tanpa saldo, kategori biaya, dan pengaturan usaha. Pada `local`/`testing`, `DatabaseSeeder` juga memanggil seeder tiga akun contoh dan data uji filter tanggal; pada produksi, hanya data dasar yang dibuat.

Untuk server MySQL 8.4 yang sudah terpasang di WSL, tersedia perintah berikut dari root proyek:

```powershell
wsl --exec sh /mnt/d/Sites/CLBK/scripts/mysql-local.sh start
```

Data server ini berada di `~/.local/share/clbk/mysql` di WSL, terpisah dari checkout. Gunakan argumen `stop` untuk menghentikannya. Script hanya menjalankan server; akun dan database perlu disiapkan sekali. Server lokal yang disiapkan untuk workspace ini memakai `127.0.0.1:3306`; kredensial aplikasi tersimpan dalam `.env` dan kredensial pengujian dalam `.env.testing`.

Jika binary log MySQL aktif, administrator perlu mengaktifkan `log_bin_trust_function_creators` agar pengguna aplikasi dapat memasang trigger. Lihat [konfigurasi MySQL](docs/mysql.md).

## Akun lokal

Untuk menjalankan seluruh aplikasi dalam container lokal beserta MySQL, ikuti [panduan Docker lokal](docs/docker-local.md). Script `scripts/start-local-docker.ps1` menyiapkan container dan data demo secara terpisah dari `.env` development. Email dinonaktifkan pada profil Docker lokal.

Untuk membuat akun manajer sendiri pada database yang belum memiliki pengguna, jalankan sebelum seeder akun contoh:

```powershell
php artisan clbk:bootstrap-user
```

Untuk seluruh data awal development beserta akun contoh, cukup satu perintah (sudah dijalankan oleh `composer setup`):

```powershell
php artisan db:seed
```

| Role | Email | Password lokal |
| --- | --- | --- |
| Manajer | manager@clbk.test | ClbkToke12345! |
| Kasir | kasir@clbk.test | ClbkToke12345! |
| Supir | supir@clbk.test | ClbkToke12345! |

Seeder akun contoh hanya diizinkan pada lingkungan `local` atau `testing`.

## Alur aplikasi

- Manajer mengelola pengguna, konsumen, kendaraan, anggota muat, pabrik, harga, dan pengaturan usaha.
- Supir mencatat timbang dan penjualan menggunakan kendaraan yang ditugaskan. Tiket menyimpan snapshot harga dan perhitungan upah saat disahkan.
- Kasir mengelola pembayaran konsumen, upah muat, penerimaan penjualan, dan pengeluaran melalui satu kas usaha.
- Dashboard dan laporan mencakup transaksi, kas, utang, piutang, upah, dan audit; laporan dapat diekspor sebagai CSV atau dicetak.
- Dokumen sah dan audit dilindungi constraint serta trigger. Koreksi memakai pembalikan sehingga riwayat tetap tersimpan.

Seluruh penulisan usaha memakai `WriteTransaction`, yang mengambil row lock pada `database_write_locks` sebelum membaca saldo. Konflik MySQL 1205/1213 dicoba ulang; efek eksternal ditempatkan setelah commit. Laporan memakai snapshot `REPEATABLE READ`.

## Pengujian

Notifikasi WhatsApp hasil timbangan dan pembayaran tersedia melalui Fonnte. Manajer dapat memasukkan token terenkripsi dan menguji koneksi melalui Pengaturan → Notifikasi WhatsApp. Ikuti [panduan aktivasi dan antrean WhatsApp](docs/whatsapp-fonnte.md). Pengiriman nonaktif secara bawaan dan memerlukan aktivasi per konsumen.

Gunakan database khusus **`clbk_mysql_test`**, terpisah dari `clbk`, dengan pengguna `clbk_test` yang hanya memiliki hak pada database pengujian. Salin `.env.testing.example` menjadi `.env.testing`, lalu isi kredensialnya. Bootstrap menolak database dengan nama lain atau host nonlokal karena pengujian menghapus tabel.

```powershell
composer test
npm run build
php vendor/bin/pint --test
```

Suite yang sama menguji MySQL untuk autentikasi, transaksi, konkurensi, constraint/trigger, laporan, dan backup/restore. Pengujian tidak membutuhkan engine database tambahan. Detail fixture tanggal tersedia di [pengujian filter tanggal](docs/date-filter-testing.md).

## Struktur

| Folder | Isi |
| --- | --- |
| `app/` | Action usaha, model, query, policy, dan layanan |
| `database/migrations/` | Migration PHP per tabel, indeks/check constraint, foreign key, dan trigger MySQL |
| `resources/` | Halaman React, komponen, CSS, dan template cetak |
| `routes/` | Rute aplikasi dan scheduler |
| `tests/` | Pengujian unit dan integrasi MySQL |
| `docs/` | Catatan alur bisnis dan panduan pemeliharaan |

Panduan [backup/restore](docs/backup-restore.md) menjelaskan arsip ZIP bertanda tangan yang berisi data MySQL dalam `database.jsonl` dan bukti privat. Untuk menjalankan aplikasi web di Coolify dengan Dockerfile, ikuti [panduan deployment](DEPLOY.md). Image memakai PHP-FPM dan Nginx pada port 8080, database MySQL terpisah, dan volume persisten untuk data serta backup.
