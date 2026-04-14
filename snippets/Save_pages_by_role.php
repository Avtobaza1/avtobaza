<?php

/**
 * AutoBaza - Захист сторінок за роллю
 */
add_action('template_redirect', function() {
    if (!is_user_logged_in()) {
        $protected = ['dashboard','transport','orders','parts','notifications','reports','references'];
        if (is_page($protected)) {
            wp_redirect(wp_login_url(get_permalink()));
            exit;
        }
        return;
    }

    $user  = wp_get_current_user();
    $roles = (array)$user->roles;

    if (in_array('administrator', $roles)) return;

    // Механік → тільки orders і notifications
    if (in_array('mechanic', $roles)) {
        $allowed = ['orders','notifications'];
        $slug = get_post_field('post_name', get_the_ID());
        if ((is_page() || is_front_page()) && !in_array($slug, $allowed)) {
            wp_redirect(home_url('/orders')); exit;
        }
    }

    // Обліковець
    if (in_array('dispatcher', $roles)) {
    if (is_front_page() || is_home() || is_page('dashboard')) {
        wp_redirect(home_url('/transport')); exit;
    }
    $allowed = ['transport','parts','notifications','references'];
        $slug = get_post_field('post_name', get_the_ID());
        if (is_page() && !in_array($slug, $allowed)) {
            wp_redirect(home_url('/dashboard')); exit;
        }
    }

    // Менеджер
    if (in_array('manager', $roles)) {
        $allowed = ['dashboard','transport','orders','parts','notifications','reports','references'];
        $slug = get_post_field('post_name', get_the_ID());
        if (is_page() && !in_array($slug, $allowed)) {
            wp_redirect(home_url('/dashboard')); exit;
        }
    }
});
