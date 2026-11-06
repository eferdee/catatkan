<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

start_session();
security_headers();
if (current_user_id() !== null) {
    header('Location: dashboard.php');
    exit;
}
$csrf = csrf_token();
$demo = (bool) cfg('demo_enabled');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#f2f5ee">
<meta name="csrf" content="<?= e($csrf) ?>">
<meta name="description" content="CatatKan: catat pemasukan dan pengeluaran, atur anggaran bulanan, dan capai target tabunganmu dalam satu dasbor yang tenang.">
<title>CatatKan — Uangmu, lebih terkendali</title>
<script src="assets/js/theme.js?v=<?= filemtime(__DIR__ . '/assets/js/theme.js') ?>"></script>
<script>document.documentElement.classList.add('js')</script>
<link rel="stylesheet" href="assets/css/fonts.css?v=<?= filemtime(__DIR__ . '/assets/css/fonts.css') ?>">
<link rel="stylesheet" href="assets/css/base.css?v=<?= filemtime(__DIR__ . '/assets/css/base.css') ?>">
<link rel="stylesheet" href="assets/css/landing.css?v=<?= filemtime(__DIR__ . '/assets/css/landing.css') ?>">
</head>
<body>
<?php include __DIR__ . '/includes/icons.php'; ?>

<header class="nav">
  <div class="wrap nav-in">
    <a class="l-brand" href="./"><span class="brand-mark"><svg class="i"><use href="#i-arrow-up-right"/></svg></span><span>Catat<b>Kan</b></span></a>
    <nav class="nav-links" aria-label="Navigasi utama">
      <a href="#fitur">Fitur</a><a href="#cara">Cara kerja</a><a href="#aman">Keamanan</a><a href="#faq">FAQ</a>
    </nav>
    <div class="nav-cta">
      <button class="icon-btn" id="themeBtn" type="button" aria-label="Ganti tema">
        <svg class="i ico-moon"><use href="#i-moon"/></svg><svg class="i ico-sun"><use href="#i-sun"/></svg>
      </button>
      <a class="btn btn-ghost btn-sm" href="login.php">Masuk</a>
      <a class="btn btn-primary btn-sm" href="register.php">Daftar<span class="hide-sm">&nbsp;gratis</span></a>
    </div>
  </div>
</header>

