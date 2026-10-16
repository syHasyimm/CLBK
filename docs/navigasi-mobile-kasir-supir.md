# Navigasi admin, kasir, dan supir

Pembaruan 11 Oktober 2026 mengikuti daftar halaman yang diminta pengguna.

## Menu per role

| Role | Halaman pada navigasi |
| --- | --- |
| Admin/manajer | Dashboard, Harga & Upah Muat, Timbang Sawit, Penjualan Pabrik, Penjualan Sortir, Laporan, Profil Saya, seluruh enam menu Keuangan, lima halaman Data master, Akun Pengguna, Pengaturan Usaha |
| Kasir | Dashboard, Harga & Upah Muat, Laporan, Profil Saya, Uang Penimbang, Gaji Karyawan Muat, Riwayat Penggajian Karyawan, Penerimaan Penjualan, Uang Keluar Harian, Kas Usaha |
| Supir | Dashboard, Timbang Sawit, Tambah Konsumen, Penjualan Pabrik, Penjualan Sortir, Profil Saya |

Pada layar di bawah 1.024 px, navigasi bawah kasir berurutan Dashboard, Penimbang, **Menu**, Gaji Muat, dan Kas Usaha. Tombol Menu tepat di tengah memakai ikon hijau yang terangkat dari navigasi. Tombol ini membuka modal berisi seluruh sepuluh halaman dalam susunan dua kolom, dengan ikon, penjelasan singkat, dan tanda halaman aktif. Penerimaan Penjualan tersedia untuk mencatat penerimaan dari pabrik dan sortir. Riwayat Penggajian Karyawan menyediakan pekerjaan lunas dan voucher gaji. Halaman tambahan menandai tombol Menu sebagai aktif. Navigasi bawah supir berurutan **Dashboard, Timbang, Menu, Pabrik, Sortir**. Panel **Menu supir** menyediakan seluruh enam tautan, termasuk Tambah Konsumen dan Profil Saya; kedua halaman tersebut menandai tombol Menu sebagai aktif.

Admin/manajer memakai komponen sidebar, header, dan navigasi ponsel yang sama dengan kasir. Navigasi bawahnya berurutan Dashboard, Penimbang, **Menu**, Gaji Muat, dan Kas Usaha. Modal **Menu admin** menyediakan seluruh 20 tautan dalam kelompok Ringkasan usaha, Operasional, Keuangan, Data master, Administrasi, dan Akun. Isi modal dapat digulir tanpa menyembunyikan judul atau tombol tutup; kelima halaman master memiliki tautan serta penanda aktif masing-masing.

Mulai lebar 1.024 px (`lg`/64 rem), seluruh role memakai sidebar tetap selebar 256 px. Navigasi bawah disembunyikan dan konten diberi ruang di sebelah sidebar. Kasir memiliki kelompok Ringkasan usaha, Keuangan, dan Akun; supir memiliki Operasional dan Akun. Nama serta role pengguna tampil sebagai informasi di bagian bawah sidebar. Sidebar dapat digulir pada layar desktop yang pendek.

Header ponsel menampilkan logo dalam bidang hijau lembut, nama usaha, keterangan pengelolaan usaha sawit, label role, dan tombol keluar. Header seluruh role tidak memiliki tombol profil atau avatar akun. Profil Saya tersedia melalui modal Menu seluruh role dan sidebar desktop. Header desktop menampilkan nama halaman aktif dan tombol keluar yang memakai permintaan POST.

Panel menu admin, kasir, dan supir memakai dialog Radix dengan fokus keyboard, tombol tutup, Escape, serta penutupan saat memilih halaman. Saat layar beralih ke desktop, modal menutup otomatis; fokus kembali ke nama halaman pada header desktop. Listener media query dibersihkan saat layout dilepas.

Posisi gulir sidebar desktop disimpan per pengguna selama aplikasi terbuka dan dipulihkan sebelum halaman baru ditampilkan. Memilih tautan bagian bawah tidak lagi mengembalikan sidebar ke atas; posisi gulir konten halaman tetap mengikuti perilaku navigasi halaman.

