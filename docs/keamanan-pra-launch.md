# Keamanan pra-launch

Perbaikan audit mencakup origin tautan reset yang tetap, trusted hosts, autentikasi ulang saat email berubah, respons pemulihan seragam, kebijakan password terpusat, rate limit, kuota bukti privat, header keamanan, dan sanitasi error database produksi. Pengaturan deployment sebenarnya tetap perlu diisi oleh pengelola server.

## Konfigurasi produksi

Gunakan `.env.example` sebagai dasar konfigurasi; jangan menyalin nilai contoh domain/SMTP tanpa menyesuaikannya. APP_KEY dibuat pada server dan harus dipertahankan saat deployment berikutnya. Tidak ada secret produksi di template.

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` berupa origin HTTPS canonical tanpa path/kredensial/query/fragment.
- Cookie wajib `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, dan SameSite `lax` atau `strict`. Aktifkan enkripsi payload melalui `SESSION_ENCRYPT=true` saat menyiapkan produksi.
- Isi SMTP sebenarnya termasuk alamat pengirim. Mailer `log`, `array`, serta mailer gabungan yang mengarah ke keduanya ditolak saat boot produksi agar token reset tidak tercatat sebagai email di log.
- Log produksi memakai level warning dan rotasi harian. Pelaporan error database hanya menulis tipe exception, SQLSTATE, dan kode driver; pesan, SQL, binding, serta rantai exception tidak disertakan. Log development lama tidak dihapus oleh perubahan ini. Batasi akses dan kelola retensinya sebelum menyalin server ke produksi.
- Web root harus menunjuk ke `public/`; `.env` dan `storage/app/private` tidak boleh dapat diunduh melalui web server.

Ketika konfigurasi produksi ditolak, debug dimatikan terlebih dahulu sehingga exception boot tidak mengungkap trace. Konfigurasi yang berubah perlu diikuti pembaruan cache konfigurasi sesuai prosedur deployment.

## HTTPS dan reverse proxy

TLS langsung dapat memakai `TRUSTED_PROXIES` kosong. Jika TLS berhenti di reverse proxy, isi daftar IP/CIDR proxy yang dikelola, dipisahkan koma, misalnya `TRUSTED_PROXIES=192.0.2.10,2001:db8::10`. Jangan gunakan wildcard atau CIDR seluruh internet.

Proxy harus mempertahankan Host canonical dan menetapkan/menimpa `X-Forwarded-Proto`, `X-Forwarded-For`, serta port yang benar. Aplikasi hanya mempercayai header tersebut dari alamat yang ditetapkan, agar HTTPS dan limiter IP bekerja tanpa menerima header buatan klien langsung. Header forwarded host tidak dipercaya. Tolak Host asing dan arahkan HTTP ke HTTPS pada ingress; aplikasi sendiri menolak request HTTP produksi sebelum controller/session diproses.

Respons aplikasi, termasuk error, memakai X-Frame-Options DENY, CSP `frame-ancestors 'none'; object-src 'none'; base-uri 'self'`, nosniff, dan no-referrer. HTTPS produksi juga memakai HSTS satu tahun tanpa memperluasnya ke subdomain. CSP ini membatasi framing/objek/base URI; kebijakan script berbasis nonce belum diterapkan karena memerlukan integrasi aset dan inline Ziggy.

## Pembatasan request dan bukti

- Login/permintaan reset/pengiriman password reset berbagi batas 20 request per menit per IP. Login tetap membatasi lima kegagalan per kombinasi email/IP.
- Konfirmasi password, perubahan password, dan perubahan profil berbagi batas lima request per menit per akun, termasuk saat IP berganti.
- Upload bukti penjualan/keuangan berbagi batas sepuluh request per menit per akun.
- `PROOFS_MAX_PER_DOCUMENT=5` membatasi jumlah bukti per dokumen; `PROOFS_MAX_BYTES_PER_USER=262144000` membatasi 250 MiB per pengunggah; `PROOFS_MAX_BYTES_TOTAL=2147483648` membatasi total metadata bukti ke 2 GiB. Nilai harus positif. Ukuran setiap file tetap maksimal 10 MiB dan MIME PDF/JPG/JPEG/PNG tetap divalidasi.

Kuota diperiksa sebelum penulisan file dan kembali di bawah write lock database. Batas tersebut mengukur file yang tercatat sebagai bukti, bukan seluruh pemakaian filesystem. Batasi volume penyimpanan, direktori temporary upload PHP/web server, backup, dan log secara terpisah agar request paralel atau pertumbuhan file lain tidak memenuhi disk aplikasi. Sediakan pemantauan kapasitas dan prosedur pengarsipan bukti yang mempertahankan histori audit.
