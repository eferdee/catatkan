<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

start_session();
header('Location: ' . (current_user_id() !== null ? 'dashboard.php' : 'login.php'));
exit;