Sidebar seluruh role dan panel Menu ponsel memakai scrollbar tipis berwarna hijau dengan jalur transparan. Pada browser yang mendukung, pegangan membulat dan menguat saat diarahkan kursor atau digeser; fokus keyboard turut menguatkan warna area gulir. Ruang scrollbar disediakan tetap agar tautan tidak bergeser ketika scrollbar muncul. Mode warna kontras sistem memakai tampilan scrollbar bawaan.

Modal muncul dari bawah pada ponsel dan berada di tengah layar pada perangkat lebih lebar. Latar meredup, modal bergerak dan membesar sedikit, lalu tautan muncul bertahap. Penutupan memakai animasi keluar sebelum elemen dilepas oleh Radix. Animasi menghormati pengaturan `prefers-reduced-motion`. Isi menu dapat digulir pada layar pendek, sementara judul dan tombol tutup tetap terlihat.

## Tampilan layar kecil

- Navigasi bawah tetap terlihat di ponsel/tablet, dengan ruang konten dan padding safe area untuk perangkat berponi atau indikator home. Header juga mengikuti safe area atas. Padding navigasi bawah dihapus pada desktop.
- Daftar transaksi umum, riwayat pembayaran, pengeluaran, dan mutasi kas memakai kartu berlabel di layar kecil. Tabel tetap tersedia pada layar besar dan cetak.
- Laporan ringkasan menyediakan rincian harian/per mobil dan arus kas dalam susunan kartu pada ponsel.
- Filter pencarian, status, dan tanggal menumpuk sesuai lebar layar. Input memakai ukuran huruf 16 px serta tinggi minimum 44 px pada ponsel.
- Timbang Sawit, penjualan pabrik/sortir, pembayaran konsumen, dan pembayaran gaji mengikuti layout ponsel atau desktop sesuai lebar layar; penugasan mobil dan rincian upah tetap mengikuti alur yang sudah ada.

Pada formulir Timbang Sawit supir, anggota muat aktif langsung tampil sebagai tag nama. Ketuk tag untuk memilih; tag terpilih berwarna hijau dan bertanda centang. Ketuk kembali untuk membatalkan pilihan. Jumlah anggota dan pembagian upah mengikuti pilihan saat ini. Tag membungkus ke baris berikutnya pada layar kecil, mendukung keyboard, dan tidak dapat diubah selama penyimpanan. Bila belum ada anggota aktif, formulir meminta supir menghubungi manajer.

Tombol **Kembali** dan **Simpan Timbangan** berada pada bilah tetap sehingga penambahan anggota dan rincian upah tidak mendorong tombol keluar layar. Pada ponsel/tablet, bilah berada di atas navigasi bawah dan mengikuti safe area; pada desktop, bilah mengikuti lebar konten di sebelah sidebar. Formulir menyisakan ruang gulir agar rincian upah terakhir tetap dapat dibaca, dan tombol simpan memakai tinggi 48 px dengan lebar yang menyesuaikan layar kecil.

## Hak akses dan tautan sumber

Admin/manajer dan kasir memakai satu Kas Usaha. Pemilih akun dan menu master akun kas dihapus; pembayaran tunai maupun transfer otomatis memakai kas yang sama. Di halaman Kas Usaha, manajer memiliki tombol **Buat Kas Usaha** yang aktif hanya ketika belum ada akun kas. Pembuatan menghasilkan kas aktif dengan saldo Rp0 tanpa input kode. Bila sudah ada akun, termasuk yang nonaktif, tombol tetap tampil dalam keadaan nonaktif dan server menolak pembuatan tambahan. Kasir tidak melihat tombol tersebut. Kas tidak dapat diganti, dinonaktifkan, atau dihapus lewat aplikasi. Riwayat serta saldo yang sudah tercatat tetap tersimpan.

Supir dapat menambahkan konsumen melalui sidebar, panel Menu supir pada ponsel/tablet, dan tombol Tambah konsumen pada formulir Timbang Sawit. Panel selalu mengikuti daftar tautan yang diizinkan bagi role tersebut sehingga fitur tambahan tetap dapat diakses dari navigasi ponsel. Setelah penyimpanan, formulir timbang terbuka dengan konsumen baru langsung terpilih. Supir tidak mendapat akses daftar, pengubahan, atau penghapusan master konsumen.

