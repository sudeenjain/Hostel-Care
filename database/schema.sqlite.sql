-- HostelCare multi-hostel schema for SQLite. Statements are separated by a semicolon at the end of a line.
-- Every hostel's data is separated by hostel_id. Platform administrators have hostel_id NULL.
CREATE TABLE hostels (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  type TEXT NOT NULL CHECK (type IN ('boys','girls','co_ed')),
  address TEXT NOT NULL,
  city TEXT NOT NULL,
  state TEXT NOT NULL,
  pincode TEXT NOT NULL,
  total_blocks INTEGER NOT NULL,
  total_rooms INTEGER NOT NULL,
  resident_count INTEGER NOT NULL,
  hostel_phone TEXT,
  owner_name TEXT NOT NULL,
  owner_email TEXT NOT NULL,
  owner_phone TEXT NOT NULL,
  owner_designation TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'unverified' CHECK (status IN ('unverified','pending','approved','active','rejected','suspended')),
  verify_hash TEXT,
  verify_expires TEXT,
  email_verified_at TEXT,
  decision_hash TEXT,
  decision_expires TEXT,
  decided_at TEXT,
  decided_by INTEGER,
  decision_note TEXT,
  setup_hash TEXT,
  setup_expires TEXT,
  owner_user_id INTEGER,
  blocks TEXT NOT NULL DEFAULT '',
  registration_open INTEGER NOT NULL DEFAULT 1,
  qr_token TEXT UNIQUE,
  published_at TEXT,
  created_ip TEXT,
  created_at TEXT NOT NULL
);
CREATE INDEX idx_hostels_status ON hostels(status);
CREATE INDEX idx_hostels_email ON hostels(owner_email);

CREATE TABLE users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  hostel_id INTEGER REFERENCES hostels(id),
  login_id TEXT NOT NULL,
  name TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  phone TEXT,
  sms_opt_in INTEGER NOT NULL DEFAULT 0,
  block TEXT,
  room TEXT,
  role TEXT NOT NULL CHECK (role IN ('student','warden','admin')),
  is_owner INTEGER NOT NULL DEFAULT 0,
  password_hash TEXT NOT NULL,
  active INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL,
  UNIQUE (hostel_id, login_id)
);
CREATE INDEX idx_users_hostel ON users(hostel_id, role);

CREATE TABLE categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  hostel_id INTEGER NOT NULL REFERENCES hostels(id),
  name TEXT NOT NULL,
  default_priority TEXT NOT NULL DEFAULT 'medium' CHECK (default_priority IN ('low','medium','high')),
  sla_hours INTEGER NOT NULL DEFAULT 48,
  active INTEGER NOT NULL DEFAULT 1,
  UNIQUE (hostel_id, name)
);

CREATE TABLE staff (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  hostel_id INTEGER NOT NULL REFERENCES hostels(id),
  name TEXT NOT NULL,
  trade TEXT NOT NULL,
  phone TEXT,
  note TEXT,
  active INTEGER NOT NULL DEFAULT 1
);
CREATE INDEX idx_staff_hostel ON staff(hostel_id);

CREATE TABLE hostel_fields (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  hostel_id INTEGER NOT NULL REFERENCES hostels(id),
  label TEXT NOT NULL,
  field_type TEXT NOT NULL CHECK (field_type IN ('dropdown','text','textarea')),
  options TEXT,
  is_required INTEGER NOT NULL DEFAULT 0,
  sort_order INTEGER NOT NULL DEFAULT 0,
  active INTEGER NOT NULL DEFAULT 1
);
CREATE INDEX idx_fields_hostel ON hostel_fields(hostel_id, sort_order);

CREATE TABLE complaints (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  hostel_id INTEGER NOT NULL REFERENCES hostels(id),
  student_id INTEGER NOT NULL REFERENCES users(id),
  category_id INTEGER NOT NULL REFERENCES categories(id),
  title TEXT NOT NULL,
  description TEXT NOT NULL,
  location TEXT NOT NULL,
  extra_json TEXT,
  priority TEXT NOT NULL CHECK (priority IN ('low','medium','high')),
  status TEXT NOT NULL DEFAULT 'submitted',
  is_confidential INTEGER NOT NULL DEFAULT 0,
  assigned_staff_id INTEGER REFERENCES staff(id),
  due_at TEXT NOT NULL,
  acknowledged_at TEXT,
  resolved_at TEXT,
  closed_at TEXT,
  rating INTEGER,
  feedback TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);
CREATE INDEX idx_complaints_hostel ON complaints(hostel_id, status);
CREATE INDEX idx_complaints_student ON complaints(student_id);
CREATE INDEX idx_complaints_due ON complaints(due_at);

CREATE TABLE complaint_updates (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  complaint_id INTEGER NOT NULL REFERENCES complaints(id) ON DELETE CASCADE,
  user_id INTEGER REFERENCES users(id),
  old_status TEXT,
  new_status TEXT,
  note TEXT,
  is_internal INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);
CREATE INDEX idx_updates_complaint ON complaint_updates(complaint_id);

CREATE TABLE attachments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  complaint_id INTEGER NOT NULL REFERENCES complaints(id) ON DELETE CASCADE,
  original_name TEXT NOT NULL,
  stored_name TEXT NOT NULL,
  mime TEXT NOT NULL,
  size_bytes INTEGER NOT NULL,
  created_at TEXT NOT NULL
);

CREATE TABLE notifications (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  complaint_id INTEGER REFERENCES complaints(id) ON DELETE CASCADE,
  message TEXT NOT NULL,
  is_read INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);
CREATE INDEX idx_notifications_user ON notifications(user_id, is_read);

CREATE TABLE sms_outbox (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  phone TEXT NOT NULL,
  message TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'queued',
  attempts INTEGER NOT NULL DEFAULT 0,
  last_error TEXT,
  gateway_id TEXT,
  created_at TEXT NOT NULL,
  sent_at TEXT
);
CREATE INDEX idx_sms_status ON sms_outbox(status, id);

CREATE TABLE mail_outbox (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  to_email TEXT NOT NULL,
  subject TEXT NOT NULL,
  body_text TEXT NOT NULL,
  body_html TEXT,
  status TEXT NOT NULL DEFAULT 'queued',
  attempts INTEGER NOT NULL DEFAULT 0,
  last_error TEXT,
  created_at TEXT NOT NULL,
  sent_at TEXT
);
CREATE INDEX idx_mail_status ON mail_outbox(status, id);

CREATE TABLE login_attempts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  identifier TEXT NOT NULL,
  ip TEXT NOT NULL,
  created_at TEXT NOT NULL
);
CREATE INDEX idx_login_attempts ON login_attempts(identifier, created_at);

CREATE TABLE settings (
  k TEXT PRIMARY KEY,
  v TEXT NOT NULL
);
