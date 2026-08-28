CREATE DATABASE IF NOT EXISTS meditrack_compact CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE meditrack_compact;

CREATE TABLE admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fullname VARCHAR(120) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('superadmin','manager','nurse','pharmacist','viewer') NOT NULL DEFAULT 'superadmin',
  theme VARCHAR(20) NOT NULL DEFAULT 'blue',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE staffmembers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id VARCHAR(30) NOT NULL UNIQUE,
  fullname VARCHAR(120) NOT NULL,
  role VARCHAR(80) NOT NULL,
  rfiduid VARCHAR(60) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE locations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  location_name VARCHAR(120) NOT NULL,
  location_type VARCHAR(40) NOT NULL DEFAULT 'ward',
  readerid VARCHAR(60) UNIQUE NULL,
  apikey VARCHAR(100) NULL,
  lastheartbeat DATETIME NULL,
  isactive TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE patients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_code VARCHAR(30) NOT NULL UNIQUE,
  fullname VARCHAR(150) NOT NULL,
  rfiduid VARCHAR(60) UNIQUE NULL,
  gender VARCHAR(20) NULL,
  age INT NULL,
  blood_group VARCHAR(10) NULL,
  phone VARCHAR(20) NULL,
  diagnosis VARCHAR(255) NULL,
  ward_id INT NULL,
  bed_no VARCHAR(20) NULL,
  status ENUM('admitted','icu','critical','discharged') NOT NULL DEFAULT 'admitted',
  fall_risk TINYINT(1) NOT NULL DEFAULT 0,
  elopement_risk TINYINT(1) NOT NULL DEFAULT 0,
  watch_level VARCHAR(20) NOT NULL DEFAULT 'normal',
  last_seen_at DATETIME NULL,
  last_seen_location_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ward (ward_id)
);

CREATE TABLE caretakers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  fullname VARCHAR(120) NOT NULL,
  relation_name VARCHAR(60) NULL,
  phone VARCHAR(20) NULL,
  email VARCHAR(120) NULL,
  address TEXT NULL,
  emergency_contact VARCHAR(20) NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_patient (patient_id)
);

CREATE TABLE caretaker_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  caretaker_id INT NOT NULL,
  patient_id INT NOT NULL,
  username VARCHAR(80) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_caretaker (caretaker_id)
);

CREATE TABLE caretaker_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  caretaker_id INT NOT NULL,
  patient_id INT NOT NULL,
  token VARCHAR(80) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_token (token)
);

CREATE TABLE items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  uid VARCHAR(60) NOT NULL UNIQUE,
  item_name VARCHAR(150) NOT NULL,
  item_type ENUM('medicine','equipment','asset') NOT NULL DEFAULT 'asset',
  brand VARCHAR(100) NULL,
  batch_no VARCHAR(60) NULL,
  quantity INT NOT NULL DEFAULT 1,
  unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  expiry_date DATE NULL,
  location_id INT NULL,
  status ENUM('instock','inuse','missing','expired') NOT NULL DEFAULT 'instock',
  recall_flag TINYINT(1) NOT NULL DEFAULT 0,
  cold_chain_required TINYINT(1) NOT NULL DEFAULT 0,
  reorder_threshold INT NOT NULL DEFAULT 10,
  reorder_qty INT NOT NULL DEFAULT 50,
  supplier_name VARCHAR(120) NULL,
  last_seen_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_item_type (item_type),
  INDEX idx_location (location_id)
);

CREATE TABLE scanlogs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  uid VARCHAR(60) NOT NULL,
  item_id INT NULL,
  patient_id INT NULL,
  staff_id INT NULL,
  from_location_id INT NULL,
  to_location_id INT NULL,
  readerid VARCHAR(60) NOT NULL,
  action_type VARCHAR(40) NOT NULL,
  notes VARCHAR(255) NULL,
  scan_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_time (scan_time)
);

CREATE TABLE alerts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  severity ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
  alert_type VARCHAR(40) NOT NULL,
  message VARCHAR(255) NOT NULL,
  related_url VARCHAR(255) NULL,
  is_resolved TINYINT(1) NOT NULL DEFAULT 0,
  resolved_by VARCHAR(120) NULL,
  resolved_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_resolved (is_resolved),
  INDEX idx_created (created_at)
);

CREATE TABLE notification_reads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  alert_id INT NOT NULL,
  admin_id INT NOT NULL,
  read_at DATETIME NOT NULL,
  UNIQUE KEY uniq_read (alert_id, admin_id)
);

CREATE TABLE patientvitals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  temperature DECIMAL(4,1) NULL,
  systolic_bp INT NULL,
  diastolic_bp INT NULL,
  pulse_rate INT NULL,
  spo2 INT NULL,
  respiratory_rate INT NULL,
  notes VARCHAR(255) NULL,
  alert_summary VARCHAR(255) NULL,
  recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_patient (patient_id)
);

CREATE TABLE medicationschedule (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  item_id INT NOT NULL,
  dose VARCHAR(50) NOT NULL,
  route_name VARCHAR(40) NOT NULL,
  scheduled_time DATETIME NOT NULL,
  status ENUM('pending','administered','missed','refused') NOT NULL DEFAULT 'pending',
  compliance_status ENUM('pending','on_time','late','missed','refused') NOT NULL DEFAULT 'pending',
  verified_staff_uid VARCHAR(60) NULL,
  verified_patient_uid VARCHAR(60) NULL,
  verified_medicine_uid VARCHAR(60) NULL,
  verified_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_patient_time (patient_id, scheduled_time),
  INDEX idx_status (status)
);

