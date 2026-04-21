<?php
include('mobile.php');
include('api-functions.php');
include('custom-api-function.php');
include('seo.php'); //utilitare pentru optimizarea SEO vogo.family
include('perf.php'); //utilitare pentru optimizarea performantelor woo vogo.family
include('audit.php'); //informatii de audit pentru AI si configurations management
// https://www.vogo.family/wp-admin/?vogo_plugin_audit=1
// https://www.vogo.family/wp-admin/admin.php?page=wc-status
include('vogo-lang.php'); //deprecated - era utilizat inainte de GTranslate enterprise
require_once get_stylesheet_directory() . '/inc/utils/utils.php';

//CART - SHIPPING ADDRESS

/**
 * CART: capture & save extra fields from Change address
 * - Saves to user_meta (shipping_* + billing_phone/email + shipping_street_number)
 * - Mirrors into WC()->customer + session
 * - Recalculates totals
 * - (Optional) Upserts a row in wp_user_addresses named "Cart shipping"
 */
add_action('wp_loaded', function () {
    if ( empty($_POST['calc_shipping']) || empty($_POST['vogo_cart_extra_fields']) ) return;

    // Nonce (folosim nonce-ul Woo)
    if ( isset($_POST['woocommerce-shipping-calculator-nonce']) &&
         ! wp_verify_nonce($_POST['woocommerce-shipping-calculator-nonce'], 'woocommerce-shipping-calculator') ) {
        return;
    }

    $uid      = get_current_user_id() ?: 0;
    $get      = fn($k) => isset($_POST[$k]) ? sanitize_text_field( wp_unslash($_POST[$k]) ) : '';
    $country  = $get('calc_shipping_country');
    $state    = $get('calc_shipping_state');
    $city     = $get('calc_shipping_city');
    $postcode = $get('calc_shipping_postcode');
    $addr1    = $get('calc_shipping_address_1');
    $addr2    = $get('calc_shipping_address_2');
    $streetno = $get('calc_shipping_street_number');
    $phone    = $get('calc_contact_phone');
    $email    = sanitize_email( $get('calc_contact_email') );

    // Persist in user_meta
    if ($uid) {
        if ($country  !== '') update_user_meta($uid, 'shipping_country',  $country);
        if ($state    !== '') update_user_meta($uid, 'shipping_state',    $state);
        if ($city     !== '') update_user_meta($uid, 'shipping_city',     $city);
        if ($postcode !== '') update_user_meta($uid, 'shipping_postcode', $postcode);
        if ($addr1    !== '') update_user_meta($uid, 'shipping_address_1',$addr1);
        if ($addr2    !== '') update_user_meta($uid, 'shipping_address_2',$addr2);
        if ($streetno !== '') update_user_meta($uid, 'shipping_street_number', $streetno); // NEW
        if ($phone    !== '') update_user_meta($uid, 'billing_phone', $phone);             // contact
        if ($email    !== '') update_user_meta($uid, 'billing_email', $email);
    }

    // Mirror in WC()->customer + session + recalc
    if ( function_exists('WC') && WC()->customer ) {
        $c = WC()->customer;
        if ($country  !== '') $c->set_shipping_country($country);
        if ($state    !== '') $c->set_shipping_state($state);
        if ($city     !== '') $c->set_shipping_city($city);
        if ($postcode !== '') $c->set_shipping_postcode($postcode);
        if ($addr1    !== '') $c->set_shipping_address_1($addr1);
        if ($addr2    !== '') $c->set_shipping_address_2($addr2);
        if ($phone    !== '') $c->set_billing_phone($phone);
        if ($email    !== '') $c->set_billing_email($email);
        $c->save();

        if ( WC()->session ) {
            if ($streetno !== '') WC()->session->set('vogo_shipping_street_number', $streetno);
            if ($phone    !== '') WC()->session->set('vogo_contact_phone', $phone);
            if ($email    !== '') WC()->session->set('vogo_contact_email', $email);
        }

        if ( WC()->cart ) WC()->cart->calculate_totals();
    }

    // OPTIONAL: sync in wp_user_addresses (dacă tabela există)
    global $wpdb; $table = $wpdb->prefix.'user_addresses';
    $exists_table = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=%s", $table
    ));
    if ($exists_table) {
        $has_fields = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=%s AND column_name='fields'", $table
        ));
        $fields_json = null;
        if ($has_fields) {
            $extras = array_filter([
                'street_number' => $streetno,
                'contact_phone' => $phone,
                'contact_email' => $email,
            ], fn($v)=>$v!=='');
            if (!empty($extras)) $fields_json = wp_json_encode($extras);
        }

        $row = [
            'user_id'          => (int)$uid,
            'address_name'     => 'Cart shipping',
            'street_address'   => $addr1,
            'street_address_2' => $addr2,
            'city'             => $city,
            'county'           => $state,
            'address_code'     => $postcode,
            'status'           => 'active',
        ];
        if ($fields_json !== null) $row['fields'] = $fields_json;

        $existing_id = $uid ? (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE user_id=%d AND LOWER(TRIM(address_name))=LOWER(TRIM(%s)) LIMIT 1",
            $uid, 'Cart shipping'
        )) : 0;

        if ($existing_id > 0) {
            $wpdb->update($table, array_filter($row, fn($v)=>$v!=='' && $v!==null), ['id'=>$existing_id]);
        } else {
            $wpdb->insert($table, $row);
        }
    }
}, 20);

// Add shipping street number field at checkout (UI)
add_filter('woocommerce_checkout_fields', function($fields){
    $prefill = WC()->session ? WC()->session->get('vogo_shipping_street_number') : get_user_meta(get_current_user_id(),'shipping_street_number',true);
    $fields['shipping']['shipping_street_number'] = [
        'type'     => 'text',
        'label'    => __('Street number','woocommerce'),
        'required' => false,
        'priority' => 62,
        'class'    => ['form-row-first'],
        'default'  => $prefill,
    ];
    return $fields;
});

// Save into order meta
add_action('woocommerce_checkout_create_order', function($order, $data){
    $no = $data['shipping_street_number'] ?? (WC()->session ? WC()->session->get('vogo_shipping_street_number') : '');
    if ($no) $order->update_meta_data('_shipping_street_number', sanitize_text_field($no));
}, 10, 2);

// Append number to formatted shipping address (emails, thank-you, etc.)
add_filter('woocommerce_order_formatted_shipping_address', function($address, $order){
    $no = $order->get_meta('_shipping_street_number');
    if ($no && !empty($address['address_1'])) $address['address_1'] .= ' '.$no;
    return $address;
}, 10, 2);


//END CART - SHIPPING ADDRESS

add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});
/**
 * Enqueue script and styles for child theme
 */

// 🔑 Lower minimum password length for testing
add_filter('woocommerce_min_password_strength', function($strength) {
    return 0; // 0 = No enforced strength
});

add_filter('woocommerce_min_password_length', function($length) {
    return 5; // ✅ set minimum length to 5 characters
});

 /**
  * Child Theme Functions
  */
  if (!is_admin()) {
    ob_start(); // Start output buffering to catch all early output
}
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
  
  require_once ABSPATH . "vendor/autoload.php"; 

  use PhpOffice\PhpSpreadsheet\IOFactory; // ✅ Move this to the top!

 // 1. Load our custom code
 require_once get_stylesheet_directory() . '/inc/product/edit_product_meta_action.php'; 
 require_once get_stylesheet_directory(). '/inc/providers/multivendor.php';
 require_once get_stylesheet_directory() . '/inc/groups/create-tables.php';
 require_once get_stylesheet_directory() . '/inc/groups/admin-menu.php';
 require_once get_stylesheet_directory() . '/inc/groups/tabs.php';
 require_once get_stylesheet_directory() . '/inc/groups/shortcodes.php';
 require_once get_stylesheet_directory() . '/register-shortcode.php';
 require_once get_stylesheet_directory() . '/login-shortcode.php';
 require_once get_stylesheet_directory() . '/inc/essentials/enqueues.php';
 require_once get_stylesheet_directory() . '/inc/essentials/shortcodes.php';
 require_once get_stylesheet_directory() . '/inc/essentials/user-roles.php';
 require_once get_stylesheet_directory() . '/inc/essentials/forum.php';
require_once get_stylesheet_directory() . '/inc/subscription/create-subscription.php';
// require_once get_stylesheet_directory() . '/inc/subscription/subscription-payments.php';
require_once get_stylesheet_directory() . '/inc/locations/mall-delivery-setup.php';
require_once get_stylesheet_directory() . '/inc/locations/mall-delivery-admin.php';
require_once get_stylesheet_directory() . '/inc/locations/mall-delivery-frontend.php';
require_once get_stylesheet_directory() . '/inc/locations/elementor-city-address-widget.php';
require_once get_stylesheet_directory() . '/inc/essentials/products.php';
require_once get_stylesheet_directory() . '/inc/locations/city-location-shortcode.php';
require_once get_stylesheet_directory() . '/inc/locations/city-category-shortcode.php';
require_once get_stylesheet_directory() . '/inc/locations/vogo-mobile-categories-shortcode.php';
require_once get_stylesheet_directory() . '/inc/shipping/saved-shipping-address.php';
require_once get_stylesheet_directory(). '/inc/providers/init.php';
require_once get_stylesheet_directory() . '/inc/review-orders/init.php';
require_once get_stylesheet_directory() . '/inc/my-accounts-menus/menus.php';
require_once get_stylesheet_directory() . '/inc/my-articles/my-articles.php';
require_once get_stylesheet_directory() . '/inc/translations/translations.php';
require_once get_stylesheet_directory() . '/inc/integrations/woocommerce/template-tags.php';
require get_stylesheet_directory() . '/admin-links.php';
require get_stylesheet_directory() . '/functions2.php';
require get_stylesheet_directory() . '/custom-functions.php';

//add_action( 'template_redirect', 'force_custom_lost_password_template_properly' );
function force_custom_lost_password_template_properly() {
    if ( is_wc_endpoint_url( 'lost-password' ) ) {
        // Prevent other content from loading
        remove_all_actions('the_content');

        add_filter('the_content', function() {
            ob_start();
            wc_get_template( 'myaccount/form-lost-password.php' );
            return ob_get_clean();
        });

        // Load the normal page template (with header/footer)
        add_filter('template_include', function($template) {
            return get_page_template(); // usually page.php or your custom page template
        });
    }
}

add_action('admin_menu', function () {
    add_menu_page(
        'Important Links',
        'Important Links',
        'manage_options',
        'vogo-important-links',
        function () {
            require get_stylesheet_directory() . '/important-links.php';
        },
        'dashicons-admin-generic',
        3 // Position near the top
    );
});

function enqueue_tailwind_admin_css() {
    if (isset($_GET['page']) && in_array($_GET['page'], ['provider-management', 'provider-settings', 'provider-coefficients', 'edit-provider', 'provider-wizard', 'test-provider-prices','mp-product-importer','mp-import-progress'],)) {
        wp_enqueue_style('tailwindcss', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');
    }
}
add_action('admin_enqueue_scripts', 'enqueue_tailwind_admin_css');

//Custom css
function custom_enqueue_styles() {
    //Custom css
    wp_enqueue_style( 'custom-css', get_stylesheet_directory_uri() .'/css/custom.css', array(), 200 );
}
add_action( 'wp_enqueue_scripts', 'custom_enqueue_styles' );

add_action('admin_head', 'my_custom_fonts'); // admin_head is a hook my_custom_fonts is a function we are adding it to the hook

function my_custom_fonts() {
  echo '<style>

  iframe#description_ifr {
    height: 150px !important;
}
  span#select2-_customer_user-5k-container {
    width: 200px !important;
}
   .select2-container .select2-search--inline {
    float: none !important;
}
    .pagination-container {
    text-align: center;
    margin-top: 20px;
}
.select2-selection__rendered {
            max-width: 200px !important;
            width: 200px !important;
        }
.select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #444;
    line-height: 28px;
    width: 200px !important;
}
.pagination-container ul {
    display: inline-flex;
    padding: 0;
    margin: 0;
    list-style: none;
}

.pagination-container ul li {
    margin: 0 5px;
}

.pagination-container ul li a,
.pagination-container ul li span {
    display: inline-block;
    padding: 8px 12px;
    text-decoration: none;
    background-color: #f7f7f7;
    color: #007cba;
    border: 1px solid #ddd;
    border-radius: 4px;
    transition: all 0.3s ease;
}

.pagination-container ul li a:hover,
.pagination-container ul li span:hover {
    background-color: #007cba;
    color: #fff;
    border-color: #007cba;
}

.pagination-container ul li .current {
    background-color: #007cba;
    color: #fff;
    border-color: #007cba;
    font-weight: bold;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #444;
    line-height: 28px;
    width: 400px !important;
}
span.select2-dropdown.select2-dropdown--above {
    padding-left: 7px;
}
.select2-results {
    padding-left: 7px;
}
.ms-drop input[type="radio"], .ms-drop input[type="checkbox"] {
    position: absolute;
    margin-top: .1rem !important;
    margin-left: -1.20rem !important;
}
  </style>';
}
function woodmart_child_enqueue_styles() {
    wp_enqueue_style( 'child-style', get_stylesheet_directory_uri() . '/style.css', array( 'woodmart-style' ), woodmart_get_theme_info( 'Version' ) );
}
add_action( 'wp_enqueue_scripts', 'woodmart_child_enqueue_styles', 10010 );

// Brand 

function create_brand_taxonomy() {
    register_taxonomy(
        'product_brand',
        'product',
        array(
            'label' => __( 'Brand' ),
            'rewrite' => array( 'slug' => 'brand' ),
            'hierarchical' => false,
            'show_admin_column' => true,
        )
    );
}
add_action( 'init', 'create_brand_taxonomy' );

// Show Brand on product page

add_action( 'woocommerce_single_product_summary', 'display_product_brand', 25 );

function display_product_brand() {
    global $post;

    // Get the terms for the 'product_brand' taxonomy
    $terms = get_the_terms( $post->ID, 'product_brand' );

    if ( $terms && ! is_wp_error( $terms ) ) {
        echo '<p class="product-brand"><strong>Brand:</strong> ';
        foreach ( $terms as $term ) {
            echo esc_html( $term->name );
        }
        echo '</p>';
    }
}


// // acquired price

// Add AQ Price field in the WooCommerce product edit page
add_action( 'woocommerce_product_options_general_product_data', 'add_acquired_price_field' );

function add_acquired_price_field() {
    woocommerce_wp_text_input( array(
        'id' => 'acquired_price',
        'label' => __( 'Acquired Price', 'woocommerce' ),
        'desc_tip' => true,
        'description' => __( 'Enter the acquired price for this product.', 'woocommerce' ),
        'type' => 'number',
        'custom_attributes' => array(
            'step' => 'any',
            'min' => '0'
        ),
    ) );
}

// // Save AQ Price field value
add_action( 'woocommerce_process_product_meta', 'save_acquired_price_field' );

function save_acquired_price_field( $post_id ) {
    $acquired_price = isset( $_POST['acquired_price'] ) ? sanitize_text_field( $_POST['acquired_price'] ) : '';
    update_post_meta( $post_id, 'acquired_price', $acquired_price );
}


// Add a new column for AQ Price in the product list
add_filter( 'manage_edit-product_columns', 'add_acquired_price_column' );
add_action( 'manage_product_posts_custom_column', 'render_acquired_price_column', 10, 2 );

function add_acquired_price_column( $columns ) {
    $columns['acquired_price'] = __( 'Acquired Price', 'woocommerce' );
    return $columns;
}

function render_acquired_price_column( $column, $post_id ) {
    if ( 'acquired_price' === $column ) {
        $acquired_price = get_post_meta( $post_id, 'acquired_price', true );
        echo wc_price( $acquired_price );
    }
}

// Calculate profit margin in admin
add_action( 'woocommerce_product_options_general_product_data', 'add_profit_margin_display' );

function add_profit_margin_display() {
    global $post;

    $price = get_post_meta( $post->ID, '_price', true );
    $acquired_price = get_post_meta( $post->ID, 'acquired_price', true );

    if ( $price && $acquired_price ) {
        $profit = $price - $acquired_price;
        echo '<p><strong>Profit Margin:</strong> ' . wc_price( $profit ) . '</p>';
    }
}

add_action('woocommerce_after_edit_account_address_form', function() {
    //echo '<p><a href="/my-account/my_addresses/" class="btn">Other Shipping Address</a></p>';
});


