# Pembaruan tampilan Dashboard

Tanggal: 10 Oktober 2026. Implementasi tampilan selesai; pemeriksaan visual melalui browser belum dilakukan karena tidak ada browser yang terhubung pada sesi ini.

Dashboard memakai tampilan khusus dengan palet hijau/sage, permukaan putih, tipografi angka yang lebih tegas, dan susunan informasi berdasarkan prioritas. Keuntungan bersih tampil di bagian atas untuk manajer, disusul pendapatan, pembelian buah dan hak upah. Posisi keuangan saat ini ditampilkan dalam satu bagian ringkas dengan tautan rincian saldo.

Filter menyediakan pilihan Hari ini, 7 hari, Bulan ini, rentang tanggal, dan mobil. Pilihan cepat memakai tanggal usaha server. Form menampilkan kondisi memuat dan pesan validasi. Tautan laporan tetap memakai filter yang sudah diterapkan.

Grafik interaktif membandingkan pendapatan dan pembelian dari ringkasan harian sumber yang sama. Periode panjang dijumlahkan ke maksimal enam rentang tanpa membuang nilai transaksi. Nilai pembalikan tetap negatif. Angka rentang dapat ditampilkan melalui pointer, keyboard, atau tombol sebelumnya/berikutnya. Hari tanpa transaksi tidak ditambahkan sebagai data contoh.

Arus kas menampilkan uang masuk/keluar, saldo akhir, dan rincian saldo per akun. Rincian mobil menjadi daftar ringkas pada layar kecil dan tabel pada layar lebih besar. Keadaan kosong tersedia untuk grafik dan mobil.

Manajer melihat **Keuntungan bersih** ketika semua mobil dipilih, dengan rincian empat komponen dan tautan ke laporan pada periode yang sama. Sesuai pilihan pengguna, rumusnya adalah pendapatan penjualan pabrik + sortir − pembelian buah − hak upah muat − pengeluaran operasional dalam periode yang dipilih. Hasil negatif ditandai **Rugi**, positif **Untung**, dan nol **Neto nol**. Pembayaran konsumen/gaji tidak mengurangi biaya lagi; penerimaan penjualan tidak menambah pendapatan lagi. Pembelian dan penjualan dapat terjadi pada tanggal yang berbeda.

Filter mobil menampilkan petunjuk memilih Semua mobil karena pengeluaran umum tidak dialokasikan ke mobil. Kasir melihat pengeluaran tanpa keuntungan bersih. Supir melihat ringkasan yang dibatasi sumbernya, tanpa saldo atau arus kas. Perubahan ini memakai response Dashboard yang sudah ada dan tidak mengubah rumus atau database usaha. Label keuntungan bersih periode juga dipakai dalam laporan, CSV, dan cetak; field internal tetap `period_difference_idr`.

## Verifikasi

- Build frontend/TypeScript: lulus.
- Prettier dan pemeriksaan whitespace: lulus.
- Pengujian laporan dan penjualan/ringkasan setelah penambahan keuntungan bersih: 36 lulus, 2.205 assertions, termasuk nilai rugi yang sama pada ringkasan, CSV, dan cetak.
- Render React: manajer, kasir, supir, filter mobil, data kosong, pembalikan negatif, dan periode 90 hari lulus tanpa warning render.
- Render setelah penambahan kartu keuntungan: untung, rugi, nol, filter mobil, kasir dan supir lulus tanpa warning; jumlah, rincian komponen, tautan periode dan pembatasan tampilan sesuai.
- Browser, interaksi pointer/keyboard langsung, dan tata letak hasil render pada ukuran layar aktual: belum diverifikasi karena koneksi browser tidak tersedia.

Komponen berada pada `resources/js/features/dashboard/components`; halaman Dashboard memakai komponen ini, sedangkan tampilan Laporan tetap memakai komponen laporan yang sudah ada. CSS tambahan dibatasi oleh class Dashboard.
