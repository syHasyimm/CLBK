# Tahap 5 PRD — Dashboard dan laporan

Tanggal: 10 Oktober 2026. Status: implementasi dan pengujian otomatis selesai; pemeriksaan visual browser/ponsel serta hasil cetak manual masih tersisa. Acuan: `prd.md` bagian 5.1, 5.14, 16–18, dengan workflow sederhana terbaru. Tahap ini berbeda dari Tahap 5 pemeriksaan akhir pada `rencana-workflow-sederhana.md`.

## Hasil

- Dashboard: metrik periode, tautan ke rincian sumber, ringkasan per mobil, utang konsumen, utang upah, piutang penjualan, saldo kas/bank saat ini, dan arus kas periode per akun.
- Laporan: ringkasan harian/per mobil; timbang; penjualan pabrik/sortir; piutang; utang konsumen beserta umur hak; upah per anggota/tiket beserta tarif dan pembagi terkunci; kas/bank; penerimaan penjualan; pengeluaran; audit.
- Filter: tanggal, mobil, konsumen, pabrik, anggota, akun kas, kategori dan status sesuai jenis laporan. Referensi nonaktif tetap tersedia untuk menelusuri histori. Filter yang tidak relevan ditolak server.
- Rincian sumber: tiket, nota dan voucher dapat dibuka dari baris laporan. Bukti pada nota/voucher tetap privat dan diunduh menurut otorisasi sumber. CSV rincian turut menyertakan tautan sumber.
- Ekspor CSV: UTF-8 BOM, pemisah titik koma, rupiah integer, kilogram tiga desimal, total seluruh hasil, dan perlindungan formula pada teks masukan pengguna. Ekspor mencakup seluruh halaman tabel.
- Cetak/PDF: halaman A4 landscape, periode/filter aktif, rincian seluruh hasil, total, ringkasan saldo dan arus kas sesuai jenis laporan. Tombol **Cetak / simpan PDF** membuka dialog browser.
- Audit: petugas, waktu WIB, aksi, jenis/ID sumber, perubahan atribut usaha yang diizinkan dan alasan koreksi. Payload audit lengkap, kredensial, atribut baru yang tidak dikenal, serta lokasi berkas privat tidak diekspor.

## Pemakaian

Tampilan Dashboard diperbarui setelah implementasi tahap ini dengan grafik periode, filter cepat, susunan metrik yang lebih modern, serta rincian mobil pada layar kecil. Hasil dan batas pemeriksaannya tersedia pada [pembaruan tampilan Dashboard](redesain-dashboard.md).

1. Buka **Dashboard** untuk memeriksa periode dan mobil. Tautan metrik membuka rincian transaksi; tautan saldo saat ini membuka seluruh tanggal sumber sampai hari ini.
2. Buka **Laporan**, pilih jenis laporan, tanggal dan filter, lalu tekan **Terapkan filter**. Total di atas tabel mencakup seluruh hasil, walaupun tabel dibatasi 50 baris per halaman.
3. Klik **Lihat rincian** untuk membuka sumber dan bukti yang tersedia.
4. Gunakan **Ekspor CSV** atau **Cetak / PDF**. Keduanya memakai filter yang telah diterapkan, bukan perubahan formulir yang belum diterapkan. CSV/cetak memuat semua halaman.

## Rumus dan makna tanggal

| Angka | Sumber/perhitungan |
|---|---|
| Pembelian dan hak upah periode | Snapshot tiket sah pada tanggal timbang, dikurangi pembatalan pada tanggal pembatalannya |
| Pendapatan periode | Nilai nota pabrik/sortir pada tanggal penjualan, dikurangi pembalikan pada tanggal pembalikannya |
| Pengeluaran periode | Voucher pengeluaran dan pembalikannya; pembayaran konsumen/gaji tidak menambah biaya kedua kali |
| Keuntungan bersih periode | Pendapatan pabrik + sortir − pembelian buah − hak upah − pengeluaran operasional dalam periode yang dipilih |
| Utang konsumen saat ini | Hak tiket sah − alokasi pembayaran konsumen sah |
| Utang upah saat ini | Pembagian hak anggota terkunci − alokasi pembayaran gaji sah |
| Piutang saat ini | Nilai nota sah − penerimaan penjualan sah, termasuk penerimaan otomatis historis |
| Saldo kas saat ini | Seluruh mutasi masuk − keluar, termasuk pembalikan |
| Arus kas periode | Saldo sebelum tanggal mulai + masuk periode − keluar periode = saldo akhir per akun |
| Saldo berjalan | Seluruh mutasi akun hingga baris tersebut, termasuk histori sebelum periode |

