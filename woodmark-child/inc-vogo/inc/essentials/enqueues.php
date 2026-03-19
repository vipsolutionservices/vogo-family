<?php 

// enqueues 

function enqueue_typing_placeholder_script() {
    // Enqueue custom script for typing effect
    wp_enqueue_script('typing-placeholder', get_stylesheet_directory_uri() . '/js/typing-placeholder.js', array('jquery'), '1.0', true);
    wp_enqueue_script('typed-js', 'https://cdn.jsdelivr.net/npm/typed.js@2.0.12', array(), null, true);

    // Inline style for the search input
    wp_add_inline_style('typing-placeholder-style', '
        .wd-search-inited {
            font-weight: bold;
            color: blue;
            border: 1px solid #ccc;
            padding: 10px;
            font-size: 16px;
            width: 300px;
        }
    ');
}
add_action('wp_enqueue_scripts', 'enqueue_typing_placeholder_script');

add_action('wp_enqueue_scripts', function() {
    if (is_account_page()) {
        wp_enqueue_script('wc-country-select');
        wp_enqueue_script('selectWoo'); // WooCommerce Select2
        wp_enqueue_style('select2');
    }
});


function enqueue_custom_gtranslate_script() {
    wp_enqueue_script('custom-gtranslate', get_stylesheet_directory_uri() . '/js/custom-gtranslate.js', [], '1.7', true);
}
//add_action('wp_enqueue_scripts', 'enqueue_custom_gtranslate_script');

function load_google_translate_script() {
    ?>
    <script src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit2" async></script>
    <script>
        function googleTranslateElementInit2() {
            new google.translate.TranslateElement({
                pageLanguage: 'en',
                autoDisplay: false
            }, 'google_translate_element2');
        }
    </script>
    <?php
}
//add_action('wp_head', 'load_google_translate_script');

add_action('wp_enqueue_scripts', 'enqueue_subscription_assets');
function enqueue_subscription_assets() {
    if (is_account_page()) {
        // SweetAlert library
        wp_enqueue_script('sweetalert', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array('jquery'), null, true);
        // Custom script for handling subscription actions
        wp_enqueue_script('subscription-actions', get_stylesheet_directory_uri() . '/js/subscription-actions.js', array('jquery', 'sweetalert'), '1.0', true);
    }
}

function enqueue_custom_js() {
    // Generate a random number for cache-busting
    $random_version = rand(1000, 9999); // Generate a random version number between 1000 and 9999

    // Register and enqueue the custom JavaScript file
    wp_enqueue_script(
        'category-filter-js', // Handle for the script
        get_stylesheet_directory_uri() . '/js/category-filter.js', // Path to the JS file
        array('jquery'), // Dependency (jQuery in this case)
        $random_version, // Use random version number for cache-busting
        true // Load the script in the footer
    );
    wp_localize_script('category-filter-js', 'ajaxurl', admin_url('admin-ajax.php'));
}
add_action('wp_enqueue_scripts', 'enqueue_custom_js');

function replace_woocommerce_city_fields($fields) {
    $cities = get_cities_list(); // Load cities from JSON
    $selected_city = isset($_COOKIE['selected_city']) ? sanitize_text_field($_COOKIE['selected_city']) : '';

    if (!empty($cities)) {
        $cities_dropdown = array_combine($cities, $cities); // Convert list to associative array

        // Replace Billing City
        $fields['billing']['billing_city'] = array(
            'type'        => 'select',
            'label'       => __('Billing City', 'woocommerce'),
            'required'    => true,
            'class'       => array('wc-enhanced-select'),
            'options'     => ['' => __('Select City', 'woocommerce')] + $cities_dropdown,
            'default'     => $selected_city // Auto-select the city from cookies
        );

        // Replace Shipping City
        $fields['shipping']['shipping_city'] = array(
            'type'        => 'select',
            'label'       => __('Shipping City', 'woocommerce'),
            'required'    => true,
            'class'       => array('wc-enhanced-select'),
            'options'     => ['' => __('Select City', 'woocommerce')] + $cities_dropdown,
            'default'     => $selected_city // Auto-select the city from cookies
        );
    }

    return $fields;
}
// add_filter('woocommerce_checkout_fields', 'replace_woocommerce_city_fields');


function replace_address_city_fields($fields) {
    $cities = get_cities_list(); // Load cities from JSON

    if (!empty($cities)) {
        $cities_dropdown = array_combine($cities, $cities); // Convert list to associative array

        // Replace Billing City
        $fields['billing_city'] = array(
            'type'        => 'select',
            'label'       => __('Billing City', 'woocommerce'),
            'required'    => true,
            'class'       => array('wc-enhanced-select'),
            'options'     => ['' => __('Select City', 'woocommerce')] + $cities_dropdown
        );

        // Replace Shipping City
        $fields['shipping_city'] = array(
            'type'        => 'select',
            'label'       => __('Shipping City', 'woocommerce'),
            'required'    => true,
            'class'       => array('wc-enhanced-select'),
            'options'     => ['' => __('Select City', 'woocommerce')] + $cities_dropdown
        );
    }

    return $fields;
}
//add_filter('woocommerce_default_address_fields', 'replace_address_city_fields');

add_action('wp_enqueue_scripts', function () {
    if (is_checkout()) {
        wp_enqueue_script(
            'ro-checkout-autofill',
            get_stylesheet_directory_uri() . '/js/ro-checkout-autofill.js',
            ['jquery'],
            null,
            true
        );
    }
});

function enqueue_vogo_qna_styles() {
    // Generate a random version number for cache-busting
    $random_version = rand(1000, 9999);

    wp_enqueue_style(
        'vogo-qna-styles',
        get_stylesheet_directory_uri() . '/css/vogo-qna.css',
        [],
        $random_version
    );
}
add_action('wp_enqueue_scripts', 'enqueue_vogo_qna_styles');

