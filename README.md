# CatatKan — Aplikasi Pengelolaan Pengeluaran (Full Stack)

Aplikasi web untuk mencatat pemasukan & pengeluaran, mengatur anggaran bulanan, memantau target tabungan,
dan melihat analisis keuangan pribadi — lengkap dengan **sistem daftar & masuk (login)**.

**Stack:** PHP 8 (PDO) · MySQL/MariaDB · HTML/CSS/JavaScript murni (tanpa framework/library — grafik SVG dibuat sendiri)

## Fitur

**Autentikasi & keamanan**
- Daftar & masuk dengan validasi di sisi klien **dan** server, indikator kekuatan kata sandi
- Kata sandi di-hash dengan `password_hash()` (bcrypt/argon), `password_needs_rehash()` otomatis
- Sesi aman: cookie `HttpOnly` + `SameSite=Lax`, `session_regenerate_id()` saat login, mode strict
- Proteksi **CSRF** (token di header `X-CSRF-Token`) pada semua request yang mengubah data
- Pembatasan percobaan login (5 gagal/email atau 25 gagal/IP dalam 15 menit → diblokir sementara)
- Seluruh query memakai **prepared statements**; output di-escape (anti-XSS); ekspor CSV anti formula-injection
- Setiap data dibatasi per pengguna (`user_id`) — tidak ada akses lintas akun
- Menu Pengaturan: ekspor CSV dan **Hapus semua data** (mengosongkan akun kembali ke awal)
- Tombol **"Coba akun demo"** berisi data contoh 12 bulan (cocok untuk reviewer portofolio)

**Aplikasi**
- Dashboard: saldo (bisa disembunyikan), pemasukan/pengeluaran + sparkline 14 hari, grafik arus kas interaktif, anggaran, transaksi terbaru, insight otomatis, target tabungan
- Transaksi: tambah / ubah / hapus, pencarian, filter jenis · kategori · bulan, paginasi, **ekspor CSV**
- Anggaran per kategori dengan indikator hampir habis / terlampaui
- Analisis: rasio tabungan, rata-rata harian, proyeksi, tren 12 bulan, donut kategori, merchant teratas
- Mode terang / gelap / otomatis, responsif (navigasi bawah + bottom-sheet di ponsel), pintasan keyboard `N`

## Struktur

```
catatkan/
├── index.php              # pengarah: login / dashboard
├── login.php, register.php, dashboard.php
├── api/                   # endpoint JSON
│   ├── auth.php           #  me · register · login · demo · logout
│   ├── transactions.php   #  list · create · update · delete · export
│   ├── summary.php        #  data dashboard & analisis
│   ├── budgets.php        #  save · delete
│   ├── goal.php           #  save · deposit
│   └── reset.php          #  hapus semua data milik pengguna
├── includes/              # bootstrap, config, kategori, ikon (diblokir dari akses web)
├── database/schema.sql
└── assets/css · assets/js
```

## Menjalankan di komputer (XAMPP / Laragon / php -S)

1. Buat database dan import skema: buka phpMyAdmin → tab **Import** → pilih `database/schema.sql`
   (atau `mysql -u root -p < database/schema.sql`).
2. Salin `includes/config.local.sample.php` → `includes/config.local.php`, lalu isi kredensial database.
3. Jalankan: letakkan folder di `htdocs`, atau `php -S localhost:8000` dari dalam folder proyek.
4. Buka `http://localhost:8000` → daftar akun baru, atau klik **Coba akun demo**.

Kebutuhan: PHP ≥ 8.1 dengan ekstensi `pdo_mysql` dan `mbstring`; MySQL 5.7+ / MariaDB 10.3+.

## Deploy ke hosting gratis (mis. InfinityFree)

1. Buat database dari panel hosting. Di `schema.sql`, **hapus dua baris** `CREATE DATABASE` dan `USE`, lalu import lewat phpMyAdmin.
2. Isi `includes/config.local.php` dengan host/nama/user/password dari panel (host biasanya `sqlXXX.infinityfree.com`).
3. Upload seluruh isi folder ke `htdocs` lewat File Manager/FTP.
4. Pastikan situs berjalan di **HTTPS** agar cookie sesi bertanda `Secure`.

> Untuk portofolio publik: pertimbangkan `demo_enabled => false` jika tidak ingin akun demo dipakai bersama banyak orang
> (data demo dibagi satu akun; pengunjung bisa mengubahnya).

## Catatan desain

- Nominal disimpan sebagai `BIGINT` rupiah penuh (tanpa desimal) agar bebas error pembulatan.
- Perbandingan "dari bulan lalu" memakai **periode yang sama** (hari 1–N) agar adil di awal bulan.
- Pengeluaran kategori *Tabungan* tidak dihitung sebagai "belanja terbesar" pada insight & analisis.
- Kategori bersifat tetap (`includes/categories.php`) — mudah dikembangkan menjadi kategori buatan pengguna.

## Ide pengembangan

Kategori kustom · transaksi berulang · unggah struk · multi-dompet · impor CSV · reset kata sandi via email · pengujian otomatis (PHPUnit / Playwright).
