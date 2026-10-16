# CLBK TOKE

Workflow sederhana tahap 1–4 sudah diterapkan. Timbangan memilih konsumen, mobil, TBS/sortir, serta anggota; penjualan pabrik memakai berat bersih dan harga otomatis; penjualan sortir memakai berat dan harga manual. Satu kali simpan langsung membentuk transaksi sah. Penjualan pabrik dan sortir mencatat piutang; uang diterima dicatat sebagian/lunas melalui Penerimaan Penjualan dan menambah saldo akun kas/bank yang dipilih. Pembayaran konsumen dan gaji tetap terpisah. Muatan Mobil dan Stok Sortir sudah dihapus. Pengeluaran tidak memakai perjalanan, dan Dashboard/Laporan menyediakan ringkasan periode serta per mobil. Data Master dan histori tetap dipertahankan. Lihat [penerimaan penjualan pabrik dan sortir](docs/penerimaan-semua-penjualan.md), [rencana workflow](docs/rencana-workflow-sederhana.md), [hasil tahap 1](docs/workflow-sederhana-tahap-1.md), [hasil tahap 2](docs/workflow-sederhana-tahap-2.md), dan [hasil tahap 3–4](docs/workflow-sederhana-tahap-3-4.md).

Sistem pengelolaan usaha sawit berbasis web menggunakan Laravel 13, Breeze, React + Inertia, **MySQL 8.4 untuk produksi**, Tailwind 4, dan shadcn/ui. Aplikasi diakses melalui browser komputer maupun ponsel. Development lokal dan suite regresi cepat tetap dapat memakai SQLite.

Implementasi saat ini mencakup fondasi web, identitas/master, harga/timbang, penjualan langsung, keuangan, serta **Tahap 5 PRD: Dashboard dan laporan**. Dashboard menyediakan tautan rincian sumber dan saldo utang konsumen/upah, piutang penjualan, serta arus kas per akun. Laporan menyediakan filter, rincian transaksi, CSV seluruh halaman, cetak/simpan PDF, dan audit manajer. Angka memakai dokumen sumber yang sama, dengan label Selisih transaksi periode. Hasil dan batas verifikasi tersedia pada [hasil tahap 5](docs/tahap-5.md). Tahap 5 pada rencana workflow sederhana adalah pemeriksaan akhir menyeluruh yang berbeda dari Tahap 5 PRD.

Tahap 6 mencakup backup/restore beserta retensi dan rekonsiliasi, pemeriksaan kesehatan, serta deployment **Coolify dengan Nginx + PHP-FPM**. Panduan pemasangan untuk pemula tersedia di [DEPLOY.md](DEPLOY.md). Verifikasi pada server produksi dan pengujian browser perangkat nyata tetap diperlukan. Lihat [hasil tahap 6](docs/tahap-6.md) dan [backup/restore](docs/backup-restore.md).

## Menjalankan web

Pembayaran konsumen melunasi seluruh tiket belum lunas dalam satu pembayaran. `composer dev` menjalankan server dan Vite.

Prasyarat lokal: PHP **8.4+** dengan `pdo_sqlite`, `sqlite3`, `zip`, `mbstring`, `openssl`, `fileinfo`, dan `curl`; Composer 2; Node.js 22.12+ atau 24. Produksi juga memerlukan `pdo_mysql`, klien MySQL, dan resource MySQL 8.4; Dockerfile menyertakan kebutuhan PHP/klien tersebut.

```powershell
composer setup
php artisan clbk:bootstrap-user
composer dev
```

Buka <http://127.0.0.1:8000>. Perintah akun awal meminta nama, email, dan kata sandi melalui terminal. Kata sandi disembunyikan saat diisi. Perintah ini hanya membuat satu **manajer aktif** ketika tabel pengguna masih kosong. Akun operasional tidak memakai kata sandi bawaan dan tidak ada pendaftaran publik. Manajer membuat akun tambahan melalui menu **Akun Pengguna**.

