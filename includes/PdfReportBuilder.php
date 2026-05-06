<?php
/**
 * includes/PdfReportBuilder.php — Branded PDF Report Builder (Phase 5.2)
 *
 * Wraps TCPDF to produce publication-quality, paginated PDF reports.
 *
 * Requires: composer require tecnickcom/tcpdf
 *
 * Usage:
 *   $pdf = new PdfReportBuilder('Visitor Activity Report');
 *   $pdf->cover('Title', 'Subtitle', 'Jan 1 – Jan 31, 2026');
 *   $pdf->section('Executive Summary');
 *   $pdf->kpiGrid($kpiArray);
 *   $pdf->chartImage($base64png, 'Caption text');
 *   $pdf->table(['Col A','Col B'], $rows);
 *   $pdf->output('/path/to/file.pdf');
 */

if (!defined('SVMS_TCPDF_LOADED')) {
    $tcpdf_autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($tcpdf_autoload)) {
        throw new RuntimeException(
            'TCPDF not found. Run: composer require tecnickcom/tcpdf'
        );
    }
    require_once $tcpdf_autoload;
    define('SVMS_TCPDF_LOADED', true);
}

class PdfReportBuilder
{
    /* ── Colours ─────────────────────────────────────────────── */
    const C_PRIMARY   = '#1a3c5e';
    const C_SECONDARY = '#2e75b6';
    const C_ACCENT    = '#00b4d8';
    const C_SUCCESS   = '#22c55e';
    const C_WARNING   = '#f59e0b';
    const C_DANGER    = '#ef4444';
    const C_TEXT      = '#1e293b';
    const C_MUTED     = '#64748b';
    const C_BORDER    = '#e2e8f0';
    const C_ZEBRA     = '#f8fafc';
    const C_WHITE     = '#ffffff';

    /* ── Page geometry (mm) ───────────────────────────────────── */
    const MARGIN_H    = 18;   // horizontal (left & right)
    const MARGIN_TOP  = 26;   // below header band
    const MARGIN_FOOT = 16;   // above footer band
    const HDR_H       = 14;   // header band height
    const FTR_H       = 10;   // footer band height

    /* ── Typography (pt) ──────────────────────────────────────── */
    const F_HEADING = 16;
    const F_BODY    = 10;
    const F_SMALL   =  8;
    const F_TABLE_H = 9;
    const F_TABLE_B = 9;

    private \TCPDF $pdf;
    private string $reportTitle;
    private string $adminName;
    private string $generatedAt;
    private string $orgName;
    private string $logoPath;

    /** @var array Current table columns (for auto-repeat header on page break) */
    private array  $_tblHeaders   = [];
    private array  $_tblColWidths = [];
    private array  $_tblOptions   = [];

    public function __construct(string $reportTitle = 'Visitor Report')
    {
        $this->reportTitle  = $reportTitle;
        $this->adminName    = $_SESSION['admin_name'] ?? 'System';
        $this->generatedAt  = date('M j, Y \a\t g:i A');
        $this->orgName      = defined('SITE_NAME') ? SITE_NAME : 'SVMS';
        $this->logoPath     = defined('UPLOAD_DIR')
            ? dirname(UPLOAD_DIR) . '/img/logo.svg'
            : __DIR__ . '/../assets/img/logo.svg';

        $this->_initTCPDF();
    }

    /* ════════════════════════════════════════════════════════════
       PUBLIC API
       ════════════════════════════════════════════════════════════ */

