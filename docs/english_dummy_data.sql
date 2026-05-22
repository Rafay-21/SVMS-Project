SET NAMES utf8mb4;
USE svms_db;
SET FOREIGN_KEY_CHECKS = 0;

INSERT IGNORE INTO `admins`
  (`name`, `username`, `email`, `password`, `role_id`, `is_active`, `theme`, `language`, `last_login_at`, `last_login_ip`)
VALUES
  ('Olivia Carter', 'olivia.carter', 'olivia.carter@svms.local', '$2y$12$eImiTXuWVxfM37uY4JANjOe5XPDAEbdPLSBKu.bqHv9VGX5v5v8sG', 2, 1, 'light', 'en', DATE_SUB(NOW(), INTERVAL 1 HOUR), '192.168.1.24'),
  ('James Wilson', 'james.wilson', 'james.wilson@svms.local', '$2y$12$eImiTXuWVxfM37uY4JANjOe5XPDAEbdPLSBKu.bqHv9VGX5v5v8sG', 3, 1, 'dark', 'en', DATE_SUB(NOW(), INTERVAL 3 HOUR), '192.168.1.31'),
  ('Sophia Bennett', 'sophia.bennett', 'sophia.bennett@svms.local', '$2y$12$eImiTXuWVxfM37uY4JANjOe5XPDAEbdPLSBKu.bqHv9VGX5v5v8sG', 4, 1, 'light', 'en', DATE_SUB(NOW(), INTERVAL 5 HOUR), '192.168.1.42');

INSERT IGNORE INTO `visitors`
  (`name`, `cnic`, `phone`, `email`, `organization`, `badge_number`, `qr_token`, `department_id`, `notes`, `created_by`, `created_at`)
VALUES
  ('John Anderson', '42101-1000001-1', '+1-555-0101', 'john.anderson@example.com', 'Northstar Consulting', 'VIS-260522-EN001', 'en-demo-visitor-001', 1, 'Executive briefing visitor', 1, DATE_SUB(NOW(), INTERVAL 12 DAY)),
  ('Emily Johnson', '42101-1000002-2', '+1-555-0102', 'emily.johnson@example.com', 'BrightPath Labs', 'VIS-260522-EN002', 'en-demo-visitor-002', 4, 'Software vendor representative', 1, DATE_SUB(NOW(), INTERVAL 11 DAY)),
  ('Michael Smith', '42101-1000003-3', '+1-555-0103', 'michael.smith@example.com', 'Apex Logistics', 'VIS-260522-EN003', 'en-demo-visitor-003', 6, 'Logistics coordination meeting', 2, DATE_SUB(NOW(), INTERVAL 10 DAY)),
  ('Sarah Williams', '42101-1000004-4', '+1-555-0104', 'sarah.williams@example.com', 'Greenfield Finance', 'VIS-260522-EN004', 'en-demo-visitor-004', 3, 'Quarterly invoice review', 2, DATE_SUB(NOW(), INTERVAL 9 DAY)),
  ('David Brown', '42101-1000005-5', '+1-555-0105', 'david.brown@example.com', 'Metro Legal Group', 'VIS-260522-EN005', 'en-demo-visitor-005', 7, 'Contract review session', 1, DATE_SUB(NOW(), INTERVAL 8 DAY)),
  ('Jessica Davis', '42101-1000006-6', '+1-555-0106', 'jessica.davis@example.com', 'Public Reach Media', 'VIS-260522-EN006', 'en-demo-visitor-006', 8, 'Media relations discussion', 3, DATE_SUB(NOW(), INTERVAL 7 DAY)),
  ('Robert Miller', '42101-1000007-7', '+1-555-0107', 'robert.miller@example.com', 'SecureGate Systems', 'VIS-260522-EN007', 'en-demo-visitor-007', 5, 'Security systems inspection', 3, DATE_SUB(NOW(), INTERVAL 6 DAY)),
  ('Amanda Wilson', '42101-1000008-8', '+1-555-0108', 'amanda.wilson@example.com', 'PeopleFirst HR', 'VIS-260522-EN008', 'en-demo-visitor-008', 2, 'Recruitment partnership meeting', 2, DATE_SUB(NOW(), INTERVAL 5 DAY)),
  ('Christopher Moore', '42101-1000009-9', '+1-555-0109', 'christopher.moore@example.com', 'BlueWave Technologies', 'VIS-260522-EN009', 'en-demo-visitor-009', 4, 'Network upgrade consultation', 1, DATE_SUB(NOW(), INTERVAL 4 DAY)),
  ('Ashley Taylor', '42101-1000010-0', '+1-555-0110', 'ashley.taylor@example.com', 'UrbanWorks Studio', 'VIS-260522-EN010', 'en-demo-visitor-010', 1, 'Facilities planning workshop', 1, DATE_SUB(NOW(), INTERVAL 3 DAY)),
  ('Daniel Thomas', '42101-1000011-1', '+1-555-0111', 'daniel.thomas@example.com', 'Civic Supplies Co.', 'VIS-260522-EN011', 'en-demo-visitor-011', 6, 'Procurement delivery follow-up', 2, DATE_SUB(NOW(), INTERVAL 2 DAY)),
  ('Laura Martinez', '42101-1000012-2', '+1-555-0112', 'laura.martinez@example.com', 'Summit Analytics', 'VIS-260522-EN012', 'en-demo-visitor-012', 3, 'Financial reporting demo', 2, DATE_SUB(NOW(), INTERVAL 1 DAY)),
  ('Kevin Clark', '42101-1000013-3', '+1-555-0113', 'kevin.clark@example.com', 'CloudBridge Inc.', 'VIS-260522-EN013', 'en-demo-visitor-013', 4, 'Cloud migration planning', 1, NOW()),
  ('Rachel Lewis', '42101-1000014-4', '+1-555-0114', 'rachel.lewis@example.com', 'Premier Events', 'VIS-260522-EN014', 'en-demo-visitor-014', 8, 'Event coordination meeting', 3, NOW()),
  ('Brian Walker', '42101-1000015-5', '+1-555-0115', 'brian.walker@example.com', 'Integrity Audits', 'VIS-260522-EN015', 'en-demo-visitor-015', 1, 'Internal audit discussion', 1, NOW());

