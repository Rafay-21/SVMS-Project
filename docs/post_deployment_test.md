# SVMS Post-Deployment Smoke Test
**Estimated time: 10 minutes**  
**Prerequisite:** Deployment complete per `deployment_checklist.md`; default seed data imported.

Run each test in order. Mark ✅ pass or ❌ fail. Any ❌ must be resolved before going live.

---

## Test Environment
- Browser: Chrome 120+ (private/incognito window for unauthenticated tests)
- Base URL: `https://your-domain.com/` (or `http://localhost/svms/` for XAMPP)
- Default super-admin credentials: `admin` / `Admin@1234!` (change immediately after)

---

## Step 1 — Root Redirect (30 sec)

**Action:** Open `https://your-domain.com/` in the browser.

| Check | Expected | Result |
|-------|----------|--------|
| URL changes | Browser redirects to `.../pages/login.php` | |
| HTTPS padlock | Green padlock visible (production only) | |
| Page title | "Login — Smart Visitor Management System" | |
| No PHP errors | No "Warning:" or "Fatal:" text on page | |

---

## Step 2 — Login + 2FA Flow (1 min)

**Action:** Enter `admin` / `Admin@1234!` → click Login.

| Check | Expected | Result |
|-------|----------|--------|
| Redirect to OTP page | `.../pages/verify_otp.php` | |
| OTP email arrives | Check the SMTP inbox for a 6-digit code | |
| Enter correct OTP | Redirected to `.../pages/dashboard.php` | |
| Dashboard loads | Page renders without errors | |
| KPI cards visible | Active Visitors, Today's Visits, Total Visitors, Pending Appointments | |
| Welcome name shown | "Welcome, Admin" in header | |

---

## Step 3 — Change Default Password (1 min)

**Action:** Click the user avatar (top right) → Profile → Change Password.

| Check | Expected | Result |
|-------|----------|--------|
| Weak password rejected | Try `password` → validation error about length/symbols | |
| Strong password accepted | Enter a 12-char password with letters+digits+symbol | |
| Session regenerated | Logged out; redirected to login | |
| New password works | Log back in with the new password | |

---

## Step 4 — Register a Test Visitor (1 min)

**Action:** Sidebar → Register Visitor → fill in: Name=`Test Visitor`, CNIC=`12345-6789012-3`, Phone=`0300-1234567` → Submit.

| Check | Expected | Result |
|-------|----------|--------|
| Visitor created | Redirected to visitor detail page | |
| Badge number shown | Format `VIS-YYMMDD-XXXXX` | |
| QR code displayed | QR image renders on detail page | |
| Print badge | Click "Print Badge" → print dialog opens | |

---

## Step 5 — Check-In & Notification (1 min)

**Action:** On the visitor detail page → click "Check In".

| Check | Expected | Result |
|-------|----------|--------|
| Status changes | Status badge shows "Checked In" | |
| Check-in time recorded | Timestamp visible on the detail page | |
| Notification bell | Bell icon increments by 1 | |
| Notification content | Shows "Test Visitor checked in" | |
| Active visitors count | Dashboard KPI "Active Visitors" increases by 1 | |

---

## Step 6 — Check-Out (30 sec)

**Action:** Back on visitor detail → click "Check Out" → confirm.

| Check | Expected | Result |
|-------|----------|--------|
| Status changes | Status badge shows "Checked Out" | |
| Check-out time recorded | Duration shown (e.g. "0m") | |

---

## Step 7 — Analytics (30 sec)

**Action:** Sidebar → Analytics.

| Check | Expected | Result |
|-------|----------|--------|
| Page loads | No errors; charts render | |
| Today's visit shown | The test visit appears in "visits by day" chart | |
| Department breakdown | Chart renders (may show "Unknown" for test visitor) | |

---

## Step 8 — Theme & Language Toggle (30 sec)

**Action:** Click the sun/moon icon in the header to switch theme.

| Check | Expected | Result |
|-------|----------|--------|
| Dark mode activates | Background turns dark; text remains readable | |
| Preference persists | Reload page → still dark mode | |
| Language toggle | Click language switcher → switch to Urdu (ur) | |
| Urdu renders | Interface labels appear in Urdu/right-to-left | |
| Switch back to English | Language changes back | |

---

## Step 9 — PWA Install (30 sec)

**Action:** In Chrome, look for the install icon in the address bar (⊕ or install prompt).

| Check | Expected | Result |
|-------|----------|--------|
| Install prompt available | Chrome shows "Install SVMS" option | |
| Manifest valid | DevTools → Application → Manifest → no errors | |
| Service worker active | DevTools → Application → Service Workers → Status: activated | |

> Skip this step if testing on Firefox or Safari; PWA install is Chromium-only.

---

## Step 10 — Log Out (10 sec)

**Action:** Click Logout in the sidebar or header.

| Check | Expected | Result |
|-------|----------|--------|
| Session destroyed | Redirected to login page | |
| Cookie cleared | DevTools → Storage → `SVMS_SESSID` cookie absent | |
| Back button blocked | Press Back → login page shown (not dashboard) | |

---

## Failure-Mode Tests

### F1 — Brute-Force Lockout (1 min)

**Action:** On the login page, submit wrong password **6 times** in under 60 seconds.

| Check | Expected | Result |
|-------|----------|--------|
| Rate limit triggered | HTTP 429 response on attempt 11 (limit is 10/min) | |
| Retry-After header | Response includes `Retry-After: 60` | |

> Test with curl: `curl -si -X POST https://your-domain.com/pages/login.php -d "username=admin&password=wrong&csrf_token=x"`

---

### F2 — Directory Listing Blocked (30 sec)

**Action:** In an incognito browser tab, visit:
- `https://your-domain.com/logs/`
- `https://your-domain.com/vendor/`
- `https://your-domain.com/includes/`

| Check | Expected | Result |
|-------|----------|--------|
| All three return 403 | "Forbidden" or 404 (not a file list) | |

---

### F3 — Unauthenticated API Access (30 sec)

**Action:** In incognito, visit `https://your-domain.com/api/get_stats.php`.

| Check | Expected | Result |
|-------|----------|--------|
| Returns 401 or redirect | Not JSON stats data; no session = blocked | |

---

### F4 — SMTP Test (1 min)

**Action:** Log in as admin → Settings → Email → click "Send Test Email".

| Check | Expected | Result |
|-------|----------|--------|
| Success message shown | "Test email sent successfully" toast | |
| Email received | Check inbox for test message from `no-reply@your-domain.com` | |

---

## Final Sign-Off

| Item | Status |
|------|--------|
| All 10 smoke-test steps passed | |
| All 4 failure-mode tests passed | |
| Default password changed | |
| SMTP configured and tested | |
| First backup created | |
| `logs/php_errors.log` is empty | |
| **Deployment approved** | |

---

*If any test fails, consult the Troubleshooting section in `deployment_checklist.md` or check `logs/php_errors.log`.*