    /**
     * Cover page: full primary-colour background with title centred.
     */
    public function cover(
        string $title,
        string $subtitle = '',
        string $dateRange = '',
        string $notes = ''
    ): void {
        $pdf = $this->pdf;
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(false);

        // Full-page primary background
        $this->_fillRect(0, 0, 210, 297, self::C_PRIMARY);

        // White decorative strip near bottom
        $this->_fillRect(0, 240, 210, 57, '#0d2240');

        // Logo (white, top-left)
        if (file_exists($this->logoPath)) {
            // SVG → try; fall back silently
            @$pdf->Image(
                $this->logoPath, self::MARGIN_H, 32, 40, 0, 'SVG',
                '', '', false, 150, '', false, false, 0, false, false, false
            );
        }

        // Org name top-right
        $pdf->SetXY(0, 34);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(...$this->_hex2rgb(self::C_WHITE));
        $pdf->Cell(210 - self::MARGIN_H, 8, $this->orgName, 0, 0, 'R');

        // Main title
        $pdf->SetY(110);
        $pdf->SetFont('helvetica', 'B', 28);
        $pdf->SetTextColor(...$this->_hex2rgb(self::C_WHITE));
        $pdf->MultiCell(210 - 2 * self::MARGIN_H, 12, $title, 0, 'C', false, 1,
            self::MARGIN_H, null, true);

        // Subtitle
        if ($subtitle) {
            $pdf->SetFont('helvetica', '', 14);
            $pdf->SetTextColor(...$this->_hex2rgb('#a8c4e0'));
            $pdf->MultiCell(210 - 2 * self::MARGIN_H, 8, $subtitle, 0, 'C', false, 1,
                self::MARGIN_H, null, true);
        }

        // Date range badge
        if ($dateRange) {
            $pdf->Ln(6);
            $x = self::MARGIN_H + 20;
            $w = 210 - 2 * self::MARGIN_H - 40;
            $y = $pdf->GetY();
            $this->_fillRect($x, $y, $w, 10, self::C_SECONDARY, 4);
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetTextColor(...$this->_hex2rgb(self::C_WHITE));
            $pdf->SetXY($x, $y + 1);
            $pdf->Cell($w, 8, $dateRange, 0, 0, 'C');
        }

        // Notes
        if ($notes) {
            $pdf->SetY(220);
            $pdf->SetFont('helvetica', 'I', 9);
            $pdf->SetTextColor(...$this->_hex2rgb('#a8c4e0'));
            $pdf->MultiCell(210 - 2 * self::MARGIN_H, 5, $notes, 0, 'C', false, 1,
                self::MARGIN_H, null, true);
        }

        // Generated-by line at bottom strip
        $pdf->SetY(252);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(...$this->_hex2rgb('#6b8fae'));
        $pdf->SetX(self::MARGIN_H);
        $pdf->Cell(210 - 2 * self::MARGIN_H, 6,
            'Generated ' . $this->generatedAt . ' by ' . $this->adminName, 0, 0, 'C');

        // Confidential watermark
        $pdf->SetY(268);
        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->SetTextColor(...$this->_hex2rgb('#4a6a8a'));
        $pdf->Cell(210, 5, 'CONFIDENTIAL — FOR AUTHORISED PERSONNEL ONLY', 0, 0, 'C');

        $pdf->SetAutoPageBreak(true, self::MARGIN_FOOT);
    }

    /**
     * Section heading — adds a coloured rule and heading text.
     */
    public function section(string $title, bool $newPage = false): void
    {
        $pdf = $this->pdf;
        if ($newPage) {
            $pdf->AddPage();
        } else {
            $pdf->Ln(4);
        }

        $y = $pdf->GetY();
        // Left accent bar
        $this->_fillRect(self::MARGIN_H, $y, 3, 7, self::C_SECONDARY);
        // Heading text
        $pdf->SetFont('helvetica', 'B', self::F_HEADING);
        $pdf->SetTextColor(...$this->_hex2rgb(self::C_PRIMARY));
        $pdf->SetXY(self::MARGIN_H + 6, $y);
        $pdf->Cell(0, 7, $title, 0, 1, 'L');
        // Thin rule
        $pdf->SetDrawColor(...$this->_hex2rgb(self::C_BORDER));
        $pdf->SetLineWidth(0.3);
        $pdf->Line(self::MARGIN_H, $pdf->GetY(), 210 - self::MARGIN_H, $pdf->GetY());
        $pdf->Ln(3);
    }

