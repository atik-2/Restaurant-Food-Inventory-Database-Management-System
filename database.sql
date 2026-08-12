-- ==========================================================
-- Restaurant & Food Inventory Database Management System
-- MySQL script for XAMPP / phpMyAdmin
--
-- HOW TO RUN:
--   phpMyAdmin -> Import -> choose this file -> Go
--
-- 10 TABLES IN 3 EASY GROUPS + 1 LOGIN TABLE (remember this!):
--   STOCK SIDE : suppliers, ingredients, purchases
--   MENU SIDE  : menu_items, recipes
--   SALES SIDE : customers, employees, orders, order_items
--   LOGIN      : users
-- ==========================================================

DROP DATABASE IF EXISTS restaurant_db;
CREATE DATABASE restaurant_db;
USE restaurant_db;

-- ==========================================================
-- PART 1: CREATE TABLES  (Primary Key + Foreign Key)
-- ==========================================================

-- ---------- STOCK SIDE ----------

CREATE TABLE suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    phone       VARCHAR(20)  NOT NULL UNIQUE,
    email       VARCHAR(100) UNIQUE,
    address     VARCHAR(150)
);

CREATE TABLE ingredients (
    ingredient_id INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL UNIQUE,
    unit          VARCHAR(10)  NOT NULL,             -- kg, litre, pcs
    quantity      DECIMAL(10,2) NOT NULL DEFAULT 0,
    reorder_level DECIMAL(10,2) NOT NULL DEFAULT 5,  -- alert line
    unit_price    DECIMAL(10,2) NOT NULL,
    supplier_id   INT,
    CHECK (quantity >= 0),
    CHECK (unit_price >= 0),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id)
        ON DELETE SET NULL
);

CREATE TABLE purchases (
    purchase_id   INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id   INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity      DECIMAL(10,2) NOT NULL,
    cost          DECIMAL(10,2) NOT NULL,
    purchase_date DATE NOT NULL,
    CHECK (quantity > 0),
    FOREIGN KEY (supplier_id)   REFERENCES suppliers(supplier_id),
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(ingredient_id)
);

-- ---------- MENU SIDE ----------

CREATE TABLE menu_items (
    menu_id      INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100) NOT NULL UNIQUE,
    category     VARCHAR(30)  NOT NULL,
    price        DECIMAL(10,2) NOT NULL,
    discount     DECIMAL(5,2)  NOT NULL DEFAULT 0,   -- discount in %
    is_available TINYINT(1)   NOT NULL DEFAULT 1,
    CHECK (price > 0),
    CHECK (discount >= 0 AND discount <= 100)
);

-- recipes = which ingredient is needed for which food (M:N table)
CREATE TABLE recipes (
    menu_id       INT NOT NULL,
    ingredient_id INT NOT NULL,
    qty_required  DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (menu_id, ingredient_id),            -- composite PK
    FOREIGN KEY (menu_id)       REFERENCES menu_items(menu_id)
        ON DELETE CASCADE,
    FOREIGN KEY (ingredient_id) REFERENCES ingredients(ingredient_id)
);

-- ---------- SALES SIDE ----------

CREATE TABLE customers (
    customer_id INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    phone       VARCHAR(20)  NOT NULL UNIQUE
);

CREATE TABLE employees (
    employee_id INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    role        VARCHAR(30)  NOT NULL,               -- Chef, Waiter, Manager
    phone       VARCHAR(20)  NOT NULL UNIQUE,
    salary      DECIMAL(10,2) NOT NULL,
    hire_date   DATE NOT NULL
);

CREATE TABLE orders (
    order_id     INT AUTO_INCREMENT PRIMARY KEY,
    customer_id  INT,                                -- NULL = walk-in customer
    employee_id  INT NOT NULL,
    order_date   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status       VARCHAR(20) NOT NULL DEFAULT 'Pending',
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    CHECK (status IN ('Pending','Served','Paid','Cancelled')),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id)
        ON DELETE SET NULL,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id)
);

CREATE TABLE order_items (
    order_item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id      INT NOT NULL,
    menu_id       INT NOT NULL,
    quantity      INT NOT NULL,
    price         DECIMAL(10,2) NOT NULL,            -- price AFTER discount
    CHECK (quantity > 0),
    FOREIGN KEY (order_id) REFERENCES orders(order_id)
        ON DELETE CASCADE,
    FOREIGN KEY (menu_id)  REFERENCES menu_items(menu_id)
);