<main>
  <section class="hero wrap">
    <div class="hero-copy">
      <span class="pill"><svg class="i"><use href="#i-sparkle"/></svg>Pencatat keuangan pribadi</span>
      <h1>Tahu ke mana uangmu pergi, <em>tanpa pusing.</em></h1>
      <p>Catat pemasukan dan pengeluaran, atur anggaran bulanan, lalu capai target tabunganmu. Semua dirangkum otomatis dalam satu dasbor yang tenang.</p>
      <div class="hero-cta">
        <a class="btn btn-primary" href="register.php">Mulai gratis<svg class="i"><use href="#i-arrow-up-right"/></svg></a>
        <?php if ($demo): ?>
        <button class="btn btn-ghost" type="button" data-demo><span class="btn-label"><svg class="i"><use href="#i-sparkle"/></svg>Coba akun demo</span><span class="spinner" aria-hidden="true"></span></button>
        <?php endif; ?>
      </div>
      <p class="form-alert demo-err" id="demoErr" role="alert" hidden></p>
      <ul class="trust">
        <li><svg class="i"><use href="#i-check"/></svg>Gratis</li>
        <li><svg class="i"><use href="#i-check"/></svg>Mode gelap</li>
        <li><svg class="i"><use href="#i-check"/></svg>Ekspor CSV</li>
      </ul>
    </div>

    <div class="stage" aria-hidden="true">
      <div class="g-card g-balance">
        <small>Total saldo</small><b>Rp 12.850.000</b>
        <div class="bars"><i style="--h:38%"></i><i style="--h:56%"></i><i style="--h:44%"></i><i style="--h:70%"></i><i style="--h:52%"></i><i style="--h:84%"></i><i style="--h:62%"></i><i style="--h:100%"></i></div>
      </div>
      <div class="g-card g-budget">
        <div class="g-row"><span>🛍️ Belanja</span><span>97%</span></div>
        <div class="prog"><i style="--w:97%"></i></div>
      </div>
      <div class="g-card g-tx">
        <span class="g-ico">☕</span>
        <div><b>Kopi Kenangan</b><small>Makanan &amp; Minuman</small></div>
        <em>− Rp 35.000</em>
      </div>
      <div class="g-card g-goal">
        <div class="g-row"><span>🎯 Liburan Bali</span><span class="ok">64%</span></div>
        <div class="prog ok"><i style="--w:64%"></i></div>
      </div>
    </div>
  </section>

  <section class="wrap sec" id="fitur">
    <div class="sec-head rv">
      <span class="eyebrow">Fitur</span>
      <h2>Semua yang kamu butuhkan, tanpa yang tidak perlu.</h2>
      <p>Dari mencatat transaksi sampai membaca pola belanja, semuanya ada di satu tempat.</p>
    </div>
    <div class="grid">
      <article class="card rv"><span class="f-ico"><svg class="i"><use href="#i-home"/></svg></span><h3>Dasbor ringkas</h3><p>Saldo, pemasukan, dan pengeluaran bulan ini dibandingkan dengan bulan lalu, lengkap dengan grafik arus kas 6 atau 12 bulan.</p></article>
      <article class="card rv"><span class="f-ico"><svg class="i"><use href="#i-swap"/></svg></span><h3>Transaksi mudah</h3><p>Tambah, ubah, hapus, cari, dan filter berdasarkan jenis, kategori, atau bulan. Ekspor ke CSV untuk Excel atau Google Sheets.</p></article>
      <article class="card rv"><span class="f-ico"><svg class="i"><use href="#i-sliders"/></svg></span><h3>Anggaran per kategori</h3><p>Tetapkan batas bulanan dan dapatkan penanda saat anggaran hampir habis atau sudah terlampaui.</p></article>
      <article class="card rv"><span class="f-ico"><svg class="i"><use href="#i-trend"/></svg></span><h3>Analisis &amp; insight</h3><p>Rasio tabungan, rata-rata pengeluaran harian, proyeksi akhir bulan, hingga merchant teratas, dibaca otomatis untukmu.</p></article>
      <article class="card rv"><span class="f-ico"><svg class="i"><use href="#i-target"/></svg></span><h3>Target tabungan</h3><p>Tentukan nama dan nominal target, lalu tambahkan tabungan kapan saja dan pantau progresnya.</p></article>
      <article class="card rv"><span class="f-ico"><svg class="i"><use href="#i-moon"/></svg></span><h3>Nyaman di mana saja</h3><p>Mode terang, gelap, atau otomatis mengikuti perangkat, dengan tampilan responsif untuk desktop dan ponsel.</p></article>
    </div>
  </section>

  <section class="wrap sec" id="cara">
    <div class="sec-head rv"><span class="eyebrow">Cara kerja</span><h2>Tiga langkah, lalu tinggal catat.</h2></div>
    <ol class="steps">
      <li class="rv"><b>1</b><h3>Buat akun</h3><p>Daftar dalam hitungan detik, atau langsung coba akun demo berisi data contoh 12 bulan.</p></li>
      <li class="rv"><b>2</b><h3>Catat transaksi</h3><p>Masukkan pemasukan dan pengeluaranmu, lalu pilih kategori yang sesuai.</p></li>
      <li class="rv"><b>3</b><h3>Pantau &amp; atur</h3><p>Lihat ringkasan, atur anggaran, dan kejar target tabunganmu dari dasbor.</p></li>
    </ol>
  </section>

  <section class="wrap sec" id="aman">
    <div class="secure rv">
      <div>
        <span class="eyebrow">Keamanan</span>
        <h2>Datamu milikmu.</h2>
        <p>Data tiap pengguna terpisah dan hanya bisa diakses oleh pemiliknya. Kamu juga bisa menghapus semua data kapan saja untuk memulai dari awal.</p>
      </div>
      <ul>
        <li><svg class="i"><use href="#i-lock"/></svg>Kata sandi ter-hash</li>
        <li><svg class="i"><use href="#i-lock"/></svg>Perlindungan CSRF</li>
        <li><svg class="i"><use href="#i-lock"/></svg>Pembatasan percobaan login</li>
      </ul>
    </div>
  </section>

  <section class="wrap sec" id="faq">
    <div class="sec-head rv"><span class="eyebrow">FAQ</span><h2>Pertanyaan yang sering muncul</h2></div>
    <div class="faq rv">
      <details><summary>Apakah saya perlu mendaftar untuk mencoba?</summary><p>Tidak. Gunakan akun demo untuk menjelajah dasbor dengan data contoh 12 bulan tanpa mendaftar.</p></details>
      <details><summary>Bisakah data diekspor?</summary><p>Bisa. Semua transaksi dapat diekspor ke file CSV yang terbuka di Excel maupun Google Sheets.</p></details>
      <details><summary>Apakah nyaman dipakai di ponsel?</summary><p>Ya. Tampilannya responsif, dan tersedia mode gelap agar nyaman di mata.</p></details>
      <details><summary>Bagaimana kalau saya ingin mulai dari nol?</summary><p>Di pengaturan ada opsi untuk menghapus semua data, sehingga kamu bisa mulai lagi dari awal.</p></details>
    </div>
  </section>

  <section class="wrap sec">
    <div class="cta rv">
      <h2>Siap mengatur uangmu <em>dengan tenang?</em></h2>
      <p>Mulai catat hari ini. Gratis dan tanpa ribet.</p>
      <div class="hero-cta center">
        <a class="btn btn-lime" href="register.php">Daftar gratis</a>
        <?php if ($demo): ?>
        <button class="btn btn-outline" type="button" data-demo><span class="btn-label">Coba akun demo</span><span class="spinner" aria-hidden="true"></span></button>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<footer class="foot wrap">
  <a class="l-brand" href="./"><span class="brand-mark"><svg class="i"><use href="#i-arrow-up-right"/></svg></span><span>Catat<b>Kan</b></span></a>
  <p>© <?= date('Y') ?> CatatKan. Pengelolaan keuangan pribadi.</p>
  <div><a href="login.php">Masuk</a><a href="register.php">Daftar</a></div>
</footer>

<script src="assets/js/landing.js?v=<?= filemtime(__DIR__ . '/assets/js/landing.js') ?>"></script>
</body>
</html>
