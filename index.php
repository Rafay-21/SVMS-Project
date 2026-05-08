<?php
$year = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0f172a">
    <meta name="robots" content="noindex,nofollow">
    <title>SVMS Prototype</title>
    <link rel="icon" type="image/svg+xml" href="assets/img/logo.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/prototype.css">
</head>
<body>
    <noscript>
        <div class="noscript-banner">This prototype requires JavaScript enabled to run the in-memory auth and dashboards.</div>
    </noscript>
    <div id="app" class="app-shell">
        <div class="loading-state">
            <div class="loading-card">
                <div class="loading-badge"><i class="bi bi-shield-lock-fill"></i></div>
                <h1>Smart Visitor Management System</h1>
                <p>Loading presentation prototype…</p>
            </div>
        </div>
    </div>
    <footer class="prototype-footer">© <?= $year ?> Smart Visitor Management System · Prototype mode</footer>
    <script src="assets/js/prototype.js" defer></script>
</body>
</html>
