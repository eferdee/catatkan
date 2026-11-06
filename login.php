<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

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
<title>Masuk — CatatKan</title>
<script src="assets/js/theme.js?v=<?= filemtime(__DIR__ . '/assets/js/theme.js') ?>"></script>
<link rel="stylesheet" href="assets/css/fonts.css?v=<?= filemtime(__DIR__ . '/assets/css/fonts.css') ?>">
<link rel="stylesheet" href="assets/css/base.css?v=<?= filemtime(__DIR__ . '/assets/css/base.css') ?>">
<link rel="stylesheet" href="assets/css/auth.css?v=<?= filemtime(__DIR__ . '/assets/css/auth.css') ?>">
</head>
<body class="auth-body">
<?php include __DIR__ . '/includes/icons.php'; ?>
<div class="auth-shell">
  <?php include __DIR__ . '/includes/auth_brand.php'; ?>

  <main class="auth-main">
    <div class="auth-card">
      <a class="back-link" href="index.php"><svg class="i"><use href="#i-chev-left"/></svg>Kembali ke beranda</a>
      <header class="auth-head">
        <h1>Selamat datang kembali</h1>
        <p>Masuk untuk melihat ringkasan keuanganmu.</p>
      </header>

      <div class="form-alert" id="formAlert" role="alert" hidden></div>

      <form data-auth="login" novalidate>
        <div class="field">
          <span>Email</span>
          <div class="input-wrap">
            <svg class="i"><use href="#i-mail"/></svg>
            <input class="input" type="email" name="email" autocomplete="email" placeholder="nama@email.com" required>
          </div>
          <em class="err" data-err="email"></em>
        </div>

        <div class="field">
          <span>Kata sandi</span>
          <div class="input-wrap">
            <svg class="i"><use href="#i-lock"/></svg>
            <input class="input" type="password" name="password" autocomplete="current-password" placeholder="Masukkan kata sandi" required>
            <button type="button" class="pw-toggle" data-toggle-pw aria-label="Tampilkan kata sandi"><svg class="i"><use href="#i-eye"/></svg></button>
          </div>
          <em class="err" data-err="password"></em>
        </div>

        <button class="btn btn-primary btn-block" type="submit"><span class="btn-label">Masuk</span><span class="spinner" aria-hidden="true"></span></button>
      </form>

      <?php if ($demo): ?>
        <div class="divider"><span>atau</span></div>
        <button class="btn btn-ghost btn-block" type="button" id="demoBtn"><span class="btn-label"><svg class="i"><use href="#i-sparkle"/></svg>Coba akun demo</span><span class="spinner" aria-hidden="true"></span></button>
        <p class="hint">Akun demo berisi data contoh 12 bulan, tanpa perlu mendaftar.</p>
      <?php endif; ?>

      <p class="switch">Belum punya akun? <a href="register.php">Daftar gratis</a></p>
    </div>
  </main>
</div>
<script src="assets/js/auth.js?v=<?= filemtime(__DIR__ . '/assets/js/auth.js') ?>"></script>
</body>
</html>
