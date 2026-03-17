-- Sample Data for Testing POS System
-- Insert categories first (code_prefix is the 3-letter DFA product-code prefix per category)
INSERT INTO categories (id, category_name, code_prefix) VALUES 
(1, 'Snacks',   'SNA'),
(2, 'Beverage', 'BEV')
ON DUPLICATE KEY UPDATE code_prefix = VALUES(code_prefix);

-- Sample customers
INSERT INTO customers (id, full_name, phone, email) VALUES
(1, 'John Reyes', '09171234567', 'john.reyes@example.com'),
(2, 'Maria Santos', '09179876543', 'maria.santos@example.com')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), email = VALUES(email);