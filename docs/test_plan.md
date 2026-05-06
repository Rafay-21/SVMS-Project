# SVMS End-to-End Test Plan
**Version:** 1.0  
**Project:** Visitor Management System (SVMS)  
**Stack:** PHP 8+, MySQLi, XAMPP, Bootstrap Icons  
**Base URL:** `http://localhost/svms/`

---

## Legend

| Symbol | Meaning |
|--------|---------|
| ✅ | Pass |
| ❌ | Fail |
| ⚠️ | Partial / Degraded |
| N/A | Not applicable |
| P | Priority: High (P1), Medium (P2), Low (P3) |

---

## 1. Authentication & Session

| ID | Test Case | Steps | Expected Result | P |
|----|-----------|-------|----------------|---|
| A-01 | Valid login | Enter correct username + password → Submit | Redirected to dashboard; session cookie set (`SVMS_SESSID`); `last_activity` recorded | P1 |
| A-02 | Invalid password | Enter wrong password → Submit | Error message shown; login counter incremented in `audit_logs` | P1 |
| A-03 | Rate limit — login | Submit wrong password 10+ times within 60 s | HTTP 429 response; `Retry-After: 60` header | P1 |
| A-04 | OTP flow | Enable OTP in profile; login; enter correct 6-digit OTP | Logged in successfully | P1 |
| A-05 | OTP rate limit | Submit wrong OTP 10+ times within 60 s | 429 response | P1 |
| A-06 | OTP expiry | Wait > OTP_EXPIRY_MINUTES; submit correct OTP | "OTP expired" error | P1 |
| A-07 | Session idle timeout | Remain inactive for SESSION_LIFETIME_HOURS | Redirected to login with "session expired" message | P1 |
| A-08 | Session binding | Copy session cookie to different IP / user-agent | Session destroyed; redirected to login | P1 |
| A-09 | Logout | Click Logout | Session destroyed; redirected to login; cookie cleared | P1 |
| A-10 | CSRF on login | POST without CSRF token | 403 response | P1 |
| A-11 | Password strength | Create account with < 10 chars, no symbol | Validation error: password policy not met | P1 |
| A-12 | Password history | Change password to one used in last 3 changes | Validation error: password used recently | P2 |
| A-13 | Forgot password | Submit valid email → receive reset link | Email delivered; token expires after 30 min | P1 |
| A-14 | Remember me (if implemented) | Check "remember me" → close browser → reopen | Session persists | P3 |

---

## 2. Visitor Registration

| ID | Test Case | Steps | Expected Result | P |
|----|-----------|-------|----------------|---|
| V-01 | Register new visitor | Fill all required fields → Submit | Visitor saved; badge number generated; redirected to visitor detail | P1 |
| V-02 | Duplicate CNIC check | Register visitor with CNIC already in DB | Warning or duplicate-detected prompt | P2 |
| V-03 | Photo capture (webcam) | Grant camera access; capture photo | Photo saved as JPEG; GD re-encode applied | P1 |
| V-04 | Photo upload (file) | Upload .jpg / .png / .webp | Accepted; file saved as `bin2hex(16).jpg` | P1 |
| V-05 | Malicious file upload | Upload .php file as photo | Rejected with MIME validation error | P1 |
| V-06 | Oversized photo | Upload > 4096×4096 px image | Rejected: dimension check | P1 |
| V-07 | Custom fields | Custom field defined for registration → fill form | Custom field value saved in `visitor_meta` | P2 |
| V-08 | QR code generation | Complete registration | QR token generated; QR code rendered on badge | P2 |
| V-09 | Badge print | Click "Print Badge" | Print dialog opens; badge-only layout visible | P3 |
| V-10 | Visitor search | Search by name / CNIC / phone | Matching results returned | P1 |

---

## 3. Check-in / Check-out

| ID | Test Case | Steps | Expected Result | P |
|----|-----------|-------|----------------|---|
| C-01 | Manual check-in | Open visitor; click Check In | `visit_log` row inserted; status=`checked_in` | P1 |
| C-02 | Manual check-out | Open active visit; click Check Out | `check_out_time` set; status=`checked_out` | P1 |
| C-03 | Kiosk QR check-in | Scan visitor QR at kiosk | `visit_log` row inserted; confirmation shown on kiosk | P1 |
| C-04 | Kiosk photo capture | Kiosk captures photo on check-in | Photo saved with GD re-encode | P1 |
| C-05 | Auto-checkout cron | Visit older than MAX_VISIT_HOURS | `status=auto_checkout`; notification generated | P2 |
| C-06 | No-show status | Mark visit as no-show | status=`no_show` recorded | P2 |
| C-07 | Double check-in | Try to check in a visitor already checked in | Error: visitor already checked in | P1 |
| C-08 | Blacklist check | Visitor on blacklist tries to check in | Blocked; alert shown; `audit_logs` entry | P1 |
| C-09 | Emergency mass checkout | Set emergency mode; click "Confirm Mass Checkout" | All `checked_in` visits → `checked_out`; log entry | P1 |

