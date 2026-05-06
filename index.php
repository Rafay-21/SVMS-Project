<?php
/**
 * index.php — Entry point
 * Redirects authenticated users to dashboard, others to login.
 */
require_once __DIR__ . '/config.php';

if (isset($_SESSION['admin_id']) && isset($_SESSION['2fa_verified'])) {
    header('Location: ' . BASE_URL . 'pages/dashboard.php');
} else {
    header('Location: ' . BASE_URL . 'pages/login.php');
}
exit;