INSERT IGNORE INTO `appointments`
  (`visitor_id`, `visitor_name`, `cnic`, `phone`, `email`, `department_id`, `person_to_meet`, `host_name`, `purpose`, `notes`, `scheduled_at`, `duration_minutes`, `status`, `qr_token`, `created_by`, `created_at`)
SELECT id, name, cnic, phone, email, department_id,
  CASE department_id WHEN 1 THEN 'Emma Roberts' WHEN 2 THEN 'Henry Adams' WHEN 3 THEN 'Grace Turner' WHEN 4 THEN 'Nathan Scott' WHEN 5 THEN 'Peter Evans' WHEN 6 THEN 'Linda Cooper' WHEN 7 THEN 'Victoria Reed' ELSE 'Megan Phillips' END,
  CASE department_id WHEN 1 THEN 'Emma Roberts' WHEN 2 THEN 'Henry Adams' WHEN 3 THEN 'Grace Turner' WHEN 4 THEN 'Nathan Scott' WHEN 5 THEN 'Peter Evans' WHEN 6 THEN 'Linda Cooper' WHEN 7 THEN 'Victoria Reed' ELSE 'Megan Phillips' END,
  notes, 'Generated English demo appointment', DATE_ADD(NOW(), INTERVAL (id % 9) DAY), 45 + ((id % 3) * 15),
  CASE id % 5 WHEN 0 THEN 'confirmed' WHEN 1 THEN 'scheduled' WHEN 2 THEN 'arrived' WHEN 3 THEN 'completed' ELSE 'scheduled' END,
  CONCAT('en-demo-appt-', LPAD(id, 3, '0')), 1, DATE_SUB(NOW(), INTERVAL (id % 6) DAY)
FROM visitors
WHERE qr_token LIKE 'en-demo-visitor-%';

INSERT IGNORE INTO `visit_log`
  (`visitor_id`, `appointment_id`, `host_name`, `department_id`, `purpose`, `vehicle_number`, `check_in_time`, `check_out_time`, `status`, `remarks`, `checked_in_by`, `checked_out_by`, `created_at`)
