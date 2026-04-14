<?php

/**
 * AutoBaza - Приховати Admin Bar
 */
// Приховуємо адмін-панель для всіх крім адміністратора
add_action('after_setup_theme', function() {
    if (!current_user_can('administrator')) {
        show_admin_bar(false);
    }
});

// Забороняємо вхід в адмін-панель для механіка і обліковця
add_action('admin_init', function() {
    if (is_admin() && !current_user_can('administrator') && 
        !(defined('DOING_AJAX') && DOING_AJAX)) {
        wp_redirect(home_url('/dashboard'));
        exit;
    }
});
// Переклад повідомлення про відсутність доступу
add_filter('the_content', function($content) {
    return str_replace(
        'Sorry, but you do not have permission to view this content.',
        '<div style="background:#fff3cd;border-left:4px solid #ffc107;padding:15px 20px;border-radius:4px;color:#856404;">
            ⚠️ У вас немає доступу до цього розділу. Зверніться до адміністратора.
        </div>',
        $content
    );
});
add_filter('the_content', function($content) {
    return str_replace(
        'Sorry, but you do not have permission to view this content.',
        '<div style="background:#fff3cd;border-left:4px solid #ffc107;padding:15px 20px;border-radius:4px;color:#856404;">⚠️ У вас немає доступу до цього розділу.</div>',
        $content
    );
});
