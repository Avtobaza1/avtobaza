<?php

/**
 * AutoBaza - Live перерахунок total_cost
 */
add_action('template_redirect', function() {
    global $wpdb;
    $p = $wpdb->prefix . 'ab_';

    // Перераховує total_cost для всіх НЕзакритих нарядів
    // при кожному завантаженні сторінки
    $wpdb->query("
        UPDATE {$p}maintenance_orders mo
        SET mo.total_cost = (
            SELECT COALESCE(SUM(mt.labor_cost), 0)
            FROM {$p}maintenance_tasks mt
            WHERE mt.order_id = mo.id
        ) + (
            SELECT COALESCE(SUM(pu.qty * pu.unit_price_at_use), 0)
            FROM {$p}part_usage pu
            JOIN {$p}maintenance_tasks mt ON pu.task_id = mt.id
            WHERE mt.order_id = mo.id
        )
        WHERE mo.status IN ('OPEN', 'IN_PROGRESS')
    ");
});
