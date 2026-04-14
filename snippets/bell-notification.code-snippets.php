<?php

/**
 * Bell Notification
 */
add_filter('wp_nav_menu_items', function($items, $args) {
    if (!is_user_logged_in()) return $items;
    
    global $wpdb;
    $count = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ab_notifications 
         WHERE status = 'NEW'"
    );
    
    if ($count > 0) {
        $items = str_replace(
            '>Нагадування<',
            '>Нагадування <span style="background:#dc3545;color:#fff;
             border-radius:50%;padding:1px 6px;font-size:11px;
             font-weight:700;">' . $count . '</span><',
            $items
        );
    }
    return $items;
}, 10, 2);
