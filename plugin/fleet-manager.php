<?php
/**
 * Plugin Name: AutoMaint (Fleet Manager Avtobaza)
 * Description: Дипломна система управління технічним станом автопарку «Автобаза».
 *              Підприємство займається перевезенням продуктів (хлібзавод, макаронна
 *              фабрика, бісквітна фабрика). Машини 5т і 10т, будки/рефрижератори.
 * Version: 1.0
 * Author: Сінько Севастьян
 *
 * ============================================================
 * АРХІТЕКТУРА СИСТЕМИ (для презентації):
 *
 * РІВЕНЬ 1 — ДОВІДНИКИ (незмінні дані):
 *   departments      — підрозділи/автоколони
 *   vehicle_types    — типи ТЗ (5т, 10т)
 *   vehicle_makes    — марки авто (MAN, Mercedes, DAF...)
 *   vehicle_models   — моделі авто (прив'язані до марки)
 *   vehicle_statuses — статуси авто (ACTIVE, IN_SERVICE...)
 *   workshops        — СТО і ремонтні бази
 *   workshop_types   — типи СТО (власний/зовнішній)
 *   odometer_sources — джерела пробігу (GPS/вручну/OBD)
 *   order_types      — типи нарядів (планове ТО/ремонт/діагностика)
 *   order_statuses   — статуси нарядів (OPEN/CLOSED...)
 *   notification_statuses — статуси нагадувань (NEW/SENT/DONE)
 *   yes_no           — допоміжна таблиця для Так/Ні
 *
 * РІВЕНЬ 2 — ОСНОВНІ ДАНІ:
 *   drivers          — водії (прив'язані до підрозділу)
 *   vehicles         — транспортні засоби (центральна таблиця!)
 *   parts            — запчастини на складі
 *   maintenance_plans — шаблони планів ТО (ТО-10, ТО-20...)
 *   maintenance_items — пункти плану ТО (що саме робимо)
 *
 * РІВЕНЬ 3 — ОПЕРАЦІЙНІ ДАНІ (що відбувається щодня):
 *   odometer_logs    — журнал пробігу (кожна зміна пробігу)
 *   maintenance_orders — наряди на ТО/ремонт
 *   maintenance_tasks  — роботи в наряді
 *   part_usage         — використані запчастини
 *   notifications      — нагадування про ТО
 *
 * ПОТІК ДАНИХ:
 *   GPS-трекер → REST API → odometer_logs + vehicles.current_km
 *   → ab_check_and_notify() → notifications (NEW)
 *   → wp_mail() → email адміну → notifications (SENT)
 *   → Механік відкриває наряд → maintenance_orders (OPEN)
 *   → Додає роботи → maintenance_tasks
 *   → Списує запчастини → part_usage → parts.stock_qty зменшується
 *   → Закриває наряд → maintenance_orders (CLOSED)
 *   → MySQL тригер → notifications (DONE) → зникає зі сторінки
 *
 * РОЛІ КОРИСТУВАЧІВ:
 *   administrator — повний доступ до всього
 *   fleet_manager — менеджер: всі сторінки, звіти
 *   dispatcher    — обліковець: транспорт, запчастини, довідники
 *   mechanic      — механік: наряди на ТО, нагадування
 * ============================================================
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ============================================================
// 1. АКТИВАЦІЯ ПЛАГІНУ — СТВОРЕННЯ ВСІХ ТАБЛИЦЬ
//
//    ВАЖЛИВО: dbDelta() вимагає КОЖНУ таблицю ОКРЕМО!
//    Одна таблиця = один виклик dbDelta().
//    Інакше WordPress створить тільки першу таблицю.
//
//    ПОРЯДОК СТВОРЕННЯ ВАЖЛИВИЙ:
//    Спочатку "батьківські" таблиці (без FK),
//    потім "дочірні" (з FK на батьківські).
// ============================================================

register_activation_hook( __FILE__, 'ab_create_db' );

function ab_create_db() {
    global $wpdb;
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    $charset_collate = $wpdb->get_charset_collate();
    $p = $wpdb->prefix . 'ab_'; // префікс 'wp_ab_' для всіх наших таблиць

    // ==========================================================
    // БЛОК А: ДОВІДНИКОВІ ТАБЛИЦІ
    // Це "словники" системи — заповнюються один раз і майже
    // не змінюються. Вони не мають зовнішніх ключів між собою.
    // ==========================================================

    // ----------------------------------------------------------
    // ТАБЛИЦЯ 1: departments — Підрозділи / автоколони
    // Приклад: "Хлібзавод №1", "Макаронна фабрика", "Бісквітна фабрика"
    // Зв'язки: drivers.dept_id → departments.id
    //          vehicles.dept_id → departments.id
    // ----------------------------------------------------------
    dbDelta( "CREATE TABLE {$p}departments (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name        VARCHAR(255)    NOT NULL,
        address     VARCHAR(500)    DEFAULT NULL,
        phone       VARCHAR(30)     DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_dept_name (name)
    ) $charset_collate;" );

    // ----------------------------------------------------------
    // ТАБЛИЦЯ 2: vehicle_types — Типи транспортних засобів
    // Приклад: "Вантажний 5т (будка)", "Вантажний 10т (рефрижератор)"
    // Потрібна для: автоматичного призначення планів ТО по типу авто
    // Зв'язки: vehicles.type_id → vehicle_types.id
    //          maintenance_plans.vehicle_type_id → vehicle_types.id
    // ----------------------------------------------------------
    dbDelta( "CREATE TABLE {$p}vehicle_types (
        id   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(100)    NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_vtype_name (name)
    ) $charset_collate;" );

    // ----------------------------------------------------------
    // ТАБЛИЦЯ 3: vehicle_makes — Марки автомобілів
    // Приклад: "MAN", "Mercedes-Benz", "DAF", "Volvo"
    // Додано в процесі розробки для каскадного вибору Марка→Модель
    // Зв'язки: vehicles.make_id → vehicle_makes.id
    //          vehicle_models.make_id → vehicle_makes.id
    // ----------------------------------------------------------
    dbDelta( "CREATE TABLE {$p}vehicle_makes (
        id   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(100)    NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_make_name (name)
    ) $charset_collate;" );

    // ----------------------------------------------------------
    // ТАБЛИЦЯ 4: vehicle_models — Моделі автомобілів
    // Приклад: make_id=1(MAN) → "TGM 12.250", "TGL 10.180"
    // Каскадний Lookup: при виборі марки MAN — показуються тільки
    // моделі MAN, а не всі моделі підряд.
    // Зв'язки: vehicle_models.make_id → vehicle_makes.id
    //          vehicles.model_id → vehicle_models.id
    // ----------------------------------------------------------
    dbDelta( "CREATE TABLE {$p}vehicle_models (
        id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        make_id BIGINT UNSIGNED NOT NULL,
        name    VARCHAR(100)    NOT NULL,
        PRIMARY KEY (id),
        KEY idx_model_make (make_id)
    ) $charset_collate;" );

    // ----------------------------------------------------------
    // ТАБЛИЦЯ 5: vehicle_statuses — Статуси транспортних засобів
    // Довідник для Lookup замість сирих рядків ACTIVE/IN_SERVICE
    // Значення: ACTIVE, IN_SERVICE, OUT_OF_SERVICE, SOLD
    // ----------------------------------------------------------
    dbDelta( "CREATE TABLE {$p}vehicle_statuses (
        id   VARCHAR(30)  NOT NULL,
        name VARCHAR(100) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;" );

    // ----------------------------------------------------------
    // ТАБЛИЦЯ 6: workshops — СТО і ремонтні бази
    // Приклад: "Власний гараж" (INTERNAL), "СТО Автосервіс №1" (EXTERNAL)
    // Зв'язки: maintenance_orders.workshop_id → workshops.id
    // ----------------------------------------------------------
    dbDelta( "CREATE TABLE {$p}workshops (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name        VARCHAR(255)    NOT NULL,
        type        VARCHAR(20)     NOT NULL DEFAULT 'INTERNAL',
        address     VARCHAR(500)    DEFAULT NULL,
        phone       VARCHAR(30)     DEFAULT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;" );

    // ----------------------------------------------------------
    // ТАБЛИЦЯ 7: workshop_types — Типи СТО
    // Довідник: INTERNAL (власний) / EXTERNAL (зовнішній)
    // ----------------------------------------------------------
    dbDelta( "CREATE TABLE {$p}workshop_types (
        id   VARCHAR(20)  NOT NULL,
        name VARCHAR(100) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;" );

    // ----------------------------------------------------------
    // ТАБЛИЦЯ 8: odometer_sources — Джерела даних пробігу
    // Довідник: GPS_TRACKER / MANUAL / OBD
    // Пояснює звідки прийшли дані пробігу в odometer_logs
    // ----------------------------------------------------------
    dbDelta( "CREATE TABLE {$p}odometer_sources (
        id   VARCHAR(30)  NOT NULL,
        name VARCHAR(100) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;" );

    // ----------------------------------------------------------
    // ТАБЛИЦЯ 9: order_types — Типи нарядів на роботу
    // Довідник: PLANNED_TO / REPAIR / DIAGNOSTIC
    // ----------------------------------------------------------
    dbDelta( "CREATE TABLE {$p}order_types (
        id   VARCHAR(30)  NOT NULL,
        name VARCHAR(100) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;" );

    // ----------------------------------------------------------
    // ТАБЛИЦЯ 10: order_statuses — Статуси нарядів
    // Довідник: OPEN → IN_PROGRESS → CLOSED / CANCELED
    // Lifecycle наряду: відкрито → в роботі → закрито
    // ----------------------------------------------------------
    dbDelta( "CREATE TABLE {$p}order_statuses (
        id   VARCHAR(20)  NOT NULL,
        name VARCHAR(100) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;" );

    // ----------------------------------------------------------
    // ТАБЛИЦЯ 11: notification_statuses — Статуси нагадувань
    // Довідник: NEW → SENT → DONE / CANCELED
    // NEW    — нагадування створено, email ще не надіслано
    // SENT   — email надіслано адміну
    // DONE   — ТО виконано (наряд закрито — MySQL тригер!)
    // CANCELED — нагадування скасовано вручну
    // ----------------------------------------------------------
    dbDelta( "CREATE TABLE {$p}notification_statuses (
        id   VARCHAR(20)  NOT NULL,
        name VARCHAR(100) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;" );

    // ----------------------------------------------------------
    // ТАБЛИЦЯ 12: yes_no — Допоміжна таблиця для Lookup Так/Ні
    // Використовується для полів is_active у водіях, планах ТО
    // Замість цифр 0/1 показує "Так" / "Ні" у формах
    // ----------------------------------------------------------
    dbDelta( "CREATE TABLE {$p}yes_no (
        id   TINYINT     NOT NULL,
        name VARCHAR(10) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;" );

    // ==========================================================
    // БЛОК Б: ОСНОВНІ ТАБЛИЦІ
    // Зберігають головні об'єкти системи.
    // ==========================================================

    // ----------------------------------------------------------
    // ТАБЛИЦЯ 13: drivers — Водії
    // Водії НЕ є користувачами системи — вони суб'єкти,
    // за якими закріплюються транспортні засоби.
    // Зв'язки: drivers.dept_id → departments.id (1:M)
    //          vehicles.driver_id → drivers.id (1:1 поточний водій)
    // ----------------------------------------------------------
    dbDelta( "CREATE TABLE {$p}drivers (
        id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        dept_id          BIGINT UNSIGNED DEFAULT NULL,
        last_name        VARCHAR(100)    NOT NULL,
        first_name       VARCHAR(100)    NOT NULL,
        phone            VARCHAR(30)     DEFAULT NULL,
        license_category VARCHAR(20)     DEFAULT NULL,
        license_number   VARCHAR(50)     DEFAULT NULL,
        is_active        TINYINT(1)      NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        KEY idx_driver_dept (dept_id)
    ) $charset_collate;" );

    // ----------------------------------------------------------
    // ТАБЛИЦЯ 14: vehicles — Транспортні засоби
    // ЦЕНТРАЛЬНА ТАБЛИЦЯ СИСТЕМИ! До неї прив'язані:
    // odometer_logs, maintenance_orders, notifications
    //
    // ВАЖЛИВА ЗМІНА від v2.0: замість текстових полів make/model
    // тепер make_id і model_id — зовнішні ключі на окремі таблиці.
    // Це дозволяє каскадний вибір Марка→Модель у формах.
    //
    // current_km — актуальний пробіг (оновлюється з GPS-трекера)
    // status     — ACTIVE | IN_SERVICE | OUT_OF_SERVICE | SOLD
    // ----------------------------------------------------------
    dbDelta( "CREATE TABLE {$p}vehicles (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        dept_id         BIGINT UNSIGNED DEFAULT NULL,
        type_id         BIGINT UNSIGNED DEFAULT NULL,
        driver_id       BIGINT UNSIGNED DEFAULT NULL,
        make_id         BIGINT UNSIGNED DEFAULT NULL,
        model_id        BIGINT UNSIGNED DEFAULT NULL,
        vin             VARCHAR(17)     DEFAULT NULL,
        plate_number    VARCHAR(20)     NOT NULL,
        year            SMALLINT        DEFAULT NULL,
        purchase_date   DATE            DEFAULT NULL,
        current_km      INT UNSIGNED    NOT NULL DEFAULT 0,
        status          VARCHAR(30)     NOT NULL DEFAULT 'ACTIVE',
        PRIMARY KEY (id),
        UNIQUE KEY uq_plate (plate_number),
        KEY idx_vehicle_dept   (dept_id),
        KEY idx_vehicle_type   (type_id),
        KEY idx_vehicle_driver (driver_id),
        KEY idx_vehicle_make   (make_id),
        KEY idx_vehicle_model  (model_id)
    ) $charset_collate;" );

    // ----------------------------------------------------------
    // ТАБЛИЦЯ 15: parts — Запчастини (склад)
    // Зберігає номенклатуру запчастин і поточний залишок.
    // stock_qty  — поточний залишок (зменшується тригером при списанні)
    // min_stock  — мінімальний залишок (для попередження "мало!")
    // unit_price — поточна ціна (для нових списань)
    // Зв'язки: part_usage.part_id → parts.id (N:M через part_usage)
    //          maintenance_items.recommended_part_id → parts.id
    // ----------------------------------------------------------
    dbDelta( "CREATE TABLE {$p}parts (
        id          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
        name        VARCHAR(255)     NOT NULL,
        sku         VARCHAR(100)     DEFAULT NULL,
        stock_qty   INT              NOT NULL DEFAULT 0,
        min_stock   INT              NOT NULL DEFAULT 0,
        unit_price  DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
        PRIMARY KEY (id),
        UNIQUE KEY uq_part_sku (sku)
    ) $charset_collate;" );

    // ----------------------------------------------------------
    // ТАБЛИЦЯ 16: maintenance_plans — Шаблони планів ТО
    // Це РЕГЛАМЕНТ обслуговування: ТО-10, ТО-20, ТО-60, ТО-100
    // interval_km   — через скільки км проводити (напр. 10000)
    // interval_days — через скільки днів (напр. 180)
    // Система перевіряє ОБИДВА інтервали і реагує на той що
    // настане РАНІШЕ (км або дні).
    // vehicle_type_id — NULL означає план для ВСІХ типів авто
    // Зв'язки: maintenance_plans.vehicle_type_id → vehicle_types.id
    //          maintenance_items.plan_id → maintenance_plans.id
    //          maintenance_orders.plan_id → maintenance_plans.id
    //          notifications.plan_id → maintenance_plans.id
    // ----------------------------------------------------------
    dbDelta( "CREATE TABLE {$p}maintenance_plans (
        id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        vehicle_type_id  BIGINT UNSIGNED DEFAULT NULL,
        name             VARCHAR(100)    NOT NULL,
        interval_km      INT UNSIGNED    DEFAULT NULL,
        interval_days    INT UNSIGNED    DEFAULT NULL,
        is_active        TINYINT(1)      NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        KEY idx_mplan_type (vehicle_type_id)
    ) $charset_collate;" );

    // ----------------------------------------------------------
    // ТАБЛИЦЯ 17: maintenance_items — Пункти плану ТО
    // Деталізація КОНКРЕТНИХ ОПЕРАЦІЙ в плані:
    // "Заміна моторної оливи", "Заміна масляного фільтру" і т.д.
    // norm_hours — нормо-години на виконання (для планування)
    // recommended_part_id — рекомендована запчастина для цієї роботи
    // Зв'язки: maintenance_items.plan_id → maintenance_plans.id (M:1)
    //          maintenance_items.recommended_part_id → parts.id
    // ----------------------------------------------------------
    dbDelta( "CREATE TABLE {$p}maintenance_items (
        id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        plan_id              BIGINT UNSIGNED NOT NULL,
        task_name            VARCHAR(255)    NOT NULL,
        description          TEXT            DEFAULT NULL,
        norm_hours           DECIMAL(5,2)    DEFAULT NULL,
        recommended_part_id  BIGINT UNSIGNED DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_mitem_plan (plan_id)
    ) $charset_collate;" );

    // ==========================================================
    // БЛОК В: ОПЕРАЦІЙНІ ТАБЛИЦІ
    // Заповнюються автоматично або механіком щодня.
    // ==========================================================

    // ----------------------------------------------------------
    // ТАБЛИЦЯ 18: odometer_logs — Журнал пробігу
    // КОЖНА зміна пробігу фіксується як ОКРЕМИЙ ЗАПИС.
    // НЕ перезаписуємо — ДОДАЄМО! Це дає повну історію.
    // source: GPS_TRACKER (автоматично) | MANUAL | OBD
    // created_by: NULL якщо від GPS-трекера, ID юзера якщо вручну
    //
    // Потік: GPS → REST API → цей журнал + vehicles.current_km
    // Зв'язки: odometer_logs.vehicle_id → vehicles.id (M:1)
    // ----------------------------------------------------------
    dbDelta( "CREATE TABLE {$p}odometer_logs (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        vehicle_id  BIGINT UNSIGNED NOT NULL,
        km          INT UNSIGNED    NOT NULL,
        log_dt      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        source      VARCHAR(30)     NOT NULL DEFAULT 'MANUAL',
        created_by  BIGINT UNSIGNED DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_olog_vehicle (vehicle_id),
        KEY idx_olog_dt      (log_dt)
    ) $charset_collate;" );

    // ----------------------------------------------------------
    // ТАБЛИЦЯ 19: maintenance_orders — Наряди на ТО і ремонт
    // Фіксує РЕАЛЬНЕ обслуговування автомобіля.
    // Lifecycle: OPEN → IN_PROGRESS → CLOSED (або CANCELED)
    //
    // odometer_km_at_open — пробіг при відкритті (для звітності!)
    // total_cost — автоматично рахується MySQL тригером:
    //   = SUM(maintenance_tasks.labor_cost)
    //   + SUM(part_usage.qty * part_usage.unit_price_at_use)
    //
    // Зв'язки: maintenance_orders.vehicle_id → vehicles.id
    //          maintenance_orders.workshop_id → workshops.id
    //          maintenance_orders.plan_id → maintenance_plans.id
    //          maintenance_tasks.order_id → maintenance_orders.id
    // ----------------------------------------------------------
    dbDelta( "CREATE TABLE {$p}maintenance_orders (
        id                  BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
        vehicle_id          BIGINT UNSIGNED  NOT NULL,
        workshop_id         BIGINT UNSIGNED  DEFAULT NULL,
        plan_id             BIGINT UNSIGNED  DEFAULT NULL,
        order_type          VARCHAR(30)      NOT NULL DEFAULT 'PLANNED_TO',
        status              VARCHAR(20)      NOT NULL DEFAULT 'OPEN',
        odometer_km_at_open INT UNSIGNED     DEFAULT NULL,
        opened_dt           DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
        closed_dt           DATETIME         DEFAULT NULL,
        total_cost          DECIMAL(12,2)    NOT NULL DEFAULT 0.00,
        notes               TEXT             DEFAULT NULL,
        created_by          BIGINT UNSIGNED  DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_morder_vehicle (vehicle_id),
        KEY idx_morder_status  (status),
        KEY idx_morder_opened  (opened_dt)
    ) $charset_collate;" );

    // ----------------------------------------------------------
    // ТАБЛИЦЯ 20: maintenance_tasks — Конкретні роботи в наряді
    // Кожен наряд містить список робіт: "Заміна оливи", "Заміна фільтру"
    // labor_cost — вартість РОБОТИ (без запчастин)
    // is_done    — механік відмічає виконані пункти
    // Зв'язки: maintenance_tasks.order_id → maintenance_orders.id (M:1)
    //          part_usage.task_id → maintenance_tasks.id (1:M)
    // ----------------------------------------------------------
    dbDelta( "CREATE TABLE {$p}maintenance_tasks (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id    BIGINT UNSIGNED NOT NULL,
        task_name   VARCHAR(255)    NOT NULL,
        labor_cost  DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
        is_done     TINYINT(1)      NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY idx_mtask_order (order_id)
    ) $charset_collate;" );

    // ----------------------------------------------------------
    // ТАБЛИЦЯ 21: part_usage — Використані запчастини
    // Зв'язує роботи (tasks) і запчастини (parts) — зв'язок N:M
    // unit_price_at_use — ціна НА МОМЕНТ ВИКОРИСТАННЯ
    //   (зберігаємо окремо бо parts.unit_price може змінюватись!)
    //
    // MySQL тригери на цю таблицю:
    //   BEFORE INSERT: перевірка залишку на складі
    //   AFTER INSERT:  зменшення stock_qty + оновлення total_cost
    //
    // Зв'язки: part_usage.task_id → maintenance_tasks.id
    //          part_usage.part_id → parts.id
    // ----------------------------------------------------------
    dbDelta( "CREATE TABLE {$p}part_usage (
        id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        task_id           BIGINT UNSIGNED NOT NULL,
        part_id           BIGINT UNSIGNED NOT NULL,
        qty               INT UNSIGNED    NOT NULL DEFAULT 1,
        unit_price_at_use DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
        PRIMARY KEY (id),
        KEY idx_pusage_task (task_id),
        KEY idx_pusage_part (part_id)
    ) $charset_collate;" );

    // ----------------------------------------------------------
    // ТАБЛИЦЯ 22: notifications — Нагадування про ТО
    // Створюються АВТОМАТИЧНО коли пробіг наближається до порогу.
    //
    // Lifecycle нагадування:
    //   NEW  → система виявила потребу в ТО
    //   SENT → email надіслано адміну (wp_mail)
    //   DONE → ТО виконано (MySQL тригер після закриття наряду!)
    //   CANCELED → скасовано вручну
    //
    // Кольорова система "світлофор":
    //   > 1000 км до ТО  → не створюємо нагадування
    //   200-1000 км      → ПОПЕРЕДЖЕННЯ (жовтий)
    //   < 200 км         → ТЕРМІНОВО (помаранчевий)
    //   0 км (прострочено) → ПРОСТРОЧЕНО (червоний)
    //
    // Зв'язки: notifications.vehicle_id → vehicles.id
    //          notifications.plan_id → maintenance_plans.id
    // ----------------------------------------------------------
    dbDelta( "CREATE TABLE {$p}notifications (
        id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        vehicle_id      BIGINT UNSIGNED NOT NULL,
        plan_id         BIGINT UNSIGNED DEFAULT NULL,
        status          VARCHAR(20)     NOT NULL DEFAULT 'NEW',
        message         TEXT            NOT NULL,
        due_km          INT UNSIGNED    DEFAULT NULL,
        due_date        DATE            DEFAULT NULL,
        created_dt      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        target_user_id  BIGINT UNSIGNED DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_notif_vehicle (vehicle_id),
        KEY idx_notif_status  (status)
    ) $charset_collate;" );

    // ==========================================================
    // ЗАПОВНЕННЯ ДОВІДНИКІВ ПОЧАТКОВИМИ ДАНИМИ
    // ==========================================================

    // Статуси авто
    $wpdb->query( "INSERT IGNORE INTO {$p}vehicle_statuses (id, name) VALUES
        ('ACTIVE',          'Активний'),
        ('IN_SERVICE',      'На ремонті'),
        ('OUT_OF_SERVICE',  'Виведений з ладу'),
        ('SOLD',            'Проданий')" );

    // Типи СТО
    $wpdb->query( "INSERT IGNORE INTO {$p}workshop_types (id, name) VALUES
        ('INTERNAL', 'Власний гараж'),
        ('EXTERNAL', 'Стороннє СТО')" );

    // Джерела пробігу
    $wpdb->query( "INSERT IGNORE INTO {$p}odometer_sources (id, name) VALUES
        ('GPS_TRACKER', 'GPS-трекер'),
        ('MANUAL',      'Вручну'),
        ('OBD',         'OBD-сканер')" );

    // Типи нарядів
    $wpdb->query( "INSERT IGNORE INTO {$p}order_types (id, name) VALUES
        ('PLANNED_TO',  'Планове ТО'),
        ('REPAIR',      'Ремонт'),
        ('DIAGNOSTIC',  'Діагностика')" );

    // Статуси нарядів
    $wpdb->query( "INSERT IGNORE INTO {$p}order_statuses (id, name) VALUES
        ('OPEN',        'Відкрито'),
        ('IN_PROGRESS', 'В роботі'),
        ('CLOSED',      'Закрито'),
        ('CANCELED',    'Скасовано')" );

    // Статуси нагадувань
    $wpdb->query( "INSERT IGNORE INTO {$p}notification_statuses (id, name) VALUES
        ('NEW',      'Нове'),
        ('SENT',     'Надіслано'),
        ('DONE',     'Виконано'),
        ('CANCELED', 'Скасовано')" );

    // Так/Ні
    $wpdb->query( "INSERT IGNORE INTO {$p}yes_no (id, name) VALUES
        (0, 'Ні'),
        (1, 'Так')" );

    // Зберігаємо версію БД
    add_option( 'ab_db_version', '3.0' );
}


// ============================================================
// 2. REST API ENDPOINT — ПРИЙОМ ДАНИХ ВІД GPS-ТРЕКЕРА
//
//    URL: POST /wp-json/ab/v1/track/{vehicle_id}
//    Body (JSON): { "km": 52500 }
//
//    Приклад виклику через PowerShell (для демонстрації):
//    Invoke-RestMethod -Uri "http://automaint.local/wp-json/ab/v1/track/1"
//      -Method POST -ContentType "application/json" -Body '{"km": 55000}'
//
//    Що відбувається після отримання даних:
//    1. Валідація (пробіг > 0, авто існує, не менше поточного)
//    2. Оновлення vehicles.current_km
//    3. Запис в odometer_logs (джерело: GPS_TRACKER)
//    4. Перевірка чи потрібне нагадування (ab_check_and_notify)
// ============================================================

add_action( 'rest_api_init', function () {
    register_rest_route( 'ab/v1', '/track/(?P<id>\d+)', array(
        'methods'             => 'POST',
        'callback'            => 'ab_api_receive_data',
        'permission_callback' => 'ab_api_check_key',
    ) );
} );

/**
 * Перевірка секретного ключа API.
 * Ключ встановлюється в WordPress → AutoMaint → Ключ API.
 * Якщо ключ не налаштований — дозволяємо (режим демо для диплому).
 */
