# CatatKan

Aplikasi web pengelolaan keuangan pribadi untuk mencatat pemasukan dan pengeluaran, mengatur anggaran bulanan, dan memantau target tabungan.

## Tujuan

CatatKan dibuat supaya siapa pun bisa tahu ke mana uangnya pergi tanpa harus repot dengan spreadsheet. Pengguna cukup mencatat transaksi, lalu aplikasi merangkumnya menjadi gambaran keuangan yang mudah dibaca, mulai dari saldo dan arus kas, batas pengeluaran per kategori, sampai insight otomatis agar kebiasaan belanja lebih terkendali.

## Fitur

**Akun**
- Daftar dan masuk dengan validasi serta indikator kekuatan kata sandi
- Data tiap pengguna terpisah dan hanya bisa diakses oleh pemiliknya
- Keamanan dasar: kata sandi ter-hash, perlindungan CSRF, dan pembatasan percobaan login

**Dashboard**
- Ringkasan total saldo (bisa disembunyikan), pemasukan, dan pengeluaran bulan ini beserta perbandingan dengan periode yang sama bulan lalu
- Grafik arus kas interaktif untuk 6 atau 12 bulan terakhir
- Transaksi terbaru, insight keuangan otomatis, dan progres target tabungan

**Transaksi**
- Tambah, ubah, dan hapus transaksi pemasukan maupun pengeluaran dengan kategori
- Pencarian, filter berdasarkan jenis, kategori, dan bulan, serta paginasi
- Ekspor ke file CSV yang bisa dibuka di Excel atau Google Sheets

**Anggaran**
- Batas pengeluaran bulanan per kategori dengan indikator hampir habis dan terlampaui

**Analisis**
- Rasio tabungan, rata-rata pengeluaran harian, proyeksi pengeluaran akhir bulan, dan pengeluaran terbesar
- Tren 12 bulan, komposisi pengeluaran per kategori, dan merchant teratas

**Target tabungan**
- Tetapkan nama dan nominal target, lalu tambahkan tabungan kapan saja

**Pengaturan dan kenyamanan**
- Mode terang, gelap, atau otomatis mengikuti perangkat
- Tampilan responsif untuk desktop dan ponsel
- Hapus semua data untuk memulai lagi dari awal