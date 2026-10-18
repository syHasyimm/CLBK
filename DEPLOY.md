# Deploy CLBK dengan Dockerfile dan Coolify

Image menjalankan PHP 8.4-FPM, Nginx pada port **8080**, dan scheduler Laravel. React dibangun saat build memakai Node.js 24; Node.js tidak diperlukan di container produksi. MySQL **8.4** berjalan sebagai resource terpisah. Alur ini memakai HTTPS dari proxy Coolify.

## 1. Siapkan resource aplikasi

Commit dan push source, termasuk `Dockerfile`, `.dockerignore`, folder `docker/`, dan seluruh migration PHP. Pada aplikasi Coolify pilih:

| Kolom | Nilai |
| --- | --- |
| Build Pack | Dockerfile |
| Base Directory | `/` |
| Dockerfile Location | `/Dockerfile` |
| Ports Exposes | `8080` |
| Ports Mappings | Kosong |
| Domains | `https://ncimproject.tech:8080` |

Suffix `:8080` pada konfigurasi domain memilih port container. Pengguna tetap membuka **https://ncimproject.tech**, tanpa port dan tanpa `/login` di kolom Domains. Domain harus mengarah ke VPS dan sertifikat HTTPS Coolify harus aktif. Jika ada record DNS AAAA, pastikan IPv6-nya juga menuju VPS yang sama.

Jangan membuka port FPM 9000 ke internet. Aplikasi dan resource MySQL harus terhubung melalui jaringan internal Coolify; port database tidak perlu dipublikasikan.

## 2. Pasang penyimpanan persisten

Persistent storage menjaga berkas tetap ada ketika Coolify mengganti container saat redeploy. Mengisi env saja belum membuat penyimpanan persisten; volume juga harus dipasang melalui Coolify.

### Tambahkan dua volume pada resource aplikasi CLBK

1. Buka **Projects > project Anda > aplikasi CLBK > Configuration > Persistent Storage**.
2. Pilih **Add > Volume Mount** (pada versi lain tombolnya **Add Volume**).
3. Tambahkan volume pertama: **Name** `clbk-data`, **Destination Path** `/data`, lalu **Add/Save**. Jika Name sudah diisi otomatis oleh Coolify, nama itu boleh dipertahankan. Volume Mount tidak memerlukan Source Path folder VPS yang dibuat manual.
4. Tambahkan volume kedua: **Name** `clbk-backups`, **Destination Path** `/backups`, lalu **Add/Save**.
5. Isi env pada langkah 3, kemudian **Redeploy**. Mount baru diterapkan ketika container dibuat ulang.

| Volume | Destination Path dalam container | Isi |
| --- | --- | --- |
| Data aplikasi | `/data` | Bukti privat `/data/proofs` dan runtime Laravel `/data/storage` |
| Backup aplikasi | `/backups` | Arsip backup otomatis dan manual |

Pasang hanya dua mount induk tersebut. Tidak perlu membuat mount tambahan untuk `/data/proofs`, `/data/storage`, atau `/var/www/html`; folder di dalam `/data` disiapkan oleh entrypoint beserta permission-nya. Bukti privat tidak perlu dipublikasikan dengan `storage:link`.

Jika sudah memiliki data, pertahankan mount, nama/source volume, APP_KEY, dan lokasi berkas lama. Memasang volume kosong pada destination yang sama dapat menyembunyikan data lama. Jangan menghapus volume atau mengganti nama/source-nya saat redeploy. Default `CLBK_PRIVATE_STORAGE=/data/proofs` mengikuti deployment sebelumnya; jika instalasi Anda memakai lokasi lain di dalam `/data`, pertahankan lokasi yang benar.

Alternatif jika ingin folder VPS yang terlihat langsung: pilih **Directory Mount**, dengan **Source Path** `/srv/clbk/data` > **Destination Path** `/data`, lalu `/srv/clbk/backups` > `/backups`. Untuk instalasi baru, siapkan kedua folder di terminal host VPS sebelum memasang mount:

