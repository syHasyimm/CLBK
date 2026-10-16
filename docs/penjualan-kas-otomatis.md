# Uang penjualan langsung masuk ke Kas & Bank

Catatan historis. Alur saat ini memakai [Penerimaan Penjualan untuk pabrik dan sortir](penerimaan-semua-penjualan.md).

Catatan versi sebelumnya. Alur ini kemudian disesuaikan menjadi [Penerimaan Penjualan khusus pabrik dan uang sortir otomatis](penerimaan-pabrik-kas-sortir.md). Riwayat penerimaan yang sudah ada tetap dipertahankan.

Penjualan pabrik dan sortir memilih akun kas/bank aktif di formulir penjualan. Jika hanya ada satu akun aktif, akun itu dipilih otomatis. Akun bank memerlukan referensi transfer. Supir mendapat pilihan nama dan jenis akun untuk mencatat penjualannya, tanpa mendapatkan akses saldo atau halaman keuangan.

Saat disimpan, penjualan sah, pendapatan, uang masuk sebesar nilai bersih setelah potongan, mutasi kas, audit, dan kunci simpan ganda dicatat dalam satu transaksi database. Kegagalan salah satu bagian membatalkan seluruh bagian. Klik ulang dengan kunci dan data sama tidak menggandakan penjualan atau saldo; perubahan akun atau referensi dengan kunci yang sama ditolak. Pendapatan tetap dicatat sekali. Penjualan dengan nilai akhir nol tidak membuat mutasi kas bernominal nol.

Menu dan endpoint tambah/daftar **Penerimaan Penjualan** dihapus. Voucher dan lampiran lama tetap bisa dibuka dari riwayat Kas & Bank. Daftar voucher jenis penerimaan lama mengarah ke Kas & Bank. Rincian penjualan menampilkan tujuan akun, jumlah, waktu, referensi, dan status uang masuknya.

Penjualan lama yang belum seluruhnya masuk kas tidak diselesaikan diam-diam oleh migrasi. Kasir atau manajer dapat memakai **Masukkan sisa uang ke Kas & Bank** di rincian penjualan. Tindakan ini hanya memasukkan sisa setelah memperhitungkan pembayaran lama yang masih sah, pada waktu server saat ini. Sisa yang berubah atau penjualan yang sudah lunas ditolak; pengiriman ulang dengan kunci yang sama tetap aman.

Manajer membatalkan penjualan dengan alasan. Semua uang masuk yang masih sah untuk nota tersebut, termasuk pembayaran lama, dibalik dalam transaksi yang sama. Saldo akun harus cukup untuk pengembalian; jika tidak, nota, pembayaran, stok, dan pendapatan tetap utuh. Mutasi asli tidak diubah/dihapus. Pembalikan memakai tanggal hari ini dan tetap diperbolehkan untuk akun yang telah dinonaktifkan. Uang masuk otomatis tidak dapat dibalik sendiri melalui halaman voucher; koreksi dilakukan melalui nota penjualan.

Migrasi `2026_10_10_000001_mark_automatic_sale_receipts` menambahkan penanda penerimaan otomatis, indeks untuk mencegah penerimaan otomatis ganda per nota, dan trigger agar penanda asal tidak dapat diubah. Pembayaran lama diberi nilai awal `false`. Migrasi tidak menambah uang, menghapus riwayat, atau menjalankan seeder. Data akun, transaksi, lampiran, serta audit yang sudah ada dipertahankan.

Verifikasi: 211 pengujian dan 3.207 assertion berhasil; TypeScript, build produksi, serta format PHP/frontend berhasil. Backup SQLite konsisten dibuat di `.bootstrap/sale-cash-20261010/before.sqlite` sebelum pemasangan migrasi. Sesudah migrasi, isi dan nominal 36 tabel bisnis tetap sama, dua penerimaan lama tetap bukan otomatis, pemeriksaan integritas menghasilkan `ok`, dan tidak ada pelanggaran foreign key. Database aktif tetap mempunyai satu penjualan sortir lama yang belum masuk kas; penyelesaiannya tersedia di rincian penjualan tersebut. Browser tidak tersedia di sesi ini, sehingga tampilan tidak diperiksa secara visual.
