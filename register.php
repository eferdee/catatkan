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
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#f2f5ee">
<meta name="csrf" content="<?= e($csrf) ?>">
<title>Daftar — CatatKan</title>
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
        <h1>Buat akun baru</h1>
        <p>Mulai kelola keuanganmu dalam hitungan detik.</p>
      </header>

      <div class="form-alert" id="formAlert" role="alert" hidden></div>

      <form data-auth="register" novalidate>
        <div class="field">
          <span>Nama lengkap</span>
          <div class="input-wrap">
            <svg class="i"><use href="#i-user"/></svg>
            <input class="input" type="text" name="name" autocomplete="name" placeholder="Nama kamu" maxlength="60" required>
          </div>
          <em class="err" data-err="name"></em>
        </div>

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
            <input class="input" type="password" name="password" autocomplete="new-password" placeholder="Minimal 8 karakter" required>
            <button type="button" class="pw-toggle" data-toggle-pw aria-label="Tampilkan kata sandi"><svg class="i"><use href="#i-eye"/></svg></button>
          </div>
          <div class="meter" id="pwMeter" data-level="0" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
          <ul class="rules" id="pwRules">
            <li data-rule="len">Minimal 8 karakter</li>
            <li data-rule="letter">Mengandung huruf</li>
            <li data-rule="digit">Mengandung angka</li>
          </ul>
          <em class="err" data-err="password"></em>
        </div>

        <div class="field">
          <span>Ulangi kata sandi</span>
          <div class="input-wrap">
            <svg class="i"><use href="#i-lock"/></svg>
            <input class="input" type="password" name="confirm" autocomplete="new-password" placeholder="Ketik ulang kata sandi" required>
          </div>
          <em class="err" data-err="confirm"></em>
        </div>

        <button class="btn btn-primary btn-block" type="submit"><span class="btn-label">Daftar &amp; mulai</span><span class="spinner" aria-hidden="true"></span></button>
      </form>

      <p class="switch">Sudah punya akun? <a href="login.php">Masuk</a></p>
    </div>
  </main>
</div>
<script src="assets/js/auth.js?v=<?= filemtime(__DIR__ . '/assets/js/auth.js') ?>"></script>
</body>
</html>