`composer setup` memasang dependensi, menyiapkan `.env` dan database yang belum tersedia, menjalankan migrasi dan seed awal, lalu membuat build frontend. Pengulangan setup mempertahankan APP_KEY dan database yang sudah ada. Perintah ini untuk development lokal; produksi MySQL mengikuti [panduan Coolify](DEPLOY.md).

Untuk menjalankan aset hasil build tanpa Vite:

```powershell
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Pengaturan lokal disimpan di `.env`; produksi Coolify menggunakan runtime Environment Variables resource aplikasi. Produksi mengakses MySQL 8.4 pada jaringan privat, sedangkan `.env` lokal dapat tetap memakai SQLite. Browser hanya mengakses backend melalui HTTP(S).

Email pengaturan ulang kata sandi memakai `MAIL_MAILER=log` pada development. Tautannya tercatat di `storage/logs/laravel.log`; pengiriman email nyata memerlukan konfigurasi mail server pada deployment.

Perbaikan audit keamanan pra-launch tersedia pada [panduan keamanan](docs/keamanan-pra-launch.md). Template produksi berada di [.env.production.example](.env.production.example); isi origin HTTPS, APP_KEY, SMTP, serta proxy yang digunakan sebelum deployment. Aplikasi menolak konfigurasi produksi dengan debug aktif, cookie tidak aman, origin non-HTTPS, atau mailer log/array. Konfigurasi lokal yang sudah ada tetap untuk development.

### Akun lokal untuk setiap role

Untuk mencoba aplikasi dengan Manajer Toke, Supir, dan Kasir, jalankan seeder terpisah pada lingkungan `local` atau `testing`:

```powershell
php artisan db:seed --class=RoleUserSeeder
```

| Role | Email | Kata sandi awal |
|---|---|---|
| Manajer Toke | `manager@clbk.test` | `ClbkToke12345!` |
| Supir | `supir@clbk.test` | `ClbkToke12345!` |
| Kasir | `kasir@clbk.test` | `ClbkToke12345!` |

Ketiga akun baru aktif dan kata sandinya disimpan sebagai hash. Seeder dapat diulang tanpa menggandakan akun atau mengubah nama, role, kata sandi, maupun status aktif akun yang sudah memakai email tersebut. Pembuatan akun dicatat pada audit tanpa kata sandi. Seeder ini tidak dipanggil oleh `DatabaseSeeder`/`composer setup` dan ditolak pada produksi. Jika memakai akun lokal ini, langkah `clbk:bootstrap-user` tidak diperlukan. Akun operasional tetap dibuat melalui bootstrap atau menu **Akun Pengguna**.

## Tahap 1

Untuk fixture mobil dan lima timbangan bertanggal pasti, ikuti [pengujian filter tanggal dan sesi](docs/date-filter-testing.md). Panduan mencakup database lokal terpisah, command staging, pembatalan form, dan sesi Coolify.

Manajer dapat menambah, mencari, mengubah, menonaktifkan, mengaktifkan kembali, serta menghapus data master yang belum digunakan, kecuali kas usaha yang disediakan sebagai satu kas tetap. Data yang dirujuk transaksi ditahan oleh foreign key dan diarahkan untuk dinonaktifkan. Pengelolaan akun pengguna menggunakan penonaktifan; minimal satu manajer harus tetap aktif. Supir dan kasir memperoleh referensi aktif yang dibutuhkan. Supir dapat menambahkan konsumen aktif melalui **Tambah Konsumen** pada formulir timbang atau sidebar; setelah disimpan, konsumen baru langsung terpilih pada formulir timbang. Pengubahan/penghapusan konsumen dan perubahan role tetap dikelola manajer.

Admin dan kasir memakai satu **Kas Usaha** untuk seluruh penerimaan, pembayaran konsumen, gaji muat, dan pengeluaran. Formulir langsung memakai kas tersebut tanpa pemilih akun; metode Tunai/Transfer bank tetap dapat dicatat, dengan referensi wajib untuk transfer. Akun kas tambahan tidak dapat dibuat atau dikelola melalui aplikasi. Saldo dan mutasi lama tetap tersimpan. [Rincian kas tunggal dan tambah konsumen](docs/kas-tunggal-dan-konsumen-supir.md).

Seed awal menyiapkan Kas Utama **tanpa saldo**, enam kategori biaya sesuai PRD, nama usaha CLBK TOKE, zona waktu WIB, toleransi berat 2%, dan awalan nomor bukti CLBK. Seed dapat diulang tanpa menimpa data yang sudah diubah dan tidak membuat akun atau konsumen contoh. Riwayat perubahan master, akun melalui administrasi/bootstrap, dan pengaturan disimpan sebagai audit append-only dalam transaksi yang sama.

Untuk proyek yang sudah menjalankan fondasi, setelah membuat backup database yang konsisten:

```powershell
php artisan migrate --force
php artisan db:seed --force
npm run build
```

Migrasi mempertahankan akun awal fondasi sebagai manajer. Jika belum ada akun, gunakan `php artisan clbk:bootstrap-user`. Lihat [hasil tahap 1](docs/tahap-1.md) untuk pemeriksaan dan batas implementasinya.

## Tahap 2

Manajer dan kasir dapat menerbitkan versi harga beli TBS, sortir awal, upah muat, dan harga sawit pabrik melalui menu **Harga & Upah Muat**. Setiap perubahan disimpan sebagai versi baru tanpa mengubah nilai transaksi yang sudah disahkan. Harga referensi pabrik mempunyai riwayat terpisah per pabrik, dengan pilihan pabrik aktif dan waktu mulai berlaku. Harga beli wajib tersedia untuk tanggal usaha tiket; harga kemarin tidak otomatis dipakai hari ini.

Manajer/supir membuka perjalanan, menyimpan draft timbang, memeriksa ringkasan, lalu mengesahkan tiket. Draft dapat tersimpan tanpa harga atau anggota, tetapi pengesahan memerlukan harga dan minimal satu anggota aktif. Supir memakai waktu server saat pengesahan; hanya manajer dapat memilih tanggal mundur dengan alasan. Pembuat draft dapat mengedit/menghapus draft selama trip masih loading.

Hak konsumen tidak dipotong upah. Pengesahan membekukan tarif, nilai buah, pembagian upah per anggota, alokasi biaya per kategori, serta membentuk lot sortir awal dan receipt stok dalam satu transaksi. Pengiriman ulang dengan kunci sama mengembalikan dokumen yang sama. Harga berubah sebelum konfirmasi mengharuskan pengguna meninjau ringkasan terbaru.

Manajer dapat membatalkan tiket sebelum perjalanan berangkat atau stok sortir bergerak, dengan alasan dan reversal stok. Histori tiket sah tetap tersimpan; tiket pengganti merujuk tiket yang dibatalkan. Kasir dapat membaca tiket sah/batal dan membayar haknya melalui modul tahap 4. Tiket dengan pembayaran konsumen atau gaji yang masih sah tidak dapat dibatalkan.

Berat muatan/hasil pabrik tidak diisi dari tiket. Hasil pemeriksaan harga/timbang dicatat di [hasil tahap 2](docs/tahap-2.md); kelanjutannya tersedia pada tahap 3.

## Tahap 3

Manajer/supir mencatat keberangkatan pada **Muatan Mobil**. Tiket sah dan biaya TBS terkait terkunci saat berangkat; tiga berat pabrik tetap kosong hingga diinput manual. Nota pabrik memakai berat bersih terakhir langsung, harga aktual, serta potongan dengan alasan. Selisih negatif atau di atas 2% memerlukan persetujuan manajer atas versi draft terbaru. Editing draft membatalkan persetujuan sebelumnya.

Retur pabrik membentuk stok sortir dengan biaya asal TBS, tanpa menambah hak konsumen. **Penjualan Sortir** menerima berat dan harga manual, menampilkan stok sebagai informasi, lalu membagi berat ke lot perjalanan dengan FIFO. Penjualan sebagian melepas biaya proporsional; penjualan terakhir menghabiskan berat dan biaya tepat. **Stok Sortir** menyediakan sisa, riwayat mutasi, serta pencatatan/pembalikan kehilangan oleh manajer.

Nota sah mencatat pendapatan dan piutang; penerimaan uang tersedia pada tahap 4. Nota dibalik oleh manajer dengan alasan setelah penerimaan uang yang masih sah dibalik dan stok turunannya dikembalikan. Pembalik laba memakai tanggal hari ini dan mempertahankan posting lama. Nota pengganti memakai nomor baru serta snapshot biaya keberangkatan. Trip lama `correction_pending` tidak memesan mobil yang sudah membuka trip baru. Trip dapat selesai sesudah nota dan stok direkonsiliasi, tanpa menunggu pembayaran konsumen/upah.

Biaya asal yang belum tersedia tetap null dengan label **Belum lengkap**. Supir hanya mengakses trip tugasnya; rincian biaya penuh/margin hanya dikirim ke manajer. Bukti PDF/JPG/PNG disimpan privat dan unduhan memeriksa akses nota. Hasil pengujian, migrasi, dan cakupan berikutnya tercatat di [hasil tahap 3](docs/tahap-3.md).

## Tahap 4

Manajer mencatat saldo awal atau setoran modal melalui **Kas & Bank** sebelum pembayaran. Akun dimulai tanpa saldo; saldo awal hanya sebelum mutasi pertama, sedangkan tambahan dana memakai setoran modal. Setoran dan penarikan pemilik tidak memengaruhi pendapatan/laba.

Kasir/manajer memakai **Uang Penimbang** dan **Gaji Karyawan Muat** untuk memeriksa hak yang belum lunas, lalu membayar sebagian atau seluruh sisa. Rincian anggota muat hanya menampilkan pekerjaan yang masih memiliki sisa upah; pekerjaan lunas dan voucher gaji tersedia pada halaman **Riwayat Penggajian Karyawan** di sidebar serta Menu kasir/admin. Riwayat dapat dicari menurut karyawan, periode, dan nomor dokumen. Pembayaran yang dibalik tetap tercatat dan mengembalikan pekerjaan ke daftar belum lunas. Harga dan hak upah tetap menggunakan snapshot saat timbang. Upah tidak dipotong dari hak konsumen. Gaji memakai rentang pekerjaan yang dipilih, dan penerima nonaktif yang masih memiliki hak tetap dapat dibayar.

Penjualan pabrik dan sortir mencatat piutang tanpa meminta akun kas. Kasir/manajer mencatat pembayaran sebagian/lunas melalui **Penerimaan Penjualan**, memilih nota serta akun tujuan kas/bank. Saldo bertambah hanya saat penerimaan dicatat. Kedua alur mencatat pendapatan sekali. Nota lama yang masih mempunyai sisa piutang juga dibayar melalui modul penerimaan yang sama. Pembatalan nota membalik uang masuk sekaligus dan memerlukan saldo yang cukup. **Uang Keluar Harian** mencatat kategori, deskripsi, biaya, dan kas keluar sekaligus. Metode tunai/transfer harus sesuai jenis akun; transfer memerlukan referensi. Saldo tidak boleh minus dan waktu pencatatan tidak boleh mendahului mutasi terakhir akun.

Voucher memiliki nomor, rincian alokasi/dokumen asal, petugas, bukti privat PDF/JPG/PNG, dan tombol **Cetak / simpan PDF** melalui dialog browser dengan format A4. Manajer dapat membalik voucher dengan alasan dan catatan pengembalian uang; histori asli tetap tersimpan dan jurnal lawan arah memakai hari pembalikan. Supir tidak mempunyai akses keuangan. Pengesahan, alokasi, kas, biaya, dan audit diproses bersama dengan pemeriksaan ulang saldo serta idempotensi.

Migrasi tahap 4 sudah dijalankan pada database lokal dengan backup konsisten dan verifikasi data lama. Rincian pemeriksaan serta batas tahap berikutnya tercatat di [hasil tahap 4](docs/tahap-4.md).

## Tahap 5 — Dashboard dan laporan

Buka **Laporan**, pilih jenis laporan, tanggal, dan filter yang relevan, lalu tekan **Terapkan filter**. Jenis laporan mencakup ringkasan periode, timbang, penjualan pabrik/sortir, piutang, utang konsumen, upah muat, kas/bank, penerimaan penjualan, pengeluaran, dan audit. Tautan **Lihat rincian** membuka dokumen sumber beserta bukti yang dapat diakses sesuai role.

**Ekspor CSV** dan **Cetak / PDF** menggunakan filter yang sudah diterapkan dan seluruh hasil, termasuk halaman tabel berikutnya. CSV memakai UTF-8, pemisah titik koma, rupiah integer, dan kilogram tiga desimal. Cetak menyediakan halaman A4 landscape dan tombol untuk dialog cetak/simpan PDF browser.

Tanggal laporan transaksi mengikuti tanggal dokumen dan pembalikannya. Pada laporan utang/upah/piutang, tanggal menyaring dokumen sumber hak, sementara dibayar dan sisa mengikuti seluruh pembayaran sah saat ini. Arus kas menampilkan saldo sebelum periode, mutasi masuk/keluar, dan saldo akhir masing-masing akun. Selisih transaksi periode = pendapatan − pembelian buah − hak upah − pengeluaran; pembayaran konsumen/gaji tidak mengurangi biaya lagi.

Seluruh **227 tes PHP (4.849 assertions)**, build frontend/TypeScript, Pint, dan Prettier lulus. Pembacaan database lokal lulus rekonsiliasi serta integrity/FK tanpa mengubah transaksi. Pemeriksaan visual browser, layar ponsel, dan hasil cetak manual belum dilakukan pada sesi ini karena koneksi browser/komputer tidak tersedia.

## Tahap 6 — Deployment dan operasional

Pada **Pengaturan Usaha**, manajer mengatur retensi cadangan dan membuat/men-download ZIP database beserta bukti privat dengan autentikasi ulang. Default tujuh harian/empat mingguan, backup harian pukul 23.00 WIB; scheduler server wajib diaktifkan. BACKUP_PATH harus privat dan terpisah dari folder bukti. Pilihan Hostinger menggunakan folder backup lokal tersendiri dengan `BACKUP_REQUIRE_SEPARATE_DISK=false` pada image Coolify; mode `true` mewajibkan perangkat terpisah pada produksi. Backup lokal ikut hilang jika disk/VPS rusak; salinan ke luar VPS tetap disarankan. APP_KEY dipertahankan serta disimpan terpisah dari ZIP.

```powershell
php artisan clbk:backup --reason=pre-upgrade
php artisan clbk:restore LOKASI-BACKUP.zip --verify-only
php artisan clbk:doctor --json
```

Restore hanya pada maintenance setelah service pemegang koneksi dihentikan, wajib membuat backup kondisi saat ini, lalu memeriksa hash seluruh tabel usaha/metrik/bukti. Sesi dan token lama dicabut; maintenance tetap aktif sampai operator selesai memeriksa. Opsi --new-server hanya menerima tujuan baru/kosong. Prosedur lengkap terdapat pada [backup/restore](docs/backup-restore.md).

Produksi memakai container PHP dan resource MySQL 8.4 terpisah pada Coolify. Catatan [tahap 6](docs/tahap-6.md) memuat verifikasi historis SQLite; konfigurasi produksi terbaru mengikuti [panduan Coolify](DEPLOY.md) dan [dukungan MySQL](docs/mysql-production.md). Pengujian browser perangkat nyata, performa di Linux, dan aktivasi produksi tetap perlu dilakukan.

## Struktur proyek

```text
app/
  Actions/Accounts/               # Penyimpanan akun dan perlindungan manajer terakhir
  Actions/MasterData/             # Penyimpanan/penghapusan master dalam transaksi
  Actions/Settings/               # Perubahan pengaturan usaha
  Actions/Sales/                  # Draft, pengesahan, persetujuan selisih, pembalikan
  Actions/Inventory/              # Kehilangan stok dan pembalikannya
  Actions/Finance/                # Posting, pembalikan voucher, dan modal kas
  Domain/Inventory/               # Saldo ledger, FIFO, dan alokasi biaya integer
  Domain/Sales/                   # Rekonsiliasi tiga berat manual dan biaya TBS
  Enums/                         # Role dan allowlist modul master
  Policies/                      # Otorisasi pengelolaan
  Services/AuditService.php       # Audit perubahan tanpa kredensial
  Services/CashLedger.php         # Saldo kas, kronologi, dan jurnal lawan arah
  Queries/PaymentBalances.php     # Sisa hak dan piutang dari alokasi sah
  Console/Commands/BootstrapUser.php
  Http/Controllers/Auth/           # Autentikasi Breeze
  Http/Requests/                   # Validasi request
  Http/Middleware/                 # Integrasi Inertia
  Models/                        # User dan model master
  Support/Database/                # Koordinator transaksi tulis
