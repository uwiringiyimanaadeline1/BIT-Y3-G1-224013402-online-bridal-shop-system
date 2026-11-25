USE online_bridal_shop_db;
ALTER TABLE dresses ADD COLUMN description TEXT NULL;
UPDATE dresses SET description=CASE
  WHEN name LIKE '%Princess%' THEN 'Classic princess silhouette with a flowing skirt.'
  WHEN name LIKE '%Mermaid%' THEN 'Elegant fitted gown with a flared hem.'
  WHEN name LIKE '%A-Line%' THEN 'Simple A-line gown for a timeless bridal look.'
  WHEN name LIKE '%Lace%' THEN 'Delicate lace details with a graceful silhouette.'
  ELSE 'A beautiful bridal gown for your special day.'
END WHERE description IS NULL OR description='';
