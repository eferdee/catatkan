<?php
declare(strict_types=1);

const DEMO_EMAIL = 'demo@catatkan.app';

/**
 * Mengisi akun dengan data contoh 12 bulan terakhir (transaksi, anggaran, target tabungan).
 * Deterministik (seed tetap) sehingga tampilan demo selalu konsisten.
 */
function seed_demo_data(PDO $pdo, int $uid): void
{
    mt_srand(2026);

    $today     = new DateTimeImmutable('today');
    $thisStart = $today->modify('first day of this month');

    // [nama merchant, nominal min, nominal maks]
    $merchants = [
        'Makanan & Minuman' => [['Kopi Kenangan', 22000, 48000], ['GrabFood', 40000, 95000], ['Warung Pempek Palembang', 30000, 75000], ['Indomaret', 15000, 90000], ['Martabak 88', 55000, 95000], ['Warteg Bahari', 17000, 38000], ['Sate Padang Ajo', 35000, 60000]],
        'Transportasi'      => [['Pertamina', 50000, 150000], ['Gojek', 14000, 45000], ['KRL Commuter', 8000, 20000], ['Parkir', 3000, 10000], ['Grab', 18000, 60000]],
        'Belanja'           => [['Tokopedia', 60000, 350000], ['Shopee', 45000, 300000], ['Uniqlo', 150000, 450000], ['Alfamart', 20000, 120000]],
        'Hiburan'           => [['Bioskop XXI', 50000, 110000], ['Steam', 60000, 250000], ['Karaoke Keluarga', 40000, 120000]],
        'Kesehatan'         => [['Apotek K24', 25000, 150000], ['Klinik Sehat', 100000, 300000]],
        'Pendidikan'        => [['Gramedia', 60000, 200000], ['Udemy', 120000, 250000]],
        'Lainnya'           => [['Donasi', 20000, 100000], ['Laundry', 25000, 60000]],
    ];
    $weights = ['Makanan & Minuman' => 38, 'Transportasi' => 17, 'Belanja' => 14, 'Hiburan' => 9, 'Kesehatan' => 6, 'Pendidikan' => 5, 'Lainnya' => 6];
    $weightSum = array_sum($weights);
    $salaryFor = static fn (int $m): int => 14000000 + (int) floor((11 - $m) / 4) * 500000;   // naik bertahap: 14 jt → 15 jt

    $pickCategory = static function () use ($weights, $weightSum): string {
        $r = mt_rand(1, $weightSum);
        foreach ($weights as $cat => $w) {
            if (($r -= $w) <= 0) {
                return $cat;
            }
        }
        return 'Lainnya';
    };

    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare('INSERT INTO transactions (user_id, name, amount, type, category, tx_date) VALUES (?, ?, ?, ?, ?, ?)');

        for ($m = 11; $m >= 0; $m--) {
            $start  = $thisStart->modify("-$m months");
            $dim    = (int) $start->format('t');
            $maxDay = $m === 0 ? (int) $today->format('j') : $dim;
            $date   = static fn (int $d): string => $start->setDate((int) $start->format('Y'), (int) $start->format('n'), max(1, min($d, $maxDay)))->format('Y-m-d');

            // Pemasukan
            $ins->execute([$uid, 'Gaji bulanan', $salaryFor($m), 'income', 'Gaji', $date(1)]);
            if ($m === 9) {
                $ins->execute([$uid, 'Bonus akhir tahun', 3000000, 'income', 'Bonus', $date(20)]);
            }
            if ($m === 6) {
                $ins->execute([$uid, 'Proyek aplikasi kasir', 3200000, 'income', 'Freelance', $date(12)]);
            }
            if ($m === 4) {
                $ins->execute([$uid, 'Proyek website klien', 1800000, 'income', 'Freelance', $date(14)]);
            }
            if ($m === 2) {
                $ins->execute([$uid, 'Dividen reksa dana', 450000, 'income', 'Investasi', $date(10)]);
            }
            if ($m === 1) {
                $ins->execute([$uid, 'Proyek desain landing page', 2500000, 'income', 'Freelance', $date(18)]);
            }

            // Pengeluaran rutin
            if ($maxDay >= 2) {
                $ins->execute([$uid, 'Transfer ke tabungan', 4000000, 'expense', 'Tabungan', $date(2)]);
                $ins->execute([$uid, 'Sewa kos', 1500000, 'expense', 'Tagihan', $date(2)]);
            }
            if ($maxDay >= 5) {
                $ins->execute([$uid, 'Indihome', 385000, 'expense', 'Tagihan', $date(5)]);
            }
            if ($maxDay >= 8) {
                $ins->execute([$uid, 'Listrik PLN', (int) (round(mt_rand(260000, 420000) / 1000) * 1000), 'expense', 'Tagihan', $date(8)]);
            }
            if ($maxDay >= 28) {
                $ins->execute([$uid, 'Spotify Premium', 54990, 'expense', 'Hiburan', $date(28)]);
            }

            // Pengeluaran acak
            $count = (int) round(36 * $maxDay / $dim) + mt_rand(-2, 3);
            $count = max($m === 0 ? 12 : 14, $count);
            for ($i = 0; $i < $count; $i++) {
                $cat = $pickCategory();
                [$name, $lo, $hi] = $merchants[$cat][mt_rand(0, count($merchants[$cat]) - 1)];
                $amount = (int) (round(mt_rand($lo, $hi) / 1000) * 1000);
                $ins->execute([$uid, $name, max(1000, $amount), 'expense', $cat, $date(mt_rand(1, $maxDay))]);
            }
        }

        $budget = $pdo->prepare('INSERT INTO budgets (user_id, category, monthly_limit) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE monthly_limit = VALUES(monthly_limit)');
        foreach (['Makanan & Minuman' => 1500000, 'Transportasi' => 800000, 'Belanja' => 700000, 'Hiburan' => 500000, 'Tagihan' => 2400000] as $cat => $limit) {
            $budget->execute([$uid, $cat, $limit]);
        }

        $pdo->prepare(
            'INSERT INTO goals (user_id, title, target_amount, saved_amount) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE title = VALUES(title), target_amount = VALUES(target_amount), saved_amount = VALUES(saved_amount)'
        )->execute([$uid, 'Dana darurat', 10000000, 6800000]);

        $pdo->commit();
    } catch (Throwable $t) {
        $pdo->rollBack();
        throw $t;
    } finally {
        mt_srand();   // kembalikan ke seed acak
    }
}