// add buttons on the side of the website
function add_fixed_buttons_to_footer() {
    // Get the base URL for uploaded files
    $upload_dir = wp_get_upload_dir();
    $base_url = $upload_dir['baseurl']; 
// Add filters for Payment Mode, Coupon, Product Tags, and AWB on the Orders admin page
add_action('restrict_manage_posts', function () {
    global $typenow;

    if ('shop_order' === $typenow) {
        // Payment Methods Filter
        $gateways = WC()->payment_gateways->payment_gateways();
        $selected_gateway = isset($_GET['filter_payment_method']) ? $_GET['filter_payment_method'] : '';
        echo '<select name="filter_payment_method">';
        echo '<option value="">All Payment Methods</option>';
        foreach ($gateways as $gateway) {
            echo '<option value="' . esc_attr($gateway->id) . '" ' . selected($selected_gateway, $gateway->id, false) . '>' . esc_html($gateway->title) . '</option>';
        }
        echo '</select>';

        // Coupon Filter
        $coupons = get_posts(['post_type' => 'shop_coupon', 'posts_per_page' => -1]);
        $selected_coupon = isset($_GET['filter_coupon']) ? $_GET['filter_coupon'] : '';
        echo '<select name="filter_coupon">';
        echo '<option value="">All Coupons</option>';
        foreach ($coupons as $coupon) {
            echo '<option value="' . esc_attr($coupon->post_title) . '" ' . selected($selected_coupon, $coupon->post_title, false) . '>' . esc_html($coupon->post_title) . '</option>';
        }
        echo '</select>';

        // Product Tag Filter
        $tags = get_terms(['taxonomy' => 'product_tag', 'hide_empty' => false]);
        $selected_tag = isset($_GET['filter_product_tag']) ? $_GET['filter_product_tag'] : '';
        echo '<select name="filter_product_tag">';
        echo '<option value="">All Product Tags</option>';
        foreach ($tags as $tag) {
            echo '<option value="' . esc_attr($tag->slug) . '" ' . selected($selected_tag, $tag->slug, false) . '>' . esc_html($tag->name) . '</option>';
        }
        echo '</select>';

        // Tracking / AWB Filter
        $awb_value = isset($_GET['filter_awb']) ? esc_attr($_GET['filter_awb']) : '';
        echo '<input type="text" name="filter_awb" placeholder="AWB Number" value="' . $awb_value . '" />';
    }
});

add_action('pre_get_posts', function ($query) {
    global $pagenow, $typenow;

    if (is_admin() && 'shop_order' === $typenow && 'edit.php' === $pagenow && $query->is_main_query()) {
        if (!empty($_GET['filter_payment_method'])) {
            $query->set('meta_query', [
                [
                    'key' => '_payment_method',
                    'value' => sanitize_text_field($_GET['filter_payment_method']),
                    'compare' => '=',
                ],
            ]);
        }

        if (!empty($_GET['filter_coupon'])) {
            $query->set('meta_query', [
                [
                    'key' => '_used_coupons',
                    'value' => sanitize_text_field($_GET['filter_coupon']),
                    'compare' => 'LIKE',
                ],
            ]);
        }

        if (!empty($_GET['filter_awb'])) {
            $query->set('meta_query', [
                [
                    'key' => '_sameday_awb',
                    'value' => sanitize_text_field($_GET['filter_awb']),
                    'compare' => 'LIKE',
                ],
            ]);
        }

        if (!empty($_GET['filter_product_tag'])) {
            $tag_slug = sanitize_text_field($_GET['filter_product_tag']);
            $query->set('meta_query', [
                [
                    'key' => '_product_tags',
                    'value' => $tag_slug,
                    'compare' => 'LIKE',
                ],
            ]);
        }
    }
});
?>
    <div class="vogo-fixed-icons-box">
        <a href="https://wa.me/400742203383" class="vogo-icon-link" target="_blank">
            <img src="<?php echo esc_url($base_url . '/2025/01/ql-contact.png'); ?>" class="vogo-fix-icon">
        </a>
        <a href="/my-account" class="vogo-icon-link">
            <img src="<?php echo esc_url($base_url . '/2025/01/ql-locate.png'); ?>" class="vogo-fix-icon">
        </a>
        <a href="/recommend-new-service" class="vogo-icon-link">
            <img src="<?php echo esc_url($base_url . '/2025/01/ql-survey.png'); ?>" class="vogo-fix-icon">
        </a>
    </div>
    <style>
        /* Only apply styles on desktop devices */

        @media screen and (min-width: 769px) {
            .vogo-fixed-icons-box {
                position: fixed;
            
                right: 10px;
                top: 50%;
                transform: translateY(-50%);
                display: flex;
                flex-direction: column;
                gap: 0px;
                z-index: 99999;
            }

            .vogo-fixed-icons-box .vogo-icon-link img {
                width: 88px; /* Adjust size as needed */
                height: 88px;
                /*padding:4px;*/
				/*border: 1px solid;*/
                background:white;
                /* Optional: Circular icons */
                /* Optional: Add shadow 
            }
        }

        /* Hide the icons on mobile */
        @media screen and (max-width: 768px) {
            .vogo-fixed-icons-box {
                display: none;
            }
        }

    </style>
<?php }
//add_action('wp_footer', 'add_fixed_buttons_to_footer');

add_filter('woocommerce_product_single_add_to_cart_text', 'custom_add_to_cart_text'); // For single product pages
add_filter('woocommerce_product_add_to_cart_text', 'custom_add_to_cart_text'); // For archives and shop pages

function custom_add_to_cart_text() {
    return '&#xf123;'; // Replace with correct Unicode
}

function modify_search_placeholder() {
    // Check if the current page is a WooCommerce product detail page
    if (is_product()) {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.querySelector('input[placeholder="Search for products"]');
            if (searchInput) {
                searchInput.setAttribute('placeholder', "What are you looking for?");
            }
        });
        </script>
        <?php
    } else {
        // Add the typing effect only on non-product pages
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const lines = [
                "What are you looking for?",
                "Cu ce te putem ajuta astazi?",
                "Qu'est-ce que tu cherches?",
                "Wonach suchst du?",
                "Mit keresel ma?",
                "Cosa stai cercando oggi?",
                "Какво търсиш днес?",
                "Bugün ne arıyorsunuz?",
                "Τι ψάχνεις σήμερα?",
				"How can we assist you today?",
				"Ce produs sau serviciu cauti?"
            ];

            function typeEffect(searchInput) {
                if (!searchInput.id) {
                    const uniqueId = `typed-input-${Math.random().toString(36).substr(2, 9)}`;
                    searchInput.id = uniqueId;
                }

                let stringIndex = 0;
                let charIndex = 0;
                const typeSpeed = 100;
                const pauseTime = 2000;
                const delayBetweenStrings = 1000;

                searchInput.setAttribute('placeholder', ''); // Clear placeholder
                function typeNextCharacter() {
                    if (charIndex < lines[stringIndex].length) {
                        searchInput.setAttribute(
                            'placeholder',
                            searchInput.getAttribute('placeholder') + lines[stringIndex][charIndex]
                        );
                        charIndex++;
                        setTimeout(typeNextCharacter, typeSpeed);
                    } else {
                        setTimeout(() => {
                            searchInput.setAttribute('placeholder', ''); // Clear placeholder
                            charIndex = 0;
                            stringIndex = (stringIndex + 1) % lines.length;
                            setTimeout(typeNextCharacter, delayBetweenStrings);
                        }, pauseTime);
                    }
                }

                typeNextCharacter();
            }

            function initializeTypingEffect() {
                const searchInputs = document.querySelectorAll('input[placeholder="Search for products"]');

                if (!searchInputs.length) {
    
                    return;
                }

                searchInputs.forEach((searchInput) => {
                    if (!searchInput.classList.contains('search-box-mn')) {
                        searchInput.classList.add('search-box-mn');
                        typeEffect(searchInput);
                    }
                });
            }

            setTimeout(initializeTypingEffect, 100);

            const observer = new MutationObserver(() => {
                initializeTypingEffect();
            });
            observer.observe(document.body, { childList: true, subtree: true });
        });
        </script>
        <?php
    }
}
add_action('wp_head', 'modify_search_placeholder');


// add_filter('woocommerce_account_menu_items', 'remove_downloads_tab', 99);

// function remove_downloads_tab($menu_links) {
//     unset($menu_links['downloads']); // Remove the Downloads tab
//     return $menu_links;
// }

function enable_gutenberg_for_products($can_edit, $post_type) {
    if ($post_type === 'product') {
        return true; // Enable Gutenberg editor for products
    }
    return $can_edit;
}
//add_filter('use_block_editor_for_post_type', 'enable_gutenberg_for_products', 10, 2);

function custom_add_title_to_links() {
    wp_add_inline_script(
        'jquery',
        "
        document.addEventListener('DOMContentLoaded', function () {
            const links = document.querySelectorAll('a[href*=\"product-category/mall-delivery\"]');
            links.forEach(link => {
                if (!link.hasAttribute('title')) {
                    link.setAttribute('title', 'Delivery from nearby malls');
                }
            });
        });
        "
    );
}
add_action('wp_enqueue_scripts', 'custom_add_title_to_links');

// Redirect users to the homepage after login
add_filter('woocommerce_login_redirect', 'custom_login_redirect', 10, 2);

function custom_login_redirect($redirect, $user) {
    // Redirect to homepage after login
    return home_url();
}

// Redirect users to the homepage after registration
add_filter('woocommerce_registration_redirect', 'custom_registration_redirect');

function custom_registration_redirect($redirect) {
    // Redirect to homepage after registration
    return home_url();
}

// Add password fields to WooCommerce registration form
add_action('woocommerce_register_form', 'custom_add_password_fields');

function custom_add_password_fields() {
    ?>
    <p class="form-row form-row-wide">
        <label for="reg_password"><?php esc_html_e('Password', 'woocommerce'); ?> <span class="required">*</span></label>
        <input type="password" class="input-text" name="password" id="reg_password" />
    </p>
    <?php
}

// 🔓 Override Jetpack password policy (min length 12) for testing
add_action('init', function () {
    // remove any validation rules Jetpack attaches to password reset/registration
    remove_all_filters('validate_password_reset');
});


// Validate the password fields during registration
add_filter('woocommerce_registration_errors', 'custom_validate_password_fields', 10, 3);

function custom_validate_password_fields($errors, $username, $email) {
    if (empty($_POST['password'])) {
        $errors->add('password_error', __('Vă rugăm să introduceți o parolă.', 'woocommerce'));
    }
    return $errors;
}

// Save the password during registration
add_action('woocommerce_created_customer', 'custom_save_password_fields', 10, 1);

function custom_save_password_fields($customer_id) {
    if (!empty($_POST['password'])) {
        wp_set_password($_POST['password'], $customer_id);
    }
}

// recommended

function get_user_purchased_products($user_id) {
    $customer_orders = wc_get_orders([
        'customer_id' => $user_id,
        'limit' => -1, // Fetch all orders
    ]);

    $purchased_product_ids = [];
    foreach ($customer_orders as $order) {
        foreach ($order->get_items() as $item) {
            $purchased_product_ids[] = $item->get_product_id();
        }
    }

    return array_unique($purchased_product_ids); // Remove duplicates
}


function get_recently_viewed_products() {
    if (!isset($_SESSION)) {
        session_start();
    }

    if (isset($_SESSION['woocommerce_recently_viewed'])) {
        return array_reverse(array_filter(explode('|', $_SESSION['woocommerce_recently_viewed'])));
    }

    return [];
}


function get_recommended_products($user_id, $limit = 6) {
    $purchased_products = get_user_purchased_products($user_id);

    if (empty($purchased_products)) {
        return []; // No recommendations if no purchases
    }

    $related_product_ids = [];
    foreach ($purchased_products as $product_id) {
        $related = wc_get_related_products($product_id, $limit);
        $related_product_ids = array_merge($related_product_ids, $related);
    }

    return array_unique($related_product_ids); // Remove duplicates
}


add_shortcode('recommended_products', 'display_recommended_products');

function display_recommended_products($atts) {
    if (!is_user_logged_in()) {
        return '<p>Vă rugăm să vă conectați pentru a vedea recomandări 123.</p>';
    }

    $user_id = get_current_user_id();
    $recommended_products = get_recommended_products($user_id);

    if (empty($recommended_products)) {
        return '<p>Vă rugăm să vă conectați pentru a vedea recomandări XXX YYY.
</p>';
    }

    $args = [
        'post_type' => 'product',
        'post__in' => $recommended_products,
        'orderby' => 'post__in', // Maintain order
        'posts_per_page' => 4, // Limit to 8 products
    ];

    $query = new WP_Query($args);

    if (!$query->have_posts()) {
        return '<p>Nicio recomandare disponibilă în acest moment.</p>';
    }

    ob_start();
    echo '<ul class="recommended-products">';
    while ($query->have_posts()) {
        $query->the_post();
        wc_get_template_part('content', 'product'); // WooCommerce product template
    }
    echo '</ul>';

    wp_reset_postdata();

    return ob_get_clean();
}

add_filter('gettext', 'vogo_remove_password_reset_text', 20, 3);

function vogo_remove_password_reset_text($translated_text, $text, $domain) {
    if ($translated_text === 'Un link pentru a seta o parolă nouă va fi trimis la adresa dvs. de email.') {
        $translated_text = ''; // Replace with an empty string or your custom text
    }
    return $translated_text;
}

add_filter('gettext', 'vogo_change_personal_data_text', 20, 3);

function vogo_change_personal_data_text($translated_text, $text, $domain) {
    if ($translated_text === 'Datele dvs. personale vor fi utilizate pentru a vă îmbunătăți experiența pe acest site, pentru a gestiona accesul la contul dvs. și în alte scopuri descrise în Politica noastră de confidențialitate.') {
        $translated_text = __('Bun venit la VOGO! Vă rugăm să rețineți că accesul la clubul nostru exclusiv se face doar pe bază de invitație. Dacă nu aveți un cod, vă invităm cu amabilitate să solicitați unul contactându-ne prin email la vogo@viptess.com sau prin WhatsApp la +40742203383. Mulțumesc!', 'woocommerce');
    }
    return $translated_text;
}

function add_custom_body_class($classes) {
    if (is_user_logged_in()) {
        $classes[] = 'logged-in';
    } else {
        $classes[] = 'logged-out';
    }
    return $classes;
}
add_filter('body_class', 'add_custom_body_class');

function add_mall_delivery_body_class($classes) {
    if (strpos($_SERVER['REQUEST_URI'], '/mall-delivery') !== false) {
        $classes[] = 'mall-delivery-page';
    }
    return $classes;
}
add_filter('body_class', 'add_mall_delivery_body_class');
 function enqueue_customs_script_for_gtranslate() {
     ?>
  <script>
        document.addEventListener("DOMContentLoaded", function () {
            function updateLanguageNames() {
                const translateDropdown = document.querySelector('.gt_selector'); // Select GTranslate dropdown
                if (translateDropdown) {
              //      console.log("GTranslate dropdown found. Updating language names...");
                    
                    // Map of language names to abbreviations
                    const languageMap = {
                        "Bulgarian": "BG",
                        "Czech": "CS",
                        "German": "DE",
                        "English": "EN",
                        "French": "FR",
                        "Romanian": "RO",
                        "Hungarian": "HU",
                        "Italian": "IT",
                        "Greek": "EL",
                        "Polish": "PL",
                        "Spanish": "ES",
                        "Turkish": "TR",
                    };
                    Array.from(translateDropdown.options).forEach((option, index) => {
                if (languageMap[option.text]) {
                    option.text = languageMap[option.text]; // Update the text
                } else if (option.text === "Select Language") {
                    translateDropdown.remove(index); // Remove "Select Language"
                }
            });

     // Set English as the default selected language
           //  translateDropdown.value = "en|en";
          translateDropdown.value="ro|ro";
            if (typeof window.doGTranslate === "function") {
               // window.doGTranslate("en|en"); // Set default language
               window.doGTranslate("ro|ro"); 
            //   console.log("Default language set  Romanion (EN).");
            }
                    // Iterate through the dropdown options
                    Array.from(translateDropdown.options).forEach(option => {
                        if (languageMap[option.text]) {
                            option.text = languageMap[option.text]; // Update the text
                        }
                    });

                   // console.log("Language names updated.");
                } else {
                 //   console.log("GTranslate dropdown not found. Retrying...");
                    setTimeout(updateLanguageNames, 500); // Retry after 500ms if dropdown isn't loaded yet
                }
            }

            updateLanguageNames(); // Call the function to update language names
        });
    </script>
  <?php
 }
 add_action('wp_footer', 'enqueue_customs_script_for_gtranslate');

add_action('woocommerce_login_form_end', 'add_register_lostpassword_links');
function add_register_lostpassword_links() {
    // Specify the Register page URL
    $register_url = get_permalink(4537); // Use your specific page ID for the Register page
    $lost_password_url = wp_lostpassword_url(); // Default WooCommerce Lost Password URL

    // Output the links in a styled container
    echo '<div class="woocommerce-login-footer">';
    echo '<div class="remember-me-section">';
    echo '<label for="rememberme" class="woocommerce-form__label woocommerce-form__label-for-checkbox inline">';
    echo '<input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" /> Ține-mă Minte';
    echo '</label>';
   
    echo '<div class="register-lost-password">';
    echo '<a href="' . esc_url($register_url) . '">' . __('Înregistrare', 'woocommerce') . '</a> | ';
    echo '<a href="' . esc_url($lost_password_url) . '">' . __('Pierdut Parola?', 'woocommerce') . '</a>';
    echo '</div>';
    echo '</div>';
     echo '</div>';
      echo '<div class="mo-google-login" style="flex: 1; min-width: 200px;">';
    echo do_shortcode('[nextend_social_login]');
    echo '</div>';
}
// Remove the default WooCommerce dashboard text
// Remove the "Hello {name}" and "From your account dashboard..." text
add_filter('woocommerce_account_dashboard', 'remove_woocommerce_dashboard_greeting', 1);

function remove_woocommerce_dashboard_greeting() {
    remove_action('woocommerce_account_dashboard', 'woocommerce_account_content', 10);
}

// Add Order Tags meta box in order edit screen
add_action('add_meta_boxes', function () {
    add_meta_box('order_tags_box', 'Order Tags', function ($post) {
        $tags = get_post_meta($post->ID, '_order_tags', true);
        echo '<input type="text" name="order_tags" value="' . esc_attr($tags) . '" style="width:100%;" placeholder="Comma-separated tags" />';
    }, 'shop_order', 'side', 'default');
});

// Save Order Tags meta field
add_action('save_post_shop_order', function ($post_id) {
    if (isset($_POST['order_tags'])) {
        update_post_meta($post_id, '_order_tags', sanitize_text_field($_POST['order_tags']));
    }
});

function remove_categories_in_home_category() {
    ?>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        // Find the home-category wrapper
        let homeCategory = document.querySelector(".home-category");

        if (homeCategory) {
            // Inside home-category, find the product grid
            let productGrid = homeCategory.querySelector(".products.wd-products");

            if (productGrid) {
                // Find only category items inside this specific product grid
                let categoryItems = productGrid.querySelectorAll(".category-grid-item");

                categoryItems.forEach(category => {
                    category.remove();
                });

                // Recalculate Masonry layout if it's being used
                let gridContainer = homeCategory.querySelector(".grid-masonry");
                if (gridContainer && typeof Masonry !== "undefined") {
                    setTimeout(() => {
                        new Masonry(gridContainer, {
                            itemSelector: ".wd-col.product-grid-item",
                            columnWidth: ".wd-col.product-grid-item",
                            percentPosition: true
                        });
                    }, 500); // Delay to ensure DOM updates
                }
            }
        }
    });
    </script>
    <?php
}
add_action('wp_footer', 'remove_categories_in_home_category');


add_action('wp_footer',function(){ ?>
<script>
 document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll('a[href*="mall-delivery"]').forEach(link => {
        link.addEventListener("click", function (event) {
            event.preventDefault(); // Prevent default AJAX navigation
            window.location.href = this.href; // Force full page reload
        });
    });
});
</script>

<?php }); 

