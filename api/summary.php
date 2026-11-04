<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

api_init();
$uid = require_auth();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    json_fail('Metode tidak diizinkan.', 405);
}
$pdo = db();

function pct_change(int $now, int $prev): ?float
{
    return $prev > 0 ? round(($now - $prev) / $prev * 100, 1) : null;
}

$today       = new DateTimeImmutable('today');
$thisStart   = $today->modify('first day of this month');
$nextStart   = $thisStart->modify('+1 month');
$months      = 12;
$rangeStart  = $thisStart->modify('-' . ($months - 1) . ' months');
$daysInMonth = (int) $today->format('t');
$elapsed     = (int) $today->format('j');
$ymd         = static fn (DateTimeImmutable $d): string => $d->format('Y-m-d');

/* ---- pengguna ---- */
$st = $pdo->prepare('SELECT name, email FROM users WHERE id = ?');
$st->execute([$uid]);
$user = $st->fetch();
if (!$user) {
    logout_user();
    json_fail('Akun tidak ditemukan.', 401);
}

/* ---- saldo keseluruhan ---- */
$st = $pdo->prepare(
    "SELECT COUNT(*) AS c,
            COALESCE(SUM(CASE WHEN type = 'income'  THEN amount END), 0) AS sum_in,
            COALESCE(SUM(CASE WHEN type = 'expense' THEN amount END), 0) AS sum_out
       FROM transactions WHERE user_id = ?"
);
$st->execute([$uid]);
$all     = $st->fetch();
$txCount = (int) $all['c'];
$balance = (int) $all['sum_in'] - (int) $all['sum_out'];

/* ---- arus kas 12 bulan ---- */
$st = $pdo->prepare(
    "SELECT DATE_FORMAT(tx_date, '%Y-%m') AS ym, type, SUM(amount) AS s
       FROM transactions WHERE user_id = ? AND tx_date >= ? AND tx_date < ?
      GROUP BY ym, type"
);
$st->execute([$uid, $ymd($rangeStart), $ymd($nextStart)]);
$agg = [];
foreach ($st as $r) {
    $agg[$r['ym']][$r['type']] = (int) $r['s'];
}
$cashflow = [];
for ($i = 0; $i < $months; $i++) {
    $d  = $rangeStart->modify("+$i months");
    $k  = $d->format('Y-m');
    $cashflow[] = [
        'key'     => $k,
        'label'   => month_short((int) $d->format('n')),
        'income'  => $agg[$k]['income'] ?? 0,
        'expense' => $agg[$k]['expense'] ?? 0,
    ];
}
$thisKey = $thisStart->format('Y-m');
$lastKey = $thisStart->modify('-1 month')->format('Y-m');
$incThis = $agg[$thisKey]['income'] ?? 0;
$expThis = $agg[$thisKey]['expense'] ?? 0;
$incLast = $agg[$lastKey]['income'] ?? 0;
$expLast = $agg[$lastKey]['expense'] ?? 0;
$net     = $incThis - $expThis;

/* Pembanding yang adil: bulan lalu pada rentang hari yang sama (hari 1..N), bukan sebulan penuh. */
$lastStart  = $thisStart->modify('-1 month');
$lastCutoff = $lastStart->modify('+' . min($elapsed, (int) $lastStart->format('t')) . ' days');
$st = $pdo->prepare(
    'SELECT type, SUM(amount) AS s FROM transactions WHERE user_id = ? AND tx_date >= ? AND tx_date < ? GROUP BY type'
);
$st->execute([$uid, $ymd($lastStart), $ymd($lastCutoff)]);
$sameIn = 0;
$sameOut = 0;
foreach ($st as $r) {
    if ($r['type'] === 'income') {
        $sameIn = (int) $r['s'];
    } else {
        $sameOut = (int) $r['s'];
    }
}

$balancePrev  = $balance - $net;
$balanceDelta = $balancePrev > 0 ? round(($balance - $balancePrev) / $balancePrev * 100, 1) : null;

/* ---- pengeluaran per kategori bulan ini ---- */
$st = $pdo->prepare(
    "SELECT category, SUM(amount) AS s, COUNT(*) AS c FROM transactions
      WHERE user_id = ? AND type = 'expense' AND tx_date >= ? AND tx_date < ?
      GROUP BY category ORDER BY s DESC"
);
$st->execute([$uid, $ymd($thisStart), $ymd($nextStart)]);
$cats     = [];
$spentMap = [];
foreach ($st as $r) {
    $amount = (int) $r['s'];
    $meta   = categories()['expense'][$r['category']] ?? ['emoji' => '📦', 'color' => '#93a09a'];
    $spentMap[$r['category']] = $amount;
    $cats[] = [
        'category' => $r['category'], 'emoji' => $meta['emoji'], 'color' => $meta['color'],
        'amount'   => $amount, 'count' => (int) $r['c'],
        'pct'      => $expThis > 0 ? round($amount / $expThis * 100, 1) : 0,
    ];
}

