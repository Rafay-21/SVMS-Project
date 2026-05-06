# SVMS User Manual
**Smart Visitor Management System — Version 2.0**

---

## Table of Contents

1. [Getting Started](#chapter-1-getting-started)
2. [Daily Operations — Walk-In Visitors](#chapter-2-daily-operations--walk-in-visitors)
3. [Appointments & Scheduling](#chapter-3-appointments--scheduling)
4. [Records, Reports & Audit Trail](#chapter-4-records-reports--audit-trail)
5. [Security Tools — Blacklist & Emergency Mode](#chapter-5-security-tools--blacklist--emergency-mode)
6. [Configuration & Super Admin Settings](#chapter-6-configuration--super-admin-settings)
7. [Troubleshooting & FAQ](#chapter-7-troubleshooting--faq)

---

## Chapter 1 — Getting Started

**What you'll learn:** How to log in, set up two-factor authentication, change your password, and navigate the dashboard.

---

### 1.1 Logging In

1. Open your browser and go to your SVMS URL (e.g. `https://svms.your-org.com/`).
2. You will be redirected to the **Login** page.

   ![Login Page](screenshots/login-page.png)

3. Enter your **Username** and **Password**, then click **Login**.
4. If Two-Factor Authentication (2FA) is enabled, you will be prompted for a 6-digit code sent to your email. Enter the code within 10 minutes.
5. After successful login you will land on the **Dashboard**.

> **Tip:** If you forget your username, ask your Super Administrator. Passwords cannot be recovered — only reset.

---

### 1.2 Dashboard Overview

The dashboard gives you a live snapshot of facility activity.

![Dashboard](screenshots/dashboard.png)

| Card | Meaning |
|------|---------|
| **Active Visitors** | People currently checked in |
| **Today's Visits** | Total check-ins since midnight |
| **Pending Appointments** | Appointments scheduled for today |
| **Total Visitors** | All-time registered visitor count |

Below the KPI cards you will find:
- **Recent Activity** — last 10 check-in/out events, refreshes every 30 seconds
- **Upcoming Appointments** — next 5 appointments for today
- **Notification Bell** — in-app alerts (top right header)

---

### 1.3 Changing Your Password

You should change the default password on first login.

1. Click your **name or avatar** in the top-right header.
2. Select **Profile / Change Password**.
3. Enter your current password, then choose a new password that meets the requirements:
   - At least 8 characters
   - At least one uppercase letter, one lowercase letter, one digit, one symbol
   - Not one of your last 10 passwords
4. Click **Save Password**.
5. You will be logged out and redirected to the login page.

> **Tip:** SVMS will never ask for your password via email. Do not share it.

---

### 1.4 Enabling Two-Factor Authentication (2FA)

2FA sends a one-time code to your registered email when you log in. It is strongly recommended.

1. Go to **Profile** (top-right menu).
2. Toggle **Enable Two-Factor Authentication** to On.
3. Enter your current password to confirm.
4. 2FA is now active. Your next login will require an email OTP.

---

### 1.5 Switching Theme and Language

- **Theme:** Click the **sun/moon icon** in the header to toggle between Light, Dark, and System modes.
- **Language:** Click the **globe icon** or the language selector to switch between English and Urdu (اردو). The UI relays to right-to-left layout for Urdu.

Your preference is saved per-account and persists across sessions.

---

## Chapter 2 — Daily Operations — Walk-In Visitors

**What you'll learn:** How to register a new visitor, perform a smart search for returning visitors, check them in, collect feedback, and check them out.

---

### 2.1 Registering a New Visitor

When someone walks in for the first time:

1. Click **Register Visitor** in the sidebar.
2. Fill in the form:
   - **Full Name** (required)
   - **CNIC** — 13-digit national ID (recommended for blacklist checking)
   - **Phone** (recommended)
   - **Email** (optional)
   - **Organization** (optional)
   - **Department they are visiting** — select from the dropdown
   - **Purpose of visit** (optional)
3. Upload a **visitor photo** using the camera icon or file picker (optional but recommended).
4. Fill in any **Custom Fields** shown (configured by your administrator).
5. Click **Register Visitor**.

SVMS will:
- Check the visitor's CNIC and phone against the **Blacklist** automatically.
- If flagged, display an alert and block check-in.
- Assign a unique **Badge Number** (e.g. `VIS-250601-A3F2C`).
- Generate a **QR code** for the visitor.

![Register Visitor](screenshots/register-visitor.png)

> **Tip:** Use "Quick Check-In" on the visitor detail page if they have visited before — no re-registration needed.

---

### 2.2 Smart Search for Returning Visitors

If a visitor has been here before:

1. Click **Visitor Records** or use the **Search Bar** at the top of any page.
2. Type a name, CNIC, phone number, badge number, or organization.
3. Results appear as you type. Click the visitor's name to open their profile.
4. From the profile, click **Check In** to record a new visit without re-entering data.

---

### 2.3 Checking In

From the visitor detail page:

1. Click **Check In**.
2. A modal appears — confirm or update:
   - Host name (person being visited)
   - Department
   - Purpose
   - Vehicle number (optional)
3. Click **Confirm Check-In**.

The dashboard's "Active Visitors" count increments immediately, and a notification is sent to all staff.

---

### 2.4 Capturing Visitor Photo at Check-In

If a camera is available:

- On the Check-In modal, click **Take Photo**. A webcam preview opens.
- Click **Capture**. The photo is saved with the visit record.
- Alternatively, upload an existing image using **Upload Photo**.

---

### 2.5 Checking Out

When the visitor leaves:

1. Find the visitor via Search or the **Active Visitors** list on the dashboard.
2. Open their profile and click **Check Out**.
3. Optionally add **Remarks** (e.g. "Delivered parcel").
4. Click **Confirm Check-Out**.

Duration is calculated automatically.

---

### 2.6 Collecting Feedback

After check-out, you can record a satisfaction rating:

1. On the visitor detail page, click **Add Feedback**.
2. Select a **star rating** (1–5) and type an optional comment.
3. Click **Submit Feedback**.

You can also send the visitor a feedback email link — SVMS generates a one-time public token so they can rate from outside the system.

---

### 2.7 Printing a Visitor Badge

1. On the visitor detail page, click **Print Badge**.
2. A printable badge card opens with:
   - Visitor name and photo
   - Badge number and QR code
   - Organization, department, and date
3. Use your browser's Print function (`Ctrl + P`) to print.

---

## Chapter 3 — Appointments & Scheduling

**What you'll learn:** How to schedule appointments, view the calendar, send e-passes, and handle kiosk arrivals.

---

### 3.1 Scheduling an Appointment

1. Sidebar → **Appointments** → **New Appointment**.
2. Fill in:
   - Visitor name, CNIC, phone, email
   - Department, host name, purpose
   - **Date and time** (`scheduled_at`)
   - **Duration** (default 30 minutes)
3. Click **Save Appointment**.

SVMS automatically sends a confirmation email with an **e-pass QR code** if the visitor's email is provided. A reminder email is sent 24 hours before the appointment by the cron job.

![Appointments Calendar](screenshots/appointments-calendar.png)

---

### 3.2 Appointment Calendar View

- Sidebar → **Appointments** → toggle the **Calendar** button.
- Appointments appear as color-coded blocks by department.
- Click any block to see appointment details.
- Use the **week / day / month** buttons to change the view.

---

### 3.3 Updating Appointment Status

| Status | When to use |
|--------|------------|
| `scheduled` | Default — appointment confirmed but not arrived |
| `confirmed` | Admin has called/confirmed attendance |
| `arrived` | Visitor has physically arrived and been checked in |
| `completed` | Visit is over |
| `cancelled` | Appointment cancelled |
| `no_show` | Visitor did not appear (auto-set by cron after 2 hours) |

Click the **status pill** on the appointment detail page to change it.

---

### 3.4 E-Pass & QR Scan Check-In

The email confirmation contains a QR code. When the visitor arrives:

1. The receptionist scans the QR code using the **Kiosk Mode** QR scanner or the manual entry field.
2. The appointment record is matched; the visitor's pre-filled data populates the check-in form.
3. One click checks them in and marks the appointment as `arrived`.

---

### 3.5 Kiosk Mode (Self-Service Arrival)

Kiosk mode lets visitors self-register or scan their appointment QR code on a public-facing tablet.

1. On the tablet browser, navigate to `.../kiosk/`.
2. The kiosk landing page shows two options: **Walk In** and **Scan Appointment QR**.
3. Self-registered walk-ins enter their name and CNIC; the system finds any existing record.
4. A receptionist or admin must **approve** kiosk arrivals from the dashboard notification.

![Kiosk Mode](screenshots/kiosk-mode.png)

> **Tip:** Use a dedicated device in full-screen / kiosk browser mode to prevent visitors from navigating away.

---

## Chapter 4 — Records, Reports & Audit Trail

**What you'll learn:** How to search visit history, export data to CSV, generate PDF reports, and review the audit log.

---

### 4.1 Visitor History

1. Sidebar → **History**.
2. Use the filter bar:
   - **Date range** (from/to)
   - **Department**
   - **Status** (checked_in / checked_out / no_show)
   - **Search term** (name, CNIC, badge)
3. Results show all visits matching your filters.
4. Click any row to open the full visit detail.

---

### 4.2 Exporting to CSV

1. Apply your desired filters on the History page.
2. Click **Export CSV**.
3. A CSV file downloads with all columns for the filtered rows.

> The CSV export is capped at 10,000 rows per download. For larger datasets, use direct database exports.

---

### 4.3 Generating PDF Reports

1. Sidebar → **Reports**.
2. Choose a report type:
   - **Daily Summary** — all visits for a selected date
   - **Weekly Summary** — 7-day aggregate
   - **Monthly Summary** — full month with charts
   - **Visitor Profile** — single visitor's full history
   - **Department Report** — visits by department over a period
3. Select the date range and any other filters.
4. Click **Generate PDF**.
5. The PDF opens in a new tab for printing or download.

![Reports](screenshots/reports.png)

> **Tip:** PDF reports are generated server-side using TCPDF. For complex reports, allow 5–10 seconds.

---

### 4.4 Audit Trail

The audit log records every significant action taken by any admin (login, check-in, settings change, backup, restore, etc.).

1. Sidebar → **Audit Log** (Super Admin only).
2. Filter by:
   - Admin user
   - Action type (e.g. `backup_create`, `restore`, `blacklist_add`)
   - Date range
3. Each row shows: timestamp, admin name, action, affected row ID, IP address.

The audit log is append-only and cannot be edited or deleted from the UI.

---

## Chapter 5 — Security Tools — Blacklist & Emergency Mode

**What you'll learn:** How to manage the blacklist, respond to security incidents, and use the emergency evacuation/lockdown tools.

---

### 5.1 Adding to the Blacklist

1. Sidebar → **Blacklist** → **Add to Blacklist**.
2. Fill in:
   - Name, CNIC, phone
   - **Reason** and **Severity** (Low / Medium / High)
   - Source (Internal, LEA Notice, Court Order, etc.)
   - Expiry date (leave blank for indefinite)
3. Click **Add to Blacklist**.

Any future visitor registration or check-in that matches this CNIC or phone will show a **red alert** and block the check-in.

![Blacklist](screenshots/blacklist.png)

---

### 5.2 Removing or Suspending a Blacklist Entry

1. Open the blacklist entry.
2. Click **Remove from Blacklist**.
3. Provide a removal reason (required).
4. The entry is marked inactive but kept in the database for audit purposes.

---

### 5.3 Emergency Mode

**Emergency Mode** is used during fire alarms, evacuations, or security lockdowns.

1. Sidebar → **Emergency Control Panel**.
2. Click **Trigger Emergency**.
3. Choose mode: **Evacuation** or **Lockdown**.
4. Optionally add notes.
5. Click **Confirm**.

What happens:
- All active check-ins are frozen — no new check-ins are allowed (Lockdown) or everyone is checked out (Evacuation).
- A **snapshot** is saved with a list of all visitors on-site at the time of trigger.
- All admins receive an emergency notification.
- The dashboard banner turns red with the emergency status.

**To end emergency mode:**
1. Open the Emergency Control Panel.
2. Click **Clear Emergency** → confirm.
3. The system returns to normal operation.

![Emergency Mode](screenshots/emergency-mode.png)

> **Tip:** Use the **Download Snapshot** button on the emergency panel to print a list of visitors for emergency personnel.

---

### 5.4 Session Management

1. Sidebar → **Settings** → **Active Sessions**.
2. View all currently logged-in admin sessions.
3. Click **Terminate Session** to force a specific admin out.
4. Only Super Admins can terminate other users' sessions.

---

## Chapter 6 — Configuration & Super Admin Settings

**What you'll learn:** How to manage users and roles, customize fields, configure SMTP email, change system settings, and run backups.

*This chapter applies only to Super Administrators.*

---

### 6.1 Managing Admin Accounts

1. Sidebar → **Admin Users**.
2. See all admins with their role and status.
3. Click **Add Admin** to create a new account.
   - Set name, username, email, role, and initial password.
   - The user must change their password on first login.
4. Click any admin's name to **edit** or **deactivate** their account.
5. Deactivated accounts cannot log in.

---

### 6.2 Roles & Permissions

Roles control what each admin can do. SVMS ships with three default roles:

| Role | Level |
|------|-------|
| Super Administrator | Full access including backup, restore, user management |
| Administrator | Full operational access; no restore or user management |
| Receptionist | Register, check-in/out, appointments, feedback |

To customize:

1. Sidebar → **Roles & Permissions**.
2. Click a role to edit its permission checkboxes.
3. Changes take effect immediately for all users with that role.

---

### 6.3 Custom Fields

Custom fields let you collect additional information during registration or appointment booking.

1. Sidebar → **Custom Fields** → **Add Field**.
2. Configure:
   - **Label** — shown to the receptionist
   - **Type** — text, number, date, select, checkbox
   - **Applies to** — registration, appointment, or both
   - **Required** — if checked, form cannot be submitted without this field
   - **Options** — for select fields, enter options separated by commas
3. Drag rows to reorder fields.

---

### 6.4 SMTP / Email Configuration

SVMS sends OTP codes, appointment confirmations, and reminders via email.

1. Sidebar → **Settings** → **Email** tab.
2. Fill in:
   - SMTP host, port, username, password
   - From email and display name
3. Click **Save Email Settings**.
4. Click **Send Test Email** to verify the configuration.

> Passwords are encrypted at rest using AES-256-CBC. They are never logged in plain text.

---

### 6.5 Site Settings

Sidebar → **Settings** → **General** tab:

| Setting | Description |
|---------|-------------|
| Site Name | Displayed in page title and reports |
| Default Language | `en` or `ur` for new accounts |
| Default Theme | `light`, `dark`, or `system` |
| Max Visit Hours | Auto-checkout threshold (default 8 hours) |

---

### 6.6 Backup & Restore

#### Creating a Manual Backup

1. Sidebar → **Backup & Restore**.
2. Click **Create Backup Now**.
3. A `.sql.gz` file is created in `logs/backups/` and listed in the backup history table.

#### Restoring a Backup

Only a Super Administrator may restore. The restore process:
1. Verifies your password
2. Requires you to type `RESTORE` to confirm
3. Restores the entire database from the selected file
4. Logs you out (all sessions are cleared after restore)

> **Warning:** Restoring overwrites all current data. Always create a fresh backup immediately before restoring.

#### Automated Backups

The cron job `scripts/cron_daily_backup.php` runs at 3:00 AM daily and creates automated backups automatically. The last 20 backups (or backups less than 30 days old) are kept.

![Backup & Restore](screenshots/backup-restore.png)

---

### 6.7 Department Management

1. Sidebar → **Departments**.
2. Add, edit, or delete departments.
3. Each department has a **colour** used in the appointment calendar and analytics charts.

---

### 6.8 Dark Mode & Themes

Themes can be set per-user (Profile) or as a system default (Settings → General → Default Theme). Dark mode applies a CSS class that switches colour tokens across all pages.

![Dark Mode](screenshots/dark-mode.png)

---

## Chapter 7 — Troubleshooting & FAQ

**What you'll learn:** How to resolve common issues and answer frequently asked questions.

---

### 7.1 I Cannot Log In

**Q: I entered the correct password but cannot log in.**
- Your account may be deactivated. Ask your Super Administrator to check.
- If 2FA is enabled, ensure you have access to your registered email.
- If you entered the wrong password multiple times, you may be rate-limited. Wait 60 seconds and try again.

**Q: I am not receiving the OTP email.**
- Check your spam/junk folder.
- Ask your administrator to verify the SMTP configuration under Settings → Email.
- Confirm your email address is correct in your admin profile.

---

### 7.2 Check-In Is Blocked with a "Blacklisted" Alert

The visitor's CNIC or phone matches an active blacklist entry. Do not proceed with check-in. Contact your security officer or the administrator who manages the blacklist.

---

### 7.3 Visitor Photo Does Not Upload

- Check the file size (max 5 MB).
- Accepted formats: JPEG, PNG, WebP.
- If on a shared server, confirm that `assets/uploads/` is writable by the web server user (`www-data` or `apache`).

---

### 7.4 PDF Report Is Blank or Has Rendering Errors

- Ensure the `vendor/` directory exists and Composer dependencies are installed (`composer install`).
- Check `logs/php_errors.log` for TCPDF-related errors.
- Verify PHP extensions `gd`, `mbstring`, and `zlib` are enabled.

---

### 7.5 Appointment Reminders Are Not Being Sent

- Confirm cron jobs are set up. Run `crontab -l` on the server to view the crontab.
- Check `logs/cron.log` for errors.
- Verify SMTP settings under Settings → Email.

---

### 7.6 SVMS Is Slow / Times Out

- Check MySQL query performance. Run `SHOW PROCESSLIST;` to identify long-running queries.
- Check PHP error log at `logs/php_errors.log`.
- For large databases, ensure indexes are in place (migration files add them).
- Increase PHP `max_execution_time` in `php.ini` if reports time out.

---

### 7.7 The Page Shows "403 Forbidden" or "You don't have permission"

You do not have the required permission for that page. Ask your Super Administrator to verify your role's permissions under Roles & Permissions.

---

### 7.8 I Accidentally Ran Emergency Mode

1. Immediately open **Emergency Control Panel**.
2. Click **Clear Emergency** → confirm.
3. Any visitors who were auto-checked out during evacuation mode will have a `status='auto_checkout'` record — you can manually re-check them in.

---

### 7.9 How Do I Reset a Forgotten Admin Password?

Only a Super Administrator can reset another admin's password from the **Admin Users** page. There is no self-service password reset link.

If the Super Administrator's own password is forgotten, it must be reset directly in the database:

```bash
# Generate a bcrypt hash for your new password
php -r "echo password_hash('YourNewPass@1234', PASSWORD_BCRYPT, ['cost'=>12]);"

# Update in MySQL
mysql -u root -p svms_db -e "UPDATE admins SET password='<hash>' WHERE username='admin';"
```

---

### 7.10 Frequently Asked Questions

**Q: Can the same person be a visitor and an admin?**  
A: No. Visitors and admin users are separate records. An admin can register themselves as a visitor if needed.

**Q: How many visitors can SVMS handle?**  
A: On a VPS with 2 GB RAM and MySQL properly tuned, SVMS comfortably handles 50,000+ visitor records and 500+ concurrent dashboard users.

**Q: Where are visitor photos stored?**  
A: In `assets/uploads/`. The directory is protected — PHP execution is blocked, but files can be served by Apache. For extra security, move the directory outside the web root and update `UPLOAD_DIR` in `config.php`.

**Q: Is SVMS accessible on mobile?**  
A: Yes. The interface is fully responsive. You can also install it as a PWA (Progressive Web App) from Chrome on Android or iOS Safari — click "Add to Home Screen."

**Q: How do I add a new language?**  
A: Copy `includes/lang/en.php` to `includes/lang/xx.php` (where `xx` is the ISO 639-1 code) and translate all values. Then add `'xx'` to the language switcher in `includes/i18n.php`.

**Q: Are backups encrypted?**  
A: Backup files are plain `.sql.gz` archives. Ensure the `logs/backups/` directory has restricted permissions (`chmod 770`) and is excluded from public web access.

---

*For further assistance, contact your System Administrator or refer to `docs/technical_reference.md`.*