function enqueue_custom_script_for_gtranslates() {
    ?>
    <!-- <script> 
        document.addEvenstListener("DOMContentLoaded", function () {

            // Update the language names in the GTranslate dropdown
            function updateLanguageNames() {
                const translateDropdown = document.querySelector('.gt_selector'); // Select GTranslate dropdown
                if (translateDropdown) {
                    console.log("GTranslate dropdown found. Updating language names...");

                    // Map of language names to abbreviations
                    const languageMap = {
                        "English": "EN",
                        "French": "FR",
                        "Romanian": "RO"
                    };

                    Array.from(translateDropdown.options).forEach((option, index) => {
                        if (languageMap[option.text]) {
                            option.text = languageMap[option.text]; // Update the text to abbreviation
                        } else if (option.text === "Select Language") {
                            // Remove the "Select Language" option
                            translateDropdown.remove(index);
                        }
                    });

                    console.log("Language names updated.");
                } else {
                    console.log("GTranslate dropdown not found. Retrying updateLanguageNames...");
                    setTimeout(updateLanguageNames, 500); // Retry after 500ms
                }
            }

            // Detect browser language and set the GTranslate language accordingly
            function detectAndSetLanguage() {
                let browserLang = navigator.language || navigator.userLanguage;
                browserLang = browserLang.split('-')[0]; // e.g., 'ro' from 'ro-RO'

                console.log("Detected browser language:", browserLang);

                // Assuming your site's default language is English, so the source is "en"
                let selectedLang = "en|en"; // Default: English (no translation)
                if (browserLang === "ro") {
                    selectedLang = "en|ro"; // Translate from English to Romanian
                } else if (browserLang === "fr") {
                    selectedLang = "en|fr"; // Translate from English to French
                }

                console.log("Setting website language to:", selectedLang);

                function applyLanguageChange() {
                    const translateDropdown = document.querySelector('.gt_selector'); // Select GTranslate dropdown
                    if (translateDropdown) {
                        translateDropdown.value = selectedLang; // Update dropdown's value

                        if (typeof window.doGTranslate === "function") {
                            console.log("Forcing translation by switching to English first...");
                            // Force a reset to English first
                            window.doGTranslate("en|en");

                            setTimeout(function() {
                                console.log("Now setting the correct language:", selectedLang);
                                window.doGTranslate(selectedLang);
                            }, 800); // Delay to ensure the reset takes effect
                        } else {
                            console.log("doGTranslate not defined yet. Retrying applyLanguageChange...");
                            setTimeout(applyLanguageChange, 500); // Retry if function isn’t available
                        }
                    } else {
                        console.log("GTranslate dropdown not found in applyLanguageChange. Retrying...");
                        setTimeout(applyLanguageChange, 500); // Retry if dropdown isn’t loaded
                    }
                }

                setTimeout(applyLanguageChange, 1200); // Initial delay to ensure proper execution order
            }

            // Initial calls with delays to allow the GTranslate widget to load
            setTimeout(updateLanguageNames, 500);
            setTimeout(detectAndSetLanguage, 1000);
        });
    </script> -->
    <?php
}
add_action('wp_footer', 'enqueue_custom_script_for_gtranslates');

/* SLIDER2 =============================================================================*/

function custom_slider_register_post_type() {
    $args = array(
        'public' => true,
        'label'  => 'Slider2 Items',
        'supports' => array('title', 'editor', 'thumbnail'),
        'menu_icon' => 'dashicons-images-alt2'
    );
    register_post_type('custom_slider', $args);
}
add_action('init', 'custom_slider_register_post_type');

// Add Meta Box for Slider Button Link
function custom_slider_add_meta_box() {
    add_meta_box(
        'custom_slider_meta', 
        'Slider Options', 
        'custom_slider_meta_callback', 
        'custom_slider', 
        'normal', 
        'high'
    );
}
add_action('add_meta_boxes', 'custom_slider_add_meta_box');

function custom_slider_meta_callback($post) {
    $caption = get_post_meta($post->ID, 'slider_caption', true);
    $slide_code = get_post_meta($post->ID, 'slider_code', true);
    $position = get_post_meta($post->ID, 'slider_position', true);
    $button_link = get_post_meta($post->ID, 'slider_button_link', true);
    $button_text = get_post_meta($post->ID, 'slider_button_text', true);
    $subtitle = get_post_meta($post->ID, 'slider_subtitle', true);
    $button_color = get_post_meta($post->ID, 'slider_button_color', true);
    $overlay_bg_color = get_post_meta($post->ID, 'overlay_bg_color', true);
    $text_color = get_post_meta($post->ID, 'text_color', true);
    $selected_page = get_post_meta($post->ID, 'slider_page', true); // Get assigned page
    $selected_categories = get_post_meta($post->ID, 'slider_categories', true); // Get assigned categories
    $categories = get_terms(array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
    ));
      // Get all pages
      $pages = get_pages();
    ?>
    <label for="slider_caption"><strong>Caption:</strong></label>
    <input type="text" id="slider_caption" name="slider_caption" value="<?php echo esc_attr($caption); ?>" style="width:100%; margin-bottom: 10px;"/>

    <label for="slider_code"><strong>Slide Code:</strong></label>
    <input type="text" id="slider_code" name="slider_code" value="<?php echo esc_attr($slide_code); ?>" style="width:100%; margin-bottom: 10px;"/>

    <label for="slider_position"><strong>Slide Position:</strong></label>
    <input type="number" id="slider_position" name="slider_position" value="<?php echo esc_attr($position); ?>" style="width:100%; margin-bottom: 10px;"/>
    <label for="slider_categories"><strong>Assign to Product Categories:</strong></label>
    <select id="slider_categories" name="slider_categories[]" multiple style="width:100%; margin-bottom: 10px;">
        <?php foreach ($categories as $category) : ?>
            <option value="<?php echo esc_attr($category->term_id); ?>" <?php echo (is_array($selected_categories) && in_array($category->term_id, $selected_categories)) ? 'selected' : ''; ?>>
                <?php echo esc_html($category->name); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <label for="slider_page"><strong>Assign to Page:</strong></label>
    <select id="slider_page" name="slider_page" style="width:100%; margin-bottom: 10px;">
        <option value="">Select a Page</option>
        <?php foreach ($pages as $page) : ?>
            <option value="<?php echo esc_attr($page->ID); ?>" <?php selected($selected_page, $page->ID); ?>>
                <?php echo esc_html($page->post_title); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <!-- <label for="slider_subtitle"><strong>Subtitle:</strong></label>
    <input type="text" id="slider_subtitle" name="slider_subtitle" value="<?php echo esc_attr($subtitle); ?>" style="width:100%; margin-bottom: 10px;"/> -->

    <label for="slider_button_link"><strong>Button Link:</strong></label>
    <input type="text" id="slider_button_link" name="slider_button_link" value="<?php echo esc_attr($button_link); ?>" style="width:100%; margin-bottom: 10px;"/>

    <label for="slider_button_text"><strong>Slide Button Text:</strong></label>
    <input type="text" id="slider_button_text" name="slider_button_text" value="<?php echo esc_attr($button_text); ?>" style="width:100%; margin-bottom: 10px;"/>

    <label for="slider_button_color"><strong>Button Color:</strong></label>
    <input type="color" id="slider_button_color" name="slider_button_color" value="<?php echo esc_attr($button_color); ?>" style="width:100%; margin-bottom: 10px;"/>
    <label for="overlay_bg_color"><strong>Overlay Background Color:</strong></label>
    <input type="color" id="overlay_bg_color" name="overlay_bg_color" value="<?php echo esc_attr($overlay_bg_color ?: '#000000'); ?>" style="width:100%; margin-bottom: 10px;"/>

    <label for="text_color"><strong>Text Color 123:</strong></label>
    <input type="color" id="text_color" name="text_color" value="<?php echo esc_attr($text_color ?: '#ffffff'); ?>" style="width:100%; margin-bottom: 10px;"/>

  <p style="margin:0 0 8px 0;"><strong>How to make the slider work:</strong></p>
  <ol style="margin:0 0 10px 18px;">
    <li>Add the shortcode to the target page: <code>[custom_slider]</code></li>
    <li>See a sample in edit mode:
      <a href="https://vogo.family/wp-admin/post.php?post=32424&action=elementor" target="_blank" rel="noopener noreferrer">
        open sample in Elementor
      </a>
    </li>
    <li>In Elementor use <em>Navigator</em> (or <kbd>Ctrl</kbd> + <kbd>I</kbd>) → add a <strong>Shortcode</strong> widget with <code>[custom_slider]</code>.</li>
  </ol>

  <p style="margin:0 0 6px 0;"><strong>Optional – hide the page title banner (WoodMart) for page ID 31:</strong></p>
  <pre style="margin:0;background:#f8fafc;border:1px solid #e2e8f0;padding:10px;overflow:auto;">
Appearance → Customize → Additional CSS:
/* Hide WoodMart title banner on page ID 31 */
.page-id-31 .page-title,
.page-id-31 .title-wrapper,
.page-id-31 .woodmart-title-container { display: none !important; }
  </pre>




    <?php
}


function custom_slider_save_meta_box($post_id) {
    // Define fields to save
    $fields = ['slider_caption', 'slider_code', 'slider_position', 'slider_button_link', 'slider_button_text', 'slider_subtitle', 'slider_button_color', 'overlay_bg_color', 'text_color', 'slider_page'];

    // Loop through and save fields
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
        }
    }

    // Save selected product categories
    if (isset($_POST['slider_categories']) && is_array($_POST['slider_categories'])) {
        $category_ids = array_map('intval', $_POST['slider_categories']); // Ensure IDs are integers
        update_post_meta($post_id, 'slider_categories', $category_ids);
    } else {
        delete_post_meta($post_id, 'slider_categories'); // Remove if no categories are selected
    }
}
add_action('save_post', 'custom_slider_save_meta_box');

function custom_slider_columns($columns) {
    $columns['slider_caption'] = 'Caption';
    $columns['slider_code'] = 'Slide Code';
    $columns['slider_position'] = 'Position';
    $columns['slider_button_link'] = 'Button Link';
    $columns['slider_button_text'] = 'Button Text';
    $columns['slider_page'] = 'Page';
    $columns['slider_categories'] = 'Categories';
    return $columns;
}
add_filter('manage_custom_slider_posts_columns', 'custom_slider_columns');

// Display Custom Fields in Admin Table
function custom_slider_custom_column($column, $post_id) {
    switch ($column) {
        case 'slider_caption':
            echo esc_html(get_post_meta($post_id, 'slider_caption', true));
            break;
        case 'slider_code':
            echo esc_html(get_post_meta($post_id, 'slider_code', true));
            break;
        case 'slider_position':
            echo esc_html(get_post_meta($post_id, 'slider_position', true));
            break;
        case 'slider_button_link':
            echo esc_html(get_post_meta($post_id, 'slider_button_link', true));
            break;
        case 'slider_button_text':
            echo esc_html(get_post_meta($post_id, 'slider_button_text', true));
            break;
        case 'slider_page':
            $page_id = get_post_meta($post_id, 'slider_page', true);
             if ($page_id) {
                    echo '<a href="'.esc_url(get_permalink($page_id)).'" target="_blank">'.get_the_title($page_id).'</a>';
                } else {
                    echo 'Not Assigned';
                }
                break;    
          case 'slider_categories':
                    $category_ids = get_post_meta($post_id, 'slider_categories', true);
                    if (!empty($category_ids)) {
                        $category_names = array();
                        foreach ($category_ids as $cat_id) {
                            $category_names[] = get_term($cat_id, 'product_cat')->name;
                        }
                        echo implode(', ', $category_names);
                    } else {
                        echo 'Not Assigned';
                    }
                    break;         
    }
}
add_action('manage_custom_slider_posts_custom_column', 'custom_slider_custom_column', 10, 2);

// Shortcode to Display Slider
function custom_slider_shortcode() {
    if (!is_page()) return ''; // Only show on WordPress pages

    global $post;
    $current_page_id = $post->ID;

    $args = array(
        'post_type'      => 'custom_slider',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'     => 'slider_page',
                'value'   => $current_page_id,
                'compare' => '='
            )
        ),
        'meta_key'       => 'slider_position',
        'orderby'        => 'meta_value_num',
        'order'          => 'ASC',
    );

    $query = new WP_Query($args);

    if (!$query->have_posts()) {
        return ''; // No sliders to show
    }

    $output = '<div class="main-slider slider">';
    while ($query->have_posts()) {
        $query->the_post();
        $image = get_the_post_thumbnail_url(get_the_ID(), 'full');
        $title = get_the_title();
        $content = get_the_content();
        $link = get_post_meta(get_the_ID(), 'slider_button_link', true);
        $button_text = get_post_meta(get_the_ID(), 'slider_button_text', true);
        $button_color = get_post_meta(get_the_ID(), 'slider_button_color', true);
        $overlay_bg_color = get_post_meta(get_the_ID(), 'overlay_bg_color', true);
        $text_color = get_post_meta(get_the_ID(), 'text_color', true);

        $output .= '<div class="slider__item" style="background-image: url('.esc_url($image).');">';
        $output .= '<div class="slider-overlay" style="background-color:'.esc_attr($overlay_bg_color).'; color:'.esc_attr($text_color).';">';
        $output .= '<h2 style="color:'.esc_attr($text_color).';" class="slider-title">'.esc_html($title).'</h2>';
        $output .= '<p>'.esc_html($content).'</p>';
        if (!empty($link) && !empty($button_text)) {
            $output .= '<a href="'.esc_url($link).'" class="slider-btn" style="background-color: '.esc_attr($button_color).';">'.esc_html($button_text).'</a>';
        }
        $output .= '</div></div>';
    }
    wp_reset_postdata();
    $output .= '</div>';
    
    return $output;
}
add_shortcode('custom_slider', 'custom_slider_shortcode');

// JavaScript File for Slider Functionality
// function custom_slider_script_file() {
//     echo "<script>
//     jQuery(document).ready(function($) {
//         if ($('.main-slider').length > 0) {
//             $('.main-slider').slick({
//                 dots: true,
//                 infinite: true,
//                 speed: 500,
//                 slidesToShow: 1,
//                 slidesToScroll: 1,
//                 autoplay: true,
//                 autoplaySpeed: 3000,
//                 arrows: true
//             });
//         }
//     });
//     </script>";
// }
// add_action('wp_footer', 'custom_slider_script_file');

// Enqueue JavaScript in the footer


add_action('wp_footer', function () { ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    function setGTranslateCookie(fromLang, toLang) {
        const value = `/${fromLang}/${toLang}`;
        const domain = location.hostname.startsWith('www.') ? location.hostname.replace('www.', '') : location.hostname;
        const expiry = new Date();
        expiry.setFullYear(expiry.getFullYear() + 1); // 1 year expiration

        const cookieStr = `googtrans=${value}; path=/; domain=.${domain}; expires=${expiry.toUTCString()};`;
        document.cookie = cookieStr;
        console.log("✅ Cookie set:", cookieStr);
    }

    function applyPersistentLanguage() {
        const cookies = document.cookie.split('; ').reduce((acc, val) => {
            const parts = val.split('=');
            acc[parts[0]] = parts[1];
            return acc;
        }, {});

        const googtrans = cookies['googtrans'];
        if (googtrans && typeof doGTranslate === 'function') {
            const targetLang = googtrans.split('/')[2];
            if (targetLang) {
                console.log("🌐 Applying language:", targetLang);
                doGTranslate(`ro|${targetLang}`);

                // Sync .gt_selector dropdowns with the current language
                document.querySelectorAll('.gt_selector').forEach(function(select) {
                    Array.from(select.options).forEach(function(option) {
                        if (option.value.endsWith('|' + targetLang)) {
                            select.value = option.value;
                            console.log("✅ Dropdown synced to:", option.value);
                        }
                    });
                });
            }
        } else if (!googtrans) {
            console.log("ℹ️ No googtrans cookie found.");
        } else {
            console.log("⏳ Waiting for doGTranslate to be available...");
            setTimeout(applyPersistentLanguage, 500);
        }
    }

    document.querySelectorAll('.gt_selector').forEach(function (dropdown) {
        dropdown.addEventListener('change', function () {
            const selected = this.value;
            const parts = selected.split('|');
            if (parts.length === 2) {
                setGTranslateCookie(parts[0], parts[1]);
                if (typeof doGTranslate === 'function') {
                    doGTranslate(selected);
                }
            }
        });
    });

    applyPersistentLanguage();
});
</script>
<?php
});
function add_category_image_class($classes) {
    if (is_tax('product_cat')) { // Check if it's a Product Category page
        $term_id = get_queried_object_id();
        $side_image = get_field('side_image', 'product_cat_' . $term_id);

        if (!empty($side_image)) {
            $classes[] = 'has-image';
        } else {
            $classes[] = 'no-image';
        }
    }
    return $classes;
}
add_filter('body_class', 'add_category_image_class');

function promo_shortcode() {
    if (!is_tax('product_cat')) {
        return ''; // Only run on product category pages
    }

    $term_id = get_queried_object_id();
    $side_image = get_field('side_image', 'product_cat_' . $term_id);
    $side_text = get_field('side_text_under_image', 'product_cat_' . $term_id);
    $button_label = get_field('button_label', 'product_cat_' . $term_id);
    $button_link = get_field('button_link', 'product_cat_' . $term_id);

    // Default fallback values if fields are empty
    $image_url = !empty($side_image) ? esc_url($side_image) : 'https://test07.vogo.family/wp-content/uploads/2025/02/joychild.webp';
    $button_label = !empty($button_label) ? esc_html($button_label) : 'Read More';
    $button_link = !empty($button_link) ? esc_url($button_link) : '#';

    ob_start(); ?>
    
    <div class="promo-banner-wrapper">
        <div class="promo-banner banner-content-background banner-hover-zoom color-scheme-light banner-btn-size-default banner-btn-style-default with-btn banner-btn-position-static wd-with-link">
            <div class="main-wrapp-img">
                <div class="banner-image wd-without-height">
                    <img width="150" height="150" src="<?php echo $image_url; ?>" class="attachment-thumbnail size-thumbnail" alt="Promo Image" decoding="async">
                </div>
                <?php if (!empty($side_text)) : ?>
                    <p class="side-text"><?php echo esc_html($side_text); ?></p>
                <?php endif; ?>
            </div>

            <div class="wrapper-content-banner wd-fill wd-items-bottom wd-justify-left">
                <div class="content-banner text-left">
                    <h4 class="banner-title wd-fontsize-l" data-elementor-setting-key="title">
                        <font style="vertical-align: inherit;">
                            Vogo
                        </font>
                    </h4>
                    
                    <div class="banner-btn-wrapper">
                        <div class="wd-button-wrapper text-left">
                            <a class="btn btn-style-default btn-shape-rectangle btn-size-default btn-icon-pos-right" href="<?php echo $button_link; ?>">
                                <span class="wd-btn-text" data-elementor-setting-key="text">
                                    <font style="vertical-align: inherit;"><?php echo $button_label; ?></font>
                                </span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <a href="<?php echo $button_link; ?>" class="wd-promo-banner-link wd-fill" aria-label="Banner link"></a>
        </div>
    </div>

    <?php
    return ob_get_clean();
}
add_shortcode('promo', 'promo_shortcode');

