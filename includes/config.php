<?php
declare(strict_types=1);

/*
 * Konfigurasi aplikasi.
 * Cara termudah: salin config.local.sample.php menjadi config.local.php lalu isi kredensial database.
 * Alternatif: set environment variable SW_DB_HOST, SW_DB_NAME, SW_DB_USER, SW_DB_PASS.
 */

$localFile = __DIR__ . '/config.local.php';
$overrides = is_file($localFile) ? (array) require $localFile : [];

return array_merge([
    'db_host'      => getenv('SW_DB_HOST') ?: 'localhost',
    'db_port'      => (int) (getenv('SW_DB_PORT') ?: 3306),
    'db_name'      => getenv('SW_DB_NAME') ?: 'catatkan',
    'db_user'      => getenv('SW_DB_USER') ?: 'root',
    'db_pass'      => getenv('SW_DB_PASS') ?: '',
    'timezone'     => 'Asia/Jakarta',
    'session_name' => 'catatkan_sid',
    // Tombol "Coba akun demo" di halaman login (berguna untuk portofolio).
    'demo_enabled' => true,
], $overrides);
