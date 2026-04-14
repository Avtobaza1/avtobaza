<?php

/**
 * AutoBaza - Редірект після входу залежно від ролі
 */
// 1. ПЕРЕКЛАД ПОВІДОМЛЕННЯ (Пробуємо два можливі фільтри плагіна)
add_filter( 'wpda_app_no_access_message', 'wpda_custom_msg', 99 );
add_filter( 'wpda_app_access_denied_message', 'wpda_custom_msg', 99 );
function wpda_custom_msg( $message ) {
    return 'Вибачте, але у вас немає прав для перегляду цієї сторінки.';
}

// 2. РЕДІРЕКТ ДЛЯ ЗАХИСТУ СТОРІНОК (Template Redirect)
add_action( 'template_redirect', function() {
    if ( !is_user_logged_in() ) return;

    $user = wp_get_current_user();
    $roles = (array) $user->roles;

    // ЗАХИСТ ДЛЯ МЕНЕДЖЕРА
    if ( in_array('manager', $roles) ) {
        $allowed = array('dashboard', 'transport', 'orders', 'parts', 'notifications', 'reports', 'references');
        $current_post = get_post();
        $slug = $current_post ? $current_post->post_name : '';

        if ( is_page() && !in_array($slug, $allowed) ) {
            wp_redirect( home_url('/') );
            exit;
        }
    }
}, 5);

// 3. РЕДІРЕКТ ПІСЛЯ ВХОДУ (Login Redirect) — щоб кожен бачив свою сторінку
add_filter( 'login_redirect', function( $redirect_to, $request, $user ) {
    if ( isset( $user->roles ) && is_array( $user->roles ) ) {
        if ( in_array( 'manager', $user->roles ) ) {
            return home_url( '/dashboard' );
        }
        if ( in_array( 'dispatcher', $user->roles ) ) {
            return home_url( '/transport' );
        }
        if ( in_array( 'mechanic', $user->roles ) ) {
            return home_url( '/orders' );
        }
        if ( in_array( 'administrator', $user->roles ) ) {
            return admin_url();
        }
    }
    return $redirect_to;
}, 10, 3 );
