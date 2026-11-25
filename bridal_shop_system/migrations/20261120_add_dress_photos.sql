USE online_bridal_shop_db;
ALTER TABLE dresses ADD COLUMN photo_url VARCHAR(600) NOT NULL DEFAULT '';
UPDATE dresses SET photo_url=CASE dress_id
  WHEN 1 THEN 'https://images.unsplash.com/photo-1548313093-370cf4ba3892?auto=format&fit=crop&w=900&q=82'
  WHEN 2 THEN 'https://images.unsplash.com/photo-1585241920473-b472eb9ffbae?auto=format&fit=crop&w=900&q=82'
  WHEN 3 THEN 'https://images.unsplash.com/photo-1593575620619-602b4ddf6e96?auto=format&fit=crop&w=900&q=82'
  WHEN 4 THEN 'https://images.unsplash.com/photo-1645827028028-5470d8487de5?auto=format&fit=crop&w=900&q=82'
  ELSE 'https://images.unsplash.com/photo-1558888867-574563243917?auto=format&fit=crop&w=900&q=82'
END WHERE photo_url='';
