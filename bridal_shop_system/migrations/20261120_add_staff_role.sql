USE online_bridal_shop_db;

ALTER TABLE users
  MODIFY role ENUM('customer','manager','admin','staff') NOT NULL DEFAULT 'customer';

UPDATE users
SET role='manager'
WHERE role IN ('admin','staff');

ALTER TABLE users
  MODIFY role ENUM('customer','manager') NOT NULL DEFAULT 'customer';
