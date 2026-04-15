-- =============================================
-- AutoBaza — всі тригери
-- Виконувати з DELIMITER // в phpMyAdmin
-- Або через: mysql -u root -p db_name < triggers.sql
-- =============================================

-- Очистити старі
DROP TRIGGER IF EXISTS ab_set_task_plan;
DROP TRIGGER IF EXISTS ab_update_order_cost_on_task;
DROP TRIGGER IF EXISTS ab_update_order_cost_on_task_update;
DROP TRIGGER IF EXISTS wp_ab_price;
DROP TRIGGER IF EXISTS ab_after_insert_part_usage;
DROP TRIGGER IF EXISTS ab_fill_price_on_part_usage;
DROP TRIGGER IF EXISTS ab_update_order_cost_after_task_insert;
DROP TRIGGER IF EXISTS ab_update_order_cost_after_task_update;
DROP TRIGGER IF EXISTS ab_update_order_cost_on_task_delete;
DROP TRIGGER IF EXISTS ab_update_order_cost_after_part_insert;
DROP TRIGGER IF EXISTS ab_update_order_cost_after_part_update;
DROP TRIGGER IF EXISTS ab_adjust_stock_on_usage_update;
DROP TRIGGER IF EXISTS ab_restore_stock_on_usage_delete;
DROP TRIGGER IF EXISTS ab_update_order_cost_after_part_delete;
DROP TRIGGER IF EXISTS ab_decrease_stock_on_part_usage_insert;

DELIMITER //

-- 1. Заповнити plan_id при створенні роботи
CREATE TRIGGER ab_set_task_plan
BEFORE INSERT ON wp_ab_maintenance_tasks
FOR EACH ROW
BEGIN
    SET NEW.plan_id = (
        SELECT plan_id FROM wp_ab_maintenance_orders
        WHERE id = NEW.order_id
    );
END//

-- 2. Заповнити ціну запчастини автоматично
CREATE TRIGGER ab_fill_price_on_part_usage
BEFORE INSERT ON wp_ab_part_usage
FOR EACH ROW
BEGIN
    IF NEW.unit_price_at_use = 0 OR NEW.unit_price_at_use IS NULL THEN
        SET NEW.unit_price_at_use = (
            SELECT COALESCE(unit_price, 0)
            FROM wp_ab_parts
            WHERE id = NEW.part_id
        );
    END IF;
END//

-- 3. Оновити total_cost при додаванні роботи
CREATE TRIGGER ab_update_order_cost_after_task_insert
AFTER INSERT ON wp_ab_maintenance_tasks
FOR EACH ROW
BEGIN
    UPDATE wp_ab_maintenance_orders mo
    SET total_cost = (
        SELECT COALESCE(SUM(mt.labor_cost), 0)
        FROM wp_ab_maintenance_tasks mt
        WHERE mt.order_id = mo.id
    ) + (
        SELECT COALESCE(SUM(pu.qty * pu.unit_price_at_use), 0)
        FROM wp_ab_part_usage pu
        JOIN wp_ab_maintenance_tasks mt ON pu.task_id = mt.id
        WHERE mt.order_id = mo.id
    )
    WHERE mo.id = NEW.order_id;
END//

-- 4. Оновити total_cost при зміні роботи
CREATE TRIGGER ab_update_order_cost_after_task_update
AFTER UPDATE ON wp_ab_maintenance_tasks
FOR EACH ROW
BEGIN
    UPDATE wp_ab_maintenance_orders mo
    SET total_cost = (
        SELECT COALESCE(SUM(mt.labor_cost), 0)
        FROM wp_ab_maintenance_tasks mt
        WHERE mt.order_id = mo.id
    ) + (
        SELECT COALESCE(SUM(pu.qty * pu.unit_price_at_use), 0)
        FROM wp_ab_part_usage pu
        JOIN wp_ab_maintenance_tasks mt ON pu.task_id = mt.id
        WHERE mt.order_id = mo.id
    )
    WHERE mo.id = NEW.order_id;
END//

-- 5. Оновити total_cost при видаленні роботи
CREATE TRIGGER ab_update_order_cost_on_task_delete
AFTER DELETE ON wp_ab_maintenance_tasks
FOR EACH ROW
BEGIN
    UPDATE wp_ab_maintenance_orders mo
    SET total_cost = (
        SELECT COALESCE(SUM(mt.labor_cost), 0)
        FROM wp_ab_maintenance_tasks mt
        WHERE mt.order_id = mo.id
    ) + (
        SELECT COALESCE(SUM(pu.qty * pu.unit_price_at_use), 0)
        FROM wp_ab_part_usage pu
        JOIN wp_ab_maintenance_tasks mt ON pu.task_id = mt.id
        WHERE mt.order_id = mo.id
    )
    WHERE mo.id = OLD.order_id;
