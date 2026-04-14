<?php

/**
 * AutoBaza - Menu by Role
 */
add_filter('wp_nav_menu_items', function($items, $args) {
    if (!is_user_logged_in()) return $items;
    $user  = wp_get_current_user();
    $roles = (array)$user->roles;
    if (in_array('administrator', $roles)) return $items;

    $show = [];
    if (in_array('mechanic', $roles)) {
        $show = ['Наряди на ТО', 'Нагадування'];
    } elseif (in_array('dispatcher', $roles)) {
    $show = ['Транспорт','Запчастини','Нагадування','Довідники'];
    } elseif (in_array('manager', $roles)) {
        $show = ['Панель керування','Транспорт','Наряди на ТО','Запчастини','Нагадування','Звіти','Довідники'];
    }
    if (empty($show)) return $items;

    $doc = new DOMDocument();
    @$doc->loadHTML(mb_convert_encoding('<ul>'.$items.'</ul>', 'HTML-ENTITIES', 'UTF-8'));
    $xpath = new DOMXPath($doc);
    foreach ($xpath->query('//ul/li') as $li) {
        $a = $xpath->query('.//a', $li)->item(0);
        if (!$a) continue;
        $text = trim($a->textContent);
        $found = false;
        foreach ($show as $allowed) {
            if (strpos($text, $allowed) !== false) { $found = true; break; }
        }
        if (!$found) $li->parentNode->removeChild($li);
    }
    $ul = $doc->getElementsByTagName('ul')->item(0);
    $result = '';
    foreach ($ul->childNodes as $child) $result .= $doc->saveHTML($child);
    return $result;
}, 10, 2);

add_filter('wp_nav_menu_items', function($items, $args) {
    if (!is_user_logged_in()) return $items;
    $user   = wp_get_current_user();
    $roles  = (array)$user->roles;
    $labels = [
        'administrator' => 'Адмін',
        'mechanic'      => 'Механік',
        'dispatcher'    => 'Обліковець',
        'manager'       => 'Менеджер',
    ];
    $label = 'Користувач';
    foreach ($labels as $role => $name) {
        if (in_array($role, $roles)) { $label = $name; break; }
    }
    $items .= '<li style="display:flex;align-items:center;gap:10px;margin-left:15px;">
        <span style="font-size:13px;color:#555;">
            '.esc_html($user->display_name).'
            <small style="color:#888;">('.$label.')</small>
        </span>
        <a href="'.wp_logout_url(wp_login_url()).'"
           style="background:#1a3a5c;color:#fff;padding:5px 14px;
                  border-radius:6px;text-decoration:none;font-size:13px;">
            Вийти
        </a>
    </li>';
    return $items;
}, 30, 2);
