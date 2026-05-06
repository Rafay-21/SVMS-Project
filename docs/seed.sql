-- ============================================================
-- SVMS v2.0 — Demo Seed Dataset
-- docs/seed.sql
--
-- Imports realistic demo data for evaluation / training.
-- Run AFTER database_setup.sql:
--   mysql -u svms_user -p svms_db < docs/seed.sql
--
-- Default admin login: admin / Admin@1234!
-- CHANGE THE PASSWORD IMMEDIATELY AFTER FIRST LOGIN.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── Default super-admin account ──────────────────────────────
-- Password: Admin@1234!  (bcrypt cost=12)
INSERT IGNORE INTO `admins`
  (`name`, `username`, `email`, `password`, `role_id`, `is_active`, `theme`, `language`)
VALUES
  ('System Administrator', 'admin', 'admin@svms.local',
   '$2y$12$eImiTXuWVxfM37uY4JANjOe5XPDAEbdPLSBKu.bqHv9VGX5v5v8sG',
   1, 1, 'light', 'en');

-- ── Sample receptionist ───────────────────────────────────────
-- Password: Pass@1234!
INSERT IGNORE INTO `admins`
  (`name`, `username`, `email`, `password`, `role_id`, `is_active`, `theme`, `language`)
VALUES
  ('Sara Khan', 'sara.khan', 'sara@svms.local',
   '$2y$12$eImiTXuWVxfM37uY4JANjOe5XPDAEbdPLSBKu.bqHv9VGX5v5v8sG',
   3, 1, 'light', 'en');

-- ── Sample visitors ───────────────────────────────────────────
INSERT IGNORE INTO `visitors`
  (`name`, `cnic`, `phone`, `email`, `organization`, `badge_number`, `qr_token`, `department_id`, `created_by`)
VALUES
  ('Ahmed Raza',   '35202-1234567-1', '0300-1234567', 'ahmed@example.com',   'Raza Enterprises',    'VIS-250601-AA001', '1001aa001', 1, 1),
  ('Fatima Malik', '35202-9876543-2', '0321-9876543', 'fatima@example.com',  'Global Tech Ltd.',    'VIS-250601-BB002', '2002bb002', 4, 1),
  ('Usman Ali',    '35202-1357924-3', '0333-1357924', 'usman@example.com',   'Ali & Sons Pvt Ltd',  'VIS-250601-CC003', '3003cc003', 2, 1),
  ('Ayesha Siddiq','35202-2468013-4', '0345-2468013', 'ayesha@example.com',  'National Bank',       'VIS-250601-DD004', '4004dd004', 3, 1),
  ('Bilal Hassan', '35202-3692581-5', '0312-3692581', 'bilal@example.com',   'Pakistan Post',       'VIS-250601-EE005', '5005ee005', 6, 1);

-- ── Sample appointments ───────────────────────────────────────
INSERT IGNORE INTO `appointments`
  (`visitor_name`, `cnic`, `phone`, `email`, `department_id`, `person_to_meet`, `host_name`,
   `purpose`, `scheduled_at`, `duration_minutes`, `status`, `qr_token`, `created_by`)
VALUES
  ('Ahmed Raza',   '35202-1234567-1', '0300-1234567', 'ahmed@example.com',
   1, 'Director Admin', 'Director Admin',
   'Annual compliance review', DATE_ADD(NOW(), INTERVAL 1 DAY), 30,
   'scheduled', 'apt001qr', 1),
  ('Fatima Malik', '35202-9876543-2', '0321-9876543', 'fatima@example.com',
   4, 'IT Manager', 'IT Manager',
   'System integration meeting', DATE_ADD(NOW(), INTERVAL 2 DAY), 60,
   'confirmed', 'apt002qr', 1),
  ('New Applicant', '', '0300-5551234', 'applicant@example.com',
   2, 'HR Director', 'HR Director',
   'Job interview', DATE_ADD(NOW(), INTERVAL 3 DAY), 45,
   'scheduled', 'apt003qr', 2);

-- ── Sample visit log ─────────────────────────────────────────
INSERT IGNORE INTO `visit_log`
  (`visitor_id`, `host_name`, `department_id`, `purpose`,
   `check_in_time`, `check_out_time`, `status`, `checked_in_by`)
VALUES
  (1, 'Director Admin', 1, 'Annual review',
   DATE_SUB(NOW(), INTERVAL 3 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR),
   'checked_out', 1),
  (2, 'IT Manager', 4, 'System integration',
   DATE_SUB(NOW(), INTERVAL 2 HOUR), NULL,
   'checked_in', 2),
  (3, 'HR Director', 2, 'Recruitment',
   DATE_SUB(NOW(), INTERVAL 5 HOUR), DATE_SUB(NOW(), INTERVAL 3 HOUR),
   'checked_out', 2),
  (4, 'Finance Officer', 3, 'Invoice discussion',
   DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 23 HOUR),
   'checked_out', 1),
  (5, 'Operations Head', 6, 'Delivery query',
   DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY) + INTERVAL 1 HOUR,
   'checked_out', 1);

-- ── Sample feedback ───────────────────────────────────────────
INSERT IGNORE INTO `feedback`
  (`visit_id`, `visitor_id`, `rating`, `comment`, `source`)
VALUES
  (1, 1, 5, 'Very smooth process. Staff was helpful.', 'staff'),
  (3, 3, 4, 'Waiting time was acceptable.', 'staff'),
  (4, 4, 3, 'Could be faster.', 'visitor');

-- ── Sample notifications ──────────────────────────────────────
INSERT IGNORE INTO `notifications`
  (`type`, `title`, `message`, `link`, `recipient_id`, `is_read`)
VALUES
  ('checkin',      'Visitor Checked In',     'Fatima Malik has checked in.',
   'pages/visitor_detail.php?id=2', NULL, 0),
  ('appointment',  'Appointment Scheduled',  'Ahmed Raza has an appointment tomorrow.',
   'pages/appointments.php', NULL, 0),
  ('system',       'Welcome to SVMS v2.0',   'Deployment successful. Please change your default password.',
   'pages/profile.php', 1, 0);

SET FOREIGN_KEY_CHECKS = 1;
