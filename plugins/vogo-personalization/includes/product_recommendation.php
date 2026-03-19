<?php
// Ensure the file is not accessed directly
if (!defined('ABSPATH')) exit;

// Add the Product Recommendation menu to My Account
add_filter('woocommerce_account_menu_items', 'vogo_rec_add_product_recommendation_menu');
function vogo_rec_add_product_recommendation_menu($items) {
    // Add the menu item before Logout
    $logout = $items['customer-logout'];
    unset($items['customer-logout']);
    $items['product-recommendation'] = __('Recomandare de produs', 'vogo');
    $items['customer-logout'] = $logout;

    return $items;
}

// Register the Product Recommendation endpoint
add_action('init', 'vogo_rec_add_product_recommendation_endpoint');
function vogo_rec_add_product_recommendation_endpoint() {
    add_rewrite_endpoint('product-recommendation', EP_ROOT | EP_PAGES);
}

// Flush rewrite rules on activation
register_activation_hook(__FILE__, 'vogo_rec_flush_rewrite_rules');
function vogo_rec_flush_rewrite_rules() {
    vogo_rec_add_product_recommendation_endpoint();
    flush_rewrite_rules();
}

add_action('woocommerce_account_product-recommendation_endpoint', 'vogo_rec_display_recommended_products');

function enqueue_font_awesome() {
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css');
}
add_action('wp_enqueue_scripts', 'enqueue_font_awesome');
// function vogo_rec_display_recommended_products() {
//     if (!is_user_logged_in()) {
//         echo '<p>You need to be logged in to view this page.</p>';
//         return;
//     }

//     $user_id = get_current_user_id();

//     // Query the custom table
//     global $wpdb;
//     $table_name = $wpdb->prefix . 'product_recommendations';

//     // Get all product IDs recommended by this user
//     $results = $wpdb->get_results(
//         $wpdb->prepare(
//             "SELECT product_id 
//              FROM $table_name 
//              WHERE user_id = %d 
//              ORDER BY recommended_at DESC",
//             $user_id
//         )
//     );

//     if (!$results) {
//         echo '<p>You have not recommended any products yet.</p>';
//         return;
//     }

//     // Display the recommended products
//     echo '<h3>Product and Services Recommended by me</h3>';
//     echo '<div class="recommended-products-container">';

//     foreach ($results as $row) {
//         $product_id = $row->product_id;
//         $product = wc_get_product($product_id);

//         // Make sure the product still exists
//         if (!$product) {
//             continue;
//         }

//         // Display product info (title, link, price, etc.)
       
//         $product_id    = $product->get_id();
//         $product_link  = get_permalink($product_id);
//         $product_title = $product->get_name();
//         $product_price = $product->get_price_html();
//         $product_image = $product->get_image('full');// For formatted price

//         echo '<div class="recommended-product-box" data-product-id="' . esc_attr($product_id) . '">';

//             // Product thumbnail
//             echo '<div class="product-image">' . $product_image . '</div>';

//             // Product title and link
//             echo '<h4 class="product-title">';
//                 echo '<a href="' . esc_url($product_link) . '">' . esc_html($product_title) . '</a>';
//             echo '</h4>';

//             // Product price
//             if ($product_price) {
//                 echo '<span class="product-price">' . $product_price . '</span>';
//             }

//             // "Remove" button
//             echo '<button class="button remove-recommended-product" data-product-id="' . esc_attr($product_id) . '">';
//                 echo __('X', 'vogo');
//             echo '</button>';

//         echo '</div>';
//     }

//   echo '</div>';
// }
function vogo_rec_display_recommended_products() {
    if (!is_user_logged_in()) {
        echo '<p>Trebuie să fii autentificat pentru a vizualiza această pagină.</p>';
        return;
    }

    $user_id = get_current_user_id();

    // Query the custom table
    global $wpdb;
    $table_name = $wpdb->prefix . 'product_recommendations';

    // Get all product IDs recommended by this user
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT product_id 
             FROM $table_name 
             WHERE user_id = %d 
             ORDER BY recommended_at DESC",
            $user_id
        )
    );

    if (!$results) {
        echo '<p>Nu ați recomandat încă niciun produs.</p>';
        return;
    }

    // Display the recommended products
    echo '<h3>Produse și servicii recomandate de mine</h3>';
    echo '<div class="recommended-products-container" style="display: flex; flex-wrap: wrap; gap: 20px;">';

    foreach ($results as $row) {
        $product_id = $row->product_id;
        $product = wc_get_product($product_id);

        // Make sure the product still exists
        if (!$product) {
            continue;
        }

        // Get product details
        $product_id    = $product->get_id();
        $product_link  = get_permalink($product_id);
        $product_title = $product->get_name();
        $product_price = $product->get_price_html();
        $product_image = $product->get_image('full'); // Product image

        echo '<div class="recommended-product-box" data-product-id="' . esc_attr($product_id) . '" style="position: relative; padding: 10px; border: 1px solid #ddd; text-align: center; width: 200px;">';

            // Product thumbnail
            echo '<div class="product-image">' . $product_image . '</div>';

            // Product title and link
            echo '<h4 class="product-title">';
                echo '<a href="' . esc_url($product_link) . '">' . esc_html($product_title) . '</a>';
            echo '</h4>';

            // Product price
            if ($product_price) {
                echo '<span class="product-price">' . $product_price . '</span>';
            }

            // "Remove" button (hidden until hover)
            echo '<button class="button remove-recommended-product" data-product-id="' . esc_attr($product_id) . '" title="Elimină din Recomandări">';
            echo '<span class="dashicons dashicons-no-alt"></span>';// Font Awesome "X" icon
            echo '</button>';

        echo '</div>';
    }

    echo '</div>';
}

