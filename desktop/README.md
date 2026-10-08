# Desktop CLBK TOKE

Klien Tauri 2 memuat origin Laravel yang sama dengan browser. Autentikasi tetap memakai cookie session Breeze dan CSRF pada origin server. Tidak ada token login yang disimpan melalui IPC dan tidak ada database lokal desktop.

## Development

Prasyarat Windows: Microsoft C++ Build Tools dengan MSVC dan Windows SDK, Rust toolchain MSVC aktif, serta WebView2. Ikuti [prasyarat resmi Tauri](https://v2.tauri.app/start/prerequisites/#windows). Pada komputer ini Rust 1.99.0, Build Tools 2022, Windows SDK, dan WebView2 sudah tersedia.

Build Tools dipasang di `D:\Sites\CLBK\.tools\vs-buildtools` karena pemasangan standar tidak memiliki cukup ruang di drive C. Folder `.tools` merupakan dependensi lokal yang terdaftar pada installer Windows; pertahankan selama digunakan untuk build. Dependensi tersebut tidak disertakan dalam source repository.

Jalankan web Laravel dengan aset yang sudah dibangun, lalu:

```powershell
cd desktop
npm ci
$env:CLBK_SERVER_URL='http://127.0.0.1:8000'
npm run dev
```

Pada development, URL default adalah `http://127.0.0.1:8000`. HTTP hanya diterima untuk loopback pada build debug. Server jaringan kantor/internet menggunakan HTTPS dengan sertifikat yang dipercaya Windows. Pengecekan sertifikat tidak dinonaktifkan.

## Konfigurasi runtime

Urutan sumber alamat server:

1. Environment variable `CLBK_SERVER_URL`.
2. Berkas `server.json` di direktori konfigurasi aplikasi, biasanya `%APPDATA%\id.clbktoke.desktop\server.json` pada Windows.
3. Default localhost hanya untuk build debug.

Isi berkas mengikuti `server.example.json`. Nilai harus berupa origin, misalnya `https://toke.example.com`, tanpa path, query, fragment, atau kredensial. Build release tidak memiliki default server. Mengubah URL server tidak memerlukan perubahan kode; sesi login pada origin sebelumnya tidak dibawa ke origin baru.

Saat dibuka, aplikasi memeriksa `/up` tanpa mengikuti redirect dan menunggu maksimal delapan detik. Jika konfigurasi tidak valid atau server tidak dapat dihubungi, halaman koneksi lokal ditampilkan. Pengguna memeriksa jaringan lalu menutup dan membuka kembali aplikasi. Setelah halaman server terbuka, banner koneksi web memeriksa `/up` berkala.

Navigasi WebView hanya diizinkan ke origin server yang tepat. Jendela baru ditolak. Tidak ada capability native yang diberikan kepada konten remote, tidak ada plugin filesystem/shell, dan global Tauri API tidak disediakan. Pendekatan ini mengikuti [capability Tauri](https://v2.tauri.app/security/capabilities/) dan [WebviewWindowBuilder](https://docs.rs/tauri/latest/tauri/webview/struct.WebviewWindowBuilder.html).

## Build dan verifikasi

```powershell
npm run info
npm run build
cd src-tauri
cargo test
```

Bundling installer dinonaktifkan pada fondasi; `tauri build` menghasilkan executable. Icon aplikasi dasar tersedia di `src-tauri/icons`, dibuat dari logo vektor web yang sama. Installer dan penandatanganan disiapkan pada tahap desktop/deployment sesuai PRD.

Pemeriksaan fondasi menggunakan `npm run build -- --debug`, yang berhasil menghasilkan `src-tauri/target/debug/clbk-toke-desktop.exe`. Build debug menerima localhost development. Tiga pengujian Rust untuk validasi alamat dan pembatasan origin sudah lolos. Semua prasyarat ditandai tersedia oleh `npm run info`.

Pengujian manual yang masih diperlukan untuk menutup tahap 0: menjalankan executable, login/logout, persistensi cookie setelah aplikasi dibuka ulang, token CSRF, batas navigasi origin di WebView, dan halaman koneksi ketika server terputus. Kontrol interaksi aplikasi Windows tidak tersedia dalam sesi otomasi ini; hasil unit/build tidak dianggap sebagai bukti bahwa seluruh alur WebView sudah diperiksa.
