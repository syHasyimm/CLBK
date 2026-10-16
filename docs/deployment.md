# Deployment server web

Panduan utama deployment yang dipilih adalah [Coolify di DEPLOY.md](../DEPLOY.md). Dokumen ini merupakan referensi alternatif untuk pemasangan Linux manual dengan Nginx + PHP-FPM; jangan menjalankan scheduler systemd di bawah bersamaan dengan Scheduled Tasks Coolify. PHP dan SQLite berjalan pada satu server, diakses melalui browser komputer dan ponsel.

**Catatan:** contoh SQLite di dokumen ini adalah referensi historis. Produksi yang dipilih sekarang memakai **MySQL 8.4 dan database awal kosong**; ikuti [DEPLOY.md](../DEPLOY.md) untuk konfigurasi, migrasi, backup, dan restore yang berlaku.

## Tata letak Linux acuan

Gunakan `/srv/clbk/releases/<versi>` untuk kode/aset read-only, `/srv/clbk/current` menunjuk release aktif, `/srv/clbk/shared/database/database.sqlite` untuk SQLite lokal, `/srv/clbk/shared/proofs` untuk bukti privat, `/srv/clbk/shared/storage` untuk session/runtime/log/maintenance, dan mount volume berbeda `/mnt/clbk-backups` untuk ZIP. Akun proses contoh `www-data`; ubah sesuai server. Kode dapat dibaca, sedangkan shared database/private/storage/bootstrap cache dapat ditulis akun proses. Folder DB juga harus writable untuk WAL/SHM. Jangan memakai network share untuk database aktif.

1. Pasang PHP 8.4+ dan ekstensi pdo_sqlite/sqlite3/zip/fileinfo/mbstring/openssl/curl, Composer dan Node versi yang didukung package lock. Pasang dependency berdasarkan lockfile; bangun frontend. Produksi memakai dependency Composer tanpa dev, kode/aset hasil build, bukan Vite atau artisan serve.
2. Siapkan .env dari .env.production.example: origin HTTPS sebenarnya, secret APP_KEY yang dipertahankan lintas release, SMTP, DB_DATABASE, CLBK_PRIVATE_STORAGE, BACKUP_PATH pada volume terpisah, cookie aman serta retensi. Symlink storage runtime ke shared agar maintenance/log tidak hilang ketika berpindah release. Secret tidak masuk paket/source.
3. Pada pemasangan baru jalankan migrasi/seed sekali pada server dan `clbk:bootstrap-user` untuk akun manajer awal. Jangan menjalankan seeder akun local pada produksi.
4. Sesuaikan deploy/nginx.conf.example dan php-fpm.conf.example; pasang sertifikat TLS yang dipercaya browser. Web root hanya public/. PHP hanya dijalankan lewat index.php. Jalur .env/database/bukti/backup/log tidak boleh tersaji publik. Akun service menjalankan server/scheduler.
5. Bila ada reverse proxy, gunakan daftar IP/CIDR eksplisit pada TRUSTED_PROXIES, pertahankan Host canonical dan timpa forwarded headers dari klien. Jangan gunakan wildcard. Lihat keamanan-pra-launch.md.
6. Sesuaikan/pasang unit scheduler dan timer ke lokasi systemd server, aktifkan timer per menit. Alternatif cron: satu invocation `php /srv/clbk/current/artisan schedule:run` setiap menit, dengan working directory dan user yang benar. Jangan memasang keduanya sekaligus. Pantau clbk:doctor dan backup harian/offsite.
7. Bangun cache konfigurasi/route/view setelah environment lengkap. Verifikasi HTTPS, login tiga role, CSRF, cetak/CSV/unduh, backup dan simulasi restore sebelum menyatakan siap produksi. Ukur p95 list/dashboard/posting terhadap 10.000 tiket di mesin target.

## Pemasangan awal Linux

Contoh memakai akun service `www-data`, release `/srv/clbk/releases/0.1.0`, dan PHP-FPM `php8.4-fpm`; sesuaikan nama service/binary dengan distro. Jalankan pada release baru yang belum berisi data operasional. Source release berasal dari checkout yang sudah ditinjau, bukan ZIP backup.

