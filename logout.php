<?php
require_once __DIR__ . '/config.php';

if (isset($_SESSION['admin_id'])) {
    log_action('logout', (int)$_SESSION['admin_id']);
}

session_unset();
session_destroy();

ob_end_clean();
header('Location: ' . BASE_URL . 'pages/login.php?msg=logged_out');
exit;
