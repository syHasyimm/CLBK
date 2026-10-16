# Tahap 4 — keuangan

Status: selesai untuk cakupan tahap 4 pada 9 Oktober 2026. Acuan: `prd.md` bagian 5.7, 5.9–5.10, 6.3–6.5, 11.6–11.7, 12.5, 16–18.

## Hasil modul

- **Uang Penimbang:** tab Belum Lunas/Lunas, card konsumen dengan hak, pembayaran, sisa, tiga tiket terbaru dan harga/berat snapshot, serta pembayaran terakhir. Rincian penerima menyediakan seluruh tiket dengan pagination, pilihan hak, pembayaran sebagian atau pelunasan, dan riwayat voucher. Badge **Sebagian** tetap berada di Belum Lunas. Alokasi mendahulukan tiket tertua yang dipilih, hanya milik satu konsumen.
- **Gaji Karyawan Muat:** card anggota, hak dari pembagian saat tiket disahkan, periode pekerjaan, pilihan pekerjaan, pembayaran sebagian/lunas, dan riwayat. Tarif saat pembayaran tidak menghitung ulang pekerjaan lama. Dasar TBS + sortir awal dan jumlah anggota ditampilkan; upah ditanggung toke. Konsumen/anggota nonaktif yang mempunyai sisa hak tetap dapat menerima pembayaran.
- **Penerimaan Penjualan:** dari detail satu nota pabrik atau sortir sah, kasir/manajer mencatat nominal yang benar-benar diterima. Penerimaan sebagian/lunas menurunkan piutang dan menambah kas. Tidak mencatat pendapatan kedua kali. Daftar/detail voucher menyediakan tautan nota asal.
- **Uang Keluar Harian:** kategori aktif, deskripsi, perjalanan opsional, nominal, akun, waktu, dan catatan. Pengesahan sekaligus membentuk biaya operasional dan kas keluar. Pembayaran konsumen/gaji memakai modulnya sendiri dan tidak membuat biaya ulang.
- **Kas & Bank:** akun, saldo seluruh tanggal, filter tanggal daftar mutasi, masuk/keluar, voucher asal, serta jurnal pembalikan. Manajer mencatat saldo awal sebelum mutasi pertama, setoran modal berikutnya, atau penarikan pemilik. Ketiganya tidak menambah pendapatan/biaya/laba. Akun kas/bank harus sesuai metode tunai/transfer; transfer wajib berreferensi.
- **Voucher dan bukti:** nomor unik, waktu WIB, pihak terkait, akun/metode, petugas, nominal, rincian alokasi/nota, catatan, serta riwayat pembalikan. Bukti PDF/JPG/PNG maksimal 10 MiB disimpan privat, MIME diperiksa dari isi, unduhan memeriksa policy. Voucher mempunyai tombol cetak/simpan PDF melalui dialog browser dan stylesheet A4 dengan identitas usaha dan ruang tanda tangan.
- **Pembalikan:** manajer mengisi alasan dan kejadian/referensi pengembalian uang. Voucher asli dan alokasi tidak dihapus. Status dibalik memulihkan sisa hak/piutang, jurnal kas lawan arah memakai hari ini; pembalikan biaya juga menulis biaya lawan tanda hari ini. Sistem tetap memerlukan saldo cukup saat pembalikan menyebabkan kas keluar. Pembalikan dapat dilakukan untuk akun yang telah nonaktif agar histori dapat dikoreksi.

## Alur pemakaian

1. Manajer menyiapkan dana nyata lewat **Kas & Bank**. Akun baru tetap memiliki saldo nol hingga setoran dicatat.
2. Kasir membuka konsumen/anggota, memeriksa snapshot dan sisa, memilih tiket/pekerjaan, memasukkan nominal/metode/akun, lalu memeriksa konfirmasi sebelum mencatat penyerahan uang.
3. Voucher, alokasi hak, mutasi kas, dan audit tersimpan bersama. Pengiriman ulang key yang sama mengembalikan voucher yang sama; payload berbeda ditolak. Sisa hak yang berubah sejak form dibuka memerlukan pemeriksaan ulang.
4. Penerimaan penjualan dilakukan dari nota sumber. Biaya harian dicatat melalui modul pengeluaran. Semua mutasi dapat ditelusuri dari ledger kas ke voucher dan dokumen asal.
5. Koreksi dilakukan dengan pembalikan setelah pengembalian uang benar-benar terjadi. Nota/tiket asal tidak dapat dibalik/dibatalkan selama masih ada alokasi uang yang sah.

