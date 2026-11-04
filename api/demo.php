<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/demo.php';

api_init();
$uid = require_auth();
require_post();

// Muat data contoh ke akun sendiri — hanya jika akun masih kosong, supaya data asli tidak tercampur.
$pdo = db();
$st = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE user_id = ?');
$st->execute([$uid]);
if ((int) $st->fetchColumn() > 0) {
    json_fail('Data contoh hanya bisa dimuat pada akun yang masih kosong.', 409);
}
seed_demo_data($pdo, $uid);
json_out([]);