function enqueue_woodmart_banner_styles() {
    if (is_tax('product_cat')) { // Load only on Product Category pages
        wp_enqueue_style('wd-banner-css', get_template_directory_uri() . '/css/parts/el-banner.min.css', array(), '8.0.6', 'all');
        wp_enqueue_style('wd-banner-style-bg-cont-css', get_template_directory_uri() . '/css/parts/el-banner-style-bg-cont.min.css', array(), '8.0.6', 'all');
        wp_enqueue_style('wd-banner-hover-zoom-css', get_template_directory_uri() . '/css/parts/el-banner-hover-zoom.min.css', array(), '8.0.6', 'all');
        wp_enqueue_style('wd-button-css', get_template_directory_uri() . '/css/parts/el-button.min.css', array(), '8.0.6', 'all');
    }
}
add_action('wp_enqueue_scripts', 'enqueue_woodmart_banner_styles');

add_filter('woocommerce_loop_add_to_cart_link', function($button, $product) {
    if ($product->get_price() === '' || $product->get_price() === null) {
        return ''; // Remove the button if price is empty
    }
    return $button;
}, 10, 2);



/**
 * Filters the WooCommerce product title to conditionally wrap it in a span with a class of 'notranslate'.
 *
 * @param string $title The product title.
 * @param WC_Product $product The product object.
 * @return string The modified or unmodified product title.
 * this function will add a class to the product title if the product has the 'want_translated' field set unchecked
 */
add_filter('woocommerce_product_title', function($title, $product) {
    $want_translated = get_field('want_translated', $product->get_id());
    if (!$want_translated) {
        return '<span class="notranslate">' . $title . '</span>';
    }
    return $title;
}, 10, 2);


add_action('woocommerce_single_product_summary', function() {
    global $post;
    $want_translated = get_field('want_translated', $post->ID);
    $notranslate_class = (!$want_translated) ? 'notranslate' : '';

    echo '<h1 class="product_title entry-title ' . esc_attr($notranslate_class) . '">' . get_the_title() . '</h1>';
}, 5);

add_action('wp_footer', function() { ?>
    <script>
        jQuery(document).ready(function($) {
            function applyNotranslate() {
                $('.product_title').each(function() {
                    <?php
                    $want_translated = get_field('want_translated'); // Check ACF field
                    if (!$want_translated) : 
                    ?>
                        $(this).addClass('notranslate').attr('translate', 'no');
                    <?php endif; ?>
                });
            }

            // Initial execution
            applyNotranslate();

            // Observe Elementor shop widget updates (for AJAX-loaded content)
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === "childList") {
                        applyNotranslate();
                    }
                });
            });

            // Target the Elementor Shop Widget container
            var targetNode = document.querySelector('.elementor-widget-woocommerce-products');
            if (targetNode) {
                observer.observe(targetNode, { childList: true, subtree: true });
            }
        });
    </script>
<?php });

add_action('wp_footer', function() { ?>
    <script>
        jQuery(document).ready(function($) {
            function applyNotranslate() {
                $('.wd-entities-title a, .wd-product-cats a').each(function() {
                    <?php
                    $want_translated = get_field('want_translated'); // Check ACF field
                    if (!$want_translated) : 
                    ?>
                        $(this).addClass('notranslate').attr('translate', 'no');
                    <?php endif; ?>
                });
            }

            // Initial execution
            applyNotranslate();

            // Observe Elementor shop widget updates (for dynamically loaded products)
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === "childList") {
                        applyNotranslate();
                    }
                });
            });

            // Target Elementor shop widget container
            var targetNode = document.querySelector('.elementor-widget-woocommerce-products, .shop-container, .products');
            if (targetNode) {
                observer.observe(targetNode, { childList: true, subtree: true });
            }
        });
    </script>
<?php });


function add_category_search_and_collapse() {
    global $pagenow, $typenow;

    if ( ($pagenow == 'post.php' || $pagenow == 'post-new.php') && $typenow == 'product' ) {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Add search box above categories list
                var categoryFilter = '<input type="text" id="category-search" placeholder="Search categories..." style="width:100%; padding:5px; margin-bottom:10px;">';
                $('#product_cat-all').before(categoryFilter);

                // Search filter function
                $('#category-search').on('keyup', function() {
                    var searchText = $(this).val().toLowerCase();
                    
                    $('#product_catchecklist li').each(function() {
                        var categoryText = $(this).text().toLowerCase();
                        if (
                            categoryText.indexOf(searchText) > -1 ||
                            $(this).find('input[type="checkbox"]').is(':checked')
                        ) {
                            $(this).show();
                        } else {
                            $(this).hide();
                        }
                    });
                });

                // Collapse child categories
                $('#product_catchecklist li').each(function() {
                    var $this = $(this);
                    
                    if ($this.find('ul.children').length > 0) {
                        // Add collapse button
                        $this.prepend('<span class="toggle-category" style="cursor:pointer; font-weight:bold; margin-right:5px;">[+]</span>');
                        $this.children('ul.children').hide();
                    }
                });

                // Toggle collapse/expand on click
                $('.toggle-category').on('click', function() {
                    var $childList = $(this).siblings('ul.children');
                    if ($childList.is(':visible')) {
                        $childList.slideUp();
                        $(this).text('[+]');
                    } else {
                        $childList.slideDown();
                        $(this).text('[-]');
                    }
                });
            });
        </script>
        <style>
            /* Indent child categories */
            #product_catchecklist ul.children {
                margin-left: 15px;
                padding-left: 10px;
                border-left: 1px dashed #ccc;
            }
            .toggle-category {
                color: #0073aa;
            }
            .toggle-category:hover {
                color: #00a0d2;
            }
        </style>
        <?php
    }
}
add_action('admin_footer', 'add_category_search_and_collapse');


// Add VAT / Registration Code field in WooCommerce billing fields
function add_vat_registration_field($fields) {
    $fields['billing_vat_code'] = array(
        'label'       => __('Cod TVA / Cod de înregistrare', 'woocommerce'),
        //'placeholder' => __('', 'woocommerce'),
        'required'    => false, // Not required
        'class'       => array('form-row-wide', 'form-row-last'), // 50% width, placed next to first row
        'priority'    => 31 // Set priority just after Company Name (Company is 30)
    );

    return $fields;
}
add_filter('woocommerce_billing_fields', 'add_vat_registration_field');

// Save VAT Code / Registration Code to user meta
function save_vat_registration_field($user_id) {
    if (isset($_POST['billing_vat_code'])) {
        update_user_meta($user_id, 'billing_vat_code', sanitize_text_field($_POST['billing_vat_code']));
    }
}
add_action('woocommerce_customer_save_address', 'save_vat_registration_field');
add_action('woocommerce_checkout_update_user_meta', 'save_vat_registration_field');

// Display VAT Code in My Account Address
function display_vat_registration_field_in_account($address, $customer_id, $address_type) {
    $vat_code = get_user_meta($customer_id, 'billing_vat_code', true);
    if ($vat_code) {
        $address['billing_vat_code'] = __('Cod TVA / Cod de înregistrare', 'woocommerce') . ': ' . $vat_code;
    }
    return $address;
}
add_filter('woocommerce_my_account_my_address_formatted_address', 'display_vat_registration_field_in_account', 10, 3);

add_action('woocommerce_admin_order_data_after_billing_address', function ($order) {
    $vat_code = $order->get_meta('billing_vat_code');
    if ($vat_code) {
        echo '<p><strong>' . __('Cod TVA / Cod de înregistrare', 'woocommerce') . ':</strong> ' . esc_html($vat_code) . '</p>';
    }
});

add_filter('woocommerce_email_customer_details_fields', function ($fields, $sent_to_admin, $order) {
    $vat_code = $order->get_meta('billing_vat_code');
    if ($vat_code) {
        $fields['billing_vat_code'] = array(
            'label' => __('Cod TVA / Cod de înregistrare', 'woocommerce'),
            'value' => $vat_code
        );
    }
    return $fields;
}, 10, 3);

add_action('woocommerce_checkout_update_order_meta', function ($order_id) {
    if (isset($_POST['billing_vat_code'])) {
        update_post_meta($order_id, 'billing_vat_code', sanitize_text_field($_POST['billing_vat_code']));
    }
});

add_action('woocommerce_checkout_create_order', function ($order, $data) {
    if (isset($_POST['billing_vat_code'])) {
        $order->update_meta_data('billing_vat_code', sanitize_text_field($_POST['billing_vat_code']));
    }
}, 10, 2);

/**
 * Adds a custom 'VAT Code' column to the WooCommerce Orders page.
 *
 * Filters the columns displayed in the WooCommerce Orders admin screen.
 */
add_filter('manage_woocommerce_page_wc-orders_columns', 'add_vat_code_column_hpos');
function add_vat_code_column_hpos($columns) {
    // Insert the column after 'order_total' or anywhere you'd like
    $new_columns = [];

    foreach ($columns as $key => $label) {
        $new_columns[$key] = $label;
        if ($key === 'order_total') {
            $new_columns['billing_vat_code'] = __('VAT Code', 'woocommerce');
        }
    }

    return $new_columns;
}
add_action('manage_woocommerce_page_wc-orders_custom_column', 'show_vat_code_column_content_hpos', 10, 2);
function show_vat_code_column_content_hpos($column, $order) {
    if ($column === 'billing_vat_code') {
        // $order is a WC_Order object when using HPOS
        $vat_code = $order->get_meta('billing_vat_code');
        echo $vat_code ? esc_html($vat_code) : '—';
    }
}
// Optional: Make column sortable (optional and advanced)
// To make the column sortable, you'd also need to modify the query, which may require custom SQL and is not always recommended unless needed.
// Show Additional Addresses under the Billing Address section
function show_saved_addresses_in_account_addresses() {
    if (!is_user_logged_in()) {
        return;
    }

    global $wpdb;
    $user_id = get_current_user_id();

    // Only display when editing the billing address
   // if (isset($_GET['edit-address']) && $_GET['edit-address'] === 'billing') {
        echo '<h3>Adrese Salvate Suplimentare</h3>';

        // Fetch saved addresses
        $addresses = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}user_addresses WHERE user_id = %d AND status = 'active'",
            $user_id
        ));

        if (!empty($addresses)) {
            echo '<table style="width:100%; border-collapse: collapse; margin-top: 10px;">
                    <thead>
                        <tr>
                            <th style="border: 1px solid #ddd; padding: 10px;">Nume Adresă</th>
                            <th style="border: 1px solid #ddd; padding: 10px;">Adresă Stradală</th>
                            <th style="border: 1px solid #ddd; padding: 10px;">Oraș</th>
                         </tr>
                    </thead>
                    <tbody>';

            foreach ($addresses as $address) {
                echo '<tr>';
                echo '<td style="border: 1px solid #ddd; padding: 10px;">' . esc_html($address->address_name ?? 'N/A') . '</td>';
                echo '<td style="border: 1px solid #ddd; padding: 10px;">' . esc_html($address->street_address ?? 'N/A') . '</td>';
                echo '<td style="border: 1px solid #ddd; padding: 10px;">' . esc_html($address->city ?? '') . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        } else {
            echo '<p>Nicio adresă suplimentară salvată.</p>';
        }
 //   }
}
add_action('woocommerce_after_edit_account_address_form', 'show_saved_addresses_in_account_addresses');
function reorder_my_account_menu_items($items) {
    // Remove the existing 'edit-account' item from its position
    if (isset($items['edit-account'])) {
        $edit_account = $items['edit-account'];
        unset($items['edit-account']); 
    }

    // Convert items into an array to insert 'edit-account' after 'dashboard'
    $new_items = [];
    foreach ($items as $key => $value) {
        $new_items[$key] = $value;
        if ($key === 'dashboard' && isset($edit_account)) {
            $new_items['edit-account'] = $edit_account; // Insert after Dashboard
        }
    }

    return $new_items;
}
add_filter('woocommerce_account_menu_items', 'reorder_my_account_menu_items', 10, 1);


add_filter( 'woocommerce_return_to_shop_redirect', function() {
    return home_url(); // Redirects to Home Page
});

function custom_woocommerce_account_dashboard() {
    $current_user = wp_get_current_user();
    
    if ($current_user->exists()) {
        echo '<h3>Detalii cont</h3>';
        echo '<p><strong>Nume de Utilizator:</strong> ' . esc_html($current_user->user_login) . '</p>';
        echo '<p><strong>Email:</strong> ' . esc_html($current_user->user_email) . '</p>';
        echo '<p><a href="' . esc_url(wc_get_account_endpoint_url('edit-account')) . '" class="edit-button">Editează Detaliile Tale</a></p>';
    }
}
add_action('woocommerce_before_account_navigation', 'custom_woocommerce_account_dashboard');

// Schedule the cron job if it’s not already scheduled
function vogo_schedule_xls_conversion() {
    if (!wp_next_scheduled('vogo_convert_xls_to_csv_event')) {
        wp_schedule_event(time(), 'daily', 'vogo_convert_xls_to_csv_event');
    }
}
add_action('wp', 'vogo_schedule_xls_conversion');

// Hook to run the XLS conversion script
function vogo_convert_xls_to_csv() {
    $xls_url = "https://www.texacom.ro/feeds/distributie.php?p=all&tip=xls";
    $xls_file = ABSPATH . "wp-content/uploads/latest_data.xls"; // Save XLS in /uploads/
    $csv_file = ABSPATH . "wp-content/uploads/updated_prices.csv"; // Save CSV in /uploads/

    // Download the XLS file
    $data = file_get_contents($xls_url);
    if (!$data) {
        error_log("Error: Failed to download XLS file.");
        return;
    }
    file_put_contents($xls_file, $data);

    // Convert XLS to CSV
    $spreadsheet = IOFactory::load($xls_file);
    $sheet = $spreadsheet->getActiveSheet();
    $csv_data = [];

    foreach ($sheet->getRowIterator() as $row) {
        $csv_row = [];
        foreach ($row->getCellIterator() as $cell) {
            $csv_row[] = $cell->getFormattedValue();
        }
        $csv_data[] = $csv_row;
    }

    // Modify Prices (Assuming price is in column index 7)
    $commercial_percentage = 1.10;
    foreach ($csv_data as $key => $row) {
        if ($key > 0 && isset($row[7]) && is_numeric($row[7])) {
            $csv_data[$key][7] = round($row[7] * $commercial_percentage, 2);
        }
    }

    // Save the CSV file
    $handle = fopen($csv_file, "w");
    foreach ($csv_data as $row) {
        fputcsv($handle, $row, ",");
    }
    fclose($handle);

    error_log("XLS file converted to CSV and prices updated successfully!");
}
add_action('vogo_convert_xls_to_csv_event', 'vogo_convert_xls_to_csv');


// Add "Last Updated" Column to Product Categories
add_filter('manage_edit-product_cat_columns', function ($columns) {
    $columns['last_updated'] = 'Last Updated';
    return $columns;
});

// add_filter('manage_product_cat_custom_column', function ($content, $column_name, $term_id) {
//     if ($column_name === 'last_updated') {
//         $last_updated = get_term_meta($term_id, 'last_updated', true);

//         if (!$last_updated) {
//             $last_updated = current_time('Y-m-d H:i:s');
//             update_term_meta($term_id, 'last_updated', $last_updated);
//         }

//         $content = date('Y-m-d H:i:s', strtotime($last_updated));
//     }

//     return $content;
// }, 10, 3);

// add_filter('manage_product_cat_custom_column', function($content, $column_name, $term_id) {
//     if ($column_name === 'menu_order') {
//         $term = get_term($term_id, 'product_cat');
//         if (!is_wp_error($term)) {
//             $content = is_numeric($term->term_order) ? number_format((float) $term->term_order) : '0';
//         }
//     }
//     return $content;
// }, 10, 3);
add_filter('manage_product_cat_custom_column', function($content, $column_name, $term_id) {
    if ($column_name === 'menu_order') {
        $term = get_term($term_id, 'product_cat');
        if (!is_wp_error($term)) {
            $order = isset($term->term_order) && is_numeric($term->term_order) ? (float) $term->term_order : 0;
            $content = number_format($order);
        }
    }
    return $content;
}, 10, 3);

// Update "Last Updated" Date on Category Edit
add_action('edit_product_cat', function ($term_id) {
    update_term_meta($term_id, 'last_updated', current_time('Y-m-d H:i:s'));
});


add_action('admin_menu', function() {
    // Remove WP All Import Menu
    remove_menu_page('pmxi-admin-home');

    // Re-add it above WooCommerce Menu
    add_menu_page(
        __('All Import', 'wpai'),   // Page title
        'All Import',               // Menu title
        'manage_options',           // Capability
        'pmxi-admin-home',          // Menu slug
        '',                         // Function (leave empty to keep default)
        'dashicons-database-import',// Icon
        54                          // Position (above WooCommerce, which is 55)
    );
});

add_action('admin_menu', function() {
    // Remove the existing "Test Price Modification" menu if it exists
    remove_menu_page('test-provider-prices');

    // Re-add the menu above WooCommerce
    add_menu_page(
        __('Price Modification', 'wpai'),    // Page title
        'Price Modification',                // Menu title
        'manage_options',                         // Capability
        'test-provider-prices',                   // Menu slug (keep the same slug)
        '',                                       // Function (empty, uses the original page)
        'dashicons-money',                // Icon (optional)
        54                                        // Position (above WooCommerce, which is 55)
    );
});


// Validate referral code during WooCommerce registration
add_action('woocommerce_register_post', function($username, $email, $errors) {
    // ✅ Only handle referral code validation manually
    if (!empty($_POST['referral_code'])) {
        $referral_code = sanitize_text_field($_POST['referral_code']);

        if (!preg_match('/^AB\d+$/', $referral_code)) {
            // Store custom referral code error
            set_transient('registration_error', __('Codul de recomandare trebuie să înceapă cu „AB” urmat de numere.', 'woocommerce'), 30);

            // Custom redirect only for referral validation
            wp_safe_redirect(wp_get_referer() ?: wc_get_page_permalink('myaccount'));
            exit;
        }
    }

    // ❌ DO NOT redirect if email or username exists — WooCommerce already shows errors
}, 10, 3);
add_action('woocommerce_register_form_start', 'vogo_add_shortcode_to_register_form');

