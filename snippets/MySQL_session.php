<?php

/**
 * AutoBaza - MySQL сесія користувача
 */
add_action('init', function() {
    if (is_user_logged_in()) {
        global $wpdb;
        $display_name = wp_get_current_user()->display_name;
        $wpdb->query(
            $wpdb->prepare(
                "SET @current_user_name = %s",
                $display_name
            )
        );
    }
});