SELECT v.id, a.id, a.host_name, v.department_id, a.purpose,
  CONCAT('ABC-', LPAD(v.id + 120, 3, '0')),
  DATE_SUB(NOW(), INTERVAL (v.id % 72) HOUR),
  CASE WHEN v.id % 4 = 0 THEN NULL ELSE DATE_SUB(NOW(), INTERVAL ((v.id % 72) - 1) HOUR) END,
  CASE WHEN v.id % 4 = 0 THEN 'checked_in' ELSE 'checked_out' END,
  'English demo visit log entry', 2,
  CASE WHEN v.id % 4 = 0 THEN NULL ELSE 2 END,
  DATE_SUB(NOW(), INTERVAL (v.id % 72) HOUR)
FROM visitors v
LEFT JOIN appointments a ON a.visitor_id = v.id
WHERE v.qr_token LIKE 'en-demo-visitor-%';

INSERT IGNORE INTO `feedback`
  (`visit_id`, `visitor_id`, `rating`, `comment`, `source`, `public_token`, `created_at`)
SELECT vl.id, vl.visitor_id,
  CASE vl.id % 5 WHEN 0 THEN 5 WHEN 1 THEN 4 WHEN 2 THEN 5 WHEN 3 THEN 3 ELSE 4 END,
  CASE vl.id % 4 WHEN 0 THEN 'Fast check-in and helpful front desk team.' WHEN 1 THEN 'Clear directions and professional service.' WHEN 2 THEN 'Security process was smooth and efficient.' ELSE 'Good overall visitor experience.' END,
  CASE vl.id % 2 WHEN 0 THEN 'visitor' ELSE 'staff' END,
  CONCAT('en-demo-feedback-', LPAD(vl.id, 3, '0')),
  DATE_ADD(vl.created_at, INTERVAL 2 HOUR)
FROM visit_log vl
JOIN visitors v ON v.id = vl.visitor_id
WHERE v.qr_token LIKE 'en-demo-visitor-%' AND vl.status = 'checked_out';

INSERT IGNORE INTO `notifications`
  (`type`, `title`, `message`, `link`, `recipient_id`, `visible_to_role_id`, `is_read`, `created_at`)
VALUES
  ('checkin', 'John Anderson Checked In', 'John Anderson from Northstar Consulting is currently on site.', 'pages/visitor_detail.php?id=6', NULL, NULL, 0, DATE_SUB(NOW(), INTERVAL 10 MINUTE)),
  ('appointment', 'Upcoming IT Vendor Meeting', 'Emily Johnson has a confirmed appointment with Nathan Scott.', 'pages/appointments.php', NULL, NULL, 0, DATE_SUB(NOW(), INTERVAL 35 MINUTE)),
  ('feedback', 'New Visitor Feedback', 'A visitor rated the check-in experience 5 stars.', 'pages/feedback_view.php', NULL, NULL, 0, DATE_SUB(NOW(), INTERVAL 1 HOUR)),
  ('security', 'Security Inspection Scheduled', 'Robert Miller will visit for security systems inspection.', 'pages/appointments.php', NULL, 4, 0, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
  ('system', 'Demo Dataset Added', 'English dummy data has been loaded for presentation screens.', 'pages/dashboard.php', 1, NULL, 1, NOW());

INSERT INTO `audit_logs`
  (`admin_id`, `action`, `target_id`, `details`, `ip_address`, `user_agent`, `created_at`)
VALUES
  (1, 'demo_data_imported', 0, '{"dataset":"english_dummy_data","records":"visitors appointments visits feedback notifications"}', '127.0.0.1', 'SVMS Demo Seeder', NOW()),
  (2, 'visitor_checked_in', 0, '{"source":"english_dummy_data"}', '192.168.1.31', 'SVMS Demo Seeder', DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
  (3, 'appointment_confirmed', 0, '{"source":"english_dummy_data"}', '192.168.1.42', 'SVMS Demo Seeder', DATE_SUB(NOW(), INTERVAL 2 HOUR));

SET FOREIGN_KEY_CHECKS = 1;
