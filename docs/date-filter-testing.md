# Pengujian filter tanggal dan sesi

Fixture menyediakan mobil aktif **BK 2026 UAT**, konsumen **Konsumen Uji Filter Tanggal**, anggota muat, serta lima tiket sah melalui action aplikasi. Waktu mengikuti **WIB**, bukan tanggal UTC.

| Waktu timbang WIB | Hasil untuk 10–12 Oktober 2026 |
| --- | --- |
| 9 Oktober 2026, 23:59:59 | Tidak termasuk |
| 10 Oktober 2026, 00:00:00 | Termasuk: batas awal |
| 11 Oktober 2026, 12:00:00 | Termasuk |
| 12 Oktober 2026, 23:59:59 | Termasuk: batas akhir |
| 13 Oktober 2026, 00:00:00 | Tidak termasuk |

Fixture menerbitkan harga untuk tanggal pengujian saat dijalankan, agar pratinjau form baru berfungsi. Pada hari lain, jalankan ulang fixture atau terbitkan harga hari itu. Pengulangan tidak menggandakan tiket atau mengubah password. Setiap tiket mempunyai transaksi aplikasi tersendiri; pengulangan dapat melanjutkan proses yang terhenti. Referensi nonaktif, penugasan supir ke mobil lain, atau tiket fixture yang diubah dilaporkan sebagai konflik, bukan ditimpa.

## Lokal/test

Pada database **local/testing terpisah** yang sudah dimigrasikan:

```sh
php artisan db:seed --class=WeighingDateFilterSeeder
```

Seeder memanggil seed awal dan `RoleUserSeeder`, sehingga tersedia tiga role seperti [akun lokal di README](../README.md). Supir fixture mendapat penugasan mobil. Seeder ini ditolak pada produksi.

Untuk membuat workspace baru tanpa memakai database kerja, jalankan dari root repo dalam terminal PowerShell khusus pengujian:

```powershell
$fixtureContextPath = php scripts/prepare-date-filter-test.php
$fixtureContext = Get-Content -LiteralPath $fixtureContextPath -Raw | ConvertFrom-Json
foreach ($fixtureSetting in $fixtureContext.environment.PSObject.Properties) {
    Set-Item -Path "Env:$($fixtureSetting.Name)" -Value $fixtureSetting.Value
}
npm run build
php artisan serve --host=127.0.0.1 --port=18067
```

Buka `http://127.0.0.1:18067`. Database, sesi database terenkripsi, key pengujian, serta log tersimpan di `.cache/date-filter-*`. Simpan path `context.json` untuk menjalankan workspace yang sama lagi: baca context, pasang environment, lalu jalankan server tanpa mengulangi script persiapan. Jangan unggah context ke repo. Tutup terminal khusus ini setelah selesai agar environment uji tidak terbawa ke pekerjaan lain.

## Staging Coolify

Gunakan resource, domain HTTPS, dan database MySQL staging yang terpisah. Pertahankan **APP_ENV=production**. Inisialisasi database baru mengikuti [DEPLOY bagian 8](../DEPLOY.md); database yang sudah berjalan tidak diinisialisasi ulang.

1. Login sebagai manajer staging. Melalui **Akun Pengguna**, buat supir uji aktif dengan password sendiri dan tanpa penugasan mobil lain. Tambahkan kasir uji bila dibutuhkan.
2. Buka **Coolify → resource staging → Terminal**. Periksa target tanpa menampilkan secret:

   ```sh
   cd /var/www/html
   gosu www-data php artisan tinker --execute="dump(['origin' => config('app.url'), 'database' => Illuminate\Support\Facades\DB::connection()->getDatabaseName()]);"
   ```

3. Ganti seluruh contoh origin, database, dan email berikut dengan nilai staging Anda:

   ```sh
   gosu www-data php artisan clbk:seed-date-filter \
     --staging-origin=https://staging.example.com \
     --database=clbk_staging \
     --manager=manager-uat@example.com \
     --driver=driver-uat@example.com
   ```

Command mencocokkan origin/database dengan konfigurasi aktif sebelum menulis dan memakai akun yang sudah ada. Tidak ada akun/password demo yang dibuat. `RoleUserSeeder` tetap ditolak di produksi. Pilih resource staging secara eksplisit: kecocokan konfigurasi tidak dapat membuktikan sendiri bahwa database terpisah dari produksi. Command tidak mengganti APP_ENV, membuka maintenance, membersihkan sesi, atau mengganti APP_KEY. Jangan memasangnya sebagai post-deployment resource operasional.