add_action('woocommerce_register_form', function() {
    if ($error = get_transient('registration_error')) {
        echo '<div class="woocommerce-error">' . esc_html($error) . '</div>';
        delete_transient('registration_error');
    }
});

function vogo_add_shortcode_to_register_form() {
    echo do_shortcode('[nextend_social_login login="1" link="1" unlink="1"]');
}


// Display the stored error message after redirection
add_action('woocommerce_before_customer_login_form', function() {
    if ($error = get_transient('referral_code_error')) {
        wc_print_notice($error, 'error');
        delete_transient('referral_code_error');  // Remove the message after displaying it
    }
});

// code to remove 

add_filter( 'woocommerce_checkout_fields', function( $fields ) {
    if ( isset( $fields['shipping']['shipping_company'] ) ) {
        unset( $fields['shipping']['shipping_company'] );
    }
    return $fields;
});



add_filter('woocommerce_rest_prepare_shop_order_object', function ($response, $order, $request) {
    // Manual Notes
    $notes = wc_get_order_notes(['order_id' => $order->get_id(), 'type' => 'internal']);
    $response->data['manual_notes'] = !empty($notes) ? wp_trim_words($notes[0]->content, 8) : '';

    // Payment Mode
    $response->data['payment_mode'] = $order->get_payment_method_title();

    // Coupon
    $coupons = $order->get_coupon_codes();
    $response->data['order_coupon'] = !empty($coupons) ? implode(', ', $coupons) : '';

    // Tracking Info
    $tracking = get_post_meta($order->get_id(), '_tracking_number', true);
    $response->data['transport_info'] = $tracking ? $tracking : '';

    // Audit Link
    $response->data['order_audit'] = admin_url('admin.php?page=order_audit&order_id=' . $order->get_id());

    return $response;
}, 10, 3);

add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === 'woocommerce_page_wc-orders') {
        wp_enqueue_script('vogo-order-columns', get_stylesheet_directory_uri() . '/js/vogo-order-columns.js', ['wp-hooks', 'jquery'], '1.0', true);
    }
});

add_action('manage_woocommerce_page_wc-orders_custom_column', function ($column, $order_id) {
    if (is_object($order_id) && isset($order_id->id)) {
        $order_id = $order_id->id;
    } elseif (is_object($order_id) && method_exists($order_id, 'get_id')) {
        $order_id = $order_id->get_id();
    }

    $order = wc_get_order($order_id);

    if ($column === 'manual_notes') {
        $note = $order ? $order->get_customer_note() : '';
        echo $note ? esc_html($note) : '-';
    }

    if ($column === 'payment_mode') {
        echo $order ? $order->get_payment_method_title() : '-';
    }

    if ($column === 'order_coupon') {
        $coupons = $order ? $order->get_coupon_codes() : [];
        echo !empty($coupons) ? implode(', ', $coupons) : '-';
    }
    if ($column === 'order_tags') {
        $product_tags = [];
 
        if ($order) {
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                $tags = get_the_terms($product_id, 'product_tag');
 
                if ($tags && !is_wp_error($tags)) {
                    foreach ($tags as $tag) {
                        $product_tags[] = $tag->name;
                    }
                }
            }
        }
 
        $product_tags = array_unique($product_tags); // Remove duplicates
        echo !empty($product_tags) ? esc_html(implode(', ', $product_tags)) : '-';
    }

    if ($column === 'transport_info') {
        $awb = get_post_meta($order_id, '_sameday_awb', true);
        $status = get_post_meta($order_id, '_sameday_parcel_status', true);
        $tracking_link = get_post_meta($order_id, '_sameday_tracking_link', true);
 
        if ($awb) {
            echo 'AWB: ' . esc_html($awb) . '<br>';
        }
        if ($status) {
            echo 'Status: ' . esc_html($status) . '<br>';
        }
        if ($tracking_link) {
            echo '<a href="' . esc_url($tracking_link) . '" target="_blank">Track</a>';
        }
        if (!$awb && !$status) {
            echo '-';
        }
    }

    if ($column === 'order_audit') {
        echo '<a href="' . admin_url('post.php?post=' . $order_id . '&action=edit') . '">View</a>';
    }
}, 10, 2);

add_action('admin_head', function() {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'woocommerce_page_wc-orders') {
        echo '<style>
            .woocommerce-page .wp-list-table.widefat {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        </style>';
    }
});

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

// Add Order Tags metabox (Compatible with Custom Orders Table UI)
// add_action('add_meta_boxes', function () {
//     $screen = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') && wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
//         ? wc_get_page_screen_id('shop-order')
//         : 'shop_order';

//     add_meta_box(
//         'order_tags_box',
//         'Order Tags',
//         function ($object) {
//             $order = is_a($object, 'WP_Post') ? wc_get_order($object->ID) : $object;
//             $tags = get_post_meta($order->get_id(), '_order_tags', true);
//             echo '<input type="text" name="order_tags" value="' . esc_attr($tags) . '" style="width:100%;" placeholder="Comma-separated tags" />';
//         },
//         $screen,
//         'side',
//         'high'
//     );
// });

// add_action('woocommerce_update_order', function ($order_id) {
//     if (isset($_POST['order_tags'])) {
//         update_post_meta($order_id, '_order_tags', sanitize_text_field($_POST['order_tags']));
//     }
// });

add_action('woocommerce_checkout_update_order_meta', function ($order_id, $data) {
    $order = wc_get_order($order_id);
    $product_tags = [];

    if ($order) {
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $tags = get_the_terms($product_id, 'product_tag');

            if ($tags && !is_wp_error($tags)) {
                foreach ($tags as $tag) {
                    $product_tags[] = $tag->slug;
                }
            }
        }
    }

    $product_tags = array_unique($product_tags); // Remove duplicates
    update_post_meta($order_id, '_product_tags', implode(',', $product_tags));
}, 10, 2);

add_action('pmxi_saved_post', function ($post_id, $xml_node, $import_id) {
    if (get_post_type($post_id) !== 'product') {
        return; // Only for products
    }

    $parent_category = trim((string) $xml_node->ParentCategory);
    $child_category  = trim((string) $xml_node->ChildCategory);

    if (empty($parent_category)) {
        error_log("❌ Parent category missing for product {$post_id}. Skipping.");
        return;
    }

    $category_ids = [];

    // ✅ Check Parent Category
    $parent_term = term_exists($parent_category, 'product_cat');
    if ($parent_term && is_array($parent_term)) {
        $parent_id = $parent_term['term_id'];
        $category_ids[] = $parent_id;
    } else {
        error_log("❌ Parent category '{$parent_category}' does not exist. Skipping product {$post_id}.");
        return;
    }

    // ✅ Check Child Category
    if (!empty($child_category)) {
        $child_term = term_exists($child_category, 'product_cat', $parent_id);
        if ($child_term && is_array($child_term)) {
            $child_id = $child_term['term_id'];
            $category_ids[] = $child_id;
        } else {
            error_log("❌ Child category '{$child_category}' does not exist under '{$parent_category}'. Skipping.");
            return;
        }
    }

    // ✅ Assign categories
    if (!empty($category_ids)) {
        wp_set_post_terms($post_id, $category_ids, 'product_cat');
        error_log("✅ Categories assigned to product {$post_id}: " . implode(',', $category_ids));
    }
}, 10, 3);



add_action('woocommerce_after_shop_loop', 'add_category_pagination', 10);

function add_category_pagination() {
    global $wp_query;
    
    $total_pages = $wp_query->max_num_pages;
    if ($total_pages > 1) {
        echo '<div class="pagination all-pages">';
        echo paginate_links(array(
            'base'    => get_pagenum_link(1) . '%_%',
            'format'  => 'page/%#%/',
            'current' => max(1, get_query_var('paged', 1)),
            'total'   => $total_pages,
        ));
        echo '</div>';
    }
}

// ✅ 1. Register the custom taxonomy
/* 25-09
add_action('init', function () {
    register_taxonomy('product_provider', 'product', [
        'label' => 'Product Providers',
        'hierarchical' => true,
        'public' => true,
        'show_admin_column' => true,
        'rewrite' => ['slug' => 'provider'],
    ]);
});
*/

// ✅ 2. Add dropdown filter in admin Products list
add_action('restrict_manage_posts', 'vogo_add_provider_filter_to_products');
function vogo_add_provider_filter_to_products() {
    global $pagenow, $typenow;

    // Only show on product post type admin page
    if ($typenow !== 'product' || $pagenow !== 'edit.php') return;
	

    $taxonomy = 'product_provider';
    $info = get_taxonomy($taxonomy);
    if (!$info) return;

    $selected = isset($_GET[$taxonomy]) ? $_GET[$taxonomy] : '';

    echo '<div id="vogo-provider-filter-wrapper" style="display:inline-block; margin-left:10px;">';

    wp_dropdown_categories([
        'show_option_all' => __('All ' . $info->label),
        'taxonomy'        => $taxonomy,
        'name'            => $taxonomy,
        'orderby'         => 'name',
        'selected'        => $selected,
        'show_count'      => true,
        'hide_empty'      => false,
        'value_field'     => 'term_id',
    ]);

    echo '</div>';
}

// ✅ 3. Filter the query based on selected Provider
add_filter('parse_query', 'vogo_filter_products_by_provider_in_admin');
function vogo_filter_products_by_provider_in_admin($query) {
    global $pagenow;

    $taxonomy = 'product_provider';

    if (
        $pagenow === 'edit.php' &&
        isset($_GET['post_type']) && $_GET['post_type'] === 'product' &&
        isset($_GET[$taxonomy]) && is_numeric($_GET[$taxonomy]) && $_GET[$taxonomy] != 0
    ) {
        $term = get_term_by('id', $_GET[$taxonomy], $taxonomy);
        if ($term) {
            $query->query_vars[$taxonomy] = $term->slug;
        }
    }
}

function enqueue_select2_for_my_articles() {
    if (is_account_page()) {
        wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);
        wp_add_inline_script('select2-js', 'jQuery(function($){ $("#article_city").select2(); });');
    }
}
add_action('wp_enqueue_scripts', 'enqueue_select2_for_my_articles');



// add_filter('woocommerce_registration_redirect', function($redirect) {
//     if (!empty($_POST) && wc_notice_count('error') > 0) {
//         return wp_get_referer() ?: get_permalink();
//     }
//     return $redirect;
// });

// Force register form to stay on /register/ after submission or error
add_filter( 'woocommerce_registration_redirect', 'stay_on_register_page_after_submit' );
function stay_on_register_page_after_submit( $redirect ) {
    if ( ! empty( wc_get_notices( 'error' ) ) ) {
        return home_url( '/register/' ); // Replace with your actual register page URL if needed
    }
    return $redirect;
}

add_filter('woocommerce_enable_myaccount_registration', '__return_false');



// Tag all order status
function custom_register_order_statuses() {
    register_post_status( 'wc-postponed', array(
        'label'                     => 'Postponed',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Postponed <span class="count">(%s)</span>', 'Postponed <span class="count">(%s)</span>' )
    ) );
	
	    register_post_status( 'wc-statusnou', array(
        'label'                     => 'Status nou',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Statusnou <span class="count">(%s)</span>', 'Statusnou <span class="count">(%s)</span>' )
    ) );

    register_post_status( 'wc-confirmed', array(
        'label'                     => 'Confirmed',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Confirmed <span class="count">(%s)</span>', 'Confirmed <span class="count">(%s)</span>' )
    ) );
}
add_action( 'init', 'custom_register_order_statuses' );

// Add to WooCommerce status list
function custom_add_order_statuses( $order_statuses ) {
    $new_order_statuses = array();

    // Insert after 'wc-processing'
    foreach ( $order_statuses as $key => $status ) {
        $new_order_statuses[ $key ] = $status;

        if ( 'wc-processing' === $key ) {
            $new_order_statuses['wc-postponed'] = 'Postponed';
            $new_order_statuses['wc-confirmed'] = 'Confirmed';
        }
    }

    return $new_order_statuses;
}
add_filter( 'wc_order_statuses', 'custom_add_order_statuses' );

// Trigger "Processing Order" email when status is changed to Confirmed
add_action( 'woocommerce_order_status_confirmed', function( $order_id ) {
    $order = wc_get_order( $order_id );
    WC()->mailer()->emails['WC_Email_Customer_Processing_Order']->trigger( $order_id );
} );

// Trigger "On-Hold" email when status is changed to Postponed
add_action( 'woocommerce_order_status_postponed', function( $order_id ) {
    $order = wc_get_order( $order_id );
    WC()->mailer()->emails['WC_Email_Customer_On_Hold_Order']->trigger( $order_id );
} );




add_action( 'woocommerce_email_after_order_table', 'add_product_feedback_to_completed_email', 10, 4 );

function add_product_feedback_to_completed_email( $order, $sent_to_admin, $plain_text, $email ) {
    if ( $email->id !== 'customer_completed_order' ) {
        return;
    }

    echo '<h2>Ne-ar face plăcere să primim și feedbackul dumneavoastră!</h2>';
    $product_name = 'test';
    // Loop through order items
    // get order id
    $order_id = $order->get_id();
    echo '<p>Sperăm că te bucuri de comanda ta</p>';
    $feedback_url = 'https://test07.vogo.family/my-account/view-order/' . $order_id;
    $qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode( $feedback_url ) . "&size=150x150";
    echo '<p><a href="' . esc_url( $feedback_url ) . '" target="_blank" style="background:#0073aa;color:#fff;padding:10px 15px;border-radius:5px;text-decoration:none;">Lasă o Recenzie</a></p>';
    echo '<p><img src="' . esc_url( $qr_code_url ) . '"  width="150" height="150" /></p>'; 
    echo '<a href="' . esc_url( $qr_code_url ) . '" download="review-' . sanitize_title( $product_name ) . '-qr.png" style="display:inline-block;margin-top:10px;background:#0073aa;color:#fff;padding:8px 14px;text-decoration:none;border-radius:5px;">Descarcă Codul QR</a>';
}

