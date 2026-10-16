# Rencana penyederhanaan workflow CLBK TOKE

Tanggal: 9 Oktober 2026; diperbarui 10 Oktober 2026. Status: disetujui pengguna. Tahap 1–4 selesai: fondasi transisi data, Timbang Sawit, penjualan langsung, serta pembersihan workflow/dashboard/laporan. Tahap 5 pemeriksaan akhir menyeluruh belum dijalankan sebagai tahap tersendiri.

## Batas perubahan

Data master dipertahankan seluruhnya: halaman, formulir, field, dan proses pengelolaannya tidak diubah. Rencana ini menyederhanakan transaksi timbang serta penjualan, menghapus fitur Muatan Mobil dan Stok Sortir dari alur operasional, dan menyesuaikan ketergantungan keuangan yang terdampak.

Tidak dibuat perjalanan otomatis atau stok FIFO tersembunyi sebagai pengganti fitur yang dihapus. Mobil merupakan informasi kendaraan yang digunakan dalam transaksi, bukan satu kelompok muatan yang harus dibuka atau ditutup.

## Navigasi

Menu operasional: Dashboard, Timbang Sawit, Penjualan Pabrik, Penjualan Sortir, dan Harga & Upah Muat. Menu Muatan Mobil serta Stok Sortir dihapus. Profil, keuangan, administrasi, dan seluruh kelompok Data Master tetap tersedia mengikuti role yang sudah ditetapkan.

## Timbang Sawit

Alur lapangan: supir datang ke lokasi konsumen → timbang buah → catat TBS dan sortir → pilih anggota muat → simpan → selesai.

Formulir utama memuat:

1. Konsumen.
2. Mobil yang mengambil buah, langsung dari Data Mobil.
3. Berat TBS dalam kg.
4. Berat sortir dalam kg; nol diperbolehkan bila tidak ada sortir.
5. Anggota muat; minimal satu anggota, dapat lebih dari satu.

Waktu timbang dan petugas pencatat otomatis tersimpan. Harga TBS, harga sortir, serta upah muat ditampilkan sebagai informasi otomatis dari fitur harga. Ringkasan uang konsumen serta upah muncul dalam formulir sebelum disimpan. Tidak ada pilihan perjalanan, proses berangkat, atau penutupan muatan.

Tombol **Simpan Timbangan** langsung menyimpan transaksi sah dalam satu proses: mencatat berat, mengunci harga, membentuk hak konsumen, dan membagi upah. Tidak perlu menyimpan draft lalu berpindah ke halaman pengesahan. Bila harga berubah selama pengguna mengisi formulir, ringkasan diperbarui untuk diperiksa sebelum penyimpanan; transaksi tidak diam-diam memakai harga berbeda dari ringkasan yang disetujui.

Uang konsumen = berat TBS × harga TBS terkunci + berat sortir × harga sortir terkunci. Dasar upah tetap TBS + sortir pada tiket. Upah ditanggung toke, tanpa memotong uang konsumen. Satu anggota memperoleh seluruh upah; beberapa anggota membaginya rata dengan pembulatan yang menjaga jumlah total.

Setelah berhasil, daftar menampilkan nomor timbang, waktu, konsumen, mobil, TBS, sortir, uang konsumen, anggota muat, dan status pembayaran. Tampilan berhasil menyediakan **Catat Timbangan Baru** serta **Lihat Rincian**. Pembayaran dikerjakan di Uang Penimbang; penyimpanan timbang tidak berarti uang sudah dibayarkan.

Sortir dari konsumen tetap berada pada rincian timbang milik konsumen tersebut. Tidak dibuat lot, saldo stok, mutasi stok, atau stok retur.

## Penjualan Pabrik

Alur: datang ke pabrik → pilih mobil → pilih pabrik → catat berat bersih diterima → simpan → selesai.

Formulir utama memuat mobil, pabrik, dan berat bersih diterima. Pemilihan pabrik tetap diperlukan untuk menentukan harga dari riwayat harga pabrik yang sudah tersedia; tidak memerlukan perubahan Data Master. Harga per kg otomatis ditampilkan dan tidak dapat diedit. Nilai penjualan = berat bersih × harga pabrik, dikurangi potongan jika dicatat.