```sh
install -d -m 750 /srv/clbk/data /srv/clbk/backups
```

Pilih Volume Mount atau Directory Mount untuk setiap destination, jangan keduanya sekaligus. Jika sebelumnya memakai volume Docker, jangan berpindah ke folder baru tanpa memindahkan dan memverifikasi datanya terlebih dahulu.

### Env yang sesuai dengan mount aplikasi

Masukkan pada **Environment Variables** aplikasi CLBK untuk runtime:

```env
LARAVEL_STORAGE_PATH=/data/storage
CLBK_PRIVATE_STORAGE=/data/proofs
BACKUP_PATH=/backups
BACKUP_REQUIRE_SEPARATE_DISK=false
SESSION_DRIVER=database
SESSION_ENCRYPT=true
CACHE_STORE=database
```

Path env adalah lokasi **di dalam container**, sehingga tetap `/data/...` dan `/backups` meskipun Source Path di VPS memakai `/srv/clbk/...`. `BACKUP_REQUIRE_SEPARATE_DISK=false` mengizinkan folder backup terpisah pada disk VPS yang sama. Salin arsip berkala ke luar VPS karena kerusakan disk dapat menghilangkan data dan backup lokal sekaligus. Simpan APP_KEY secara terpisah untuk pemulihan backup.

### Pastikan resource MySQL juga persisten

Buka **resource MySQL 8.4 > Configuration > Persistent Storage** dan periksa mount data MySQL pada **Destination Path `/var/lib/mysql`**. Resource database Coolify biasanya menyiapkan penyimpanan ini saat dibuat; pertahankan mount yang sudah ada. Lokasi ini berada pada container MySQL, bukan aplikasi CLBK. Akun, transaksi, sesi, dan cache database tersimpan di MySQL, sehingga dua volume aplikasi tidak menggantikan penyimpanan database.

### Verifikasi setelah deploy

Di **Terminal aplikasi CLBK**, jalankan:

```sh
mountpoint /data
mountpoint /backups
```

Hasil yang diharapkan: `/data is a mountpoint` dan `/backups is a mountpoint`. Jika muncul `is not a mountpoint`, periksa Destination Path pada resource aplikasi lalu redeploy. Entrypoint akan menolak berjalan tanpa kedua mount agar berkas tidak tersimpan pada filesystem container sementara.

Setelah database dan akun awal siap, uji upload bukti dan buat backup, lakukan redeploy biasa, lalu pastikan keduanya masih dapat dibuka. Jangan menghapus storage sebagai bagian pengujian.