add_shortcode('print_order_page', function() {
    if (!is_user_logged_in()) {
        return '<p>Trebuie să fii autentificat pentru a vedea această pagină.</p>';
    }

    ob_start(); ?>
    <div id="printable-area" style="text-align: center; padding: 40px;">
        <div id="order-feedback-section">
            <p>Loading order info...</p>
        </div>
        <p><button onclick="window.print()" style="margin-top:20px;padding:10px 20px;background:#333;color:#fff;border:none;border-radius:5px;">Print</button></p>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const params = new URLSearchParams(window.location.search);
        const orderId = params.get("order_id");
        console.log(params);
        if (!orderId) {
            document.getElementById("order-feedback-section").innerHTML = "<p>ID-ul comenzii nu a fost furnizat.</p>";
            return;
        }

        fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=get_order_info&order_id=' + orderId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const html = `
                        <h2>Order #${data.order_number}</h2>
                        <p>Scan the QR code or use the link below to review your order:</p>
                        <p><img src="${data.qr_code_url}" width="150" height="150" /></p>
                        <p><a href="${data.feedback_url}" target="_blank">${data.feedback_url}</a></p>
                    `;
                    document.getElementById("order-feedback-section").innerHTML = html;
                } else {
                    document.getElementById("order-feedback-section").innerHTML = "<p>" + data.message + "</p>";
                }
            });
    });
    </script>
    <?php
    return ob_get_clean();
});

add_action('wp_ajax_get_order_info', 'get_order_info_ajax');
add_action('wp_ajax_nopriv_get_order_info', 'get_order_info_ajax');

function get_order_info_ajax() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Trebuie să fii autentificat pentru a vedea această pagină.']);
    }

    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    $order = wc_get_order($order_id);

    if (!$order || (!current_user_can('manage_woocommerce') && $order->get_user_id() !== get_current_user_id())) {
        wp_send_json_error(['message' => 'Comanda nu a fost găsită sau nu aveți permisiunea să o vizualizați.']);
    }

    $feedback_url = site_url('/order-review?order_id=' . $order_id);
    $qr_code_url = 'https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=' . urlencode($feedback_url);

    wp_send_json_success([
        'order_number' => $order->get_order_number(),
        'feedback_url' => $feedback_url,
        'qr_code_url'  => $qr_code_url,
    ]);
}

// ✅ Show Order Feedback QR for HPOS-compatible WooCommerce
add_action('woocommerce_admin_order_data_after_order_details', 'render_order_feedback_meta_box');
function render_order_feedback_meta_box($order) {
    if (!$order instanceof WC_Order) {
        return;
    }

    $feedback_url = site_url('/my-account/view-order/' . $order->get_id());
    $qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode( $feedback_url ) . "&size=150x150";

    echo '<div class="order_feedback_qr" style="padding:15px 0;">';
    echo '<h4>Cod QR pentru Feedback</h4>';
  //  echo '<p>Scan the QR code or use the link below to review the order:</p>';
 //   echo '<p><img src="' . esc_url($qr_code_url) . '" width="150" height="150" /></p>';
    echo '<p><a href="' . esc_url($feedback_url) . '" target="_blank">' . esc_html($feedback_url) . '</a></p>';
    echo '</div>';
    echo '<p><button type="button" onclick="printOrderFeedbackQR()" style="margin-top:10px;padding:6px 12px;background:#0073aa;color:#fff;border:none;border-radius:4px;">Printează QR</button></p>';

echo '<script>
function printOrderFeedbackQR() {
    const qrContainer = document.querySelector(".order_feedback_qr");
    if (!qrContainer) return;

    const img = qrContainer.querySelector("img");
    const link = qrContainer.querySelector("a");
    const html = `
        <html>
        <head>
            <title>Print QR</title>
            <style>
                body { font-family: sans-serif; text-align: center; padding: 20px; }
                img { max-width: 100%; height: auto; }
                a { display: block; margin-top: 10px; font-size: 14px; }
            </style>
        </head>
        <body>
            <h2>Order Feedback QR</h2>
            ${img ? img.outerHTML : ""}
            ${link ? link.outerHTML : ""}
        </body>
        </html>
    `;

    const win = window.open("", "_blank", "width=400,height=600");
    win.document.open();
    win.document.write(html);
    win.document.close();

    // Wait 500ms to ensure content is fully rendered before printing
    setTimeout(function () {
        win.focus();
        win.print();
        win.close();
    }, 500);
}
</script>';
}

add_action('admin_menu', function () {
    add_submenu_page(
        'woocommerce',
        'Order QR Codes',
        'Order QR Codes',
        'manage_woocommerce',
        'order-qr-codes',
        'render_order_qr_page'
    );
});

function render_order_qr_page() {
    // Get pagination and filter parameters
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 20;
    $offset = ($paged - 1) * $per_page;

    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $selected_provider = isset($_GET['provider']) ? sanitize_text_field($_GET['provider']) : '';

    // Prepare filter args (remove meta_query and customer_id; we will filter manually)
    $args = [
        'limit'   => -1, // get all, filter manually
        'orderby' => 'date',
        'order'   => 'DESC',
        'type' => 'shop_order',
    ];

    if ($status_filter) {
        $args['status'] = [$status_filter];
    } else {
        $args['status'] = ['completed', 'processing'];
    }

    // Get all orders for this status, then filter manually for search and provider
    $all_orders = wc_get_orders($args);
    $filtered_orders = [];
    $normalized_search = trim(strtolower($search));
    foreach ($all_orders as $order) {
        // Provider filter
        $matches_provider = empty($selected_provider) ? true : false;
        if (!empty($selected_provider)) {
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                $product_id = $product && $product->is_type('variation') ? $product->get_parent_id() : $item->get_product_id();
                $terms = get_the_terms($product_id, 'product_provider');
                if (!empty($terms) && !is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        if ($term->slug === $selected_provider) {
                            $matches_provider = true;
                            break 2;
                        }
                    }
                }
            }
            if (!$matches_provider) continue;
        }

        $user = $order->get_user();
        $billing_first_name = $order->get_billing_first_name();
        $billing_last_name = $order->get_billing_last_name();
        $billing_email = $order->get_billing_email();
        $customer_name = $user ? $user->display_name : $billing_first_name . ' ' . $billing_last_name;

        $search = $normalized_search;
        if (
            empty($search) ||
            (stripos(strtolower((string)$billing_first_name), $search) !== false) ||
            (stripos(strtolower((string)$billing_last_name), $search) !== false) ||
            (stripos(strtolower((string)$billing_email), $search) !== false) ||
            ($user && stripos(strtolower($user->display_name), $search) !== false)
        ) {
            $filtered_orders[] = $order;
        }
    }
    $total_orders = count($filtered_orders);
    $total_pages = ceil($total_orders / $per_page);

    // $orders = array_slice($filtered_orders, $offset, $per_page);
    $orders = array_slice($filtered_orders, $offset, $per_page);

    // Filter Form
    // #ORDER_PAGES
    echo '<div class="wrap"><h1>Order Feedback QR Codes</h1>';
    echo '<form method="get" style="margin-bottom: 20px;">';
    echo '<input type="hidden" name="page" value="' . esc_attr($_GET['page']) . '" />';
    echo '<input type="text" name="search" placeholder="Search by name/email" value="' . esc_attr($search) . '" />';
    echo '<select name="status">
            <option value="">All Statuses</option>
            <option value="processing"' . selected($status_filter, 'processing', false) . '>Processing</option>
            <option value="completed"' . selected($status_filter, 'completed', false) . '>Completed</option>
        </select>';
    // Provider dropdown
    $providers = get_terms(['taxonomy' => 'product_provider', 'hide_empty' => false]);
    $selected_provider = isset($_GET['provider']) ? sanitize_text_field($_GET['provider']) : '';
    echo '<select name="provider"><option value="">All Providers</option>';
    foreach ($providers as $provider) {
        $selected = selected($selected_provider, $provider->slug, false);
        echo "<option value='{$provider->slug}' $selected>{$provider->name}</option>";
    }
    echo '</select>';
    echo '<input type="submit" class="button" value="Filter" />';
    echo '</form>';

    // Print Button
    echo '<p><button onclick="window.print()" class="button button-primary">Print Page</button></p>';
    echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px;">';

    foreach ($orders as $order) {
        $order_id = $order->get_id();
        $user = $order->get_user();
        $customer_name = $user ? $user->display_name : $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

        $feedback_url = site_url('/my-account/view-order/' . $order_id);
        $qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($feedback_url) . '&size=150x150';

        echo '<div style="border:1px solid #ddd; padding:10px; max-width:100%; text-align:center; background:#fff; box-sizing:border-box;">';
        echo '<h3>Order #' . esc_html($order_id) . '</h3>';
        echo '<p><strong>' . esc_html($customer_name) . '</strong></p>';
        echo '<p><img src="' . esc_url($qr_code_url) . '" width="150" height="150" /></p>';
        echo '<p><a href="' . esc_url($feedback_url) . '" target="_blank">' . esc_html($feedback_url) . '</a></p>';
        echo '</div>';
    }

    echo '</div>';

    // Before Pagination block: set $page_slug
    $page_slug = $_GET['page'] ?? 'order-qr-codes'; // fallback to submenu slug

    // Pagination links
    echo '<p>Total orders: ' . $total_orders . '</p>';
    echo '<p>Total pages: ' . $total_pages . '</p>';
    if ($total_pages > 1) {
        echo '<p style="color:red;">🔁 Pagination is active. Pages: ' . $total_pages . '</p>';
        echo '<div style="margin-top:20px;">';
        for ($i = 1; $i <= $total_pages; $i++) {
            $url = admin_url('admin.php?page=' . esc_attr($page_slug) . '&paged=' . $i . '&status=' . esc_attr($status_filter) . '&search=' . esc_attr($search) . '&provider=' . esc_attr($selected_provider));
            echo '<a class="button" style="margin-right:5px;" href="' . esc_url($url) . '">' . $i . '</a>';
        }
        echo '</div>';
    }

    // Print-friendly styles
    echo '<style>
    @media print {
        .wrap h1, .wrap form, .wrap button, .wrap div[style*="margin-top"] { display: none !important; }
        body { background: #fff; }
        div.wrap { margin: 0; padding: 0; }
        div.wrap > div { page-break-inside: avoid; }
        div[style*="grid-template-columns"] {
            display: grid !important;
            grid-template-columns: repeat(5, 1fr) !important;
            gap: 10px !important;
        }
        div[style*="grid-template-columns"] > div {
            break-inside: avoid;
            page-break-inside: avoid;
            max-width: 100%;
        }
    }
    </style>';
}
// Add custom collapsible behavior for Product Categories admin page
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook === 'edit-tags.php' && isset($_GET['taxonomy']) && $_GET['taxonomy'] === 'product_cat') {
        add_action('admin_footer', function () {
            ?>
            <script>
            jQuery(document).ready(function ($) {
                // Loop through each row and add toggle to level-0 categories
                $('#the-list tr').each(function () {
                    const $row = $(this);
                    const levelMatch = $row.attr('class').match(/level-(\d+)/);
                    const level = levelMatch ? parseInt(levelMatch[1]) : 0;

                    $row.attr('data-level', level);

                    // Add toggle only to level-0
                    if (level === 0) {
                        const $nameCell = $row.find('.name.column-name');
                        $nameCell.prepend('<span class="toggle-cat" style="cursor:pointer; margin-right:8px;">➖</span>');
                    }
                });

                // Toggle children rows
                $(document).on('click', '.toggle-cat', function () {
                    const $toggle = $(this);
                    const $parentRow = $toggle.closest('tr');
                    const parentLevel = parseInt($parentRow.attr('data-level'));
                    let $nextRow = $parentRow.next();

                    let shouldCollapse = $toggle.text() === '➖';
                    $toggle.text(shouldCollapse ? '➕' : '➖');

                    while ($nextRow.length) {
                        const nextLevel = parseInt($nextRow.attr('data-level'));
                        if (isNaN(nextLevel) || nextLevel <= parentLevel) break;

                        if (shouldCollapse) {
                            $nextRow.hide();
                        } else {
                            // Only show immediate children unless their own parent is collapsed
                            if (nextLevel === parentLevel + 1) {
                                $nextRow.show();
                                $nextRow.find('.toggle-cat').text('➖'); // reset their toggle state
                            }
                        }

                        $nextRow = $nextRow.next();
                    }
                });
            });
            </script>
            <style>
                .toggle-cat {
                    font-weight: bold;
                    font-size: 16px;
                    cursor: pointer;
                    user-select: none;
                }
            </style>
            <?php
        });
    }
});

add_action('woocommerce_after_shop_loop_item_title', 'custom_price_and_cart_wrap', 9);
function custom_price_and_cart_wrap() {
    echo '<div class="price-cart-wrapper">';
}

add_action('woocommerce_after_shop_loop_item', 'custom_price_and_cart_wrap_close', 19);
function custom_price_and_cart_wrap_close() {
    echo '</div>';
}



add_action('wp_footer', 'add_mobile_sorting_label_script');

add_action('wp_footer', 'add_mobile_sorting_label_script');

function add_mobile_sorting_label_script() {
    if (is_shop() || is_product_category()) { // Only on product archive pages
        ?>
        <style>
        @media (max-width: 768px) {
            .mobile-sort-wrapper {
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                margin-bottom: 15px;
                padding: 10px;
            }
            .mobile-sort-wrapper label {
                font-weight: 600;
                margin-bottom: 8px;
                font-size: 16px;
            }
            .mobile-sort-wrapper select.orderby {
                width: 100%;
                padding: 8px;
                font-size: 15px;
            }
        }
        </style>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.innerWidth <= 768) {
                const select = document.querySelector('form.woocommerce-ordering select.orderby');
                if (select && !document.querySelector('.mobile-sort-wrapper')) {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'mobile-sort-wrapper';

                    const label = document.createElement('label');
                    label.textContent = 'Order By:';
                    label.setAttribute('for', select.id || 'woocommerce-orderby');

                    // Move select inside wrapper
                    const parent = select.parentNode;
                    parent.insertBefore(wrapper, select);
                    wrapper.appendChild(label);
                    wrapper.appendChild(select);
                }
            }
        });
        </script>
        <?php
    }
}



add_action('woocommerce_new_product_review_comment', 'notify_provider_on_new_review_html_email');
function notify_provider_on_new_review_html_email($comment_id) {
    $comment = get_comment($comment_id);
    if (!$comment) return;

    $post_id = $comment->comment_post_ID;

    if (get_post_type($post_id) !== 'product') return;

    $rating = get_comment_meta($comment_id, 'rating', true);
    if (!$rating) return;

    $terms = get_the_terms($post_id, 'product_provider');
    if (!$terms || is_wp_error($terms)) {
        error_log("❌ No provider terms found for product ID $post_id.");
        return;
    }

    $provider = $terms[0];
    $email = get_term_meta($provider->term_id, 'provider_email', true);

    if (!$email || !is_email($email)) {
        error_log("❌ Invalid or missing provider email for term ID " . $provider->term_id);
        return;
    }

    $product_title = get_the_title($post_id);
    $reviewer = $comment->comment_author;
    $review = $comment->comment_content;
    $product_link = get_permalink($post_id) . '#comment-' . $comment_id;

    ob_start(); ?>
    <p>Hello,</p>
    <p>A new review has been submitted for your product <strong><?php echo esc_html($product_title); ?></strong>:</p>
    <p><strong>Rating:</strong> <?php echo esc_html($rating); ?>/5</p>
    <p><strong>Reviewer:</strong> <?php echo esc_html($reviewer); ?></p>
    <p><strong>Review:</strong><br><?php echo nl2br(esc_html($review)); ?></p>
    <p><a href="<?php echo esc_url($product_link); ?>">View this product</a></p>
    <?php
    $message = ob_get_clean();

    $mailer = WC()->mailer();
    $wrapped_message = $mailer->wrap_message("New Product Review", $message);
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    $sent = wp_mail($email, "New Review for Product: $product_title", $wrapped_message, $headers);

    if (!$sent) {
        error_log("❌ Failed to send review email to $email");
    } else {
        error_log("✅ Review email sent to $email for product ID $post_id");
    }
}
/* --------------------------------------------------------------------------
 *  PER‑PROVIDER ORDER NOTIFICATIONS
 * -------------------------------------------------------------------------- */

/**
 * Fires when order is first marked Processing (paid) – HPOS & legacy safe
 */
add_action( 'woocommerce_order_status_processing', 'notify_providers_about_order', 20, 2 );
add_action( 'woocommerce_order_status_completed',  'notify_providers_about_order', 20, 2 );

/**
 * Send one HTML‑styled email (WooCommerce‑like) to each provider
 * whenever an order first reaches Processing or Completed.
 *
 * @param int         $order_id
 * @param WC_Order|null $order
 */
function notify_providers_about_order( $order_id, $order = null ) {
//TAG#ORDER-SPLIT-EMAIL
	/* -------------------------------------------------------------
	 * 1. Get the order object safely
	 * ----------------------------------------------------------- */
	if ( ! $order ) {
		$order = wc_get_order( $order_id );
	}
	if ( ! $order ) {
		return;
	}

	/* -------------------------------------------------------------
	 * 2. Gather items by provider term
	 * ----------------------------------------------------------- */
	$providers = []; // [ term_id => [ email, name, items[] ] ]

	foreach ( $order->get_items( 'line_item' ) as $item ) {
		$product_id = $item->get_product_id();
		$terms      = wp_get_post_terms( $product_id, 'product_provider' );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			continue;
		}

		$term  = $terms[0]; // one provider per product
		$email = sanitize_email( get_term_meta( $term->term_id, 'provider_email', true ) );

		if ( ! $email || ! is_email( $email ) ) {
			continue;
		}

		if ( ! isset( $providers[ $term->term_id ] ) ) {
			$providers[ $term->term_id ] = [
				'email' => $email,
				'name'  => $term->name,
				'items' => [],
			];
		}

		$providers[ $term->term_id ]['items'][] = $item;
	}

	if ( empty( $providers ) ) {
		return; // nothing to notify
	}

	/* -------------------------------------------------------------
	 * 3. Build & send one email per provider
	 * ----------------------------------------------------------- */
	$store_name   = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$order_number = $order->get_order_number();
	$currency     = $order->get_currency();

	foreach ( $providers as $provider ) {

		/* ---- a) build rows for this provider’s items ------------- */
		$rows = '';
		foreach ( $provider['items'] as $it ) {
			$rows .= sprintf(
				'<tr>
					<td style="padding:8px 10px;border:1px solid #eee;">%s</td>
					<td style="padding:8px 10px;text-align:center;border:1px solid #eee;">%s</td>
					<td style="padding:8px 10px;text-align:right;border:1px solid #eee;">%s</td>
				</tr>',
				esc_html( $it->get_name() ),
				esc_html( $it->get_quantity() ),
				wc_price( $it->get_subtotal(), [ 'currency' => $currency ] )
			);
		}

		/* ---- b) assemble WooCommerce‑style message --------------- */
		$body = sprintf(
			'<body style="margin:0;padding:0;background:#f5f5f5;">
				<table role="presentation" width="100%%" cellspacing="0" cellpadding="0" style="background:#f5f5f5;">
					<tr>
						<td align="center" style="padding:30px 10px;">
							<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:600px;background:#ffffff;border:1px solid #dedede;">
								<tr>
									<td style="background:#2E4F26;padding:20px;text-align:center;">
										<h1 style="margin:0;font-size:22px;line-height:1.3;color:#ffffff;font-weight:bold;">%s</h1>
									</td>
								</tr>
								<tr>
									<td style="padding:30px;font-size:14px;color:#636363;">
										<p style="margin:0 0 12px;">%s</p>
										<p style="margin:0 0 25px;">%s</p>

										<table role="presentation" width="100%%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-size:14px;">
											<thead>
												<tr>
													<th align="left"   style="padding:8px 10px;border:1px solid #eee;background:#f0f0f0;">%s</th>
													<th align="center" style="padding:8px 10px;border:1px solid #eee;background:#f0f0f0;">%s</th>
													<th align="right"  style="padding:8px 10px;border:1px solid #eee;background:#f0f0f0;">%s</th>
												</tr>
											</thead>
											<tbody>%s</tbody>
										</table>

										<p style="margin:25px 0 0;">%s</p>
									</td>
								</tr>
								<tr>
									<td style="background:#f5f5f5;padding:20px;text-align:center;font-size:12px;color:#999999;">
										&copy; %s %s
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>
			</body>',
			/* header */                       esc_html( $store_name ),
			/* intro 1 */                      sprintf( __( 'Hello %s,', 'woocommerce' ), esc_html( $provider['name'] ) ),
			/* intro 2 */                      sprintf( __( 'You have new items in Order #%s:', 'woocommerce' ), esc_html( $order_number ) ),
			/* table headings */               esc_html__( 'Product', 'woocommerce' ),
			                                   esc_html__( 'Qty', 'woocommerce' ),
			                                   esc_html__( 'Subtotal', 'woocommerce' ),
			/* rows */                         $rows,
			/* footer line above copyright */  esc_html__( 'For full order details please log in to your dashboard.', 'woocommerce' ),
			/* year */                         date_i18n( 'Y' ),
			/* footer store name */            esc_html( $store_name )
		);

		/* ---- c) send the email ----------------------------------- */
		wp_mail(
			$provider['email'],
			sprintf( '[%s] ' . __( 'Order #%s – new items for you', 'woocommerce' ), $store_name, $order_number ),
			$body,
			[ 'Content-Type: text/html; charset=UTF-8' ]
		);
	}
}



add_action('elementor/query/fix_manual_category_order', function( $query ) {
    // Force order by menu_order (term_order) ASC
    $query->set('orderby', 'menu_order');
    $query->set('order', 'ASC');
});

/**
 * Filters the terms retrieved for a taxonomy query, allowing custom sorting for product categories.
 *
 * @param array        $terms      Array of term objects retrieved for the query.
 * @param array|string $taxonomies Taxonomy or array of taxonomies queried.
 * @param array        $args       Array of arguments for retrieving terms.
 * @param WP_Term_Query $term_query The WP_Term_Query instance.
 *
 * @return array Filtered array of terms.
 *
 * This filter applies custom sorting logic to the 'product_cat' taxonomy terms:
 * - If the 'orderby' query parameter is set to 'term_order', or if no 'orderby' parameter is provided,
 *   terms are sorted by their 'term_order' property.
 * - Terms with a 'term_order' of 0 are pushed to the end of the list.
 * - Sorting is skipped on product edit/add screens in the WordPress admin.
 *
 * Hooked to the 'get_terms' filter with a priority of 20 and accepts 4 arguments.
 */


add_filter( 'woocommerce_placeholder_img_src', 'vogo_custom_placeholder_image' );

// TAG#DEFAULT-PRODUCT-IMAGE
function vogo_custom_placeholder_image( $src ) {
    return 'https://test07.vogo.family/wp-content/uploads/2025/04/no-image-icon-23500.jpg';
}

// position of the product

// Add a new meta box for Product Position


add_filter('manage_product_cat_custom_column', function($content, $column_name, $term_id) {
    if ($column_name === 'menu_order') {
        $term = get_term($term_id, 'product_cat');
        if (!is_wp_error($term)) {
            $order = isset($term->term_order) && is_numeric($term->term_order) ? (float) $term->term_order : 0;
            $content = number_format($order);
        }
    }
    return $content;
}, 10, 3);

add_filter('manage_edit-product_cat_sortable_columns', function($sortable_columns) {
    $sortable_columns['menu_order'] = 'term_order';
    return $sortable_columns;
});

// add_action('pre_get_terms', function($query) {
//     if (
//         is_admin() &&
//         isset($_GET['orderby']) && $_GET['orderby'] === 'term_order' &&
//         isset($_GET['taxonomy']) && $_GET['taxonomy'] === 'product_cat'
//     ) {
//         $query->query_vars['orderby'] = 'none'; // Stop WP from adding ASC again
//     }
// });

// add_filter('request', function($query_vars) {
//     if (
//         is_admin() &&
//         isset($_GET['orderby']) && $_GET['orderby'] === 'term_order' &&
//         isset($_GET['taxonomy']) && $_GET['taxonomy'] === 'product_cat'
//     ) {
//         // Trick WordPress: use a fake 'orderby' to bypass WP's default logic
//         $query_vars['orderby'] = 'custom_term_order';
//     }
//     return $query_vars;
// });
// add_filter('terms_clauses', function($clauses, $taxonomies, $args) {
//     if (!in_array('product_cat', (array) $taxonomies)) {
//         return $clauses;
//     }

//     if (
//         is_admin() &&
//         isset($_GET['orderby']) && $_GET['orderby'] === 'term_order' &&
//         isset($_GET['taxonomy']) && $_GET['taxonomy'] === 'product_cat'
//     ) {
//         global $wpdb;

//         $order = (isset($_GET['order']) && strtolower($_GET['order']) === 'desc') ? 'DESC' : 'ASC';

//         // 🛠 Kill native "ASC" from $args by removing 'orderby'
//       // $args['orderby'] = '';

//         // ✅ Add our own full SQL order clause
//         $clauses['orderby'] = "ORDER BY (t.term_order = 0), t.term_order $order";

//         error_log('✅ Fixed SQL clause: ' . $clauses['orderby']);
//     }

//     return $clauses;
// }, 99, 3);

// add_filter('terms_clauses', function($clauses, $taxonomies, $args) {
//     if (!in_array('product_cat', (array) $taxonomies)) return $clauses;

//     if (
//         is_admin() &&
//         isset($_GET['orderby']) && $_GET['orderby'] === 'term_order' &&
//         isset($_GET['taxonomy']) && $_GET['taxonomy'] === 'product_cat'
//     ) {
//         global $wpdb;
//         $dir = ( isset( $_GET['order'] ) && strtolower( $_GET['order'] ) === 'desc' ) ? 'DESC' : 'ASC';
//         // One clean clause:  zeros‑last flag, then term_order ASC|DESC
//         $clauses['orderby'] = "ORDER BY term_order {$dir}";
//     }

//     return $clauses;
// }, 99, 3);


/**
 * Stop WP_Term_Query from appending its own ASC/DESC when we’re
 * sorting Product Categories by Position.
 */
// add_action( 'parse_term_query', function ( WP_Term_Query $query ) {

//     // Only touch the Product-Categories screen when the Position header is clicked
//     if ( ! is_admin()
//          || empty( $_GET['taxonomy'] ) || $_GET['taxonomy'] !== 'product_cat'
//          || empty( $_GET['orderby'] )  || $_GET['orderby']  !== 'term_order' ) {
//         return;
//     }

//     // Stop WP_Term_Query from appending its own  ASC/DESC
//     $query->query_vars['orderby'] = 'none';

// }, PHP_INT_MAX, 1 );

// add_filter('terms_clauses', function($clauses, $taxonomies, $args) {
//     if (
//         is_admin() &&
//         isset($_GET['orderby'], $_GET['taxonomy']) &&
//         $_GET['orderby'] === 'term_order' &&
//         $_GET['taxonomy'] === 'product_cat' &&
//         in_array('product_cat', (array) $taxonomies)
//     ) {
//         global $wpdb;

//         $order = (isset($_GET['order']) && strtolower($_GET['order']) === 'desc') ? 'DESC' : 'ASC';

//         // ✅ Valid SQL ORDER BY clause
//         $clauses['orderby'] = "ORDER BY (t.term_order = 0), t.term_order $order";

//         error_log("✅ Custom ORDER BY clause applied: " . $clauses['orderby']);
//     }

//     return $clauses;
// }, 99, 3);

// add_filter( 'terms_clauses', function ( $clauses, $taxonomies, $args ) {

//     if (
//         is_admin() &&
//         isset( $_GET['orderby'], $_GET['taxonomy'] ) &&
//         $_GET['orderby'] === 'term_order' &&
//         $_GET['taxonomy'] === 'product_cat' &&
//         in_array( 'product_cat', (array) $taxonomies, true )
//     ) {
//         global $wpdb;

//         // Determine ASC / DESC using the query's own args first (safer than relying on $_GET)
    
//         $dir = ( isset( $args['order'] ) && strtoupper( $args['order'] ) === 'DESC' ) ? 'DESC' : 'ASC';
//         $clauses['orderby'] = "ORDER BY (t.term_order = 0) ASC, t.term_order $dir";


// /* add this ↓ line */
//        // $clauses['orderby'] = preg_replace( '/\s+(ASC|DESC)\s+\1\s*$/i', ' $1', $clauses['orderby'] );
//     }

//     return $clauses;
// }, 99, 3 );

/**
 * Log the SQL query WordPress uses to pull product-category terms
 * (runs only in wp-admin → Products → Categories).
 */
// add_filter( 'get_terms', function ( $terms, $taxonomies, $args, $term_query ) {

//     if ( is_admin()
//          && isset( $_GET['taxonomy'] )
//          && $_GET['taxonomy'] === 'product_cat'
//          && in_array( 'product_cat', (array) $taxonomies, true ) )
//     {
//         // Write the query to debug.log (WP_DEBUG & WP_DEBUG_LOG must be true)
//         error_log( "🔍 Product-cat SQL:\n" . $term_query->request . "\n" );
//     }

//     return $terms;

// }, 100, 4 );  

// add_filter('get_terms_args', function($args, $taxonomies) {
//     if (
//         is_admin() &&
//         isset($_GET['orderby'], $_GET['taxonomy']) &&
//         $_GET['orderby'] === 'term_order' &&
//         $_GET['taxonomy'] === 'product_cat' &&
//         in_array('product_cat', (array) $taxonomies)
//     ) {
//         // Prevent WordPress from injecting its own ORDER BY
//         $args['orderby'] = 'none';   // Prevent WP from injecting its own ORDER BY
//     }
//     return $args;
// }, 99, 2);


// add_filter('get_terms', function ($terms, $taxonomies, $args, $term_query) {
//     if (
//         is_admin() &&
//         !empty($args['orderby']) && $args['orderby'] === 'term_order' &&
//         !empty($taxonomies) && in_array('product_cat', (array)$taxonomies)
//     ) {
//         // usort($terms, function ($a, $b) {
//         //     $a_order = ($a->term_order == 0) ? PHP_INT_MAX : $a->term_order;
//         //     $b_order = ($b->term_order == 0) ? PHP_INT_MAX : $b->term_order;
//         //     return $a_order <=> $b_order;
//         // });
        
//         error_log('terms: ' . print_r($terms,true));

//         usort($terms, function ($a, $b) {
//             // Safely get term_order values, defaulting to PHP_INT_MAX if not set
//             $a_order = (isset($a->term_order) && $a->term_order != 0) ? $a->term_order : PHP_INT_MAX;
//             $b_order = (isset($b->term_order) && $b->term_order != 0) ? $b->term_order : PHP_INT_MAX;
            
//             return $a_order <=> $b_order;
//         });
//     }

//     return $terms;
// }, 20, 4);

  
    add_action('admin_menu', function () {
        add_menu_page(
            'Project Links',
            'Project Links',
            'manage_options',
            'vogo-project-links',
            'vogo_render_project_links_page',
            'dashicons-admin-links',
            25
        );
    });


function add_custom_admin_menu_link() {
    add_menu_page(
        'Admin Dashboard',                         // Page title
        'Admin dashboard',                         // Menu title
        'manage_options',                          // Capability
        'admin-dashboard-links',                   // Menu slug
        '',                                        // Callback (not needed for external links)
        'dashicons-admin-generic',                 // Icon
        3                                          // Position
    );
}
add_action('admin_menu', 'add_custom_admin_menu_link');

// Redirect menu to frontend page
function redirect_admin_menu_link() {
    global $pagenow;
    if ($pagenow === 'admin.php' && isset($_GET['page']) && $_GET['page'] === 'admin-dashboard-links') {
        wp_redirect('https://test07.vogo.family/admin-dashboard-links/');
        exit;
    }
}
add_action('admin_init', 'redirect_admin_menu_link');



// 1. Add a custom textarea field for shop manager notes in order edit page
add_action('woocommerce_admin_order_data_after_order_details', 'custom_shop_manager_notes_field');
function custom_shop_manager_notes_field($order) {
    $note = get_post_meta($order->get_id(), '_shop_manager_note', true);
    ?>
    <div class="shop_manager_note_box">
        <p class="form-field form-field-wide">
            <label for="shop_manager_note"><strong>Shop Manager Notes:</strong></label>
            <textarea name="shop_manager_note" id="shop_manager_note" style="width:100%; min-height:100px;"><?php echo esc_textarea($note); ?></textarea>
        </p>
    </div>
    <?php
}

// 2. Save the shop manager note when the order is updated
add_action('woocommerce_process_shop_order_meta', 'save_shop_manager_notes_field');
function save_shop_manager_notes_field($post_id) {
    if (isset($_POST['shop_manager_note'])) {
        update_post_meta($post_id, '_shop_manager_note', sanitize_textarea_field($_POST['shop_manager_note']));
    }
}

/// Add the column
// add_filter('manage_edit-shop_order_columns', 'add_shop_manager_notes_column', 20);
// function add_shop_manager_notes_column($columns) {
//     $new_columns = [];

//     foreach ($columns as $key => $label) {
//         $new_columns[$key] = $label;
//         if ($key === 'order_status') {
//             $new_columns['shop_manager_notes'] = 'Manager Notes';
//         }
//     }

//     return $new_columns;
// }

// Render column content

add_action('manage_woocommerce_page_wc-orders_custom_column', 'show_shop_manager_notes_column_hpos', 10, 2);
function show_shop_manager_notes_column_hpos($column, $order) {
    if ($column === 'shop_manager_notes') {
        $note = get_post_meta($order->get_id(), '_shop_manager_note', true);
        echo $note ? esc_html(wp_trim_words($note, 10)) : '—';
    }
}

add_filter('manage_woocommerce_page_wc-orders_columns', 'add_shop_manager_notes_column_hpos');
function add_shop_manager_notes_column_hpos($columns) {
    $new_columns = [];

    foreach ($columns as $key => $label) {
        $new_columns[$key] = $label;
        if ($key === 'status') { // use 'status' instead of 'order_status' in HPOS
            $new_columns['shop_manager_notes'] = 'Manager Notes';
        }
    }

    return $new_columns;
}

add_filter( 'woocommerce_thankyou_order_received_text', 'custom_thank_you_message', 10, 2 );
function custom_thank_you_message( $thank_you_text, $order ) {
    return 'Mulțumim. Comanda ta a fost primită.'; // Your custom message
}

// Add custom column for Shop Manager Notes
//TAG#COLUMN-MANAGER-NOTES
add_filter('manage_woocommerce_page_wc-orders_columns', 'addition_shop_manager_notes_column_hpos', 20);
function addition_shop_manager_notes_column_hpos($columns) {
    $new_columns = [];

    foreach ($columns as $column_name => $column_info) {
        $new_columns[$column_name] = $column_info;

        // Insert after the order status column
      //  if ($column_name === 'order_status' || $column_name === 'status') {
      if($column_name === 'manual_notes') {
          

      $new_columns['shop_manager_notes'] = __('Manager Notes', 'woocommerce');

        }
        if($column_name === 'shop_manager_notes') {
           // $new_columns['shop_manager_notes'] = __('Manager Notes', 'woocommerce');
            $new_columns['custom_order_review'] = __('Revizuiește', 'woocommerce');
        }
    }

    return $new_columns;
}

// Show the manager note in the column
// 1. Add custom columns: Manager Notes & Provider
add_filter('manage_woocommerce_page_wc-orders_columns', 'add_custom_columns_to_wc_orders', 20);
function add_custom_columns_to_wc_orders($columns) {
    $new_columns = [];

    foreach ($columns as $column_name => $column_info) {
        $new_columns[$column_name] = $column_info;

        if ($column_name === 'order_status' || $column_name === 'status') {
          //  $new_columns['shop_manager_notes'] = __('Manager Notes', 'woocommerce');
            $new_columns['provider'] = __('Product Providers', 'woocommerce');
        }
    }

    return $new_columns;
}

add_action('manage_woocommerce_page_wc-orders_custom_column', 'render_custom_columns_wc_orders', 20, 2);
function render_custom_columns_wc_orders($column, $order) {
    if (!($order instanceof WC_Order)) {
        $order = wc_get_order($order);
    }

    // if ($column === 'shop_manager_notes') {
    //     $note = get_post_meta($order->get_id(), '_shop_manager_note', true);
    //     echo $note ? esc_html(wp_trim_words($note, 10)) : '—';
    // }

    if ($column === 'provider') {
        $provider_names = [];

        foreach ($order->get_items('line_item') as $item) {
            $product = $item->get_product();
            if (!$product) continue;

            $terms = get_the_terms($product->get_id(), 'product_provider');
            if (!empty($terms) && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $provider_names[] = $term->name;
                }
            }
        }

        $provider_names = array_unique($provider_names);
        echo !empty($provider_names) ? esc_html(implode(', ', $provider_names)) : '—';
    }
}

// 3. Add Provider filter dropdown to the Orders admin list
add_action('restrict_manage_posts', 'add_provider_filter_to_wc_orders');
function add_provider_filter_to_wc_orders() {
    global $typenow;

    if ($typenow === 'shop_order') {
        $selected = isset($_GET['filter_provider']) ? $_GET['filter_provider'] : '';

        // Define providers statically or fetch dynamically from existing orders
        $providers = ['Provider A', 'Provider B', 'Provider C'];

        echo '<select name="filter_provider">
            <option value="">' . __('All Providers', 'woocommerce') . '</option>';
        foreach ($providers as $provider) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($provider),
                selected($selected, $provider, false),
                esc_html($provider)
            );
        }
        echo '</select>';
    }
}

// 4. Filter the order list based on Provider dropdown
add_filter('request', 'filter_wc_orders_by_provider');
function filter_wc_orders_by_provider($vars) {
    global $typenow;

    if ($typenow === 'shop_order' && isset($_GET['filter_provider']) && $_GET['filter_provider'] !== '') {
        $vars['meta_query'][] = [
            'key' => 'product_provider',
            'value' => sanitize_text_field($_GET['filter_provider']),
            'compare' => '='
        ];
    }

    return $vars;
}

add_action('admin_footer-user-edit.php', 'rename_roles_label_js');
add_action('admin_footer-profile.php', 'rename_roles_label_js');

function rename_roles_label_js() {
    ?>
    <script>
    jQuery(document).ready(function ($) {
        $('th:contains("Other Roles")').text('Main Roles');
    });
    </script>
    <?php
}

// ✅ Save product providers on new orders
/**
 * Hook into WooCommerce checkout process to update order meta with product provider slugs.
 *
 * This function is triggered during the WooCommerce checkout process and retrieves the
 * product provider slugs associated with the products in the order. It then saves these
 * slugs as a unique array in the order's meta data under the key '_order_product_providers'.
 *
 * @param int $order_id The ID of the order being processed.
 *
 * @hook woocommerce_checkout_update_order_meta
 *
 * @uses wc_get_order() To retrieve the WooCommerce order object.
 * @uses WC_Order::get_items() To get the line items in the order.
 * @uses WC_Order_Item_Product::get_product() To get the product object for a line item.
 * @uses WC_Product::get_id() To get the product ID.
 * @uses get_the_terms() To retrieve the terms associated with the product for the 'product_provider' taxonomy.
 * @uses update_post_meta() To save the unique provider slugs to the order meta.
 */

add_action('woocommerce_checkout_update_order_meta', function($order_id) {
    $order = wc_get_order($order_id);
    $provider_slugs = [];

    foreach ($order->get_items('line_item') as $item) {
        $product = $item->get_product();
        if (!$product) continue;

        $terms = get_the_terms($product->get_id(), 'product_provider');
        if (!empty($terms) && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $provider_slugs[] = $term->slug;
            }
        }
    }

    if (!empty($provider_slugs)) {
        update_post_meta($order_id, '_order_product_providers', array_unique($provider_slugs));
    }
});

// ✅ Add provider filter dropdown to admin order list
add_action('woocommerce_order_list_table_restrict_manage_orders', function($post_type, $which) {
    if ($post_type !== 'shop_order') return;

    $selected = $_GET['filter_product_provider'] ?? '';
    $terms = get_terms(['taxonomy' => 'product_provider', 'hide_empty' => false]);

    echo '<select name="filter_product_provider">';
    echo '<option value="">Filter by Provider</option>';
    foreach ($terms as $term) {
        printf(
            '<option value="%s"%s>%s</option>',
            esc_attr($term->slug),
            selected($selected, $term->slug, false),
            esc_html($term->name)
        );
    }
    echo '</select>';
}, 10, 2);

/**
 * Filters the WooCommerce order list table query arguments to allow filtering by a custom taxonomy term.
 *
 * This filter checks for a custom query parameter `filter_product_provider` in the URL.
 * If the parameter is present, it retrieves the slug of the desired term and filters the
 * orders to include only those that contain products associated with the specified term
 * in the `product_provider` taxonomy.
 *
 * @param array $args The query arguments for the WooCommerce order list table.
 * @return array Modified query arguments with filtered order IDs if the custom filter is applied.
 *
 * Usage:
 * - Add `filter_product_provider` as a query parameter in the URL with the desired taxonomy term slug.
 * - Example: `?filter_product_provider=example-slug`
 *
 * Debugging:
 * - Logs the applied filter slug and matching order IDs to the error log for debugging purposes.
 *
 * Notes:
 * - Limits the number of orders retrieved to 1000 for performance reasons.
 * - Ensures that only valid taxonomy terms are considered.
 */

add_filter('woocommerce_order_list_table_prepare_items_query_args', function($args) {
    if (!empty($_GET['filter_product_provider'])) {
        $slug = sanitize_text_field($_GET['filter_product_provider']);
        $matching_order_ids = [];

        // Get recent orders
        $orders = wc_get_orders([
            'limit' => 1000,
            'return' => 'objects',
        ]);

        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if (!$product) continue;

                $terms = get_the_terms($product->get_id(), 'product_provider');
                if (!empty($terms) && !is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        if ($term->slug === $slug) {
                            $matching_order_ids[] = $order->get_id();
                            break 2; // One match is enough
                        }
                    }
                }
            }
        }

        // Always apply filter
        $args['post__in'] = array_unique($matching_order_ids);
        if (empty($args['post__in'])) {
            $args['post__in'] = [0]; // Force no results if no matches
        }
    }

    return $args;
});

/**
 * Adds custom date range filters to the WooCommerce order list table.
 *
 * This function hooks into the 'woocommerce_order_list_table_restrict_manage_orders' action
 * to add two date input fields (start and end) for filtering orders by a date range.
 *
 * @param string $post_type The current post type being filtered. This should be 'shop_order'.
 * @param string $which     The location of the extra table nav markup: 'top' or 'bottom'.
 *
 * @return void
 */
add_action('woocommerce_order_list_table_restrict_manage_orders', function($post_type, $which) {
    if ($post_type !== 'shop_order') return;

    $start = isset($_GET['filter_date_start']) ? esc_attr($_GET['filter_date_start']) : '';
    $end   = isset($_GET['filter_date_end']) ? esc_attr($_GET['filter_date_end']) : '';

    echo '<input type="date" name="filter_date_start" placeholder="From" value="' . $start . '"/>';
    echo '&nbsp;';
    echo '<input type="date" name="filter_date_end" placeholder="To" value="' . $end . '"/>';
}, 10, 2);

/**
 * Adds date range filtering to WooCommerce order list queries.
 *
 * Filters orders by 'filter_date_start' (from) and 'filter_date_end' (to) passed via $_GET.
 *
 * @param array $query_args Query arguments for fetching orders.
 * @return array Modified query arguments with date filtering.
 */
add_filter('woocommerce_order_list_table_prepare_items_query_args', function($query_args) {
    if (!empty($_GET['filter_date_start'])) {
        $query_args['date_query'][] = [
            'after'  => sanitize_text_field($_GET['filter_date_start']),
            'inclusive' => true,
            'column' => 'post_date'
        ];
    }

    if (!empty($_GET['filter_date_end'])) {
        $query_args['date_query'][] = [
            'before' => sanitize_text_field($_GET['filter_date_end']) . ' 23:59:59',
            'inclusive' => true,
            'column' => 'post_date'
        ];
    }

    return $query_args;
});
add_action('admin_footer', 'woocommerce_clear_all_product_categories_button');
function woocommerce_clear_all_product_categories_button() {
    $screen = get_current_screen();
    if ($screen->post_type !== 'product') return;
    ?>
    <script>
        jQuery(document).ready(function($) {
            const $categoryBox = $('#product_catdiv');

            if (!$categoryBox.length || $('#clear-all-product-categories').length) return;

            // Add the Clear All button
            const $clearButton = $('<a href="#" id="clear-all-product-categories" style="color:red;font-weight:bold;display:inline-block;margin-top:8px;font-size: 20px;margin-bottom:10px;">❌ Clear all</a>');
            $categoryBox.find('.categorychecklist').after($clearButton);

            // Clear function using .click() to mimic real user behavior
            $clearButton.on('click', function(e) {
                e.preventDefault();

                $categoryBox.find('.categorychecklist input[type="checkbox"]').each(function() {
                    if ($(this).is(':checked')) {
                        $(this).click(); // trigger native toggle, WooCommerce picks it up
                    }
                });
            });
        });
    </script>
    <?php
}

add_filter('acf/validate_save_post', '__return_true');

add_action('comment_post', 'vogo_review_notification_hook', 10, 2);

/**
 * Sends a notification email to the product provider when a review is approved.
 *
 * This function is triggered when a comment is posted, and it checks if the comment is an 
 * approved product review. If so, it retrieves the associated product and its provider's 
 * email address, then sends a notification email to the provider with the review details.
 *
 * @param int $comment_id The ID of the comment being processed.
 * @param int $comment_approved Whether the comment is approved. 1 if approved, 0 otherwise.
 *
 * @uses get_comment_type() To verify if the comment is a review.
 * @uses get_post_type() To ensure the post is a WooCommerce product.
 * @uses wp_get_post_terms() To retrieve the product's provider terms.
 * @uses get_term_meta() To get the provider's email.
 * @uses get_comment_meta() To fetch the review rating.
 * @uses get_the_title() To retrieve the product title.
 * @uses get_permalink() To get the product URL.
 * @uses wp_mail() To send the notification email to the provider.
 */

function vogo_review_notification_hook($comment_id, $comment_approved) {
    // Only proceed if it's an approved product review
   // error_log('it is firing Comment ID: ' . $comment_id);
    if ($comment_approved && get_comment_type($comment_id) === 'review') {
        $comment = get_comment($comment_id);
        $product_id = $comment->comment_post_ID;
     //   error_log('it is firing Product ID: ' . $product_id);
        // Ensure this is a WooCommerce product
        if (get_post_type($product_id) !== 'product') return;

        // Get the product provider term
        $provider_terms = wp_get_post_terms($product_id, 'product_provider');
        if (empty($provider_terms) || is_wp_error($provider_terms)) return;

        $provider = $provider_terms[0];
        $provider_email = get_term_meta($provider->term_id, 'provider_email', true);
       // error_log('it is firing Provider email: ' . $provider_email);
        if (empty($provider_email) || !is_email($provider_email)) return;

        $reviewer_name  = esc_html($comment->comment_author);
        $reviewer_email = sanitize_email($comment->comment_author_email);
        $rating = get_comment_meta($comment_id, 'rating', true);
        $rating_html = $rating ? str_repeat('★', intval($rating)) . str_repeat('☆', 5 - intval($rating)) : 'N/A';

        $product_title = get_the_title($product_id);
        $product_url = get_permalink($product_id);
        $review_content = esc_html($comment->comment_content);
        // Prepare review info
        $rating = get_comment_meta($comment_id, 'rating', true);
        $rating_text = $rating ? "Rating: {$rating}/5\n" : '';

        $subject = "New Review for " . get_the_title($product_id);
        $message = "Hello,\n\nYour product \"" . get_the_title($product_id) . "\" has received a new review:\n\n";
        $message .= $rating_text;
        $message .= "Comment:\n" . $comment->comment_content . "\n\n";
        $message .= "Reviewer: " . $reviewer_name . "\n";
        $message .= "Reviewer Email: " . $reviewer_email . "\n";
        $message .= "View the product: " . get_permalink($product_id);

        wp_mail($provider_email, $subject, $message);
    }
}

add_action('transition_comment_status', 'vogo_notify_provider_on_review_approval', 10, 3);

/**
 * Notifies the product provider when a product review is approved.
 *
 * This function checks if a comment's status is transitioned to 'approved' and
 * if the comment type is 'review'. It retrieves the associated product's provider
 * email and sends a notification email to the provider with the review details.
 *
 * @param string $new_status  The new status of the comment.
 * @param string $old_status  The old status of the comment.
 * @param WP_Comment $comment The comment object containing review details.
 *
 * @return void
 *
 * @uses get_comment_type() To check if the comment type is a review.
 * @uses get_post_type() To ensure the post is a WooCommerce product.
 * @uses wp_get_post_terms() To retrieve the product's provider terms.
 * @uses get_term_meta() To get the provider's email.
 * @uses get_comment_meta() To fetch the review rating.
 * @uses get_the_title() To retrieve the product title.
 * @uses get_permalink() To get the product URL.
 * @uses wp_mail() To send the notification email to the provider.
 */

function vogo_notify_provider_on_review_approval($new_status, $old_status, $comment) {
    // Only continue if changing from unapproved to approved
    if ($new_status !== 'approve' || get_comment_type($comment->comment_ID) !== 'review') {
        return;
    }

    $product_id = $comment->comment_post_ID;

    if (get_post_type($product_id) !== 'product') return;

    // Correct taxonomy
    $provider_terms = wp_get_post_terms($product_id, 'product_provider');
    if (empty($provider_terms) || is_wp_error($provider_terms)) return;

    $provider = $provider_terms[0];
    $provider_email = get_term_meta($provider->term_id, 'provider_email', true);

    if (empty($provider_email) || !is_email($provider_email)) return;

    // Review data
    $reviewer_name  = esc_html($comment->comment_author);
    $reviewer_email = sanitize_email($comment->comment_author_email);
    $rating         = get_comment_meta($comment->comment_ID, 'rating', true);
    $rating_text    = $rating ? "Rating: {$rating}/5\n" : 'Rating: Not given';

    $product_title  = get_the_title($product_id);
    $product_url    = get_permalink($product_id);
    $review_content = esc_html($comment->comment_content);

    $subject = "🛒 New Review for “$product_title” (Approved)";

    $message = "Hello,\n\n";
    $message .= "Your product \"$product_title\" has just had a new review approved:\n\n";
    $message .= "Reviewer: $reviewer_name <$reviewer_email>\n";
    $message .= "$rating_text\n";
    $message .= "Comment:\n$review_content\n\n";
    $message .= "View product: $product_url\n";

    // Send the email
    wp_mail($provider_email, $subject, $message);
}

add_action( 'woocommerce_product_bulk_and_quick_edit', 'acme_force_single_provider', 10, 2 );

function acme_force_single_provider( $post_id, $post ) {

	// Safety: make sure the current user can edit this product.
	if ( ! current_user_can( 'edit_product', $post_id ) ) {
		return;
	}

	/* ------------------------------------------------------------
	 *  Detect the “product_provider” field in the bulk-edit request
	 * ------------------------------------------------------------ */
	$new = null;

	// Bulk-edit sends taxonomies inside tax_input[{$taxonomy}][]
	if ( isset( $_REQUEST['tax_input']['product_provider'] ) ) {
		$new = $_REQUEST['tax_input']['product_provider'];
	}
	// Quick-edit sends product_provider[] directly
	elseif ( isset( $_REQUEST['product_provider'] ) ) {
		$new = $_REQUEST['product_provider'];
	}

	// Merchant picked “— No change —” → leave product alone.
	if ( empty( $new ) || in_array( $new, array( -1, '0', 'no_change' ), true ) ) {
		return;
	}

	// Normalise to an array of integers.
	$new_terms = array_map( 'intval', (array) $new );

	/* -----------------------------
	 *  Replace, don’t append  (4th arg = false)
	 * ----------------------------- */
	wp_set_object_terms( $post_id, $new_terms, 'product_provider', false );

	// Clear caches so the list table shows the new provider instantly.
	clean_object_term_cache( $post_id, 'product' );
}

add_filter('manage_woocommerce_page_wc-orders_columns', function ($columns) {
    
    $columns['payment_mode'] = 'Payment Mode';
    $columns['order_coupon'] = 'Coupon';
    $columns['transport_info'] = 'Tracking';
    $columns['order_tags'] = 'Tags';
    $columns['order_audit'] = 'Audit';
    $columns['manual_notes'] = 'Notes';
    $coulumns['shop_manager_notes'] = 'Shop Manager Notes';
    return $columns;
});
    
add_action('wp_footer', function () {
    if (!is_account_page()) return; // Only run this on the My Account page
    ?>
    <script>
        function toSentenceCase(str) {
            str = str.trim();
            return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
        }

        function applySentenceCaseToElements(selector) {
            document.querySelectorAll(selector).forEach(el => {
                if (el.children.length === 0) {
                    el.textContent = toSentenceCase(el.textContent);
                }
            });
        }

        document.addEventListener("DOMContentLoaded", function () {
            applySentenceCaseToElements("p, h1, h2, h3, h4, h5, h6, li, a, button, strong");
        });
    </script>
    <?php
});

function restrict_my_account_page() {
    if ( is_page('my-account') && !is_user_logged_in() ) {
        // Do not redirect if the endpoint is 'lost-password'
        global $wp;
        if ( isset( $wp->query_vars['lost-password'] ) ) {
            return;
        }

        wp_redirect( site_url('/login/') );
        exit;
    }
}
add_action('template_redirect', 'restrict_my_account_page');



// Disable password strength meter
//add_action('wp_print_scripts', 'disable_password_strength_meter', 100);
function disable_password_strength_meter() {
    wp_dequeue_script('password-strength-meter');
}

//password check XXX
add_filter('woocommerce_registration_errors', 'check_minimum_password_length', 10, 3);
function check_minimum_password_length($errors, $username, $email) {
    if (isset($_POST['password'])) {
        $password = $_POST['password'];

        // Check minimum length
        if (strlen($password) < 5) {
            $errors->add('password_too_short', __('Parola trebuie să aibă cel puțin 12 caractere.', 'woocommerce'));
        }

        // Check for at least one uppercase letter
/*        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors->add('password_no_uppercase', __('Parola trebuie să conțină cel puțin o literă mare.', 'woocommerce'));
        }

        // Check for at least one lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            $errors->add('password_no_lowercase', __('Parola trebuie să conțină cel puțin o literă mică.', 'woocommerce'));
        }

        // Check for at least one digit
        if (!preg_match('/[0-9]/', $password)) {
            $errors->add('password_no_digit', __('Parola trebuie să conțină cel puțin o cifră.', 'woocommerce'));
        }

        // Check for at least one special character
        if (!preg_match('/[\W_]/', $password)) {
            $errors->add('password_no_special', __('Parola trebuie să conțină cel puțin un caracter special (ex: !@#$%).', 'woocommerce'));
        }
           
        */
    }
    
    return $errors;
}

add_action('template_redirect', 'redirect_shop_page_to_home');
function redirect_shop_page_to_home() {
    if (is_shop()) {
        wp_redirect(home_url('/'));
        exit;
    }
}

// Remove frontend JS strength meter
add_action('wp_print_scripts', function() {
    wp_dequeue_script('password-strength-meter');
}, 100);


// log audit of posts updates and deletes

function log_post_changes($post_id, $post, $update) {
    if (wp_is_post_revision($post_id)) return;

    $user = wp_get_current_user();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

    global $wpdb;
    $wpdb->insert('wp_posts_log2', [
        'post_id'      => $post_id,
        'post_type'    => $post->post_type,
        'post_title'   => $post->post_title,
        'post_content' => $post->post_content,
        'user_id'      => $user->ID,
        'username'     => $user->user_login,
        'user_email'   => $user->user_email,
        'action'       => $update ? 'update' : 'insert',
        'ip_address'   => $ip,
        'log_time'     => current_time('mysql')
    ]);
}
add_action('save_post', 'log_post_changes', 10, 3);

function log_post_deletion($post_id) {
    $post = get_post($post_id);
    if (!$post) return;

    $user = wp_get_current_user();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

    global $wpdb;
    $wpdb->insert('wp_posts_log2', [
        'post_id'      => $post_id,
        'post_type'    => $post->post_type,
        'post_title'   => $post->post_title,
        'post_content' => $post->post_content,
        'user_id'      => $user->ID,
        'username'     => $user->user_login,
        'user_email'   => $user->user_email,
        'action'       => 'delete',
        'ip_address'   => $ip,
        'log_time'     => current_time('mysql')
    ]);
}
add_action('before_delete_post', 'log_post_deletion');

add_action('init', function() {
    flush_rewrite_rules();
});

add_action('init', function() {
    load_plugin_textdomain(
        'media-library-organizer',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
});

/*
add_action('init', function() {
    if (current_user_can('manage_options')) {
        global $wpdb;

        // Obținem și valorile din wp-config.php
        $db_user = defined('DB_USER') ? DB_USER : 'n/a';
        $db_name = defined('DB_NAME') ? DB_NAME : 'n/a';
        $db_host = defined('DB_HOST') ? DB_HOST : 'n/a';

        // Verificăm conexiunea la MySQL
        $mysql_connection = mysqli_connect($db_host, $db_user, defined('DB_PASSWORD') ? DB_PASSWORD : '');

        echo '<pre style="z-index:9999;position:absolute;top:20px;left:20px;background:white;color:black;padding:15px;border:2px solid red;font-size:14px;max-width:600px;">';
        echo "🔍 WordPress Database Debug\n\n";
        echo "🧱 Table prefix:           " . esc_html($wpdb->prefix) . "\n";
        echo "📂 Example table:          " . esc_html($wpdb->prefix . 'users') . "\n";
        echo "🧑 DB_USER:                " . esc_html($db_user) . "\n";
        echo "🗄️ DB_NAME:                " . esc_html($db_name) . "\n";
        echo "🌐 DB_HOST:                " . esc_html($db_host) . "\n";
        echo "🔌 MySQL connection test:  " . ($mysql_connection ? '✅ Connected' : '❌ Failed') . "\n";
        echo '</pre>';

        if ($mysql_connection) {
            mysqli_close($mysql_connection);
        }
    }
});
*/

// 🔓 Override WooCommerce + Jetpack password enforcement for testing
add_filter('woocommerce_min_password_length', function($length) {
    return 5; // ✅ minimum 5 chars
}, 1);

add_filter('woocommerce_min_password_strength', function($strength) {
    return 0; // ✅ disable complexity
}, 1);

add_filter('woocommerce_registration_errors', function($errors, $username, $email) {
    if (!empty($_POST['password']) && strlen($_POST['password']) < 5) {
        $errors->add('password_error', __('Parola trebuie să aibă minim 5 caractere (OVERRIDE).', 'woocommerce'));
    }
    return $errors;
}, 1, 3);

// 🚫 Disable Jetpack extra validation on register (optional)
add_action('init', function() {
    remove_all_filters('registration_errors');
}, 20);


// 🚫 Disable Jetpack / WP core password length enforcement (for testing only!)
add_action('init', function() {
    // WordPress core filter for new user registration
    remove_all_filters('registration_errors');
    remove_all_filters('validate_password_reset');
}, 20);
