-- SVMS Schema Fix Script
-- Fixes all column name mismatches between the PHP code and the actual database

-- 1. admins: rename name → full_name
ALTER TABLE admins CHANGE `name` `full_name` VARCHAR(150) NOT NULL;

-- 2. visitors: rename name → full_name
ALTER TABLE visitors CHANGE `name` `full_name` VARCHAR(150) NOT NULL;

-- 3. visitors: add missing columns
ALTER TABLE visitors ADD COLUMN `vip` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE visitors ADD COLUMN `custom_data` TEXT NULL;

-- 4. visit_log: rename host_name → person_to_meet
ALTER TABLE visit_log CHANGE `host_name` `person_to_meet` VARCHAR(150) NOT NULL DEFAULT '';

-- 5. visit_log: add missing columns
ALTER TABLE visit_log ADD COLUMN `badge_number` VARCHAR(50) NULL;
ALTER TABLE visit_log ADD COLUMN `visitor_type` VARCHAR(20) NOT NULL DEFAULT 'walk_in';
ALTER TABLE visit_log ADD COLUMN `registered_by` INT UNSIGNED NULL;

-- 6. departments: add is_active
ALTER TABLE departments ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1;

-- 7. Add view_visitors permission to all roles
UPDATE roles SET permissions = JSON_SET(permissions, '$.view_visitors', TRUE);

-- 8. Create visits VIEW (used by dashboard)
CREATE OR REPLACE VIEW visits AS
SELECT
    vl.id,
    vl.visitor_id,
    v.full_name,
    v.phone,
    v.cnic,
    v.email,
    v.photo_path,
    v.vip,
    vl.badge_number,
    vl.person_to_meet AS host_name,
    vl.department_id,
    COALESCE(d.name, '') AS host_department,
    COALESCE(d.name, '') AS dept_name,
    vl.purpose,
    vl.vehicle_number,
    vl.visitor_type,
    vl.check_in_time,
    vl.check_out_time,
    vl.status,
    vl.check_in_photo,
    vl.remarks,
    vl.checked_in_by,
    vl.registered_by,
    vl.checked_out_by,
    vl.created_at
FROM visit_log vl
JOIN visitors v ON v.id = vl.visitor_id
LEFT JOIN departments d ON d.id = vl.department_id;

SELECT 'All schema fixes applied successfully.' AS result;
