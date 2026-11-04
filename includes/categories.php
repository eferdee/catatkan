<?php
declare(strict_types=1);

// Daftar kategori tetap. Kunci = nama kategori yang disimpan di kolom transactions.category.
return [
    'expense' => [
        'Makanan & Minuman' => ['emoji' => '🍜', 'color' => '#f0884a'],
        'Transportasi'      => ['emoji' => '🚗', 'color' => '#3f8fb5'],
        'Belanja'           => ['emoji' => '🛍️', 'color' => '#a870d6'],
        'Hiburan'           => ['emoji' => '🎬', 'color' => '#e0568a'],
        'Kesehatan'         => ['emoji' => '💊', 'color' => '#4fb286'],
        'Pendidikan'        => ['emoji' => '📚', 'color' => '#e5b83a'],
        'Tagihan'           => ['emoji' => '🧾', 'color' => '#6d7be0'],
        'Tabungan'          => ['emoji' => '🏦', 'color' => '#2fa3a0'],
        'Lainnya'           => ['emoji' => '📦', 'color' => '#93a09a'],
    ],
    'income' => [
        'Gaji'      => ['emoji' => '💼', 'color' => '#16995d'],
        'Freelance' => ['emoji' => '💻', 'color' => '#3f8fb5'],
        'Bonus'     => ['emoji' => '🎁', 'color' => '#e5b83a'],
        'Investasi' => ['emoji' => '📈', 'color' => '#a870d6'],
        'Lainnya'   => ['emoji' => '💰', 'color' => '#93a09a'],
    ],
];
