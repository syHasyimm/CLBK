Berikut rekap alur deploy CLBK yang akhirnya berhasil menggunakan **[https://toke.ncimproject.tech](https://toke.ncimproject.tech)**. Saya cek ulang: halaman login membalas **200 OK** dan `/healthz` menghasilkan **`pong`**.

1. **Siapkan resource aplikasi di Coolify**

   Hubungkan repository proyek, kemudian gunakan konfigurasi:

   | Pengaturan | Nilai |
   |---|---|
   | Build Pack | `Dockerfile` |
   | Base Directory | `/` |
   | Dockerfile Location | `/Dockerfile` |
   | Ports Exposes | `8080` |
   | Ports Mappings | Kosong |

   Alur akses aplikasinya:

   ```text
   Browser HTTPS → Proxy Coolify → Nginx:8080 → PHP-FPM:9000 → Laravel
   ```

2. **Siapkan database MySQL 8.4**

   Buat resource MySQL terpisah di Coolify. Catat hostname internal, nama database, username, dan password untuk environment aplikasi.

   Pastikan penyimpanan database persisten pada:

   ```text
   /var/lib/mysql
   ```

   `DB_HOST` aplikasi memakai hostname internal resource MySQL.

3. **Penuhi prasyarat trigger MySQL**

   Kita sempat mengalami kegagalan migrasi:

   ```text
   Administrator MySQL perlu menjalankan
   SET PERSIST log_bin_trust_function_creators=ON;
   ```

   Buka **Terminal resource MySQL**, lalu login administrator:

   ```sh
   mysql -u root -p
   ```

   Jalankan pada prompt `mysql>`:

   ```sql
   SET PERSIST log_bin_trust_function_creators = ON;
   SELECT @@GLOBAL.log_bin_trust_function_creators;
   ```

   Hasil pemeriksaan harus **`1`**. Pengaturan ini langsung aktif dan disimpan untuk restart berikutnya. [Dokumentasi MySQL](https://dev.mysql.com/doc/refman/8.4/en/persisted-system-variables.html)

4. **Pasang persistent storage aplikasi**

   Buka **aplikasi CLBK → Configuration → Persistent Storage**, lalu pasang dua volume:

   | Destination Path | Kegunaan |
   |---|---|
   | `/data` | Bukti privat dan penyimpanan runtime Laravel |
   | `/backups` | Arsip backup aplikasi |

   Pada awal proses, entrypoint menolak startup karena pemeriksaan mount `/backups` gagal. Storage perlu benar-benar terpasang pada container aplikasi; mengisi environment saja belum memasang volume.

   Setelah menyimpan konfigurasi storage, lakukan **Redeploy**.

5. **Siapkan DNS untuk subdomain akhir**

   Domain yang akhirnya Anda gunakan adalah:

   ```text
   toke.ncimproject.tech
   ```

   Record DNS-nya mengarah ke VPS:

   | Type | Name | Value |
   |---|---|---|
   | `A` | `toke` | `72.62.195.215` |

   Setiap hostname tambahan, termasuk `www.toke.ncimproject.tech`, membutuhkan record DNS yang sesuai jika ingin digunakan.

6. **Atur domain aplikasi di Coolify**

   Pada pengaturan **Domains**, gunakan:

   ```text
   https://toke.ncimproject.tech
   ```

   Pertahankan **Ports Exposes `8080`** dan biarkan label dikelola otomatis oleh Coolify.

   Domain tanpa suffix `:8080` dapat memakai port pertama pada Ports Exposes. Perubahan domain perlu disimpan dan diterapkan melalui redeploy. [Dokumentasi Coolify](https://coolify.io/docs/core/networking/domains)

7. **Isi environment runtime aplikasi**

   Konfigurasi inti yang digunakan:

   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://toke.ncimproject.tech
   APP_MAINTENANCE_DRIVER=file

   DB_CONNECTION=mysql
   DB_PORT=3306

   LARAVEL_STORAGE_PATH=/data/storage
   CLBK_PRIVATE_STORAGE=/data/proofs
   BACKUP_PATH=/backups
   BACKUP_REQUIRE_SEPARATE_DISK=false

   SESSION_DRIVER=database
   SESSION_ENCRYPT=true
   SESSION_SECURE_COOKIE=true
   SESSION_DOMAIN=null
   CACHE_STORE=database

   MAIL_ENABLED=false
   MAIL_MAILER=array
   ```

   Isi juga nilai sesuai resource Anda:

   ```text
   APP_KEY
   DB_HOST
   DB_DATABASE
   DB_USERNAME
   DB_PASSWORD
   TRUSTED_PROXIES
   ```

   Gunakan APP_KEY yang sama untuk deployment berikutnya. `TRUSTED_PROXIES` berisi IP proxy pada jaringan aplikasi.

8. **Deploy dan periksa kesehatan container**

   Klik **Deploy/Redeploy**, kemudian periksa dari **Terminal aplikasi CLBK**:

   ```sh
   mountpoint /data
   mountpoint /backups
   curl -fsS http://127.0.0.1:8080/healthz
   ```

   Hasil yang diharapkan:

   ```text
   /data is a mountpoint
   /backups is a mountpoint
   pong
   ```

   Dalam pemeriksaan kita, container berstatus **running** dan **healthy**. Health check ini memeriksa Nginx serta PHP-FPM; kesiapan database diperiksa terpisah.

9. **Jalankan migrasi database**

   Masih di **Terminal aplikasi CLBK**:

   ```sh
   cd /var/www/html
   gosu www-data php artisan migrate --force
   ```

   Pastikan seluruh migrasi berhasil. Ketika prasyarat MySQL tadi belum aktif, migrasi berhenti pada pemeriksaan pertama. Setelah pengaturannya diperbaiki, migrasi dapat diulangi.

10. **Siapkan data dasar dan akun awal**

    Untuk instalasi baru:

    ```sh
    gosu www-data php artisan db:seed --force
    gosu www-data php artisan clbk:bootstrap-user
    ```

    Isi nama, email, dan password melalui prompt. Bootstrap akun awal dilakukan saat aplikasi belum memiliki pengguna.

11. **Periksa kesiapan database dan buka maintenance**

    Jalankan:

    ```sh
    gosu www-data php artisan clbk:database-ready
    ```

    Setelah migrasi lengkap dan akun awal tersedia:

    ```sh
    gosu www-data php artisan up
    ```

    **Ini langkah terakhir yang mengatasi halaman 503 Laravel.** Proyek mempertahankan maintenance saat instalasi belum siap; migrasi tidak otomatis menonaktifkannya. Perintah `up` membuka kembali aplikasi. [Dokumentasi Laravel](https://laravel.com/docs/13.x/configuration#maintenance-mode)

12. **Verifikasi akses akhir**

    Buka:

    ```text
    https://toke.ncimproject.tech/login
    https://toke.ncimproject.tech/healthz
    ```

    Kemudian uji login, halaman aplikasi, upload bukti, dan pembuatan backup. Pemeriksaan operasional tambahan tersedia melalui:

    ```sh
    gosu www-data php artisan clbk:doctor --json
    ```

Masalah yang muncul selama perjalanan deploy kita:

| Masalah | Temuan dan penanganan |
|---|---|
| Pesan persistent storage `/backups` | Pemeriksaan mount gagal; periksa destination pada resource aplikasi dan redeploy. |
| **502 Bad Gateway** | Kita memeriksa port dan routing. Port `8080` ternyata sudah benar; penyebab spesifik 502 awal belum terbukti dari log yang tersedia. |
| Error ACME `www.school.ncimproject.tech` | DNS hostname tersebut tidak ditemukan. Log ini berkaitan dengan domain lain. |
| Migrasi gagal | Prasyarat `log_bin_trust_function_creators` belum aktif; diperbaiki melalui administrator MySQL. |
| **503 `no available server`** pada domain utama | Setelah berpindah ke subdomain, router aktif menggunakan `toke.ncimproject.tech`. |
| **503 halaman Laravel** pada subdomain | Routing sudah bekerja, tetapi aplikasi masih maintenance; diselesaikan setelah kesiapan database diperiksa dan `artisan up` dijalankan. |

Untuk **deployment berikutnya**, alurnya menjadi: buat backup → aktifkan maintenance → deploy versi baru → jalankan migrasi → periksa kesiapan database → jalankan `artisan up` → verifikasi akses. Pertahankan APP_KEY, database, dan volume yang sama; seed serta bootstrap akun awal tidak perlu diulang sebagai rutinitas redeploy.
