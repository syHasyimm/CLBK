# Deploy CLBK TOKE dengan Coolify untuk aplikasi web

Panduan ini untuk pemilik usaha yang belum terbiasa deploy. Disesuaikan **16 Oktober 2026** untuk **VPS Hostinger + Coolify + MySQL 8.4 dengan database awal kosong dan backup pada folder lokal tersendiri**, sesuai pilihan Anda. Dashboard Coolify, container aplikasi, dan resource MySQL terpisah berjalan pada satu VPS milik Anda. Bila Coolify sudah aktif, lanjut ke bagian 4 setelah memastikan server dan domainnya sesuai. Instruksi produksi SQLite pada catatan tahap sebelumnya sudah digantikan oleh panduan ini.

Hasil akhirnya: satu alamat seperti **https://toke.namausaha.id**, diakses melalui browser komputer maupun ponsel. Semua memakai backend dan data usaha yang sama. Anda tidak perlu memasang PHP/database pada komputer pengguna atau HP. **Domain, server, dan akun Coolify produksi belum diberikan; panduan dan konfigurasi ini belum merupakan deployment yang sudah berjalan.**

Urutan: siapkan VPS → pasang Coolify → hubungkan source → siapkan folder persisten → isi konfigurasi → deploy → buat/pulihkan data → aktifkan backup → uji browser komputer/ponsel.

