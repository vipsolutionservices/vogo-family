<?php
function vogo_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Define the referral orders table name
    $referral_orders_table = $wpdb->prefix . 'referral_orders';

    // Define the user groups table name
    $user_groups_table = $wpdb->prefix . 'user_groups';

    // Define the group categories table name
    $group_categories_table = $wpdb->prefix . 'group_categories';

    // Define the favorites table name
    $favorites_table = $wpdb->prefix . 'favorites';

    $table_name = $wpdb->prefix . 'product_recommendations';

    // SQL to create the referral_orders table
    $referral_orders_sql = "CREATE TABLE IF NOT EXISTS $referral_orders_table (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        order_value DECIMAL(10, 2) NOT NULL,
        account_username VARCHAR(255) NOT NULL,
        parent_account_username VARCHAR(255) NOT NULL
    ) $charset_collate;";

    // SQL to create the user_groups table
    $user_groups_sql = "CREATE TABLE IF NOT EXISTS $user_groups_table (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        group_id INT NOT NULL
    ) $charset_collate;";

    // SQL to create the group_categories table
    $group_categories_sql = "CREATE TABLE IF NOT EXISTS $group_categories_table (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_id INT NOT NULL,
        category_id INT NOT NULL
    ) $charset_collate;";

    // SQL to create the favorites table
    $favorites_sql = "CREATE TABLE IF NOT EXISTS $favorites_table (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        item_id INT NOT NULL,
        item_type ENUM('product', 'category') NOT NULL
    ) $charset_collate;";

    $product_recommendations_sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        product_id BIGINT UNSIGNED NOT NULL,
        recommended_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY user_product_unique (user_id, product_id)
    ) $charset_collate;";

    // Use dbDelta to safely create or update the tables
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($referral_orders_sql);
    dbDelta($user_groups_sql);
    dbDelta($group_categories_sql);
    dbDelta($favorites_sql);
    dbDelta($product_recommendations_sql);
}

// Hook to create tables during plugin activation
register_activation_hook(__FILE__, 'vogo_create_tables');