/* ---- anggaran ---- */
$st = $pdo->prepare('SELECT category, monthly_limit FROM budgets WHERE user_id = ?');
$st->execute([$uid]);
$budgets = [];
foreach ($st as $r) {
    $limit = (int) $r['monthly_limit'];
    $spent = $spentMap[$r['category']] ?? 0;
    $meta  = categories()['expense'][$r['category']] ?? ['emoji' => '📦', 'color' => '#93a09a'];
    $budgets[] = [
        'category' => $r['category'], 'emoji' => $meta['emoji'], 'color' => $meta['color'],
        'limit'    => $limit, 'spent' => $spent,
        'pct'      => $limit > 0 ? (int) round($spent / $limit * 100) : 0,
    ];
}
usort($budgets, static fn ($a, $b) => $b['pct'] <=> $a['pct']);

/* ---- merchant teratas ---- */
$st = $pdo->prepare(
    "SELECT name, SUM(amount) AS s, COUNT(*) AS c FROM transactions
      WHERE user_id = ? AND type = 'expense' AND category <> 'Tabungan' AND tx_date >= ? AND tx_date < ?
      GROUP BY name ORDER BY s DESC LIMIT 5"
);
$st->execute([$uid, $ymd($thisStart), $ymd($nextStart)]);
$topMerchants = array_map(static fn ($r) => ['name' => $r['name'], 'amount' => (int) $r['s'], 'count' => (int) $r['c']], $st->fetchAll());

/* ---- sparkline 14 hari ---- */
$sparkStart = $today->modify('-13 days');
$st = $pdo->prepare(
    'SELECT tx_date AS d, type, SUM(amount) AS s FROM transactions
      WHERE user_id = ? AND tx_date >= ? AND tx_date <= ? GROUP BY d, type'
);
$st->execute([$uid, $ymd($sparkStart), $ymd($today)]);
$daily = [];
foreach ($st as $r) {
    $daily[$r['d']][$r['type']] = (int) $r['s'];
}
$spark = ['income' => [], 'expense' => []];
for ($i = 0; $i < 14; $i++) {
    $k = $sparkStart->modify("+$i days")->format('Y-m-d');
    $spark['income'][]  = $daily[$k]['income'] ?? 0;
    $spark['expense'][] = $daily[$k]['expense'] ?? 0;
}

/* ---- pengeluaran terbesar bulan ini ---- */
$st = $pdo->prepare(
    "SELECT name, amount, tx_date, category FROM transactions
      WHERE user_id = ? AND type = 'expense' AND category <> 'Tabungan' AND tx_date >= ? AND tx_date < ?
      ORDER BY amount DESC, id DESC LIMIT 1"
);
$st->execute([$uid, $ymd($thisStart), $ymd($nextStart)]);
$big = $st->fetch();
$biggest = $big ? ['name' => $big['name'], 'amount' => (int) $big['amount'], 'date' => $big['tx_date'], 'category' => $big['category']] : null;

/* ---- transaksi terbaru ---- */
$st = $pdo->prepare('SELECT id, name, amount, type, category, tx_date, note FROM transactions WHERE user_id = ? ORDER BY tx_date DESC, id DESC LIMIT 5');
$st->execute([$uid]);
$recent = array_map('tx_row', $st->fetchAll());

/* ---- target tabungan ---- */
$st = $pdo->prepare('SELECT title, target_amount, saved_amount FROM goals WHERE user_id = ?');
$st->execute([$uid]);
$g = $st->fetch();
$goal = $g
    ? ['title' => $g['title'], 'target' => (int) $g['target_amount'], 'saved' => (int) $g['saved_amount']]
    : ['title' => 'Dana darurat', 'target' => 10000000, 'saved' => 0];
$goal['pct'] = $goal['target'] > 0 ? min(100, (int) round($goal['saved'] / $goal['target'] * 100)) : 0;

/* ---- statistik turunan ---- */
$avgDaily   = $elapsed > 0 ? (int) round($expThis / $elapsed) : 0;
// Di awal bulan, tagihan & cicilan biasanya sudah terbayar sekaligus sehingga ekstrapolasi linear
// menyesatkan. Sebelum hari ke-7, pakai pengeluaran bulan lalu sebagai acuan.
$projected  = ($elapsed >= 7 || $expLast <= 0)
    ? ($elapsed > 0 ? (int) round($expThis / $elapsed * $daysInMonth) : 0)
    : max($expThis, $expLast);
$savingRate = $incThis > 0 ? round(($incThis - $expThis) / $incThis * 100, 1) : null;

