# Penerimaan penjualan pabrik dan sortir

Penjualan pabrik dan sortir disimpan sebagai nota sah dengan piutang. Menyimpan atau mengesahkan penjualan mencatat pendapatan sekali, tanpa menerima uang dan tanpa memilih akun kas/bank. Kasir/manajer membuka **Penerimaan Penjualan**, memilih nota pabrik atau sortir, lalu mencatat uang yang benar-benar diterima. Penerimaan dapat sebagian atau lunas dan langsung menambah saldo akun kas/bank yang dipilih.

Modul menampilkan daftar nota pabrik, nota sortir, dan riwayat penerimaan kedua jenis penjualan. Setiap nota memperlihatkan nilai penjualan, uang diterima, sisa piutang, dan status. Daftar awal berisi nota belum lunas. Filter sebagian/lunas/semua, pencarian nomor/pabrik/pembeli/kontak, dan tanggal berlaku bagi kedua daftar. Riwayat voucher memiliki tautan ke nota asal. Form penerimaan memakai pasangan jenis penjualan dan ID sehingga nota pabrik dan sortir yang memiliki ID sama tetap terpisah.

Penerimaan memeriksa akun aktif, kecocokan metode tunai/transfer dengan jenis akun, referensi transfer, tanggal transaksi, dan sisa piutang terbaru. Nominal harus positif dan tidak boleh melebihi sisa piutang. Pengiriman ulang dengan kunci yang sama tidak menggandakan voucher atau kas; perubahan isi dengan kunci sama ditolak. Transaksi, kas, dan audit diproses bersama. Supir dapat mencatat penjualan, sedangkan pencatatan penerimaan hanya tersedia bagi kasir/manajer.

Penerimaan lama, termasuk penerimaan yang dahulu otomatis, tetap dihitung sebagai uang diterima. Tidak ada pemindahan, penghapusan, atau pencatatan ulang data lama. Sisa piutang lama dapat dibayar lewat modul yang sama. Manajer dapat membalik voucher penerimaan dengan alasan dan catatan pengembalian uang. Pembalikan penerimaan mengurangi kas dan membuka kembali piutang, tanpa mengubah nota atau pendapatan. Pembatalan nota membalik seluruh penerimaan sah terkait sekaligus; saldo harus mencukupi dan histori tetap tersimpan. Endpoint pelunasan langsung pada penjualan telah dihapus.

Perubahan ini tidak memerlukan migrasi database. Penanda penerimaan otomatis dipertahankan sebagai metadata histori.

Verifikasi: seluruh 215 pengujian (3.601 assertion), pemeriksaan TypeScript, build produksi, serta format PHP/frontend berhasil. Database aktif lolos pemeriksaan integritas dan foreign key. Riwayat tiga penerimaan yang sudah ada tetap tersimpan, termasuk penerimaan otomatis lama. Pemeriksaan visual tidak tersedia karena sesi ini tidak memiliki browser terhubung.
