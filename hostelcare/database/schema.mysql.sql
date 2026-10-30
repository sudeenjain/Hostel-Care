-- HostelCare multi-hostel schema for MySQL 5.7+ / MariaDB 10.3+. Statements are separated by a semicolon at the end of a line.
-- Create the database first:  CREATE DATABASE hostelcare CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- Every hostel's data is separated by hostel_id. Platform administrators have hostel_id NULL.
CREATE TABLE hostels (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  type ENUM('boys','girls','co_ed') NOT NULL,
  address VARCHAR(250) NOT NULL,
  city VARCHAR(80) NOT NULL,
  state VARCHAR(80) NOT NULL,
  pincode VARCHAR(12) NOT NULL,
  total_blocks INT UNSIGNED NOT NULL,
  total_rooms INT UNSIGNED NOT NULL,
  resident_count INT UNSIGNED NOT NULL,
  hostel_phone VARCHAR(20) NULL,
  owner_name VARCHAR(120) NOT NULL,
  owner_email VARCHAR(190) NOT NULL,
  owner_phone VARCHAR(20) NOT NULL,
  owner_designation VARCHAR(60) NOT NULL,
  status ENUM('unverified','pending','approved','active','rejected','suspended') NOT NULL DEFAULT 'unverified',
  verify_hash CHAR(64) NULL,
  verify_expires DATETIME NULL,
  email_verified_at DATETIME NULL,
  decision_hash CHAR(64) NULL,
  decision_expires DATETIME NULL,
  decided_at DATETIME NULL,
  decided_by INT UNSIGNED NULL,
  decision_note VARCHAR(500) NULL,
  setup_hash CHAR(64) NULL,
  setup_expires DATETIME NULL,
  owner_user_id INT UNSIGNED NULL,
  blocks TEXT NOT NULL,
  registration_open TINYINT(1) NOT NULL DEFAULT 1,
  qr_token VARCHAR(48) NULL UNIQUE,
  published_at DATETIME NULL,
  created_ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_hostels_status (status),
  INDEX idx_hostels_email (owner_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hostel_id INT UNSIGNED NULL,
  login_id VARCHAR(190) NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(20) NULL,
  sms_opt_in TINYINT(1) NOT NULL DEFAULT 0,
  block VARCHAR(40) NULL,
  room VARCHAR(40) NULL,
  role ENUM('student','warden','admin') NOT NULL,
  is_owner TINYINT(1) NOT NULL DEFAULT 0,
  password_hash VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_user_login (hostel_id, login_id),
  INDEX idx_users_hostel (hostel_id, role),
  CONSTRAINT fk_users_hostel FOREIGN KEY (hostel_id) REFERENCES hostels(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hostel_id INT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  default_priority ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  sla_hours INT UNSIGNED NOT NULL DEFAULT 48,
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_category (hostel_id, name),
  CONSTRAINT fk_cat_hostel FOREIGN KEY (hostel_id) REFERENCES hostels(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE staff (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hostel_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  trade VARCHAR(80) NOT NULL,
  phone VARCHAR(20) NULL,
  note VARCHAR(250) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  INDEX idx_staff_hostel (hostel_id),
  CONSTRAINT fk_staff_hostel FOREIGN KEY (hostel_id) REFERENCES hostels(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE hostel_fields (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hostel_id INT UNSIGNED NOT NULL,
  label VARCHAR(80) NOT NULL,
  field_type ENUM('dropdown','text','textarea') NOT NULL,
  options TEXT NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  INDEX idx_fields_hostel (hostel_id, sort_order),
  CONSTRAINT fk_field_hostel FOREIGN KEY (hostel_id) REFERENCES hostels(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE complaints (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  hostel_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  description TEXT NOT NULL,
  location VARCHAR(160) NOT NULL,
  extra_json TEXT NULL,
  priority ENUM('low','medium','high') NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'submitted',
  is_confidential TINYINT(1) NOT NULL DEFAULT 0,
  assigned_staff_id INT UNSIGNED NULL,
  due_at DATETIME NOT NULL,
  acknowledged_at DATETIME NULL,
  resolved_at DATETIME NULL,
  closed_at DATETIME NULL,
  rating TINYINT UNSIGNED NULL,
  feedback TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_complaints_hostel (hostel_id, status),
  INDEX idx_complaints_student (student_id),
  INDEX idx_complaints_due (due_at),
  CONSTRAINT fk_c_hostel FOREIGN KEY (hostel_id) REFERENCES hostels(id),
  CONSTRAINT fk_c_student FOREIGN KEY (student_id) REFERENCES users(id),
  CONSTRAINT fk_c_category FOREIGN KEY (category_id) REFERENCES categories(id),
  CONSTRAINT fk_c_staff FOREIGN KEY (assigned_staff_id) REFERENCES staff(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE complaint_updates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  complaint_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  old_status VARCHAR(20) NULL,
  new_status VARCHAR(20) NULL,
  note TEXT NULL,
  is_internal TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  INDEX idx_updates_complaint (complaint_id),
  CONSTRAINT fk_u_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
  CONSTRAINT fk_u_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE attachments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  complaint_id INT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(80) NOT NULL,
  mime VARCHAR(60) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_a_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  complaint_id INT UNSIGNED NULL,
  message VARCHAR(255) NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  INDEX idx_notifications_user (user_id, is_read),
  CONSTRAINT fk_n_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_n_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sms_outbox (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  phone VARCHAR(20) NOT NULL,
  message VARCHAR(400) NOT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'queued',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error VARCHAR(255) NULL,
  gateway_id VARCHAR(80) NULL,
  created_at DATETIME NOT NULL,
  sent_at DATETIME NULL,
  INDEX idx_sms_status (status, id),
  CONSTRAINT fk_sms_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE mail_outbox (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  to_email VARCHAR(190) NOT NULL,
  subject VARCHAR(250) NOT NULL,
  body_text TEXT NOT NULL,
  body_html MEDIUMTEXT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'queued',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  sent_at DATETIME NULL,
  INDEX idx_mail_status (status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identifier VARCHAR(190) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_login_attempts (identifier, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE settings (
  k VARCHAR(60) PRIMARY KEY,
  v VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
