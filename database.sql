CREATE DATABASE IF NOT EXISTS lost_found CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lost_found;


DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS item_match_notifications;
DROP TABLE IF EXISTS claims;
DROP TABLE IF EXISTS found_items;
DROP TABLE IF EXISTS lost_items;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS admins;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  uid VARCHAR(50) NOT NULL UNIQUE,
  dept VARCHAR(100) NOT NULL,
  role VARCHAR(30) NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  uid VARCHAR(50) NOT NULL UNIQUE,
  dept VARCHAR(100) NOT NULL,
  role VARCHAR(30) NOT NULL DEFAULT 'admin',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE lost_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  user_name VARCHAR(100) NOT NULL,
  item_name VARCHAR(150) NOT NULL,
  category VARCHAR(50) NOT NULL,
  description TEXT,
  keywords VARCHAR(255),
  image_path VARCHAR(255),
  date_lost DATE NOT NULL,
  location VARCHAR(150) NOT NULL,
  status ENUM('Active','Resolved') NOT NULL DEFAULT 'Active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE found_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  user_name VARCHAR(100) NOT NULL,
  item_name VARCHAR(150) NOT NULL,
  category VARCHAR(50) NOT NULL,
  description TEXT,
  keywords VARCHAR(255),
  image_path VARCHAR(255),
  date_found DATE NOT NULL,
  location VARCHAR(150) NOT NULL,
  status ENUM('Available','Claimed') NOT NULL DEFAULT 'Available',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE claims (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  lost_item_id INT NULL,
  item_name VARCHAR(150) NOT NULL,
  user_id INT NOT NULL,
  user_name VARCHAR(100) NOT NULL,
  details TEXT NOT NULL,
  image_path VARCHAR(255),
  status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (item_id) REFERENCES found_items(id) ON DELETE CASCADE,
  FOREIGN KEY (lost_item_id) REFERENCES lost_items(id) ON DELETE SET NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE item_match_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  lost_item_id INT NOT NULL,
  found_item_id INT NOT NULL,
  match_score INT NOT NULL DEFAULT 0,
  match_reason VARCHAR(255),
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_match (user_id,lost_item_id,found_item_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (lost_item_id) REFERENCES lost_items(id) ON DELETE CASCADE,
  FOREIGN KEY (found_item_id) REFERENCES found_items(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(50) NOT NULL,
  description TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;



INSERT INTO users (name,email,password,uid,dept,role,status,is_admin) VALUES
('Demo User','user@demo.com','password','24102535','Information Technology','student','active',0);

INSERT INTO admins (name,email,password,uid,dept,role,status) VALUES
('Admin User','admin@demo.com','admin123','ADMIN001','Administration','admin','active');

INSERT INTO found_items (user_id,user_name,item_name,category,description,keywords,date_found,location,status) VALUES
(1,'Demo User','Samsung Galaxy S22','Phone','A black Samsung phone with cracked screen protector','phone samsung black','2026-05-12','Library Block','Available'),
(1,'Demo User','Brown Leather Wallet','Wallet','Contains ID cards and some cash','wallet brown leather','2026-05-14','Canteen','Available'),
(1,'Demo User','Blue Backpack','Bag','Adidas bag with laptop compartment','bag blue adidas','2026-05-15','Seminar Hall','Available'),
(1,'Demo User','Dell Laptop Bag','Laptop','Black bag with charger inside','dell laptop charger','2026-05-17','IT Lab','Available'),
(1,'Demo User','Engineering Drawing Book','Books','Has name written inside cover','drawing book','2026-05-18','Sports Ground','Available');

INSERT INTO lost_items (user_id,user_name,item_name,category,description,keywords,date_lost,location,status) VALUES
(1,'Demo User','iPhone 14 Pro','Phone','Space grey, has CUCEK sticker on back','iphone grey sticker','2026-05-11','Library Block','Active'),
(1,'Demo User','Blue Water Bottle','Others','1 litre Nalgene bottle','blue bottle','2026-05-13','Canteen','Active'),
(1,'Demo User','Student ID Card','ID Card','Laminated CUCEK ID','id card student','2026-05-16','Seminar Hall','Active');

INSERT INTO audit_logs (user_id,action,description) VALUES
(NULL,'SYSTEM','Demo data initialized');
