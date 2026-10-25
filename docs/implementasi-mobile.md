# Implementasi UI mobile CLBK TOKE

Tanggal: 25 Oktober 2026. Acuan: [prototype.md](../prototype.md).

## Cakupan

Tampilan mobile diterapkan pada role manajer, kasir, dan supir, menggunakan komponen serta rute yang sudah ada.

- Header aplikasi ringkas, navigasi bawah lima posisi, dan Menu dua kolom yang mengikuti izin role. Keluar akun tersedia pada Menu. Sidebar desktop tetap digunakan mulai lebar 1.024 px.
- Beranda kasir menampilkan kas dan pekerjaan keuangan; manajer menampilkan hasil periode dan posisi usaha; supir menampilkan berat, penugasan mobil, dan akses operasional. Nominal memakai data server, dengan pilihan sembunyikan nominal pada perangkat.
- Pencarian tetap terlihat pada daftar mobile. Filter tambahan menggunakan panel dari bawah. Filter dashboard, transaksi, data master, penjualan pabrik, gaji, kas, dan laporan mengikuti alur permintaan yang sudah ada.
- Uang Penimbang dan Gaji Muat memakai baris penerima ringkas. Total pada halaman diberi keterangan lingkup halaman/filter, bukan dijumlahkan lalu dianggap sebagai total seluruh usaha.
- Pelunasan konsumen menampilkan penerima, cakupan seluruh tiket belum lunas, metode, waktu, dan dampak kas. Pembayaran gaji tetap dapat sebagian sesuai sisa periode. Saldo tidak cukup membatasi tindakan pembayaran.
- Timbangan, penjualan pabrik, dan penjualan sortir memiliki langkah pemeriksaan sebelum permintaan simpan. Harga, pembagian upah, idempotensi, dan penugasan tetap diproses melalui alur server yang sama.
- Dock tombol utama berada di bawah pada form. Navigasi bawah disembunyikan pada halaman form. Deteksi keyboard mengembalikan dock ke aliran konten agar tidak menutupi isian. Kesalahan server mengarahkan fokus ke isian terlihat yang tidak valid.
- Pemilih referensi menggunakan panel pencarian di mobile. Daftar transaksi dan data master memakai kartu untuk semua role pada lebar di bawah 1.024 px.
- Kas memakai daftar mutasi dengan arah uang dan tautan dokumen sumber. Filter tanggal hanya membatasi mutasi. Laporan memiliki tab jenis laporan dan menu ekspor/cetak pada mobile.

Tidak ada migrasi, perubahan hak akses, pengubahan perhitungan uang/berat, maupun transaksi demonstrasi yang ditambahkan. Shared prop pengguna ditambah dengan ID mobil penugasan milik pengguna sendiri, agar beranda supir dapat menampilkan penugasan yang benar.

## Berkas utama

- [Layout bersama](../resources/js/layouts/StaffLayout.tsx).
- [Beranda mobile per role](../resources/js/features/dashboard/components/MobileHome.tsx).
- [Gaya mobile](../resources/css/mobile-app.css).
- [Menu](../resources/js/components/common/WorkspaceMenu.tsx), [pencarian/filter](../resources/js/components/common/ResponsiveFilters.tsx), dan [pemeriksaan transaksi](../resources/js/components/common/TransactionReview.tsx).
- [Pelunasan konsumen](../resources/js/features/finance/components/CustomerSettlementForm.tsx).
- [Kas Usaha](../resources/js/pages/Finance/Cash.tsx).
- [Shared props](../app/Http/Middleware/HandleInertiaRequests.php) dan [regresi penugasan supir](../tests/Feature/DriverVehicleAssignmentTest.php).

## Verifikasi

- Build produksi mencakup pemeriksaan TypeScript aplikasi/PWA dan Vite.
- Prettier pada seluruh berkas frontend yang berubah, Pint pada perubahan PHP, serta pemeriksaan whitespace diff.
- 36 pemeriksaan render komponen dan kontrak menu: seluruh role, tujuan yang diizinkan, ketiadaan informasi kas pada beranda supir, penugasan, data kosong, dan kas yang belum tersedia. Ini pemeriksaan render statis, bukan pengujian klik atau tata letak browser.
- Ditambahkan kasus regresi server untuk memastikan dashboard membagikan penugasan pengguna sendiri dan tetap tidak memberikan kas maupun password kepada supir.

## Aktivasi aplikasi Docker lokal

Pada 25 Oktober 2026, image `clbk:local` dibangun ulang dan container aplikasi dibuat ulang melalui `scripts/start-local-docker.ps1`. Aplikasi aktif di **http://localhost:8088/login**. Container aplikasi dan MySQL berstatus sehat. Volume yang sudah ada digunakan kembali, tidak ada migrasi tertunda, dan seeder tidak dijalankan ulang.

- Hash SHA-256 `MobileHome.tsx` dan `mobile-app.css` di container sama dengan berkas workspace.
- Login dan dashboard manajer, kasir, serta supir berhasil melalui HTTP 200. Shared prop penugasan tersedia, password tidak dibagikan, dan prop kas tetap kosong untuk supir.
- Bundle dashboard dan CSS yang benar-benar disajikan melalui HTTP memuat `mobile-home-hero` serta gaya akses cepat baru. Pemeriksaan origin aset JS/CSS dan route juga lulus; seluruh aset yang diperiksa mengembalikan HTTP 200.
- Source tidak di-mount ke container sebagai live code. Rebuild ini menerapkan perubahan workspace yang sebelumnya belum masuk ke aplikasi berjalan. Tampilan mobile berlaku pada lebar di bawah 1.024 px. Aplikasi PWA yang masih terbuka dapat menawarkan pembaruan setelah memeriksa service worker.

Pemeriksaan HTTP ini memverifikasi autentikasi, data halaman, dan aset yang disajikan; pemeriksaan tata letak serta interaksi browser belum dilakukan.

## Catatan pemeriksaan lingkungan native sebelumnya

Pengujian integrasi native dicoba sebelum aktivasi Docker, tetapi migrasi database pengujian berhenti karena MySQL native masih memiliki `log_bin_trust_function_creators=OFF` ketika binary log aktif. Pengujian autentikasi terhenti sebelum assertion bisnis. Prasyarat administrator telah dijelaskan di [panduan MySQL](mysql.md). Database native tersebut terpisah dari MySQL Docker yang digunakan aplikasi pada port 8088.

Pemeriksaan HTTP pada server PHP native sementara menemukan error 500 karena konfigurasi koneksi aplikasi memakai akun root tanpa password yang tidak diterima server MySQL. Kredensial, konfigurasi `.env`, dan pengaturan global server tidak diubah. Server PHP sementara untuk pemeriksaan dihentikan setelah verifikasi. Masalah ini tidak terjadi pada pemeriksaan Docker di atas.

Browser otomatis tidak tersedia di sesi ini. Pemeriksaan visual pada 320, 360, 390, 430, 768, dan 1.024 px, keyboard virtual, serta kontras/fokus aktual masih diperlukan pada aplikasi Docker yang kini sudah aktif.
