<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

start_session();
if (current_user_id() !== null) {
    header('Location: dashboard.php');
    exit;
}
// Pengunjung yang belum masuk melihat landing page.
require __DIR__ . '/landing.php';