Tanggal laporan utang/upah/piutang menyaring tanggal dokumen sumber, sedangkan dibayar dan sisa memakai keadaan pembayaran seluruh tanggal saat ini. Umur hak konsumen dihitung dari tanggal timbang sampai hari ini. Penerimaan mengikuti tanggal uang diterima dan menurunkan piutang tanpa menambah pendapatan lagi. Setoran modal/penarikan hanya memengaruhi kas.

Pengeluaran umum dan kas tidak dialokasikan otomatis ke mobil. Saat mobil difilter, saldo kas/arus kas tetap seluruh usaha, sedangkan saldo utang/piutang mengikuti mobil. Keuntungan bersih periode hanya tersedia bagi manajer pada seluruh usaha. Label ini menggantikan Selisih transaksi periode sesuai pilihan pengguna; rumus dan field internal `period_difference_idr` tetap sama. Pembelian dan penjualan dapat terjadi pada tanggal yang berbeda. Laporan perjalanan/stok/FIFO dari rancangan lama tidak menjadi workflow baru.

## Hak akses

Manajer mengakses seluruh laporan termasuk keuntungan bersih periode dan audit. Kasir mengakses laporan operasional/keuangan tanpa keuntungan bersih periode atau audit. Supir hanya mengakses ringkasan dan rincian timbang/penjualan miliknya atau perjalanan historis yang ditugaskan; laporan kas, penerimaan, pengeluaran, utang/upah/piutang dan audit ditolak. Batas tersebut berlaku pada layar, CSV, cetak, tautan sumber dan unduhan bukti.

## Verifikasi

| Pemeriksaan | Hasil |
|---|---|
| Seluruh pengujian PHP | **227 lulus, 4.849 assertions** |
| Build frontend / TypeScript | Lulus |
| Pint / Prettier | Lulus |
| Rekonsiliasi database lokal dalam mode read-only | Semua rincian dan saldo sesuai ringkasan sumber |
| SQLite integrity / foreign key | `ok` / 0 pelanggaran |
| Visual browser, ponsel, hasil cetak manual | Belum dilakukan; koneksi browser/komputer tidak tersedia |

Tambahan pengujian mencakup rekonsiliasi hak konsumen/upah/piutang/kas; angka layar/CSV/cetak; 55 nota lintas halaman dengan berat pecahan; filter dan pembayaran sebagian/lunas; master nonaktif; scope supir dan penolakan endpoint sesuai role; pengguna nonaktif; formula CSV dan escaping cetak; pembalikan lintas tengah malam WIB; saldo awal sebelum periode; bukti privat; perubahan audit tanpa atribut sensitif; filter tidak valid dan hasil kosong.

Pembacaan Dashboard/laporan/ekspor/cetak memakai satu snapshot. Pengujian dua koneksi SQLite WAL membuktikan pembacaan tetap konsisten saat koneksi lain commit, tanpa mengambil reservasi write lock. Total/halaman tidak tercampur dengan posting yang terjadi di tengah pembacaan.

Contoh rekonsiliasi otomatis: pembelian Rp3.200.000, hak upah Rp110.000, pendapatan Rp3.350.000 dan pengeluaran Rp50.000 menghasilkan selisih −Rp10.000. Penerimaan Rp3.200.000, pembayaran konsumen Rp1.000.000, gaji Rp10.000 dan pengeluaran Rp50.000 menghasilkan kas Rp2.140.000. Sisa konsumen Rp2.200.000, upah Rp100.000 dan piutang Rp150.000. CSV dan cetak mengandung nilai yang sama.

Database pengujian terpisah dari data usaha. Pembacaan database lokal menggunakan `PRAGMA query_only=ON`; tahap ini tidak memerlukan migrasi baru atau pencatatan transaksi contoh. Implementasi berada pada `ReportController`, `OperationalReport`, `AuditChanges`, `CashFlowSummary`, `PeriodSummary`, `ReadSnapshot`, komponen laporan dan template cetak. Endpoint baru: `GET /reports/export` dan `GET /reports/print`.

Tahap 6 PRD (deployment, backup/restore dan pembuktian hari kerja lengkap melalui browser) berada di luar cakupan ini. Verifikasi visual dan cetak manual perlu dilengkapi pada sesi dengan browser yang terhubung.
