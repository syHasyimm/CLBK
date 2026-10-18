# Pengujian filter tanggal dan sesi

Suite `WeighingDateFilterTest`, `SeedDateFilterCommandTest`, dan `Auth/WeighingSessionTest` memakai database MySQL khusus `clbk_mysql_test`.

```powershell
php vendor/bin/phpunit --filter WeighingDateFilterTest
php vendor/bin/phpunit --filter SeedDateFilterCommandTest
php vendor/bin/phpunit --filter WeighingSessionTest
```

Untuk pemeriksaan browser pada database development `clbk` yang sudah dimigrasikan:

```powershell
php artisan db:seed
```

Seeder menyediakan mobil **BK 2026 UAT**, konsumen **Konsumen Uji Filter Tanggal**, anggota muat, tiga akun development, dan lima tiket. Seeder hanya diizinkan pada lingkungan local/testing dan menambahkan data contoh ke database development. Pengulangan tidak menggandakan tiket atau mengubah password.

| Waktu timbang WIB | Filter 10–12 Oktober 2026 |
| --- | --- |
| 9 Oktober, 23:59:59 | Tidak termasuk |
| 10 Oktober, 00:00:00 | Batas awal, termasuk |
| 11 Oktober, 12:00:00 | Termasuk |
| 12 Oktober, 23:59:59 | Batas akhir, termasuk |
| 13 Oktober, 00:00:00 | Tidak termasuk |

Masuk dengan akun dari [README](../README.md), buka Timbang, pilih mobil fixture, lalu terapkan rentang tanggal tersebut. Hasil yang benar adalah tiga tiket. Tanggal mengikuti WIB. Jalankan ulang fixture pada hari pemeriksaan untuk menerbitkan harga hari itu.