END//

-- 6. Оновити total_cost при додаванні запчастини
CREATE TRIGGER ab_update_order_cost_after_part_insert
AFTER INSERT ON wp_ab_part_usage
FOR EACH ROW
BEGIN
    UPDATE wp_ab_maintenance_orders mo
    SET total_cost = (
        SELECT COALESCE(SUM(mt.labor_cost), 0)
        FROM wp_ab_maintenance_tasks mt
        WHERE mt.order_id = mo.id
    ) + (
        SELECT COALESCE(SUM(pu.qty * pu.unit_price_at_use), 0)
        FROM wp_ab_part_usage pu
        JOIN wp_ab_maintenance_tasks mt ON pu.task_id = mt.id
        WHERE mt.order_id = mo.id
    )
    WHERE mo.id = (
        SELECT order_id FROM wp_ab_maintenance_tasks
        WHERE id = NEW.task_id
    );
END//

-- 7. Зменшити склад при списанні запчастини
CREATE TRIGGER ab_decrease_stock_on_part_usage_insert
AFTER INSERT ON wp_ab_part_usage
FOR EACH ROW
BEGIN
    UPDATE wp_ab_parts
    SET stock_qty = stock_qty - NEW.qty
    WHERE id = NEW.part_id;
END//

-- 8. Коригувати склад при зміні кількості
CREATE TRIGGER ab_adjust_stock_on_usage_update
AFTER UPDATE ON wp_ab_part_usage
FOR EACH ROW
BEGIN
    UPDATE wp_ab_parts
    SET stock_qty = stock_qty + OLD.qty - NEW.qty
    WHERE id = NEW.part_id;
END//

-- 9. Оновити total_cost при зміні запчастини
CREATE TRIGGER ab_update_order_cost_after_part_update
AFTER UPDATE ON wp_ab_part_usage
FOR EACH ROW
BEGIN
    DECLARE v_order_id BIGINT UNSIGNED;
    SELECT order_id INTO v_order_id
    FROM wp_ab_maintenance_tasks
    WHERE id = NEW.task_id;

    IF v_order_id IS NOT NULL THEN
        UPDATE wp_ab_maintenance_orders mo
        SET total_cost = (
            SELECT COALESCE(SUM(mt.labor_cost), 0)
            FROM wp_ab_maintenance_tasks mt
            WHERE mt.order_id = v_order_id
        ) + (
            SELECT COALESCE(SUM(pu.qty * pu.unit_price_at_use), 0)
            FROM wp_ab_part_usage pu
            JOIN wp_ab_maintenance_tasks mt ON pu.task_id = mt.id
            WHERE mt.order_id = v_order_id
        )
        WHERE mo.id = v_order_id;
    END IF;
END//

-- 10. Повернути склад при видаленні запчастини з наряду
CREATE TRIGGER ab_restore_stock_on_usage_delete
AFTER DELETE ON wp_ab_part_usage
FOR EACH ROW
BEGIN
    UPDATE wp_ab_parts
    SET stock_qty = stock_qty + OLD.qty
    WHERE id = OLD.part_id;
END//

-- 11. Оновити total_cost при видаленні запчастини
CREATE TRIGGER ab_update_order_cost_after_part_delete
AFTER DELETE ON wp_ab_part_usage
FOR EACH ROW
BEGIN
    UPDATE wp_ab_maintenance_orders mo
    SET total_cost = (
        SELECT COALESCE(SUM(mt.labor_cost), 0)
        FROM wp_ab_maintenance_tasks mt
        WHERE mt.order_id = mo.id
    ) + (
        SELECT COALESCE(SUM(pu.qty * pu.unit_price_at_use), 0)
        FROM wp_ab_part_usage pu
        JOIN wp_ab_maintenance_tasks mt ON pu.task_id = mt.id
        WHERE mt.order_id = mo.id
    )
    WHERE mo.id = (
        SELECT order_id FROM wp_ab_maintenance_tasks
        WHERE id = OLD.task_id
    );
END//

DELIMITER ;
