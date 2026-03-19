<?php 
// Register the custom Elementor widget
function register_city_address_widget($widgets_manager) {
    require_once(__DIR__ . '/elementor-widgets/elementor-city-address-widget.php'); // Include the widget class
    $widgets_manager->register(new \Elementor_City_Address_Widget());
}
add_action('elementor/widgets/register', 'register_city_address_widget');

// AJAX action for filtering product categories
add_action('wp_ajax_filter_categories', 'filter_product_categories_by_city_address');
add_action('wp_ajax_nopriv_filter_categories', 'filter_product_categories_by_city_address');

// AJAX: return categories available for the selected city
function filter_product_categories_by_city_address() {
    global $wpdb;

    // -- Read selected city from cookie
    $city = '';
    if (isset($_COOKIE['selected_city'])) {
        $city = trim(sanitize_text_field(wp_unslash($_COOKIE['selected_city'])));
    }

    if ($city === '') {
        wp_die('<p>No city selected.</p>');
    }

    // -- (Optional) read user's address list (kept for compatibility, not echoed)
    $user_id  = get_current_user_id();
    $addresses = maybe_unserialize(get_user_meta($user_id, '_user_addresses', true));
    if (!is_array($addresses)) {
        $addresses = [];
    }

    // -- Match serialized arrays: meta_value LIKE "%CityName%"
    $like = '%' . $wpdb->esc_like($city) . '%';

    $sql = $wpdb->prepare(
        "
        SELECT DISTINCT t.term_id, t.name AS category_name, t.slug
        FROM {$wpdb->terms} t
        INNER JOIN {$wpdb->term_taxonomy} tt
            ON tt.term_id = t.term_id AND tt.taxonomy = 'product_cat'
        INNER JOIN {$wpdb->termmeta} tm
            ON tm.term_id = t.term_id
        WHERE
            (tm.meta_key = 'product_category_cities' AND tm.meta_value LIKE %s)
            OR
            (tm.meta_key = 'mall_location'          AND tm.meta_value LIKE %s)
        ORDER BY t.name ASC
        ",
        $like, $like
    );

    $categories = $wpdb->get_results($sql);

    // -- Render grid
    ob_start();

    if ($categories) {
        echo '<div class="wd-row category-grid">';
        foreach ($categories as $category) {
            $term_id   = (int) $category->term_id;
            $term_link = get_term_link($term_id, 'product_cat');
            if (is_wp_error($term_link)) {
                $term_link = '#';
            }

            $thumb_id  = (int) get_term_meta($term_id, 'thumbnail_id', true);
            $thumb_url = $thumb_id ? wp_get_attachment_url($thumb_id) : '';

            echo '<div class="wd-col category-grid-item wd-cat cat-design-alt categories-with-shadow without-product-count wd-with-subcat product-category">';
            echo   '<div class="wd-cat-wrap">';
            echo     '<div class="wrapp-category">';
            echo       '<div class="category-image-wrapp">';
            echo         '<a href="' . esc_url($term_link) . '" class="category-image" aria-label="' . esc_attr($category->category_name) . '">';
            if ($thumb_url) {
                echo       '<img loading="lazy" decoding="async" width="500" height="500" src="' . esc_url($thumb_url) . '" class="attachment-large size-large" alt="' . esc_attr($category->category_name) . '">';
            }
            echo         '</a>';
            echo       '</div>';
            echo       '<div class="hover-mask"><h3 class="wd-entities-title">' . esc_html($category->category_name) . '</h3></div>';
            echo       '<a href="' . esc_url($term_link) . '" class="category-link wd-fill" aria-label="' . esc_attr($category->category_name) . '"></a>';
            echo     '</div>';
            echo   '</div>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p>No categories for this city.</p>';
    }

    wp_die(ob_get_clean());
}


