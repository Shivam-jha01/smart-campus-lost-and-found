-- Schema for Vercel Postgres / Neon PostgreSQL
-- CUCEK Smart Campus Lost & Found System

DROP TABLE IF EXISTS audit_logs CASCADE;
DROP TABLE IF EXISTS item_match_notifications CASCADE;
DROP TABLE IF EXISTS claims CASCADE;
DROP TABLE IF EXISTS found_items CASCADE;
DROP TABLE IF EXISTS lost_items CASCADE;
DROP TABLE IF EXISTS users CASCADE;
DROP TABLE IF EXISTS admins CASCADE;
DROP TABLE IF EXISTS email_notifications CASCADE;

CREATE TABLE users (
  id SERIAL PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  uid VARCHAR(50) NOT NULL UNIQUE,
  dept VARCHAR(100) NOT NULL,
  role VARCHAR(30) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  is_admin SMALLINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE admins (
  id SERIAL PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  uid VARCHAR(50) NOT NULL UNIQUE,
  dept VARCHAR(100) NOT NULL,
  role VARCHAR(30) NOT NULL DEFAULT 'admin',
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE lost_items (
  id SERIAL PRIMARY KEY,
  user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  user_name VARCHAR(100) NOT NULL,
  item_name VARCHAR(150) NOT NULL,
  category VARCHAR(50) NOT NULL,
  description TEXT,
  keywords VARCHAR(255),
  image_path TEXT,
  date_lost DATE NOT NULL,
  location VARCHAR(150) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'Active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE found_items (
  id SERIAL PRIMARY KEY,
  user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  user_name VARCHAR(100) NOT NULL,
  item_name VARCHAR(150) NOT NULL,
  category VARCHAR(50) NOT NULL,
  description TEXT,
  keywords VARCHAR(255),
  image_path TEXT,
  date_found DATE NOT NULL,
  location VARCHAR(150) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'Available',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE claims (
  id SERIAL PRIMARY KEY,
  item_id INT NOT NULL REFERENCES found_items(id) ON DELETE CASCADE,
  lost_item_id INT REFERENCES lost_items(id) ON DELETE SET NULL,
  item_name VARCHAR(150) NOT NULL,
  user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  user_name VARCHAR(100) NOT NULL,
  details TEXT NOT NULL,
  image_path TEXT,
  status VARCHAR(20) NOT NULL DEFAULT 'Pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE item_match_notifications (
  id SERIAL PRIMARY KEY,
  user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  lost_item_id INT NOT NULL REFERENCES lost_items(id) ON DELETE CASCADE,
  found_item_id INT NOT NULL REFERENCES found_items(id) ON DELETE CASCADE,
  match_score INT NOT NULL DEFAULT 0,
  match_reason VARCHAR(255),
  is_read SMALLINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT unique_match UNIQUE (user_id, lost_item_id, found_item_id)
);

CREATE TABLE audit_logs (
  id SERIAL PRIMARY KEY,
  user_id INT REFERENCES users(id) ON DELETE SET NULL,
  action VARCHAR(50) NOT NULL,
  description TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE email_notifications (
  id SERIAL PRIMARY KEY,
  user_id INT REFERENCES users(id) ON DELETE SET NULL,
  recipient_email VARCHAR(120) NOT NULL,
  subject VARCHAR(180) NOT NULL,
  message TEXT NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'queued',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_lost_items_user ON lost_items(user_id);
CREATE INDEX IF NOT EXISTS idx_found_items_user ON found_items(user_id);
CREATE INDEX IF NOT EXISTS idx_claims_user ON claims(user_id);
CREATE INDEX IF NOT EXISTS idx_claims_item ON claims(item_id);
CREATE INDEX IF NOT EXISTS idx_match_user ON item_match_notifications(user_id);

-- Seed Initial Data
INSERT INTO users (name, email, password, uid, dept, role, status, is_admin) VALUES
('Demo User', 'user@demo.com', '$2y$10$w8uMlhb9eLhE7qL0eK78h.UeWvLgZkpF.e34u6z2O90325dJz8u8G', '24102535', 'Information Technology', 'student', 'active', 0);

INSERT INTO admins (name, email, password, uid, dept, role, status) VALUES
('Admin User', 'admin@demo.com', '$2y$10$w8uMlhb9eLhE7qL0eK78h.UeWvLgZkpF.e34u6z2O90325dJz8u8G', 'ADMIN001', 'Administration', 'admin', 'active');

INSERT INTO found_items (user_id, user_name, item_name, category, description, keywords, date_found, location, status) VALUES
(1, 'Demo User', 'Samsung Galaxy S22', 'Phone', 'A black Samsung phone with cracked screen protector', 'phone samsung black', '2026-05-12', 'Library Block', 'Available'),
(1, 'Demo User', 'Brown Leather Wallet', 'Wallet', 'Contains ID cards and some cash', 'wallet brown leather', '2026-05-14', 'Canteen', 'Available'),
(1, 'Demo User', 'Blue Backpack', 'Bag', 'Adidas bag with laptop compartment', 'bag blue adidas', '2026-05-15', 'Seminar Hall', 'Available'),
(1, 'Demo User', 'Dell Laptop Bag', 'Laptop', 'Black bag with charger inside', 'dell laptop charger', '2026-05-17', 'IT Lab', 'Available'),
(1, 'Demo User', 'Engineering Drawing Book', 'Books', 'Has name written inside cover', 'drawing book', '2026-05-18', 'Sports Ground', 'Available');

INSERT INTO lost_items (user_id, user_name, item_name, category, description, keywords, date_lost, location, status) VALUES
(1, 'Demo User', 'iPhone 14 Pro', 'Phone', 'Space grey, has CUCEK sticker on back', 'iphone grey sticker', '2026-05-11', 'Library Block', 'Active'),
(1, 'Demo User', 'Blue Water Bottle', 'Others', '1 litre Nalgene bottle', 'blue bottle', '2026-05-13', 'Canteen', 'Active'),
(1, 'Demo User', 'Student ID Card', 'ID Card', 'Laminated CUCEK ID', 'id card student', '2026-05-16', 'Seminar Hall', 'Active');

INSERT INTO audit_logs (user_id, action, description) VALUES
(NULL, 'SYSTEM', 'PostgreSQL database initialized successfully');
