<?php
function create_mall_delivery_tables_on_theme_activation() {
    create_mall_delivery_tables();
}
add_action('after_switch_theme', 'create_mall_delivery_tables_on_theme_activation');

function create_mall_delivery_tables() {
    global $wpdb;

    // Define the table names
    $user_addresses_table = $wpdb->prefix . 'user_addresses';
    $mall_locations_table = $wpdb->prefix . 'mall_locations';
    $product_delivery_locations_table = $wpdb->prefix . 'product_delivery_locations';
    $category_delivery_locations_table = $wpdb->prefix . 'category_delivery_locations';

    // Define the SQL for creating tables using dbDelta
    $sql = "
   CREATE TABLE $user_addresses_table (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        street_address TEXT NOT NULL,
        city VARCHAR(255) NOT NULL,
        address_code VARCHAR(5) NOT NULL,
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'inactive'
    ) {$wpdb->get_charset_collate()};;

    CREATE TABLE $mall_locations_table (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        mall_name VARCHAR(255) NOT NULL,
        city VARCHAR(255) NOT NULL,
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'active'
    ) {$wpdb->get_charset_collate()};

    CREATE TABLE $product_delivery_locations_table (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        product_id BIGINT UNSIGNED NOT NULL,
        location VARCHAR(255) NOT NULL
    ) {$wpdb->get_charset_collate()};

    CREATE TABLE $category_delivery_locations_table (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        category_id BIGINT UNSIGNED NOT NULL,
        location VARCHAR(255) NOT NULL
    ) {$wpdb->get_charset_collate()};
    ";

    // Use dbDelta to create/update tables
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta($sql);
}

add_action('add_meta_boxes', function () {
    add_meta_box('product_city_meta', 'Available Cities', 'render_product_city_meta', 'product', 'side');
    add_meta_box('product_mall_meta', 'Available Mall Locations', 'render_product_mall_meta', 'product', 'side');
});

// function render_product_city_meta($post) {
//     $saved = get_post_meta($post->ID, '_available_cities', true);
//     $saved = is_array($saved) ? $saved : [];

//     $cities = get_cities_list(); // From your existing cities.json logic

//     echo '<select multiple name="available_cities[]" style="width:100%;height:150px;">';
//     foreach ($cities as $city) {
//         $selected = in_array($city, $saved) ? 'selected' : '';
//         echo "<option value='$city' $selected>$city</option>";
//     }
//     echo '</select>';
// }

function render_product_city_meta($post) {
    $saved = get_post_meta($post->ID, '_available_cities', true);
    $saved = is_array($saved) ? $saved : [];

    $cities = get_cities_list(); // Assuming this returns an array of city names

    // Add buttons and select field
    echo '<div style="margin-bottom:5px;">
        <button type="button" onclick="selectAllCities()" style="margin-right:5px;">Select All</button>
        <button type="button" onclick="deselectAllCities()">Deselect All</button>
    </div>';

    echo '<select multiple name="available_cities[]" style="width:100%;height:150px;">';
    foreach ($cities as $city) {
        $selected = in_array($city, $saved) ? 'selected' : '';
        echo "<option value='" . esc_attr($city) . "' $selected>" . esc_html($city) . "</option>";
    }
    echo '</select>';

    // Include JS for buttons
    echo '<script>
    function selectAllCities() {
        const select = document.querySelector("select[name=\'available_cities[]\']");
        if (select) {
            for (let option of select.options) {
                option.selected = true;
            }
        }
    }

    function deselectAllCities() {
        const select = document.querySelector("select[name=\'available_cities[]\']");
        if (select) {
            for (let option of select.options) {
                option.selected = false;
            }
        }
    }
    </script>';
}
function render_product_mall_meta($post) {
    global $wpdb;
    $locations = $wpdb->get_results("SELECT id, mall_name FROM {$wpdb->prefix}mall_locations WHERE status = 'active'");
    $saved = get_post_meta($post->ID, '_available_malls', true);
    $saved = is_array($saved) ? $saved : [];

    // Add buttons
    echo '<div style="margin-bottom:5px;">
        <button type="button" onclick="selectAllMalls()" style="margin-right:5px;">Select All</button>
        <button type="button" onclick="deselectAllMalls()">Deselect All</button>
    </div>';

    // Select field
    echo '<select multiple name="available_malls[]" id="available_malls_select" style="width:100%;height:150px;">';
    foreach ($locations as $location) {
        $selected = in_array($location->id, $saved) ? 'selected' : '';
        echo "<option value='" . esc_attr($location->id) . "' $selected>" . esc_html($location->mall_name) . "</option>";
    }
    echo '</select>';

    // JS functions
    echo '<script>
    function selectAllMalls() {
        const select = document.querySelector("select[name=\'available_malls[]\']");
        if (select) {
            for (let option of select.options) {
                option.selected = true;
            }
        }
    }

    function deselectAllMalls() {
        const select = document.querySelector("select[name=\'available_malls[]\']");
        if (select) {
            for (let option of select.options) {
                option.selected = false;
            }
        }
    }
    </script>';
}

add_action('save_post_product', function ($post_id) {
    if (isset($_POST['available_cities'])) {
        update_post_meta($post_id, '_available_cities', array_map('sanitize_text_field', $_POST['available_cities']));
    } else {
        delete_post_meta($post_id, '_available_cities');
    }

    if (isset($_POST['available_malls'])) {
        update_post_meta($post_id, '_available_malls', array_map('intval', $_POST['available_malls']));
    } else {
        delete_post_meta($post_id, '_available_malls');
    }
});

add_filter('posts_where', function($where, $query) {
    if (!is_admin() && $query->is_main_query() && is_product_category()) {
        $city = isset($_COOKIE['selected_city']) ? sanitize_text_field($_COOKIE['selected_city']) : '';

        global $wpdb;
        if (!empty($city)) {
            $where .= " AND (NOT EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm
                WHERE pm.post_id = {$wpdb->posts}.ID
                AND pm.meta_key = '_available_cities'
            ) OR EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm
                WHERE pm.post_id = {$wpdb->posts}.ID
                AND pm.meta_key = '_available_cities'
                AND pm.meta_value LIKE '%{$city}%'
            ))";
        }
    }
    return $where;
}, 10, 2);