Referensi: [Persistent storage Coolify](https://coolify.io/docs/applications/configuration/persistent-storage).

## 3. Isi environment runtime

Gunakan [`.env.production.example`](.env.production.example) sebagai daftar nilai, lalu masukkan pada **Environment Variables** Coolify untuk runtime. File `.env` lokal, password, dan APP_KEY tidak disertakan dalam build image.

Isi setidaknya:

- `APP_ENV=production`, `APP_DEBUG=false`.
- `APP_URL=https://ncimproject.tech`, tanpa port internal atau path `/login`.
- `APP_KEY`: gunakan kunci aplikasi yang sudah ada. Jangan mengganti kunci pada redeploy. Untuk instalasi benar-benar baru, buat satu kali di komputer yang memiliki PHP dengan `php artisan key:generate --show`, lalu simpan hasilnya langsung di Coolify, bukan di Git atau chat.
- `DB_HOST`: hostname internal dari resource MySQL Coolify. **127.0.0.1 di container aplikasi menunjuk container aplikasi sendiri**, bukan resource MySQL.
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`: cocokkan dengan resource MySQL.
- `TRUSTED_PROXIES`: IP proxy Coolify pada jaringan yang terhubung ke aplikasi.
- `MAIL_ENABLED=false` dan `MAIL_MAILER=array`: mode tanpa email, tanpa kebutuhan SMTP. Hapus env SMTP lama seperti `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_URL`, dan `MAIL_FROM_ADDRESS` jika sudah tidak dipakai. `MAIL_ENABLED=false` tetap memaksa transport tanpa pengiriman meskipun `MAIL_MAILER` lama belum dihapus.
- Path persistent storage mengikuti blok env pada langkah 2.

Pada mode tanpa email, tautan **Lupa kata sandi?** disembunyikan dan endpoint reset/verifikasi email dinonaktifkan. Manajer tetap dapat mengubah kata sandi pengguna lewat **Akun Pengguna > edit akun > kata sandi baru dan konfirmasi**. Pengguna yang sudah masuk tetap bisa mengganti kata sandinya sendiri. Jika satu-satunya akun manajer terkunci, jangan membuat ulang database atau menjalankan seeder demo untuk memulihkannya.

Jika suatu saat email diperlukan, isi `MAIL_ENABLED=true`, `MAIL_MAILER=smtp`, lalu konfigurasi SMTP nyata dan redeploy. Selama email aktif, validasi produksi tetap menolak transport `log`/`array` agar reset password tidak berpura-pura terkirim atau membocorkan token ke log.

Untuk melihat IP proxy, jalankan di **terminal host VPS**:

```sh
docker inspect coolify-proxy --format '{{range $name, $network := .NetworkSettings.Networks}}{{printf "%s: %s\n" $name $network.IPAddress}}{{end}}'
```

Pilih IP pada jaringan yang sama dengan aplikasi, lalu isi `TRUSTED_PROXIES` dengan IP tersebut. Jika perlu beberapa IP, pisahkan dengan koma. Jangan isi `*` atau CIDR seluruh internet. Saat proxy dibuat ulang dan IP berubah, sesuaikan nilai ini lalu redeploy. Laravel hanya mempercayai header HTTPS dan IP pengunjung dari proxy yang terdaftar; ini diperlukan agar login dan cookie aman berjalan di belakang Coolify.

Pertahankan `SESSION_DRIVER=database`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`, `SESSION_PATH=/`, dan `SESSION_DOMAIN=null`. Cookie menjadi khusus domain aplikasi dan sesi tersimpan di MySQL saat container berganti.

Nilai rahasia cukup tersedia saat runtime. Build hanya membutuhkan source, lockfile, dan unduhan dependency publik. `VITE_APP_NAME` boleh diisi sebagai build argument karena nilainya memang terlihat pada browser; jangan menambahkan secret pada variabel `VITE_*`.

## 4. Deploy image

Klik **Deploy**. Dockerfile memasang dependency PHP tanpa paket development dan membangun frontend dengan `npm ci` lalu `npm run build`.

Health check image memakai **HTTP GET `http://127.0.0.1:8080/healthz`**. `curl` tersedia dalam image. Endpoint memeriksa Nginx dan PHP-FPM, sehingga tetap memberi 200 ketika aplikasi Laravel masih maintenance untuk inisialisasi database. Coolify mengutamakan HEALTHCHECK image pada deployment Dockerfile; jangan mengarahkannya ke `/login` atau `/up` untuk bootstrap ini.

Container yang healthy belum berarti database sudah diinisialisasi. Instalasi baru sengaja tetap maintenance sampai langkah berikut selesai.

## 5. Jalankan migration dan buat akun awal

Buka **Terminal aplikasi Coolify** setelah container berjalan:

```sh
cd /var/www/html
gosu www-data php artisan migrate --force
```

Seluruh tabel dibuat lewat migration di `database/migrations`. Periksa hasilnya dan lanjutkan hanya jika sukses. Migration juga memverifikasi kompatibilitas database lama tanpa membuat ulang tabelnya. Detail upgrade tersedia di [docs/mysql.md](docs/mysql.md).

Jika muncul error izin trigger / MySQL 1419, administrator MySQL perlu menjalankan pada **resource MySQL**, menggunakan akun administrator:

```sql
SET PERSIST log_bin_trust_function_creators=ON;
```

User aplikasi tetap hanya diberi hak pada database aplikasi, termasuk CREATE/ALTER/INDEX/TRIGGER; jangan menjadikannya akun root atau memberinya SUPER. Sesudah pengaturan administrator selesai, ulangi `migrate --force`.

Khusus instalasi baru tanpa akun, lanjutkan satu per satu:

```sh
gosu www-data php artisan db:seed --force
gosu www-data php artisan clbk:bootstrap-user
```

Masukkan nama, email, dan kata sandi melalui prompt. `RoleUserSeeder` adalah data contoh lokal/testing dan tidak dipakai di produksi. Jika akun sudah ada, jangan menjalankan bootstrap ulang; kelola akun lewat Akun Pengguna.

Periksa lalu buka aplikasi:

```sh
gosu www-data php artisan clbk:database-ready
gosu www-data php artisan clbk:doctor --json
```

Pastikan pemeriksaan database berhasil, akun awal ada, dan masalah yang dilaporkan doctor telah ditangani. Kemudian:

```sh
gosu www-data php artisan up
```

Entrypoint tidak menjalankan migration, seeder, `key:generate`, atau `up` otomatis. Restart container mempertahankan data dan keputusan maintenance operator.

## 6. Periksa aplikasi

- Buka `https://ncimproject.tech/login`, masuk dengan akun yang dibuat, berpindah halaman, lalu muat ulang untuk memastikan sesi tetap aktif.
- Periksa halaman laporan serta upload/download bukti dengan akun yang berhak.
- Buat dan verifikasi backup melalui Pengaturan Usaha. Scheduler sudah berjalan di container; jangan menambahkan scheduler kedua untuk resource yang sama. Heartbeat diperbarui setiap menit.
- Pada mode tanpa email, pastikan tautan Lupa kata sandi tidak tampil. Uji penggantian kata sandi akun uji melalui manajer, kemudian login dengan kata sandi baru.

Untuk health check internal, di terminal aplikasi:

```sh
curl -fsS http://127.0.0.1:8080/healthz
```

Hasilnya `pong`. Mengakses `/login` langsung melalui HTTP localhost dapat ditolak karena produksi mewajibkan HTTPS dan Host canonical. Uji login lewat domain HTTPS publik.

Jika muncul `no available server`, periksa status container, domain, port 8080, dan jaringan proxy. Jika domain menampilkan halaman maintenance Laravel, selesaikan migration/bootstrap dan `artisan up`; health check bukan pembuka maintenance.

## 7. Pembaruan berikutnya

Buat backup dengan source yang sedang berjalan sebelum mengganti versi. Aktifkan maintenance dengan `gosu www-data php artisan down`, deploy source baru, lalu jalankan `migrate --force` dan pemeriksaan pada langkah 5. Jalankan `up` hanya setelah semua langkah berhasil. Pertahankan APP_KEY, database, serta volume `/data` dan `/backups` yang sama.

Jangan menjalankan `migrate:fresh`, `db:wipe`, atau menghapus volume untuk memperbaiki deployment berisi data. Arsip backup menyimpan fingerprint migration; pertahankan source yang cocok untuk memulihkan arsip versi lama, dan buat backup baru setelah upgrade.

## Build manual

Dari root repository pada komputer/server dengan Docker Engine aktif:

```sh
docker build -t clbk:production .
```

Contoh menjalankan image di belakang proxy HTTPS yang sudah dikonfigurasi:

```sh
docker run -d --name clbk \
  --env-file /lokasi/privat/clbk-production.env \
  --mount type=volume,source=clbk-data,target=/data \
  --mount type=volume,source=clbk-backups,target=/backups \
  --network NAMA_JARINGAN_PROXY \
  clbk:production
```

Nama jaringan dan isi env harus sesuai server Anda. Pada Coolify, biarkan Coolify mengelola container, jaringan, domain, dan volume melalui konfigurasi resource; perintah manual ini tidak diperlukan.

Referensi: [Docker multi-stage Laravel](https://docs.docker.com/guides/laravel/), [Laravel deployment](https://laravel.com/docs/13.x/deployment), [Coolify health checks](https://coolify.io/docs/applications/configuration/health-checks).
