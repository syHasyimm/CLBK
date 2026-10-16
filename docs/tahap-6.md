# Tahap 6 PRD — Deployment dan operasional web

Target deployment: **Coolify pada Linux, Nginx + PHP-FPM**, satu server SQLite, diakses melalui browser komputer dan ponsel. Panduan utama untuk pemasangan adalah [DEPLOY.md](../DEPLOY.md). Domain dan akses server produksi belum tersedia saat konfigurasi disusun; build Docker penuh dan verifikasi mesin target tetap diperlukan.

## Hasil implementasi

| Kebutuhan | Hasil | Batas verifikasi |
|---|---|---|
| Akses aplikasi | Browser pada origin HTTPS yang sama; tampilan responsif, session, CSRF dan policy server | Login, form, cetak dan unduhan perlu diuji pada browser perangkat nyata |
| Cetak/unduh | Halaman A4, CSV dan bukti memakai sesi serta otorisasi server | Dialog printer/PDF dan penyimpanan unduhan bergantung perangkat/browser |
| Backup | Snapshot SQLite VACUUM INTO, bukti privat yang dirujuk, manifest HMAC dan SHA256 | WAL, integrity/FK, kelengkapan bukti, key salah, arsip rusak dan traversal diuji otomatis |
| Jadwal/retensi | Backup default pukul 23.00 WIB, tujuh harian/empat mingguan, dapat diubah manajer | Scheduler Coolify dan pengiriman offsite harus diaktifkan pada server |
| Restore | CLI dalam maintenance, safety backup wajib, SQLite Backup API, rekonsiliasi dan pencabutan sesi/token | Pemulihan data representatif dan waktu operator perlu diuji pada server target |
| Deployment | Dockerfile PHP 8.4, build frontend, Nginx/PHP-FPM, pemeriksaan mount dan runtime env | Docker Engine tidak tersedia saat penyusunan; image belum diuji build/run penuh |
| Monitoring | clbk:doctor --json dan script ping monitor; pengecekan DB, backup, disk dan heartbeat | Penerima alarm, dashboard check dan kapasitas backup/offsite harus dikonfigurasi |

Kode aplikasi berada pada image, sedangkan data dan runtime disimpan pada mount persisten. Sesuai pilihan Hostinger terbaru, image Coolify memakai folder backup lokal tersendiri dengan `BACKUP_REQUIRE_SEPARATE_DISK=false`; mount `/data` dan `/backups` tetap wajib memakai folder sumber berbeda. Mode disk terpisah tersedia dengan nilai `true`. Backup lokal tidak melindungi kerusakan disk atau hilangnya VPS; salinan ke luar VPS tetap disarankan dan tidak dikirim otomatis oleh mode lokal ini.

## Pemakaian manajer dan operator

Pada **Pengaturan Usaha**, manajer mengatur retensi, memasukkan kata sandi saat ini, lalu menekan **Buat cadangan sekarang**. Unduhan memerlukan konfirmasi password. Supir/kasir tidak mempunyai akses backup. ZIP tidak boleh disimpan pada public/public disk; simpan APP_KEY terpisah di vault dan salin backup secara terenkripsi ke luar VPS.

Restore dilakukan melalui terminal operator setelah maintenance dan penghentian request/worker/scheduler yang memegang koneksi. Restore tidak membuka maintenance secara otomatis. Seluruh tabel usaha, metrik dan bukti diperiksa sebelum sesi/reset token/remember token/cache dibersihkan dan audit restore ditambahkan. Periksa juga akun aktif, password dan hak akses yang kembali ke waktu snapshot sebelum membuka aplikasi.

```sh
php artisan clbk:backup --reason=pre-upgrade
php artisan clbk:restore LOKASI-BACKUP.zip --verify-only
php artisan clbk:doctor --json
```

Dalam container Coolify jalankan perintah aplikasi melalui gosu www-data seperti pada DEPLOY.md. Gunakan satu Scheduled Task schedule:run per menit; jangan mengaktifkan unit systemd tambahan untuk job yang sama.

## Bukti pemulihan dan alur usaha

