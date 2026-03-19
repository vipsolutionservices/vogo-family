<?php
require_once __DIR__ . '/table-setup.php';
require_once __DIR__ . '/display-form.php';
require_once __DIR__ . '/submit-handler.php';

add_action('wp_enqueue_scripts', function () {
    if (is_account_page()) {
        wp_enqueue_script('order-review-js', get_stylesheet_directory_uri() . '/inc/review-orders/assets/review.js', ['jquery'], 1.1, true);
        wp_localize_script('order-review-js', 'OrderReview', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('order_review_nonce')
        ]);
        wp_enqueue_style('order-review-css', get_stylesheet_directory_uri() . '/inc/review-orders/assets/review.css');
    }
});

// add_action('admin_menu', function () {
//     add_menu_page(
//         'Order Reviews',
//         'Order Reviews',
//         'manage_woocommerce',
//         'order-reviews',
//         'render_order_reviews_page',
//         'dashicons-star-filled',
//         56 // position below WooCommerce
//     );
// });
add_action('admin_menu', function () {
    add_menu_page(
        'Order Reviews',
        'Order Reviews',
        'manage_woocommerce',
        'order-reviews',
        'orm_render_order_reviews_page',
        'dashicons-star-filled',
        56
    );
});

function orm_render_order_reviews_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'order_reviews';

    $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
    $end_date   = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
    $rating     = isset($_GET['rating']) ? intval($_GET['rating']) : '';

    $where = '1=1';
    if ($start_date) {
        $where .= " AND created_at >= '" . esc_sql($start_date) . " 00:00:00'";
    }
    if ($end_date) {
        $where .= " AND created_at <= '" . esc_sql($end_date) . " 23:59:59'";
    }
    if ($rating) {
        $where .= " AND rating = " . intval($rating);
    }

    $reviews = $wpdb->get_results("SELECT * FROM $table WHERE $where ORDER BY created_at DESC");

    echo '<div class="wrap"><h1>Order Reviews</h1>';
    echo '<form method="get" style="margin-bottom: 20px;">';
    echo '<input type="hidden" name="page" value="order-reviews" />';
    echo '<label for="start_date">Start Date:</label> ';
    echo '<input type="date" name="start_date" value="' . esc_attr($start_date) . '" /> ';
    echo '<label for="end_date">End Date:</label> ';
    echo '<input type="date" name="end_date" value="' . esc_attr($end_date) . '" /> ';
    echo '<label for="rating">Stars:</label> ';
    echo '<select name="rating">';
    echo '<option value="">All</option>';
    for ($i = 1; $i <= 5; $i++) {
        echo '<option value="' . $i . '"' . selected($rating, $i, false) . '>' . $i . ' Star' . ($i > 1 ? 's' : '') . '</option>';
    }
    echo '</select> ';
    echo '<input type="submit" class="button button-primary" value="Filter" /> ';
    echo '<a class="button" href="' . esc_url(admin_url('admin-post.php?action=orm_export_reviews&start_date=' . $start_date . '&end_date=' . $end_date . '&rating=' . $rating)) . '">Export to Excel</a>';
    echo '</form>';

    if (empty($reviews)) {
        echo '<p>No reviews submitted yet.</p>';
    } else {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Order ID & Products Ordered</th><th>Customer</th><th>Rating</th><th>Comment</th><th>Date</th></tr></thead><tbody>';
        foreach ($reviews as $review) {
            $user = get_userdata($review->user_id);
            echo '<tr> <td>';
                echo '<a href="' . admin_url('post.php?post=' . $review->order_id . '&action=edit') . '" target="_blank">' . esc_html($review->order_id) . '</a>';

    // Load order products
    $order = wc_get_order($review->order_id);
    if ($order) {
        echo '<ul style="margin: 8px 0 0 0; padding-left: 15px;">';
        foreach ($order->get_items() as $item) {
            echo '<li>' . esc_html($item->get_name()) . '</li>';
        }
        echo '</ul>';
    }

    echo '</td>';
            echo '<td>' . esc_html($user ? $user->display_name : 'User ID ' . $review->user_id) . '</td>';
            echo '<td>' . esc_html($review->rating) . ' ⭐️</td>';
            echo '<td>' . esc_html($review->comment) . '</td>';
            echo '<td>' . esc_html($review->created_at) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    echo '</div>';
}



add_action('admin_post_orm_export_reviews', 'orm_export_reviews');
function orm_export_reviews() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die('Unauthorized');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'order_reviews';

    $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
    $end_date   = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';

    $where = '1=1';
    if ($start_date) {
        $where .= " AND created_at >= '" . esc_sql($start_date) . " 00:00:00'";
    }
    if ($end_date) {
        $where .= " AND created_at <= '" . esc_sql($end_date) . " 23:59:59'";
    }

    $reviews = $wpdb->get_results("SELECT * FROM $table WHERE $where ORDER BY created_at DESC");

    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=order-reviews.xls");

    echo "Order ID\tCustomer\tRating\tComment\tDate\n";
    foreach ($reviews as $review) {
        $user = get_userdata($review->user_id);
        $customer = $user ? $user->display_name : 'User ID ' . $review->user_id;
        echo "{$review->order_id}\t{$customer}\t{$review->rating}\t{$review->comment}\t{$review->created_at}\n";
    }

    exit;
}
function render_order_reviews_page() {
    global $wpdb;

    $table = $wpdb->prefix . 'order_reviews';
    $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';

    $where = '1=1';
    if ($start_date) {
        $where .= " AND created_at >= '" . esc_sql($start_date) . " 00:00:00'";
    }
    if ($end_date) {
        $where .= " AND created_at <= '" . esc_sql($end_date) . " 23:59:59'";
    }

    $reviews = $wpdb->get_results("SELECT * FROM $table WHERE $where ORDER BY created_at DESC");

    // Date Filter Form
    echo '<div class="wrap"><h1>Order Reviews</h1>';
    echo '<form method="get" style="margin-bottom: 20px;">';
    echo '<input type="hidden" name="page" value="order_reviews_page" />';
    echo '<label for="start_date">Start Date: </label>';
    echo '<input type="date" name="start_date" value="' . esc_attr($start_date) . '" />';
    echo '&nbsp;<label for="end_date">End Date: </label>';
    echo '<input type="date" name="end_date" value="' . esc_attr($end_date) . '" />';
    echo '&nbsp;<input type="submit" class="button button-primary" value="Filter" />';
    echo '&nbsp;<a class="button" href="' . esc_url(admin_url('admin-post.php?action=export_order_reviews_to_excel&start_date=' . $start_date . '&end_date=' . $end_date)) . '">Export to Excel</a>';
    echo '</form>';

    // Table
    if (empty($reviews)) {
        echo '<p>No reviews submitted yet.</p>';
    } else {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Order ID</th><th>Customer</th><th>Rating</th><th>Comment</th><th>Date</th></tr></thead><tbody>';
        foreach ($reviews as $review) {
            $user = get_userdata($review->user_id);
            echo '<tr>';
            echo '<td><a href="' . admin_url('post.php?post=' . $review->order_id . '&action=edit') . '" target="_blank">' . esc_html($review->order_id) . '</a></td>';
            echo '<td>' . esc_html($user ? $user->display_name : 'User ID ' . $review->user_id) . '</td>';
            echo '<td>' . esc_html($review->rating) . ' ⭐️</td>';
            echo '<td>' . esc_html($review->comment) . '</td>';
            echo '<td>' . esc_html($review->created_at) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
    echo '</div>';
}

add_action('admin_post_export_order_reviews_to_excel', 'export_order_reviews_to_excel');

function export_order_reviews_to_excel() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die('Unauthorized');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'order_reviews';
    $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';

    $where = '1=1';
    if ($start_date) {
        $where .= " AND created_at >= '" . esc_sql($start_date) . " 00:00:00'";
    }
    if ($end_date) {
        $where .= " AND created_at <= '" . esc_sql($end_date) . " 23:59:59'";
    }

    $reviews = $wpdb->get_results("SELECT * FROM $table WHERE $where ORDER BY created_at DESC");

    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=order-reviews.xls");

    echo "Order ID\tCustomer\tRating\tComment\tDate\n";

    foreach ($reviews as $review) {
        $user = get_userdata($review->user_id);
        $customer = $user ? $user->display_name : 'User ID ' . $review->user_id;
        echo "{$review->order_id}\t{$customer}\t{$review->rating}\t{$review->comment}\t{$review->created_at}\n";
    }

    exit;
}