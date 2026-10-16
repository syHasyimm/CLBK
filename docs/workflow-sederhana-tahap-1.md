# Workflow sederhana — tahap 1

Status: selesai pada 9 Oktober 2026. Tahap ini menyiapkan penyimpanan, pemetaan histori, dan aturan akses; perubahan formulir mengikuti tahap 2 dan 3. Seluruh Data Master dipertahankan.

## Perubahan fondasi

Tabel timbangan, penjualan pabrik, dan penjualan sortir sekarang mempunyai `vehicle_id` langsung serta `workflow` (`legacy` atau `direct`). Kendaraan dokumen lama diambil dari perjalanan terkait tanpa mengubah kolom, harga terkunci, nominal, status, petugas, atau waktu sebelumnya.

Dokumen lama tetap bertipe `legacy`, dengan referensi perjalanan yang sesuai kendaraan. Jalur transaksi saat ini sudah mencatat kendaraan langsung untuk setiap tambah/edit draft. Format `direct` disiapkan untuk tahap berikutnya: harus mempunyai kendaraan, tanpa referensi perjalanan, dan tidak boleh berganti format setelah dibuat.

Penjualan pabrik `direct` cukup menyimpan berat bersih; berat awal, sortir pabrik, selisih, dan persetujuan lama dibiarkan kosong. Harga harus terhubung ke versi harga pabrik yang cocok dengan pabrik, waktu, serta snapshot rupiah. Penjualan sortir `direct` dapat menyimpan berat dan harga manual tanpa pembeli wajib atau FIFO. Biaya asal transaksi penjualan baru tetap `unavailable`; tidak diisi dengan perkiraan.

Aturan database mempertahankan proteksi nominal sah dan kendaraan pada dokumen yang sudah diposting. Jalur `direct` menolak stok dari timbangan, retur pabrik, dan alokasi FIFO. Aturan rekonsiliasi legacy tetap berjalan untuk histori dan jalur transaksi saat ini.

Model timbangan/penjualan mempunyai relasi langsung ke mobil. Policy transaksi `direct` memberi akses supir berdasarkan petugas pencatat; supir lain tidak mendapat akses hanya karena menggunakan kendaraan yang sama. Kasir tetap hanya melihat dokumen sah, dan manajer tetap dapat melihat serta mengoreksi sesuai aturan sebelumnya.

Rollback migrasi ini menolak berjalan jika sudah ada dokumen `direct`, sehingga tidak membuang atau mengonversi data baru secara diam-diam. Sebelum transaksi `direct` mulai digunakan, rollback untuk dokumen legacy tetap mempertahankan data dan nomor terakhir AUTOINCREMENT, termasuk nomor milik draft yang sudah dihapus.

## Verifikasi

- Seluruh **173 pengujian (2.079 assertion)** berhasil, termasuk harga terkunci, pembagian upah, keuangan, migrasi histori, akses role, dan format transaksi langsung.
- Pengujian format langsung membuktikan tidak membutuhkan perjalanan atau stok, menolak harga pabrik yang tidak cocok, memeriksa nilai jual saat pengesahan, menjaga kendaraan dokumen sah, serta menolak pembuatan retur/stok/FIFO.
- Pengujian migrasi membandingkan seluruh isi tabel sebelum/sesudah, termasuk pembayaran konsumen, penerimaan uang, audit, dan master; hak/sisa pembayaran tetap benar.
- Pengujian memakai SQLite `:memory:`. Mode pemeliharaan pengujian memakai cache `array`, sehingga mode pemeliharaan server nyata tidak memengaruhi respons HTTP dalam pengujian.
- Migrasi berhasil diuji terlebih dahulu pada salinan database aktual.
- Database aktif sudah menerima migrasi `2026_10_09_000005_prepare_direct_vehicle_workflow`; seluruh delapan migrasi berstatus `Ran`.
- Pemeriksaan pada database aktif selesai **9 Oktober 2026 pukul 22:11:58 WIB**. Semua kolom dan baris lama pada **36 tabel persisten** sama dengan backup sebelum pemasangan; migrasi dan tabel sesi/cache tidak masuk perbandingan tersebut.
- Satu timbangan dan satu penjualan pabrik dipetakan ke mobil yang benar; belum ada penjualan sortir pada database aktif saat pemeriksaan. Nomor terakhir tabel lama tetap sama.
- `PRAGMA integrity_check = ok`; `PRAGMA foreign_key_check` tidak menemukan pelanggaran. Tersedia 106 trigger setelah penambahan aturan workflow.
- Pemeriksaan format PHP berhasil. Halaman login server lokal kembali memberikan HTTP 200, dan mode pemeliharaan sudah nonaktif.

## Backup dan batas tahap

Backup sebelum pekerjaan: `.bootstrap/before-workflow-stage1-20261009.sqlite`. Backup konsisten sebelum pemasangan, setelah mode pemeliharaan aktif: `.bootstrap/before-workflow-stage1-install-20261009.sqlite`. Uji pemasangan berada di `.bootstrap/workflow-stage1-preview.sqlite`. Laporan perbandingan database aktif berada di `.bootstrap/workflow-stage1-report.json`.

Backup konsisten setelah pemasangan: `.bootstrap/after-workflow-stage1-20261009.sqlite`. Salinan ini juga lolos pemeriksaan integritas dan relasi, serta memuat seluruh delapan migrasi.

Database tidak direset dan seeder tidak diulang. Mode pemeliharaan dinonaktifkan setelah migrasi dan verifikasi berhasil. Formulir timbang satu kali simpan, formulir penjualan baru, penghapusan menu perjalanan/stok, serta dashboard/laporan baru **belum** diaktifkan pada tahap ini. Tahap berikutnya adalah perubahan Timbang Sawit sesuai rencana yang disetujui.
