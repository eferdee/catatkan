<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

api_init();
$uid    = require_auth();
$pdo    = db();
$action = (string) ($_GET['action'] ?? '');

function read_goal(PDO $pdo, int $uid): array
{
    $st = $pdo->prepare('SELECT title, target_amount, saved_amount FROM goals WHERE user_id = ?');
    $st->execute([$uid]);
    $g = $st->fetch();
    $goal = ['title' => $g['title'], 'target' => (int) $g['target_amount'], 'saved' => (int) $g['saved_amount']];
    $goal['pct'] = $goal['target'] > 0 ? min(100, (int) round($goal['saved'] / $goal['target'] * 100)) : 0;
    return $goal;
}

switch ($action) {
    /* ubah nama, target, dan jumlah terkumpul */
    case 'save':
        $in     = require_post();
        $title  = clean_text($in['title'] ?? '');
        $target = int_in($in, 'target');
        $saved  = int_in($in, 'saved');
        $errors = [];
        if ($title === '' || mb_strlen($title) > 80) {
            $errors['title'] = 'Nama target wajib diisi (maks. 80 karakter).';
        }
        if ($target === null || $target < 1000 || $target > 999999999999) {
            $errors['target'] = 'Target minimal Rp 1.000.';
        }
        if ($saved === null || $saved < 0 || $saved > 999999999999) {
            $errors['saved'] = 'Jumlah terkumpul tidak valid.';
        }
        if ($errors) {
            json_fail('Periksa kembali isian Anda.', 422, $errors);
        }
        $pdo->prepare(
            'INSERT INTO goals (user_id, title, target_amount, saved_amount) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE title = VALUES(title), target_amount = VALUES(target_amount), saved_amount = VALUES(saved_amount)'
        )->execute([$uid, $title, $target, $saved]);
        json_out(['goal' => read_goal($pdo, $uid)]);

    /* tambah tabungan */
    case 'deposit':
        $in     = require_post();
        $amount = int_in($in, 'amount');
        if ($amount === null || $amount < 1 || $amount > 999999999999) {
            json_fail('Periksa kembali isian Anda.', 422, ['amount' => 'Masukkan jumlah lebih dari 0.']);
        }
        $pdo->prepare(
            'INSERT INTO goals (user_id, saved_amount) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE saved_amount = saved_amount + VALUES(saved_amount)'
        )->execute([$uid, $amount]);
        json_out(['goal' => read_goal($pdo, $uid)]);

    default:
        json_fail('Aksi tidak dikenal.', 404);
}