    /**
     * KPI grid — renders up to 4 KPI boxes side by side.
     *
     * @param array $kpis  Each item: ['label'=>'', 'value'=>'', 'delta'=>'', 'delta_dir'=>'up|down|neutral', 'icon'=>'?']
     */
    public function kpiGrid(array $kpis): void
    {
        $pdf    = $this->pdf;
        $count  = min(4, count($kpis));
        if ($count === 0) return;

        $usable = 210 - 2 * self::MARGIN_H;
        $gap    = 4;
        $w      = ($usable - ($count - 1) * $gap) / $count;
        $h      = 24;
        $y      = $pdf->GetY();
        $x      = self::MARGIN_H;

        foreach (array_slice($kpis, 0, 4) as $i => $kpi) {
            $kx = $x + $i * ($w + $gap);

            // Card background
            $this->_fillRoundedRect($kx, $y, $w, $h, 2, '#f0f6fd');
            // Left accent
            $this->_fillRect($kx, $y, 2, $h, self::C_SECONDARY);

            // Label
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetTextColor(...$this->_hex2rgb(self::C_MUTED));
            $pdf->SetXY($kx + 5, $y + 2);
            $pdf->Cell($w - 7, 4, strtoupper($kpi['label'] ?? ''), 0, 0, 'L');

            // Value
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->SetTextColor(...$this->_hex2rgb(self::C_PRIMARY));
            $pdf->SetXY($kx + 5, $y + 7);
            $pdf->Cell($w - 7, 8, $kpi['value'] ?? '—', 0, 0, 'L');

            // Delta
            if (!empty($kpi['delta']) && $kpi['delta'] !== null) {
                $dir = $kpi['delta_dir'] ?? 'neutral';
                $arrow = $dir === 'up' ? '▲' : ($dir === 'down' ? '▼' : '—');
                $clr   = $dir === 'up' ? self::C_SUCCESS : ($dir === 'down' ? self::C_DANGER : self::C_MUTED);
                $pdf->SetFont('helvetica', '', 7);
                $pdf->SetTextColor(...$this->_hex2rgb($clr));
                $pdf->SetXY($kx + 5, $y + 16);
                $pdf->Cell($w - 7, 4, $arrow . ' ' . $kpi['delta'], 0, 0, 'L');
            }
        }

        $pdf->SetY($y + $h + 4);
    }

    /**
     * Narrative paragraph (for auto-generated summary text).
     */
    public function paragraph(string $text): void
    {
        $pdf = $this->pdf;
        $pdf->SetFont('helvetica', '', self::F_BODY);
        $pdf->SetTextColor(...$this->_hex2rgb(self::C_TEXT));
        $pdf->MultiCell(
            210 - 2 * self::MARGIN_H, 5.5, $text,
            0, 'J', false, 1, self::MARGIN_H, null, true
        );
        $pdf->Ln(2);
    }

    /**
     * Embed a chart image from base64 PNG.
     * The PNG should be provided WITHOUT the data:image/png;base64, prefix.
     *
     * @param string $base64  Raw base64-encoded PNG bytes
     * @param string $caption Caption shown below the chart
     * @param float  $maxH    Maximum height in mm (default 80)
     */
    public function chartImage(string $base64, string $caption = '', float $maxH = 80): void
    {
        if (empty($base64)) return;

        $pdf    = $this->pdf;
        $usable = 210 - 2 * self::MARGIN_H;

        // Decode and write to temp file
        $tmpFile = tempnam(sys_get_temp_dir(), 'svms_chart_') . '.png';
        file_put_contents($tmpFile, base64_decode($base64));

        // Get image dimensions for aspect ratio
        $size = @getimagesize($tmpFile);
        if (!$size) {
            @unlink($tmpFile);
            return;
        }

        [$imgW, $imgH] = $size;
        $ratio  = $imgW > 0 ? $imgH / $imgW : 1;
        $drawW  = $usable;
        $drawH  = min($maxH, $drawW * $ratio);

        // Check if we need a new page
        if ($pdf->GetY() + $drawH + 10 > 297 - self::MARGIN_FOOT) {
            $pdf->AddPage();
        }

        $y = $pdf->GetY();

        // Light card background
        $this->_fillRoundedRect(self::MARGIN_H, $y, $usable, $drawH + 2, 2, '#f8fafc');

        // Image
        $pdf->Image(
            $tmpFile,
            self::MARGIN_H + 1,
            $y + 1,
            $usable - 2,
            $drawH,
            'PNG', '', '', true, 150
        );

        $pdf->SetY($y + $drawH + 3);

        @unlink($tmpFile);

        // Caption
        if ($caption) {
            $pdf->SetFont('helvetica', 'I', 8);
            $pdf->SetTextColor(...$this->_hex2rgb(self::C_MUTED));
            $pdf->Cell(210 - 2 * self::MARGIN_H, 5, $caption, 0, 1, 'C');
        }

        $pdf->Ln(3);
    }