CREATE TABLE medicationadministrations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  schedule_id INT NOT NULL,
  patient_id INT NOT NULL,
  item_id INT NOT NULL,
  staff_id INT NULL,
  note_text VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE medication_verifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  schedule_id INT NOT NULL,
  patient_id INT NOT NULL,
  staff_id INT NOT NULL,
  item_id INT NOT NULL,
  result_text ENUM('pass','fail') NOT NULL DEFAULT 'pass',
  message_text VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE iv_drips (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  item_id INT NULL,
  fluid_name VARCHAR(120) NOT NULL,
  total_ml INT NOT NULL,
  remaining_ml INT NOT NULL,
  flow_rate_ml_hr DECIMAL(10,2) NOT NULL,
  started_at DATETIME NOT NULL,
  eta_end DATETIME NULL,
  status ENUM('running','paused','completed') NOT NULL DEFAULT 'running',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_patient_status (patient_id, status)
);

CREATE TABLE patientsafetyprofiles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patientid INT NOT NULL UNIQUE,
  allowedlocations VARCHAR(255) NULL,
  restrictedlocations VARCHAR(255) NULL,
  maxunseenminutes INT NOT NULL DEFAULT 120,
  washroomlimitminutes INT NOT NULL DEFAULT 15,
  bedexitenabled TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE workflowtasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  task_key VARCHAR(120) NOT NULL UNIQUE,
  task_type VARCHAR(40) NOT NULL,
  title VARCHAR(200) NOT NULL,
  description VARCHAR(500) NULL,
  patient_id INT NOT NULL DEFAULT 0,
  item_id INT NOT NULL DEFAULT 0,
  priority ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  status ENUM('open','inprogress','done') NOT NULL DEFAULT 'open',
  due_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status_priority (status, priority)
);

CREATE TABLE audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL DEFAULT 0,
  action VARCHAR(80) NOT NULL,
  target_table VARCHAR(60) NOT NULL DEFAULT '',
  target_id INT NOT NULL DEFAULT 0,
  detail VARCHAR(500) NOT NULL DEFAULT '',
  ip VARCHAR(45) NOT NULL DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created (created_at)
);

CREATE TABLE batch_traces (
  id INT AUTO_INCREMENT PRIMARY KEY,
  batchno VARCHAR(60) NOT NULL,
  item_id INT NOT NULL,
  patient_id INT NOT NULL DEFAULT 0,
  location_id INT NOT NULL DEFAULT 0,
  scan_time DATETIME NOT NULL,
  INDEX idx_batch (batchno),
  INDEX idx_scan_time (scan_time)
);

CREATE TABLE maintenance_schedule (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  last_service_date DATE NULL,
  next_service_date DATE NOT NULL,
  service_notes VARCHAR(255) NULL,
  status ENUM('pending','done','overdue') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE discharge_checklist (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  admin_id INT NOT NULL DEFAULT 0,
  meds_cleared TINYINT(1) NOT NULL DEFAULT 0,
  iv_completed TINYINT(1) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE offline_scan_queue (
  id INT AUTO_INCREMENT PRIMARY KEY,
  uid VARCHAR(60) NOT NULL,
  readerid VARCHAR(60) NOT NULL,
  queued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed TINYINT(1) NOT NULL DEFAULT 0,
  processed_at DATETIME NULL,
  result VARCHAR(100) NULL
);

CREATE TABLE systemlogs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  log_level VARCHAR(20) NOT NULL,
  source_name VARCHAR(60) NOT NULL,
  message_text VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -------------------------------------------------------
-- Seed Data
-- -------------------------------------------------------

INSERT INTO admins (fullname, username, password, role, theme) VALUES
('Project Admin', 'admin', '$2y$10$Uses9A0mAc6OyxW1vQv8w.e7z3xKzq2ut9EHpJKqpji7YIY/3eMZm', 'superadmin', 'blue');

INSERT INTO staffmembers (employee_id, fullname, role, rfiduid) VALUES
('EMP001','Nurse Priya','Nurse','STAFF001'),
('EMP002','Pharmacist Arun','Pharmacist','STAFF002');

INSERT INTO locations (location_name, location_type, readerid, apikey) VALUES
('General Ward 1','ward','ESP32GW1','KEYGW1'),
('Pharmacy','pharmacy','ESP32PH1','KEYPH1'),
('ICU','icu','ESP32ICU1','KEYICU1');

INSERT INTO patients (patient_code, fullname, rfiduid, gender, age, blood_group, phone, diagnosis, ward_id, bed_no, status) VALUES
('PAT001','Rahul Sharma','PAT001','Male',45,'B+','9876543210','Post-op care',1,'GW1-01','admitted'),
('PAT002','Anita Verma','PAT002','Female',31,'O+','9876543211','Pneumonia',3,'ICU-02','icu');

INSERT INTO caretakers (patient_id, fullname, relation_name, phone, email, emergency_contact) VALUES
(1,'Suman Sharma','Wife','9000000001','suman@example.com','9000000001'),
(2,'Rakesh Verma','Brother','9000000002','rakesh@example.com','9000000002');

INSERT INTO items (uid, item_name, item_type, brand, quantity, unit_cost, expiry_date, location_id, status) VALUES
('MED001','Paracetamol 650mg','medicine','Cipla',100,2.50,'2027-12-31',2,'instock'),
('MED002','Ceftriaxone 1g','medicine','Abbott',50,35.00,'2027-10-31',2,'instock'),
('EQ001','Infusion Pump','equipment','BPL',5,25000.00,NULL,3,'instock'),
('AS001','Wheelchair','asset','Karma',8,6000.00,NULL,1,'instock');

INSERT INTO medicationschedule (patient_id, item_id, dose, route_name, scheduled_time) VALUES
(1,1,'1 Tablet','oral', DATE_ADD(NOW(), INTERVAL 20 MINUTE)),
(2,2,'1 Vial','iv', DATE_ADD(NOW(), INTERVAL 40 MINUTE));