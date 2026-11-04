<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

api_init();
$uid    = require_auth();
$pdo    = db();
$action = (string) ($_GET['action'] ?? '');

switch ($action) {
    /* tambah atau ubah batas anggaran sebuah kategori */
    case 'save':
        $in       = require_post();
        $category = (string) ($in['category'] ?? '');
        $limit    = int_in($in, 'limit');
        $errors   = [];
        if (!isset(categories()['expense'][$category])) {
            $errors['category'] = 'Pilih kategori pengeluaran yang valid.';
        }
        if ($limit === null || $limit < 1000 || $limit > 999999999999) {
            $errors['limit'] = 'Batas anggaran minimal Rp 1.000.';
        }
        if ($errors) {
            json_fail('Periksa kembali isian Anda.', 422, $errors);
        }
        $pdo->prepare(
            'INSERT INTO budgets (user_id, category, monthly_limit) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE monthly_limit = VALUES(monthly_limit)'
        )->execute([$uid, $category, $limit]);
        json_out(['category' => $category, 'limit' => $limit]);

    case 'delete':
        $in       = require_post();
        $category = (string) ($in['category'] ?? '');
        $st = $pdo->prepare('DELETE FROM budgets WHERE user_id = ? AND category = ?');
        $st->execute([$uid, $category]);
        if ($st->rowCount() === 0) {
            json_fail('Anggaran tidak ditemukan.', 404);
        }
        json_out([]);

    default:
        json_fail('Aksi tidak dikenal.', 404);
}
