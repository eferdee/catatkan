<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

api_init();
$uid = require_auth();
require_post();
$pdo = db();

// Akun demo dipakai bersama banyak pengunjung, jadi tidak boleh dikosongkan.
$st = $pdo->prepare('SELECT is_demo FROM users WHERE id = ?');
$st->execute([$uid]);
$row = $st->fetch();
if (!$row) {
    logout_user();
    json_fail('Akun tidak ditemukan.', 401);
}
if ((int) $row['is_demo'] === 1) {
    json_fail('Akun demo dipakai bersama dan tidak bisa direset.', 403);
}

// Kosongkan semua data milik pengguna ini saja; akun (login) tetap ada.
$pdo->beginTransaction();
try {
    $pdo->prepare('DELETE FROM transactions WHERE user_id = ?')->execute([$uid]);
    $pdo->prepare('DELETE FROM budgets WHERE user_id = ?')->execute([$uid]);
    $pdo->prepare(
        "INSERT INTO goals (user_id, title, target_amount, saved_amount) VALUES (?, 'Dana darurat', 10000000, 0)
         ON DUPLICATE KEY UPDATE title = VALUES(title), target_amount = VALUES(target_amount), saved_amount = 0"
    )->execute([$uid]);
    $pdo->commit();
} catch (Throwable $t) {
    $pdo->rollBack();
    throw $t;
}
json_out([]);
