<?php
function create_provider_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_providers = $wpdb->prefix . 'provider_feeds';
    $table_coefficients = $wpdb->prefix . 'provider_coefficients';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_providers'") != $table_providers) {
        $sql_providers = "CREATE TABLE $table_providers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provider_name VARCHAR(255) NOT NULL,
            feed_url TEXT NOT NULL,
            cron_schedule ENUM('daily', 'weekly', 'monthly', 'yearly') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_providers);
    }

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_coefficients'") != $table_coefficients) {
        $sql_coefficients = "CREATE TABLE $table_coefficients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provider_id INT NOT NULL,
            category_name VARCHAR(255) NOT NULL,
            coefficient DECIMAL(5,2) NOT NULL DEFAULT 1.0,
            FOREIGN KEY (provider_id) REFERENCES $table_providers(id) ON DELETE CASCADE
        ) $charset_collate;";
        dbDelta($sql_coefficients);
    }
}

function ensure_provider_category_slabs_table_exists() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'provider_category_slabs';
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE $table_name (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        provider_id BIGINT UNSIGNED NOT NULL,
        category_name VARCHAR(255) NOT NULL,
        min_price DECIMAL(10,2) NOT NULL,
        max_price DECIMAL(10,2) DEFAULT NULL,
        coefficient DECIMAL(10,2) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_provider_id (provider_id),
        KEY idx_category_name (category_name)
    ) $charset_collate;";
    
    dbDelta($sql);
}

// ✅ Hook that runs once when theme initializes
add_action('after_setup_theme', function () {
    create_provider_tables();
    ensure_provider_category_slabs_table_exists(); // Run this too
});


add_action('admin_menu', function () {
    add_submenu_page(
        'woocommerce',
        'Provider Logs',
        'Provider Logs',
        'manage_options',
        'provider-logs',
        'display_provider_logs_page'
    );
});

function display_provider_logs_page() {
    global $wpdb;

    $provider_id = isset($_GET['provider_id']) ? intval($_GET['provider_id']) : 0;

    echo '<div class="wrap">';
    echo '<h1 class="wp-heading-inline">Provider Logs</h1>';

    if (!$provider_id) {
        echo '<p>No provider selected.</p>';
        echo '</div>';
        return;
    }

    $provider = $wpdb->get_row(
        $wpdb->prepare("SELECT provider_name FROM {$wpdb->prefix}provider_feeds WHERE id = %d", $provider_id)
    );

    if (!$provider) {
        echo '<p>Provider not found.</p>';
        echo '</div>';
        return;
    }

    echo '<h2>Logs for Provider: ' . esc_html($provider->provider_name) . '</h2>';

    $log_file = WP_CONTENT_DIR . '/uploads/provider_logs/provider_' . $provider_id . '.log';

    if (file_exists($log_file)) {
        echo '<pre style="background:#f1f1f1; padding:10px; border:1px solid #ccc; max-height:600px; overflow:auto;">';
        echo esc_html(file_get_contents($log_file));
        echo '</pre>';
    } else {
        echo '<p>No logs found for this provider.</p>';
    }

    echo '</div>';
}