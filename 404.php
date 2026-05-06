<?php
/**
 * 404.php — Custom not-found page.
 */
require_once __DIR__ . '/config.php';
http_response_code(404);

$page_title = '404 — Page Not Found';
include __DIR__ . '/includes/header.php';
?>
<div class="main">
  <div class="container">
    <div class="empty-state" style="min-height:60vh;">
      <img src="<?= BASE_URL ?>assets/img/empty-state.svg" alt="Not found" width="180">
      <h1 style="font-size:4rem;font-weight:700;color:var(--primary);margin:16px 0 8px;">404</h1>
      <h2 style="margin-bottom:12px;">Page Not Found</h2>
      <p style="color:var(--text-muted);margin-bottom:24px;">
        The page you're looking for doesn't exist or has been moved.
      </p>
      <a href="<?= BASE_URL ?>" class="btn btn-primary">
        <i class="bi bi-house-door"></i> Return Home
      </a>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
