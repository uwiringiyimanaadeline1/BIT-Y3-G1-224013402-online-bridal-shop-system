CREATE DATABASE IF NOT EXISTS online_bridal_shop_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE online_bridal_shop_db;

CREATE TABLE IF NOT EXISTS users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('customer','manager') NOT NULL DEFAULT 'customer',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS dresses (
  dress_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  size VARCHAR(20) NOT NULL,
  color VARCHAR(50) NOT NULL,
  rental_price DECIMAL(10,2) NOT NULL,
  available TINYINT(1) NOT NULL DEFAULT 1,
  description TEXT NULL,
  photo_url VARCHAR(600) NOT NULL DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS dress_photos (
  photo_id INT AUTO_INCREMENT PRIMARY KEY,
  dress_id INT NOT NULL,
  photo_url VARCHAR(600) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_dress_photos_dress FOREIGN KEY (dress_id) REFERENCES dresses(dress_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rentals (
  rental_id INT AUTO_INCREMENT PRIMARY KEY,
  dress_id INT NOT NULL,
  customer_id INT NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  total DECIMAL(10,2) NOT NULL,
  status ENUM('pending','approved','returned','cancelled') NOT NULL DEFAULT 'pending',
  terms_accepted TINYINT(1) NOT NULL DEFAULT 0,
  payment_status ENUM('awaiting','confirmed','released') NOT NULL DEFAULT 'awaiting',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rental_dress FOREIGN KEY (dress_id) REFERENCES dresses(dress_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_rental_customer FOREIGN KEY (customer_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fittings (
  fitting_id INT AUTO_INCREMENT PRIMARY KEY,
  rental_id INT NOT NULL,
  fitting_date DATE NOT NULL,
  notes TEXT NULL,
  completed TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_fitting_rental FOREIGN KEY (rental_id) REFERENCES rentals(rental_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_dresses_name ON dresses(name);
CREATE INDEX idx_rentals_customer ON rentals(customer_id);
CREATE INDEX idx_rentals_status ON rentals(status);