## Konfigurasi sesi hosting

Pada Runtime Variables **resource staging**, gunakan:

```dotenv
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_COOKIE=clbk_staging_session
SESSION_PATH=/
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

Hapus `SESSION_DOMAIN` agar cookie hanya berlaku pada hostname staging. Hapus `SESSION_CONNECTION` bila sesi mengikuti koneksi MySQL aplikasi. Semua replika staging memakai database sesi yang sama dan MySQL memakai volume persisten. Tabel `sessions` sudah ada pada schema aplikasi; tidak diperlukan migrasi baru. Driver `array` tidak menyimpan sesi antar-request dan tidak sesuai untuk hosting/browser.

Gunakan origin HTTPS yang sama pada APP_URL, domain Coolify, dan browser; jangan berpindah antara IP, sslip.io, domain utama, dan domain staging. Gunakan key/cookie tersendiri untuk staging, tetapi **pertahankan APP_KEY staging yang sama pada setiap restart/redeploy dan semua replika staging**. Mengganti key membatalkan kemampuan membaca cookie/sesi lama.

Sesudah perubahan Runtime Variables, **Redeploy staging**, lalu login sekali setelah perubahan nama cookie. Periksa konfigurasi efektif tanpa membaca isi sesi atau secret:

```sh
gosu www-data php artisan tinker --execute="dump(collect(config('session'))->only(['driver', 'lifetime', 'encrypt', 'cookie', 'path', 'domain', 'secure', 'http_only', 'same_site'])->all());"
```

Jika masih diarahkan ke login, periksa Network browser: request `/weighings` harus mengirim cookie sesi ke hostname yang sama. Respons 302 ke `/login` menunjukkan sesi tidak diterima; 419 menunjukkan CSRF tidak cocok. Periksa scope cookie, key yang stabil, database sesi, logs, serta trusted proxy sesuai DEPLOY. Pertahankan Secure/HttpOnly/CSRF; jangan mengganti APP_KEY untuk memperbaiki sesi.

## Verifikasi browser

1. Login sebagai supir uji. Pada **Timbang Sawit**, cari **Konsumen Uji Filter Tanggal** untuk menghindari data staging lain. Sebelum ada penyimpanan tambahan, terdapat lima tiket fixture. Form baru mengisi mobil **BK 2026 UAT** otomatis.
2. Isi berat TBS, pilih **Batal** atau **Daftar timbangan**. **Tetap di sini** mempertahankan input. **Buang perubahan** kembali ke daftar dengan akun tetap login dan tanpa menyimpan tiket. Refresh/tutup tab memakai konfirmasi bawaan browser; Batal/tautan aplikasi memakai dialog aplikasi.
3. Saat form berubah, klik **Keluar dari akun**, lalu **Tetap di sini**. Tidak boleh ada request logout dan input tetap ada. Logout diteruskan hanya setelah pembuangan perubahan disetujui.
4. Isi **Dari tanggal = 2026-10-10**, **Sampai tanggal = 2026-10-12**, lalu **Terapkan**. Tepat tiga tiket fixture tampil, termasuk kedua batas. Reset mengembalikan kelima tiket fixture; rentang 14–15 Oktober kosong untuk fixture.
5. Ulangi daftar/filter sebagai manajer dan kasir; kasir tidak membuka form pencatatan. Supir lain tetap tidak dapat melihat tiket supir fixture.
6. Muat ulang daftar dan restart container staging tanpa mengganti key/driver/database/cookie. Sesi yang belum kedaluwarsa harus tetap login. Validasi form yang gagal dan redirect setelah penyimpanan berhasil juga mempertahankan autentikasi. Penyimpanan baru menambah tiket bertanggal hari pengujian, sehingga jumlah keseluruhan dapat bertambah.
7. Ulangi Batal → Tetap di sini → Batal → Buang perubahan pada lebar ponsel. Gunakan Batal untuk membatalkan form, bukan Keluar dari akun.

## Pengujian otomatis

```sh
php artisan test --filter=WeighingDateFilterTest
php artisan test --filter=WeighingSessionTest
php artisan test --filter=SeedDateFilterCommandTest
npm run build
```

Ketiga kelas juga masuk suite MySQL. Pengujian sesi memakai login/cookie nyata dan sesi database terenkripsi, dengan cache guard/store dilepas di antara request agar persistence tidak tertutup oleh `actingAs`. Pengujian dilakukan pada database terpisah.
