# Notifikasi WhatsApp melalui Fonnte

Fitur tahap pertama mengirim hasil timbangan dan konfirmasi pembayaran kepada konsumen. Hasil timbangan hanya memuat nama, nomor tiket, tanggal/waktu WIB, berat TBS, dan berat sortir. Harga, total pembelian, upah, dan saldo tidak disertakan. Konfirmasi pembayaran memuat nominal, metode, nomor bukti, dan jumlah tiket; satu pembayaran gabungan menghasilkan satu pesan.

## Aktivasi

1. Hubungkan nomor pengirim pada dashboard Fonnte dan salin **device token**.
2. Masuk sebagai manajer, buka **Pengaturan → Notifikasi WhatsApp** atau **Pengaturan Usaha → Pengaturan WhatsApp** (`/settings/whatsapp`). Masukkan token pada bagian **Token & koneksi WhatsApp**, lalu klik **Simpan & Uji koneksi**. Token disimpan terenkripsi dalam tabel khusus `whatsapp_connections`, tidak masuk ke pengaturan umum, audit, atau respons browser. Kolom token kembali kosong setelah disimpan. Token membutuhkan APP_KEY yang sama agar dapat dibaca kembali.
3. Hasil pemeriksaan membedakan **Terhubung**, **Perangkat terputus**, **Token tidak valid**, dan **Pemeriksaan gagal**. API `POST https://api.fonnte.com/device` memeriksa profil perangkat tanpa mengirim pesan WhatsApp. Gunakan **Uji token tersimpan** untuk memeriksa lagi. Hasil menampilkan waktu pemeriksaan terakhir; kondisi perangkat dapat berubah setelah waktu tersebut. Mengganti token menonaktifkan pengiriman dan membatalkan hasil pemeriksaan sebelumnya.
4. Setelah hasil **Terhubung**, aktifkan pengiriman dan jenis pesan yang diperlukan, lalu simpan pengaturan. Token dari halaman ini tidak memerlukan perubahan `.env` atau restart worker. Fallback `WHATSAPP_ENABLED=true` dan `FONNTE_TOKEN` untuk konfigurasi server lama tetap didukung selama belum ada token tersimpan melalui halaman.
5. Untuk pembaruan status pengiriman, lengkapi `.env` atau environment resource Coolify:

```dotenv
FONNTE_WEBHOOK_SECRET=isi_rahasia_acak_minimal_32_karakter
FONNTE_DEVICE=
```

`FONNTE_DEVICE` opsional: bila diisi, callback harus membawa field `device` yang sama. Gunakan identitas device yang dikirim Fonnte, yang menurut dokumentasinya dapat berbeda dari nomor WhatsApp yang terhubung. Rahasia webhook harus 32–128 karakter dari huruf, angka, `_`, atau `-`; dapat dibuat di server dengan `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`.

Terapkan migrasi tambahan dan bangun frontend saat memperbarui versi aplikasi:

```powershell
php artisan migrate --force
npm run build
php artisan config:cache
php artisan queue:restart
```

Pada dashboard Fonnte, atur **webhook update message status** ke `https://domain-aplikasi/integrations/fonnte/webhook/RAHASIA_YANG_SAMA`. Endpoint memerlukan HTTPS yang dapat diakses Fonnte. Rahasia URL tidak ditampilkan melalui halaman aplikasi; batasi akses log reverse proxy karena URL callback memuat rahasia. Endpoint hanya menerima pembaruan status, bukan perintah transaksi atau balasan otomatis.
6. Aktifkan **Kirim notifikasi WhatsApp** pada formulir konsumen setelah konsumen bersedia menerima pesan. Nomor telepon mendukung `08…`, `628…`, dan `+628…`; nomor dinormalisasi hanya untuk pengiriman. Nomor bersama antar konsumen tetap diperbolehkan.
7. Kirim pesan tes ke nomor sendiri dan periksa riwayat. Transaksi historis sebelum aktivasi tidak dikirim otomatis.

## Menjalankan antrean

Koneksi queue `whatsapp` selalu menggunakan database MySQL aplikasi dengan nama antrean `whatsapp`, meskipun `QUEUE_CONNECTION=sync`. Tidak diperlukan Redis. Worker mengirim HTTP setelah transaksi commit.

Image Docker sudah menjalankan worker melalui Supervisor. Scheduler yang sudah ada memulihkan outbox setiap menit. Jangan menjalankan scheduler kedua pada resource yang sama. Setelah mengubah environment Coolify, redeploy agar konfigurasi dan worker memuat environment baru.

Untuk development tanpa Docker, jalankan masing-masing di terminal terpisah selain `composer dev`:

```powershell
php artisan queue:work whatsapp --queue=whatsapp --sleep=3 --tries=1 --timeout=40
php artisan schedule:work
```

