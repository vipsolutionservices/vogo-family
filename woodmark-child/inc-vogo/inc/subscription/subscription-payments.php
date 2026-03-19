<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Automatically renews subscriptions by charging NETOPIA Payments API.
 */
function renew_subscriptions() {
    global $wpdb;

    // Fetch active subscriptions
    $subscriptions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}subscriptions WHERE status = 'active'");

    foreach ($subscriptions as $subscription) {
        $current_date = current_time('mysql');

        // Check if subscription has expired
        if (strtotime($current_date) >= strtotime($subscription->end_date)) {
            // Retrieve stored payment token
            $payment_token = $subscription->payment_token;

            if (!$payment_token) {
                error_log("Subscription ID {$subscription->id} has no payment token. Cannot auto-renew.");
                continue;
            }

            // Process payment
            $payment_status = process_netopia_payment($payment_token, $subscription->product_id, $subscription->user_id);

            if ($payment_status === 'success') {
                // Extend subscription by 30 days
                $new_end_date = date('Y-m-d H:i:s', strtotime('+30 days', strtotime($subscription->end_date)));
                $wpdb->update(
                    "{$wpdb->prefix}subscriptions",
                    array('end_date' => $new_end_date),
                    array('id' => $subscription->id),
                    array('%s'),
                    array('%d')
                );
            } else {
                // Payment failed, update subscription status
                $wpdb->update(
                    "{$wpdb->prefix}subscriptions",
                    array('status' => 'pending_payment'),
                    array('id' => $subscription->id),
                    array('%s'),
                    array('%d')
                );
            }
        }
    }
}
add_action('renew_subscriptions_event', 'renew_subscriptions');

/**
 * Charges the stored NETOPIA payment token.
 */
function process_netopia_payment($payment_token, $product_id, $user_id) {
    $netopia_api_url = 'https://secure.paynet.ro/order/json';
    $merchant_id = 'YOUR_MERCHANT_ID';
    $secret_key = 'YOUR_SECRET_KEY';
    
    // Get product price
    $price = get_post_meta($product_id, '_price', true);

    // Prepare request data
    $request_data = array(
        'merchantId' => $merchant_id,
        'paymentToken' => $payment_token,
        'amount' => $price,
        'currency' => 'RON',
        'orderId' => uniqid(),
        'description' => 'Subscription Renewal',
    );

    // Make API request
    $response = wp_remote_post($netopia_api_url, array(
        'body'    => json_encode($request_data),
        'headers' => array('Content-Type' => 'application/json'),
        'timeout' => 30,
    ));

    if (is_wp_error($response)) {
        error_log('NETOPIA Payment Error: ' . $response->get_error_message());
        return 'failed';
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($body['status'] === 'success') {
        return 'success';
    } else {
        error_log('NETOPIA Payment Failed: ' . $body['message']);
        return 'failed';
    }
}

/**
 * Create a database table for tracking payment logs.
 */
function create_payment_logs_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'subscription_payments';

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        subscription_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        transaction_id VARCHAR(255) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status ENUM('success', 'failed', 'pending') NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (subscription_id) REFERENCES {$wpdb->prefix}subscriptions(id),
        FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID)
    );";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
add_action('after_setup_theme', 'create_payment_logs_table');

/**
 * Logs each payment attempt in the database.
 */
function log_subscription_payment($subscription_id, $user_id, $transaction_id, $amount, $status) {
    global $wpdb;
    $wpdb->insert("{$wpdb->prefix}subscription_payments", array(
        'subscription_id' => $subscription_id,
        'user_id'         => $user_id,
        'transaction_id'  => $transaction_id,
        'amount'          => $amount,
        'status'          => $status,
    ), array('%d', '%d', '%s', '%f', '%s'));
}
?>