function ab_api_check_key( WP_REST_Request $request ) {
    $saved_key    = get_option( 'ab_api_key', '' );
    $provided_key = sanitize_text_field( $request->get_param( 'api_key' ) );

    if ( empty( $saved_key ) ) return true; // демо-режим

    return hash_equals( $saved_key, $provided_key );
}

/**
 * Основна логіка отримання пробігу від трекера.
 * Захист від "мотання одометра" — нові дані не можуть бути меншими.
 */
function ab_api_receive_data( WP_REST_Request $data ) {
    global $wpdb;
    $p          = $wpdb->prefix . 'ab_';
    $vehicle_id = (int) $data['id'];
    $new_km     = (int) $data->get_param( 'km' );

    if ( $new_km <= 0 ) {
        return new WP_Error( 'invalid_km', 'Пробіг повинен бути більше нуля', array( 'status' => 400 ) );
    }

    $vehicle = $wpdb->get_row( $wpdb->prepare(
        "SELECT id, current_km, status FROM {$p}vehicles WHERE id = %d",
        $vehicle_id
    ) );

    if ( ! $vehicle ) {
        return new WP_Error( 'no_vehicle', 'Автомобіль не знайдено', array( 'status' => 404 ) );
    }

    // Захист від мотання одометра
    if ( $new_km < (int) $vehicle->current_km ) {
        return new WP_Error( 'km_rollback', sprintf(
            'Помилка: новий пробіг (%d км) менший за поточний (%d км). Можливе мотання одометра.',
            $new_km, $vehicle->current_km
        ), array( 'status' => 409 ) );
    }

    // Оновлення поточного пробігу
    $wpdb->update( "{$p}vehicles", array( 'current_km' => $new_km ), array( 'id' => $vehicle_id ) );

    // Запис в журнал пробігу
    $wpdb->insert( "{$p}odometer_logs", array(
        'vehicle_id' => $vehicle_id,
        'km'         => $new_km,
        'log_dt'     => current_time( 'mysql' ),
        'source'     => 'GPS_TRACKER',
        'created_by' => null,
    ) );

    // Перевірка необхідності нагадування
    ab_check_and_notify( $vehicle_id, $new_km );

    return array(
        'status'      => 'success',
        'message'     => 'Дані телематики отримано',
        'vehicle_id'  => $vehicle_id,
        'new_mileage' => $new_km,
    );
}


