<?php
/**
 * Create or update the custom tables for Groups
 */

add_action('after_switch_theme', 'my_create_custom_tables');
function my_create_custom_tables() {
    global $wpdb;

    // Make sure we can use dbDelta
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    $charset_collate = $wpdb->get_charset_collate();

    // Table 1: user_groups
    $table_groups = $wpdb->prefix . 'user_groups';
    $sql_groups = "CREATE TABLE IF NOT EXISTS $table_groups (
      id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      name VARCHAR(200) NOT NULL,
      created_at DATETIME NOT NULL,
      PRIMARY KEY (id)
    ) $charset_collate;";

    // Table 2: user_group_users (maps user_id ↔ group_id)
    $table_user_groups = $wpdb->prefix . 'user_group_users';
    $sql_user_groups = "CREATE TABLE IF NOT EXISTS $table_user_groups (
      id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      user_id BIGINT(20) UNSIGNED NOT NULL,
      group_id BIGINT(20) UNSIGNED NOT NULL,
      PRIMARY KEY (id)
    ) $charset_collate;";

    // Table 3: user_group_categories (maps group_id ↔ category_id)
    $table_group_cats = $wpdb->prefix . 'user_group_categories';
    $sql_group_cats = "CREATE TABLE IF NOT EXISTS $table_group_cats (
      id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      group_id BIGINT(20) UNSIGNED NOT NULL,
      category_id BIGINT(20) UNSIGNED NOT NULL,
      PRIMARY KEY (id)
    ) $charset_collate;";

    // Run dbDelta on each table creation
    dbDelta($sql_groups);
    dbDelta($sql_user_groups);
    dbDelta($sql_group_cats);
}
