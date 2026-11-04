<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

api_init();
$uid    = require_auth();
$pdo    = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? ($method === 'GET' ? 'list' : ''));

/** Bangun klausa WHERE dari query string (q, type, category, month). */
function tx_filters(int $uid): array
{
    $where  = ['user_id = :uid'];
    $params = [':uid' => $uid];

    $q = clean_text($_GET['q'] ?? '');
    if ($q !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $where[] = '(name LIKE :q1 OR category LIKE :q2 OR note LIKE :q3)';
        $params[':q1'] = $params[':q2'] = $params[':q3'] = $like;
    }
    $type = (string) ($_GET['type'] ?? '');
    if ($type === 'income' || $type === 'expense') {
        $where[] = 'type = :type';
        $params[':type'] = $type;
    }
    $category = (string) ($_GET['category'] ?? '');
    if ($category !== '') {
        $where[] = 'category = :cat';
        $params[':cat'] = $category;
    }
    $month = (string) ($_GET['month'] ?? '');
    if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $month)) {
        $start = new DateTimeImmutable($month . '-01');
        $where[] = 'tx_date >= :from AND tx_date < :to';
        $params[':from'] = $start->format('Y-m-d');
        $params[':to']   = $start->modify('+1 month')->format('Y-m-d');
    }
    return [implode(' AND ', $where), $params];
}

/** Validasi input transaksi. Mengembalikan [data bersih, daftar error]. */
function validate_tx(array $in): array
{
    $errors   = [];
    $name     = clean_text($in['name'] ?? '');
    $type     = (string) ($in['type'] ?? '');
    $category = (string) ($in['category'] ?? '');
    $amount   = int_in($in, 'amount');
    $date     = (string) ($in['date'] ?? '');
    $note     = clean_text($in['note'] ?? '');

    if ($name === '' || mb_strlen($name) > 80) {
        $errors['name'] = 'Nama transaksi wajib diisi (maks. 80 karakter).';
    }
    if (!in_array($type, ['income', 'expense'], true)) {
        $errors['type'] = 'Jenis transaksi tidak valid.';
    } elseif (!isset(categories()[$type][$category])) {
        $errors['category'] = 'Kategori tidak valid.';
    }
    if ($amount === null || $amount < 1 || $amount > 999999999999) {
        $errors['amount'] = 'Jumlah harus berupa angka lebih dari 0.';
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) {
        $errors['date'] = 'Tanggal tidak valid.';
    } elseif ($d > new DateTimeImmutable('tomorrow') || $d < new DateTimeImmutable('2000-01-01')) {
        $errors['date'] = 'Tanggal tidak boleh di masa depan.';
    }
    if (mb_strlen($note) > 255) {
        $errors['note'] = 'Catatan maksimal 255 karakter.';
    }
    return [[
        'name' => $name, 'type' => $type, 'category' => $category,
        'amount' => (int) $amount, 'date' => $date, 'note' => $note === '' ? null : $note,
    ], $errors];
}

function find_tx(PDO $pdo, int $uid, int $id): ?array
{
    $st = $pdo->prepare('SELECT id, name, amount, type, category, tx_date, note FROM transactions WHERE id = ? AND user_id = ?');
    $st->execute([$id, $uid]);
    return $st->fetch() ?: null;
}

/** Cegah CSV/formula injection saat dibuka di Excel. */
function csv_cell(string $v): string
{
    return ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) ? "'" . $v : $v;
}