---

## 4. Appointments

| ID | Test Case | Steps | Expected Result | P |
|----|-----------|-------|----------------|---|
| AP-01 | Create appointment | Fill visitor name, host, date/time → Save | Appointment saved; notification sent to host | P1 |
| AP-02 | Appointment conflict | Create two appointments for same host at same time | Warning shown (if conflict detection enabled) | P2 |
| AP-03 | Approve appointment | Admin approves pending appointment | Status → `approved`; visitor notification sent | P1 |
| AP-04 | Reject appointment | Admin rejects appointment | Status → `rejected`; notification with reason | P1 |
| AP-05 | Convert to visit | Approved appointment → check in | `visit_log` row linked to appointment | P2 |
| AP-06 | Appointment calendar | Open calendar view | Appointments displayed on correct dates | P2 |
| AP-07 | Custom fields for appointment | Custom field for appointment | Value saved | P2 |

---

## 5. Visit History & Audit

| ID | Test Case | Steps | Expected Result | P |
|----|-----------|-------|----------------|---|
| H-01 | Visit history list | Open History page | All completed/active visits listed; sortable | P1 |
| H-02 | Filter by date range | Apply start date + end date filter | Only visits within range shown | P1 |
| H-03 | Filter by visitor name | Type name in search box | Matching visits shown | P1 |
| H-04 | Filter by host | Select host from dropdown | Filtered results | P2 |
| H-05 | Visit detail view | Click on a visit | Full detail shown: visitor info, host, check-in/out, photos | P1 |
| H-06 | Audit log | Perform admin action → open Audit Log page | Action recorded with IP, user agent, timestamp | P1 |
| H-07 | Audit log search | Search by action type | Filtered results | P2 |

---

## 6. Analytics & Dashboard

| ID | Test Case | Steps | Expected Result | P |
|----|-----------|-------|----------------|---|
| AN-01 | Dashboard KPI cards | Open dashboard | Active visitors, today's count, total visitors, pending appointments shown | P1 |
| AN-02 | Chart — visits by day | Dashboard chart | Bar/line chart renders; last 30 days shown | P2 |
| AN-03 | Chart — by department | Dashboard pie/donut | Correct department breakdown | P2 |
| AN-04 | Peak hours chart | Analytics page | Hourly heatmap displayed | P3 |
| AN-05 | Date range filter on analytics | Change date range | Charts and KPIs update accordingly | P2 |
| AN-06 | Real-time active visitors | Open dashboard | Active count matches `visit_log WHERE status='checked_in'` | P1 |

---

## 7. Reports

| ID | Test Case | Steps | Expected Result | P |
|----|-----------|-------|----------------|---|
| R-01 | Generate PDF report | Select date range → Generate Report | PDF downloaded | P1 |
| R-02 | Generate CSV export | Select CSV format → Generate | CSV downloaded with correct columns | P1 |
| R-03 | Report rate limit | Generate report 5+ times/min | 429 response on 6th attempt | P1 |
| R-04 | Report with no data | Select date range with no visits | Empty report / "no data" message | P2 |
| R-05 | Report filter by department | Filter by department | Only matching rows in export | P2 |

---

## 8. Notifications

| ID | Test Case | Steps | Expected Result | P |
|----|-----------|-------|----------------|---|
| N-01 | In-app notification | Trigger notification-generating event | Bell icon badge increments; notification in dropdown | P1 |
| N-02 | Mark notification read | Click a notification | Notification marked as read; badge decrements | P1 |
| N-03 | Mark all read | Click "Mark all read" | All notifications read; badge resets to 0 | P2 |
| N-04 | Email notification | Configure SMTP; trigger email notification | Email received by recipient | P1 |
| N-05 | SMTP password encryption | Save SMTP password in Settings | Password stored as `enc:…` in DB | P1 |
| N-06 | Notification bell polling | Leave page open for 1+ min | Bell updates without page refresh | P2 |

---

## 9. Settings & Themes

| ID | Test Case | Steps | Expected Result | P |
|----|-----------|-------|----------------|---|
| ST-01 | SMTP settings save | Enter SMTP config → Save | Settings saved; password encrypted with `enc:` prefix | P1 |
| ST-02 | Site name / logo | Change site name → reload page | New site name shown in header | P2 |
| ST-03 | Theme toggle | Switch between light/dark | Theme changes immediately; preference persisted | P1 |
| ST-04 | Custom color theme | Select a custom accent color | Applied to CSS custom properties | P3 |
| ST-05 | Session lifetime setting | Change SESSION_LIFETIME_HOURS → idle | Correct timeout applied | P2 |
| ST-06 | Operations tab — auto checkout | Trigger auto-checkout manually | All expired visits checked out | P2 |

