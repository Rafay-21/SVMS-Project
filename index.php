<?php
/**
 * index.php — Public landing page for SVMS.
 * Authenticated users are forwarded directly to the dashboard.
 */
require_once __DIR__ . '/config.php';

ob_end_clean();

// Already logged in → dashboard
if (isset($_SESSION['admin_id']) && !empty($_SESSION['2fa_verified'])) {
    header('Location: ' . BASE_URL . 'pages/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="SVMS — Professional visitor management system. QR check-in, real-time tracking, analytics, and enterprise security for modern organizations.">
  <title><?= e(SITE_NAME) ?> — Secure Visitor Tracking &amp; Management</title>
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>assets/img/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <style>
  /* ── Reset & Base ─────────────────────────────────────────────── */
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  html { scroll-behavior: smooth; }
  body { font-family: 'Inter', system-ui, -apple-system, sans-serif; font-size: 16px; line-height: 1.6; color: #1e293b; background: #fff; overflow-x: hidden; }
  a { color: inherit; text-decoration: none; }
  img { max-width: 100%; height: auto; display: block; }

  /* ── Design Tokens ────────────────────────────────────────────── */
  :root {
    --primary: #1a3c5e;
    --primary-mid: #1f4a72;
    --secondary: #2e75b6;
    --accent: #00b4d8;
    --accent-dk: #0098ba;
    --success: #059669;
    --danger: #dc2626;
    --purple: #7c3aed;
    --text: #1e293b;
    --text-muted: #64748b;
    --border: #e2e8f0;
    --surface: #f8fafc;
    --card: #ffffff;
    --r: 12px;
    --r-lg: 18px;
    --shadow: 0 4px 20px rgba(0,0,0,.08), 0 1px 4px rgba(0,0,0,.05);
    --shadow-lg: 0 24px 64px rgba(0,0,0,.12), 0 8px 24px rgba(0,0,0,.07);
  }

  /* ── Layout ───────────────────────────────────────────────────── */
  .container { max-width: 1160px; margin: 0 auto; padding: 0 28px; }
  .section { padding: 96px 0; }
  .text-center { text-align: center; }
  .text-center .section-sub { margin-left: auto; margin-right: auto; }

  /* ── Typography helpers ───────────────────────────────────────── */
  .tag {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 5px 13px; border-radius: 100px;
    font-size: 11.5px; font-weight: 700; letter-spacing: .9px; text-transform: uppercase;
    margin-bottom: 18px;
  }
  .tag-blue  { background: rgba(46,117,182,.1);  color: var(--secondary); }
  .tag-cyan  { background: rgba(0,180,216,.1);   color: var(--accent-dk); }
  .tag-green { background: rgba(5,150,105,.1);   color: var(--success); }
  .tag-red   { background: rgba(220,38,38,.08);  color: var(--danger); }
  .tag-white { background: rgba(0,180,216,.18);  color: #5bc8e8; }
  .h2 {
    font-size: clamp(28px, 3.8vw, 44px);
    font-weight: 900; color: var(--text);
    letter-spacing: -1px; line-height: 1.12;
    margin-bottom: 14px;
  }
  .h2-white { color: #fff; }
  .section-sub {
    font-size: 17px; color: var(--text-muted);
    line-height: 1.7; max-width: 560px;
  }
  .section-sub-white { color: rgba(255,255,255,.62); }

  /* ── Buttons ──────────────────────────────────────────────────── */
  .btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    padding: 13px 28px; border-radius: 10px;
    font-size: 15px; font-weight: 700; font-family: inherit;
    cursor: pointer; border: none; transition: all .18s cubic-bezier(.4,0,.2,1);
    letter-spacing: -.1px; white-space: nowrap;
  }
  .btn:hover { transform: translateY(-2px); }
  .btn-primary { background: var(--primary); color: #fff; box-shadow: 0 2px 10px rgba(26,60,94,.28); }
  .btn-primary:hover { background: #152e4d; box-shadow: 0 8px 24px rgba(26,60,94,.38); }
  .btn-accent  { background: var(--accent); color: #fff; box-shadow: 0 2px 10px rgba(0,180,216,.28); }
  .btn-accent:hover  { background: var(--accent-dk); box-shadow: 0 8px 24px rgba(0,180,216,.38); }
  .btn-ghost  { background: rgba(255,255,255,.13); color: #fff; border: 1.5px solid rgba(255,255,255,.28); }
  .btn-ghost:hover  { background: rgba(255,255,255,.22); border-color: rgba(255,255,255,.45); }
  .btn-outline { background: transparent; color: var(--primary); border: 2px solid var(--primary); }
  .btn-outline:hover { background: rgba(26,60,94,.04); }
  .btn-lg { padding: 15px 34px; font-size: 16px; }

  /* ── Navbar ───────────────────────────────────────────────────── */
  .nav {
    position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
    height: 70px; padding: 0 40px;
    display: flex; align-items: center; justify-content: space-between; gap: 20px;
    transition: background .35s, box-shadow .35s, border-color .35s;
    border-bottom: 1px solid transparent;
  }
  .nav.scrolled {
    background: rgba(255,255,255,.93);
    backdrop-filter: blur(18px) saturate(180%);
    border-bottom-color: rgba(0,0,0,.06);
    box-shadow: 0 2px 24px rgba(0,0,0,.07);
  }
  .nav-logo { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
  .nav-logo img { width: 36px; height: 36px; border-radius: 9px; }
  .nav-logo-name {
    font-size: 16px; font-weight: 800; letter-spacing: -.3px;
    color: rgba(255,255,255,.95); transition: color .3s;
  }
  .nav.scrolled .nav-logo-name { color: var(--primary); }
  .nav-links { display: flex; align-items: center; gap: 2px; flex: 1; justify-content: center; }
  .nav-link {
    padding: 7px 15px; border-radius: 8px;
    font-size: 14px; font-weight: 500;
    color: rgba(255,255,255,.72); transition: color .15s, background .15s;
  }
  .nav-link:hover { color: #fff; background: rgba(255,255,255,.12); }
  .nav.scrolled .nav-link { color: var(--text-muted); }
  .nav.scrolled .nav-link:hover { color: var(--text); background: #f1f5f9; }
  .nav-actions { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
  .btn-nav-in {
    padding: 8px 18px; border-radius: 8px;
    font-size: 14px; font-weight: 600;
    color: rgba(255,255,255,.85); border: 1.5px solid rgba(255,255,255,.25);
    background: transparent; transition: all .15s;
  }
  .btn-nav-in:hover { background: rgba(255,255,255,.15); color: #fff; }
  .nav.scrolled .btn-nav-in { color: var(--primary); border-color: var(--border); }
  .nav.scrolled .btn-nav-in:hover { background: rgba(26,60,94,.05); }
  .btn-nav-up {
    padding: 8px 18px; border-radius: 8px;
    font-size: 14px; font-weight: 700;
    background: rgba(255,255,255,.18); color: #fff; border: 1.5px solid rgba(255,255,255,.3);
    transition: all .15s;
  }
  .btn-nav-up:hover { background: rgba(255,255,255,.28); }
  .nav.scrolled .btn-nav-up { background: var(--primary); border-color: var(--primary); }
  .nav.scrolled .btn-nav-up:hover { background: #152e4d; }
  /* Hamburger */
  .nav-burger {
    display: none; background: none; border: none; cursor: pointer;
    padding: 6px; border-radius: 8px; font-size: 24px;
    color: rgba(255,255,255,.9); transition: color .2s;
    line-height: 1;
  }
  .nav.scrolled .nav-burger { color: var(--text); }
  .mobile-menu {
    display: none; position: fixed; top: 70px; left: 0; right: 0;
    background: #fff; border-bottom: 1px solid var(--border);
    padding: 18px 24px 22px; box-shadow: 0 12px 32px rgba(0,0,0,.1);
    z-index: 999;
  }
  .mobile-menu.open { display: block; }
  .mobile-link {
    display: block; padding: 11px 0; font-size: 15px; font-weight: 500;
    color: var(--text-muted); border-bottom: 1px solid var(--border);
    transition: color .15s;
  }
  .mobile-link:hover { color: var(--text); }
  .mobile-link:last-of-type { border-bottom: none; }
  .mobile-btns { display: flex; gap: 10px; margin-top: 16px; }
  .mobile-btns a {
    flex: 1; text-align: center; padding: 11px;
    border-radius: 9px; font-size: 14px; font-weight: 700;
  }

  /* ── Hero ─────────────────────────────────────────────────────── */
  .hero {
    min-height: 100vh;
    background:
      linear-gradient(155deg, rgba(5,16,28,.95) 0%, rgba(12,30,56,.90) 30%, rgba(22,52,82,.87) 60%, rgba(10,36,64,.93) 100%),
      url('<?= BASE_URL ?>assets/img/bg/hero-bg.jpg') center / cover no-repeat;
    padding: 110px 0 80px;
    position: relative; overflow: hidden;
    display: flex; align-items: center;
  }
  .hero::before {
    content: ''; position: absolute; inset: 0; pointer-events: none;
    background:
      radial-gradient(ellipse 70% 50% at 70% 30%, rgba(0,180,216,.16) 0%, transparent 60%),
      radial-gradient(ellipse 40% 60% at 10% 80%, rgba(46,117,182,.12) 0%, transparent 55%);
  }
  .hero::after {
    content: ''; position: absolute; inset: 0; pointer-events: none;
    background-image: radial-gradient(circle, rgba(255,255,255,.055) 1px, transparent 1px);
    background-size: 26px 26px;
  }
  .hero-grid {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 56px; align-items: center;
    position: relative; z-index: 1;
  }
  /* Hero left */
  .hero-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 6px 14px; border-radius: 100px; margin-bottom: 22px;
    background: rgba(0,180,216,.14); border: 1px solid rgba(0,180,216,.28);
    font-size: 12px; font-weight: 700; letter-spacing: .8px;
    text-transform: uppercase; color: rgba(0,180,216,.9);
  }
  .hero-dot {
    width: 6px; height: 6px; border-radius: 50%; background: #00b4d8;
    animation: pulse-dot 2.2s ease-in-out infinite;
  }
  @keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1);} 50%{opacity:.5;transform:scale(.75);} }
  .hero-title {
    font-size: clamp(38px, 5.2vw, 64px);
    font-weight: 900; color: #fff;
    letter-spacing: -2px; line-height: 1.06;
    margin-bottom: 22px;
  }
  .hero-title .accent {
    background: linear-gradient(135deg, #00b4d8 0%, #5bc8e8 100%);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
  }
  .hero-desc {
    font-size: 18px; color: rgba(255,255,255,.65);
    line-height: 1.7; max-width: 480px; margin-bottom: 36px;
  }
  .hero-cta { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 44px; }
  .hero-proof {
    display: flex; align-items: center; gap: 14px;
    padding-top: 28px; border-top: 1px solid rgba(255,255,255,.1);
  }
  .proof-avatars { display: flex; }
  .proof-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    border: 2px solid rgba(26,60,94,.8);
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 800; color: rgba(255,255,255,.85);
    margin-left: -9px; background: rgba(255,255,255,.12);
  }
  .proof-avatar:first-child { margin-left: 0; }
  .proof-text { font-size: 13px; color: rgba(255,255,255,.5); }
  .proof-text strong { color: rgba(255,255,255,.85); }
  /* Hero right */
  .hero-visual { position: relative; display: flex; align-items: center; justify-content: center; }
  .hero-frame {
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.15);
    border-radius: 20px; padding: 0; overflow: hidden;
    backdrop-filter: blur(12px);
    box-shadow: 0 40px 100px rgba(0,0,0,.42), 0 10px 32px rgba(0,0,0,.28),
                inset 0 1px 0 rgba(255,255,255,.1);
    animation: float-hero 5.5s ease-in-out infinite;
    width: 100%; max-width: 440px;
  }
  @keyframes float-hero { 0%,100%{transform:translateY(0);} 50%{transform:translateY(-14px);} }
  .hero-frame-bar {
    height: 34px; background: rgba(255,255,255,.10);
    display: flex; align-items: center; padding: 0 14px; gap: 7px;
    border-bottom: 1px solid rgba(255,255,255,.08);
  }
  .hero-frame-dot { width: 11px; height: 11px; border-radius: 50%; }
  .hero-frame img {
    width: 100%; height: 280px;
    object-fit: cover; object-position: center; display: block;
  }
  .hero-frame-caption {
    padding: 13px 18px;
    background: rgba(0,0,0,.28);
    border-top: 1px solid rgba(255,255,255,.07);
    display: flex; align-items: center; gap: 10px;
  }
  .hero-frame-live-dot {
    width: 8px; height: 8px; border-radius: 50%; background: #22c55e;
    box-shadow: 0 0 0 3px rgba(34,197,94,.25);
    animation: pulse-dot 2s infinite; flex-shrink: 0;
  }
  .hero-frame-caption-text { font-size: 13px; color: rgba(255,255,255,.8); font-weight: 500; }
  .hero-frame-caption-badge {
    margin-left: auto; background: rgba(0,180,216,.22); color: #00b4d8;
    font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px;
    border: 1px solid rgba(0,180,216,.32); white-space: nowrap;
  }
  /* Floating cards */
  .float-card {
    position: absolute; background: #fff; border-radius: 14px;
    padding: 11px 15px; box-shadow: 0 10px 32px rgba(0,0,0,.18);
    display: flex; align-items: center; gap: 11px;
    animation: float-badge 3.5s ease-in-out infinite;
  }
  .float-card-1 { top: 5%; left: -10%; animation-delay: 0s; }
  .float-card-2 { bottom: 10%; right: -8%; animation-delay: 1.8s; }
  @keyframes float-badge { 0%,100%{transform:translateY(0) rotate(-1deg);} 50%{transform:translateY(-10px) rotate(1deg);} }
  .float-icon {
    width: 40px; height: 40px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
  }
  .float-val  { font-size: 15px; font-weight: 800; color: var(--text); line-height: 1.15; }
  .float-lbl  { font-size: 11px; color: var(--text-muted); font-weight: 500; }

  /* ── Stats bar ────────────────────────────────────────────────── */
  .stats-bar { background: var(--surface); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
  .stats-bar-grid { display: grid; grid-template-columns: repeat(4, 1fr); }
  .stat-cell { padding: 36px 24px; text-align: center; border-right: 1px solid var(--border); }
  .stat-cell:last-child { border-right: none; }
  .stat-num {
    font-size: 40px; font-weight: 900; color: var(--primary);
    letter-spacing: -1.5px; line-height: 1; margin-bottom: 7px;
  }
  .stat-num em { font-size: 26px; font-style: normal; color: var(--accent); }
  .stat-lbl { font-size: 13.5px; color: var(--text-muted); font-weight: 500; }

  /* ── Features ─────────────────────────────────────────────────── */
  .feature-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 54px; }
  .feature-card {
    background: var(--card); border: 1.5px solid var(--border); border-radius: var(--r-lg);
    padding: 30px 26px; transition: border-color .22s, box-shadow .22s, transform .22s;
  }
  .feature-card:hover { border-color: var(--secondary); box-shadow: var(--shadow); transform: translateY(-4px); }
  .feat-icon {
    width: 54px; height: 54px; border-radius: 15px;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; margin-bottom: 20px;
  }
  .feat-title { font-size: 17px; font-weight: 700; color: var(--text); margin-bottom: 8px; letter-spacing: -.2px; }
  .feat-desc  { font-size: 14px; color: var(--text-muted); line-height: 1.65; }

  /* ── Steps / How It Works ─────────────────────────────────────── */
  .steps-section {
    background:
      linear-gradient(158deg, rgba(5,18,34,.95) 0%, rgba(14,31,58,.91) 40%, rgba(22,50,78,.88) 100%),
      url('<?= BASE_URL ?>assets/img/bg/steps-bg.jpg') center / cover no-repeat fixed;
    position: relative; overflow: hidden; padding: 96px 0;
  }
  .steps-section::before {
    content:''; position:absolute; inset:0; pointer-events:none;
    background-image: radial-gradient(circle, rgba(255,255,255,.055) 1px, transparent 1px);
    background-size: 24px 24px;
  }
  .steps-section::after {
    content:''; position:absolute; inset:0; pointer-events:none;
    background: radial-gradient(ellipse 50% 60% at 80% 40%, rgba(0,180,216,.14) 0%, transparent 55%);
  }
  .steps-grid {
    display: grid; grid-template-columns: repeat(3, 1fr);
    gap: 28px; margin-top: 54px; position: relative; z-index: 1;
  }
  .steps-grid::before {
    content: ''; position: absolute; top: 36px;
    left: calc(100% / 6); right: calc(100% / 6); height: 2px;
    background: linear-gradient(90deg, rgba(0,180,216,.2), rgba(0,180,216,.65), rgba(0,180,216,.2));
    z-index: 0; pointer-events: none;
  }
  .step-card {
    background: rgba(255,255,255,.048); border: 1px solid rgba(255,255,255,.1);
    border-radius: var(--r-lg); padding: 32px 26px; text-align: center;
    position: relative; z-index: 1; transition: background .22s;
  }
  .step-card:hover { background: rgba(255,255,255,.09); }
  .step-num {
    width: 68px; height: 68px; border-radius: 50%; margin: 0 auto 22px;
    background: rgba(0,180,216,.18); border: 2px solid rgba(0,180,216,.45);
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; font-weight: 900; color: #00b4d8;
  }
  .step-title { font-size: 19px; font-weight: 800; color: #fff; margin-bottom: 12px; letter-spacing: -.3px; }
  .step-desc  { font-size: 14.5px; color: rgba(255,255,255,.58); line-height: 1.68; }

  /* ── Showcase ─────────────────────────────────────────────────── */
  .showcase-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 64px; align-items: center; }
  .spotlight-list { margin-top: 30px; display: flex; flex-direction: column; gap: 18px; }
  .sl-item { display: flex; align-items: flex-start; gap: 14px; }
  .sl-icon {
    width: 40px; height: 40px; border-radius: 11px;
    display: flex; align-items: center; justify-content: center;
    font-size: 17px; flex-shrink: 0; margin-top: 2px;
  }
  .sl-title { font-size: 15px; font-weight: 700; color: var(--text); margin-bottom: 3px; }
  .sl-desc  { font-size: 14px; color: var(--text-muted); line-height: 1.58; }

  /* ── Dashboard Mockup ─────────────────────────────────────────── */
  .mockup-wrap {
    border-radius: 18px; overflow: hidden;
    box-shadow: var(--shadow-lg); border: 1px solid var(--border);
    background: #fff;
    transform: perspective(1200px) rotateY(-5deg) rotateX(2deg);
    transition: transform .45s cubic-bezier(.4,0,.2,1);
  }
  .mockup-wrap:hover { transform: perspective(1200px) rotateY(0) rotateX(0); }
  .m-bar {
    background: #f1f5f9; border-bottom: 1px solid var(--border);
    padding: 10px 14px; display: flex; align-items: center; gap: 7px;
  }
  .m-dot { width: 12px; height: 12px; border-radius: 50%; }
  .m-url {
    flex: 1; margin: 0 8px; background: #fff; border: 1px solid var(--border);
    border-radius: 7px; padding: 5px 11px; font-size: 11.5px; color: var(--text-muted);
  }
  .m-body { padding: 14px; background: #f8fafc; }
  /* Top navbar strip inside mockup */
  .m-nav {
    background: var(--primary); border-radius: 8px 8px 0 0; height: 38px;
    display: flex; align-items: center; padding: 0 12px; gap: 8px;
    margin: -14px -14px 12px -14px;
  }
  .m-nav-pill { height: 8px; border-radius: 4px; background: rgba(255,255,255,.22); }
  .m-layout { display: flex; gap: 10px; }
  /* Sidebar */
  .m-sidebar {
    width: 90px; flex-shrink: 0;
    background: var(--primary); border-radius: 8px; padding: 9px 8px;
  }
  .m-sb-item { height: 8px; border-radius: 3px; margin-bottom: 7px; background: rgba(255,255,255,.16); }
  .m-sb-item.on { background: rgba(0,180,216,.55); }
  /* Content */
  .m-content { flex: 1; display: flex; flex-direction: column; gap: 10px; }
  /* Welcome banner inside mockup */
  .m-banner {
    background: linear-gradient(135deg, var(--primary), #0d2a47);
    border-radius: 8px; padding: 10px 12px;
    display: flex; align-items: center; justify-content: space-between;
  }
  .m-banner-line { height: 7px; border-radius: 3px; background: rgba(255,255,255,.55); margin-bottom: 4px; }
  .m-banner-sub  { height: 5px; border-radius: 2px; background: rgba(255,255,255,.28); width: 60%; }
  .m-banner-btns { display: flex; gap: 4px; margin-top: 6px; }
  .m-banner-btn  { height: 14px; border-radius: 5px; background: rgba(255,255,255,.2); }
  /* Stats row */
  .m-stats { display: grid; grid-template-columns: repeat(3,1fr); gap: 7px; }
  .m-stat {
    background: #fff; border: 1px solid var(--border); border-radius: 7px; padding: 8px 9px;
  }
  .m-stat-n { height: 11px; border-radius: 3px; width: 45%; margin-bottom: 5px; }
  .m-stat-l { height: 7px; border-radius: 2px; width: 80%; background: #e2e8f0; }
  /* Chart */
  .m-chart {
    background: #fff; border: 1px solid var(--border); border-radius: 7px;
    padding: 10px; height: 72px; display: flex; align-items: flex-end; gap: 4px;
  }
  .m-bar-item { border-radius: 3px 3px 0 0; flex: 1; }
  /* Table */
  .m-table { background: #fff; border: 1px solid var(--border); border-radius: 7px; overflow: hidden; }
  .m-th { background: #f1f5f9; padding: 6px 10px; display: flex; gap: 8px; }
  .m-tc { height: 7px; border-radius: 2px; background: #dde3ec; }
  .m-tr { padding: 7px 10px; display: flex; gap: 8px; align-items: center; border-top: 1px solid #f8fafc; }
  .m-badge { height: 15px; border-radius: 7px; width: 38px; }

  /* ── Security ─────────────────────────────────────────────────── */
  .security-section {
    background:
      linear-gradient(rgba(248,250,252,.97), rgba(241,245,249,.97)),
      url('<?= BASE_URL ?>assets/img/bg/security-bg.jpg') center / cover no-repeat;
    border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);
  }
  .security-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 20px; margin-top: 50px; }
  .sec-card {
    background: var(--card); border: 1.5px solid var(--border); border-radius: var(--r-lg);
    padding: 26px 22px; text-align: center; transition: border-color .2s, box-shadow .2s;
  }
  .sec-card:hover { border-color: var(--secondary); box-shadow: var(--shadow); }
  .sec-icon {
    width: 56px; height: 56px; border-radius: 50%; margin: 0 auto 16px;
    background: rgba(26,60,94,.07);
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; color: var(--primary);
  }
  .sec-title { font-size: 15px; font-weight: 700; color: var(--text); margin-bottom: 7px; }
  .sec-desc  { font-size: 13.5px; color: var(--text-muted); line-height: 1.6; }

  /* ── CTA ──────────────────────────────────────────────────────── */
  .cta-section {
    background:
      linear-gradient(140deg, rgba(8,24,44,.95) 0%, rgba(22,52,82,.90) 50%, rgba(10,36,64,.95) 100%),
      url('<?= BASE_URL ?>assets/img/bg/cta-bg.jpg') center / cover no-repeat fixed;
    position: relative; overflow: hidden;
  }
  .cta-section::before {
    content:''; position:absolute; inset:0; pointer-events:none;
    background:
      radial-gradient(ellipse 55% 70% at 85% 50%, rgba(0,180,216,.2) 0%, transparent 55%),
      radial-gradient(ellipse 40% 50% at 15% 60%, rgba(46,117,182,.12) 0%, transparent 50%);
  }
  .cta-section::after {
    content:''; position:absolute; inset:0; pointer-events:none;
    background-image: radial-gradient(circle, rgba(255,255,255,.055) 1px, transparent 1px);
    background-size: 24px 24px;
  }
  .cta-inner { position: relative; z-index: 1; text-align: center; padding: 100px 0; }
  .cta-title {
    font-size: clamp(30px, 4.5vw, 50px);
    font-weight: 900; color: #fff;
    letter-spacing: -1.2px; line-height: 1.1; margin-bottom: 16px;
  }
  .cta-sub {
    font-size: 18px; color: rgba(255,255,255,.62); line-height: 1.68;
    max-width: 480px; margin: 0 auto 38px;
  }
  .cta-btns { display: flex; flex-wrap: wrap; justify-content: center; gap: 14px; }
  .cta-note { margin-top: 20px; font-size: 13px; color: rgba(255,255,255,.36); }

  /* ── Footer ───────────────────────────────────────────────────── */
  footer {
    background:
      linear-gradient(rgba(5,14,26,.98), rgba(7,22,36,.99)),
      url('<?= BASE_URL ?>assets/img/bg/steps-bg.jpg') center / cover no-repeat;
    padding: 56px 0 32px;
  }
  .footer-grid { display: grid; grid-template-columns: 2.2fr 1fr 1fr 1fr; gap: 44px; margin-bottom: 44px; }
  .footer-brand { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
  .footer-brand img { width: 30px; height: 30px; border-radius: 8px; }
  .footer-brand-name { font-size: 16px; font-weight: 800; color: #fff; letter-spacing: -.2px; }
  .footer-tagline { font-size: 14px; color: rgba(255,255,255,.38); line-height: 1.65; max-width: 270px; }
  .footer-h { font-size: 11.5px; font-weight: 700; color: rgba(255,255,255,.45); letter-spacing: 1px; text-transform: uppercase; margin-bottom: 14px; }
  .footer-a { display: block; font-size: 14px; color: rgba(255,255,255,.42); margin-bottom: 10px; transition: color .15s; }
  .footer-a:hover { color: rgba(255,255,255,.8); }
  .footer-bottom {
    border-top: 1px solid rgba(255,255,255,.06); padding-top: 26px;
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;
  }
  .footer-copy { font-size: 13px; color: rgba(255,255,255,.28); }
  .footer-pills { display: flex; gap: 9px; }
  .footer-pill {
    padding: 4px 11px; border-radius: 7px; font-size: 11px; font-weight: 600;
    background: rgba(255,255,255,.06); color: rgba(255,255,255,.38); border: 1px solid rgba(255,255,255,.07);
  }

  /* ── Responsive ───────────────────────────────────────────────── */
  @media (max-width: 1060px) {
    .nav-links, .nav-actions { display: none; }
    .nav-burger { display: flex; }
    .hero-grid { grid-template-columns: 1fr; }
    .hero-visual { display: none; }
    .steps-grid { grid-template-columns: 1fr; }
    .steps-grid::before { display: none; }
    .showcase-grid { grid-template-columns: 1fr; }
    .security-grid { grid-template-columns: repeat(2, 1fr); }
    .footer-grid { grid-template-columns: 1fr 1fr; }
  }
  @media (max-width: 760px) {
    .section { padding: 68px 0; }
    .stats-bar-grid { grid-template-columns: repeat(2, 1fr); }
    .stat-cell { border-right: none; border-bottom: 1px solid var(--border); }
    .stat-cell:nth-child(odd)  { border-right: 1px solid var(--border); }
    .stat-cell:nth-child(3),
    .stat-cell:nth-child(4) { border-bottom: none; }
    .feature-grid { grid-template-columns: 1fr; }
    .security-grid { grid-template-columns: 1fr; }
    .footer-grid { grid-template-columns: 1fr; }
    .hero-cta .btn, .cta-btns .btn { width: 100%; }
  }
  </style>
</head>
<body>

<!-- ════════════════════════════════════════ NAVBAR ══════════════════════════════════════════ -->
<nav class="nav" id="mainNav">
  <a href="<?= BASE_URL ?>" class="nav-logo" style="text-decoration:none;">
    <img src="<?= BASE_URL ?>assets/img/logo.svg" alt="SVMS">
    <span class="nav-logo-name"><?= e(SITE_NAME) ?></span>
  </a>

  <div class="nav-links">
    <a href="#features"    class="nav-link">Features</a>
    <a href="#how-it-works" class="nav-link">How It Works</a>
    <a href="#showcase"    class="nav-link">Dashboard</a>
    <a href="#security"    class="nav-link">Security</a>
  </div>

  <div class="nav-actions">
    <a href="<?= BASE_URL ?>pages/login.php"    class="btn-nav-in">Sign In</a>
    <a href="<?= BASE_URL ?>pages/register.php" class="btn-nav-up">Get Started &rarr;</a>
  </div>

  <button class="nav-burger" id="burgerBtn" aria-label="Menu">
    <i class="bi bi-list"></i>
  </button>
</nav>

<!-- Mobile menu -->
<div class="mobile-menu" id="mobileMenu">
  <a href="#features"    class="mobile-link" onclick="closeMob()">Features</a>
  <a href="#how-it-works" class="mobile-link" onclick="closeMob()">How It Works</a>
  <a href="#showcase"    class="mobile-link" onclick="closeMob()">Dashboard</a>
  <a href="#security"    class="mobile-link" onclick="closeMob()">Security</a>
  <div class="mobile-btns">
    <a href="<?= BASE_URL ?>pages/login.php"
       style="background:transparent;border:1.5px solid var(--border);color:var(--primary);">
      Sign In
    </a>
    <a href="<?= BASE_URL ?>pages/register.php"
       style="background:var(--primary);color:#fff;">
      Get Started
    </a>
  </div>
</div>

<!-- ════════════════════════════════════════ HERO ════════════════════════════════════════════ -->
<section class="hero">
  <div class="container">
    <div class="hero-grid">

      <!-- Left: Copy -->
      <div>
        <div class="hero-eyebrow">
          <span class="hero-dot"></span>
          Enterprise Visitor Management
        </div>
        <h1 class="hero-title">
          Secure Every<br>
          Visitor, Every<br>
          <span class="accent">Visit.</span>
        </h1>
        <p class="hero-desc">
          The all-in-one platform to manage, track, and secure your facility visitors.
          QR check-in, live dashboard, analytics, blacklist alerts — all in one place.
        </p>
        <div class="hero-cta">
          <a href="<?= BASE_URL ?>pages/register.php" class="btn btn-accent btn-lg">
            <i class="bi bi-rocket-takeoff-fill"></i> Get Started Free
          </a>
          <a href="<?= BASE_URL ?>pages/login.php" class="btn btn-ghost btn-lg">
            <i class="bi bi-box-arrow-in-right"></i> Sign In
          </a>
        </div>
        <div class="hero-proof">
          <div class="proof-avatars">
            <div class="proof-avatar">A</div>
            <div class="proof-avatar" style="background:rgba(0,180,216,.3);">R</div>
            <div class="proof-avatar" style="background:rgba(46,117,182,.35);">S</div>
            <div class="proof-avatar" style="background:rgba(92,200,232,.25);">M</div>
          </div>
          <p class="proof-text">
            Trusted by <strong>500+ organizations</strong> to manage their visitors every day
          </p>
        </div>
      </div>

      <!-- Right: Illustration -->
      <div class="hero-visual">
        <!-- Floating badge: top-left -->
        <div class="float-card float-card-1">
          <div class="float-icon" style="background:rgba(5,150,105,.1);">
            <i class="bi bi-person-check-fill" style="color:#059669;"></i>
          </div>
          <div>
            <div class="float-val">24 Checked In</div>
            <div class="float-lbl">Live · Right now</div>
          </div>
        </div>

        <!-- Main photo frame -->
        <div class="hero-frame">
          <!-- Browser-chrome top bar -->
          <div class="hero-frame-bar">
            <div class="hero-frame-dot" style="background:rgba(255,82,82,.75);"></div>
            <div class="hero-frame-dot" style="background:rgba(255,193,7,.75);"></div>
            <div class="hero-frame-dot" style="background:rgba(40,200,64,.75);"></div>
            <div style="flex:1;height:8px;background:rgba(255,255,255,.18);border-radius:4px;margin:0 10px;"></div>
            <div style="width:18px;height:18px;border-radius:50%;background:rgba(255,255,255,.18);flex-shrink:0;"></div>
          </div>
          <!-- Reception / visitor management photo -->
          <img src="<?= BASE_URL ?>assets/img/bg/hero-visual.jpg"
               alt="Professional visitor check-in at a modern reception desk"
               loading="eager">
          <!-- Live status caption -->
          <div class="hero-frame-caption">
            <span class="hero-frame-live-dot"></span>
            <span class="hero-frame-caption-text">Visitor check-in — Live view</span>
            <span class="hero-frame-caption-badge">&#10003; Active</span>
          </div>
        </div>

        <!-- Floating badge: bottom-right -->
        <div class="float-card float-card-2">
          <div class="float-icon" style="background:rgba(0,180,216,.12);">
            <i class="bi bi-qr-code-scan" style="color:#00b4d8;font-size:18px;"></i>
          </div>
          <div>
            <div class="float-val">QR Verified</div>
            <div class="float-lbl">Instant access</div>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ════════════════════════════════════════ STATS ════════════════════════════════════════════ -->
<div class="stats-bar">
  <div class="container">
    <div class="stats-bar-grid">
      <div class="stat-cell">
        <div class="stat-num">10K<em>+</em></div>
        <div class="stat-lbl">Visitors Managed Daily</div>
      </div>
      <div class="stat-cell">
        <div class="stat-num">99<em>.9%</em></div>
        <div class="stat-lbl">System Uptime</div>
      </div>
      <div class="stat-cell">
        <div class="stat-num">30<em>s</em></div>
        <div class="stat-lbl">Avg. Check-in Time</div>
      </div>
      <div class="stat-cell">
        <div class="stat-num">6<em>+</em></div>
        <div class="stat-lbl">Security Layers</div>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════ FEATURES ════════════════════════════════════════ -->
<section class="section" id="features">
  <div class="container">
    <div class="text-center">
      <div class="tag tag-blue"><i class="bi bi-grid-3x3-gap-fill"></i> Core Features</div>
      <h2 class="h2">Everything your team needs</h2>
      <p class="section-sub">Powerful, security-first features built for organizations that take visitor management seriously.</p>
    </div>

    <div class="feature-grid">

      <div class="feature-card">
        <div class="feat-icon" style="background:rgba(26,60,94,.08);">
          <i class="bi bi-qr-code-scan" style="color:var(--primary);"></i>
        </div>
        <h3 class="feat-title">QR &amp; Badge Check-in</h3>
        <p class="feat-desc">Instant check-in via QR code scan with automatic digital badge generation. No paperwork, no queues, no delays.</p>
      </div>

      <div class="feature-card">
        <div class="feat-icon" style="background:rgba(5,150,105,.08);">
          <i class="bi bi-activity" style="color:var(--success);"></i>
        </div>
        <h3 class="feat-title">Real-Time Tracking</h3>
        <p class="feat-desc">See who is on your premises right now. Live dashboard updates every second with check-in and check-out events.</p>
      </div>

      <div class="feature-card">
        <div class="feat-icon" style="background:rgba(2,132,199,.08);">
          <i class="bi bi-calendar-check" style="color:#0284c7;"></i>
        </div>
        <h3 class="feat-title">Appointment Scheduling</h3>
        <p class="feat-desc">Pre-register visitors with appointment slots. Automated email reminders sent to hosts and visitors before arrival.</p>
      </div>

      <div class="feature-card">
        <div class="feat-icon" style="background:rgba(0,180,216,.08);">
          <i class="bi bi-bar-chart-line-fill" style="color:var(--accent);"></i>
        </div>
        <h3 class="feat-title">Analytics &amp; Reports</h3>
        <p class="feat-desc">Visual dashboards with peak-hour analysis, department breakdowns, and one-click PDF report exports for compliance.</p>
      </div>

      <div class="feature-card">
        <div class="feat-icon" style="background:rgba(220,38,38,.07);">
          <i class="bi bi-slash-circle-fill" style="color:var(--danger);"></i>
        </div>
        <h3 class="feat-title">Blacklist &amp; Watchlist</h3>
        <p class="feat-desc">Automatic CNIC-based screening on every check-in. Instant alerts push to security staff when flagged visitors arrive.</p>
      </div>

      <div class="feature-card">
        <div class="feat-icon" style="background:rgba(139,92,246,.08);">
          <i class="bi bi-tablet-landscape-fill" style="color:var(--purple);"></i>
        </div>
        <h3 class="feat-title">Self-Service Kiosk</h3>
        <p class="feat-desc">Touch-screen kiosk with webcam photo capture. Visitors self-register and check in without any staff assistance.</p>
      </div>

    </div>
  </div>
</section>

<!-- ════════════════════════════════════════ HOW IT WORKS ════════════════════════════════════ -->
<section id="how-it-works" class="steps-section">
  <div class="container">
    <div class="text-center" style="position:relative;z-index:1;">
      <div class="tag tag-white"><i class="bi bi-lightning-charge-fill"></i> How It Works</div>
      <h2 class="h2 h2-white">Up and running in minutes</h2>
      <p class="section-sub section-sub-white">Three simple steps — from visitor arrival to access granted.</p>
    </div>
    <div class="steps-grid">

      <div class="step-card">
        <div class="step-num">1</div>
        <h3 class="step-title">Register</h3>
        <p class="step-desc">Staff register the visitor — or visitors self-register at the kiosk. Full name, CNIC, photo, host name, and purpose are captured securely.</p>
      </div>

      <div class="step-card">
        <div class="step-num">2</div>
        <h3 class="step-title">Verify &amp; Check In</h3>
        <p class="step-desc">QR code or badge scan triggers an instant blacklist check. A digital badge is generated and the host receives an automatic email notification.</p>
      </div>

      <div class="step-card">
        <div class="step-num">3</div>
        <h3 class="step-title">Track &amp; Report</h3>
        <p class="step-desc">Monitor every live visit on the command dashboard. Export audit-ready PDF reports and analyze visitor trends over time.</p>
      </div>

    </div>
  </div>
</section>

<!-- ════════════════════════════════════════ SHOWCASE ════════════════════════════════════════ -->
<section class="section" id="showcase">
  <div class="container">
    <div class="showcase-grid">

      <!-- Left: copy -->
      <div>
        <div class="tag tag-cyan"><i class="bi bi-speedometer2"></i> Live Dashboard</div>
        <h2 class="h2">A command center<br>for your security team</h2>
        <p class="section-sub" style="max-width:100%;">
          The SVMS dashboard gives every team member exactly the information they need — real-time, role-specific, and beautifully organized.
        </p>
        <div class="spotlight-list">
          <div class="sl-item">
            <div class="sl-icon" style="background:rgba(26,60,94,.08);">
              <i class="bi bi-people-fill" style="color:var(--primary);"></i>
            </div>
            <div>
              <div class="sl-title">Live visitor count &amp; status</div>
              <div class="sl-desc">Know exactly who is on premises at any moment with live check-in and check-out tracking.</div>
            </div>
          </div>
          <div class="sl-item">
            <div class="sl-icon" style="background:rgba(0,180,216,.08);">
              <i class="bi bi-bell-fill" style="color:var(--accent);"></i>
            </div>
            <div>
              <div class="sl-title">Instant security notifications</div>
              <div class="sl-desc">Blacklist matches, emergency alerts, and unusual patterns surface immediately in the notification panel.</div>
            </div>
          </div>
          <div class="sl-item">
            <div class="sl-icon" style="background:rgba(5,150,105,.08);">
              <i class="bi bi-graph-up-arrow" style="color:var(--success);"></i>
            </div>
            <div>
              <div class="sl-title">Visitor trend analytics</div>
              <div class="sl-desc">Chart volume by day, time, department, or purpose — and download full PDF audit reports in one click.</div>
            </div>
          </div>
        </div>
        <div style="margin-top:34px;">
          <a href="<?= BASE_URL ?>pages/login.php" class="btn btn-primary">
            <i class="bi bi-arrow-right-circle-fill"></i> Open Live Dashboard
          </a>
        </div>
      </div>

      <!-- Right: dashboard mockup -->
      <div>
        <div class="mockup-wrap">
          <!-- Browser bar -->
          <div class="m-bar">
            <div class="m-dot" style="background:#ff5f57;"></div>
            <div class="m-dot" style="background:#febc2e;"></div>
            <div class="m-dot" style="background:#28c840;"></div>
            <div class="m-url">localhost/pages/dashboard.php</div>
          </div>
          <!-- Body -->
          <div class="m-body">
            <!-- Top navbar strip -->
            <div class="m-nav">
              <div class="m-nav-pill" style="width:90px;"></div>
              <div style="flex:1;height:8px;background:rgba(255,255,255,.15);border-radius:4px;"></div>
              <div style="width:22px;height:22px;border-radius:50%;background:rgba(255,255,255,.22);flex-shrink:0;"></div>
            </div>
            <!-- Sidebar + content -->
            <div class="m-layout">
              <!-- Sidebar -->
              <div class="m-sidebar">
                <div class="m-sb-item on"></div>
                <div class="m-sb-item"></div>
                <div class="m-sb-item"></div>
                <div class="m-sb-item"></div>
                <div class="m-sb-item"></div>
                <div class="m-sb-item"></div>
              </div>
              <!-- Main content -->
              <div class="m-content">
                <!-- Welcome banner -->
                <div class="m-banner">
                  <div style="flex:1;">
                    <div class="m-banner-line" style="width:110px;"></div>
                    <div class="m-banner-sub"></div>
                    <div class="m-banner-btns">
                      <div class="m-banner-btn" style="width:56px;"></div>
                      <div class="m-banner-btn" style="width:44px;background:rgba(0,180,216,.35);"></div>
                    </div>
                  </div>
                  <div style="width:62px;height:42px;overflow:hidden;opacity:.7;flex-shrink:0;">
                    <img src="<?= BASE_URL ?>assets/img/dashboard-hero.svg"
                         style="width:82px;height:auto;margin-top:-4px;filter:brightness(1.3);" alt="">
                  </div>
                </div>
                <!-- Stats row -->
                <div class="m-stats">
                  <div class="m-stat">
                    <div class="m-stat-n" style="background:var(--primary);"></div>
                    <div class="m-stat-l"></div>
                  </div>
                  <div class="m-stat">
                    <div class="m-stat-n" style="background:var(--success);width:35%;"></div>
                    <div class="m-stat-l"></div>
                  </div>
                  <div class="m-stat">
                    <div class="m-stat-n" style="background:var(--purple);width:55%;"></div>
                    <div class="m-stat-l"></div>
                  </div>
                </div>
                <!-- Chart -->
                <div class="m-chart">
                  <div class="m-bar-item" style="height:42%;background:#e2e8f0;"></div>
                  <div class="m-bar-item" style="height:62%;background:#cbd5e1;"></div>
                  <div class="m-bar-item" style="height:82%;background:#2e75b6;"></div>
                  <div class="m-bar-item" style="height:55%;background:#cbd5e1;"></div>
                  <div class="m-bar-item" style="height:94%;background:#00b4d8;"></div>
                  <div class="m-bar-item" style="height:72%;background:#2e75b6;"></div>
                  <div class="m-bar-item" style="height:48%;background:#e2e8f0;"></div>
                </div>
                <!-- Table -->
                <div class="m-table">
                  <div class="m-th">
                    <div class="m-tc" style="width:36%;"></div>
                    <div class="m-tc" style="width:26%;"></div>
                    <div class="m-tc" style="width:20%;"></div>
                  </div>
                  <?php for ($i = 0; $i < 3; $i++): ?>
                  <div class="m-tr">
                    <div style="flex:1;height:7px;background:#e8ecf1;border-radius:2px;"></div>
                    <div style="flex:.7;height:7px;background:#eef1f5;border-radius:2px;"></div>
                    <div class="m-badge" style="background:rgba(5,150,105,.18);"></div>
                  </div>
                  <?php endfor; ?>
                </div>
              </div><!-- /.m-content -->
            </div><!-- /.m-layout -->
          </div><!-- /.m-body -->
        </div><!-- /.mockup-wrap -->
      </div>

    </div>
  </div>
</section>

<!-- ════════════════════════════════════════ SECURITY ════════════════════════════════════════ -->
<section class="section security-section" id="security">
  <div class="container">
    <div class="text-center">
      <div class="tag tag-red"><i class="bi bi-shield-check-fill"></i> Security</div>
      <h2 class="h2">Built for security-conscious teams</h2>
      <p class="section-sub">Enterprise-grade protection for your premises, your data, and your people.</p>
    </div>
    <div class="security-grid">

      <div class="sec-card">
        <div class="sec-icon"><i class="bi bi-person-badge-fill"></i></div>
        <h4 class="sec-title">Role-Based Access Control</h4>
        <p class="sec-desc">Granular permissions for Super Admin, Receptionist, Security Guard, and fully custom roles.</p>
      </div>

      <div class="sec-card">
        <div class="sec-icon"><i class="bi bi-shield-lock-fill"></i></div>
        <h4 class="sec-title">Two-Factor Authentication</h4>
        <p class="sec-desc">Email OTP verification for every staff login. Protect your dashboard even if credentials are compromised.</p>
      </div>

      <div class="sec-card">
        <div class="sec-icon"><i class="bi bi-journal-text"></i></div>
        <h4 class="sec-title">Full Audit Trail</h4>
        <p class="sec-desc">Every action is logged with timestamp and responsible user. Perfect for compliance audits and internal investigations.</p>
      </div>

      <div class="sec-card">
        <div class="sec-icon"><i class="bi bi-exclamation-octagon-fill"></i></div>
        <h4 class="sec-title">Emergency Control</h4>
        <p class="sec-desc">One-click facility lockdown or evacuation mode with a live visitor headcount for emergency responders.</p>
      </div>

    </div>
  </div>
</section>

<!-- ════════════════════════════════════════ CTA ═════════════════════════════════════════════ -->
<section class="cta-section">
  <div class="container">
    <div class="cta-inner">
      <h2 class="cta-title">Ready to secure your premises?</h2>
      <p class="cta-sub">
        Join hundreds of organizations that use SVMS to manage visitors, ensure security,
        and gain real actionable insights.
      </p>
      <div class="cta-btns">
        <a href="<?= BASE_URL ?>pages/register.php" class="btn btn-accent btn-lg">
          <i class="bi bi-rocket-takeoff-fill"></i> Create Your Account
        </a>
        <a href="<?= BASE_URL ?>pages/login.php" class="btn btn-ghost btn-lg">
          <i class="bi bi-box-arrow-in-right"></i> Sign In to Dashboard
        </a>
      </div>
      <p class="cta-note">Free to use &nbsp;&bull;&nbsp; No credit card required &nbsp;&bull;&nbsp; Set up in under 5 minutes</p>
    </div>
  </div>
</section>

<!-- ════════════════════════════════════════ FOOTER ══════════════════════════════════════════ -->
<footer>
  <div class="container">
    <div class="footer-grid">
      <!-- Brand -->
      <div>
        <div class="footer-brand">
          <img src="<?= BASE_URL ?>assets/img/logo.svg" alt="SVMS Logo">
          <span class="footer-brand-name"><?= e(SITE_NAME) ?></span>
        </div>
        <p class="footer-tagline">
          The professional visitor management system for modern, security-conscious organizations.
        </p>
      </div>
      <!-- Product -->
      <div>
        <div class="footer-h">Product</div>
        <a href="#features"    class="footer-a">Features</a>
        <a href="#how-it-works" class="footer-a">How It Works</a>
        <a href="#showcase"    class="footer-a">Dashboard</a>
        <a href="#security"    class="footer-a">Security</a>
      </div>
      <!-- Access -->
      <div>
        <div class="footer-h">Access</div>
        <a href="<?= BASE_URL ?>pages/login.php"           class="footer-a">Sign In</a>
        <a href="<?= BASE_URL ?>pages/register.php"        class="footer-a">Register</a>
        <a href="<?= BASE_URL ?>kiosk/"                    class="footer-a">Kiosk Mode</a>
        <a href="<?= BASE_URL ?>pages/feedback_public.php" class="footer-a">Submit Feedback</a>
      </div>
      <!-- System -->
      <div>
        <div class="footer-h">System</div>
        <a href="<?= BASE_URL ?>pages/dashboard.php"          class="footer-a">Dashboard</a>
        <a href="<?= BASE_URL ?>pages/analytics.php"          class="footer-a">Analytics</a>
        <a href="<?= BASE_URL ?>pages/appointments.php"       class="footer-a">Appointments</a>
        <a href="<?= BASE_URL ?>pages/register_visitor.php"   class="footer-a">Register Visitor</a>
      </div>
    </div>

    <div class="footer-bottom">
      <p class="footer-copy">&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>. Built for security and simplicity.</p>
      <div class="footer-pills">
        <span class="footer-pill"><i class="bi bi-shield-check" style="margin-right:4px;"></i>Secure</span>
        <span class="footer-pill"><i class="bi bi-lightning-charge" style="margin-right:4px;"></i>Fast</span>
        <span class="footer-pill"><i class="bi bi-phone" style="margin-right:4px;"></i>PWA Ready</span>
      </div>
    </div>
  </div>
</footer>

<script>
// ── Sticky nav ────────────────────────────────────────────────
var nav = document.getElementById('mainNav');
window.addEventListener('scroll', function () {
  nav.classList.toggle('scrolled', window.scrollY > 55);
}, { passive: true });

// ── Mobile menu ───────────────────────────────────────────────
var burger = document.getElementById('burgerBtn');
var mob    = document.getElementById('mobileMenu');

burger.addEventListener('click', function () {
  var open = mob.classList.toggle('open');
  burger.innerHTML = open ? '<i class="bi bi-x-lg"></i>' : '<i class="bi bi-list"></i>';
});

function closeMob() {
  mob.classList.remove('open');
  burger.innerHTML = '<i class="bi bi-list"></i>';
}

document.addEventListener('click', function (e) {
  if (!nav.contains(e.target) && !mob.contains(e.target)) closeMob();
});

// ── Smooth scroll for anchor links ────────────────────────────
document.querySelectorAll('a[href^="#"]').forEach(function (a) {
  a.addEventListener('click', function (e) {
    var id = this.getAttribute('href').slice(1);
    var el = document.getElementById(id);
    if (el) {
      e.preventDefault();
      var top = el.getBoundingClientRect().top + window.scrollY - 80;
      window.scrollTo({ top: top, behavior: 'smooth' });
      closeMob();
    }
  });
});
</script>
</body>
</html>
