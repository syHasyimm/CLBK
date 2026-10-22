# Container lokal

Jalankan Docker Desktop, lalu dari PowerShell di root proyek:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\start-local-docker.ps1
```

Script membangun image dan menjalankan aplikasi serta MySQL 8.4. Default aplikasi **http://localhost:8088/login**. Jika port sudah dipakai pada setup pertama, script memilih port berikutnya dan menampilkan URL yang digunakan. Container layanan yang sudah dihapus dari konfigurasi dibersihkan otomatis saat script dijalankan ulang.

Database `clbk_local` dan volume project `clbk-local` terpisah dari `.env`, database development lain, dan VPS. MySQL tidak dipublikasikan ke host. Aplikasi hanya menerima koneksi dari komputer lokal (`127.0.0.1`). Profil lokal menggunakan `MAIL_ENABLED=false` dan `MAIL_MAILER=array`, sehingga email dinonaktifkan dan tidak membutuhkan SMTP. Image produksi juga menonaktifkan email secara default.

`.env.docker.local` dibuat sekali dengan APP_KEY dan password MySQL acak. File ini diabaikan Git dan build Docker. Simpan file tersebut bersama volume; jangan menghapusnya untuk melakukan restart. Kunci tidak diganti ketika script dijalankan ulang.

Profil lokal menggunakan `APP_ENV=local` dan cookie HTTP lokal. Dockerfile/entrypoint produksi tetap mewajibkan konfigurasi produksi yang aman; profil lokal ini tidak digunakan di Coolify.

Port eksternal pada `APP_URL` juga diteruskan ke PHP-FPM agar URL aset Vite dan route Ziggy memakai origin yang sama, misalnya `http://localhost:8088`. Setelah mengubah port atau konfigurasi Docker, jalankan kembali script start untuk rebuild dan membuat ulang container aplikasi; me-refresh browser saja tidak menerapkan konfigurasi image baru.

Script start memeriksa bahwa URL JavaScript, CSS, dan Ziggy memakai origin yang sama dan seluruh aset dapat dimuat dengan HTTP 200. Pemeriksaan ini juga dapat dijalankan sendiri dengan `powershell -ExecutionPolicy Bypass -File .\scripts\check-local-docker-assets.ps1`.

## Akun dan data awal

Ketika volume baru diinisialisasi, script menjalankan `php artisan db:seed` melalui `DatabaseSeeder` untuk menyiapkan seluruh data awal:

| Role | Email | Kata sandi demo lokal |
| --- | --- | --- |
| Manajer Toke | manager@clbk.test | ClbkToke12345! |
| Supir | supir@clbk.test | ClbkToke12345! |
| Kasir | kasir@clbk.test | ClbkToke12345! |

Tersedia mobil `BK 2026 UAT`, konsumen dan anggota muat uji, serta lima tiket pada 9–13 Oktober 2026. Filter tanggal **10–12 Oktober 2026** menghasilkan tiga tiket. Seeder hanya dijalankan saat setup awal; menjalankan ulang script mempertahankan akun, perubahan data, dan volume yang sudah ada.

Untuk menjalankan seluruh seeder secara manual, cukup satu perintah dari root proyek:

```powershell
docker compose --env-file .env.docker.local -f compose.local.yaml exec --user www-data app php artisan db:seed
```

Tidak perlu memanggil setiap kelas seeder secara terpisah. Pengulangan mempertahankan akun dan tiket yang sudah dibuat; jika akun/referensi fixture telah diubah atau dinonaktifkan, seeder melaporkan konflik tanpa menimpanya. Profil produksi hanya mengisi data dasar, tanpa akun atau transaksi demo.

## Mengelola container

Perintah berikut dijalankan dari root proyek:

```powershell
# Status
docker compose --env-file .env.docker.local -f compose.local.yaml ps

# Log aplikasi
docker compose --env-file .env.docker.local -f compose.local.yaml logs --tail 100 app

# Hentikan tanpa menghapus database atau berkas
docker compose --env-file .env.docker.local -f compose.local.yaml stop

# Jalankan kembali container yang sama
docker compose --env-file .env.docker.local -f compose.local.yaml start

# Terminal aplikasi
docker compose --env-file .env.docker.local -f compose.local.yaml exec --user www-data app sh
```

Sesudah mengubah source, jalankan kembali script setup untuk membangun image terbaru dan menjalankan migration yang belum tercatat. Source tidak di-mount sebagai live code; perubahan PHP/React membutuhkan rebuild.

Data tersimpan pada volume `clbk-local_mysql-data`, `clbk-local_app-data`, dan `clbk-local_backups`. Jangan menggunakan `down --volumes`, `docker volume prune`, atau `migrate:fresh` jika data lokal ingin dipertahankan.

Panduan produksi terpisah di [DEPLOY.md](../DEPLOY.md).
