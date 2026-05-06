# SVMS Deployment Checklist
**Version 2.0** | Target: Fresh LAMP / XAMPP Server

Use this checklist end-to-end when deploying SVMS to a new server.
Tick each box as you complete it. A fresh deploy should take **under 30 minutes**.

---

## 1. Server Requirements

### Minimum Software
- [ ] **OS:** Ubuntu 22.04 LTS / Debian 12 / CentOS Stream 9 (or Windows Server 2019+ with XAMPP 8.2)
- [ ] **Web Server:** Apache 2.4+ with:
  - [ ] `mod_rewrite` enabled (`a2enmod rewrite`)
  - [ ] `mod_headers` enabled (`a2enmod headers`)
  - [ ] `mod_ssl` enabled (`a2enmod ssl`)
  - [ ] `mod_expires` enabled (`a2enmod expires`)
  - [ ] `AllowOverride All` in VirtualHost block
- [ ] **PHP:** 8.1 or higher
  - [ ] Extensions: `mysqli`, `mbstring`, `json`, `gd`, `openssl`, `exif`, `fileinfo`, `zlib`
  - [ ] `file_uploads = On`
  - [ ] `upload_max_filesize` ≥ 10M
  - [ ] `post_max_size` ≥ 20M
  - [ ] `max_execution_time` ≥ 60
  - [ ] `memory_limit` ≥ 128M
- [ ] **Database:** MySQL 8.0+ **or** MariaDB 10.6+
- [ ] **Composer:** 2.x (only needed to reinstall dependencies; `vendor/` ships in the release ZIP)

### Verify PHP extensions
```bash
php -m | grep -E "mysqli|mbstring|gd|openssl|exif|fileinfo|zlib|json"
```

### Recommended VPS Sizing
| Traffic Level | CPU | RAM | Disk |
|---------------|-----|-----|------|
| Up to 50 simultaneous visitors/day | 1 vCPU | 1 GB | 20 GB SSD |
| Up to 500/day | 2 vCPU | 2 GB | 40 GB SSD |
| 500–5 000/day | 4 vCPU | 4 GB | 80 GB SSD |

---

## 2. DNS & SSL

- [ ] Point DNS **A record** for your domain (e.g. `svms.example.com`) to the server IP
- [ ] Wait for DNS propagation (check: `dig svms.example.com +short`)
- [ ] Obtain SSL certificate — recommended: **Let's Encrypt**
  ```bash
  sudo apt install certbot python3-certbot-apache
  sudo certbot --apache -d svms.example.com
  ```
- [ ] Verify auto-renewal: `sudo certbot renew --dry-run`

---

## 3. Deploy Application Files

### 3.1 Copy Files
```bash
# From your local machine / CI pipeline:
scp -r ./svms user@server:/var/www/html/

# Or on the server:
git clone https://github.com/your-org/svms.git /var/www/html/svms
```

### 3.2 Set Ownership
```bash
sudo chown -R www-data:www-data /var/www/html/svms
```

### 3.3 Set Permissions
```bash
# Files: 644  |  Directories: 755  |  Sensitive dirs: 770
find /var/www/html/svms -type f -exec chmod 644 {} \;
find /var/www/html/svms -type d -exec chmod 755 {} \;
chmod 770 /var/www/html/svms/logs
chmod 770 /var/www/html/svms/assets/uploads
chmod 770 /var/www/html/svms/backups

# config/keys.php is auto-generated; ensure config/ dir has restricted access
chmod 700 /var/www/html/svms/config 2>/dev/null || true
```

> **Windows/XAMPP:** Place the `svms/` folder under `C:\xampp\htdocs\svms\`. No chmod needed.

---

## 4. Configure the Application

### 4.1 Create `config.php`
```bash
cp /var/www/html/svms/docs/environment_config.php.example /var/www/html/svms/config.php
nano /var/www/html/svms/config.php
```

Edit every `CHANGE_ME` placeholder:

| Constant | Example value |
|----------|--------------|
| `DB_HOST` | `localhost` |
| `DB_USER` | `svms_user` |
| `DB_PASS` | `strong_random_password` |
| `DB_NAME` | `svms_db` |
| `BASE_URL` | `https://svms.example.com/` |
| `SMTP_HOST` | `smtp.sendgrid.net` |
| `SMTP_USER` | `apikey` |
| `SMTP_PASS` | *(your SMTP password — will be encrypted on first settings save)* |
| `APP_KEY` | *(generate: `php -r "echo bin2hex(random_bytes(32));"`)* |
| `IS_DEV` | `false` |

### 4.2 Install Production `.htaccess`
```bash
cp /var/www/html/svms/docs/htaccess_production.txt /var/www/html/svms/.htaccess
```

This file is identical to the development `.htaccess` but with:
- HTTPS redirect rule **uncommented**
- HSTS header **uncommented**

---

## 5. Database Setup

