<?php

/**
 * AutoBaza - Шорткод KPI блок
 */
add_shortcode('ab_kpi_block', function() {
    global $wpdb;
    $p = $wpdb->prefix . 'ab_';
    
    $active    = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$p}vehicles WHERE status='ACTIVE'");
    $service   = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$p}vehicles WHERE status='IN_SERVICE'");
    $orders    = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$p}maintenance_orders WHERE status='OPEN'");
    $notif     = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$p}notifications WHERE status='NEW'");
    $month_cost = (float) $wpdb->get_var(
        "SELECT COALESCE(SUM(total_cost),0) 
         FROM {$p}maintenance_orders 
         WHERE status='CLOSED' 
           AND MONTH(closed_dt)=MONTH(CURDATE()) 
           AND YEAR(closed_dt)=YEAR(CURDATE())");
    
    ob_start(); ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
                gap:16px;margin:24px 0;">
        
        <div style="background:#fff;border-radius:10px;padding:20px;
                    border-left:4px solid #28a745;
                    box-shadow:0 2px 8px rgba(0,0,0,0.08);">
            <div style="font-size:13px;color:#5f6368;margin-bottom:6px;">
                Активних авто
            </div>
            <div style="font-size:36px;font-weight:700;color:#28a745;">
                <?php echo $active; ?>
            </div>
        </div>
        
        <div style="background:#fff;border-radius:10px;padding:20px;
                    border-left:4px solid #1a73e8;
                    box-shadow:0 2px 8px rgba(0,0,0,0.08);">
            <div style="font-size:13px;color:#5f6368;margin-bottom:6px;">
                На ремонті
            </div>
            <div style="font-size:36px;font-weight:700;color:#1a73e8;">
                <?php echo $service; ?>
            </div>
        </div>
        
        <div style="background:#fff;border-radius:10px;padding:20px;
                    border-left:4px solid #ffc107;
                    box-shadow:0 2px 8px rgba(0,0,0,0.08);">
            <div style="font-size:13px;color:#5f6368;margin-bottom:6px;">
                Відкритих нарядів
            </div>
            <div style="font-size:36px;font-weight:700;color:#e37400;">
                <?php echo $orders; ?>
            </div>
        </div>
        
        <div style="background:#fff;border-radius:10px;padding:20px;
                    border-left:4px solid #dc3545;
                    box-shadow:0 2px 8px rgba(0,0,0,0.08);">
            <div style="font-size:13px;color:#5f6368;margin-bottom:6px;">
                Нових нагадувань
            </div>
            <div style="font-size:36px;font-weight:700;color:#dc3545;">
                <?php echo $notif; ?>
            </div>
        </div>
        
        <div style="background:#fff;border-radius:10px;padding:20px;
                    border-left:4px solid #6f42c1;
                    box-shadow:0 2px 8px rgba(0,0,0,0.08);">
            <div style="font-size:13px;color:#5f6368;margin-bottom:6px;">
                Витрати цього місяця
            </div>
            <div style="font-size:28px;font-weight:700;color:#6f42c1;">
                <?php echo number_format($month_cost, 0, '.', ' '); ?> грн
            </div>
        </div>
        
    </div>
    <?php
    return ob_get_clean();
});