// ============================================================
// 3. ЛОГІКА НАГАДУВАНЬ
//
//    Кольорова система "світлофор":
//    > 1000 км  → все добре, не спамимо
//    200-1000   → ПОПЕРЕДЖЕННЯ (жовтий) — почніть планувати
//    < 200      → ТЕРМІНОВО (помаранчевий) — направити в сервіс
//    < 0        → ПРОСТРОЧЕНО (червоний) — негайно!
//
//    Захист від дублювання: якщо вже є активне нагадування
//    (NEW або SENT) для цього авто і плану — нове не створюється.
// ============================================================

function ab_check_and_notify( $vehicle_id, $current_km ) {
    global $wpdb;
    $p = $wpdb->prefix . 'ab_';

    $vehicle = $wpdb->get_row( $wpdb->prepare(
        "SELECT v.id, v.type_id, v.plate_number FROM {$p}vehicles v WHERE v.id = %d",
        $vehicle_id
    ) );
    if ( ! $vehicle ) return;

    $plans = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$p}maintenance_plans
         WHERE is_active = 1
           AND (vehicle_type_id = %d OR vehicle_type_id IS NULL)",
        $vehicle->type_id
    ) );

    foreach ( $plans as $plan ) {
        if ( empty( $plan->interval_km ) ) continue;

        // Пробіг на останньому закритому наряді за цим планом
        $last_order_km = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT odometer_km_at_open FROM {$p}maintenance_orders
             WHERE vehicle_id = %d AND plan_id = %d AND status = 'CLOSED'
             ORDER BY closed_dt DESC LIMIT 1",
            $vehicle_id, $plan->id
        ) );

        $km_since_last_to = $current_km - $last_order_km;
        $km_remaining     = (int) $plan->interval_km - $km_since_last_to;

        if ( $km_remaining > 1000 ) continue; // все добре

        // Перевірка дублювання
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$p}notifications
             WHERE vehicle_id = %d AND plan_id = %d AND status IN ('NEW','SENT')
             LIMIT 1",
            $vehicle_id, $plan->id
        ) );
        if ( $existing ) continue;

        // Формуємо текст повідомлення
        if ( $km_remaining <= 0 ) {
            $message = sprintf(
                '🔴 ПРОСТРОЧЕНО! Авто %s: ТО "%s" прострочене на %d км. Негайно направити в сервіс!',
                esc_html( $vehicle->plate_number ),
                esc_html( $plan->name ),
                abs( $km_remaining )
            );
        } elseif ( $km_remaining < 200 ) {
            $message = sprintf(
                '🟠 ТЕРМІНОВО! Авто %s: до ТО "%s" залишилось %d км. Направити в сервіс найближчим часом.',
                esc_html( $vehicle->plate_number ),
                esc_html( $plan->name ),
                $km_remaining
            );
        } else {
            $message = sprintf(
                '🟡 ПОПЕРЕДЖЕННЯ: Авто %s: до ТО "%s" залишилось %d км. Почніть планування.',
                esc_html( $vehicle->plate_number ),
                esc_html( $plan->name ),
                $km_remaining
            );
        }

        // Зберігаємо нагадування
        $wpdb->insert( "{$p}notifications", array(
            'vehicle_id' => $vehicle_id,
            'plan_id'    => $plan->id,
            'status'     => 'NEW',
            'message'    => $message,
            'due_km'     => $last_order_km + (int) $plan->interval_km,
            'created_dt' => current_time( 'mysql' ),
        ) );

        // Надсилаємо email і оновлюємо статус на SENT
        $notification_id = $wpdb->insert_id;
        ab_send_notification_email( $message, $notification_id );
    }
}