Formulir tambah/ubah dan daftar master tidak menampilkan kolom Kode. Formulir Tambah Konsumen supir cukup mewajibkan nama; identitas mobil memakai plat nomor. Kode internal dibuat otomatis dalam transaksi penyimpanan, tetap unik terhadap data lama, serta tidak berubah saat edit. Pemilih referensi menampilkan nama atau plat tanpa awalan kode master.

Supir tidak dapat membuka Harga & Upah Muat atau halaman Laporan, ekspor CSV, maupun cetak laporan melalui URL langsung. Dashboard supir mengarahkan rincian transaksi ke daftar Timbang, Pabrik, atau Sortir; tautan laporan di grafik dan rincian mobil disembunyikan.

Manajer dan kasir dapat menerbitkan versi harga beli TBS, harga beli sortir, upah muat, dan harga sawit pabrik melalui menu Harga & Upah Muat. Formulir kasir menyediakan harga beli dan upah muat serta harga referensi pabrik, dengan pilihan pabrik aktif, waktu mulai berlaku, dan harga TBS per kg. Hak penerbitan harga diperiksa di server, dan setiap perubahan disimpan sebagai versi baru dengan petugas penerbit tercatat dalam audit tanpa mengubah nilai transaksi yang sudah disahkan. Kasir juga dapat melihat laporan serta membuka dan mencatat Penerimaan Penjualan pabrik maupun sortir. Menu operasional yang tidak ditampilkan pada navigasi kasir tetap dapat dibuka melalui rincian dokumen dari laporan, voucher, dan mutasi kas sesuai izin yang ada. Aturan pencatatan keuangan, pembalikan, dan penerimaan penjualan tetap mengikuti implementasi sebelumnya.

## Uang Penimbang dan Periksa Pembayaran

Daftar kartu konsumen hanya memuat timbangan dengan sisa hak lebih dari nol untuk admin/manajer dan kasir. Timbangan lunas dikeluarkan sebelum pengelompokan kartu dan pemilihan tiga tiket pratinjau. Jumlah tiket serta nominal pada kartu mengikuti timbangan yang masih memiliki sisa; pembayaran sebagian tetap tampil. Tab Lunas di daftar kartu konsumen dihapus, dan parameter status lama dinormalisasi menjadi Belum Lunas.

Pada kartu Uang Penimbang, rincian setiap tiket memisahkan **Berat TBS** dan **Berat sortir** menjadi dua kolom. Angka berat tampil besar dengan satuan kg dan format Indonesia; harga per kg ditampilkan terpisah di bawahnya. Buah yang tidak tercatat pada tiket ditampilkan 0 kg dengan keterangan tidak ada buah tersebut. Berat ini berasal dari masing-masing tiket pratinjau, bukan total seluruh tiket konsumen.

Periksa Pembayaran menyaring tiket lunas sebelum pagination 50 baris, sehingga riwayat lama tidak menghabiskan halaman pilihan pembayaran. Ringkasan periode tetap mencakup semua hak dan pembayaran dalam periode, sedangkan kartu pilihan hanya memuat sisa hak. Jika semuanya lunas, form diganti keterangan tidak ada timbangan yang belum lunas. Voucher dan riwayat pembayaran tetap tersimpan; pembalikan pembayaran mengembalikan tiket ke daftar yang perlu dibayar.

## Riwayat Penggajian Karyawan

Kasir dan admin/manajer memiliki tautan Riwayat Penggajian Karyawan pada sidebar desktop dan modal Menu. Gaji Karyawan Muat serta rincian anggota hanya menampilkan pekerjaan dengan sisa upah; pembayaran sebagian tetap tersedia sampai seluruh haknya lunas. Penyaringan dilakukan sebelum pagination dan pengelompokan kartu. Ringkasan rincian periode tetap memakai seluruh hak dan pembayaran sah untuk menjaga dasar pembayaran.

Setelah pembayaran gaji berhasil, kasir dan admin/manajer diarahkan ke halaman daftar Gaji Karyawan Muat (`payroll.index`) dengan pesan berhasil. Berlaku untuk pembayaran sebagian dan pelunasan; voucher tetap tersedia melalui riwayat penggajian.

