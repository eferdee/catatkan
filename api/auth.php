<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/demo.php';

api_init();

const MAX_FAILS_PER_EMAIL = 5;
const MAX_FAILS_PER_IP    = 25;

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function login_locked(PDO $pdo, string $email, string $ip): bool
{
    $st = $pdo->prepare(
        'SELECT COALESCE(SUM(email = ?), 0) AS by_email, COALESCE(SUM(ip = ?), 0) AS by_ip
           FROM login_attempts WHERE attempted_at > (NOW() - INTERVAL 15 MINUTE)'
    );
    $st->execute([$email, $ip]);
    $r = $st->fetch();
    return (int) $r['by_email'] >= MAX_FAILS_PER_EMAIL || (int) $r['by_ip'] >= MAX_FAILS_PER_IP;
}

function record_login_failure(PDO $pdo, string $email, string $ip): void
{
    $pdo->prepare('INSERT INTO login_attempts (email, ip) VALUES (?, ?)')->execute([$email, $ip]);
    if (random_int(1, 20) === 1) {   // bersih-bersih sesekali
        $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
    }
}

function public_user(array $u): array
{
    return ['id' => (int) $u['id'], 'name' => (string) $u['name'], 'email' => (string) $u['email']];
}

$action = (string) ($_GET['action'] ?? '');

switch ($action) {
    /* ------------------------------ GET: sesi saat ini ------------------------------ */
    case 'me':
        $uid = require_auth();
        $st = db()->prepare('SELECT id, name, email FROM users WHERE id = ?');
        $st->execute([$uid]);
        $u = $st->fetch();
        if (!$u) {
            logout_user();
            json_fail('Akun tidak ditemukan.', 401);
        }
        json_out(['user' => public_user($u)]);

    /* --------------------------------- POST: daftar --------------------------------- */
    case 'register':
        $in = require_post();
        $name     = clean_text($in['name'] ?? '');
        $email    = strtolower(trim((string) ($in['email'] ?? '')));
        $password = (string) ($in['password'] ?? '');
        $errors   = [];

        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
            $errors['name'] = 'Nama harus 2–60 karakter.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $errors['email'] = 'Format email tidak valid.';
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'Kata sandi minimal 8 karakter.';
        } elseif (strlen($password) > 72) {
            $errors['password'] = 'Kata sandi maksimal 72 karakter.';
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            $errors['password'] = 'Gunakan kombinasi huruf dan angka.';
        }
        if ($errors) {
            json_fail('Periksa kembali isian Anda.', 422, $errors);
        }

        $pdo = db();
        $st = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
        $st->execute([$email]);
        if ($st->fetchColumn()) {
            json_fail('Email sudah terdaftar.', 409, ['email' => 'Email ini sudah terdaftar. Coba masuk.']);
        }

        try {
            $pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)')
                ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {   // balapan: email baru saja didaftarkan
                json_fail('Email sudah terdaftar.', 409, ['email' => 'Email ini sudah terdaftar. Coba masuk.']);
            }
            throw $ex;
        }
        $userId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO goals (user_id) VALUES (?)')->execute([$userId]);

        login_user($userId);
        json_out(['user' => ['id' => $userId, 'name' => $name, 'email' => $email], 'redirect' => 'dashboard.php'], 201);

    /* ---------------------------------- POST: masuk ---------------------------------- */
    case 'login':
        $in = require_post();
        $email    = strtolower(trim((string) ($in['email'] ?? '')));
        $password = (string) ($in['password'] ?? '');
        if ($email === '' || $password === '') {
            json_fail('Email dan kata sandi wajib diisi.', 422, array_filter([
                'email'    => $email === '' ? 'Email wajib diisi.' : null,
                'password' => $password === '' ? 'Kata sandi wajib diisi.' : null,
            ]));
        }

        $pdo = db();
        $ip  = client_ip();
        if (login_locked($pdo, $email, $ip)) {
            json_fail('Terlalu banyak percobaan masuk. Coba lagi dalam 15 menit.', 429);
        }

        $st = $pdo->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ?');
        $st->execute([$email]);
        $u = $st->fetch();

        if (!$u) {
            password_hash($password, PASSWORD_DEFAULT);   // samakan waktu respons agar email tidak bisa ditebak
            record_login_failure($pdo, $email, $ip);
            json_fail('Email atau kata sandi salah.', 401);
        }
        if (!password_verify($password, $u['password_hash'])) {
            record_login_failure($pdo, $email, $ip);
            json_fail('Email atau kata sandi salah.', 401);
        }

        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $u['id']]);
        }
        $pdo->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$email]);

        login_user((int) $u['id']);
        json_out(['user' => public_user($u), 'redirect' => 'dashboard.php']);

    /* -------------------------------- POST: akun demo -------------------------------- */
    case 'demo':
        require_post();
        if (!cfg('demo_enabled')) {
            json_fail('Akun demo dinonaktifkan.', 403);
        }
        $pdo = db();
        $st = $pdo->prepare('SELECT id, name, email FROM users WHERE email = ?');
        $st->execute([DEMO_EMAIL]);
        $u = $st->fetch();
        if (!$u) {
            $pdo->prepare('INSERT INTO users (name, email, password_hash, is_demo) VALUES (?, ?, ?, 1)')
                ->execute(['Farid Demo', DEMO_EMAIL, password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT)]);
            $u = ['id' => (int) $pdo->lastInsertId(), 'name' => 'Farid Demo', 'email' => DEMO_EMAIL];
        }
        $st = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE user_id = ?');
        $st->execute([$u['id']]);
        if ((int) $st->fetchColumn() === 0) {
            seed_demo_data($pdo, (int) $u['id']);
        }
        login_user((int) $u['id']);
        json_out(['user' => public_user($u), 'redirect' => 'dashboard.php']);

    /* ---------------------------------- POST: keluar ---------------------------------- */
    case 'logout':
        require_post();
        logout_user();
        json_out(['redirect' => 'login.php']);

    default:
        json_fail('Aksi tidak dikenal.', 404);
}
