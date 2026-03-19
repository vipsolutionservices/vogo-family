<?php 
function vogo_recommend_button_shortcode() {
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        global $wpdb;
        $followers = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'referred_by' AND meta_value = %d",
            $user_id
        ));
        $tooltip = sprintf(__('Recomand!', 'vogo'), $followers);

        return '<button type="button" 
                       class="button add-to-reference" 
                       data-product-id="' . get_the_ID() . '"
                       title="' . esc_attr($tooltip) . '"
               >Recomand acest produs sau serviciu!</button>';
    } else {
       // return '<p>Please <a href="' . wp_login_url(get_permalink()) . '">log in</a> to add to your recommendations.</p>';
    }
}
add_shortcode('vogo_recommend_button', 'vogo_recommend_button_shortcode');


add_action('wp_ajax_add_to_reference', 'handle_add_to_reference');
function handle_add_to_reference() {
    // Check nonce for security
    check_ajax_referer('add_to_reference_nonce', 'nonce');

    // Get current user
    if (!is_user_logged_in()) {
        wp_send_json_error('Trebuie să fii autentificat pentru a efectua această acțiune.');
    }

    $user_id = get_current_user_id();
    $product_id = intval($_POST['product_id']);

    if (!$product_id) {
        wp_send_json_error('Invalid product.');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'product_recommendations';

    $already_exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) 
             FROM $table_name 
             WHERE user_id = %d 
               AND product_id = %d",
            $user_id,
            $product_id
        )
    );

    if ($already_exists) {
        // This user has already added this product to their references
        wp_send_json_error('Acest produs este deja în lista dvs. de recomandări.');
    }
    // Insert data into custom table
    $result = $wpdb->insert(
        $table_name,
        [
            'user_id'       => $user_id,
            'product_id'    => $product_id,
            'recommended_at'=> current_time('mysql'),
        ],
        ['%d', '%d', '%s']
    );

    if ($result) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Nu s-a putut adăuga produsul la referință');
    }
}