add_action('wp_ajax_remove_recommended_product', 'vogo_rec_handle_remove_recommended_product');
function vogo_rec_handle_remove_recommended_product() {
    // Security check (nonce)
    check_ajax_referer('vogo_remove_product_nonce', 'nonce');

    // Ensure user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('Trebuie să fii autentificat.');
    }

    $user_id    = get_current_user_id();
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

    if ($product_id === 0) {
        wp_send_json_error('Invalid product.');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'product_recommendations';

    // Delete the product for this user
    $deleted = $wpdb->delete(
        $table_name,
        [
            'user_id'    => $user_id,
            'product_id' => $product_id
        ],
        ['%d', '%d']
    );

    if ($deleted !== false) {
        wp_send_json_success("Produs eliminat cu succes.");
    } else {
        wp_send_json_error("Produsul nu a fost eliminat.");
    }
}


// short code for the product recommendation

add_shortcode('referrer_recommendations', 'display_referrer_recommended_products');
/**
 * Shortcode to render recommended products from the user's referrer.
 *
 * 1. Makes sure the user is logged in.
 * 2. Gets the current user ID.
 * 3. Gets the referrer ID (adjust meta key as needed).
 * 4. Queries the wp_product_recommendations table for products recommended by that referrer.
 * 5. If no products were recommended by the referrer, returns nothing.
 * 6. Builds the output.
 * 7. Returns the generated HTML.
 *
 * @since 1.0.0
 */
function display_referrer_recommended_products($atts) {
    // Check if the user is logged in
    if (!is_user_logged_in()) {
        return '<p style="text-align:center"><strong><a href="/login" style="text-decoration:underline">Log in</a></strong> pentru a vedea recomandări conforme cu orasul si preferintele dumneavoastra.</p>';
    }

    // Get the current user's ID
    $current_user_id = get_current_user_id();

    // Get the referrer ID from user meta
    $referrer_id = get_user_meta($current_user_id, 'referred_by', true);

    // If no referrer exists, return a message
    if (empty($referrer_id)) {
        return '';
    }

    // Fetch recommended product IDs from the database
    global $wpdb;
    $table_name = $wpdb->prefix . 'product_recommendations';

    $recommended_products = $wpdb->get_col($wpdb->prepare(
        "SELECT product_id 
         FROM $table_name
         WHERE user_id = %d
         ORDER BY recommended_at DESC",
        $referrer_id
    ));

    // If no products are recommended, return a message
    if (empty($recommended_products)) {
        return '';
    }

    // Query WooCommerce products
    $args = [
        'post_type' => 'product',
        'post__in' => $recommended_products, // Include only recommended product IDs
        'orderby' => 'post__in', // Maintain the order of IDs
        'posts_per_page' => 6 , // Limit to 4 products (adjust as needed)
    ];

    $query = new WP_Query($args);

    // If no products are found, return a message
    if (!$query->have_posts()) {
        return '<p>Nu există recomandări disponibile în acest moment.</p>';
    }

    // Start output buffering
    ob_start();

    // Use WooCommerce's default product grid
    echo '<h1 class="recommended-products-title">' . esc_html__('Produse recomandate', 'your-textdomain') . '</h1>';
    echo '<ul class="recommended-products">'; // WooCommerce product list class
    while ($query->have_posts()) {
        $query->the_post();
        wc_get_template_part('content', 'product'); // Use WooCommerce product layout
    }
    echo '</ul>';

    // Reset the WordPress post data
    wp_reset_postdata();

    // Return the output
    return ob_get_clean();
}
// Inline style for visibility of the "remove" button on mobile
add_action('wp_footer', function () {
    ?>
    <style>
    .recommended-product-box .remove-recommended-product {
        display: none;
        position: absolute;
        top: 5px;
        right: 5px;
    }

    .recommended-product-box:hover .remove-recommended-product {
        display: inline-block;
    }

    @media (max-width: 767px) {
        .recommended-products-container {
            display: grid !important;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            justify-content: center;
        }

        .recommended-product-box {
            width: 100% !important;
        }
        .recommended-product-box .remove-recommended-product {
            display: inline-block;
            padding: 6px 10px;
            font-size: 16px;
        }
    }
    </style>
    <?php
});