config/
  clbk.php                        # Zona waktu operasional WIB
  database.php                    # SQLite WAL + IMMEDIATE
resources/
  css/app.css                     # Tailwind 4 dan token tema
  js/
    components/ui/                # Komponen shadcn milik proyek
    components/common/            # Komponen lintas halaman
    hooks/                        # Status koneksi server
    features/profile/components/  # Formulir profil dan kata sandi
    features/master-data/         # Formulir master bersama
    features/users/               # Formulir akun dan role
    features/settings/            # Formulir pengaturan usaha
    features/sales/               # Form penjualan dan ringkasan biaya bersama
    features/finance/             # Card, form, status, riwayat, dan pembalikan bersama
    layouts/                      # Layout masuk dan ruang kerja
    lib/                          # Utilitas UI
    pages/Auth/                   # Login dan pemulihan akun
    pages/Profile/                # Profil dan kata sandi
    pages/MasterData/              # List/form tipis yang memakai komponen bersama
    pages/Users/                   # Akun dan role
    pages/Settings/                # Pengaturan usaha
    pages/Sales/                   # Daftar/form/detail pabrik dan sortir
    pages/Inventory/               # Lot stok dan riwayat mutasi
    pages/Finance/                 # Pembayaran, gaji, penerimaan, biaya, dan kas
    types/                        # Tipe data bersama
  views/app.blade.php
