-- Additional database triggers and procedures for automatic profit tracking

-- Create admin_revenue table if it doesn't exist
CREATE TABLE IF NOT EXISTS admin_revenue (
    revenue_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    order_detail_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    farmer_price DECIMAL(10,2) NOT NULL,
    admin_price DECIMAL(10,2) NOT NULL,
    profit_amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(order_id),
    FOREIGN KEY (order_detail_id) REFERENCES order_details(order_detail_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id),
    UNIQUE KEY unique_order_detail (order_detail_id)
);

-- 1. Trigger to automatically populate admin_revenue when order status changes to 'Completed'
DELIMITER $$

CREATE TRIGGER after_order_complete
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
    -- Only run if status changed to 'Completed'
    IF NEW.status = 'Completed' AND OLD.status != 'Completed' THEN
        -- Insert into admin_revenue for each order detail
        INSERT INTO admin_revenue (order_id, order_detail_id, product_id, quantity, farmer_price, admin_price, profit_amount)
        SELECT 
            NEW.order_id,
            od.order_detail_id,
            od.product_id,
            od.quantity,
            od.farmer_price,
            od.admin_price,
            (od.admin_price - od.farmer_price) * od.quantity
        FROM order_details od
        WHERE od.order_id = NEW.order_id
        AND NOT EXISTS (
            SELECT 1 FROM admin_revenue ar 
            WHERE ar.order_detail_id = od.order_detail_id
        );
    END IF;
END$$

DELIMITER ;

-- 2. Stored procedure to manually populate admin_revenue for existing completed orders
DELIMITER $$

CREATE PROCEDURE populate_admin_revenue()
BEGIN
    -- Populate revenue for all completed orders that aren't already tracked
    INSERT INTO admin_revenue (order_id, order_detail_id, product_id, quantity, farmer_price, admin_price, profit_amount)
    SELECT 
        o.order_id,
        od.order_detail_id,
        od.product_id,
        od.quantity,
        od.farmer_price,
        od.admin_price,
        (od.admin_price - od.farmer_price) * od.quantity as profit_amount
    FROM orders o
    INNER JOIN order_details od ON o.order_id = od.order_id
    WHERE o.status = 'Completed'
    AND od.profit_per_unit > 0
    AND NOT EXISTS (
        SELECT 1 FROM admin_revenue ar 
        WHERE ar.order_detail_id = od.order_detail_id
    );
    
    SELECT ROW_COUNT() as records_inserted;
END$$

DELIMITER ;

-- 3. Run the procedure to populate existing data
CALL populate_admin_revenue();

-- 4. Create a view for quick profit margin analysis
CREATE OR REPLACE VIEW product_profit_analysis AS
SELECT 
    p.product_id,
    p.product_name,
    p.category,
    p.price as farmer_price,
    p.admin_price,
    (p.admin_price - p.price) as profit_per_unit,
    ((p.admin_price - p.price) / p.price * 100) as profit_margin_percent,
    p.quantity as current_stock,
    p.total_stock,
    p.sold_quantity,
    (p.admin_price - p.price) * p.sold_quantity as total_profit_earned,
    (p.admin_price - p.price) * p.quantity as potential_profit_remaining,
    f.name as farmer_name,
    p.approval_status
FROM products p
INNER JOIN farmer f ON p.farmer_id = f.farmer_id
WHERE p.approval_status = 'approved' 
AND p.admin_price IS NOT NULL;

-- 5. Index optimization for revenue queries
CREATE INDEX idx_revenue_date ON admin_revenue(created_at);
CREATE INDEX idx_revenue_product ON admin_revenue(product_id, created_at);
CREATE INDEX idx_revenue_order ON admin_revenue(order_id, created_at);

-- 6. Function to calculate potential profit for a product
DELIMITER $$

CREATE FUNCTION calculate_potential_profit(product_id_param INT)
RETURNS DECIMAL(10,2)
DETERMINISTIC
BEGIN
    DECLARE potential_profit DECIMAL(10,2);
    
    SELECT (admin_price - price) * quantity INTO potential_profit
    FROM products
    WHERE product_id = product_id_param
    AND approval_status = 'approved'
    AND admin_price IS NOT NULL;
    
    RETURN IFNULL(potential_profit, 0);
END$$

DELIMITER ;

-- 7. Admin dashboard summary view
CREATE OR REPLACE VIEW admin_dashboard_summary AS
SELECT 
    (SELECT COUNT(*) FROM products WHERE approval_status = 'pending') as pending_products,
    (SELECT COUNT(*) FROM products WHERE approval_status = 'approved') as approved_products,
    (SELECT COUNT(*) FROM products WHERE quantity < 10 AND approval_status = 'approved') as low_stock_products,
    (SELECT COUNT(*) FROM orders WHERE status = 'Pending') as pending_orders,
    (SELECT COUNT(*) FROM orders WHERE status = 'Completed') as completed_orders,
    (SELECT IFNULL(SUM(profit_amount), 0) FROM admin_revenue) as total_profit,
    (SELECT IFNULL(SUM(profit_amount), 0) FROM admin_revenue WHERE DATE(created_at) = CURDATE()) as today_profit,
    (SELECT IFNULL(SUM(profit_amount), 0) FROM admin_revenue WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as month_profit,
    (SELECT IFNULL(SUM((admin_price - price) * quantity), 0) FROM products WHERE approval_status = 'approved' AND admin_price IS NOT NULL) as potential_profit;

-- Note: After running this script:
-- 1. The trigger will automatically populate admin_revenue when orders are marked as 'Completed'
-- 2. Use the views for quick dashboard statistics
-- 3. The stored procedure can be called manually if needed: CALL populate_admin_revenue();
