<?php

/**
 * Set utf8mb4
 */
add_action('init', function() {
    global $wpdb;
    $wpdb->query("SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    $wpdb->query("SET collation_connection = 'utf8mb4_0900_ai_ci'");
    $wpdb->query("SET collation_database = 'utf8mb4_0900_ai_ci'");
}, 1);

// Також перехоплюємо підключення WP Data Access
add_filter('wpda_db_connection_options', function($options) {
    $options['collation'] = 'utf8mb4_0900_ai_ci';
    return $options;
});
