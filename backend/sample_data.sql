-- Sample Data for Testing POS System
-- Insert categories first
INSERT INTO categories (id, category_name) VALUES 
(1, 'Snacks'),
(2, 'Beverage');

-- Insert sample products
INSERT INTO products (product_name, category_id, price, stock, status) VALUES 
('Piattos', 1, 40.00, 10, 'Available'),
('Chilsung', 2, 25.00, 0, 'Out of Stock');

-- Additional sample products for convenience store
INSERT INTO products (product_name, category_id, price, stock, status) VALUES 
('Chippy', 1, 35.00, 15, 'Available'),
('Nova', 1, 30.00, 20, 'Available'),
('Coca-Cola', 2, 45.00, 25, 'Available'),
('Sprite', 2, 45.00, 18, 'Available'),
('Mountain Dew', 2, 45.00, 12, 'Available'),
('C2 Apple', 2, 20.00, 30, 'Available'),
('Sky Flakes', 1, 28.00, 8, 'Available'),
('Oishi Prawn Crackers', 1, 38.00, 5, 'Available');

-- Sample customers
INSERT INTO customers (id, full_name, phone, email) VALUES
(1, 'John Reyes', '09171234567', 'john.reyes@example.com'),
(2, 'Maria Santos', '09179876543', 'maria.santos@example.com')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), email = VALUES(email);