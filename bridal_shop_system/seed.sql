USE online_bridal_shop_db;

INSERT INTO dresses (name,size,color,rental_price,available,description,photo_url) VALUES
('Princess Wedding Dress','M','White',150000,1,'Classic princess silhouette with a flowing skirt.','https://images.unsplash.com/photo-1548313093-370cf4ba3892?auto=format&fit=crop&w=900&q=82'),
('Mermaid Wedding Dress','L','Ivory',180000,1,'Elegant fitted gown with a flared hem.','https://images.unsplash.com/photo-1585241920473-b472eb9ffbae?auto=format&fit=crop&w=900&q=82'),
('A-Line Wedding Dress','S','White',120000,1,'Simple A-line gown suitable for a timeless look.','https://images.unsplash.com/photo-1593575620619-602b4ddf6e96?auto=format&fit=crop&w=900&q=82'),
('Lace Bridal Gown','M','Cream',160000,0,'Lace bridal gown; currently unavailable.','https://images.unsplash.com/photo-1645827028028-5470d8487de5?auto=format&fit=crop&w=900&q=82'),
('Luxury Ball Gown','XL','White',200000,1,'Formal ball gown with a full skirt.','https://images.unsplash.com/photo-1558888867-574563243917?auto=format&fit=crop&w=900&q=82');

-- Register an account through the website, then promote it to manager with:
-- UPDATE users SET role='manager' WHERE email='your-manager-email@example.com';