---

## 10. Emergency Mode

| ID | Test Case | Steps | Expected Result | P |
|----|-----------|-------|----------------|---|
| EM-01 | Activate evacuation mode | Set mode = evacuation | Yellow banner in header; active visitor count shown | P1 |
| EM-02 | Activate lockdown mode | Set mode = lockdown | Red banner in header | P1 |
| EM-03 | Mass checkout confirmation | Click "Confirm Mass Checkout"; type `CONFIRM CHECKOUT` | All checked-in visits → checked_out; log entry | P1 |
| EM-04 | Print snapshot | Click "Print Snapshot" | Only snapshot card visible in print layout | P2 |
| EM-05 | Dismiss banner | Click dismiss button in header | Banner hidden for this session | P3 |
| EM-06 | Deactivate emergency mode | Set mode = normal | Banner removed from header | P1 |

---

## 11. Kiosk Mode

| ID | Test Case | Steps | Expected Result | P |
|----|-----------|-------|----------------|---|
| K-01 | Kiosk login | Navigate to kiosk URL; enter PIN | Kiosk session started (`SVMS_KIOSK`) | P1 |
| K-02 | QR scan check-in | Scan valid visitor QR | Check-in recorded; confirmation displayed | P1 |
| K-03 | Unknown QR | Scan unknown QR code | Error message shown | P1 |
| K-04 | Blacklisted visitor | Blacklisted visitor scans QR | Blocked; alert shown | P1 |
| K-05 | Kiosk timeout | Leave kiosk idle | Kiosk resets to home screen | P2 |
| K-06 | Rate limit on blacklist check | Rapid-fire blacklist check API calls | 429 on >60 req/min | P1 |

---

## 12. Backup & Restore

| ID | Test Case | Steps | Expected Result | P |
|----|-----------|-------|----------------|---|
| BK-01 | Non-Super Admin access | Log in as regular admin; navigate to Backup page | 403 Forbidden | P1 |
| BK-02 | Create backup (mysqldump) | Super Admin → Create Backup Now | Success message; file appears in Backup History; `backups` table row created | P1 |
| BK-03 | Create backup (PHP fallback) | Disable mysqldump; create backup | `.sql.gz` file created; recorded in DB | P1 |
| BK-04 | Download backup | Click Download on a backup row | File downloaded as `application/octet-stream` | P1 |
| BK-05 | Delete backup | Click Delete → confirm | File removed; DB row removed | P1 |
| BK-06 | Auto-prune | Create 21+ backups | Oldest pruned beyond 20 | P2 |
| BK-07 | Restore existing backup | Select backup → type RESTORE → enter password | Database restored; all sessions terminated; redirected to login | P1 |
| BK-08 | Restore wrong password | Enter wrong password in restore modal | 403 "Password verification failed" | P1 |
| BK-09 | Restore missing phrase | Submit restore without typing RESTORE | 422 "Type RESTORE" error | P1 |
| BK-10 | Restore uploaded .sql | Upload valid .sql file → restore | Restore succeeds | P1 |
| BK-11 | Restore malicious file | Upload .php disguised as .sql | Rejected (first-1KB inspection) | P1 |
| BK-12 | Restore file too large | Upload file > 50 MB | 413 error | P1 |
| BK-13 | Automated cron backup | Run `php scripts/cron_daily_backup.php` via CLI | File created; `type=automated` in `backups` table; log entry in `cron.log` | P1 |
| BK-14 | Cron via HTTP | `GET /scripts/cron_daily_backup.php` in browser | 403 (CLI only) | P1 |

---

## 13. Security

| ID | Test Case | Steps | Expected Result | P |
|----|-----------|-------|----------------|---|
| S-01 | Directory listing disabled | `GET /logs/` in browser | 403 Forbidden (no directory listing) | P1 |
| S-02 | PHP execution in uploads blocked | Upload PHP file; access via URL | 403 or served as download | P1 |
| S-03 | CSP header present | Inspect response headers | `Content-Security-Policy` header present | P1 |
| S-04 | X-Frame-Options | Inspect headers | `X-Frame-Options: SAMEORIGIN` | P1 |
| S-05 | X-Content-Type-Options | Inspect headers | `X-Content-Type-Options: nosniff` | P1 |
| S-06 | CSRF on all forms | Intercept POST; remove CSRF token | 403 response | P1 |
| S-07 | SQL injection in search | Enter `' OR '1'='1` in visitor search | Sanitized; no SQL error or unexpected results | P1 |
| S-08 | XSS in visitor name | Enter `<script>alert(1)</script>` | Output escaped via `e()`; no alert executes | P1 |
| S-09 | Error disclosure disabled | Trigger PHP error in production mode | Generic 500 page shown; no stack trace | P1 |
| S-10 | Session cookie flags | Inspect cookie | `HttpOnly`, `SameSite=Strict`, `Secure` (when HTTPS) | P1 |
| S-11 | Rate limit — smart search | Call smart search API >60/min | 429 response | P1 |
| S-12 | Password hashing | Check DB | Passwords stored as bcrypt (`$2y$12$…`) | P1 |
| S-13 | SMTP password encryption | Check `settings` table | SMTP password stored as `enc:…` | P1 |