-- ---------- LOGIN ----------

CREATE TABLE users (
    user_id     INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,               -- password_hash()
    role        VARCHAR(10)  NOT NULL,               -- admin / customer
    customer_id INT,                                 -- only for customer
    CHECK (role IN ('admin','customer')),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id)
        ON DELETE CASCADE
);
-- ==========================================================
-- PART 2: INSERT SAMPLE DATA
-- ==========================================================

INSERT INTO suppliers (name, phone, email, address) VALUES
('Fresh Farm Ltd',   '01711000001', 'fresh@farm.com',  'Savar, Dhaka'),
('Meat House BD',    '01711000002', 'sales@meat.com',  'Mirpur, Dhaka'),
('Daily Dairy',      '01711000003', 'info@dairy.com',  'Gazipur'),
('Spice Bazar',      '01711000004', NULL,              'Old Dhaka');

INSERT INTO ingredients (name, unit, quantity, reorder_level, unit_price, supplier_id) VALUES
('Rice',      'kg',    50.00, 20.00,  75.00, 1),
('Chicken',   'kg',    12.00, 15.00, 280.00, 2),
('Beef',      'kg',     8.00, 10.00, 750.00, 2),
('Potato',    'kg',    30.00, 10.00,  40.00, 1),
('Onion',     'kg',    25.00, 10.00,  60.00, 1),
('Oil',       'litre', 18.00,  8.00, 165.00, 4),
('Milk',      'litre',  5.00, 10.00,  90.00, 3),
('Cheese',    'kg',     3.00,  4.00, 850.00, 3),
('Flour',     'kg',    22.00, 10.00,  55.00, 1),
('Spice Mix', 'kg',     4.00,  3.00, 320.00, 4);

INSERT INTO purchases (supplier_id, ingredient_id, quantity, cost, purchase_date) VALUES
(1, 1, 50.00, 3750.00, '2026-07-01'),
(2, 2, 20.00, 5600.00, '2026-07-03'),
(2, 3, 15.00,11250.00, '2026-07-03'),
(1, 4, 30.00, 1200.00, '2026-07-05'),
(3, 7, 20.00, 1800.00, '2026-07-06'),
(4, 6, 25.00, 4125.00, '2026-07-10'),
(1, 5, 25.00, 1500.00, '2026-07-12'),
(3, 8, 10.00, 8500.00, '2026-07-15');

-- price = normal price, discount = % off
INSERT INTO menu_items (name, category, price, discount, is_available) VALUES
('Chicken Biryani', 'Main',    280.00, 10.00, 1),   -- 10% off -> 252.00
('Beef Curry',      'Main',    350.00,  0.00, 1),
('Plain Rice',      'Main',     60.00,  0.00, 1),
('French Fries',    'Snacks',  120.00,  0.00, 1),
('Cheese Burger',   'Snacks',  250.00, 20.00, 1),   -- 20% off -> 200.00
('Milk Tea',        'Drinks',   40.00,  0.00, 1),
('Cold Coffee',     'Drinks',  110.00,  0.00, 0);

INSERT INTO recipes (menu_id, ingredient_id, qty_required) VALUES
(1, 1, 0.25), (1, 2, 0.30), (1, 6, 0.05), (1, 10, 0.02),  -- Biryani
(2, 3, 0.30), (2, 5, 0.10), (2, 6, 0.05), (2, 10, 0.02),  -- Beef Curry
(3, 1, 0.20),                                              -- Plain Rice
(4, 4, 0.30), (4, 6, 0.08),                                -- Fries
(5, 9, 0.15), (5, 2, 0.15), (5, 8, 0.05),                  -- Burger
(6, 7, 0.15);                                              -- Milk Tea

INSERT INTO customers (name, phone) VALUES
('Rahim Uddin',  '01811000001'),
('Karim Ali',    '01811000002'),
('Nusrat Jahan', '01811000003'),
('Sabbir Ahmed', '01811000004');

INSERT INTO employees (name, role, phone, salary, hire_date) VALUES
('Nishat Islam',  'Manager', '01911000001', 45000.00, '2024-01-10'),
('Amir Hamja',    'Chef',    '01911000002', 35000.00, '2024-03-15'),
('Rakibul Hasan', 'Waiter',  '01911000003', 18000.00, '2025-01-05'),
('Sadik Khan',    'Waiter',  '01911000004', 18000.00, '2025-06-20'),
('Kawsar Arnob',  'Cashier', '01911000005', 22000.00, '2025-09-01');