    /**
     * Render a data table with zebra rows, primary header, auto-page-break with header repeat.
     *
     * @param string[] $headers   Column labels
     * @param array[]  $rows      Data rows (associative or indexed)
     * @param array    $options   [
     *   'col_widths' => [float …]   // mm; null = auto-equal
     *   'aligns'     => ['L','R',…] // per-column alignment, default 'L'
     *   'max_rows'   => int         // truncate after N rows
     *   'row_h'      => float       // row height in mm, default 6
     * ]
     */
    public function table(array $headers, array $rows, array $options = []): void
    {
        $pdf    = $this->pdf;
        $usable = 210 - 2 * self::MARGIN_H;
        $colN   = count($headers);
        if ($colN === 0) return;

        // Column widths
        if (!empty($options['col_widths'])) {
            $colW = $options['col_widths'];
        } else {
            $eqW  = $usable / $colN;
            $colW = array_fill(0, $colN, $eqW);
        }

        $aligns  = $options['aligns']  ?? array_fill(0, $colN, 'L');
        $rowH    = $options['row_h']   ?? 6;
        $maxRows = $options['max_rows'] ?? PHP_INT_MAX;

        // Store for page-break header repeat
        $this->_tblHeaders   = $headers;
        $this->_tblColWidths = $colW;
        $this->_tblOptions   = $options;

        $this->_drawTableHeader($headers, $colW, $aligns, $rowH);

        $rowCount = 0;
        foreach ($rows as $row) {
            if ($rowCount >= $maxRows) break;
            $rowCount++;

            $isZebra = ($rowCount % 2 === 0);
            $bgClr   = $isZebra ? self::C_ZEBRA : self::C_WHITE;

            // Check page break
            if ($pdf->GetY() + $rowH > 297 - self::MARGIN_FOOT) {
                $pdf->AddPage();
                $this->_drawTableHeader($headers, $colW, $aligns, $rowH);
            }

            $values = array_values((array)$row);
            $y      = $pdf->GetY();

            // Row background
            $this->_fillRect(self::MARGIN_H, $y, $usable, $rowH, $bgClr);

            // Cells
            $pdf->SetFont('helvetica', '', self::F_TABLE_B);
            $pdf->SetTextColor(...$this->_hex2rgb(self::C_TEXT));
            $pdf->SetY($y);
            $pdf->SetX(self::MARGIN_H);

            foreach ($colW as $ci => $cw) {
                $align = $aligns[$ci] ?? 'L';
                $val   = isset($values[$ci]) ? (string)$values[$ci] : '';
                // Truncate very long text
                if (strlen($val) > 80) $val = substr($val, 0, 77) . '…';
                $pdf->Cell($cw, $rowH, $val, 0, 0, $align);
            }
            $pdf->Ln();

            // Bottom border
            $pdf->SetDrawColor(...$this->_hex2rgb(self::C_BORDER));
            $pdf->SetLineWidth(0.2);
            $pdf->Line(self::MARGIN_H, $pdf->GetY(), 210 - self::MARGIN_H, $pdf->GetY());
        }

        $pdf->Ln(4);
    }

    /**
     * Force a page break.
     */
    public function addPageBreak(): void
    {
        $this->pdf->AddPage();
    }

    /**
     * Output the finished PDF.
     *
     * @param string $outPath  Absolute filesystem path to write the file
     * @return string          The path written
     */
    public function output(string $outPath): string
    {
        $dir = dirname($outPath);
        if (!is_dir($dir)) mkdir($dir, 0750, true);
        $this->pdf->Output($outPath, 'F');
        return $outPath;
    }

    /* ════════════════════════════════════════════════════════════
       PRIVATE HELPERS
       ════════════════════════════════════════════════════════════ */

    private function _initTCPDF(): void
    {
        // _SvmsTCPDF is defined at the bottom of this file;
        // by the time __construct() runs, both classes are fully declared.
        $pdf = new _SvmsTCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->builder = $this; // wire back-reference for Header()/Footer()

        $pdf->SetCreator($this->orgName);
        $pdf->SetAuthor($this->adminName);
        $pdf->SetTitle($this->reportTitle);
        $pdf->SetSubject('Visitor Management Report');

        // Enable custom header/footer (rendered by _SvmsTCPDF::Header/Footer)
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);

        // Margins (top margin gives space below our 14mm header band)
        $pdf->SetMargins(self::MARGIN_H, self::MARGIN_TOP, self::MARGIN_H);
        $pdf->SetAutoPageBreak(true, self::MARGIN_FOOT);
        $pdf->SetHeaderMargin(0);
        $pdf->SetFooterMargin(0);

        // Font
        $pdf->SetFont('helvetica', '', self::F_BODY);

        $this->pdf = $pdf;