## Perhitungan dan integritas

Rupiah berupa integer. Sisa konsumen = total tiket sah − jumlah alokasi pembayaran yang masih sah. Sisa gaji = `weighing_loaders.share_amount_idr` − alokasi gaji sah. Sisa piutang = pendapatan nota − penerimaan sah. Saldo kas = seluruh mutasi masuk − seluruh mutasi keluar, termasuk jurnal pembalikan. Status Lunas/Sebagian/Belum Lunas diturunkan dari nilai tersebut.

Kas tidak boleh negatif. Waktu tidak boleh di masa depan atau sebelum sumber. Tanggal usaha sebelumnya memerlukan alasan; pencatatan tidak boleh mendahului mutasi terakhir akun agar urutan saldo tetap dapat direkonsiliasi. Periode gaji default mengikuti bulan WIB, termasuk ketika tanggal UTC masih bulan sebelumnya. Filter tanggal pada card membatasi pekerjaan/tiket, sedangkan saldo kas tetap merupakan saldo saat ini dari seluruh tanggal.

Posting dan pembalikan memakai `WriteTransaction` IMMEDIATE: aktor/role, versi, sisa hak/piutang, dan saldo dibaca ulang sesudah write lock. Header, alokasi, kas, biaya, nomor, idempotensi, serta audit commit/rollback bersama. Bukti ditulis sekali di luar callback retry, lalu metadata/audit di dalam transaksi; file dibersihkan bila metadata gagal disimpan. Voucher/alokasi/jurnal tidak memiliki aksi edit/hapus.

Supir ditolak pada endpoint keuangan, walaupun mengirim request langsung. Kasir tidak dapat membalik voucher atau mencatat modal. Tiket dengan alokasi konsumen/gaji sah tidak dapat dibatalkan; nota dengan penerimaan sah tidak dapat dibalik. Pemeriksaan ini tersedia juga pada trigger database, bersama batas nominal/alokasi, kecocokan sumber, saldo, dan immutability.

## Skema dan komponen

Migrasi `2026_10_09_000004_create_finance` menambah tujuh tabel: `customer_payments`, `customer_payment_allocations`, `payroll_payments`, `payroll_payment_allocations`, `sale_receipts`, `daily_expenses`, dan `cash_entries`. Timestamp kejadian voucher diseragamkan sebagai `occurred_at` UTC; label UI menampilkan waktu pembayaran/penerimaan/pengeluaran WIB. `business_date` terpisah dipakai untuk tanggal usaha dan jurnal pembalikan. Penerimaan merujuk tepat satu nota pabrik/sortir.

`profit_entries` diperluas dengan sumber `daily_expense_id` dan perjalanan nullable untuk biaya umum. `attachments` menerima empat jenis voucher tambahan. Rebuild mempertahankan ID, baris, index, dan proteksi lama; pemeriksaan FK dilakukan setelah migrasi. Pembalikan laba mempertahankan referensi ke entry asli.

Action berada di `app/Actions/Finance`, saldo/alokasi pada `CashLedger` dan `PaymentBalances`, policy bersama pada `FinancePolicy`, dan validasi pada `StoreFinanceRequest`. Komponen reusable berada di `resources/js/features/finance/components`: `FinanceForm`, `PayeeSummaryCard`, `PaymentStatusBadge`, `FinanceHistoryTable`, dan `ReverseVoucherForm`. Halaman tipis berada di `resources/js/pages/Finance` dan memakai pemilih referensi, filter, tabel, konfirmasi, serta komponen UI yang sudah ada.

## Pemeriksaan

| Pemeriksaan akhir | Hasil |
|---|---|
| Seluruh PHP tahap 0–4 dan seeder role | **161 lulus, 1.934 assertions** |
| Pengujian keuangan khusus | **23 skenario lulus** |
| Build frontend / TypeScript | Lulus |
| Pint / Prettier | Lulus |
| Composer validate strict | Lulus |
| Console browser pada tab pemeriksaan akhir | Tidak ada warning/error |
| Migrasi database lokal / integrity / FK | Lulus; 26 tabel lama cocok dengan backup |