Untuk Docker lokal, environment dapat diisi di `.env.docker.local` dan diteruskan oleh `compose.local.yaml`. Biarkan nonaktif pada data demo kecuali nomor penerima memang milik Anda. Status webhook lokal memerlukan URL HTTPS publik yang sudah dikonfigurasi; pengiriman pesan sendiri hanya memerlukan koneksi keluar ke Fonnte.

## Status dan kegagalan

- **Menunggu / Sedang dikirim:** pesan berada di aplikasi.
- **Diterima Fonnte:** API mengembalikan keberhasilan beserta ID pesan. Ini belum membuktikan pesan diterima konsumen.
- **Terkirim / Diterima penerima / Dibaca:** diperbarui berdasarkan status/state callback. Nilai state yang tidak dikenal tidak dianggap sebagai bukti diterima atau dibaca; state `0` ditandai belum diterima.
- **Gagal:** Fonnte menolak permintaan atau melaporkan kegagalan. Manajer dapat mencoba ulang hanya bila permintaan awal belum mempunyai ID Fonnte dan transaksi/nomor masih sesuai.
- **Perlu diperiksa:** timeout, respons tidak jelas, atau worker terhenti saat mengirim. Tidak dicoba ulang otomatis karena Fonnte mungkin sudah menerima pesan. Periksa dashboard Fonnte terlebih dahulu. Tidak ada jaminan exactly-once lintas API.
- **Dilewati:** nomor kosong/tidak valid, konsumen tidak aktif/menolak notifikasi, nomor berubah, pengiriman dinonaktifkan, atau status transaksi berubah.
- **Dibatalkan:** transaksi asal dibatalkan sebelum antrean diproses. Pesan yang sudah diterima Fonnte tidak ditarik; notifikasi pembatalan/koreksi dibuat terpisah.

Perangkat terputus dengan penolakan eksplisit dicoba ulang setelah 1, 5, dan 15 menit, maksimal empat percobaan per siklus awal. Kesalahan token/kuota memerlukan perbaikan konfigurasi. Tidak ada retry HTTP otomatis pada request yang hasilnya belum pasti. Riwayat percobaan tersedia pada detail pesan; token dan respons mentah tidak disimpan di sana.

Kasir dapat membaca riwayat. Hanya manajer dapat mengubah pengaturan, mengirim tes, dan mencoba ulang. Supir tidak dapat membuka riwayat pesan; supir dapat mencatat persetujuan konsumen saat menambah konsumen.

## Pembatalan dan restore

Pembatalan timbangan dan pembalikan pembayaran menghentikan pesan yang masih menunggu lalu membuat notifikasi koreksi. Timbangan pengganti mencantumkan nomor tiket yang digantikan. Pesan timbangan tetap tidak memuat harga/nominal.

Backup menyertakan riwayat/outbox dan token dari halaman dalam bentuk terenkripsi. APP_KEY harus dipertahankan untuk membaca kembali token tersebut. Token fallback dari environment server tetap tidak masuk arsip. **Restore harus dilakukan dalam maintenance dengan worker dihentikan; jangan memakai `--force` pada worker untuk melewati maintenance.** Setelah restore, pengiriman dinonaktifkan, status koneksi dikembalikan ke belum diuji, pesan pending/retrying/processing/uncertain dikarantina, dan antrean WA yang dipulihkan dibersihkan. Pesan karantina tidak dapat dicoba ulang dari UI. Uji kembali koneksi dan periksa dashboard Fonnte sebelum mengaktifkan pengiriman untuk transaksi baru. Backup lama harus dipulihkan memakai versi aplikasi/migrasi yang sesuai, lalu jalankan migrasi baru.

## Validasi

`tests/Feature/WhatsAppNotificationsTest.php` memeriksa isi pesan tanpa harga, pembayaran gabungan, idempotensi, rollback, hak akses, retry/timeout, perubahan nomor, pembatalan, webhook berulang/lebih awal, recovery antrean, dan restore. HTTP diuji dengan fake Fonnte sehingga pengujian tidak mengirim WA sungguhan.

`tests/Feature/WhatsAppConnectionTest.php` memeriksa enkripsi token, pencegahan kebocoran token melalui props/audit/old input, pemeriksaan profil perangkat, kondisi koneksi gagal, pembatasan akses manajer, dan penggantian token saat pemeriksaan berjalan.

Referensi: [profil perangkat Fonnte](https://docs.fonnte.com/api-device-profile/), [API pengiriman Fonnte](https://docs.fonnte.com/api-send-message/), [webhook status](https://docs.fonnte.com/webhook-update-message-status/), [penjelasan state 0](https://docs.fonnte.com/about-state-0/), [Laravel 13 queues](https://laravel.com/docs/13.x/queues).