/**
 * Надсилання email про нагадування.
 * Після успішного надсилання — статус нагадування SENT.
 * Статус DONE встановлюється автоматично MySQL тригером
 * при закритті наряду на ТО.
 */
function ab_send_notification_email( $message, $notification_id = null ) {
    $admin_email = get_option( 'admin_email' );
    $site_name   = get_bloginfo( 'name' );

    $sent = wp_mail(
        $admin_email,
        "[{$site_name}] Нагадування про ТО автомобіля",
        $message . "\n\nАвтоматичне повідомлення системи «Автобаза»."
    );

    // Оновлюємо статус на SENT якщо email надіслано успішно
    if ( $sent && $notification_id ) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'ab_notifications',
            array( 'status' => 'SENT' ),
            array( 'id'     => $notification_id )
        );
    }
}


// ============================================================
// 4. WP-CRON — АВТОМАТИЧНА ПЕРЕВІРКА ВСІХ АВТО КОЖНІ 6 ГОДИН
//
//    Навіщо: GPS-трекер надсилає дані при кожному русі,
//    але якщо авто стоїть — перевіряємо за інтервалом ДНІВ.
//    WP-Cron запускається при відвідуванні сайту.
// ============================================================

add_filter( 'cron_schedules', function( $schedules ) {
    $schedules['every_6_hours'] = array(
        'interval' => 6 * HOUR_IN_SECONDS,
        'display'  => 'Кожні 6 годин',
    );
    return $schedules;
} );