1. Di checkout build yang bersih jalankan `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`, `composer check-platform-reqs --no-dev`, `npm ci`, lalu `npm run build`. Deploy kode, vendor, dan public/build sebagai satu release. Jangan kirim .env, database lokal, .cache, node_modules atau storage lokal. Sediakan ekstensi sqlite3 dan zip walaupun pemeriksaan Composer tidak menyatakannya sebagai dependency langsung.
2. Mount volume cadangan dan pastikan perangkatnya berbeda lewat `findmnt -T /mnt/clbk-backups` dan `findmnt -T /srv/clbk/shared/database`. Persiapkan folder dengan mode 0750, pemilik service untuk database/proofs/storage dan backup; mode file DB/ZIP 0600. Operator deployment memiliki kode; service hanya membaca kode serta menulis bootstrap/cache dan folder shared. Jangan gunakan chmod 777.
3. Siapkan shared storage: `app`, `framework/cache/data`, `framework/sessions`, `framework/views`, dan `logs`. Pada release baru, pindahkan direktori storage bawaan ke storage.dist, lalu symlink `/srv/clbk/shared/storage` sebagai storage. Symlink `.env` ke `/srv/clbk/shared/.env`; isi dari `.env.production.example`. Batasi .env 0640 dengan group service. APP_KEY baru dibuat sekali pada pemasangan kosong, lalu disimpan terpisah secara aman; mesin lama memakai key yang sudah ada.
4. Isi APP_URL dengan origin HTTPS final, SMTP nyata, path DB/private dan volume backup. Buat file SQLite kosong dengan owner service. Jalankan sebagai service dari direktori release:

```sh
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan db:seed --force
sudo -u www-data php artisan clbk:bootstrap-user
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan clbk:backup --reason=initial-deploy
```

5. Arahkan `/srv/clbk/current` ke release. Pasang pool `deploy/php-fpm.conf.example` dan virtual host `deploy/nginx.conf.example` dengan domain/TLS sebenarnya. Validasi `php-fpm8.4 -t` serta `nginx -t` sebelum reload service. Nama binary dapat berbeda pada distro lain. Uji dengan `curl --fail https://DOMAIN/up`. Akses .env, SQLite, ZIP backup, dan folder private melalui web harus ditolak.
6. Salin clbk-scheduler.service/timer ke `/etc/systemd/system`, jalankan `systemd-analyze verify` untuk kedua unit, `systemctl daemon-reload`, kemudian `systemctl enable --now clbk-scheduler.timer`. Setelah satu menit, jalankan `sudo -u www-data php artisan clbk:doctor --json`; exit code harus 0. Unit scheduler dan PHP-FPM memakai konfigurasi serta path shared yang sama. Jangan membuka berkas SQLite secara langsung dari perangkat pengguna.

Konfigurasi Nginx/PHP-FPM/systemd belum diuji pada daemon Linux di sesi Windows ini. Pemeriksaan syntax dan akses nyata pada mesin target adalah langkah wajib sebelum aktivasi produksi. Hasil backend/build lokal tidak menggantikannya.

## Pemantauan

Jalankan `clbk:doctor --json` dari monitor eksternal setiap 5 menit dengan akun service. Nonzero harus memicu notifikasi operator; tentukan penerima/kanal saat penyiapan server. Pemeriksaan mencakup quick_check/FK SQLite, kapasitas disk data, verifikasi isi backup terbaru, umur backup 24 jam, dan heartbeat scheduler 5 menit. Verifikasi arsip membaca seluruh isinya; sesuaikan interval dan timeout monitor terhadap ukuran data sebenarnya.

Pantau juga kapasitas volume backup dan pertumbuhan log melalui monitor sistem. Periksa `storage/logs/backup-scheduler.log`, log Laravel harian, `journalctl -u clbk-scheduler.service`, dan log PHP-FPM/Nginx. Kegagalan transaksi database, termasuk konflik SQLite yang kehabisan retry, dilaporkan melalui pelaporan exception aplikasi. Rotasi output backup-scheduler.log melalui logrotate dengan owner service; jangan merotasi ZIP dengan menghapus file di luar aturan retensi aplikasi. LOG_LEVEL=warning tetap menyimpan kegagalan backup, sementara keberhasilan dapat dilihat dari arsip/monitor. Salin backup terverifikasi ke offsite terenkripsi dan uji pemulihannya berkala.

## Upgrade dan rollback

Backup pre-upgrade harus berhasil dan terverifikasi. Maintenance harus berada pada shared storage; hentikan request/worker/scheduler. Siapkan release baru terpisah, gunakan DB/proof/APP_KEY yang sama, migrasi hanya sekali, perbarui cache dan restart worker. Jalankan rekonsiliasi serta smoke test sebelum membuka maintenance. Jika migrasi sudah mengubah data, mengembalikan symlink kode saja bukan rollback: gunakan release lama dan ZIP pre-upgrade, ikuti backup-restore.md. Catat nomor release, checksum paket, DB/migrasi, waktu dan operator.
