-- Sample Data for Testing POS System
-- Insert categories first (code_prefix is the 3-letter DFA product-code prefix per category)
INSERT INTO categories (id, category_name, code_prefix) VALUES 
(1, 'Snacks',   'SNA'),
(2, 'Beverage', 'BEV')
ON DUPLICATE KEY UPDATE code_prefix = VALUES(code_prefix);

-- Insert sample products with stored product_code values
-- Format: <CATEGORY_PREFIX><3-digit product id>
INSERT INTO products (id, product_name, category_id, product_code, price, stock, status) VALUES
(1,  'Piattos',              1, 'SNA001', 40.00, 10, 'Available'),
(2,  'Chilsung',             2, 'BEV002', 25.00,  0, 'Out of Stock'),
(3,  'Chippy',               1, 'SNA003', 35.00, 15, 'Available'),
(4,  'Nova',                 1, 'SNA004', 30.00, 20, 'Available'),
(5,  'Coca-Cola',            2, 'BEV005', 45.00, 25, 'Available'),
(6,  'Sprite',               2, 'BEV006', 45.00, 18, 'Available'),
(7,  'Mountain Dew',         2, 'BEV007', 45.00, 12, 'Available'),
(8,  'C2 Apple',             2, 'BEV008', 20.00, 30, 'Available'),
(9,  'Sky Flakes',           1, 'SNA009', 28.00,  8, 'Available'),
(10, 'Oishi Prawn Crackers', 1, 'SNA010', 38.00,  5, 'Available')
ON DUPLICATE KEY UPDATE
	product_name = VALUES(product_name),
	category_id = VALUES(category_id),
	product_code = VALUES(product_code),
	price = VALUES(price),
	stock = VALUES(stock),
	status = VALUES(status);

-- Sample customers
INSERT INTO customers (id, full_name, phone, email) VALUES
(1, 'John Reyes', '09171234567', 'john.reyes@example.com'),
(2, 'Maria Santos', '09179876543', 'maria.santos@example.com')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), email = VALUES(email);