/* ---- insight otomatis ---- */
$insights = [];
$warned = 0;
foreach ($budgets as $b) {
    if ($warned >= 2) {
        break;
    }
    if ($b['pct'] >= 100) {
        $insights[] = ['type' => 'warning', 'title' => "Anggaran {$b['category']} terlampaui",
            'text' => "Pengeluaran {$b['category']} sudah {$b['pct']}% dari batas bulanan (" . rp($b['spent']) . ' dari ' . rp($b['limit']) . '). Pertimbangkan menahan pengeluaran di kategori ini.'];
        $warned++;
    } elseif ($b['pct'] >= 85) {
        $insights[] = ['type' => 'warning', 'title' => "Anggaran {$b['category']} hampir habis",
            'text' => "Sudah {$b['pct']}% terpakai, tersisa " . rp(max(0, $b['limit'] - $b['spent'])) . '. Pertimbangkan menunda pembelian non-prioritas.'];
        $warned++;
    }
}
if ($elapsed >= 7 && $expLast > 0) {
    if ($projected <= $expLast * 0.9) {
        $insights[] = ['type' => 'positive', 'title' => 'Pengeluaranmu lebih hemat',
            'text' => 'Dengan laju saat ini, pengeluaran bulan ini diperkirakan ' . rp($projected) . ', lebih rendah dari bulan lalu (' . rp($expLast) . ').'];
    } elseif ($projected >= $expLast * 1.1) {
        $insights[] = ['type' => 'warning', 'title' => 'Pengeluaran berjalan lebih tinggi',
            'text' => 'Dengan laju saat ini, pengeluaran bulan ini diperkirakan ' . rp($projected) . ', lebih tinggi dari bulan lalu (' . rp($expLast) . ').'];
    }
}
if ($savingRate !== null) {
    if ($savingRate >= 20) {
        $insights[] = ['type' => 'positive', 'title' => 'Rasio tabungan sehat',
            'text' => 'Kamu menyisihkan ' . number_format($savingRate, 0, ',', '.') . '% dari pemasukan bulan ini. Pertahankan!'];
    } elseif ($savingRate < 0) {
        $insights[] = ['type' => 'warning', 'title' => 'Pengeluaran melebihi pemasukan',
            'text' => 'Bulan ini pengeluaran melampaui pemasukan sebesar ' . rp(abs($net)) . '. Tinjau kembali kategori terbesarmu.'];
    } else {
        $insights[] = ['type' => 'tip', 'title' => 'Tingkatkan rasio tabungan',
            'text' => 'Rasio tabunganmu ' . number_format($savingRate, 0, ',', '.') . '%. Coba sisihkan minimal 20% pemasukan di awal bulan.'];
    }
}
$spendCats = array_values(array_filter($cats, static fn ($c) => $c['category'] !== 'Tabungan'));   // menabung bukan belanja
if ($spendCats && $expThis > 0) {
    $top = $spendCats[0];
    $insights[] = ['type' => 'tip', 'title' => "Terbesar: {$top['category']}",
        'text' => "{$top['category']} menyerap " . number_format((float) $top['pct'], 0, ',', '.') . '% dari total pengeluaran bulan ini (' . rp($top['amount']) . ').'];
}
if ($goal['saved'] < $goal['target']) {
    $insights[] = ['type' => 'tip', 'title' => "Target {$goal['title']}",
        'text' => 'Tinggal ' . rp($goal['target'] - $goal['saved']) . ' lagi untuk mencapai targetmu. Terus konsisten!'];
}
if ($txCount === 0) {
    $insights = [['type' => 'tip', 'title' => 'Mulai dari transaksi pertama',
        'text' => 'Catat pemasukan dan pengeluaranmu, nanti CatatKan menyusun insight otomatis di sini.']];
}
$weight = ['warning' => 0, 'positive' => 1, 'tip' => 2];
usort($insights, static fn ($a, $b) => $weight[$a['type']] <=> $weight[$b['type']]);
$insights = array_slice($insights, 0, 5);

json_out([
    'user'     => ['name' => $user['name'], 'email' => $user['email']],
    'has_data' => $txCount > 0,
    'period'   => [
        'label'         => month_long((int) $today->format('n')) . ' ' . $today->format('Y'),
        'days_elapsed'  => $elapsed,
        'days_in_month' => $daysInMonth,
    ],
    'balance'  => ['total' => $balance, 'delta_pct' => $balanceDelta],
    'month'    => [
        'income' => $incThis, 'expense' => $expThis, 'net' => $net,
        'income_delta'  => pct_change($incThis, $sameIn),
        'expense_delta' => pct_change($expThis, $sameOut),
    ],
    'cashflow'      => $cashflow,
    'spark'         => $spark,
    'categories'    => $cats,
    'budgets'       => $budgets,
    'top_merchants' => $topMerchants,
    'stats'         => [
        'avg_daily' => $avgDaily, 'projected_expense' => $projected,
        'saving_rate' => $savingRate, 'biggest' => $biggest,
    ],
    'goal'     => $goal,
    'insights' => $insights,
    'recent'   => $recent,
]);