INSERT INTO orders (customer_id, employee_id, order_date, status, total_amount) VALUES
(1,    3, '2026-08-01 13:20:00', 'Paid',      600.00),
(2,    3, '2026-08-01 19:45:00', 'Paid',      350.00),
(NULL, 4, '2026-08-02 12:10:00', 'Paid',      370.00),
(3,    4, '2026-08-02 20:05:00', 'Paid',      840.00),
(1,    5, '2026-08-03 14:30:00', 'Served',    280.00),
(4,    3, '2026-08-03 21:00:00', 'Cancelled',   0.00);

INSERT INTO order_items (order_id, menu_id, quantity, price) VALUES
(1, 1, 2, 280.00), (1, 6, 1,  40.00),        -- 600
(2, 2, 1, 350.00),                            -- 350
(3, 5, 1, 250.00), (3, 4, 1, 120.00),        -- 370
(4, 1, 3, 280.00),                            -- 840
(5, 1, 1, 280.00);                            -- 280

-- ---------- LOGIN ACCOUNTS ----------
-- Password plain text-e rakha hoy na. SHA2() diye hash kore rakha hoy.
-- admin  password = admin123
-- others password = 1234
INSERT INTO users (username, password, role, customer_id) VALUES
('admin',  SHA2('admin123', 256), 'admin',    NULL),
('rahim',  SHA2('1234', 256),     'customer', 1),
('karim',  SHA2('1234', 256),     'customer', 2),
('nusrat', SHA2('1234', 256),     'customer', 3);

-- ==========================================================
-- PART 3: SQL OPERATIONS (INSERT / UPDATE / DELETE / SELECT)
-- ==========================================================

-- Q1. Show all menu items (SELECT)
SELECT * FROM menu_items;

-- Q2. Only available Main dishes, cheapest first (WHERE + ORDER BY)
SELECT name, price
FROM menu_items
WHERE category = 'Main' AND is_available = 1
ORDER BY price ASC;

-- Q3. Add a new food item (INSERT)  -- admin panel-er "Add Food" form ei kaj kore
-- INSERT INTO menu_items (name, category, price, discount, is_available)
-- VALUES ('Vegetable Soup', 'Snacks', 90.00, 0, 1);

-- Q3b. DISCOUNT set kora (UPDATE) -- admin panel-er "Save" button
-- UPDATE menu_items SET price = 300.00, discount = 25.00 WHERE menu_id = 1;

-- Q3c. Discount baad diye customer je dam dey
SELECT name, price, discount,
       ROUND(price - (price * discount / 100), 2) AS final_price
FROM menu_items
WHERE is_available = 1
ORDER BY discount DESC;

-- Q4. Increase all Drinks price by 10% (UPDATE)
-- UPDATE menu_items
-- SET price = price * 1.10
-- WHERE category = 'Drinks';

-- Q5. Remove unavailable items (DELETE)
-- DELETE FROM menu_items
-- WHERE is_available = 0;
--
-- NOTE: Q3, Q4, Q5 are commented out so that importing this file always
-- gives the same clean data. Copy-paste them in the SQL tab to test.

-- ==========================================================
-- PART 4: JOINS
-- ==========================================================

-- Q6. INNER JOIN -> each ingredient with its supplier name
SELECT i.name AS ingredient, i.quantity, i.unit, s.name AS supplier
FROM ingredients i
INNER JOIN suppliers s ON i.supplier_id = s.supplier_id;

-- Q7. LEFT JOIN -> all customers, even those who never ordered
SELECT c.name, COUNT(o.order_id) AS total_orders
FROM customers c
LEFT JOIN orders o ON c.customer_id = o.customer_id
GROUP BY c.customer_id, c.name;

-- Q8. Full bill of one order (3 tables joined)
SELECT o.order_id, c.name AS customer, m.name AS item,
       oi.quantity, oi.price, (oi.quantity * oi.price) AS subtotal
FROM orders o
JOIN customers   c  ON o.customer_id = c.customer_id
JOIN order_items oi ON o.order_id    = oi.order_id
JOIN menu_items  m  ON oi.menu_id    = m.menu_id
WHERE o.order_id = 1;

-- ==========================================================
-- PART 5: REPORT QUERIES (GROUP BY / aggregate)
-- ==========================================================