Halaman riwayat memisahkan Pekerjaan lunas dari Voucher pembayaran. Pekerjaan lunas memakai tanggal pekerjaan dan hak upah yang dikunci saat timbang. Voucher memakai tanggal pembayaran, periode gaji, nominal, akun, dan status Sah/Dibalik; bukti gaji dapat dibuka dan dicetak. Keduanya mendukung pencarian nama/kode karyawan atau nomor dokumen, rentang tanggal, filter satu karyawan, dan pagination. Riwayat awal mencakup seluruh tanggal, termasuk karyawan nonaktif.

Pekerjaan yang kembali mempunyai sisa akibat pembalikan keluar dari tab Pekerjaan lunas dan muncul lagi pada rincian anggota. Voucher pembalikan tetap berada di riwayat. Navigasi hanya menandai Gaji Muat sebagai aktif pada daftar/rincian pembayaran, dan menandai Riwayat Penggajian Karyawan pada halaman riwayat/voucher; supir tidak mendapat akses riwayat.

## Verifikasi

- Tombol Buat Kas Usaha manajer: `CreateBusinessCashTest`, `SingleCashTest`, dan `MasterDataTest` lulus, 41 pengujian dan 908 assertions. Mencakup pembuatan pertama tanpa kode, saldo Rp0, pencatatan modal sesudah pembuatan, tombol nonaktif setelah satu akun, penolakan kas kedua termasuk akun lama/nonaktif, otorisasi server/action, pembacaan ulang role, audit atomik, dan regresi kas tunggal/master. Build produksi dan pemeriksaan format berhasil.

- Pembaruan master tanpa input kode: `MasterDataTest` dan `DriverCustomerTest` lulus, 35 pengujian dan 610 assertions. Mencakup semua lima master yang dapat ditambah, formulir tanpa kolom kode, kode otomatis unik terhadap data lama, kode tidak dipakai ulang setelah penghapusan, edit tanpa mengubah kode, rollback audit/urutan, validasi plat, serta penambahan konsumen oleh supir.

- Pembaruan Riwayat Penggajian Karyawan: `FinanceTest` dan `PayrollHistoryTest` lulus dengan 35 pengujian dan 1.917 assertions. Mencakup pelunasan/pembayaran sebagian, pembalikan, karyawan nonaktif, penyaringan sebelum pagination, ringkasan seluruh halaman, basis tanggal pekerjaan/pembayaran, filter, akses role, dan riwayat tanpa perubahan kas/audit.

- `php artisan test --compact`: 245 tes, 5.838 assertion, berhasil. Mencakup seluruh role, laporan/CSV/cetak, penerimaan penjualan, histori transaksi, penugasan mobil, serta penyaringan timbangan lunas pada kartu konsumen dan Periksa Pembayaran.
- Pemeriksaan keuangan terarah: 30 tes, 1.125 assertion, berhasil. Memeriksa pembayaran sebagian/lunas, riwayat tetap tersimpan, muncul kembali setelah pembalikan, dan penyaringan sebelum pagination meskipun ada 52 tiket lunas.
- `npm run build`: TypeScript dan build produksi berhasil.
- Verifikasi render navigasi terbaru: 68 skenario seluruh role, header tanpa tautan profil, sidebar, aturan visibilitas desktop/ponsel, posisi Menu di tengah, sembilan tautan kasir, 20 tautan admin, enam halaman detail master, penanda aktif penerimaan/riwayat/voucher, halaman keuangan/laporan, serta nama usaha/akun panjang berhasil tanpa peringatan React.
- Verifikasi render pembayaran terbaru: 23 skenario gaji/pembayaran konsumen berhasil, termasuk kartu hanya belum lunas dan keadaan kosong sesudah pelunasan untuk admin/kasir. Regresi penugasan mobil/form serta penjualan pabrik telah diperiksa pada pembaruan sebelumnya.

Browser tidak terhubung pada sesi ini. Pemeriksaan visual pada perangkat 320/375/390 px, peralihan 1.023 ke 1.024 px saat modal terbuka, interaksi panel Menu, keyboard virtual, safe area, dan pratinjau cetak masih perlu dilakukan di browser. Verifikasi render di atas memeriksa struktur dan tautan, bukan ukuran piksel, interaksi browser, atau hasil screenshot.
