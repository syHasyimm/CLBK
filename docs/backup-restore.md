# Backup dan pemulihan CLBK TOKE

Cadangan ZIP berisi snapshot SQLite melalui VACUUM INTO (menangani WAL), seluruh file bukti yang dirujuk snapshot, serta manifest dengan SHA256 tiap file, hash logis/totals setiap tabel usaha, saldo kas, utang konsumen/upah, piutang, stok historis, dan versi migrasi. Manifest ditandatangani HMAC memakai APP_KEY. Backup ditolak bila bukti hilang, integritas/FK gagal, disk minimum tidak cukup, atau operasi lain memegang lock.

## Lokasi dan hak akses

Tetapkan BACKUP_PATH absolut di luar public dan repository. Pada produksi volumenya harus berbeda dari volume DB; path di disk sama ditolak. Direktori hanya dapat dibaca/ditulis akun service dan pengelola backup. Arsip mengandung data pribadi serta hash password: enkripsi volume/offsite, batasi ACL, dan jangan mengunggah ke penyimpanan publik. APP_KEY disimpan terpisah di vault/backup secret yang aman; tidak dimasukkan ZIP. Mesin pengganti membutuhkan APP_KEY yang sama untuk verifikasi dan data terenkripsi.

Manajer membuat/men-download cadangan pada Pengaturan Usaha dengan autentikasi ulang. Manajer mengatur jumlah harian (1–90) dan mingguan (1–52). Restore hanya melalui terminal pengelola server, bukan HTTP, agar service dapat dihentikan dengan benar. Akses terminal, lokasi ZIP, dan APP_KEY hanya untuk pengelola yang berwenang.

## Jadwal dan retention

Scheduler berjalan setiap menit; job backup pukul BACKUP_TIME (default 23.00 Asia/Jakarta), tanpa overlap. `php artisan clbk:backup --scheduled` membuat backup lalu menyisakan default tujuh hari snapshot terbaru dan satu snapshot per minggu untuk empat minggu. Batas dapat diubah manajer. Backup manual, pre-upgrade, serta pre-restore tidak dihapus otomatis; arsipkan terpisah setelah pengelola memverifikasinya. File parsial tidak diterbitkan sebagai backup.

```sh
php artisan clbk:backup --reason=pre-upgrade
php artisan clbk:restore /mnt/clbk-backups/FILE.zip --verify-only
php artisan clbk:doctor --json
```

Pantau exit code nonzero, backup-scheduler.log, error log, heartbeat scheduler, umur cadangan ≤24 jam, serta kapasitas data/backup/temp/log. Salin hasil terverifikasi ke offsite dengan kontrol akses; mount berbeda saja belum melindungi kehilangan seluruh mesin. Kegagalan backup harus ditangani sebelum RPO 24 jam terlewati.

## Restore pada server yang sama

1. Pilih ZIP dan kode release yang cocok. `--verify-only` memeriksa HMAC, ukuran/hash, integritas/FK serta semua data snapshot tanpa mengganti DB.
2. Aktifkan `php artisan down`, hentikan scheduler, worker dan PHP-FPM/IIS agar tidak ada request lama yang masih memegang koneksi. CLI harus memakai akun service dan konfigurasi yang sama. Jangan menyalin DB aktif atau menghapus file WAL secara manual.
3. Jalankan `php artisan clbk:restore /lokasi/FILE.zip --force`. Flag menegaskan bahwa operator sudah menghentikan proses. Tanpa maintenance restore ditolak. Sebelum perubahan, kondisi saat ini wajib berhasil dicadangkan ke ZIP safety.
4. Bukti dipulihkan dan DB diganti menggunakan SQLite Backup API. Hasil diperiksa ulang terhadap hash logis semua tabel usaha dan seluruh metrik, termasuk harga snapshot/histori stok. Session, reset token, remember token seluruh pengguna dan cache lama dibersihkan; satu audit restore ditambahkan setelah rekonsiliasi. SESSION_DRIVER wajib database agar pencabutan menyeluruh dapat dipastikan. File bukti ekstra dari kondisi sebelum restore dipertahankan untuk safety, bukan dihapus diam-diam.
5. Maintenance tetap aktif. Verifikasi jumlah dokumen, kas/utang/piutang, laporan dan akun aktif/role/penugasan mobil melalui terminal. Backup mengembalikan akun dan hash kata sandi pada waktu snapshot: terapkan kembali penonaktifan/perubahan hak akses setelah tanggal cadangan dan reset kata sandi yang perlu dicabut sebelum membuka aplikasi. Tutup akses pengguna umum di reverse proxy, hidupkan PHP-FPM, lalu jalankan `php artisan up` untuk uji login/cetak/unduhan oleh operator terbatas. Setelah benar, buka akses umum dan aktifkan kembali scheduler/worker. Catat durasi; target pemulihan ≤2 jam setelah mesin tersedia. Jika gagal, aktifkan maintenance kembali dan gunakan ZIP safety dengan kode yang cocok.

## Mesin pengganti

Siapkan PHP 8.4+ (pdo_sqlite, sqlite3, zip, fileinfo, mbstring, curl, openssl), release yang identik, APP_KEY serta konfigurasi service, folder DB/private dan volume backup. DB tujuan harus belum ada atau benar-benar kosong. Setelah maintenance aktif, gunakan `clbk:restore FILE.zip --new-server --force`; opsi ini menolak DB nonkosong dan tidak melewati backup safety pada data yang sudah ada. Tidak perlu menjalankan migrasi/seed sebelum restore; lakukan upgrade pada langkah terpisah setelah hasil dipastikan benar.

ZIP dari versi migrasi/kode migrasi berbeda ditolak. Kembali ke release yang cocok sebelum pemulihan; jangan mengedit manifest atau memaksa restore lintas versi. Metode ini tidak mengganti prosedur menjaga salinan source release dan secret.