[Laporan simulasi](tahap-6-recovery.json) merekam restore dari ZIP 31.496 byte ke database dan storage bukti pengganti yang baru: **0,066 detik**, 33 tabel usaha cocok dan satu bukti cocok checksum. Saldo fixture: kas Rp8.300.000, utang konsumen Rp3.200.000, upah Rp110.000, piutang/stok historis nol. Maintenance tetap aktif dan sesi/remember token dicabut.

Tes tambahan memulihkan piutang belum diterima **Rp3.150.000** dan histori stok **10.000 gram**, biaya beli Rp20.000 serta biaya muat Rp1.000. Rekonsiliasi meliputi hash logis tabel dan harga snapshot.

Tes satu hari kerja memakai modal Rp5.000.000, timbang TBS 1.000 kg + sortir 100 kg, harga beli Rp3.000/Rp2.000 dan upah Rp100/kg. Hak konsumen Rp3.200.000 dan upah Rp110.000. Penjualan pabrik 900 kg × Rp3.500 ditambah sortir 100 kg × Rp2.000 menghasilkan pendapatan Rp3.350.000. Setelah penerimaan dan pengeluaran Rp50.000, kas Rp8.300.000 dan keuntungan periode **−Rp10.000**. Pelunasan hari berikutnya menghasilkan kas **Rp4.990.000**, utang/piutang nol, dan keuntungan periode tetap sama.

Durasi simulasi berlaku untuk dataset sintetis kecil pada lingkungan lokal setelah runtime pengganti tersedia. Target RTO ≤2 jam harus diukur dengan data representatif, termasuk provisioning, akses secret dan pemeriksaan operator. RPO ≤24 jam memerlukan scheduler aktif, penanganan backup gagal serta salinan offsite; implementasi jadwal saja bukan bukti operasional produksi.

Untuk mengulangi simulasi terisolasi:

```powershell
php scripts/prepare-stage6-acceptance.php
php scripts/verify-stage6-recovery.php PATH-CONTEXT-JSON-YANG-DIHASILKAN
php vendor/bin/phpunit tests/Feature/BackupRestoreTest.php tests/Feature/FullWorkingDayTest.php
```

Script pertama membuat direktori .cache/stage6-* baru dengan SQLite, APP_KEY dan akun sintetis terpisah. Context berisi login uji dan tidak masuk version control. Script pemulihan memvalidasi path sebelum membuka DB; tujuan restore baru dan maintenance uji tidak menggunakan database operasional.

## Pengujian dan penerimaan

Pemeriksaan terbaru setelah perubahan cakupan aplikasi: **npm run build lulus** (TypeScript + Vite, 3.091 modul), **354 tes PHP lulus dengan 8.637 assertions** dalam 107,74 detik, dan Pint pada dua berkas PHP yang diubah lulus. Tautan lokal pada 12 dokumen serta struktur Markdown/PowerShell panduan deployment juga diperiksa. Endpoint kesehatan web menggunakan **/up**. BackupRestoreTest tetap memeriksa kesehatan web dan akses bukti setelah pemulihan.

| Gate | Langkah penutupan |
|---|---|
| AC-34 koneksi putus | Putus/sambung jaringan ketika browser terbuka; status jelas, tidak ada posting offline, dan retry tidak menggandakan dokumen |
| AC-35 browser komputer/ponsel | Login/logout tiap role, sesi, CSRF, unggah/unduh bukti, CSV, cetak/PDF dan penolakan akun nonaktif |
| AC-36 pemulihan | Restore pada lingkungan Linux uji terpisah dengan data representatif; cocokkan kas/utang/piutang/stok/bukti dan waktu total |
| Produksi Coolify | Build/run image, DNS/HTTPS, SMTP, storage/mount/izin, satu scheduler, offsite dan alarm operator |
| Performa | Ukur p95 daftar/dashboard/posting dengan ≥10.000 tiket dan pengguna bersamaan pada mesin target |

Verifikasi konfigurasi lokal sebelumnya meliputi sintaks shell/PowerShell, parsing INI, nginx -t pada Linux dengan path uji, serta urutan cache Laravel pada salinan production tanpa DB. Pemeriksaan tersebut tidak menggantikan build image maupun uji layanan produksi.
