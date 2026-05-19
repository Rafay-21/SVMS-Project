<?php
require_once __DIR__ . '/config.php';
ob_end_clean();
header('Location: ' . BASE_URL . 'pages/login.php');
exit;