Skenario otomatis mencakup pembayaran sebagian/lunas sesudah harga berganti; FIFO pilihan dan penerima tercampur; saldo kurang, bayar berlebih dan ringkasan kedaluwarsa; replay key dan konflik payload; snapshot/periode gaji serta penerima nonaktif; penerimaan pabrik dan sortir; biaya satu kali serta pembalikan lintas tanggal; dependensi pembatalan; policy role; transfer dengan referensi; proof privat/content/ukuran; kegagalan audit yang membatalkan posting/pembalikan/biaya; immutable record; kronologi; dan roundtrip migrasi.

Konkurensi diuji dengan **dua proses kasir terpisah** terhadap SQLite WAL. Kedua kasir berebut melunasi tiket yang sama, kemudian berebut dana pada akun bersama untuk penerima berbeda. Masing-masing kasus hanya mengesahkan satu pembayaran ketika hak/dana tidak cukup untuk keduanya; kas akhir tepat nol dan tidak ada pembayaran ganda.

Pemeriksaan browser memakai database uji terpisah, bukan transaksi contoh pada database proyek:

| Langkah | Hasil data uji |
|---|---|
| Saldo awal | Rp10.000.000 |
| Bayar konsumen sebagian | Hak Rp3.200.000, dibayar Rp1.000.000, sisa Rp2.200.000, badge Sebagian |
| Gaji Andi, dua anggota pada dasar 1.100 kg × Rp100/kg | Rp55.000, memakai hak snapshot |
| Terima uang dari nota pabrik Rp3.150.000 | Diterima Rp500.000, sisa piutang Rp2.650.000 |
| Catat biaya BBM | Rp100.000; saldo kas Rp9.345.000 |
| Manajer membalik biaya BBM | Voucher/nominal asli tetap, alasan/pengembalian tersimpan; saldo Rp9.445.000 |
| Pelunasan konsumen lewat layar 390 × 844 | Voucher Rp2.200.000; tab Lunas menampilkan hak/dibayar Rp3.200.000 dan sisa Rp0 |
| Akses role | Kasir mendapat modul keuangan tanpa modal/pembalikan; supir tidak mendapat menu keuangan |

Form pada layar ponsel tidak menambah overflow halaman; tabel riwayat dapat digeser di dalam wadahnya. Screenshot tersimpan di `docs/screenshots/tahap-4-konsumen.jpg`, `tahap-4-lunas.jpg`, `tahap-4-kas.jpg`, `tahap-4-pembalikan.jpg`, dan `tahap-4-mobile.jpg`. Dialog printer/simpan PDF belum diuji langsung; stylesheet A4 tersedia, uji browser perangkat nyata mengikuti tahap 6.

## Migrasi lokal dan batas tahap

Backup konsisten dibuat dengan SQLite `VACUUM INTO` pada `.bootstrap/before-stage4-20261009.sqlite` sebelum migrasi database proyek. Sesudah migrasi, seluruh kolom/baris pada **26 tabel lama** dibandingkan dua arah dengan backup dan cocok, termasuk akun, master, perjalanan, audit, dan sumber transaksi. Tujuh tabel keuangan baru kosong. `integrity_check` menghasilkan `ok` dan `foreign_key_check` tidak menghasilkan kesalahan. Sesi/cache/job tidak masuk perbandingan domain karena bersifat sementara. Tidak ada seed transaksi contoh, perubahan kata sandi, atau saldo buatan pada database proyek.

Rollback skema diuji untuk mempertahankan profit/bukti tahap 3, termasuk entry pembalikan dan FK. Rollback tahap 4 menghapus tabel keuangan tahap 4; pemulihan data keuangan operasional menggunakan backup, bukan rollback migrasi. Jadwal backup, simulasi restore, deployment, dan uji hari kerja melalui browser tetap mengikuti tahap 6.

Tahap 5 belum dikerjakan: metrik dashboard, laporan historis, drill-down dan ekspor akan menggunakan ledger/alokasi ini. Daftar keuangan yang ada menampilkan sisa/status saat ini; filter tanggal pekerjaan tidak mengubahnya menjadi laporan saldo historis.