switch ($action) {
    /* ---------------------------------- daftar ---------------------------------- */
    case 'list':
        if ($method !== 'GET') {
            json_fail('Metode tidak diizinkan.', 405);
        }
        [$where, $params] = tx_filters($uid);
        $per  = min(50, max(1, (int) ($_GET['per_page'] ?? 10)));
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $st = $pdo->prepare(
            "SELECT COUNT(*) AS c,
                    COALESCE(SUM(CASE WHEN type = 'income'  THEN amount END), 0) AS sum_in,
                    COALESCE(SUM(CASE WHEN type = 'expense' THEN amount END), 0) AS sum_out
               FROM transactions WHERE $where"
        );
        $st->execute($params);
        $agg   = $st->fetch();
        $total = (int) $agg['c'];
        $pages = max(1, (int) ceil($total / $per));
        $page  = min($page, $pages);

        $st = $pdo->prepare(
            "SELECT id, name, amount, type, category, tx_date, note FROM transactions
              WHERE $where ORDER BY tx_date DESC, id DESC LIMIT :lim OFFSET :off"
        );
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->bindValue(':lim', $per, PDO::PARAM_INT);
        $st->bindValue(':off', ($page - 1) * $per, PDO::PARAM_INT);
        $st->execute();

        json_out([
            'items'   => array_map('tx_row', $st->fetchAll()),
            'total'   => $total,
            'page'    => $page,
            'pages'   => $pages,
            'per_page' => $per,
            'sum_income'  => (int) $agg['sum_in'],
            'sum_expense' => (int) $agg['sum_out'],
        ]);

    /* ----------------------------------- ekspor ----------------------------------- */
    case 'export':
        if ($method !== 'GET') {
            json_fail('Metode tidak diizinkan.', 405);
        }
        [$where, $params] = tx_filters($uid);
        $st = $pdo->prepare("SELECT id, name, amount, type, category, tx_date, note FROM transactions WHERE $where ORDER BY tx_date DESC, id DESC");
        $st->execute($params);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="catatkan-transaksi-' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");   // BOM agar Excel membaca UTF-8 dengan benar
        fputcsv($out, ['Tanggal', 'Nama', 'Jenis', 'Kategori', 'Jumlah (Rp)', 'Catatan'], ',', '"', '\\');
        foreach ($st as $r) {
            fputcsv($out, [
                $r['tx_date'],
                csv_cell((string) $r['name']),
                $r['type'] === 'income' ? 'Pemasukan' : 'Pengeluaran',
                $r['category'],
                (int) $r['amount'],
                csv_cell((string) ($r['note'] ?? '')),
            ], ',', '"', '\\');
        }
        fclose($out);
        exit;

    /* ----------------------------------- tambah ----------------------------------- */
    case 'create':
        $in = require_post();
        [$c, $errors] = validate_tx($in);
        if ($errors) {
            json_fail('Periksa kembali isian Anda.', 422, $errors);
        }
        $pdo->prepare('INSERT INTO transactions (user_id, name, amount, type, category, tx_date, note) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$uid, $c['name'], $c['amount'], $c['type'], $c['category'], $c['date'], $c['note']]);
        json_out(['item' => tx_row(find_tx($pdo, $uid, (int) $pdo->lastInsertId()))], 201);

    /* ------------------------------------ ubah ------------------------------------ */
    case 'update':
        $in = require_post();
        $id = int_in($in, 'id');
        if ($id === null || find_tx($pdo, $uid, $id) === null) {
            json_fail('Transaksi tidak ditemukan.', 404);
        }
        [$c, $errors] = validate_tx($in);
        if ($errors) {
            json_fail('Periksa kembali isian Anda.', 422, $errors);
        }
        $pdo->prepare('UPDATE transactions SET name = ?, amount = ?, type = ?, category = ?, tx_date = ?, note = ? WHERE id = ? AND user_id = ?')
            ->execute([$c['name'], $c['amount'], $c['type'], $c['category'], $c['date'], $c['note'], $id, $uid]);
        json_out(['item' => tx_row(find_tx($pdo, $uid, $id))]);

    /* ------------------------------------ hapus ------------------------------------ */
    case 'delete':
        $in = require_post();
        $id = int_in($in, 'id');
        if ($id === null) {
            json_fail('Transaksi tidak ditemukan.', 404);
        }
        $st = $pdo->prepare('DELETE FROM transactions WHERE id = ? AND user_id = ?');
        $st->execute([$id, $uid]);
        if ($st->rowCount() === 0) {
            json_fail('Transaksi tidak ditemukan.', 404);
        }
        json_out([]);

    default:
        json_fail('Aksi tidak dikenal.', 404);
}
