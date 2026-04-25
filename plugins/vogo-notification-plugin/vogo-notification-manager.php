
<?php
/*
Plugin Name: VOGO Push Notification Manager
Description: Sends API notifications to VOGO mobile app on key WooCommerce events.
Version: 1.0
Author: Durgesh Tanwar
*/

// === CONFIG ===
define('VOGO_NOTIFICATION_API_URL', 'https://your-mobile-app-server.com/api/send-notification');

// === Send Notification Function ===
function vogo_send_notification($type, $data) {
    $api_url = get_option('vogo_notification_api_url', VOGO_NOTIFICATION_API_URL);

    $body = [
        'type' => $type,
        'data' => $data,
    ];

    $response = wp_remote_post($api_url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => json_encode($body),
        'timeout' => 10,
    ]);

    if (is_wp_error($response)) {
        error_log('VOGO Notification Failed: ' . $response->get_error_message());
    }
}

// === Admin Settings ===
add_action('admin_menu', function() {
    add_submenu_page('vogo-brand-options', 'VOGO Notifications', 'VOGO Notifications', 'manage_options', 'vogo-notifications', 'vogo_notification_settings_page');
});

add_action('admin_init', function() {
    register_setting('vogo_notification_settings', 'vogo_notification_api_url');
});

function vogo_notification_settings_page() {
    ?>
    <div class="wrap">
        <h2>VOGO Notification Settings</h2>
        <form method="post" action="options.php">
            <?php settings_fields('vogo_notification_settings'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">API Endpoint URL</th>
                    <td><input type="text" name="vogo_notification_api_url" value="<?php echo esc_attr(get_option('vogo_notification_api_url', VOGO_NOTIFICATION_API_URL)); ?>" size="50" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// === Notification Hooks ===

// 1. User Login
add_action('wp_login', function($user_login, $user) {
    $data = [
        'user_id' => $user->ID,
        'email' => $user->user_email,
        'name' => $user->display_name,
        'login_time' => current_time('mysql'),
    ];
    vogo_send_notification('user_login', $data);
}, 10, 2);

// 2. User Registration
// DIAGNOSTIC 2026-04-25: dezactivat temporar - suspect ca wp_remote_post sincron (timeout 10s)
// blocheaza wc_create_new_customer la register cu referral. Re-activam dupa identificare cauza.
// add_action('user_register', function($user_id) {
//     $user = get_userdata($user_id);
//     $data = [
//         'user_id' => $user_id,
//         'email' => $user->user_email,
//         'name' => $user->display_name,
//         'registered' => $user->user_registered,
//     ];
//     vogo_send_notification('user_register', $data);
// });

// 3. Order Placed
add_action('woocommerce_thankyou', function($order_id) {
    $order = wc_get_order($order_id);
    $data = [
        'order_id' => $order_id,
        'total' => $order->get_total(),
        'status' => $order->get_status(),
        'customer' => $order->get_billing_email(),
    ];
    vogo_send_notification('order_placed', $data);
});

// 4. Order Status Changed
add_action('woocommerce_order_status_changed', function($order_id, $old_status, $new_status) {
    $order = wc_get_order($order_id);
    $data = [
        'order_id' => $order_id,
        'old_status' => $old_status,
        'new_status' => $new_status,
        'customer' => $order->get_billing_email(),
    ];
    vogo_send_notification('order_status_changed', $data);
}, 10, 3);

// 5. Subscription Events (optional, if subscriptions used)
add_action('woocommerce_subscription_status_active', function($subscription) {
    $data = [
        'subscription_id' => $subscription->get_id(),
        'status' => $subscription->get_status(),
        'customer' => $subscription->get_billing_email(),
    ];
    vogo_send_notification('subscription_active', $data);
});

add_action('woocommerce_subscription_renewal_payment_complete', function($subscription) {
    $data = [
        'subscription_id' => $subscription->get_id(),
        'status' => $subscription->get_status(),
        'customer' => $subscription->get_billing_email(),
    ];
    vogo_send_notification('subscription_renewed', $data);
});

// 6. Product Stock Back
add_action('woocommerce_product_set_stock_status', function($product_id, $stock_status) {
    if ($stock_status === 'instock') {
        $product = wc_get_product($product_id);
        $data = [
            'product_id' => $product_id,
            'product_name' => $product->get_name(),
            'stock_status' => $stock_status,
        ];
        vogo_send_notification('product_back_in_stock', $data);
    }
}, 10, 2);