register_activation_hook( __FILE__, function() {
    if ( ! wp_next_scheduled( 'ab_cron_check_notifications' ) ) {
        wp_schedule_event( time(), 'every_6_hours', 'ab_cron_check_notifications' );
    }
} );

register_deactivation_hook( __FILE__, function() {
    $timestamp = wp_next_scheduled( 'ab_cron_check_notifications' );
    if ( $timestamp ) wp_unschedule_event( $timestamp, 'ab_cron_check_notifications' );
} );

add_action( 'ab_cron_check_notifications', 'ab_cron_run_all_checks' );

function ab_cron_run_all_checks() {
    global $wpdb;
    $p = $wpdb->prefix . 'ab_';

    $vehicles = $wpdb->get_results(
        "SELECT id, current_km FROM {$p}vehicles WHERE status = 'ACTIVE'"
    );

    foreach ( $vehicles as $v ) {
        ab_check_and_notify( $v->id, $v->current_km );
    }
}


// ============================================================
// 5. АДМІН-МЕНЮ — СТОРІНКА ПЛАГІНУ В WORDPRESS
// ============================================================

add_action( 'admin_menu', function() {
    add_menu_page(
        'Автобаза', 'Автобаза', 'manage_options',
        'avtobaza', 'ab_admin_page', 'dashicons-car', 30
    );
    add_submenu_page( 'avtobaza', 'Налаштування API', 'Ключ API',
        'manage_options', 'avtobaza-settings', 'ab_settings_page' );
} );

