USE online_bridal_shop_db;

ALTER TABLE rentals
  ADD COLUMN terms_accepted TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
  ADD COLUMN payment_status ENUM('awaiting','confirmed','released') NOT NULL DEFAULT 'awaiting' AFTER terms_accepted;
