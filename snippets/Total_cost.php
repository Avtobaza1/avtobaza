<?php

/**
 * AutoBaza - Автопідрахунок вартості наряду
 */
add_filter('wpda_before_insert_row', function($data, $table) {
    global $wpdb;

    $current_user = wp_get_current_user();
    $user_name    = $current_user->display_name;

    if ($table === $wpdb->prefix . 'ab_maintenance_orders') {
        $data['created_by'] = $user_name;
    }

    if ($table === $wpdb->prefix . 'ab_odometer_logs') {
        $data['created_by'] = $user_name;
    }

    return $data;
}, 10, 2);
