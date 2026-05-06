<?php
/**
 * includes/i18n.php — SVMS Internationalisation v2.0
 *
 * Lang detection priority (highest → lowest):
 *   1. ?lang= query param  (sets session + DB)
 *   2. $_SESSION['lang']
 *   3. admin.language column (loaded after auth)
 *   4. $_COOKIE['svms_lang']
 *   5. DEFAULT_LANG constant
 *
 * Exposes:
 *   init_i18n()              — call once per request (auto-called here)
 *   t($key, $vars=[])        — translate with {{name}} substitution
 *   t_plural($key, $n, $v)   — pluralise (key.singular / key.plural)
 *   current_lang()           — returns 'en' | 'ur'
 *   is_rtl()                 — returns bool
 *   date_fmt($ts)            — locale-aware date string
 *   time_fmt($ts)            — locale-aware time string
 */

$LANG              = [];
$_i18n_initialised = false;

function init_i18n(): void
{
    global $LANG, $_i18n_initialised, $_SESSION;

    if ($_i18n_initialised) return;
    $_i18n_initialised = true;

    $supported = ['en', 'ur'];

    // Priority 1 — ?lang= query param
    if (!empty($_GET['lang']) && in_array($_GET['lang'], $supported, true)) {
        $lang = $_GET['lang'];
        $_SESSION['lang'] = $lang;
        setcookie('svms_lang', $lang, time() + 365 * 86400, '/', '', false, true);
        // Persist to DB when admin is logged in
        if (!empty($_SESSION['admin_id'])) {
            try {
                query_exec('UPDATE admins SET language=? WHERE id=?', 'si',
                    [$lang, (int)$_SESSION['admin_id']]);
            } catch (\Throwable $ignored) {}
        }
    }

    // Priority 2 — session
    $lang = $_SESSION['lang'] ?? null;

    // Priority 3 — DB (if admin logged in and session has no lang yet)
    if (!$lang && !empty($_SESSION['admin_id'])) {
        try {
            $row = query_one('SELECT language, theme FROM admins WHERE id=?', 'i',
                [(int)$_SESSION['admin_id']]);
            if ($row) {
                if (!empty($row['language'])) {
                    $lang = $row['language'];
                    $_SESSION['lang'] = $lang;
                }
                // Hydrate theme preference from DB into session
                if (!empty($row['theme']) && empty($_SESSION['theme'])) {
                    $_SESSION['theme'] = $row['theme'];
                }
            }
        } catch (\Throwable $ignored) {}
    }

    // Priority 4 — cookie
    if (!$lang && !empty($_COOKIE['svms_lang'])) {
        $lang = $_COOKIE['svms_lang'];
    }

    // Priority 5 — default
    if (!$lang) {
        $lang = defined('DEFAULT_LANG') ? DEFAULT_LANG : 'en';
    }

    if (!in_array($lang, $supported, true)) {
        $lang = 'en';
    }

    $_SESSION['lang'] = $lang;

    // Load language file
    $file = __DIR__ . '/lang/' . $lang . '.php';
    if (!file_exists($file)) {
        $file = __DIR__ . '/lang/en.php';
    }
    require_once $file;
}

// Auto-initialise on include
init_i18n();

/* ── Core translation function ──────────────────────────────── */

/**
 * Translate a key with optional {{var}} substitutions.
 * Falls back to the key itself if not found.
 */
function t(string $key, array $vars = []): string
{
    global $LANG;
    $str = $LANG[$key] ?? $key;
    foreach ($vars as $k => $v) {
        $str = str_replace('{{' . $k . '}}', htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'), $str);
    }
    return $str;
}

/**
 * Pluralise: uses key.singular for $n===1, key.plural otherwise.
 */
function t_plural(string $key, int $n, array $vars = []): string
{
    $vars['count'] = $n;
    return t($n === 1 ? $key . '.singular' : $key . '.plural', $vars);
}

/* ── Query helpers ──────────────────────────────────────────── */

function current_lang(): string
{
    return $_SESSION['lang'] ?? (defined('DEFAULT_LANG') ? DEFAULT_LANG : 'en');
}

function is_rtl(): bool
{
    return current_lang() === 'ur';
}

/**
 * Locale-aware date formatting.
 * English: 'Mon, 26 Apr 2026'  |  Urdu: 'پیر، 26 اپریل 2026'
 */
function date_fmt(int $ts = 0): string
{
    if ($ts === 0) $ts = time();
    if (current_lang() === 'ur') {
        $days = ['اتوار','پیر','منگل','بدھ','جمعرات','جمعہ','ہفتہ'];
        $months = ['جنوری','فروری','مارچ','اپریل','مئی','جون',
                   'جولائی','اگست','ستمبر','اکتوبر','نومبر','دسمبر'];
        return $days[(int)date('w', $ts)] . '، ' .
               (int)date('j', $ts) . ' ' .
               $months[(int)date('n', $ts) - 1] . ' ' .
               date('Y', $ts);
    }
    return date('D, j M Y', $ts);
}

/**
 * Locale-aware time formatting (12-hour + am/pm).
 */
function time_fmt(int $ts = 0): string
{
    if ($ts === 0) $ts = time();
    $hour   = (int)date('g', $ts);
    $min    = date('i', $ts);
    $isAm   = date('A', $ts) === 'AM';
    if (current_lang() === 'ur') {
        $amStr = $isAm ? 'صبح' : 'شام';
        return $hour . ':' . $min . ' ' . $amStr;
    }
    return date('g:i A', $ts);
}

/* ── Backward-compat shims ──────────────────────────────────── */

/** @deprecated  Use t() instead */
function __t(string $key, array $replacements = []): string
{
    global $LANG;
    $str = $LANG[$key] ?? $key;
    // Support old :variable syntax
    foreach ($replacements as $k => $v) {
        $str = str_replace(':' . $k, htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'), $str);
    }
    return $str;
}

/** @deprecated  Use t() with echo instead */
function _t(string $key, array $replacements = []): void
{
    echo __t($key, $replacements);
}
