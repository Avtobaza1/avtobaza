<?php

/**
 * AutoBaza - Відновлення складу при видаленні наряду
 */
add_filter('wpda_before_delete_row', function($data, $table) {
    global $wpdb;
    $p = $wpdb->prefix . 'ab_';

    // Спрацьовує тільки при видаленні наряду
    if ($table !== $p . 'maintenance_orders') {
        return $data;
    }

    $order_id = isset($data['id']) ? (int)$data['id'] : 0;
    if (!$order_id) return $data;

    // Повертаємо запчастини на склад ПЕРЕД видаленням
    $wpdb->query($wpdb->prepare("
        UPDATE {$p}parts p
        JOIN wp_ab_part_usage pu ON pu.part_id = p.id
        JOIN {$p}maintenance_tasks mt ON mt.id = pu.task_id
        SET p.stock_qty = p.stock_qty + pu.qty
        WHERE mt.order_id = %d
    ", $order_id));

    return $data;
}, 10, 2);
