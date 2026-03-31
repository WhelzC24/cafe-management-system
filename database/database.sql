CREATE DATABASE IF NOT EXISTS web_system;
USE web_system;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    role VARCHAR(20) NOT NULL DEFAULT 'user',
    date_registered TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    category VARCHAR(60) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(500) DEFAULT NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(120) NOT NULL,
    customer_email VARCHAR(120) DEFAULT NULL,
    customer_phone VARCHAR(40) NOT NULL,
    notes TEXT DEFAULT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    processed_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_processed_by FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT DEFAULT NULL,
    product_name VARCHAR(120) NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    line_total DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
);

-- Admin account (password: admin123)
INSERT INTO users (fullname, email, username, password, role)
VALUES ('Admin User', 'admin@cozycornercafe.com', 'admin',
        '$2y$10$kOcNb36.hT3GTEtVPpC5T.7wLJthB/MRfUMg9Q/0hxp0BswcR8lrm', 'admin');

INSERT INTO products (name, category, description, price, image_url) VALUES
('Espresso', 'Coffee', 'Rich and bold single shot of espresso with a velvety crema on top', 2.50, 'https://images.unsplash.com/photo-1510591509098-f4fdc6d0ff04?w=600&q=80'),
('Americano', 'Coffee', 'Espresso with hot water for a smooth, full-bodied taste', 3.00, 'https://images.unsplash.com/photo-1521302080334-4bebac2763a6?w=600&q=80'),
('Cappuccino', 'Coffee', 'Espresso with steamed milk and a thick layer of velvety foam', 4.00, 'https://images.unsplash.com/photo-1534778101976-62847782c213?w=600&q=80'),
('Latte', 'Coffee', 'Creamy espresso drink with silky steamed milk and light foam art', 4.50, 'https://images.unsplash.com/photo-1561882468-9110d70d2a78?w=600&q=80'),
('Flat White', 'Coffee', 'Velvety microfoam milk with a double ristretto espresso base', 4.75, 'https://images.unsplash.com/photo-1577590835286-1cdd23c4e6b8?w=600&q=80'),
('Croissant', 'Pastries', 'Buttery, flaky French pastry baked fresh every morning', 3.50, 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?w=600&q=80'),
('Chocolate Donut', 'Pastries', 'Soft, pillowy donut dipped in rich chocolate glaze', 2.75, 'https://images.unsplash.com/photo-1551106652-a5bcf4b29ab6?w=600&q=80'),
('Blueberry Muffin', 'Pastries', 'Moist muffin loaded with fresh blueberries and a crumbly sugar top', 3.25, 'https://images.unsplash.com/photo-1607958996333-41aef7caefaa?w=600&q=80'),
('Cinnamon Roll', 'Pastries', 'Warm cinnamon roll with brown sugar filling and cream cheese frosting', 4.00, 'https://images.unsplash.com/photo-1609771776991-49355b2a6885?w=600&q=80'),
('Cheesecake Slice', 'Pastries', 'Creamy New York-style cheesecake with a buttery graham cracker crust', 5.50, 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=600&q=80'),
('Iced Coffee', 'Cold Drinks', 'Chilled cold-brew coffee poured over ice with your choice of milk', 3.50, 'https://images.unsplash.com/photo-1461023058943-07fcbe16d735?w=600&q=80'),
('Matcha Latte', 'Cold Drinks', 'Premium ceremonial-grade matcha whisked with oat milk over ice', 5.00, 'https://images.unsplash.com/photo-1515823064-d6e0c04616a7?w=600&q=80'),
('Fresh Orange Juice', 'Cold Drinks', 'Freshly squeezed oranges for a bright, vitamin-packed morning boost', 4.50, 'https://images.unsplash.com/photo-1546173159-315724a31696?w=600&q=80'),
('Hot Chocolate', 'Hot Drinks', 'Belgian dark chocolate melted into steamed milk, topped with whipped cream', 3.75, 'https://images.unsplash.com/photo-1542990253-a781e04c0082?w=600&q=80'),
('Avocado Toast', 'Food', 'Smashed avocado on sourdough with cherry tomatoes, chili flakes and sea salt', 7.50, 'https://images.unsplash.com/photo-1541519227354-08fa5d50c820?w=600&q=80'),
('Club Sandwich', 'Food', 'Triple-decker with grilled chicken, bacon, lettuce, tomato and mayo on toasted bread', 9.00, 'https://images.unsplash.com/photo-1567234669003-dce7a7a88821?w=600&q=80');
