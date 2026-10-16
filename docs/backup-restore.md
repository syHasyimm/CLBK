# Backup dan pemulihan CLBK TOKE

Produksi menggunakan MySQL 8.4. Cadangan ZIP berisi snapshot MySQL dari satu read transaction konsisten, seluruh file bukti yang dirujuk snapshot, serta manifest dengan SHA256 tiap file, hash logis/totals setiap tabel usaha, saldo kas, utang konsumen/upah, piutang, stok historis, dan versi migrasi/schema. Manifest ditandatangani HMAC memakai APP_KEY. Backup ditolak bila bukti hilang, integritas/FK gagal, disk minimum tidak cukup, atau operasi lain memegang lock. Wadah snapshot di ZIP tetap bernama `database.sqlite` untuk verifikasi portabel; itu bukan database operasional. Development SQLite tetap memakai VACUUM INTO.

## Lokasi dan hak akses

Tetapkan BACKUP_PATH absolut di luar public dan repository, terpisah dari folder bukti privat. Backup tidak boleh memakai folder yang sama, menjadi induk, atau berada di dalam folder bukti. Direktori hanya dapat dibaca/ditulis akun service dan pengelola backup. Arsip mengandung data pribadi serta hash password: enkripsi volume/offsite, batasi ACL, dan jangan mengunggah ke penyimpanan publik. APP_KEY disimpan terpisah di vault/backup secret yang aman; tidak dimasukkan ZIP. Mesin pengganti membutuhkan APP_KEY yang sama untuk verifikasi dan data terenkripsi.

Pilihan Hostinger saat ini memakai **folder backup lokal tersendiri**, dengan `BACKUP_REQUIRE_SEPARATE_DISK=false`. Image Coolify dan templatenya sudah memakai mode ini. Mount host `/srv/clbk/backups` ke `/backups`; folder host lama seperti `/mnt/clbk-backups/archives` juga boleh dipertahankan. Mount `/data` dan `/backups` tetap wajib persisten dengan folder sumber berbeda. Backup boleh berada pada disk yang sama, tetapi ikut hilang jika disk/VPS rusak. Pengiriman ke luar VPS tidak otomatis disediakan oleh mode ini.

Jika memilih `BACKUP_REQUIRE_SEPARATE_DISK=true`, pada produksi perangkat backup harus berbeda dari file privat aplikasi. Ini tetap menjadi default konfigurasi di luar image Coolify bila env tidak diisi. Perangkat serta kapasitas volume MySQL harus diperiksa pada resource/server database agar backup juga terpisah dari database. Pemisahan mount saja belum membuktikan adanya disk fisik berbeda.

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
4. Bukti dipulihkan. Pada MySQL, schema tujuan harus sudah dimigrasi; data diganti dalam satu transaksi dan diperiksa terhadap hash logis semua tabel usaha/metrik. Trigger dilepas sebelum transaksi lalu dipasang kembali, termasuk ketika transaksi gagal. DDL MySQL tidak transaksional: jangan menghentikan proses restore; maintenance harus tetap aktif bila proses terputus. Jalankan `clbk:database-ready` dan doctor untuk memeriksa guard sebelum membuka layanan. Session, reset token, remember token seluruh pengguna dan cache lama dibersihkan; satu audit restore ditambahkan setelah rekonsiliasi. SESSION_DRIVER wajib database. Bukti ekstra saat ini dipertahankan untuk safety. Jalur development SQLite tetap menggunakan SQLite Backup API.
5. Maintenance tetap aktif. Verifikasi jumlah dokumen, kas/utang/piutang, laporan dan akun aktif/role/penugasan mobil melalui terminal. Backup mengembalikan akun dan hash kata sandi pada waktu snapshot: terapkan kembali penonaktifan/perubahan hak akses setelah tanggal cadangan dan reset kata sandi yang perlu dicabut sebelum membuka aplikasi. Tutup akses pengguna umum di reverse proxy, hidupkan PHP-FPM, lalu jalankan `php artisan up` untuk uji login/cetak/unduhan oleh operator terbatas. Setelah benar, buka akses umum dan aktifkan kembali scheduler/worker. Catat durasi; target pemulihan ≤2 jam setelah mesin tersedia. Jika gagal, aktifkan maintenance kembali dan gunakan ZIP safety dengan kode yang cocok.

## Mesin pengganti

Siapkan image PHP produksi dan resource MySQL 8.4 sesuai [DEPLOY.md](../DEPLOY.md), release identik, APP_KEY sumber, storage privat serta backup. Pada MySQL baru, jalankan `migrate --force` untuk menyiapkan schema **tanpa seed/bootstrap**, aktifkan maintenance, lalu `clbk:restore FILE.zip --new-server --force`. Opsi ini menolak database yang sudah berisi data usaha dan tidak melewati safety backup pada data yang sudah ada. Engine backup dan tujuan harus sama; arsip SQLite lama bukan migrasi otomatis ke MySQL. Jalur SQLite development tetap membutuhkan file tujuan yang belum ada/kosong dan tidak memerlukan migrate sebelum restore.

ZIP dari versi migrasi/kode migrasi berbeda ditolak. Kembali ke release yang cocok sebelum pemulihan; jangan mengedit manifest atau memaksa restore lintas versi. Metode ini tidak mengganti prosedur menjaga salinan source release dan secret.