Nomor transaksi, waktu, dan petugas otomatis tersimpan. Nomor nota pabrik, potongan, dan catatan ditempatkan sebagai informasi tambahan opsional agar tidak menghalangi pencatatan sederhana. Tidak ada input berat awal, berat sortir pabrik, perjalanan, selisih timbang, alasan persetujuan, atau unggah bukti penjualan pabrik.

Tombol **Simpan Penjualan** langsung menyimpan penjualan sah dan mengunci harga. Tidak ada proses pengesahan terpisah, pemeriksaan status perjalanan, atau pembentukan stok retur. Penjualan tidak wajib mengambil tiket konsumen tertentu dan tidak menjumlahkan seluruh timbangan mobil untuk mengisi berat jual. Mobil yang sama dapat mencatat beberapa penjualan, masing-masing dengan nomor transaksi berbeda.

Jika harga untuk pabrik dan waktu transaksi belum tersedia, formulir mengarahkan pengguna mengatur harga terlebih dahulu. Nilai penjualan lama tidak berubah ketika harga berikutnya dipublikasikan. Penerimaan uang tetap dicatat kasir/manajer di fitur Penerimaan Penjualan; menyimpan penjualan tidak otomatis menandakan pabrik sudah membayar.

## Penjualan Sortir

Alur: supir datang ke tempat penjualan sortir → pilih mobil → masukkan berat sortir terjual → masukkan harga buah per kg → simpan → selesai.

Formulir utama hanya meminta mobil, berat sortir terjual, dan harga jual per kg. Identitas pembeli/tempat serta catatan dapat dicatat sebagai informasi tambahan opsional. Nomor transaksi, waktu, dan petugas otomatis tersimpan.

Harga jual sortir tetap manual sesuai transaksi di tempat penjualan. Nilai penjualan = berat terjual × harga jual. Tombol **Simpan Penjualan Sortir** langsung mencatat penjualan sah, tanpa pilihan perjalanan, lot, alokasi FIFO, atau pemeriksaan saldo stok. Penjualan dapat dicatat tanpa nota pabrik sebelumnya. Penerimaan uang tetap merupakan proses kasir/manajer yang terpisah.

Sortir yang ditimbang dari konsumen dan sortir yang dijual merupakan dua pencatatan berbeda. Aplikasi tidak menghubungkan keduanya secara otomatis atau menganggap harga jual sortir sebagai harga untuk membayar konsumen.

## Keuangan, dashboard, dan hak akses

Uang Penimbang dan Gaji Karyawan Muat tetap membaca hak dari timbangan sah dengan harga/upah terkunci. Pelunasan sebagian/penuh, kas, penerimaan penjualan, serta pengeluaran harian mengikuti aturan keuangan yang sudah ada. Referensi perjalanan yang opsional pada pengeluaran dihapus dari formulir; pencatatan pengeluaran harian tetap berfungsi.

Dashboard menampilkan jumlah transaksi timbang, TBS dan sortir yang dibeli dari konsumen, berat TBS dan sortir terjual, nilai pembelian, pendapatan penjualan, hak upah, pengeluaran, utang konsumen, dan kas untuk periode yang dipilih. Ringkasan per mobil menjadi filter/rincian, tanpa halaman Muatan Mobil.

Karena tidak ada pencocokan pembelian ke nota melalui perjalanan atau stok, biaya asal suatu penjualan tidak dapat ditentukan secara akurat. Laba per nota serta margin FIFO tidak ditampilkan untuk transaksi baru. Ringkasan baru menggunakan label **Selisih transaksi periode**: pendapatan penjualan periode − nilai pembelian periode − hak upah periode − pengeluaran operasional periode. Angka tersebut tidak disebut laba penjualan atau saldo kas; buah yang dibeli pada periode berbeda dapat membuat nilainya berbeda dari laba aktual. Pembayaran utang dan pencairan gaji tidak dikurangkan lagi dari ringkasan ini, agar biaya yang sudah diakui tidak dihitung dua kali.

