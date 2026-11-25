USE online_bridal_shop_db;
INSERT INTO users (username,email,password_hash,role) VALUES
('Shop Manager','uwiringiyimanaadeline1@gmail.com','$2y$10$G1DtqjrcElRbfLVB89wGIuzQbRQKw2LhIAcJ6ZJ0PiscwwAw7Puo.','manager')
ON DUPLICATE KEY UPDATE username=VALUES(username),password_hash=VALUES(password_hash),role='manager';
