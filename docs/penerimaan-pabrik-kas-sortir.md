# Penerimaan penjualan pabrik dan kas sortir

Catatan historis. Alur saat ini memakai [Penerimaan Penjualan untuk pabrik dan sortir](penerimaan-semua-penjualan.md).

Penjualan pabrik disimpan sebagai nota sah dengan piutang. Formulirnya tidak meminta akun kas/bank dan tidak otomatis menerima uang. Kasir atau manajer membuka **Penerimaan Penjualan**, memilih nota pabrik, lalu mencatat uang yang benar-benar diterima, sebagian atau lunas. Akun kas/bank, metode, waktu, nominal, serta referensi mengikuti aturan pembayaran yang sudah ada. Nominal tidak boleh melebihi sisa piutang dan sisa yang berubah ditolak. Pembayaran tidak mencatat pendapatan kedua kali.

Menu Penerimaan Penjualan menampilkan nota pabrik, nilai penjualan, jumlah diterima, sisa piutang, dan riwayat voucher pabrik. Daftar awal menampilkan nota belum lunas; tersedia filter semua status, sebagian, lunas, pencarian nomor/pabrik, serta tanggal. Formulir dan action penerimaan menolak penjualan sortir. Supir tidak dapat membuka modul penerimaan atau membuat pembayarannya.

Penjualan sortir tetap memilih akun tujuan kas/bank aktif. Jika hanya satu akun aktif, akun itu dipilih otomatis. Saat disimpan, nota, pendapatan, uang masuk, mutasi kas, audit, dan kunci simpan ganda dicatat dalam satu transaksi. Pengiriman ulang tidak menggandakan uang. Akun bank memerlukan referensi transfer. Supir hanya menerima pilihan nama dan jenis akun, tanpa saldo akun atau akses modul keuangan.

Riwayat yang sudah tersimpan tidak diubah. Penerimaan pabrik yang sempat otomatis tetap dihitung sebagai uang diterima sehingga tidak ditagih dua kali; manajer dapat membalik penerimaan tersebut melalui voucher pabrik. Uang sortir otomatis mengikuti nota dan dibalik melalui pembatalan nota. Penerimaan sortir lama tetap dapat dibuka dari Kas & Bank. Sisa penjualan sortir lama dapat diselesaikan lewat **Masukkan sisa uang ke Kas & Bank** di rincian sortir. Endpoint penyelesaian langsung pabrik dihapus agar semua penerimaan pabrik memakai modul yang sama.

Pembatalan nota tetap membalik semua uang masuk yang masih sah untuk nota tersebut dalam transaksi yang sama. Saldo harus cukup untuk pengembalian; kegagalan mempertahankan nota, penerimaan, stok, pendapatan, serta saldo semula. Pembalikan memakai tanggal saat ini dan menyimpan mutasi asli.

Tidak ada migrasi baru, perubahan transaksi usaha, reset database, atau pengulangan seeder. Penanda asal penerimaan otomatis yang sudah ada tetap dipertahankan.

Verifikasi: 215 pengujian dan 3.366 assertion berhasil, termasuk pembayaran pabrik sebagian/lunas, pengiriman ulang, sisa piutang berubah, penolakan penerimaan sortir, serta koreksi penerimaan pabrik yang dahulu otomatis. TypeScript, build produksi, dan format PHP/frontend berhasil. Database aktif lolos pemeriksaan integritas dan foreign key; satu penerimaan pabrik otomatis yang sudah tersimpan tetap dipertahankan. Pemeriksaan visual tidak tersedia karena tidak ada browser terhubung dalam sesi ini.
