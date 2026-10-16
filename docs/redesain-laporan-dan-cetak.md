# Tampilan Laporan dan dokumen cetak

Tanggal: 11 Oktober 2026. Halaman Laporan kasir dan manajer memakai tampilan baru yang selaras dengan Dashboard, Penjualan Pabrik, serta Gaji Karyawan Muat.

## Halaman Laporan

Judul, periode, filter aktif, serta tombol Ekspor CSV dan Cetak / PDF ditampilkan dengan hierarki yang lebih jelas. Jenis laporan dikelompokkan menurut ringkasan, operasional, tagihan/upah, keuangan, dan administrasi. Filter tanggal serta rincian memiliki label dan pesan validasi; Reset rincian mempertahankan jenis laporan dan periode yang sudah diterapkan.

Ringkasan usaha memisahkan nilai penjualan, pembelian buah, hak upah muat, pengeluaran operasional, volume buah, saldo saat ini, serta arus kas. Angka memiliki tautan menuju laporan sumber. Keuntungan bersih tetap mengikuti perhitungan periode yang telah dipilih pengguna dan hanya tersedia bagi manajer ketika seluruh mobil dipilih.

Rincian harian, per mobil, transaksi, serta arus kas memiliki susunan kartu pada layar kecil untuk kedua role. Desktop memakai tabel. Pagination transaksi tetap tersedia; rincian harian/per mobil yang memang tidak dipaginasi tidak menampilkan kontrol halaman. Total laporan tetap mencakup seluruh hasil filter. CSV dan cetak memakai filter yang sudah diterapkan, termasuk semua halaman.

## Cetak dan simpan PDF

Seluruh 11 jenis laporan memakai identitas usaha, periode/cakupan filter, penjelasan dasar angka, ringkasan total, tabel dengan angka rata kanan, baris berselang warna, serta waktu penyusunan. Kertas A4 otomatis menggunakan landscape untuk tabel dengan lebih dari tujuh kolom dan portrait untuk tabel lebih ringkas. Judul tabel diulang pada halaman berikutnya; total akhir dan footer mengikuti rincian terakhir. Dokumen memiliki nomor halaman dan jumlah halaman.

Voucher Pembayaran Konsumen, Gaji Karyawan Muat, Penerimaan Penjualan Pabrik, Penerimaan Penjualan Sortir, dan Uang Keluar Harian memakai komponen dokumen yang sama. Identitas usaha, nomor/status voucher, nominal, penerima, waktu WIB, akun, metode, petugas, referensi, dokumen asal, periode kerja, catatan, dan alasan tanggal sebelumnya tetap disertakan sesuai data transaksi. Voucher berstatus Dibalik menjelaskan alasan, waktu, dan referensi pengembalian. Alokasi pembayaran mempunyai total akhir; versi cetak menyediakan ruang tanda tangan dan nomor halaman.

Header aplikasi, sidebar, navigasi bawah, filter, tombol, unggah bukti, serta form pembalikan tidak masuk dokumen cetak. Voucher pendek dalam contoh pemeriksaan muat satu halaman A4. Voucher dan laporan panjang tetap memuat seluruh rincian lintas halaman.

## Verifikasi

- Build TypeScript/Vite, Prettier, dan pemeriksaan whitespace: lulus.
- Pengujian laporan: 11 pengujian, 1.199 assertions. Pengujian keuangan: 30 pengujian, 1.125 assertions.
- Render React: 100 skenario tanpa warning, termasuk seluruh jenis laporan kasir/manajer, filter mobil, kondisi kosong, cakupan keuntungan, tautan sumber/ekspor/cetak, navigasi, serta tujuh skenario voucher.
- Cetak: 20 contoh PDF dari template aplikasi dan data sintetis dirender dengan WeasyPrint; total 35 halaman. Pemeriksaan mencakup seluruh jenis laporan, ringkasan kasir/manajer, laporan kosong, 55 baris timbang/penjualan sortir, voucher biasa/dibalik, serta 72 alokasi upah. Teks tidak keluar dari batas halaman; rincian terakhir, total, dan footer tetap tercetak. Pratinjau gambar diperiksa untuk identitas, keterbacaan, pemisahan halaman, status pembalikan, dan ruang tanda tangan.
- Pemeriksaan interaksi dan tampilan langsung di browser, termasuk dialog cetak browser, belum dilakukan karena browser tidak terhubung pada sesi ini. Pemeriksaan PDF menggunakan renderer cetak terpisah.

Perubahan hanya menyentuh penyajian antarmuka dan template cetak. Perhitungan, akses role, ekspor CSV, penyimpanan transaksi, dan aturan pembayaran memakai implementasi yang sudah ada; tidak diperlukan migrasi database.