function ab_admin_page() {
    global $wpdb;
    $p = $wpdb->prefix . 'ab_';

    $vehicles_count    = $wpdb->get_var( "SELECT COUNT(*) FROM {$p}vehicles WHERE status='ACTIVE'" );
    $open_orders_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$p}maintenance_orders WHERE status='OPEN'" );
    $new_notif_count   = $wpdb->get_var( "SELECT COUNT(*) FROM {$p}notifications WHERE status='NEW'" );
    $parts_low_count   = $wpdb->get_var( "SELECT COUNT(*) FROM {$p}parts WHERE stock_qty < min_stock" );

    echo '<div class="wrap">';
    echo '<h1>🚛 Автобаза — Система управління автопарком</h1>';
    echo '<p>Версія 3.0 | <a href="' . admin_url('admin.php?page=avtobaza-settings') . '">Налаштування API</a></p>';
    echo '<p>Для роботи з таблицями використовуйте плагін <strong>WP Data Access</strong>.</p>';

    echo '<div style="display:flex;gap:20px;margin-top:20px;flex-wrap:wrap;">';
    echo "<div style='background:#fff;padding:20px;border-left:4px solid #28a745;border-radius:4px;min-width:150px'><h2 style='margin:0'>{$vehicles_count}</h2><p>Активних авто</p></div>";
    echo "<div style='background:#fff;padding:20px;border-left:4px solid #007bff;border-radius:4px;min-width:150px'><h2 style='margin:0'>{$open_orders_count}</h2><p>Відкритих нарядів</p></div>";
    echo "<div style='background:#fff;padding:20px;border-left:4px solid #ffc107;border-radius:4px;min-width:150px'><h2 style='margin:0'>{$new_notif_count}</h2><p>Нових нагадувань</p></div>";
    echo "<div style='background:#fff;padding:20px;border-left:4px solid #dc3545;border-radius:4px;min-width:150px'><h2 style='margin:0'>{$parts_low_count}</h2><p>Запчастин мало</p></div>";
    echo '</div>';

    echo '<h2 style="margin-top:30px;">Структура бази даних (22 таблиці)</h2>';
    echo '<table class="widefat striped"><thead><tr><th>#</th><th>Таблиця</th><th>Призначення</th><th>Записів</th></tr></thead><tbody>';

    $tables = [
        ['departments',           'Підрозділи / автоколони'],
        ['vehicle_types',         'Типи транспортних засобів'],
        ['vehicle_makes',         'Марки автомобілів'],
        ['vehicle_models',        'Моделі автомобілів'],
        ['vehicle_statuses',      'Статуси авто (довідник)'],
        ['workshops',             'СТО і ремонтні бази'],
        ['workshop_types',        'Типи СТО (довідник)'],
        ['odometer_sources',      'Джерела пробігу (довідник)'],
        ['order_types',           'Типи нарядів (довідник)'],
        ['order_statuses',        'Статуси нарядів (довідник)'],
        ['notification_statuses', 'Статуси нагадувань (довідник)'],
        ['yes_no',                'Так/Ні (допоміжна)'],
        ['drivers',               'Водії'],
        ['vehicles',              'Транспортні засоби (центральна!)'],
        ['parts',                 'Запчастини на складі'],
        ['maintenance_plans',     'Плани ТО (шаблони регламентів)'],
        ['maintenance_items',     'Пункти планів ТО'],
        ['odometer_logs',         'Журнал пробігу'],
        ['maintenance_orders',    'Наряди на ТО і ремонт'],
        ['maintenance_tasks',     'Роботи в нарядах'],
        ['part_usage',            'Використані запчастини'],
        ['notifications',         'Нагадування про ТО'],
    ];

    foreach ( $tables as $i => $t ) {
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$p}{$t[0]}" );
        echo "<tr><td>" . ($i+1) . "</td><td><code>wp_ab_{$t[0]}</code></td><td>{$t[1]}</td><td>{$count}</td></tr>";
    }

    echo '</tbody></table></div>';
}

