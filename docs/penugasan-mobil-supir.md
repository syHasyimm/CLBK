# Penugasan satu mobil per akun supir

Tanggal: 10 Oktober 2026. Implementasi dan migrasi database lokal selesai.

## Pemakaian

1. Manajer membuka **Kelola Pengguna**, lalu menambah atau mengubah akun dengan role **Supir**.
2. Pilih satu **Mobil yang ditugaskan** dari mobil aktif, kemudian simpan akun.
3. Pada **Timbang Sawit**, **Penjualan Pabrik**, dan **Penjualan Sortir**, mobil supir terisi otomatis dan terkunci. Supir melanjutkan isian transaksi lainnya.
4. Untuk mengganti mobil, manajer mengubah penugasan akun. Supir memuat ulang form yang sudah terbuka sebelum mencatat transaksi berikutnya.

Akun supir tanpa penugasan tetap dapat masuk dan membaca riwayat yang berhak diakses, tetapi tidak dapat menyimpan transaksi baru. Form menunjukkan **Mobil belum ditetapkan. Hubungi admin.** Mobil nonaktif juga memblokir pencatatan sampai manajer menetapkan mobil aktif.

Daftar pengguna menampilkan plat mobil, status nonaktif, atau Belum ditetapkan. Pengaturan **Supir default** pada Data Mobil tetap merupakan referensi historis; sumber penugasan operasional adalah akun pada Kelola Pengguna. Form Data Mobil menyertakan petunjuk dan tautan ke pengaturan akun.

## Aturan

- Setiap akun supir memiliki paling banyak satu mobil. Beberapa akun supir dapat ditetapkan ke mobil yang sama; hak akses transaksi tetap mengikuti pencatatnya.
- Penugasan hanya dikelola manajer, hanya menunjuk mobil aktif, dan dicatat dalam audit. Profil pribadi tidak dapat mengubah penugasan. Role selain Supir tidak memiliki penugasan mobil.
- Server membaca penugasan terbaru dan memeriksanya di dalam transaksi database yang sama dengan penyimpanan tiket/nota. Nomor mobil dari URL atau kiriman formulir tidak dapat mengalihkan transaksi supir ke mobil lain.
- Versi penugasan bertambah ketika mobil diganti atau dilepas. Form memakai versi saat dibuka, sehingga perubahan A → B → A tetap mengharuskan muat ulang. Perubahan nama, kata sandi, atau status akun tidak mengganti versi penugasan mobil.
- Transaksi yang sudah berhasil disimpan tetap dapat dikembalikan oleh kiriman ulang dengan kunci idempotensi dan data persis sama, termasuk setelah penugasan dilepas. Kiriman ini tidak menciptakan dokumen, nomor, hak, jurnal atau audit tambahan.
- Mobil pada transaksi lama tetap tersimpan pada tiket/nota asal. Pergantian penugasan tidak memperluas akses supir ke transaksi pencatat lain, termasuk ketika keduanya memakai mobil yang sama.
- Manajer tetap dapat memilih mobil untuk pencatatan dan koreksi. Perhitungan pembelian, upah, pendapatan, penerimaan serta keuntungan bersih memakai aturan yang sudah berlaku.

## Penyimpanan dan pemasangan

Migrasi `2026_10_10_000002_assign_driver_vehicle` menambah `users.assigned_vehicle_id` dengan foreign key ke mobil dan batas role Supir, serta `vehicle_assignment_version` berupa integer positif. Tidak ada penugasan otomatis dari Supir default; akun lama memerlukan penetapan oleh manajer.

Data cadangan dan catatan verifikasi instalasi sebelumnya telah diarsipkan di luar source proyek.

## Verifikasi

- Seluruh pengujian PHP: **237 lulus, 5.138 assertions**.
- Pengujian mencakup pembuatan akun dengan mobil, pergantian/pelepasan, validasi mobil, role, profil, form/URL, pilihan mobil, pemalsuan permintaan, form lama, perubahan A → B → A, mobil nonaktif, simpan ganda setelah pelepasan, histori/akses pencatat, perlindungan penghapusan mobil, constraint dan migrasi naik/turun.
- Build frontend/TypeScript, Pint, Prettier, dan pemeriksaan whitespace: lulus.
- Render React: **15 skenario lulus tanpa warning**, mencakup form timbang dan kedua penjualan untuk manajer, supir ditugaskan, belum ditugaskan, mobil nonaktif, serta form akun baru/supir/kasir.
- Tampilan dan interaksi langsung di browser belum diverifikasi karena tidak ada browser yang terhubung.
