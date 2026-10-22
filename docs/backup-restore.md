# Backup dan pemulihan

Cadangan ZIP format 2 berisi `database.jsonl`, bukti privat, `manifest.json`, dan tanda tangan HMAC dengan APP_KEY. JSONL menyimpan tabel, kolom, urutan ID berikutnya, serta baris MySQL; string memakai base64 untuk mempertahankan isi byte. Ekspor membaca satu snapshot konsisten dan memeriksa foreign key. Manifest memuat checksum, jumlah/hash baris, saldo kas, utang, piutang, stok, dan fingerprint migrasi/skema.

Tetapkan `BACKUP_PATH` absolut, privat, terpisah dari folder bukti. Default lokal adalah `storage/app/backups`; folder publik atau folder yang bertumpang tindih dengan bukti ditolak. `BACKUP_REQUIRE_SEPARATE_DISK=true` mewajibkan volume berbeda saat lingkungan production. APP_KEY perlu disimpan terpisah dari ZIP.

Manajer membuat dan mengunduh cadangan melalui Pengaturan Usaha dengan autentikasi ulang. Retensi harian dan mingguan dapat diatur di halaman tersebut. Scheduler menyediakan backup pada `BACKUP_TIME`; saat diperlukan, jalankan `php artisan schedule:work` pada terminal terpisah.

## Verifikasi

```powershell
php artisan clbk:backup
php artisan clbk:restore D:/Cadangan/FILE.zip --verify-only
php artisan clbk:doctor --json
```

Verifikasi memeriksa tanda tangan, checksum, struktur data, hash baris, metrik, dan kelengkapan bukti tanpa mengubah database.

## Restore

1. Aktifkan maintenance dengan `php artisan down`, lalu hentikan proses web, scheduler, dan worker yang menulis data.
2. Jalankan `php artisan clbk:restore D:/Cadangan/FILE.zip --force`. Data saat ini dicadangkan terlebih dahulu sebagai safety backup.
3. Restore mengganti data dalam transaksi, memeriksa foreign key dan rekonsiliasi sebelum commit, lalu memasang kembali trigger. Trigger juga dipasang kembali ketika transaksi gagal. Sesi, reset token, remember token, dan cache lama dicabut; audit restore ditambahkan. Pengiriman WhatsApp dinonaktifkan, antrean WA yang dipulihkan dibersihkan, dan pesan belum pasti/belum terkirim dikarantina agar tidak terkirim ulang dari backup.
4. Maintenance tetap aktif. Periksa `clbk:database-ready`, `clbk:doctor`, saldo, dokumen, bukti, dan akses akun sebelum menjalankan `php artisan up`. Rekonsiliasi riwayat WhatsApp dengan Fonnte sebelum mengaktifkan kembali notifikasi dan worker. Pesan karantina tidak dikirim otomatis; lihat [panduan WhatsApp](whatsapp-fonnte.md).

DDL trigger MySQL tidak transaksional. Jika proses terputus, pertahankan maintenance dan perbaiki kelengkapan trigger sebelum membuka aplikasi.

Untuk database tujuan baru: gunakan kode dan APP_KEY yang sama, jalankan migrasi **tanpa seed**, lalu restore dengan `--new-server --force`. Opsi ini menolak database yang berisi data usaha. Arsip dari format atau fingerprint migrasi berbeda ditolak; simpan source dan secret yang sesuai untuk arsip lama.