function ab_settings_page() {
    if ( isset( $_POST['ab_api_key_save'] ) ) {
        update_option( 'ab_api_key', sanitize_text_field( $_POST['ab_api_key_value'] ) );
        echo '<div class="notice notice-success"><p>Ключ збережено!</p></div>';
    }
    $current_key = get_option( 'ab_api_key', '' );
    echo '<div class="wrap"><h1>Налаштування API</h1>';
    echo '<form method="post"><table class="form-table"><tr>';
    echo '<th>Секретний ключ API (для трекерів)</th>';
    echo '<td><input type="text" name="ab_api_key_value" value="' . esc_attr($current_key) . '" size="40">';
    echo '<p class="description">Залиште порожнім для публічного доступу (тільки для демо).<br>';
    echo 'URL для трекера: <code>' . get_rest_url(null, 'ab/v1/track/{vehicle_id}') . '</code><br>';
    echo 'Тест через PowerShell: <code>Invoke-RestMethod -Uri "' . get_rest_url(null, 'ab/v1/track/1') . '" -Method POST -ContentType "application/json" -Body \'{"km": 55000}\'</code></p></td>';
    echo '</tr></table>';
    echo '<input type="hidden" name="ab_api_key_save" value="1">';
    submit_button('Зберегти ключ');
    echo '</form></div>';
}