Supir dapat mencatat timbang serta penjualan dan melihat transaksi yang dicatatnya. Kasir mengurus pembayaran konsumen, payroll, dan penerimaan penjualan. Manajer melihat seluruh transaksi serta mengurus koreksi. Data yang sudah sah tidak diedit diam-diam; koreksi mempertahankan riwayat dan memeriksa pembayaran terkait. Kendaraan tidak lagi menentukan akses melalui penugasan perjalanan; akses mengikuti role dan petugas pencatat.

Pembaruan penugasan: manajer menetapkan satu mobil aktif pada akun supir melalui Kelola Pengguna. Form timbang dan kedua jenis penjualan memakai mobil itu secara otomatis dan terkunci. Akun tanpa mobil aktif diblokir dari pencatatan transaksi baru. Versi penugasan mencegah form lama disimpan setelah pergantian; transaksi lama tetap memakai mobil asal dan akses pencatat yang sama. Rincian tersedia pada [penugasan mobil supir](penugasan-mobil-supir.md).

## Penyesuaian penyimpanan dan data lama

- Timbangan, penjualan pabrik, dan penjualan sortir mempunyai referensi langsung ke `vehicle_id`, serta nomor, waktu, dan petugas pencatat.
- Ketergantungan wajib terhadap `trip_id` dihapus dari transaksi baru, validasi, izin, perhitungan, pencatatan keuangan, dan tampilan. Relasi lama tetap dapat dipertahankan untuk membaca histori.
- Penjualan pabrik baru hanya menyimpan berat bersih sebagai berat yang diinput. Kolom berat awal, sortir pabrik, dan persetujuan selisih lama tidak lagi menjadi syarat transaksi baru.
- Timbangan baru tidak menghasilkan stok sortir. Penjualan sortir baru tidak menghasilkan alokasi FIFO atau mutasi stok.
- Catatan konsumen, nominal transaksi, snapshot harga, hak upah, pembayaran, piutang, kas, audit, dan dokumen lama dipertahankan. Tidak menjalankan reset database, mengosongkan transaksi, atau mengulang seeder untuk perubahan ini.
- Kendaraan transaksi lama diambil dari perjalanan yang terhubung, sebelum ketergantungan tersebut dilepas. Angka biaya/laba histori tetap dipertahankan sebagai histori, tanpa menebak biaya asal untuk transaksi baru.
- Halaman dan endpoint operasional perjalanan serta stok dinonaktifkan sesudah seluruh dependensi dilepas. Tabel/arsip lama tidak dibuang pada tahap penyederhanaan ini.

## Urutan implementasi

1. **Fondasi dan transisi data:** backup konsisten, penambahan referensi kendaraan langsung, pemetaan data lama, penyesuaian aturan database dan akses. Verifikasi nominal, pembayaran, serta relasi data lama tetap benar.
2. **Timbang Sawit:** formulir konsumen/mobil/berat/anggota, ringkasan otomatis, simpan langsung sah, serta pembayaran dan payroll yang tetap membaca hak dengan benar.
3. **Penjualan:** formulir pabrik dengan satu berat dan harga otomatis; formulir sortir dengan mobil, berat, serta harga manual; simpan langsung sah dan penerimaan uang tetap terpisah.
4. **Pembersihan workflow dan tampilan:** hapus menu/halaman operasional perjalanan dan stok, tautan yang terkait, serta input perjalanan pada pengeluaran; sesuaikan dashboard dan laporan tanpa mengubah Data Master.
5. **Pemeriksaan akhir:** uji seluruh role, perubahan harga, pembagian upah, koreksi, pembayaran sebagian/penuh, simpan ganda, histori lama, dan penggunaan formulir pada ponsel. Perbarui PRD sesuai workflow yang sudah disepakati.

Implementasi dikerjakan bertahap. Fondasi tahap 1 sudah diterapkan pada database aktif dan diverifikasi terhadap backup. Timbang Sawit tahap 2 memakai mobil langsung, ringkasan otomatis, dan simpan langsung sah. Tahap 3 penjualan pabrik/sortir serta tahap 4 penghapusan halaman perjalanan/stok, penyesuaian pengeluaran, Dashboard, dan Laporan sudah selesai. Catatan pemasangan historis telah diarsipkan. Tahap 5 tetap menjadi pemeriksaan akhir berikutnya.
