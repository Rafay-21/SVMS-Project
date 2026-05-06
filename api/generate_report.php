<?php
/**
 * api/generate_report.php — PDF Report Generator (Phase 5.2)
 *
 * POST (application/json or multipart/form-data):
 * {
 *   csrf_token   : string,
 *   from         : "YYYY-MM-DD",
 *   to           : "YYYY-MM-DD",
 *   title        : string  (optional, default "Visitor Activity Report")
 *   notes        : string  (optional)
 *   include_charts : "1"|"0"  (default "1")
 *   include_raw    : "1"|"0"  (default "1")
 *   report_type    : "analytics"|"visit_list"  (default "analytics")
 *   charts  : {                (when include_charts=1)
 *     heatmap  : "base64...",
 *     dept     : "base64...",
 *     purpose  : "base64...",
 *     trend    : "base64..."
 *   }
 *   kpi     : { ... }          (KPI object from analytics_data.php)
 *   top_visitors : [ ... ]
 *   departments  : [ ... ]
 *   trend        : [ ... ]
 *
 *   // for report_type=visit_list only:
 *   filters_label: string
 * }
 *
 * Returns: { ok: true, url: "http://…/logs/reports/report_….pdf" }
 *
 * Files are saved under LOG_DIR/reports/ and older than 30 days
 * are pruned by cron housekeeping.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/PdfReportBuilder.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// ── Auth ──────────────────────────────────────────────────────────────────────
if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

// ── Rate limit: 5 reports/min per admin ───────────────────────────────────────
if (!rl_check('rpt:' . (int)$_SESSION['admin_id'], 5)) {
    rl_abort();
}

// ── Accept both JSON body and multipart/form-data ────────────────────────────
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (str_contains($contentType, 'application/json')) {
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? [];
} else {
    $input = $_POST;
    // Charts arrive as separate POST fields when sent as form-data
    if (!empty($_POST['charts'])) {
        $chartsDecoded = json_decode($_POST['charts'], true) ?? [];
        $input['charts'] = $chartsDecoded;
    }
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
$csrf = $input['csrf_token'] ?? ($input['csrf'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF token mismatch']);
    exit;
}

// ── Input validation ──────────────────────────────────────────────────────────
$from  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['from'] ?? '') ? $input['from'] : date('Y-m-d', strtotime('-29 days'));
$to    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['to']   ?? '') ? $input['to']   : date('Y-m-d');
if ($from > $to) [$from, $to] = [$to, $from];

$title         = trim(strip_tags($input['title']         ?? 'Visitor Activity Report'));
$notes         = trim(strip_tags($input['notes']         ?? ''));
$includeCharts = !empty($input['include_charts']) && $input['include_charts'] !== '0';
$includeRaw    = !empty($input['include_raw'])    && $input['include_raw']    !== '0';
$reportType    = in_array($input['report_type'] ?? '', ['analytics','visit_list'])
    ? $input['report_type'] : 'analytics';

if (!$title) $title = 'Visitor Activity Report';
$title = substr($title, 0, 200);
$notes = substr($notes, 0, 1000);

// ── Pull analytics data fresh (or from submitted payload) ─────────────────────
$kpi           = $input['kpi']          ?? null;
$departments   = $input['departments']  ?? [];
$purposes      = $input['purposes']     ?? [];
$trend         = $input['trend']        ?? [];
$topVisitors   = $input['top_visitors'] ?? [];
$charts        = $input['charts']       ?? [];

// If KPI was not submitted, query it now
if ($kpi === null) {
    require_once __DIR__ . '/../includes/db_functions.php';
    $kpi_row = query_one(
        "SELECT
            COUNT(*)                                   AS total_visits,
            COUNT(DISTINCT visitor_id)                 AS unique_visitors,
            AVG(TIMESTAMPDIFF(MINUTE, check_in_time, COALESCE(check_out_time, NOW()))) AS avg_duration_min
         FROM visit_log
         WHERE check_in_time BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)",
        'ss', [$from, $to]
    ) ?? [];

    $peak_row = query_one(
        "SELECT HOUR(check_in_time) AS hr
         FROM visit_log
         WHERE check_in_time BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
         GROUP BY hr ORDER BY COUNT(*) DESC LIMIT 1",
        'ss', [$from, $to]
    );

    $kpi = [
        'total_visits'     => (int)($kpi_row['total_visits']    ?? 0),
        'unique_visitors'  => (int)($kpi_row['unique_visitors'] ?? 0),
        'avg_duration_min' => (float)round($kpi_row['avg_duration_min'] ?? 0, 1),
        'peak_hour'        => $peak_row ? (int)$peak_row['hr'] : null,
    ];
}

// If departments were not submitted, query top 10
if (empty($departments)) {
    $departments = query_all(
        "SELECT d.id, COALESCE(d.name,'Unassigned') AS name, COALESCE(d.colour,'#2e75b6') AS colour, COUNT(vl.id) AS visits
         FROM visit_log vl LEFT JOIN departments d ON d.id = vl.department_id
         WHERE vl.check_in_time BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
         GROUP BY d.id ORDER BY visits DESC LIMIT 10",
        'ss', [$from, $to]
    ) ?? [];
}

// If top visitors were not submitted, query
if (empty($topVisitors)) {
    $topVisitors = query_all(
        "SELECT vl.visitor_id, vis.full_name, COUNT(vl.id) AS visits,
                MAX(vl.check_in_time) AS last_visit,
                SUM(TIMESTAMPDIFF(MINUTE, vl.check_in_time, COALESCE(vl.check_out_time, NOW()))) AS total_min
         FROM visit_log vl JOIN visitors vis ON vis.id = vl.visitor_id
         WHERE vl.check_in_time BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
         GROUP BY vl.visitor_id, vis.full_name
         ORDER BY visits DESC LIMIT 10",
        'ss', [$from, $to]
    ) ?? [];
}

// Recent visits for raw data section (last 50 of the period)
$recentVisits = [];
if ($includeRaw || $reportType === 'visit_list') {
    $maxRaw = 50;
    if ($reportType === 'visit_list') $maxRaw = 200;

    $extraWhere = '';
    $extraTypes = 'ss';
    $extraParams = [$from, $to];

    if (!empty($input['filters_dept'])) {
        $extraWhere .= ' AND vl.department_id = ?';
        $extraTypes  .= 'i';
        $extraParams[] = (int)$input['filters_dept'];
    }
    if (!empty($input['filters_status'])) {
        $safeStatus = in_array($input['filters_status'], ['checked_in','checked_out','no_show'])
            ? $input['filters_status'] : null;
        if ($safeStatus) {
            $extraWhere .= " AND vl.status = '$safeStatus'";
        }
    }

    $recentVisits = query_all(
        "SELECT vl.id, vis.full_name, v2.phone, vl.badge_number,
                COALESCE(d.name,'—') AS dept_name, vl.person_to_meet, vl.purpose,
                vl.visitor_type, vl.status,
                vl.check_in_time, vl.check_out_time,
                TIMESTAMPDIFF(MINUTE, vl.check_in_time, COALESCE(vl.check_out_time, NOW())) AS duration_min
         FROM visit_log vl
         JOIN visitors vis ON vis.id = vl.visitor_id
         JOIN visitors v2  ON v2.id  = vl.visitor_id
         LEFT JOIN departments d ON d.id = vl.department_id
         WHERE vl.check_in_time BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
         $extraWhere
         ORDER BY vl.check_in_time DESC LIMIT $maxRaw",
        $extraTypes, $extraParams
    ) ?? [];
}

// ── Auto-narrative helper ─────────────────────────────────────────────────────
function _build_narrative(array $kpi, array $trend, string $from, string $to): string
{
    $total  = number_format($kpi['total_visits'] ?? 0);
    $unique = number_format($kpi['unique_visitors'] ?? 0);
    $dur    = (int)($kpi['avg_duration_min'] ?? 0);
    $durStr = $dur >= 60
        ? floor($dur / 60) . 'h ' . ($dur % 60) . 'min'
        : $dur . ' min';

    $line1 = "During the period $from to $to, the facility recorded a total of "
           . "$total visits from $unique unique visitors, with an average visit duration of $durStr.";

    $line2 = '';
    if (!empty($trend)) {
        $peak = max(array_column($trend, 'visits'));
        $peakDay = '';
        foreach ($trend as $t) {
            if ((int)$t['visits'] === $peak) {
                $peakDay = $t['date'] ?? '';
                break;
            }
        }
        if ($peakDay && $peak > 0) {
            $line2 = " Peak traffic was recorded on $peakDay with $peak visits.";
        }
    }

    $line3 = '';
    if (isset($kpi['peak_hour']) && $kpi['peak_hour'] !== null) {
        $ph   = (int)$kpi['peak_hour'];
        $end  = ($ph + 1) % 24;
        $line3 = " The busiest hour was " . str_pad($ph, 2, '0', STR_PAD_LEFT) . ':00–'
               . str_pad($end, 2, '0', STR_PAD_LEFT) . ':59.';
    }

    return $line1 . $line2 . $line3;
}

// ── Ensure reports directory ──────────────────────────────────────────────────
$reportsDir = LOG_DIR . '/reports';
if (!is_dir($reportsDir)) mkdir($reportsDir, 0750, true);

// ── Filename ──────────────────────────────────────────────────────────────────
$adminId  = (int)$_SESSION['admin_id'];
$stamp    = date('Ymd_His');
$filename = 'report_' . $stamp . '_' . $adminId . '.pdf';
$outPath  = $reportsDir . '/' . $filename;

// ── Build PDF ─────────────────────────────────────────────────────────────────
try {
    $builder = new PdfReportBuilder($title);
    $dateRangeLabel = date('M j, Y', strtotime($from)) . ' – ' . date('M j, Y', strtotime($to));

    /* ── 1. Cover page ──────────────────────────────────────── */
    $builder->cover($title, '', $dateRangeLabel, $notes);

    /* ── 2. Executive Summary ───────────────────────────────── */
    $builder->section('Executive Summary', true);

    // KPI grid
    $peak_h = $kpi['peak_hour'] ?? null;
    $peakStr = $peak_h !== null
        ? str_pad($peak_h, 2, '0', STR_PAD_LEFT) . ':00'
        : '—';

    $deltaTotal = $kpi['delta_total'] ?? null;
    $deltaU     = $kpi['delta_unique'] ?? null;
    $deltaDur   = $kpi['delta_duration'] ?? null;

    $builder->kpiGrid([
        [
            'label'     => 'Total Visits',
            'value'     => number_format($kpi['total_visits'] ?? 0),
            'delta'     => $deltaTotal && $deltaTotal['pct'] !== null
                ? abs($deltaTotal['pct']) . '%'
                : null,
            'delta_dir' => $deltaTotal['dir'] ?? 'neutral',
        ],
        [
            'label'     => 'Unique Visitors',
            'value'     => number_format($kpi['unique_visitors'] ?? 0),
            'delta'     => $deltaU && $deltaU['pct'] !== null
                ? abs($deltaU['pct']) . '%'
                : null,
            'delta_dir' => $deltaU['dir'] ?? 'neutral',
        ],
        [
            'label'     => 'Avg Duration',
            'value'     => _fmt_duration((int)($kpi['avg_duration_min'] ?? 0)),
            'delta'     => $deltaDur && $deltaDur['pct'] !== null
                ? abs($deltaDur['pct']) . '%'
                : null,
            'delta_dir' => $deltaDur['dir'] ?? 'neutral',
        ],
        [
            'label' => 'Peak Hour',
            'value' => $peakStr,
            'delta' => null,
        ],
    ]);

    // Narrative text
    $narrative = _build_narrative($kpi, $trend, $from, $to);
    $builder->paragraph($narrative);

    /* ── 3. Charts ───────────────────────────────────────────── */
    if ($includeCharts && !empty($charts)) {
        $builder->section('Visual Analysis', true);

        $chartDefs = [
            'heatmap' => 'Peak Hours Heatmap — Visitor activity by day of week and hour of day.',
            'dept'    => 'Top Departments — Departments receiving the most visitors during this period.',
            'purpose' => 'Visit Purposes — Breakdown of visit purposes across all recorded visits.',
            'trend'   => 'Daily Trend — Total visits per day with 7-day moving average overlay.',
        ];

        foreach ($chartDefs as $key => $caption) {
            if (!empty($charts[$key])) {
                // Strip data URI prefix if present
                $b64 = $charts[$key];
                if (str_starts_with($b64, 'data:')) {
                    $b64 = substr($b64, strpos($b64, ',') + 1);
                }
                $builder->chartImage($b64, $caption, 85);
            }
        }
    }

    /* ── 4. Top Visitors table ───────────────────────────────── */
    if (!empty($topVisitors)) {
        $builder->section('Top 10 Visitors', true);

        $tvHeaders = ['#', 'Visitor Name', 'Visits', 'Last Visit', 'Total Time'];
        $tvWidths  = [10, 65, 20, 45, 34];
        $tvAligns  = ['C', 'L', 'C', 'L', 'R'];
        $tvRows = [];
        foreach (array_slice($topVisitors, 0, 10) as $i => $v) {
            $tvRows[] = [
                $i + 1,
                $v['full_name'] ?? '—',
                $v['visits']    ?? 0,
                !empty($v['last_visit'])
                    ? date('M j, Y', strtotime($v['last_visit']))
                    : '—',
                _fmt_duration((int)($v['total_min'] ?? 0)),
            ];
        }
        $builder->table($tvHeaders, $tvRows, [
            'col_widths' => $tvWidths,
            'aligns'     => $tvAligns,
        ]);
    }

    /* ── 5. Top Departments table ────────────────────────────── */
    if (!empty($departments)) {
        $builder->section('Department Breakdown', false);

        $dHeaders = ['Department', 'Visits'];
        $dWidths  = [130, 44];
        $dAligns  = ['L', 'R'];
        $dRows    = [];
        foreach (array_slice($departments, 0, 10) as $d) {
            $dRows[] = [
                $d['name']   ?? 'Unassigned',
                (int)($d['visits'] ?? 0),
            ];
        }
        $builder->table($dHeaders, $dRows, [
            'col_widths' => $dWidths,
            'aligns'     => $dAligns,
        ]);
    }

    /* ── 6. Raw visits table ──────────────────────────────────── */
    if ($includeRaw && !empty($recentVisits)) {
        $label = ($reportType === 'visit_list')
            ? 'Filtered Visit List'
            : 'Recent Visits (last ' . count($recentVisits) . ')';

        $builder->section($label, true);

        if ($reportType === 'visit_list' && !empty($input['filters_label'])) {
            $builder->paragraph('Filters: ' . strip_tags($input['filters_label']));
        }

        $rHeaders = ['Visitor', 'Department', 'Purpose', 'Check-In', 'Duration', 'Status'];
        $rWidths  = [40, 35, 30, 38, 18, 13];
        $rAligns  = ['L', 'L', 'L', 'L', 'R', 'C'];
        $rRows    = [];
        foreach ($recentVisits as $rv) {
            $dur = (int)($rv['duration_min'] ?? 0);
            $rRows[] = [
                substr($rv['full_name'] ?? '—', 0, 28),
                substr($rv['dept_name'] ?? '—', 0, 22),
                substr($rv['purpose']   ?? '—', 0, 20),
                $rv['check_in_time']
                    ? date('M j g:i A', strtotime($rv['check_in_time']))
                    : '—',
                _fmt_duration($dur),
                ucfirst(str_replace('_', ' ', $rv['status'] ?? '')),
            ];
        }
        $builder->table($rHeaders, $rRows, [
            'col_widths' => $rWidths,
            'aligns'     => $rAligns,
            'row_h'      => 5.5,
        ]);
    }

    /* ── Output ──────────────────────────────────────────────── */
    $builder->output($outPath);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'PDF generation failed: ' . $e->getMessage()]);
    exit;
}

// ── Log + respond ─────────────────────────────────────────────────────────────
log_action('report_generated', 0, json_encode([
    'filename' => $filename,
    'type'     => $reportType,
    'from'     => $from,
    'to'       => $to,
    'size'     => file_exists($outPath) ? filesize($outPath) : 0,
]));

$reportUrl = BASE_URL . 'api/download_report.php?file=' . rawurlencode($filename);

echo json_encode([
    'ok'       => true,
    'url'      => $reportUrl,
    'filename' => $filename,
]);

// ── Helper ────────────────────────────────────────────────────────────────────
function _fmt_duration(int $min): string
{
    if ($min <= 0) return '—';
    $h = (int)floor($min / 60);
    $m = $min % 60;
    return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
}