Bagian yang sering dibuka kembali: [pengaturan Coolify](#7-buat-aplikasi-coolify-dan-isi-konfigurasi), [akses komputer dan HP](#12-buka-aplikasi-di-browser-komputer-dan-hp), [backup](#14-backup-harian-offsite-dan-backup-dashboard), [restore](#15-restore-data-dengan-aman), [update](#17-cara-update-aplikasi-berikutnya), dan [pemecahan masalah](#18-masalah-yang-sering-muncul).

## 1. Saran server dan biaya

Gunakan VPS Hostinger yang Anda pilih, dengan Linux **Ubuntu 24.04 LTS x64**, Coolify self-hosted, satu domain usaha, dan folder backup lokal tersendiri. Nginx + PHP-FPM 8.4 berjalan **di dalam container**, sehingga tidak perlu dipasang secara manual pada sistem operasi VPS. Coolify mengurus pintu masuk HTTPS dan sertifikatnya.

| Kebutuhan | Saran | Penjelasan sederhana |
|---|---|---|
| VPS utama | 4 vCPU, RAM 8 GiB, SSD 160 GiB | Ruang untuk Coolify, aplikasi, dan proses build |
| Alternatif lebih hemat | 2 vCPU, RAM 4 GiB, SSD 80 GiB | Perlu pantau RAM saat build; tambah kapasitas jika sering kehabisan memori |
| Folder backup lokal | /srv/clbk/backups | Folder tersendiri pada disk VPS; volume tambahan opsional |
| Domain aplikasi | toke.namausaha.id | Diisikan pada semua perangkat pengguna |
| Domain dashboard | panel.namausaha.id | Khusus pengelola Coolify |
| Email transaksi | SMTP, misalnya Brevo | Agar lupa password benar-benar mengirim email |
| Salinan di luar VPS | Backup terenkripsi ke komputer aman/object storage | Menjaga data jika seluruh VPS hilang |

Ukuran ini saran awal, bukan hasil uji kapasitas aplikasi produksi. Coolify sendiri mempunyai kebutuhan minimum; build dan aplikasi memerlukan sumber daya tambahan. [Persyaratan Coolify](https://coolify.io/docs/start-with-self-hosted).

Tidak perlu membeli volume tambahan untuk mode folder lokal ini. Pilih kapasitas sesuai kebutuhan dan pantau ruang untuk database, bukti, build, log, serta arsip. Hostinger menjelaskan penambahan resource dilakukan melalui upgrade paket VPS; upgrade kapasitas belum membuktikan adanya disk backup terpisah. Periksa biaya pada akun Anda sebelum membeli upgrade. [Upgrade VPS Hostinger](https://www.hostinger.com/support/1583229-how-to-upgrade-a-vps-server-at-hostinger/).

Coolify self-hosted menggunakan VPS Anda. Coolify Cloud mengelola dashboard untuk Anda, tetapi server aplikasi tetap diperlukan; biaya/penyiapannya berbeda. Panduan ini memakai jalur self-hosted agar langkahnya satu alur. Bantuan satu kali teknisi Linux berguna untuk pemasangan disk dan latihan pemulihan; akun domain/VPS dan kunci tetap Anda pegang.

## 2. Kenali istilah dan tempat mengetik perintah

```text
Browser komputer / browser ponsel
            |
          HTTPS
            |
      domain aplikasi
            |
   proxy HTTPS milik Coolify
            |
   container Nginx + PHP-FPM
       |                |
 data usaha       folder backup lokal
 persisten              |
                  salinan di luar VPS
```

- **VPS**: komputer sewaan yang menyala 24 jam.
- **Container**: paket aplikasi yang dapat diganti saat deploy. Data penting harus disimpan di luar paket itu.
- **Deploy**: membangun dan menjalankan versi aplikasi pada server.
- **WINDOWS**: PowerShell di komputer D:/Sites/CLBK.
- **SERVER**: terminal setelah SSH ke VPS; contoh pemasangan awal memakai root.
- **TERMINAL APLIKASI**: menu Terminal pada resource CLBK TOKE di Coolify. Ini berada di dalam container, bukan terminal VPS.

Jangan menempelkan perintah SERVER langsung ke PowerShell. Jangan menempelkan isi konfigurasi ke terminal sebagai perintah. Pada editor nano: Ctrl+O, Enter untuk menyimpan, Ctrl+X untuk keluar. Password Linux tidak terlihat ketika diketik.

Ganti semua contoh `IP_SERVER_ANDA`, `toke.example.com`, `panel.example.com`, `email-anda@example.com`, `NAMA_BACKUP.zip`, dan nilai `ISI_...` dengan milik Anda. Jika suatu langkah error, perbaiki sebelum melanjutkan.

Siapkan: akun VPS/domain/GitHub, email pengelola, domain aplikasi dan panel, SMTP, pilihan data awal kosong atau pindahan, serta orang yang menerima alarm. Aktifkan autentikasi dua faktor pada akun pengelola bila tersedia. Simpan secret di password manager.

**Koneksi ke server tetap diperlukan.** Browser tidak mempunyai mode pencatatan transaksi offline. Semua perangkat memakai aplikasi yang sama dan satu resource MySQL. Database hanya diakses backend melalui jaringan privat Coolify; port 3306 tidak perlu dibuka ke internet.

## 3. Pasang Coolify pada VPS baru

### 3.1 Buat SSH key dan VPS

**WINDOWS:**

```powershell
New-Item -ItemType Directory -Path "$env:USERPROFILE/.ssh" -Force | Out-Null
ssh-keygen -t ed25519 -f "$env:USERPROFILE/.ssh/clbk_deploy" -C "clbk-deploy"
Get-Content "$env:USERPROFILE/.ssh/clbk_deploy.pub"
```

Beri passphrase dan simpan aman. Bila nama key sudah ada, jangan menimpanya; gunakan key tersebut atau nama baru secara konsisten. Pada panel penyedia VPS, tambahkan isi file **.pub** sebagai SSH key. File tanpa .pub adalah private key; jangan dibagikan.

Buat VPS Ubuntu 24.04 LTS x64, lokasi Singapore, kapasitas pilihan bagian 1, autentikasi SSH key. Catat IP publiknya. Jika VPS sudah berisi aplikasi lain, jangan menjalankan pemasangan baru sembarangan; gunakan VPS baru atau minta pengelola memeriksa konflik port/data.

### 3.2 Atur firewall penyedia

| Port TCP masuk | Izinkan dari | Untuk |
|---|---|---|
| 22 | IP publik pengelola; sesuaikan kebutuhan koneksi Coolify | SSH |
| 80 dan 443 | Internet | Web, HTTPS, penerbitan sertifikat |
| 8000, 6001, 6002 | Tidak perlu dibuka ke internet pada alur tunnel di bawah | Dashboard/realtime/terminal awal lewat SSH |

Gunakan firewall jaringan di dashboard penyedia dan kaitkan pada VPS yang benar. Jangan membuka port database, 9000, atau 8080 ke internet. Uji SSH tetap tersambung sebelum menutup sesi lama. Jika ada aturan bawaan yang membuka 8000/6001/6002 ke publik, batasi/tutup; alur awal di bawah melewatkannya melalui SSH. [Firewall Coolify](https://coolify.io/docs/core/infrastructure/servers/firewall).

### 3.3 Instal dan buka dashboard

**WINDOWS:**

```powershell
ssh -i "$env:USERPROFILE/.ssh/clbk_deploy" root@IP_SERVER_ANDA
```

Cocokkan fingerprint SSH melalui console/dashboard penyedia saat koneksi pertama. Sekarang Anda berada pada SERVER.

**SERVER, VPS baru:**

```bash
apt-get update
apt-get upgrade -y
apt-get install -y curl ca-certificates nano openssh-server openssl
timedatectl
curl -fsSL https://cdn.coollabs.io/coolify/install.sh -o /root/install-coolify.sh
bash /root/install-coolify.sh
```

Periksa tanggal/jam server dan sinkronisasi NTP pada timedatectl; koreksi melalui pengelola VPS bila salah. Zona waktu host boleh UTC; backup bisnis pada proyek ini dijadwalkan menurut Asia/Jakarta. URL installer berasal dari dokumentasi resmi; Anda dapat memeriksa isinya sebelum menjalankan. Tunggu pesan selesai. Dashboard awal memakai HTTP. Untuk membuat akun awal melalui koneksi SSH terenkripsi, buka **PowerShell WINDOWS kedua**, lalu:

```powershell
ssh -i "$env:USERPROFILE/.ssh/clbk_deploy" -o ExitOnForwardFailure=yes -N -L 8000:127.0.0.1:8000 -L 6001:127.0.0.1:6001 -L 6002:127.0.0.1:6002 root@IP_SERVER_ANDA
```

Jendela tampak diam karena menjaga tunnel tetap terbuka; biarkan berjalan. Buka **http://localhost:8000** di browser komputer yang sama dan segera buat akun admin pertama. Ini dashboard VPS melalui tunnel, bukan aplikasi lokal. Jika port lokal sedang dipakai atau tunnel gagal, perbaiki error SSH sebelum login. Setelah domain dashboard HTTPS berfungsi pada bagian 4, tunnel dapat ditutup dengan Ctrl+C. [Pemasangan resmi Coolify](https://coolify.io/docs/start-with-self-hosted), [port forwarding SSH](https://man.openbsd.org/ssh#L).

Pada Coolify, buka **Servers → localhost → Validate/Validate connection**. Pastikan valid. Coolify membutuhkan akses SSH miliknya sendiri; **jangan menerapkan PermitRootLogin no** tanpa memindahkan dan menguji koneksi Coolify. Jika mengamankan root, gunakan SSH key dan `PermitRootLogin prohibit-password`, lalu validasi lagi sebelum menutup terminal. [SSH Coolify](https://coolify.io/docs/core/infrastructure/servers/openssh).

Jangan memasang Nginx/Apache/Certbot di host dengan mengikuti panduan server manual: port 80/443 dipakai proxy Coolify. PHP, Composer dan Node aplikasi disediakan oleh Dockerfile.

## 4. Hubungkan domain dan amankan dashboard

Pada DNS domain, buat record:

| Jenis | Nama | Nilai |
|---|---|---|
| A | toke | IP_SERVER_ANDA |
| A | panel | IP_SERVER_ANDA |

Contoh tersebut berarti toke.namausaha.id dan panel.namausaha.id. Bila memakai Cloudflare, gunakan **DNS only** dahulu. Hapus AAAA yang menunjuk IPv6 yang belum disiapkan; jangan mengubah record layanan lain.

**WINDOWS:**

```powershell
nslookup toke.example.com
nslookup panel.example.com
```

Hasil harus IP VPS. Tunggu pembaruan DNS bila belum sesuai. Pada pengaturan instance Coolify, isi domain dashboard **https://panel.example.com**, simpan, dan ikuti petunjuk pengaktifan proxy. Uji login, terminal, dan pembaruan dashboard melalui domain HTTPS, kemudian tutup akses publik port awal sesuai bagian 3. Jangan memakai domain dashboard sebagai domain aplikasi.

Untuk domain resource aplikasi, Coolify menerbitkan/memperbarui sertifikat saat menggunakan https:// dan DNS/port benar. Sertifikat HTTPS ini mengamankan koneksi browser pengguna. [Domain Coolify](https://coolify.io/docs/core/networking/domains).

## 5. Siapkan folder data dan backup persisten

### 5.1 Hostinger: buat dua folder lokal

Mode yang dipilih menggunakan dua folder tersendiri pada VPS yang sama. Folder boleh berada pada disk yang sama. Tidak perlu memformat disk atau mengubah partisi.

**SERVER:**

```bash
install -d -o 33 -g 33 -m 750 /srv/clbk/data
install -d -o 33 -g 33 -m 700 /srv/clbk/backups
ls -ld /srv/clbk/data /srv/clbk/backups
df -h /srv/clbk/data /srv/clbk/backups
```

UID/GID 33 adalah www-data di image PHP yang disediakan. Dua folder harus berbeda; backup tidak boleh berada di dalam folder bukti privat atau folder publik. Angka perangkat/disk yang sama diperbolehkan pada mode lokal.

| Di server/host | Di dalam container | Isi |
|---|---|---|
| /srv/clbk/data | /data | Proofs, storage/runtime/log aplikasi |
| /srv/clbk/backups | /backups | ZIP backup pada folder lokal tersendiri |

Entrypoint membuat folder file privat/runtime. Data MySQL berada di persistent storage resource MySQL pada `/var/lib/mysql`, bukan di `/data` aplikasi. **Jangan mount seluruh /var/www/html**: kode/vendor/aset berada di image. Jangan membuat storage:link untuk bukti; file bukti bersifat privat.

Jika sebelumnya sudah membuat folder `/mnt/clbk-backups/archives` dan memasangnya ke `/backups`, folder tersebut boleh tetap digunakan. Tidak perlu memindahkan atau menghapus arsip hanya untuk mengganti nama folder. Source Path pada Coolify harus mengarah ke folder yang benar-benar Anda gunakan.

### 5.2 Aktifkan mode folder lokal

Pada **resource aplikasi CLBK → Environment Variables → Production**, gunakan Runtime Variable:

```dotenv
BACKUP_PATH=/backups
BACKUP_REQUIRE_SEPARATE_DISK=false
```

Default Dockerfile dan template Coolify sudah memakai mode ini. Pengaturan `false` menerima backup pada disk yang sama, tetapi tetap mewajibkan dua mount persisten dan folder sumber yang berbeda. Aplikasi juga menolak folder backup yang sama, berada di dalam, atau menjadi induk folder bukti privat; pemeriksaan direktori publik, kapasitas, autentikasi, dan integritas arsip tetap berlaku. Mengisi env saja tidak memasang storage: tambahkan kedua mount sesuai bagian 7.2.

Backup lokal membantu pemulihan kesalahan aplikasi/data. Jika disk atau seluruh VPS rusak, data dan backup lokal dapat hilang bersama. Salinan ke luar VPS tetap disarankan pada bagian 14; mode ini tidak mengunggah backup ke luar VPS secara otomatis.

### 5.3 Opsional: backup pada disk terpisah

Jika nanti tersedia storage backup terpisah, pasang pada host sesuai petunjuk penyedia dan buat folder arsip berizin UID/GID 33, mode 700. Pastikan disk terpasang kembali setelah reboot. Jangan memformat disk yang sudah berisi data.

Periksa lokasi data dan backup melalui `findmnt -T` serta `lsblk -f`. Ganti Source Directory mount `/backups` ke folder pada storage tersebut, lalu ubah runtime env menjadi `BACKUP_REQUIRE_SEPARATE_DISK=true`. Pada mode ini perangkat backup dan `/data` harus berbeda; pastikan volume MySQL juga berada di disk data yang berbeda dari backup. Dua folder atau dua named volume Docker pada disk yang sama tidak memenuhi mode ini. Di luar image Coolify, konfigurasi aplikasi tetap memakai `true` bila env tidak diisi.

### 5.4 Hostinger: error perangkat backup sama

Pesan `Backup harus menggunakan perangkat disk berbeda dari penyimpanan privat aplikasi.` pada image lama berarti kedua mount sudah terpasang, tetapi pemeriksaan disk masih mewajibkan perangkat berbeda.

Untuk mode lokal yang sekarang dipilih:

1. Commit dan push perubahan Dockerfile, entrypoint, config backup, dan BackupService ke branch yang dipakai Coolify.
2. Isi `BACKUP_REQUIRE_SEPARATE_DISK=false` sebagai Production Runtime Variable, lalu simpan. Jangan memakai Build Variable untuk pengaturan ini.
3. Pastikan mount `/data` dan `/backups` memakai folder sumber yang berbeda dan tetap persisten, sesuai bagian 7.2.
4. Klik **Redeploy** dan pastikan Deployment Logs memakai commit baru. Image lama belum mengenali pengaturan mode lokal; restart image lama saja tidak memperbaikinya. Perubahan mount juga membutuhkan container baru. [Bind mount Coolify](https://coolify.io/docs/core/persistent-storage/storage-mounts/bind-mounts), [penerapan volume mount](https://coolify.io/docs/core/persistent-storage/storage-mounts/volume-mounts).
5. Setelah database/akun siap, buat dan verifikasi backup sesuai bagian 9. Backup lokal harus berhasil sebelum aplikasi dipakai untuk transaksi.

Sebagai lapisan tambahan, Hostinger menyediakan backup VPS yang disimpan terpisah dari server. Pengaturannya ada di **hPanel → VPS → Manage → Backups & Monitoring → Snapshots & Backups → Manage Schedule**; backup harian dapat memerlukan pembelian tambahan. Backup VPS ini tidak memasang `/backups`. Tetap uji pemulihan arsip aplikasi agar database dan bukti dapat direkonsiliasi. [Backup VPS Hostinger](https://www.hostinger.com/support/1583232-how-to-back-up-or-restore-a-vps-at-hostinger/).

## 6. Siapkan source di GitHub private

Coolify akan mengambil kode dari Git, termasuk perubahan yang sudah di-commit dan di-push. File lokal yang belum dikirim tidak ikut deploy. Source server diambil dari repository yang sudah ditinjau.

Konfigurasi deployment sudah disediakan pada proyek ini:

| Berkas | Fungsi |
|---|---|
| Dockerfile | PHP 8.4 + build aset Node 24 + Nginx/PHP-FPM |
| deploy/coolify/entrypoint.sh | Memeriksa mount/env dan menyiapkan runtime; tidak auto-migrate/seed |
| deploy/coolify/nginx.conf | Web root public/, port internal 8080, pembatasan file sensitif |
| deploy/coolify/php.ini dan php-fpm.conf | Batas upload/memori dan worker PHP |
| deploy/coolify/supervisord.conf | Menjalankan Nginx + PHP-FPM dalam container |
| deploy/coolify/monitor.sh | Memeriksa kesehatan dan mengirim ping alarm opsional |
| .dockerignore | Membatasi file yang masuk build; tidak membawa .env/database/key lokal |

Cara mudah memakai GitHub Desktop:

1. Login GitHub, lalu **File → Add local repository**, pilih D:/Sites/CLBK.
2. Periksa daftar perubahan dan sertakan seluruh source aplikasi yang memang akan dirilis, lockfile, deployment, serta DEPLOY.md. Jangan hanya mengirim folder deploy jika fitur aplikasi masih belum di-commit.
3. Pastikan tidak ada .env asli, database SQLite, file bukti usaha, ZIP backup, private key, auth.json, atau credentials.json dalam perubahan yang akan dipublish. .env.example/.env.production.example boleh sebagai template tanpa secret.
4. Commit dengan pesan yang jelas, lalu Publish/Push ke repository **private**. Jangan memilih public untuk source usaha ini.
5. Catat branch, misalnya main, dan commit rilis. Pastikan Dockerfile pada root repository terlihat di GitHub.

Anda dapat memeriksa daftar file, tanpa mencetak isi secret:

**WINDOWS:**

```powershell
Set-Location D:/Sites/CLBK
git status --short
git ls-files -- '.env' '.env.production' '*.sqlite*' '*credentials.json'
```

Perintah kedua seharusnya tidak menampilkan file sensitif yang dilacak. .gitignore tidak menghapus secret yang sudah pernah terlanjur di-commit; bila ada kasus itu, keluarkan dari repository dan ganti secret yang terekspos sebelum deploy. Jangan menggunakan git add . tanpa meninjau daftar file terlebih dahulu.

## 7. Buat aplikasi Coolify dan isi konfigurasi

### 7.0 Buat resource MySQL 8.4 terlebih dahulu

1. Buka **Project CLBK TOKE → Production → New Resource → Databases → MySQL**.
2. Pilih server dan **destination/network yang sama dengan aplikasi**.
3. Pada General, pilih image **`mysql:8.4`**, initial database **`clbk`**, dan normal username **`clbk`**. Simpan normal-user password serta root password di pengelola password, dengan label berbeda.
4. Biarkan **Make it publicly available nonaktif** dan jangan membuat port mapping 3306. Pastikan persistent storage MySQL tetap terpasang pada `/var/lib/mysql`; jangan menghapus volume itu saat redeploy.
5. Klik **Start**, tunggu resource running/healthy. Dari **Internal URL**, catat hostname, port, database, username dan password. `DB_HOST` memakai hostname internal tersebut, **bukan localhost, IP publik, atau domain web**.

Resource aplikasi tidak menjalankan MySQL di dalam image PHP. Keduanya berkomunikasi melalui network privat. [MySQL di Coolify](https://coolify.io/docs/databases/mysql).

Sebelum migrasi pertama, izinkan akun aplikasi memasang trigger penjaga transaksi saat binary logging MySQL aktif. Buka **Terminal resource MySQL**, jalankan:

```bash
mysql -u root -p
```

Masukkan **root password MySQL resource ini** pada prompt. Di prompt `mysql>`, jalankan:

```sql
SET PERSIST log_bin_trust_function_creators = ON;
SHOW VARIABLES LIKE 'log_bin_trust_function_creators';
EXIT;
```

Hasil harus `ON`. Pengaturan tersimpan pada volume MySQL dan tetap berlaku setelah restart. Akun aplikasi tetap memakai normal user `clbk`; root hanya untuk administrasi. Tanpa pengaturan ini, migrasi dapat ditolak dengan error 1419 saat membuat trigger. Jangan menghilangkan trigger untuk melewati error. [Aturan binary logging dan trigger MySQL 8.4](https://dev.mysql.com/doc/refman/8.4/en/stored-programs-logging.html).

### 7.1 Hubungkan repository

1. Pada Coolify buat **Project → CLBK TOKE**, pilih environment Production.
2. Tambahkan resource dari private Git repository. Hubungkan GitHub App hanya ke repository yang diperlukan, atau gunakan deploy key baca saja.
3. Pilih server **localhost** dan destination/network default **coolify** untuk alur satu VPS ini.
4. Pilih repository dan branch yang telah dikirim.
5. Pilih **Build Pack: Dockerfile**.

Isi pengaturan berikut. Nama menu dapat sedikit berbeda antarrilis Coolify.

| Pengaturan | Nilai |
|---|---|
| Base Directory | / |
| Dockerfile Location | /Dockerfile |
| Docker build context | Root repository; jangan deploy/coolify saja |
| Build Stage/Target | runtime, atau kosong untuk stage terakhir |
| Ports Exposes/internal port | 8080 |
| Ports Mappings/published host ports | Kosong |
| Domains | https://toke.example.com:8080, ganti hostname dengan domain aplikasi Anda |
| Replicas | 1 |
| Auto Deploy dari push | Matikan dahulu; update database dilakukan terencana |
| Preview deployment | Matikan dahulu; jangan membagikan mount data produksi ke preview |

Pada kolom **Domains**, gunakan **https://toke.example.com:8080** setelah mengganti hostname dengan domain aplikasi Anda. Akhiran `:8080` menentukan port tujuan proxy di dalam container. Akses pengguna tetap **https://toke.example.com** melalui 443; **APP_URL dan alamat browser tidak memakai :8080**. Jika domain tidak menyertakan port, Coolify memakai port pertama dari Ports Exposes; isi **8080** saja pada field tersebut. Nginx menerima HTTP pada port internal 8080, sedangkan HTTPS pengguna ditangani proxy Coolify. Pastikan HTTPS dan pengalihan HTTP → HTTPS diaktifkan. Dockerfile menjalankan server pada 0.0.0.0:8080. [Pengaturan domain dan port Coolify](https://coolify.io/docs/applications/configuration/general), [build Dockerfile di Coolify](https://coolify.io/docs/applications/builds/dockerfile).

### 7.2 Tambahkan dua persistent directory mount

Pada resource aplikasi → **Persistent Storage/Storages**, buat Directory Mount, atau Volume Mount dengan Source Path eksplisit jika itu nama pilihan pada versi Anda:

| Mount | Source Path pada server | Destination Path container |
|---|---|---|
| Data | /srv/clbk/data | /data |
| Backup | /srv/clbk/backups | /backups |

Simpan dan periksa kembali kedua path. Container menolak start bila mount belum terpasang atau kedua mount memakai folder sumber yang sama. Pada mode lokal (`BACKUP_REQUIRE_SEPARATE_DISK=false`), disk yang sama diperbolehkan. Jika memilih `true`, disk data/backup harus berbeda. Redeploy harus memakai source path yang sama. Mount backup lama dengan source `/mnt/clbk-backups/archives` boleh dipertahankan jika itulah folder arsip Anda. Jangan menghapus Directory Mount lewat dialog yang ikut menghapus isi folder host. [Persistent storage Coolify](https://coolify.io/docs/applications/configuration/persistent-storage), [bind mount](https://coolify.io/docs/core/persistent-storage/storage-mounts/bind-mounts).

### 7.3 Siapkan SMTP

Buat layanan email transaksi, verifikasi domain/pengirim, dan pasang DNS SPF/DKIM yang diminta penyedia. Pada DigitalOcean, SMTP keluar port **25, 465, dan 587 diblokir**; contoh ini memakai **Brevo port 2525 dengan STARTTLS**. Gunakan SMTP login dan SMTP key dari dashboard Brevo, bukan API key. [Pembatasan SMTP DigitalOcean](https://docs.digitalocean.com/support/why-is-smtp-blocked/), [Brevo SMTP](https://help.brevo.com/hc/en-us/articles/7924908994450-Send-transactional-emails-using-Brevo-SMTP), [port Brevo](https://help.brevo.com/hc/en-us/articles/10905415650322-Which-SMTP-port-should-I-use-Port-587-465-or-2525).

Jika memakai penyedia lain, ikuti host/port yang diizinkan. MAIL_SCHEME=smtp untuk STARTTLS; smtps untuk TLS implisit seperti 465. Jangan memakai MAIL_SCHEME=tls atau mengganti mailer produksi menjadi log/array.

### 7.4 Tentukan APP_KEY

**Pemasangan baru tanpa data lama:** buat key sekali pada SERVER, salin hasilnya ke vault dan Environment Variables aplikasi:

Jika `.env.production` lokal sudah berisi APP_KEY yang dibuat saat persiapan instalasi baru ini, gunakan key tersebut dan simpan di vault; lewati pembuatan key di bawah. Jangan mengganti key setelah aplikasi mulai menyimpan data.

```bash
openssl rand -base64 32
```

Tambahkan awalan **base64:** pada hasilnya, misalnya base64:HASIL_RANDOM_ANDA. Jangan menaruh key di Dockerfile, Git, atau Build Arguments.

**Memindahkan database/backup lama:** gunakan **APP_KEY aplikasi sumber yang sama**, tanpa membuat pengganti. Key diperlukan untuk verifikasi backup dan data terenkripsi. APP_KEY aplikasi CLBK berbeda dari APP_KEY instance Coolify; simpan keduanya dengan label yang jelas.

### 7.5 Isi Environment Variables aplikasi

Template khusus container tersedia di [deploy/coolify/.env.example](deploy/coolify/.env.example). Untuk menyiapkan isian dari komputer Windows, salin template menjadi `.env.production` bila file tersebut belum ada:

```powershell
if (-not (Test-Path -LiteralPath '.env.production')) {
    Copy-Item -LiteralPath 'deploy/coolify/.env.example' -Destination '.env.production'
}
```

File `.env.production` diabaikan Git dan tidak masuk image Docker. File ini adalah lembar persiapan; Coolify **tidak otomatis membacanya dari komputer Anda**. Lengkapi `APP_URL`, `APP_KEY`, `TRUSTED_PROXIES`, `DB_HOST`, `DB_PASSWORD`, serta SMTP, lalu masukkan nilainya ke **Production Environment Variables** resource aplikasi. Jika `.env.production` sudah disiapkan pada komputer ini, gunakan key yang tersimpan di sana; jangan membuat key baru setiap deploy. `.env` lokal tetap untuk development. Untuk Dockerfile Coolify gunakan template dalam `deploy/coolify/` agar path container sesuai.

Pada Coolify, aktifkan **Runtime Variable** dan nonaktifkan **Build Variable** untuk isian ini. Untuk password yang mengandung `$`, aktifkan **Literal** agar nilainya tidak dianggap referensi variabel. Setelah mengimpor lewat editor bulk, periksa nilai melalui editor per variabel. Hapus `DB_URL`, `MAIL_URL`, dan `SESSION_DOMAIN` lama jika sebelumnya ada: URL koneksi dapat menimpa konfigurasi database/SMTP di bawah, sedangkan cookie sebaiknya hanya berlaku pada host aplikasi. Simpan lalu **Redeploy** agar container dan cache konfigurasi memakai nilai baru. [Environment variables Coolify](https://coolify.io/docs/applications/configuration/environment-variables), [cache konfigurasi Laravel 13](https://laravel.com/docs/13.x/configuration#configuration-caching).

**Isi APP_URL secara eksplisit:** gunakan satu URL publik HTTPS aplikasi, misalnya `https://toke.namausaha.id` setelah menggantinya dengan domain sebenarnya. Pada editor per variabel, Key adalah `APP_URL` dan Value hanya URL tersebut; jangan menempelkan `APP_URL=` ke kolom Value. Jangan memakai `http://`, domain tanpa `https://`, URL dashboard Coolify, `/login`, query, fragment, atau daftar beberapa domain. Jika Domains Coolify memakai `https://toke.namausaha.id:8080` untuk routing internal, APP_URL tetap `https://toke.namausaha.id`. APP_URL harus menjadi Production Runtime Variable pada resource aplikasi CLBK. Kolom Domains dan APP_URL memiliki fungsi berbeda: domain mengatur proxy, sedangkan APP_URL mengatur alamat yang dipakai Laravel untuk membuat tautan. Setelah memperbaiki nilai, simpan dan Redeploy; container yang gagal boot tidak perlu dipaksa menjalankan Artisan terlebih dahulu. [Domain dan port internal Coolify](https://coolify.io/docs/applications/configuration/general#domains).

Gunakan Environment Variables **resource CLBK TOKE**, bukan environment instance Coolify. Semua nilai berikut adalah **runtime**; jangan aktifkan Available at Buildtime untuk APP_KEY, kredensial SMTP, atau ping URL. Image dibangun tanpa secret produksi. Jika memakai editor bulk, periksa kembali nilai setelah disimpan, terutama tanda kutip dan password yang mengandung karakter khusus.

```dotenv
APP_NAME="CLBK TOKE"
APP_ENV=production
APP_KEY=base64:ISI_KEY_ANDA
APP_DEBUG=false
APP_URL=https://toke.example.com
APP_LOCALE=id
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=id_ID
APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=14
DB_CONNECTION=mysql
DB_HOST=ISI_HOSTNAME_INTERNAL_MYSQL
DB_PORT=3306
DB_DATABASE=clbk
DB_USERNAME=clbk
DB_PASSWORD=ISI_PASSWORD_NORMAL_USER_MYSQL
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_0900_bin
CLBK_PRIVATE_STORAGE=/data/proofs
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
TRUSTED_PROXIES=ISI_CIDR_NETWORK_COOLIFY
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
CACHE_STORE=database
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=2525
MAIL_USERNAME=ISI_SMTP_LOGIN
MAIL_PASSWORD=ISI_SMTP_KEY
MAIL_FROM_ADDRESS=ISI_EMAIL_PENGIRIM_TERVERIFIKASI
MAIL_FROM_NAME="CLBK TOKE"
BACKUP_PATH=/backups
BACKUP_REQUIRE_SEPARATE_DISK=false
BACKUP_KEEP_DAILY=7
BACKUP_KEEP_WEEKLY=4
BACKUP_TIME=23:00
BACKUP_MIN_FREE_BYTES=1073741824
PROOFS_MAX_PER_DOCUMENT=5
PROOFS_MAX_BYTES_PER_USER=262144000
PROOFS_MAX_BYTES_TOTAL=2147483648
```

Biarkan **SESSION_DOMAIN tidak didefinisikan** untuk cookie host-only; hapus nilai lama jika sebelumnya diisi. Path di atas adalah path **container**, bukan path D:/... atau /srv/... host. Entry point memeriksa path itu agar data tidak tersimpan pada lapisan container yang mudah hilang.

Untuk mengganti ISI_CIDR_NETWORK_COOLIFY, lihat subnet destination yang benar pada SERVER:

```bash
docker network inspect coolify --format '{{range .IPAM.Config}}{{println .Subnet}}{{end}}'
```

Contoh hasil mungkin 10.0.1.0/24; **gunakan hasil nyata**, bukan menyalin contoh. Bila lebih dari satu subnet, pisahkan dengan koma. Ini hanya berlaku untuk destination default coolify pada panduan ini. Percayai hanya subnet yang dikelola sendiri dan dipakai proxy; jangan isi *, 0.0.0.0/0, atau seluruh rentang privat tanpa pemeriksaan. Jangan menaruh container pihak lain yang tidak dipercaya dalam network yang sama. Tujuannya agar IP asli pengguna dan rate limiting terbaca benar. [Jaringan Coolify](https://coolify.io/docs/core/networking-in-coolify).

### 7.6 Atur health check dan deployment

Gunakan health check HTTP **GET /healthz**, port **8080**, status **200**, interval 30 detik, timeout 5 detik, startup/start period 120 detik. Dockerfile juga menyediakannya. Endpoint ini memeriksa Nginx → PHP-FPM dan tetap bisa sukses selama maintenance awal; **bukan bukti database/backup sehat**. /up dan doctor diperiksa setelah inisialisasi. [Health check Coolify](https://coolify.io/docs/applications/configuration/health-checks).

Kosongkan **Pre-deployment Command** dan **Post-deployment Command** migrasi/seed pada pemasangan awal. Inisialisasi dilakukan melalui terminal setelah container siap, sesuai bagian 8. Simpan konfigurasi, lalu klik **Deploy**. Buka **Deployment Logs** sampai build selesai dan container running/healthy. Build pertama dapat lama karena mengunduh dependency dan membangun aset.

Image memakai lockfile Composer/npm, tidak memakai vendor/node_modules dari Windows. PHP image menyediakan `pdo_mysql` dan klien MySQL untuk migrasi schema awal. SQLite di image hanya dipakai sebagai format snapshot backup portabel; database operasional tetap MySQL. Tidak perlu menjalankan npm run dev, artisan serve, atau Composer setup pada produksi.

## 8. Inisialisasi data: pilih A atau B

Saat database kosong atau schema/trigger belum lengkap, container sengaja menampilkan **maintenance/503** pada web dan tetap menjalankan /healthz. Entrypoint memakai `clbk:database-ready --serve-maintenance`, sehingga Nginx/FPM dan terminal tersedia untuk pemulihan. Pemeriksaan `clbk:database-ready` tanpa opsi tersebut tetap gagal bila schema belum lengkap; jangan membuka maintenance sebelum pemeriksaan normal lulus. Kegagalan koneksi/versi MySQL tetap menghentikan startup. Buka **Terminal resource aplikasi CLBK TOKE**, bukan terminal container Coolify/proxy. Terminal awal memakai root; perintah aplikasi dijalankan melalui gosu www-data agar izin file benar.

**Untuk pemasangan baru yang Anda pilih, gunakan jalur A.** Jalur B khusus cadangan produksi MySQL dari aplikasi versi yang sama; jalankan migrasi untuk membuat schema, tetapi jangan seed/bootstrap sebelum restore.

### A. Usaha mulai dari database kosong

**TERMINAL APLIKASI:**

```bash
cd /var/www/html
gosu www-data php artisan clbk:mysql-initialize &&
gosu www-data php artisan db:seed --force &&
gosu www-data php artisan clbk:bootstrap-user &&
gosu www-data php artisan clbk:database-ready
```

Isi nama/email/password manajer. Password minimal 12 karakter dengan huruf dan angka. Tidak ada akun admin/password produksi bawaan. Bootstrap hanya bekerja saat tabel users kosong. Seed menyiapkan Kas Utama bersaldo nol, pengaturan, dan kategori biaya; akun tambahan dibuat manajer melalui aplikasi.

Tanda `&&` memastikan perintah berikutnya hanya berjalan jika perintah sebelumnya berhasil. Jika ada error, berhenti dan perbaiki dahulu. Jangan melanjutkan seed/bootstrap atau membuka maintenance setelah inisialisasi gagal.

`clbk:mysql-initialize` memeriksa versi MySQL, mengaktifkan maintenance, dan memeriksa isi tabel sebelum memanggil migrasi. Import awal yang terhenti boleh diulang hanya ketika riwayat migrasi dan seluruh tabel masih kosong, selain baris mutex bawaan. Jika menemukan data, atau riwayat migrasi terisi tetapi trigger belum lengkap, perintah menolak tanpa mengulang import. Schema yang sudah lengkap tidak diimpor ulang. Izin trigger diperiksa sebelum schema dimuat agar error 1419 tidak meninggalkan tabel baru setengah siap. Perintah ini tidak membuat akun, seed, atau membuka maintenance secara otomatis.

Pada MySQL kosong, Laravel otomatis memuat `database/schema/mysql-schema.sql`, termasuk foreign key, CHECK, index unik, dan trigger aturan bisnis. Daftar migrasi historis ikut tercatat sehingga SQL migrasi SQLite lama tidak dijalankan pada MySQL. Jangan menjalankan `migrate:rollback`/`migrate:refresh` terhadap migrasi historis ini; gunakan backup yang cocok untuk pemulihan. Migrasi baru setelah baseline harus mendukung MySQL. [Schema dump Laravel 13](https://laravel.com/docs/13.x/migrations#squashing-migrations).

Jangan menjalankan **RoleUserSeeder, migrate:fresh, migrate:refresh, db:wipe** pada produksi. Jangan membawa akun contoh berpassword yang diketahui ke internet. Lanjut bagian 9.

### B. Pindahkan data usaha yang sudah ada

Bagian ini hanya menerima backup dengan `database_driver=mysql`. Backup SQLite lama tidak dapat langsung dipulihkan ke MySQL; konversi data lintas engine memerlukan pekerjaan terpisah. Ini tidak diperlukan untuk instalasi database kosong yang Anda pilih.

Pastikan sumber berisi data usaha nyata yang memang akan diteruskan. Bila sumber hanya latihan, pilih jalur A. Siapkan akun manajer produksi di sumber dan ganti/nonaktifkan akun demo sebelum pemindahan. Bekukan input semua pengguna sampai pemindahan selesai; jangan membiarkan laptop dan VPS masing-masing menerima transaksi.

**Penting untuk Windows → Git → Linux:** restore memeriksa hash isi file migrasi, termasuk line ending. Git dapat menormalkan CRLF menjadi LF. Sebelum membuat backup pindahan, samakan berkas database/migrations pada sumber dengan commit yang akan dideploy, lalu verifikasi. Jika pernah mengubah skema sendiri, minta pengembang meninjau; jangan mengubah migrasi/skema untuk memaksa lolos. Prosedur memastikan hash ada di bagian 16.

Gunakan source/commit dan APP_KEY yang sama dengan backup. Pada sumber Windows:

```powershell
Set-Location D:/Sites/CLBK
php artisan down
php artisan clbk:backup --reason=go-live
```

Setelah down, pastikan request/worker sumber yang sudah berjalan benar-benar selesai sebelum membuat ZIP final. Bila masih ada penulis lain, hentikan lebih dahulu lalu buat backup lagi. Jangan menerima transaksi baru pada sumber selama perpindahan.

Cari ZIP pada BACKUP_PATH .env sumber; default lokal bila tidak ditentukan adalah storage/app/backups. Semua bukti sumber harus tersedia. Pada konfigurasi production, mode disk terpisah diperiksa bila `BACKUP_REQUIRE_SEPARATE_DISK=true`; mode `false` menerima folder lokal tersendiri. Jika perlu mengganti BACKUP_PATH lokal, isi path absolut privat dan config:clear sebelum membuat backup.

Verifikasi dan kirim ZIP, ganti path/nama sebenarnya:

```powershell
php artisan clbk:restore 'D:/LOKASI-BACKUP/NAMA_BACKUP.zip' --verify-only
scp -i "$env:USERPROFILE/.ssh/clbk_deploy" 'D:/LOKASI-BACKUP/NAMA_BACKUP.zip' root@IP_SERVER_ANDA:/root/
```

**SERVER:**

```bash
install -o 33 -g 33 -m 600 /root/NAMA_BACKUP.zip /mnt/clbk-backups/archives/NAMA_BACKUP.zip
```

Pastikan APP_KEY aplikasi Coolify adalah key sumber. **TERMINAL APLIKASI**, jalankan migrate untuk membuat schema, tanpa seed/bootstrap:

```bash
cd /var/www/html
gosu www-data php artisan clbk:mysql-initialize
gosu www-data php artisan down
gosu www-data php artisan clbk:restore /backups/NAMA_BACKUP.zip --verify-only
supervisorctl -c /etc/supervisor/clbk.conf stop nginx php-fpm
gosu www-data php artisan clbk:restore /backups/NAMA_BACKUP.zip --new-server --force
supervisorctl -c /etc/supervisor/clbk.conf start php-fpm nginx
```

--new-server mensyaratkan schema MySQL sudah dimigrasi tetapi belum memiliki data usaha/pengguna. Jika ditolak karena sudah terisi, **jangan menghapus database**; periksa isinya. Maintenance tetap aktif sesudah restore; akun lama dipulihkan dan sesi/token lama dicabut. Tidak perlu bootstrap-user lagi.

Scheduler belum dibuat pada instalasi baru. Jika memulihkan resource yang sudah pernah aktif, nonaktifkan semua scheduled task dan tunggu eksekusi berjalan selesai sebelum langkah stop service. Jangan klik redeploy ketika pemulihan berjalan. Sumber lama tetap maintenance sampai VPS diterima, kemudian dihentikan sebagai server penulis.

## 9. Buka aplikasi dan uji HTTPS/email

**TERMINAL APLIKASI:**

```bash
cd /var/www/html
gosu www-data php artisan clbk:backup --reason=initial-deploy
```

Catat ID arsip hasilnya. Ganti nama pada perintah berikut, lalu:

```bash
gosu www-data php artisan clbk:restore /backups/NAMA_BACKUP.zip --verify-only
gosu www-data php artisan clbk:database-ready
gosu www-data php artisan up
```

**WINDOWS:**

```powershell
curl.exe --fail https://toke.example.com/up
curl.exe -I http://toke.example.com
```

Harapkan /up sukses dan HTTP mengarah ke HTTPS. Buka browser, pastikan sertifikat dipercaya tanpa peringatan. Jangan memakai SSL self-signed/pengecualian sertifikat untuk HP.

Uji email dari **TERMINAL APLIKASI**, ganti alamat penerima Anda:

```bash
gosu www-data php artisan tinker --execute='Illuminate\Support\Facades\Mail::raw("Email uji CLBK TOKE", function ($message) { $message->to("email-anda@example.com")->subject("Uji email CLBK TOKE"); });'
```

Periksa inbox/spam. Lalu coba **Lupa password** untuk akun Anda. Link harus beralamat HTTPS domain aplikasi yang benar dan benar-benar bisa mengganti password. Kegagalan SMTP harus diperbaiki; keberhasilan build saja tidak membuktikan email terkirim.

## 10. Aktifkan scheduler backup dan alarm

### 10.1 Satu scheduler setiap menit

Pada resource CLBK TOKE → **Scheduled Tasks**, tambah:

| Kolom | Nilai |
|---|---|
| Name | CLBK scheduler |
| Command | gosu www-data php /var/www/html/artisan schedule:run --no-interaction |
| Frequency | * * * * * |
| Container | Kosong untuk aplikasi satu container; ikuti container aktif |
| Timeout | Awal 10800 detik untuk mengizinkan backup besar; ukur durasi nyata |

Untuk aplikasi satu container, biarkan kolom Container kosong agar task mengikuti container aktif saat redeploy, atau gunakan pilihan otomatis yang disediakan versi Coolify Anda; jangan mengunci nama container rilis lama. Aktifkan, jalankan **Execute now**, lalu periksa **Recent executions**. Coolify masuk container secara otomatis; jangan menambahkan docker exec pada Command. **Jangan sekaligus memasang cron/systemd scheduler dari folder deploy/ lama**. Proyek menjalankan heartbeat setiap menit dan backup pukul **23.00 Asia/Jakarta/WIB**. [Scheduled tasks Coolify](https://coolify.io/docs/core/automation/scheduled-tasks/create-a-task).

Tunggu 1–2 menit, lalu **TERMINAL APLIKASI**:

```bash
gosu www-data php artisan clbk:doctor --json
```

Harapkan exit code 0/semua pemeriksaan healthy. Doctor memeriksa versi MySQL, InnoDB/FK, keberadaan trigger dan mutex transaksi, ruang disk file privat, isi/umur backup maksimum 24 jam, dan heartbeat maksimum 5 menit. Ruang disk server MySQL perlu dipantau juga melalui resource MySQL/VPS. Backup pertama dari bagian 9 diperlukan agar pemeriksaan backup bisa lulus. Doctor bukan pengirim email otomatis dan tidak memeriksa bahwa offsite sudah terkirim.

### 10.2 Alarm jika scheduler/server bermasalah

Gunakan layanan pemantauan seperti Healthchecks.io. Buat check interval 5 menit, grace awal 10 menit, aktifkan notifikasi email, dan uji tombol test. Salin **ping URL privat**, misalnya https://hc-ping.com/UUID-MILIK-ANDA. URL ini secret; jangan ditaruh di Git atau dibagikan. [Pemantauan cron](https://healthchecks.io/docs/monitoring_cron_jobs/).

Tambah runtime env aplikasi **CLBK_MONITOR_PING_URL** dengan URL check itu, simpan lalu redeploy terencana. Setelah container baru sehat, tambah Scheduled Task kedua:

| Kolom | Nilai |
|---|---|
| Name | CLBK doctor alarm |
| Command | gosu www-data flock -n /data/storage/framework/doctor.lock /usr/local/bin/clbk-monitor |
| Frequency | */5 * * * * |
| Timeout | Awal 600 detik; sesuaikan ukuran backup |

Script menyimpan hasil di /data/storage/logs/doctor-latest.json dan hanya mengirim sinyal sukses/gagal, bukan data transaksi/log ke layanan monitor. Jika VPS mati total, ping berhenti dan layanan mengirim alarm. Pastikan check menerima ping dan email peringatan benar-benar sampai. Script disediakan pada deploy/coolify/monitor.sh.

Dokter membaca/mengekstrak backup; pada data besar atur interval, timeout, dan grace berdasarkan pengukuran. Tambahkan pemantauan HTTPS dari luar dan alarm disk VPS/volume backup, misalnya saat 80% terpakai. Saat maintenance terencana, jedakan alarm lalu aktifkan kembali.

## 11. Siapkan akun dan uji alur usaha

Login sebagai manajer melalui browser. Isi profil usaha, akun pengguna beserta role, data konsumen/pabrik/karyawan/mobil, penugasan mobil supir, harga/tanggal berlaku, serta modal/saldo awal sesuai kondisi usaha sebenarnya.

Uji dengan akun manajer, kasir dan supir. Periksa menu, batas data masing-masing, URL langsung ke dokumen orang lain, unggah/unduh bukti, PDF/CSV, logout, dan penolakan akun nonaktif. Untuk data pindahan, cocokkan jumlah dokumen, saldo kas, utang konsumen/upah, piutang, stok/harga snapshot dan bukti dengan sumber.

Latihan transaksi lengkap dilakukan pada **instalasi uji terpisah** dengan database/storage/backup/cookie/APP_KEY sendiri. Jangan membuat transaksi palsu di produksi lalu menghapus database untuk membersihkannya. Histori dokumen sah tidak dirancang untuk dibersihkan sembarangan. Jangan menjalankan preview otomatis dengan mount produksi.

## 12. Buka aplikasi di browser komputer dan HP

1. Pastikan server sudah lulus pengujian HTTPS, email, backup, dan akun pada bagian sebelumnya.
2. Pada komputer, buka browser yang diperbarui dan ketik **https://toke.example.com** sesuai domain usaha Anda.
3. Login dengan akun yang diberikan manajer. Simpan alamat sebagai bookmark agar mudah dibuka kembali.
4. Pada HP, buka domain HTTPS yang sama melalui browser, lalu login. Tampilan menyesuaikan ukuran layar dan hak akses akun.
5. Uji input, navigasi, unggah JPEG/PNG/PDF melalui pemilih berkas, unduh CSV/bukti, dan logout pada kedua perangkat.
6. Untuk cetak/simpan PDF, buka halaman cetak laporan atau voucher, tekan tombol cetak, lalu pilih printer/PDF melalui dialog browser. Periksa ukuran A4 dan orientasi yang sesuai.
7. Uji putus/sambung jaringan. Sebelum mengulang pengiriman form, periksa riwayat untuk memastikan transaksi pertama sudah tersimpan atau belum.

Semua perangkat membaca data dari satu server. Gunakan akun masing-masing; logout sesudah memakai komputer bersama. Jangan membagikan .env, database, APP_KEY, atau akun manajer kepada pengguna biasa.

## 13. Checklist sebelum transaksi harian

- [ ] DNS dan HTTPS valid; HTTP dialihkan ke HTTPS.
- [ ] Dashboard Coolify diamankan, akun pengelola dan akses SSH tersimpan.
- [ ] Akun produksi tersedia; akun demo tidak menjadi akses publik.
- [ ] Saldo awal, data master, penugasan supir/mobil dan harga benar.
- [ ] Browser komputer dan ponsel login/logout dengan role yang tepat.
- [ ] Batas data pengguna dan penolakan akses langsung sudah diuji.
- [ ] Form, unggah/unduh bukti, laporan, CSV dan cetak/PDF berhasil pada perangkat sebenarnya.
- [ ] Putus/sambung internet diuji; pengguna memeriksa riwayat sebelum mengirim ulang transaksi.
- [ ] Dua mount persisten benar dan perangkat backup berbeda dari data.
- [ ] Scheduler satu kali per menit, backup ZIP terverifikasi, doctor sehat.
- [ ] Salinan backup di luar VPS tersedia, APP_KEY tersimpan terpisah.
- [ ] Alarm benar-benar sampai pada pengelola, bukan hanya tersimpan di log.
- [ ] Restore diuji pada lingkungan terpisah dan data/bukti cocok.
- [ ] Ada orang yang menangani alarm, pembaruan, dan penagihan domain/VPS.

Pengujian otomatis aplikasi tidak menggantikan uji server dan browser perangkat nyata. Docker image Coolify di panduan ini belum dibangun/dijalankan pada VPS Anda. Jika volume transaksi besar, uji kinerja pada mesin target dengan data representatif, termasuk skenario 10.000 tiket, sebelum menyatakan kapasitasnya cukup.

## 14. Backup harian, offsite, dan backup dashboard

### 14.1 Apa yang harus disimpan

Backup aplikasi bukan hanya database. ZIP clbk mencakup snapshot MySQL dari satu read transaction yang konsisten, seluruh bukti yang dirujuk, serta manifest/hash bertanda HMAC. Di dalam arsip, data snapshot memakai wadah portabel `database.sqlite`; ini bukan database operasional dan tidak mengganti MySQL. Tidak perlu menyalin `/var/lib/mysql` secara manual. Default menyimpan tujuh backup harian dan satu per minggu untuk empat minggu. Backup manual/pre-upgrade/pre-restore **tidak dihapus otomatis**; pantau kapasitas.

Simpan di luar VPS:

1. ZIP backup aplikasi yang sudah diverifikasi, dalam penyimpanan terenkripsi dan akses terbatas.
2. **APP_KEY aplikasi CLBK**, catatan runtime environment, dan konfigurasi mount, di vault aman terpisah.
3. Source **commit rilis yang cocok dengan backup**, lockfile, dan catatan versi/migrasi. Tanpa kode yang cocok, restore dapat ditolak.
4. Backup instance Coolify dan secret instance-nya, terpisah dari backup aplikasi.

ZIP aplikasi mengandung data pribadi/hash password dan **tidak terenkripsi hanya karena ditandatangani**. HMAC memeriksa keaslian, bukan menyembunyikan isi. Jangan mengirim ZIP mentah ke grup chat atau bucket publik.

### 14.2 Cara sederhana untuk pemilik usaha

Setiap hari, login sebagai manajer → **Pengaturan Usaha → Cadangan/Backup**. Periksa backup terbaru dan waktunya; download setelah autentikasi ulang. Simpan di folder komputer yang terenkripsi, lalu salin ke media/penyimpanan lain yang aman. Jangan mengandalkan satu komputer dan VPS pada lokasi/akun yang sama. Otomatisasi disarankan agar tidak bergantung pada ingatan operator.

Setiap minggu cek alarm, ruang disk dan bahwa backup bisa diambil kembali. Setiap bulan coba pemulihan di lingkungan uji. Jika backup lebih dari 24 jam atau alarm gagal, tangani hari itu juga.

**TERMINAL APLIKASI**, pemeriksaan manual:

```bash
gosu www-data php artisan clbk:doctor --json
gosu www-data php artisan clbk:backup --reason=manual
```

### 14.3 Offsite otomatis terenkripsi, pilihan untuk dibantu teknisi

Contoh memakai Rclone Crypt → bucket Spaces private di region berbeda. Spaces menyimpan salinan ZIP backup, bukan volume MySQL aktif. `rclone copy` menyalin tanpa menghapus file lama di tujuan, berbeda dari sync. [Rclone copy](https://rclone.org/commands/rclone_copy/), [Crypt](https://rclone.org/crypt/).

Di dashboard penyedia, buat bucket **private**, misalnya pada FRA1 bila VPS berada di SGP1. Buat access key terbatas ke bucket tersebut; jangan gunakan token pengelola seluruh akun. Catat endpoint yang benar. [Akses Spaces](https://docs.digitalocean.com/products/spaces/how-to/manage-access/).

**SERVER**, bukan terminal aplikasi:

```bash
apt-get update
apt-get install -y rclone cron
rclone config
```

Pada konfigurasi interaktif root:

1. Buat remote **spaces**, tipe **S3**, provider **DigitalOcean**.
2. env_auth=false, isi access key/secret bucket; region/location constraint kosong bila diminta sesuai backend Spaces.
3. Endpoint sesuai region bucket, misalnya **fra1.digitaloceanspaces.com**, ACL **private**.
4. Buat remote kedua **clbk-safe**, tipe **crypt**.
5. Remote yang dibungkus: **spaces:NAMA_BUCKET_ANDA/clbk**, filename encryption **standard**, directory name encryption **true**.
6. Buat password crypt dan salt/password2 yang kuat, simpan keduanya di vault di luar VPS. Tanpanya data offsite terenkripsi tidak dapat dipulihkan.
7. Selesai dan simpan konfigurasi root. Jangan mengaktifkan password interaktif untuk seluruh file config tanpa menyiapkan mekanisme secret noninteraktif untuk cron.

**SERVER:**

```bash
chmod 700 /root/.config/rclone
chmod 600 /root/.config/rclone/rclone.conf
rclone copy /mnt/clbk-backups/archives clbk-safe: --include 'clbk-*.zip' --dry-run
rclone copy /mnt/clbk-backups/archives clbk-safe: --include 'clbk-*.zip'
rclone lsf clbk-safe:
```

Nama arsip terlihat normal melalui clbk-safe, sedangkan file di bucket dibungkus enkripsi. Selalu gunakan remote **clbk-safe:**, bukan spaces: langsung, untuk ZIP usaha. Backup file konfigurasi rclone juga secara terenkripsi; nilai password yang di-obscure dalam config bukan pengganti enkripsi file config.

Untuk otomatisasi, buat check Healthchecks **terpisah**: interval 1 jam, grace awal 30 menit, sesuaikan dengan durasi upload pertama. Pada SERVER:

```bash
nano /usr/local/sbin/clbk-offsite
```

Isi, ganti URL check offsite Anda:

```sh
#!/bin/sh
set -eu
umask 077
PING_URL='https://hc-ping.com/UUID-CHECK-OFFSITE-ANDA'
if /usr/bin/rclone copy /mnt/clbk-backups/archives clbk-safe: --include 'clbk-*.zip'; then
    /usr/bin/curl -fsS -m 10 --retry 2 -o /dev/null "$PING_URL"
else
    /usr/bin/curl -fsS -m 10 --retry 2 -o /dev/null "$PING_URL/fail"
    exit 1
fi
```

Kemudian:

```bash
chmod 700 /usr/local/sbin/clbk-offsite
/usr/local/sbin/clbk-offsite
nano /etc/cron.d/clbk-offsite
```

Isi cron berikut, dengan newline di akhir file:

```cron
17 * * * * root /usr/bin/flock -n /run/clbk-offsite.lock /usr/local/sbin/clbk-offsite
```

```bash
chmod 644 /etc/cron.d/clbk-offsite
systemctl enable --now cron
```

Ini job penyalinan offsite, **bukan scheduler Laravel kedua**. Pantau dashboard check dan pengiriman email. Ping sukses menunjukkan penyalinan berhasil, tetapi keberadaan backup baru tetap diperiksa doctor. Uji unduh satu arsip ke folder baru, tanpa menimpa backup yang ada:

```bash
install -d -o 33 -g 33 -m 700 /mnt/clbk-backups/archives/restore-test
rclone copyto clbk-safe:NAMA_BACKUP.zip /mnt/clbk-backups/archives/restore-test/NAMA_BACKUP.zip
chown 33:33 /mnt/clbk-backups/archives/restore-test/NAMA_BACKUP.zip
chmod 600 /mnt/clbk-backups/archives/restore-test/NAMA_BACKUP.zip
```

Lalu pada **TERMINAL APLIKASI**:

```bash
gosu www-data php artisan clbk:restore /backups/restore-test/NAMA_BACKUP.zip --verify-only
```

Lanjutkan latihan restore sebenarnya di lingkungan terpisah. Crypt didekripsi oleh rclone saat diambil; APP_KEY CLBK tetap diperlukan untuk memverifikasi ZIP. Pantau tagihan/pertumbuhan offsite, karena copy tidak menghapus backup lama. Jangan memasang lifecycle penghapusan sembarangan sebelum aturan retensi dan pemulihan diuji. Penyimpanan private/region berbeda ini belum menjadi backup immutable; pertahankan juga salinan terpisah yang tidak dapat dihapus memakai kredensial VPS.

### 14.4 Backup Coolify juga

Pada Coolify self-hosted, buka **Settings → Backup**, konfigurasi jadwal dan **Back up now**, pastikan eksekusi Success lalu download ke tempat aman. Ini menyimpan konfigurasi/dashboard Coolify, **tidak otomatis mencakup data aplikasi CLBK**. Simpan juga file **/data/coolify/source/.env** secara aman di luar VPS; APP_KEY instance tersebut diperlukan untuk membaca kembali kredensial terenkripsi Coolify. Jangan memasukkannya ke Git atau ZIP untuk pengguna. [Backup instance Coolify](https://coolify.io/docs/core/backup-and-recovery/instance-backup).

Backup database terjadwal milik resource MySQL Coolify boleh menjadi lapisan tambahan. Tetap pertahankan ZIP aplikasi yang juga menyimpan bukti privat dan merekonsiliasi angka usaha; backup SQL saja tidak menyertakan file bukti.

## 15. Restore data dengan aman

Restore mengganti kondisi usaha dengan kondisi saat ZIP dibuat. Transaksi sesudah waktu backup tidak otomatis ikut. Target operasionalnya kehilangan data maksimal 24 jam dan pemulihan maksimal 2 jam setelah mesin tersedia; **durasi ini harus diuji pada ukuran data/server Anda**, bukan dianggap jaminan dari build lokal.

### 15.1 Latihan wajib pada lingkungan terpisah

Buat resource Coolify uji pada server yang sesuai, domain uji sendiri, source/commit yang cocok dengan backup, data source misalnya **/srv/clbk-restore-test/data**, dan backup source **/mnt/clbk-backups/restore-test-archives**. Destination tetap /data dan /backups. Buat folder dan izin seperti bagian 5. Gunakan key yang cocok dengan ZIP untuk latihan restore ini, batasi akses pengelola, dan jangan menghubungkannya ke mount produksi. Email uji jangan sampai mengirim ke pelanggan tanpa sengaja.

Pulihkan mengikuti jalur B bagian 8; **jangan migrate/seed terlebih dahulu**. Bandingkan jumlah dokumen, kas, utang/piutang, stok, harga historis, file bukti, akun/role dan penugasan. Uji login/cetak/unduh. Catat waktu mulai, selesai, nama ZIP, commit, dan hasil. Setelah latihan, hentikan resource uji agar tidak menjadi server transaksi kedua.

### 15.2 Pemulihan pada resource produksi yang sudah berisi data

Lakukan pada jam maintenance dengan operator berwenang. Siapkan ZIP terverifikasi, APP_KEY, source/commit yang cocok, serta salinan offsite sebelum mulai. Jika ZIP berasal dari versi aplikasi lama, jangan memaksakan restore pada kode baru; ikuti rollback versi dengan teknisi.

1. Beri tahu pengguna berhenti input. Nonaktifkan Scheduled Task scheduler dan doctor di Coolify. Tunggu eksekusi yang sedang berjalan selesai; hentikan worker/integrasi eksternal lain jika pernah ditambahkan.
2. **TERMINAL APLIKASI**, lakukan:

```bash
cd /var/www/html
gosu www-data php artisan down
supervisorctl -c /etc/supervisor/clbk.conf stop nginx php-fpm
gosu www-data php artisan clbk:restore /backups/NAMA_BACKUP.zip --verify-only
gosu www-data php artisan clbk:restore /backups/NAMA_BACKUP.zip --force
```

Supervisor tetap hidup sehingga terminal tersedia, tetapi request web/PHP dihentikan. Jangan klik Stop/Deploy resource selama proses. Flag --force mengonfirmasi operator telah menghentikan proses; flag tersebut **tidak** melewati verifikasi. Restore membuat safety backup kondisi sekarang sebelum mengganti data; jika safety gagal, jangan memaksa dengan menghapus database atau memakai --new-server.

3. Pastikan pesan **Pemulihan dan rekonsiliasi selesai**. Catat ID safety backup. Maintenance tetap aktif dan seluruh session/reset/remember token lama dicabut. Jika gagal, pertahankan maintenance dan periksa error/safety bersama pengelola.
4. Untuk uji oleh operator sementara pengguna lain masih ditutup, buat secret maintenance acak lalu jalankan perintah berikut; ganti contoh secret dengan hasil random kuat. Bagikan URL bypass hanya ke operator yang menguji.

```bash
gosu www-data php artisan down --secret=GANTI_DENGAN_SECRET_RANDOM_PANJANG
supervisorctl -c /etc/supervisor/clbk.conf start php-fpm nginx
```

Operator membuka **https://toke.example.com/SECRET_YANG_DIISI**, lalu login. Secret bypass hanya melewati layar maintenance; login dan otorisasi tetap wajib. Pengguna lain tetap 503. Jangan mengirim URL bypass lewat grup; ini credential sementara. Jangan mengaktifkan access log proxy yang menyimpan secret URL tanpa perlindungan. [Maintenance Laravel](https://laravel.com/docs/13.x/configuration#maintenance-mode).

5. Periksa hasil rekonsiliasi, laporan, bukti, serta akun/role. Backup juga mengembalikan password/keaktifan/hak akses pada waktu snapshot. Terapkan kembali penonaktifan atau perubahan akses setelah tanggal backup dan ganti password yang sudah dicabut **sebelum membuka untuk umum**. Tunda transaksi nyata sampai pemeriksaan selesai.
6. Bila benar, **TERMINAL APLIKASI**:

```bash
gosu www-data php artisan up
gosu www-data php artisan clbk:backup --reason=post-restore-verified
```

Aktifkan kembali dua scheduled task dan alarm, tunggu heartbeat, lalu jalankan doctor. Pengguna login ulang. Simpan catatan insiden dan jangan langsung menghapus backup safety.

### 15.3 Jika seluruh VPS rusak/hilang

Siapkan VPS pengganti/Coolify, resource MySQL 8.4 baru, folder backup persisten sesuai mode yang dipilih, domain, source commit yang cocok, APP_KEY aplikasi, konfigurasi runtime dan ZIP dari offsite. Ikuti bagian 3–8 **jalur B**. Jalankan migrate untuk menyiapkan schema MySQL tujuan, tanpa seed/bootstrap sebelum restore. Gunakan APP_KEY sumber yang sama.

Jika database lama rusak sehingga safety backup tidak dapat dibuat, gunakan lingkungan pengganti yang benar-benar baru dan pertahankan volume lama untuk pemeriksaan. Jangan menghapus volume MySQL atau memaksa --new-server pada resource yang masih mempunyai data. Atur DNS ke server pengganti setelah hasil diterima dan hentikan server lama sebelum menerima transaksi, agar hanya ada satu penulis.

## 16. Memastikan migrasi Windows dan Linux cocok

Bagian ini penting **sebelum membuat ZIP pindahan dari Windows**, karena manifest restore memeriksa byte file migrasi. Bukan hanya nama file/SQL, line ending juga harus cocok. Untuk pemasangan kosong bagian ini tidak diperlukan.

1. Pastikan semua migrasi sumber sudah di-commit, di-push dan berada pada commit yang dideploy. Jangan menormalkan berkas yang masih mempunyai perubahan skema belum ditinjau.
2. Pada WINDOWS, periksa:

```powershell
Set-Location D:/Sites/CLBK
git diff --exit-code HEAD -- database/migrations database/schema/mysql-schema.sql
if ($LASTEXITCODE -ne 0) { throw 'Tinjau dan commit perubahan migrasi sebelum melanjutkan' }
git ls-files --others --exclude-standard -- database/migrations database/schema/mysql-schema.sql
```

Perintah terakhir tidak boleh menampilkan file migrasi belum terlacak. Dengan aturan .gitattributes proyek `eol=lf`, source Git Linux memakai LF. Jika worktree Windows masih CRLF, normalkan **hanya line ending** berkas migrasi setelah memastikan perubahan skema sudah bersih:

```powershell
$utf8Migration = [Text.UTF8Encoding]::new($false)
Get-ChildItem -LiteralPath database/migrations -Filter '*.php' -File | ForEach-Object {
    $migrationText = [IO.File]::ReadAllText($_.FullName)
    [IO.File]::WriteAllText($_.FullName, $migrationText.Replace("`r`n", "`n"), $utf8Migration)
}
Get-ChildItem -LiteralPath database/migrations -Filter '*.php' -File | Sort-Object Name | ForEach-Object {
    $migrationHash = (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash.ToLowerInvariant()
    '{0}  {1}' -f $migrationHash, $_.Name
}
Get-FileHash -LiteralPath database/schema/mysql-schema.sql -Algorithm SHA256
```

3. Pada **TERMINAL APLIKASI** versi yang akan menerima restore:

```bash
cd /var/www/html
sha256sum database/migrations/*.php database/schema/mysql-schema.sql
```

4. Cocokkan setiap nama file dan SHA256, termasuk `mysql-schema.sql`; abaikan perbedaan awalan path pada tampilan. Jika ada perbedaan lain, berhenti dan minta pengembang menyamakan source/commit. Jangan mengedit manifest backup atau mengubah skema untuk mengakali verifikasi.
5. **Setelah** cocok, buat ZIP baru dari sumber yang sudah dihentikan inputnya, memakai langkah bagian 8B. Backup lama yang sudah memiliki fingerprint berbeda tidak berubah hanya karena berkas source diperbaiki.

Untuk arsip historis, simpan snapshot source dengan byte migrasi aslinya bersama catatan rilis. Git saja tidak menggantikan arsip source asli bila backup terlanjur dibuat sebelum normalisasi. Proses ini tidak menjalankan migrasi baru dan tidak mengubah isi database.

## 17. Cara update aplikasi berikutnya

Untuk sistem keuangan ini, gunakan pembaruan terencana dengan waktu maintenance. Jangan mengandalkan rollback container untuk mengembalikan perubahan database.

1. Uji versi baru pada staging dengan data terpisah. Catat commit lama/baru dan simpan source/image lama yang masih dibutuhkan untuk pemulihan; jangan membersihkan image secara agresif sebelum verifikasi.
2. Pastikan backup harian, offsite, APP_KEY dan disk sehat. Beri tahu pengguna berhenti input.
3. Nonaktifkan Scheduled Tasks CLBK di Coolify dan tunggu eksekusi berjalan selesai. **TERMINAL APLIKASI versi lama**:

```bash
gosu www-data php artisan down
supervisorctl -c /etc/supervisor/clbk.conf stop nginx php-fpm
gosu www-data php artisan clbk:backup --reason=pre-upgrade
```

4. Verifikasi ZIP pre-upgrade menggunakan --verify-only seperti bagian 9 dan salin ke offsite. Bila gagal, jangan lanjut. Catat nama ZIP dan source commit lama yang cocok.
5. Push kode baru yang sudah ditinjau, lalu klik **Deploy** secara manual pada resource yang sama. **Jangan membuat source mount baru atau mengubah APP_KEY.** Container baru memakai data/maintenance yang sama. Entrypoint tidak menjalankan migrate/seed atau membuka maintenance otomatis. Pengguna tetap menerima maintenance walaupun container healthy.
6. Setelah container baru berjalan, buka **TERMINAL APLIKASI versi baru**:

```bash
cd /var/www/html
gosu www-data php artisan migrate --force
gosu www-data php artisan config:cache
gosu www-data php artisan route:cache
gosu www-data php artisan view:cache
```

Seed tambahan hanya dijalankan bila instruksi rilis memerlukannya. Jangan menjalankan seeder akun demo. Bila mengganti runtime env lewat Coolify, redeploy/recreate diperlukan; config:cache saja tidak memasukkan environment baru ke container lama.

7. Gunakan maintenance secret dan uji operator seperti bagian 15.2. Pastikan data lama masih terlihat, laporan/hak akses dan file bukti benar. Jalankan backup manual baru dan verify, buka maintenance setelah uji berhasil, lalu aktifkan scheduler/doctor lagi. Uji /up dan login browser dari luar server.
8. Pengguna memuat ulang halaman browser setelah update. Jika form lama menampilkan konflik atau versi aset berubah, muat ulang dahulu lalu periksa riwayat sebelum mengirim ulang.

**Jika gagal:** pertahankan maintenance. Bila tidak ada perubahan skema/data, versi kode sebelumnya dapat dipakai kembali setelah ditinjau. Bila migrate sudah mengubah skema/data, tombol Rollback Coolify hanya mengembalikan image/kode; gunakan **source/image lama + ZIP pre-upgrade yang cocok**, dengan prosedur restore bagian 15. Simpan kondisi gagal terlebih dahulu. Jika migrasi baru gagal setengah jalan sehingga safety backup ditolak, gunakan target pemulihan baru dan pertahankan data lama; jangan menghapus database untuk memaksa rollback. Transaksi baru sesudah backup perlu direkonsiliasi sebelum pemulihan dinyatakan selesai.

## 18. Masalah yang sering muncul

| Gejala | Yang diperiksa dan tindakan |
|---|---|
| Build berhenti karena memori | Periksa RAM/disk VPS dan Deployment Logs; hindari build bersamaan, tambah kapasitas bila perlu. Jangan menghapus data/volume untuk mengosongkan ruang. |
| Repository bisa dibuka di laptop tetapi Coolify gagal clone | Periksa izin GitHub App/deploy key ke repository private dan branch yang dipilih. |
| File/fitur lokal tidak muncul setelah deploy | Commit/push source yang belum terkirim; cocokkan commit pada Deployment Logs. |
| Container gagal start: mount/perangkat sama | Periksa dua mount dengan folder sumber berbeda. Untuk backup lokal Hostinger, gunakan BACKUP_REQUIRE_SEPARATE_DISK=false dan image baru sesuai bagian 5.4. Jika true, disk backup harus terpisah. |
| 502/Bad Gateway | Ikuti diagnosis bagian 18.1. Ports Exposes harus 8080 dan Domains mengarah ke port tersebut. Port FPM 9000 bukan port HTTP. |
| 503 sesudah deploy pertama | Maintenance awal memang aktif; selesaikan jalur A/B lalu artisan up. Sesudah update/restore, buka hanya setelah diverifikasi. |
| Healthz sehat tetapi aplikasi error | Healthz hanya memeriksa Nginx/FPM. Periksa runtime env, log Laravel, /up dan doctor; pastikan migrasi telah dijalankan pada jalur A atau restore sukses pada B. |
| HTTPS tidak terbit atau klien menolak sertifikat | DNS harus ke VPS yang benar, 80/443 terbuka, hapus AAAA yang salah, periksa proxy dan waktu perangkat. Cloudflare DNS only dahulu. Jangan mematikan verifikasi SSL. |
| Login berulang, 419/Page Expired | Pastikan URL HTTPS sama, APP_KEY tidak berubah, SESSION_DOMAIN tidak diisi salah, Secure/HttpOnly benar, satu server penulis, session database tersedia. Simpan env dan redeploy, lalu logout/bersihkan cookie origin itu dan login kembali. Jangan menonaktifkan CSRF/Secure cookie. |
| 403/data kosong untuk supir | Periksa is_active, role, penugasan mobil dan kepemilikan data; jangan membuka policy untuk menghilangkan error. |
| Banyak pengguna terkena throttle bersamaan | Verifikasi TRUSTED_PROXIES sesuai subnet proxy/destination yang nyata, terutama setelah network diganti. Jangan memakai wildcard. |
| Halaman kosong/aset 404 | Periksa npm run build pada build log, public/build/manifest.json, kapitalisasi nama file pada Linux, dan pastikan public/hot lokal tidak ikut image. |
| Tidak bisa upload/413 | Batas Nginx 11 MiB request dan PHP 10 MiB per file; validasi aplikasi dapat lebih kecil. Coba JPEG/PNG/PDF sesuai batas/quota, bukan menaikkan batas tanpa memeriksa kapasitas. |
| Email tidak masuk | Pada DigitalOcean gunakan port yang diizinkan seperti Brevo 2525; periksa SMTP login/key bukan API key, sender/domain SPF/DKIM, spam, dan status key provider. Jalankan email uji bulanan. |
| Doctor: heartbeat terlambat | Scheduled Task harus enabled, container/command benar, Recent executions tidak gagal. Jangan menggandakan scheduler untuk memperbaikinya. |
| Doctor: backup tua/tidak valid | Baca kegagalan backup, ruang disk dan keberadaan bukti. Buat backup baru hanya setelah sebab diperbaiki, lalu verify. Jangan menghapus bukti yang masih dirujuk. |
| APP_ENV harus production | Isi APP_ENV=production pada Production Environment Variables aplikasi dan aktifkan Runtime Variable. File .env.production lokal tidak otomatis dibaca Coolify. Simpan lalu Redeploy. |
| APP_URL produksi harus berupa origin HTTPS | Pada resource aplikasi, isi APP_URL dengan satu URL publik HTTPS, misalnya https://toke.namausaha.id sesuai domain sebenarnya. Value tidak memuat APP_URL=, /login, query, fragment, atau port internal :8080. Aktifkan Production Runtime Variable, simpan dan Redeploy. Rincian pada bagian 7.5. |
| MySQL tidak dapat dihubungi | Pastikan resource MySQL healthy, DB_HOST memakai hostname Internal URL, port 3306, password normal user benar, serta aplikasi/database berada pada destination yang sama. |
| Error 1419 saat migrasi / Schema atau trigger belum lengkap | Ikuti pemulihan bagian 18.2. Administrator MySQL menetapkan log_bin_trust_function_creators=ON; gunakan image baru dan clbk:mysql-initialize untuk instalasi kosong yang terhenti. Jangan menghapus data atau trigger. |
| MySQL deadlock/lock timeout | Koordinator mencoba ulang konflik 1205/1213 maksimal tiga kali. Periksa beban/operasi bersamaan dan riwayat transaksi sebelum submit ulang. Tetap gunakan idempotency key. |
| Restore terhenti | Pertahankan maintenance dan hentikan worker. Periksa safety backup serta trigger MySQL dengan clbk:database-ready dan clbk:doctor sebelum artisan up. Restore melepas/memasang kembali trigger; proses tidak boleh dihentikan di tengah jalan. |
| Disk penuh | Periksa host dan volume backup; backup manual/offsite/image/log bisa tumbuh. Arsipkan sesuai retensi setelah verifikasi. Jangan memakai docker system prune --volumes sembarangan. |
| Cetak/PDF berbeda format | Buka halaman cetak laporan/voucher dahulu, periksa dialog PDF/printer dan ukuran kertas. Uji printer fisik yang benar. |
| Lupa password manajer | Gunakan Lupa password setelah SMTP teruji. Bootstrap-user tidak membuat manajer kedua ketika users sudah berisi; jangan menghapus tabel user. |

Diagnostik **TERMINAL APLIKASI**:

```bash
supervisorctl -c /etc/supervisor/clbk.conf status
nginx -t
php-fpm -t
gosu www-data php artisan about
gosu www-data php artisan clbk:doctor --json
ls -lah /data/storage/logs
```

Baca Runtime/Deployment Logs Coolify dan log Laravel privat. Jangan mengaktifkan APP_DEBUG di produksi untuk membagikan stack trace. Sebelum mengirim log ke teknisi, sembunyikan APP_KEY, kredensial SMTP, session cookie, reset token, ping URL, email/data pelanggan dan angka usaha sensitif.

Untuk merotasi log scheduler yang terus bertambah, teknisi dapat memasang logrotate pada **SERVER** untuk `/srv/clbk/data/storage/logs/backup-scheduler.log`, harian, simpan 14, compress, missingok/notifempty, create 0640 uid/gid www-data (33), su www-data www-data. Jalankan logrotate --debug untuk meninjau sebelum mengaktifkannya. Log Laravel daily mengikuti LOG_DAILY_DAYS; ZIP backup tidak dirotasi dengan logrotate.

### 18.1 Deploy sukses tetapi website Bad Gateway

Deployment yang selesai belum cukup untuk menentukan penyebab 502. Periksa jalur **browser → proxy Coolify → Nginx port 8080 → PHP-FPM** secara bertahap.

1. Buka **resource aplikasi CLBK → Configuration → General/Network**. Isi **Ports Exposes** dengan `8080` saja, **Ports Mappings** tetap kosong, dan **Domains** dengan `https://DOMAIN_APLIKASI_ANDA:8080`. Ganti placeholder dengan hostname sebenarnya. Jangan memakai port 9000 sebagai tujuan domain; port tersebut memakai FastCGI, bukan HTTP.
2. Pada **Production Environment Variables**, APP_URL tetap `https://DOMAIN_APLIKASI_ANDA`, tanpa `:8080`. Simpan perubahan konfigurasi dan klik **Redeploy** agar routing container diperbarui.
3. Periksa bahwa container terbaru berstatus **running/healthy** pada Coolify. Bila restart berulang atau unhealthy, baca Runtime Logs sebelum menyimpulkan proxy salah port.
4. Buka **Terminal resource aplikasi CLBK**, lalu jalankan dua perintah berikut. Perintah hanya membaca status dan tidak menampilkan env atau cookie:

```bash
curl -sS --max-time 10 -o /dev/null -w 'healthz_http=%{http_code}\n' http://127.0.0.1:8080/healthz
supervisorctl -c /etc/supervisor/clbk.conf status
```

Kedua proses `nginx` dan `php-fpm` seharusnya berstatus `RUNNING`. Baca hasil curl sebagai berikut:

| Hasil di dalam container | Makna dan langkah berikutnya |
|---|---|
| healthz_http=200, tetapi domain publik 502 | Nginx dan FPM bisa saling menghubungi. Periksa tujuan port domain, proxy server berstatus Running, serta network/destination aplikasi dan proxy. Jangan membuka port 8080 ke internet untuk melewati proxy. |
| healthz_http=502 | Nginx menerima request, tetapi jalur ke FPM gagal. Periksa status php-fpm dan Runtime Logs; port FPM pada repo adalah 127.0.0.1:9000. |
| healthz_http=000 disertai connection refused/timeout | Nginx belum menerima request pada port 8080, proses belum siap, atau container bermasalah. Periksa status nginx dan Runtime Logs. |
| Status lain | Periksa Runtime Logs dan apakah image/konfigurasi yang dipakai sesuai commit deployment; endpoint healthz pada repo seharusnya mengembalikan 200. |

Jika terminal tidak bisa dibuka, ambil 30 baris terakhir Runtime Logs dari resource aplikasi. Untuk diagnosis lanjutan, catat URL publik, nilai Domains, Ports Exposes, hasil kedua perintah, dan status container. Jangan membagikan seluruh env atau output `docker inspect` lengkap karena dapat berisi secret. [Panduan Bad Gateway Coolify](https://coolify.io/docs/troubleshoot/applications/bad-gateway).

Jika setelah routing diperbaiki website menampilkan **503 maintenance**, selesaikan inisialisasi database/akun dan verifikasi bagian 8–9 sebelum membuka aplikasi. Maintenance awal tidak diselesaikan dengan menghapus data atau seed ulang database yang sudah dipakai.

### 18.2 Error 1419 dan container restart karena schema belum lengkap

Jika log berisi `ERROR 1419 (HY000)` saat `CREATE TRIGGER`, MySQL sudah dapat dihubungi tetapi akun aplikasi belum boleh membuat trigger ketika binary logging aktif. Import schema berhenti sebagian. Pada image lama, pemeriksaan schema kemudian menghentikan entrypoint sebelum Nginx berjalan, sehingga muncul unhealthy/rollback atau Bad Gateway. Peringatan umum Coolify tentang curl bukan penyebab pada log ini; Dockerfile sudah memasang curl.

Lakukan urutan berikut untuk instalasi baru:

1. Pada **resource aplikasi CLBK**, kosongkan **Post-deployment Command** migrasi/seed. Jangan mematikan health check.
2. Buka **Terminal resource MySQL** (bukan terminal aplikasi), jalankan:

```bash
mysql -u root -p
```

Masukkan root password MySQL pada prompt. Setelah masuk ke prompt `mysql>`, jalankan:

```sql
SET PERSIST log_bin_trust_function_creators = ON;
SHOW GLOBAL VARIABLES LIKE 'log_bin_trust_function_creators';
EXIT;
```

Hasil pemeriksaan harus `ON`. Nilai ini langsung berlaku dan tersimpan untuk restart MySQL berikutnya. Jangan mengganti DB_USERNAME aplikasi menjadi root atau memberi SUPER kepada akun aplikasi. [Trigger dan binary logging MySQL 8.4](https://dev.mysql.com/doc/refman/8.4/en/stored-programs-logging.html).

3. Commit/push perubahan `app/Console/Commands/DatabaseReady.php`, file baru `app/Console/Commands/MySqlInitialize.php`, dan `deploy/coolify/entrypoint.sh`, lalu **Redeploy** dari commit tersebut. Image baru menyediakan halaman maintenance dan terminal saat schema belum lengkap. Database tidak dimigrasi otomatis oleh startup.
4. Buka **Terminal resource aplikasi CLBK**, jalankan:

```bash
gosu www-data php artisan clbk:mysql-initialize
```

Perintah ini memeriksa isi database sebelum mengulang import. Jika berhasil, lanjutkan seed dan pembuatan akun sesuai bagian 8 A, lalu backup/verifikasi bagian 9 sebelum `artisan up`. Jika menolak karena ada data atau riwayat migrasi yang tidak lengkap, pertahankan maintenance dan tinjau pemulihan yang mempertahankan data; jangan memaksa lewat `migrate:fresh`, `db:wipe`, atau menghapus volume MySQL.

Import baseline berisi `DROP TABLE IF EXISTS`, sehingga **jangan menjalankan ulang file SQL atau `migrate --force` secara buta** setelah import awal gagal. Perintah initializer membatasi percobaan ulang ke instalasi kosong. `clbk:database-ready` tanpa `--serve-maintenance` harus lulus sebelum membuka akses pengguna.

## 19. Catatan serah terima yang Anda pegang

Catat domain aplikasi/panel, penyedia/lokasi VPS, IP, akun penagihan dan tanggal perpanjangan, commit aktif, nama backup terakhir, lokasi offsite, serta kontak penerima alarm. Simpan secret di vault, bukan di tabel catatan biasa. Pisahkan APP_KEY CLBK, APP_KEY Coolify dan private SSH key dengan label yang jelas.

Dokumen teknis pendamping: [backup/restore](docs/backup-restore.md) dan [hasil operasional](docs/tahap-6.md). Panduan server manual pada docs/deployment.md dan unit systemd lama hanya referensi jalur lain; **untuk pilihan Coolify, ikuti DEPLOY.md ini dan Scheduled Tasks Coolify**.

Pemeriksaan lokal yang lulus: sintaks blok Bash/PowerShell dan kedua shell script, parsing file INI/PHP-FPM/Supervisor, tautan lokal serta UTF-8 dokumen, dan nginx -t memakai binary Debian pada Linux dengan lokasi include/log/pid/temp disesuaikan untuk direktori uji. Urutan package:discover/config:cache/route:cache/view:cache juga lulus pada salinan uji production tanpa database, memakai vendor lokal. Data dan konfigurasi runtime aplikasi kerja tidak dipakai sebagai tujuan uji.

**Docker Engine tidak tersedia dalam sesi penyusunan ini, sehingga image belum diuji build/run penuh.** Parsing INI tidak menggantikan pemeriksaan daemon PHP-FPM/Supervisor. Build Docker di Coolify, koneksi DNS/SMTP, disk backup nyata, restore, serta interaksi browser komputer/ponsel harus lulus pada lingkungan target sebelum go-live.