-- Q9. LOW STOCK ALERT (main feature)
SELECT name, quantity, unit, reorder_level
FROM ingredients
WHERE quantity <= reorder_level;

-- Q10. Daily sales report
SELECT DATE(order_date) AS day,
       COUNT(*) AS total_orders,
       SUM(total_amount) AS total_sales
FROM orders
WHERE status = 'Paid'
GROUP BY DATE(order_date)
ORDER BY day;

-- Q11. Best selling food items
SELECT m.name, SUM(oi.quantity) AS sold
FROM order_items oi
JOIN menu_items m ON oi.menu_id = m.menu_id
GROUP BY m.menu_id, m.name
ORDER BY sold DESC;

-- Q12. Total purchase cost per supplier (GROUP BY + HAVING)
SELECT s.name, SUM(p.cost) AS total_cost
FROM suppliers s
JOIN purchases p ON s.supplier_id = p.supplier_id
GROUP BY s.supplier_id, s.name
HAVING SUM(p.cost) > 5000
ORDER BY total_cost DESC;

-- Q13. Which items sold more than the average price (SUBQUERY)
SELECT name, price
FROM menu_items
WHERE price > (SELECT AVG(price) FROM menu_items);

-- Q14. Employee wise sales
SELECT e.name, e.role, COUNT(o.order_id) AS orders_handled,
       IFNULL(SUM(o.total_amount), 0) AS sales
FROM employees e
LEFT JOIN orders o ON e.employee_id = o.employee_id
GROUP BY e.employee_id, e.name, e.role
ORDER BY sales DESC;

-- Q15. Ingredient stock value in store
SELECT name, quantity, unit_price,
       (quantity * unit_price) AS stock_value
FROM ingredients
ORDER BY stock_value DESC;

-- ==========================================================
-- PART 6: VIEWS  (saved query = easy report)
-- ==========================================================

CREATE VIEW v_low_stock AS
SELECT i.name, i.quantity, i.unit, i.reorder_level, s.name AS supplier
FROM ingredients i
LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id
WHERE i.quantity <= i.reorder_level;

CREATE VIEW v_daily_sales AS
SELECT DATE(order_date) AS day,
       COUNT(*) AS total_orders,
       SUM(total_amount) AS total_sales
FROM orders
WHERE status = 'Paid'
GROUP BY DATE(order_date);

-- use them like a normal table:
SELECT * FROM v_low_stock;
SELECT * FROM v_daily_sales;

-- ==========================================================
-- PART 7: TRIGGERS  (Automatic Stock Update)
-- ==========================================================
-- Purchase korle stock bare, order korle stock kome -- automatically.

DELIMITER $$

-- 1) New purchase  ->  stock INCREASES
CREATE TRIGGER trg_purchase_add_stock
AFTER INSERT ON purchases
FOR EACH ROW
BEGIN
    UPDATE ingredients
    SET quantity = quantity + NEW.quantity
    WHERE ingredient_id = NEW.ingredient_id;
END$$

-- 2) New order item -> ingredients DEDUCTED + bill total updated
CREATE TRIGGER trg_order_deduct_stock
AFTER INSERT ON order_items
FOR EACH ROW
BEGIN
    UPDATE ingredients i
    JOIN recipes r ON r.ingredient_id = i.ingredient_id
    SET i.quantity = i.quantity - (r.qty_required * NEW.quantity)
    WHERE r.menu_id = NEW.menu_id;

    UPDATE orders
    SET total_amount = total_amount + (NEW.price * NEW.quantity)
    WHERE order_id = NEW.order_id;
END$$

DELIMITER ;

-- ---------- TRIGGER DEMO (run these one by one) ----------
-- Before:
-- SELECT name, quantity FROM ingredients WHERE ingredient_id IN (1,2,6,10);
--
-- A customer orders 1 Chicken Biryani:
-- INSERT INTO orders (customer_id, employee_id, status) VALUES (2, 3, 'Pending');
-- INSERT INTO order_items (order_id, menu_id, quantity, price)
-- VALUES (LAST_INSERT_ID(), 1, 1, 280.00);
--
-- After: rice -0.25, chicken -0.30, oil -0.05, spice -0.02, bill = 280
-- SELECT name, quantity FROM ingredients WHERE ingredient_id IN (1,2,6,10);
-- SELECT * FROM orders ORDER BY order_id DESC LIMIT 1;

-- ==========================================================
-- END OF FILE
-- ==========================================================