---

## 14. PWA / Offline

| ID | Test Case | Steps | Expected Result | P |
|----|-----------|-------|----------------|---|
| PW-01 | Install prompt | Open site in Chrome; check address bar | Install app prompt shown | P3 |
| PW-02 | Offline home shell | Install PWA; go offline; open app | Cached shell shown with "offline" indicator | P3 |
| PW-03 | Service worker registration | Open DevTools → Application → Service Workers | Service worker registered and active | P3 |
| PW-04 | Manifest | DevTools → Application → Manifest | Name, icons, theme_color present | P3 |

---

## 15. Responsive Design

| ID | Test Case | Breakpoint | Expected Result | P |
|----|-----------|-----------|----------------|---|
| RD-01 | Dashboard | 1440px (desktop) | Full sidebar visible; two-column layout | P1 |
| RD-02 | Dashboard | 768px (tablet) | Sidebar collapses or becomes drawer | P1 |
| RD-03 | Dashboard | 375px (mobile) | Single-column layout; bottom nav visible | P1 |
| RD-04 | Data tables | 375px | Horizontal scroll or stacked rows | P1 |
| RD-05 | Modals | 375px | Modal fits screen; scrollable body | P1 |
| RD-06 | Print layout | Any | Header/nav hidden; content-only print | P2 |

---

## 16. Cross-Browser

| ID | Browser | Test Case | Expected Result | P |
|----|---------|-----------|----------------|---|
| CB-01 | Chrome 120+ | Full smoke test | All features work | P1 |
| CB-02 | Firefox 120+ | Full smoke test | All features work | P1 |
| CB-03 | Edge 120+ | Full smoke test | All features work | P1 |
| CB-04 | Safari 17+ | Full smoke test | All features work | P2 |
| CB-05 | Chrome Android | Kiosk + Registration | Camera access; forms work | P2 |

---

## 17. Accessibility

| ID | Test Case | Tool / Method | Expected Result | P |
|----|-----------|--------------|----------------|---|
| AC-01 | All form inputs labelled | Inspect HTML / axe DevTools | Every `<input>` has associated `<label>` or `aria-label` | P1 |
| AC-02 | Icon-only buttons have accessible name | Inspect HTML | `aria-label` on icon-only `<button>` elements | P1 |
| AC-03 | Modal focus trap | Open modal; Tab key | Focus stays within modal; Escape closes | P1 |
| AC-04 | Keyboard navigation | Tab through page | Logical tab order; no focus traps outside modals | P1 |
| AC-05 | Color contrast (text) | axe / WAVE | Normal text ≥ 4.5:1; large text ≥ 3:1 | P1 |
| AC-06 | Skip-to-content link | Tab at page top | "Skip to main content" link appears | P2 |
| AC-07 | Live regions for alerts | Trigger toast / error | `aria-live` region announces update to screen reader | P2 |
| AC-08 | Images have alt text | Inspect HTML | All `<img>` have `alt` attribute | P1 |
| AC-09 | Form error messages linked | Trigger form error | Error message linked via `aria-describedby` or `aria-errormessage` | P1 |
| AC-10 | Focus indicator visible | Tab through forms | Visible focus ring on all interactive elements | P1 |

---

## 18. Performance

| ID | Test Case | Tool | Target | P |
|----|-----------|------|--------|---|
| PF-01 | Dashboard initial load | Chrome DevTools Network | < 3 s on localhost | P2 |
| PF-02 | Visitor list (1000 rows) | Page timing | < 2 s; pagination applied | P2 |
| PF-03 | Backup creation | Time measure | < 30 s for typical DB size | P2 |
| PF-04 | Report generation (PDF) | Time measure | < 10 s | P2 |
| PF-05 | Smart search API | Network tab | Response < 300 ms | P2 |

---

## Appendix: Test Environment Setup

1. XAMPP running: Apache 2.4+, PHP 8.1+, MySQL 5.7+ / MariaDB 10.5+
2. DB: `svms_db`; all migrations (001–010) run
3. Admin user: `super_admin` role with all permissions
4. SMTP: configured or mocked (Mailtrap)
5. Keys file: `/config/keys.php` generated by `config.php` on first load
6. Cron: testable by running `php scripts/cron_daily_backup.php` from CLI

---

*Last updated: <?= date('Y-m-d') ?>*
