<?php
add_action('wp_ajax_submit_order_review', 'handle_order_review_ajax');
add_action('wp_ajax_nopriv_submit_order_review', 'handle_order_review_ajax');

function handle_order_review_ajax() {
    check_ajax_referer('order_review_nonce', 'nonce');

    $order_id = intval($_POST['order_id']);
    $rating = intval($_POST['rating']);
    $comment = sanitize_textarea_field($_POST['comment']);
    $user_id = get_current_user_id();

    if (!$order_id || !$rating || !$comment || !$user_id) {
        wp_send_json_error('Incomplete data');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'order_reviews';

    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE order_id = %d AND user_id = %d",
        $order_id, $user_id
    ));

    if ($existing) {
        wp_send_json_error('Ai trimis deja o recenzie');
    }

    $wpdb->insert($table, [
        'order_id' => $order_id,
        'user_id' => $user_id,
        'rating' => $rating,
        'comment' => $comment
    ]);

    wp_send_json_success('Recenzia a fost trimisă cu succes');
}