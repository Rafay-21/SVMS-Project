<?php
/**
 * 500.php — Internal Server Error page.
 * Shown by error_handler.php in production. Must NOT leak details.
 * Designed to be safe even if config.php partially failed.
 */
if (!defined('BASE_URL'))  define('BASE_URL',  '/svms/');
if (!defined('SITE_NAME')) define('SITE_NAME', 'SVMS');
http_response_code(500);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>500 — Server Error · <?= htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: #f1f5f9;
      color: #1e293b;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }
    .card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 4px 32px rgba(0,0,0,.1);
      padding: 48px 40px;
      max-width: 520px;
      width: 100%;
      text-align: center;
    }
    .icon { font-size: 64px; margin-bottom: 16px; }
    h1 { font-size: 3rem; font-weight: 800; color: #ef4444; margin-bottom: 8px; }
    h2 { font-size: 1.25rem; font-weight: 600; color: #1e293b; margin-bottom: 12px; }
    p  { color: #64748b; line-height: 1.6; margin-bottom: 24px; }
    a.btn {
      display: inline-block;
      padding: 12px 28px;
      background: #2e75b6;
      color: #fff;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      font-size: 15px;
      transition: background .2s;
    }
    a.btn:hover { background: #1d5c96; }
    .ref { margin-top: 20px; font-size: 12px; color: #94a3b8; }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">⚠️</div>
    <h1>500</h1>
    <h2>Something went wrong</h2>
    <p>
      An unexpected error occurred on the server.<br>
      Our team has been notified. Please try again in a moment.
    </p>
    <a href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>" class="btn">
      ← Return to Dashboard
    </a>
    <p class="ref">
      Reference: <?= htmlspecialchars(date('YmdHis') . '-' . substr(uniqid(), -6), ENT_QUOTES, 'UTF-8') ?>
    </p>
  </div>
</body>
</html>