### 5.1 Create Database & User
```sql
-- Run in MySQL as root:
CREATE DATABASE svms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'svms_user'@'localhost' IDENTIFIED BY 'strong_random_password';
GRANT ALL PRIVILEGES ON svms_db.* TO 'svms_user'@'localhost';
FLUSH PRIVILEGES;
```

### 5.2 Import Schema
```bash
mysql -u svms_user -p svms_db < /var/www/html/svms/docs/database_setup.sql
```

### 5.3 Import Seed Data (optional — demo dataset)
```bash
mysql -u svms_user -p svms_db < /var/www/html/svms/docs/seed.sql
```

### 5.4 Verify
```sql
USE svms_db;
SHOW TABLES;
-- Expect: admins, appointments, audit_logs, backups, blacklist, custom_fields,
--         custom_field_values, departments, email_queue, emergency_snapshots,
--         feedback, notifications, roles, settings, visit_log, visitors,
--         admin_password_history
SELECT COUNT(*) FROM admins;   -- ≥ 1 (the default super admin)
SELECT COUNT(*) FROM roles;    -- ≥ 3 (super_admin, admin, receptionist)
```

---

## 6. Cron Jobs

Add all five cron entries as `www-data` user:
```bash
sudo -u www-data crontab -e
```

Paste the following lines (adjust path to match your install):
```cron
# SVMS Cron Jobs
# ─────────────────────────────────────────────────────────────
# Process email queue every 2 minutes
*/2 * * * * php /var/www/html/svms/scripts/cron_email_queue.php >> /var/www/html/svms/logs/cron.log 2>&1

# Send appointment reminders every 15 minutes
*/15 * * * * php /var/www/html/svms/scripts/cron_appointment_reminders.php >> /var/www/html/svms/logs/cron.log 2>&1

# Mark no-show appointments once per hour
0 * * * * php /var/www/html/svms/scripts/cron_appointment_no_show.php >> /var/www/html/svms/logs/cron.log 2>&1

# Auto-checkout visitors who exceed MAX_VISIT_HOURS, every 15 minutes
*/15 * * * * php /var/www/html/svms/scripts/cron_auto_checkout.php >> /var/www/html/svms/logs/cron.log 2>&1

# Daily database backup at 3:00 AM
0 3 * * * php /var/www/html/svms/scripts/cron_daily_backup.php >> /var/www/html/svms/logs/cron.log 2>&1

# Daily digest email at 7:00 AM
0 7 * * * php /var/www/html/svms/scripts/cron_daily_digest.php >> /var/www/html/svms/logs/cron.log 2>&1
```

Verify cron is running: `sudo tail -f /var/www/html/svms/logs/cron.log`

---

## 7. One-Time Post-Deploy Scripts

- [ ] Run badge regenerator (re-indexes any legacy records):
  ```bash
  php /var/www/html/svms/scripts/regenerate_badges.php
  ```

---

## 8. Apache VirtualHost (Linux only)

Create `/etc/apache2/sites-available/svms.conf`:
```apache
<VirtualHost *:443>
    ServerName svms.example.com
    DocumentRoot /var/www/html/svms
    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/svms.example.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/svms.example.com/privkey.pem

    <Directory /var/www/html/svms>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/svms_error.log
    CustomLog ${APACHE_LOG_DIR}/svms_access.log combined
</VirtualHost>

<VirtualHost *:80>
    ServerName svms.example.com
    Redirect permanent / https://svms.example.com/
</VirtualHost>
```

```bash
sudo a2ensite svms.conf
sudo systemctl reload apache2
```

---

## 9. First Login & Security Hardening

- [ ] Browse to `https://svms.example.com/` → redirects to login
- [ ] Log in with default credentials (from `docs/seed.sql` or set during setup)
- [ ] **Immediately change the default admin password** (Profile → Change Password)
- [ ] Set SMTP credentials under Settings → Email
- [ ] Test email delivery: Settings → Email → Send Test Email
- [ ] Verify backup directory is writable: Backup → Create Backup Now
- [ ] Check `logs/php_errors.log` is empty

---

## 10. Post-Deployment Smoke Test

Run the 10-minute checklist in [post_deployment_test.md](post_deployment_test.md).

---

## Troubleshooting Quick Reference

| Symptom | Likely Cause | Fix |
|---------|-------------|-----|
| 500 error on first load | `config.php` missing or DB unreachable | Check `logs/php_errors.log` |
| 403 on all pages | `AllowOverride All` not set | Edit Apache VirtualHost; `a2enmod rewrite` |
| Emails not sending | SMTP credentials wrong / firewall blocking port 587 | Test with `php scripts/test_smtp.php` |
| Photos not saving | `assets/uploads/` not writable | `chmod 770 assets/uploads` |
| Backups failing | `logs/backups/` not writable or mysqldump absent | Install `mysql-client`; check perms |
| Session expiring too fast | `SESSION_LIFETIME_HOURS` too low | Increase in `config.php` |
| QR codes blank | GD extension not loaded | `sudo apt install php-gd && systemctl restart apache2` |
