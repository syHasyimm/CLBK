# Gaji Karyawan Muat dan pembayaran seluruh periode

Tanggal: 10 Oktober 2026. Tampilan daftar karyawan dan halaman Periksa & Bayar menggunakan palet hijau/sage yang selaras dengan Dashboard dan Penjualan Pabrik, dengan angka upah yang lebih tegas, filter berlabel, serta susunan yang menyesuaikan layar kecil.

## Pemakaian

Daftar karyawan menampilkan kode/nama, jumlah timbangan yang belum lunas, total hak pada pekerjaan tersebut, nilai yang sudah dibayar, sisa upah, status pembayaran, dan pembayaran terakhir. Pencarian nama/kode serta tanggal pekerjaan memakai filter server; pekerjaan lunas tersedia di halaman Riwayat Penggajian Karyawan. Tombol Periksa & Bayar membawa periode yang difilter. Ketika tanggal tidak diisi, rentang pekerjaan belum lunas pertama sampai terakhir karyawan dibawa ke halaman pembayaran. Membuka URL halaman penerima langsung tanpa periode tetap memakai bulan berjalan seperti sebelumnya.

Halaman Periksa & Bayar memiliki **satu bagian Timbangan belum lunas**, tanpa checkbox atau tombol Pilih Semua. Bagian ini menjelaskan total hak periode, pembayaran terdahulu, sisa upah, dan jumlah timbangan yang belum lunas. Setiap rincian belum lunas memuat nomor dan waktu timbang WIB, dasar berat, tarif terkunci, jumlah anggota, hak karyawan, nilai dibayar, dan sisa upah; nomor tiket dapat dibuka untuk melihat sumbernya. Rincian panjang dapat digulir dan tetap memiliki pagination. Pekerjaan lunas dan voucher diakses melalui tautan Riwayat Penggajian Karyawan di header.

Seluruh sisa upah dalam periode otomatis menjadi dasar pembayaran, termasuk timbangan di halaman lain. Nominal awal terisi seluruh sisa periode; pengguna dapat mengurangi nominal untuk pembayaran sebagian. Pembayaran sebagian dialokasikan dari timbangan tertua. Metode, akun kas/bank, referensi transfer, waktu WIB, alasan tanggal sebelumnya, dan catatan tetap tersedia. Konfirmasi menjelaskan karyawan, nominal, akun, waktu, periode, dan cara alokasi. Kondisi lunas atau tanpa timbangan menampilkan penjelasan tanpa form transaksi baru. Karyawan nonaktif tetap dapat menerima sisa hak yang sah.

## Pengolahan server

Form baru mengirim `all_in_period: true`, penerima, periode, dan `expected_remaining_idr` dari total seluruh periode. Server mengambil sisa hak sah penerima dalam periode itu ketika menyimpan, melewati pekerjaan yang sudah lunas, serta mengurutkan waktu timbang dan ID. Rincian anggota hanya menampilkan pekerjaan yang belum lunas, termasuk pembayaran sebagian. Pekerjaan lunas dan voucher tersedia pada halaman Riwayat Penggajian Karyawan untuk kasir serta admin/manajer. Mode otomatis tidak mengirim daftar `selected_ids` sehingga pembayaran tidak dibatasi ukuran halaman atau batas 200 ID pilihan manual.

Pemeriksaan otorisasi, waktu sumber/pembayaran, saldo akun, referensi bank, perubahan sisa hak, idempotensi, dan pencatatan audit dilakukan dalam transaksi tulis yang sama. Perubahan total sisa, termasuk akibat timbangan baru atau pembayaran lain, menyebabkan penolakan snapshot lama sebelum voucher/alokasi/kas ditulis. Pembalikan voucher mengembalikan sisa hak melalui aturan yang sudah ada. Hak upah memakai nilai pembagian yang terkunci saat timbang; pembayaran tidak menghitung ulang tarif atau membukukan biaya upah kedua kali.

Total, rincian halaman, dan riwayat pembayaran dibaca dalam satu snapshot. Mode pilihan ID lama tetap didukung untuk kompatibilitas; perubahan antarmuka otomatis diterapkan pada Gaji Karyawan Muat. Pembayaran Konsumen memakai form dan pemilihan tiket yang sudah ada.

## Verifikasi

- Build frontend/TypeScript, Prettier, Pint, dan pemeriksaan whitespace: lulus.
- Seluruh suite aplikasi: 241 pengujian lulus. Setelah penambahan cakupan tanggal pada daftar, pemeriksaan akhir `FinanceTest` juga lulus: 28 pengujian, 765 assertions.
- Empat pengujian pembayaran otomatis mencakup alokasi dari hak sebagian yang sudah dibayar, tarif terkunci, penerima nonaktif, pembatasan penerima/periode, pembalikan, **201 timbangan lintas halaman**, replay tanpa duplikasi, penolakan sisa yang berubah, nominal berlebih, role, periode, mode, dan waktu pembayaran.
- Render React mencakup daftar dan penerima bagi manajer/kasir, rentang seluruh riwayat ketika daftar tanpa tanggal, data kosong/lunas, penerima nonaktif, riwayat voucher dibalik, pesan validasi tanggal, nominal seluruh periode, ketiadaan checkbox/Pilih Semua, serta kompatibilitas Pembayaran Konsumen. Tidak ada warning render.
- Pemeriksaan visual dan interaksi langsung di browser belum dilakukan karena browser tidak terhubung pada sesi ini.

Komponen khusus berada pada `resources/js/features/finance/components/Payroll*.tsx`. Halaman `Finance/Worklist` dan `Finance/Payee` memakai komponen tersebut untuk upah muat; CSS tambahan dibatasi oleh class `payroll`, `payroll-heading`, `payroll-panel`, dan `payroll-link`. Perubahan ini tidak memerlukan migrasi database.