        // First page will be added by cover() or first section call
    }

    /**
     * Called by TCPDF's Header() — we do NOT use auto-header,
     * instead we call _drawPageChrome() after AddPage via a workaround
     * (override Header/Footer using a subclass below at file bottom).
     */
    public function drawHeader(): void
    {
        $pdf = $this->pdf;
        $page = $pdf->getPage();
        if ($page <= 1) return; // Cover page has its own design

        // Primary band across top
        $this->_fillRect(0, 0, 210, self::HDR_H, self::C_PRIMARY);

        // Logo left
        if (file_exists($this->logoPath)) {
            @$pdf->Image(
                $this->logoPath, self::MARGIN_H, 1, 20, 0, 'SVG',
                '', '', false, 150
            );
        }

        // Report title centre
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(...$this->_hex2rgb(self::C_WHITE));
        $pdf->SetXY(0, 3);
        $pdf->Cell(210, 6, $this->reportTitle, 0, 0, 'C');

        // Page number right
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY(0, 4);
        $pdf->Cell(210 - self::MARGIN_H, 5,
            'Page ' . $pdf->getPage() . ' / ' . $pdf->getNumPages(),
            0, 0, 'R');
    }

    public function drawFooter(): void
    {
        $pdf  = $this->pdf;
        $page = $pdf->getPage();
        if ($page <= 1) return;

        $y = 297 - self::FTR_H;

        // Thin top rule
        $pdf->SetDrawColor(...$this->_hex2rgb(self::C_BORDER));
        $pdf->SetLineWidth(0.3);
        $pdf->Line(self::MARGIN_H, $y, 210 - self::MARGIN_H, $y);

        // Org name left
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(...$this->_hex2rgb(self::C_MUTED));
        $pdf->SetXY(self::MARGIN_H, $y + 1);
        $pdf->Cell(60, 5, $this->orgName, 0, 0, 'L');

        // Generated-by centre
        $pdf->SetXY(0, $y + 1);
        $pdf->Cell(210, 5,
            'Generated ' . $this->generatedAt . ' by ' . $this->adminName,
            0, 0, 'C');

        // Page X/Y right
        $pdf->SetXY(0, $y + 1);
        $pdf->Cell(210 - self::MARGIN_H, 5,
            'Page ' . $pdf->getPage() . ' / ' . $pdf->getNumPages(),
            0, 0, 'R');
    }

    private function _drawTableHeader(
        array $headers,
        array $colW,
        array $aligns,
        float $rowH
    ): void {
        $pdf = $this->pdf;
        $y   = $pdf->GetY();

        // Background
        $usable = array_sum($colW);
        $this->_fillRect(self::MARGIN_H, $y, $usable, $rowH, self::C_PRIMARY);

        // Text
        $pdf->SetFont('helvetica', 'B', self::F_TABLE_H);
        $pdf->SetTextColor(...$this->_hex2rgb(self::C_WHITE));
        $pdf->SetY($y);
        $pdf->SetX(self::MARGIN_H);

        foreach ($colW as $ci => $cw) {
            $align = $aligns[$ci] ?? 'L';
            $pdf->Cell($cw, $rowH, $headers[$ci] ?? '', 0, 0, $align);
        }
        $pdf->Ln();
    }

    /** Fill a rectangle. */
    private function _fillRect(
        float $x, float $y, float $w, float $h,
        string $hex, float $r = 0
    ): void {
        $pdf = $this->pdf;
        [$R, $G, $B] = $this->_hex2rgb($hex);
        $pdf->SetFillColor($R, $G, $B);
        if ($r > 0) {
            $pdf->RoundedRect($x, $y, $w, $h, $r, '1111', 'F');
        } else {
            $pdf->Rect($x, $y, $w, $h, 'F');
        }
    }

    private function _fillRoundedRect(
        float $x, float $y, float $w, float $h,
        float $r, string $hex
    ): void {
        $this->_fillRect($x, $y, $w, $h, $hex, $r);
    }

    /** Convert #rrggbb hex colour to [R,G,B] integer array. */
    private function _hex2rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}

/* ════════════════════════════════════════════════════════════════
   TCPDF subclass: plugs custom header/footer into TCPDF lifecycle
   ════════════════════════════════════════════════════════════════ */

/**
 * SvmsTCPDF — Extends TCPDF to render custom header/footer via
 * a reference back to the PdfReportBuilder instance.
 *
 * Usage (internal — PdfReportBuilder uses _SvmsTCPDF internally).
 */
class _SvmsTCPDF extends \TCPDF
{
    /** @var PdfReportBuilder */
    public PdfReportBuilder $builder;

    public function Header(): void
    {
        if (isset($this->builder)) {
            $this->builder->drawHeader();
        }
    }

    public function Footer(): void
    {
        if (isset($this->builder)) {
            $this->builder->drawFooter();
        }
    }
}
