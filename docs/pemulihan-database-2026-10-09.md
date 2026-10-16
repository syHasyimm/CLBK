# Pemulihan database — 9 Oktober 2026

## Kondisi dan sumber pemulihan

Database SQLite aktif mengalami kerusakan struktur. Salinan file utama juga gagal dibaca secara mandiri, sehingga masalah bukan sekadar pesan kesalahan Laravel. Penyebab awal kerusakan belum dapat dipastikan. TablePlus terdeteksi mengunci file ketika pemasangan hasil pemulihan dicoba; temuan ini tidak membuktikan bahwa TablePlus menyebabkan kerusakan.

Seluruh berkas asli (`database.sqlite`, `database.sqlite-wal`, dan `database.sqlite-shm`) disalin sebelum pemulihan ke `.bootstrap/recovery-20261009-160919/`. Backup sehat sebelum tahap 4 berada di `.bootstrap/before-stage4-20261009.sqlite`. Berkas pemulihan mengandung data pribadi dan harus tetap disimpan secara lokal, di luar kontrol versi.

Pemulihan menggunakan alat resmi SQLite 3.53.4 dengan `--ignore-freelist`. Hasil mentah tidak langsung dipasang karena ada struktur tabel yang hilang. Struktur yang benar dibentuk dari backup sehat dan migrasi tahap 4, kemudian diisi data terbaru yang diselamatkan. Semua halaman tabel anonim yang ditemukan telah dipetakan; halaman indeks dibentuk ulang dari struktur resmi aplikasi.

## Data hasil pemulihan

- Tiga akun pengguna dan hash kata sandinya sama dengan backup sehat.
- Satu konsumen, dua anggota muat, satu mobil, satu pabrik, satu perjalanan berstatus dikirim, satu jadwal harga konsumen, dan satu jadwal harga pabrik.
- Dua belas riwayat audit dan satu permintaan idempotensi.
- Satu akun kas, enam kategori pengeluaran, dan empat pengaturan dipulihkan dari backup sehat serta diperiksa terhadap riwayat audit.
- Tabel timbangan, penjualan, dan transaksi keuangan tetap kosong sesuai data yang berhasil ditemukan; tidak dibuat transaksi pengganti.

Data dua anggota muat dipulihkan dari snapshot audit penciptaannya. Snapshot audit tidak menyimpan `created_at` dan `updated_at`, sehingga kedua atribut tersebut menggunakan waktu kejadian audit penciptaan. Waktu asli secara persis tidak dapat dipastikan. Atribut bisnis anggota muat mengikuti snapshot audit tanpa perkiraan.

## Pemeriksaan sebelum pemasangan

Kandidat final: `.bootstrap/recovery-20261009-160919/candidate-v3.sqlite`.

- `PRAGMA integrity_check`: `ok`.
- `PRAGMA foreign_key_check`: tidak ada pelanggaran.
- Tujuh catatan migrasi dan 87 trigger database dipertahankan.
- Data terbaru sesuai snapshot audit terakhir; waktu yang tersimpan dalam format SQL dibandingkan dengan waktu ISO audit sebagai waktu yang sama.
- Akun dan kata sandi tidak direset.
- WAL kandidat telah dikosongkan dan koneksi ditutup sebelum pemasangan.
- SHA-256 file rusak aktif masih sama dengan salinan awal: `DDD97BF8AF06170C9019B183926C756B0BD79B9BDF5FC9DCA6F08A6203127051`.

Rincian hitungan tabel dan sumber data ada di `.bootstrap/recovery-20261009-160919/candidate-report.json`.

## Status pemasangan

Selesai. Setelah pengguna menutup koneksi TablePlus, pemeriksaan kunci tidak menemukan proses yang menahan file. Hash file utama tetap sama dengan salinan awal. File asli diarsipkan sebagai `.bootstrap/recovery-20261009-160919/original-active-database.sqlite`, kemudian kandidat final dipasang sebagai `database/database.sqlite`. WAL/SHM asli sudah hilang ketika koneksi TablePlus ditutup; salinan awal keduanya tetap tersimpan di folder pemulihan dan tidak digunakan pada database pengganti.

Pemeriksaan melalui koneksi database Laravel selesai pada **9 Oktober 2026, 10:04:08 UTC**:

- Integritas database aktif: `ok`; tidak ada pelanggaran relasi.
- Seluruh isi **40 tabel** sama dengan kandidat yang sudah diverifikasi sebelum aplikasi dibuka kembali.
- Seluruh **87 trigger** tersedia.
- Ketujuh migrasi berstatus `Ran`, termasuk migrasi keuangan tahap 4.
- Mode jurnal aktif kembali menggunakan `WAL` sesuai konfigurasi aplikasi.
- Mode pemeliharaan sudah dinonaktifkan. Halaman `http://127.0.0.1:8000/login` merespons HTTP `200`.

Laporan pemasangan: `.bootstrap/recovery-20261009-160919/installed-report.json`. Backup konsisten baru dibuat melalui SQLite sebagai `.bootstrap/recovery-20261009-160919/after-recovery.sqlite` dan lolos pemeriksaan integritas serta relasi. Tidak dijalankan pengosongan database atau pengulangan seeder pada database aktif.
