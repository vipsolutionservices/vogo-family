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

function filter_product_categories_by_city_address() {
    global $wpdb;

    // Retrieve the selected city from cookie (session removed for better compatibility)
  // $city = isset($_COOKIE['selected_city']) ? sanitize_text_field($_COOKIE['selected_city']) : '';
    $city = isset($_COOKIE['selected_city']) ? urldecode(sanitize_text_field($_COOKIE['selected_city'])) : '';

    if (empty($city)) {
        echo 'Niciun oraș selectat.';
        wp_die();
    }

    // Retrieve the user's selected address from user meta
    $user_id = get_current_user_id();
    $address_id = isset($_GET['address']) ? intval($_GET['address']) : 0;

    // Fetch user addresses from meta and unserialize them
    $addresses = maybe_unserialize(get_user_meta($user_id, '_user_addresses', true));

    // Ensure addresses is an array
    if (!is_array($addresses)) {
        $addresses = [];
    }

    // Find the matching address based on ID and city
    $address_details = null;
    foreach ($addresses as $address) {
        if (isset($address['address']) && isset($address['city']) && strtolower($address['city']) === strtolower($city)) {
            if ($address_id == 0 || $address['address'] == $_GET['address']) { // Match address if ID is provided
                $address_details = $address['address'];
                break;
            }
        }
    }

    if (!$address_details) {
        echo 'Nu a fost găsită nicio adresă validă pentru acest oraș nici ea.';
        wp_die();
    }

    // Fetch mall locations in the selected city
    $mall_locations = $wpdb->get_col($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}mall_locations WHERE city = %s AND status = 'active'", 
        $city
    ));

    if (!$mall_locations) {
        echo 'Nu s-au găsit locații de mall pentru acest oraș.';
        wp_die();
    }

    // Convert mall location IDs into a comma-separated list
    $mall_location_ids = implode(",", array_map('intval', $mall_locations));

    // Query product categories linked to these mall locations
    $categories = $wpdb->get_results("
        SELECT t.term_id, t.name AS category_name, t.slug
        FROM {$wpdb->prefix}terms t
        INNER JOIN {$wpdb->prefix}term_taxonomy tt ON tt.term_id = t.term_id
        INNER JOIN {$wpdb->prefix}termmeta tm ON tm.term_id = t.term_id
        WHERE tt.taxonomy = 'product_cat' 
        AND tm.meta_key = 'mall_location'
        AND tm.meta_value IN ({$mall_location_ids})
    ");

    // Output categories in Elementor grid format
    if ($categories) {
        echo '<div class="wd-row category-grid">'; // Container for the grid
        foreach ($categories as $category) {
            // Get category thumbnail (if available)
            $thumbnail_id = get_term_meta($category->term_id, 'thumbnail_id', true);
            $thumbnail_url = wp_get_attachment_url($thumbnail_id);
            $term_id = (int) $category->term_id; // Cast to integer
            $term_link = get_term_link($term_id, 'product_cat');
        //    $term_link = get_term_link($category->term_id);

            // Handle errors in term link
            if (is_wp_error($term_link)) {
// Output the WP_Error object (for debugging)
                $term_link = '#'; // Fallback to # if necessary
            }

            echo '<div class="wd-col category-grid-item wd-cat cat-design-alt categories-with-shadow without-product-count wd-with-subcat product-category product first" data-loop="1">';
            echo '<div class="wd-cat-wrap">';
            echo '<div class="wrapp-category">';
            echo '<div class="category-image-wrapp">';
            echo '<a href="' . esc_url($term_link) . '" class="category-image" aria-label="Category image">';

            // Display category image
            if ($thumbnail_url) {
                echo '<img loading="lazy" decoding="async" width="500" height="500" src="' . esc_url($thumbnail_url) . '" class="attachment-large size-large" alt="' . esc_attr($category->category_name) . '">';
            }

            echo '</a>';
            echo '</div>'; // Close category-image-wrapp

            // Hover mask section (for additional content like title, count, etc.)
            echo '<div class="hover-mask">';
            echo '<h3 class="wd-entities-title">' . esc_html($category->category_name) . '</h3>';
            echo '</div>';

            // Link for category page
            echo '<a href="' . esc_url($term_link) . '" class="category-link wd-fill" aria-label="Product category ' . esc_attr($category->category_name) . '"></a>';

            echo '</div>'; // Close .wrapp-category
            echo '</div>'; // Close .wd-cat-wrap
            echo '</div>'; // Close .wd-col
        }
        echo '</div>'; // Close .wd-row category-grid
    } else {
        echo 'Nu s-au găsit categorii de produse nici ele.';
    }

    wp_die(); // Properly terminate AJAX request
}