lang/id/                          # Pesan autentikasi dan validasi
scripts/prepare-environment.php
tests/Feature/                    # Autentikasi, sesi, dan konkurensi SQLite
docs/tahap-0.md                    # Catatan fondasi web
docs/tahap-1.md                    # Catatan hasil identitas dan master
docs/tahap-2.md                    # Catatan harga, timbang, dan stok sortir awal
docs/tahap-3.md                    # Catatan penjualan, retur, dan rekonsiliasi stok
docs/tahap-4.md                    # Catatan pembayaran, kas, dan pembalikan keuangan
prd.md                            # Acuan implementasi modul berikutnya
```

Master memakai allowlist `MasterResource` untuk metadata field dan model, dengan policy serta action yang sama. Controller tidak menerima nama tabel dari pengguna. Perhitungan harga, berat, hak, upah, dan stok berada di `app/Domain`; perubahan dokumen berada di `app/Actions`. Policy dan scope membatasi dokumen supir pada perjalanan yang ditugaskan kepadanya. `OperationalPresenter` menyaring rincian biaya dari payload nonmanajer.

## Komponen UI bersama

Komponen dasar berada di `resources/js/components/ui`: `Button`, `Input`, `Card`, `Label`, `Badge`, `Textarea`, dan `AlertDialog`; pilihan native memakai tema yang sama. Komponen lintas halaman mencakup `FormField`, `RecordsTable`, `DataTable`, `ListFilters`, `OperationalFilters`, `ReferencePicker`, `RecordActions`, `ConfirmDialog`, dan `UnsavedChangesGuard`. Pemilih referensi memakai pencarian dan pagination server, bukan memuat seluruh master. Wrapper formulir Breeze memakai komponen dasar yang sama. Warna, ukuran radius, dan font dikelola melalui token CSS di `resources/css/foundation.css`. Alias `@/` menunjuk ke `resources/js/` dan konfigurasi shadcn berada di `components.json`.

Jika menambahkan komponen, gunakan alias proyek dari konfigurasi tersebut. Fondasi menggunakan Tailwind 4 melalui plugin Vite; jangan menambahkan kembali konfigurasi PostCSS/Tailwind 3.

## Transaksi database

Pada SQLite development, koneksi menetapkan foreign key aktif, `journal_mode=WAL`, `synchronous=FULL`, waktu tunggu lock 5 detik, dan `transaction_mode=IMMEDIATE`. PHP 8.4+ diperlukan untuk jalur transaksi ini dalam framework yang terpasang.

Gunakan `App\Support\Database\WriteTransaction` pada batas terluar use case yang mengubah saldo, stok, atau pembayaran:

```php
$result = $transactions->run(function ($connection) {
    // Baca ulang saldo dan lakukan seluruh perubahan melalui koneksi ini.
    // Koordinator sudah memperoleh write lock sebelum callback berjalan.
    return $resultOfDatabaseChanges;
});
```

Contoh tersebut menunjukkan kontrak pemanggilan. Action master, akun, pengaturan, harga, perjalanan, timbang, penjualan, dan stok memakai koordinator yang sama. Koordinator menjalankan commit/rollback melalui Laravel dan mencoba ulang `SQLITE_BUSY`/`SQLITE_LOCKED` maksimal tiga kali. Ini juga mencakup konflik saat `BEGIN`, yang tidak dicoba ulang oleh `DB::transaction(..., 3)` pada framework yang diuji. Callback tidak boleh melakukan efek eksternal yang tidak dapat diulang. Gunakan `afterCommit` atau jalankan efek sekali sesudah transaksi kembali; kegagalan setelah callback selesai tidak mengulang perubahan yang mungkin telah commit. Konflik yang tetap terjadi menghasilkan `WriteConflict`; pengesahan dan pembalikan memakai idempotensi sesuai PRD.

Pada MySQL, koordinator mengambil row lock singleton `database_write_locks` sebelum membaca saldo/data usaha dan mencoba ulang deadlock/timeout 1205/1213. Ini mempertahankan serialisasi penulisan yang sebelumnya disediakan BEGIN IMMEDIATE; throughput penulisan tetap satu transaksi usaha pada satu waktu.

Koordinator tidak menerima pemanggilan di dalam transaksi yang sudah aktif. Gabungkan seluruh perubahan dalam satu use case dan satu koneksi. SQLite lokal tetap harus berada di disk lokal, sedangkan produksi memakai resource MySQL melalui network privat.

Timestamp aplikasi tetap UTC. Zona waktu untuk tanggal operasional adalah `Asia/Jakarta` dari `config/clbk.php`.

## Akses dari komputer dan ponsel

Buka domain HTTPS aplikasi melalui browser, lalu login dengan akun yang diberikan manajer. Tampilan responsif menyesuaikan ukuran layar; data dan otorisasi tetap diproses pada server yang sama. Cetak/PDF, CSV, dan unggah/unduh bukti menggunakan fasilitas browser. Koneksi ke server diperlukan untuk mencatat transaksi.

## Pemeriksaan

```powershell
npm run build
npm run format:check
composer test
vendor\bin\pint --test
composer validate --strict
```

Jalankan build sebelum pengujian HTTP karena halaman Inertia menggunakan manifest Vite. Pengujian konkurensi memakai dua koneksi terhadap SQLite sementara berbasis berkas, bukan hanya database memory.

Pengujian produksi MySQL memakai `php vendor/bin/phpunit --configuration phpunit.mysql.xml` dengan database lokal khusus tes. Persiapan, cakupan, dan pengamanan database tes tersedia di [dukungan MySQL](docs/mysql-production.md#pengujian-lokal).

Lockfile Composer dan npm disertakan. `.env`, database operasional, cache dependensi, dan hasil build tidak dimasukkan ke version control.
