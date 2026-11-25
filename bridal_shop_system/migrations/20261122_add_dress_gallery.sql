USE online_bridal_shop_db;

CREATE TABLE IF NOT EXISTS dress_photos (
  photo_id INT AUTO_INCREMENT PRIMARY KEY,
  dress_id INT NOT NULL,
  photo_url VARCHAR(600) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_dress_photos_dress FOREIGN KEY (dress_id) REFERENCES dresses(dress_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;
