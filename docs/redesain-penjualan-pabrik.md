# Pembaruan tampilan Penjualan Pabrik

Tanggal: 10 Oktober 2026. Halaman daftar dan pencatatan Penjualan Pabrik memakai tampilan khusus dengan palet hijau/sage yang selaras dengan Dashboard, tipografi angka yang lebih tegas, dan jarak antarbagian yang lebih lapang.

Daftar nota menampilkan nomor/tanggal transaksi, pabrik dan nomor nota, mobil, berat bersih, harga per kilogram, nilai nota setelah potongan, status penerimaan, dan sisa yang belum diterima. Tabel digunakan pada layar besar; layar kecil memakai daftar ringkas dengan rincian yang sama. Filter nomor penjualan/nota, status, dan tanggal memiliki label, keadaan memuat, tombol reset, dan pesan validasi. Jumlah nota serta halaman mengikuti pagination server dan mempertahankan query filter. Riwayat tanpa berat menampilkan tanda kosong, bukan angka nol.

Halaman pencatatan dibagi menjadi Mobil & pabrik, Hasil timbang pabrik, dan Informasi nota. Berat bersih menggunakan input besar dengan satuan kilogram; harga pabrik otomatis tampil di sebelahnya. Nomor nota dan potongan opsional langsung terlihat. Catatan dapat dibuka saat diperlukan. Form pengganti tetap menampilkan nomor asal, berat/potongan awal, dan alasan koreksi wajib.

Ringkasan menonjolkan nilai bersih penjualan, disertai berat, harga, nilai buah, dan potongan dari preview server. Ringkasan tetap berada di samping form pada layar besar dan muncul setelah isian pada layar kecil. Nilai yang belum tersedia ditampilkan dengan tanda kosong. Tombol simpan memperlihatkan keadaan memproses dan aktif setelah persyaratan serta preview terpenuhi.

Supir melihat mobil yang ditetapkan oleh admin sebagai isian terkunci. Akun tanpa penugasan aktif tetap mendapat pesan dan tombol simpan nonaktif. Harga, token revisi penugasan, idempotensi, dan validasi memakai alur penyimpanan yang sudah ada. Uang diterima dicatat melalui Penerimaan Penjualan; tautan menuju modul tersebut tersedia bagi manajer dan kasir. Kasir dapat membaca daftar tanpa tombol pencatatan baru.

## Verifikasi

- Build frontend dan TypeScript: lulus.
- Prettier dan pemeriksaan whitespace: lulus.
- `DriverVehicleAssignmentTest` serta `DirectSalesAndSummaryTest`: 35 pengujian lulus, 1.244 assertions, meliputi pembatasan role, penugasan/stale form, harga, penerimaan sebagian/lunas, idempotensi, dan pembalikan.
- Render React: 16 skenario daftar/ringkasan lulus tanpa warning, mencakup tiga role, status penerimaan, pagination, keadaan kosong/filter, validasi tanggal, umpan balik tersimpan, form koreksi, kompatibilitas daftar sortir, serta ringkasan menunggu/nilai bersih/nol.
- Pemeriksaan tambahan 15 render form mencakup pencatatan timbang/pabrik/sortir bagi manajer serta supir ditugaskan/tanpa mobil/mobil nonaktif, dan form pengguna.
- Pemeriksaan visual, interaksi browser, serta ukuran layar aktual belum dilakukan karena tidak ada browser yang terhubung pada sesi ini.

Komponen tampilan khusus berada pada `resources/js/features/sales/components/FactorySalesIndex.tsx`, `FactorySalesFilters.tsx`, `FactorySalesTable.tsx`, dan `FactorySaleSummary.tsx`. Form menggunakan cabang pabrik pada `SaleForm.tsx`; styling tambahan dibatasi oleh class `factory-sales`, `factory-heading`, `factory-panel`, `factory-text-link`, dan `factory-step`.
