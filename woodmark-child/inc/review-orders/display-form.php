<?php
add_action('woocommerce_order_details_after_order_table', 'show_order_review_form');

function show_order_review_form($order) {
    // Skip this function if we are on the thank you page
    if (is_order_received_page()) {
        return;
    }

    $user_id = get_current_user_id();
    $order_id = $order->get_id();
    global $wpdb;
    $table = $wpdb->prefix . 'order_reviews';

    $review = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE order_id = %d AND user_id = %d",
        $order_id, $user_id
    ));

    echo '<div class="order-review-form hide-this">';

    if ($review) {
        echo "<h3>Recenzia ta</h3>";
        echo '<p><strong>Și evaluarea:</strong> ';
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $review->rating) {
                echo '<span class="star selected">★</span>';
            } else {
                echo '<span class="star">★</span>';
            }
        }
        echo '</p>';
        echo "<p><strong>Comment:</strong> " . esc_html($review->comment) . "</p>";
    } else {
        ?>
        <h3>Lasă recenzie</h3>
        <div class="stars" data-order="<?php echo $order_id; ?>">
            <?php for ($i = 1; $i <= 5; $i++) : ?>
                <span class="star" data-value="<?php echo $i; ?>">★</span>
            <?php endfor; ?>
        </div>
        <textarea id="order_review_comment_<?php echo $order_id; ?>" placeholder="Your Comment"></textarea>
        <button class="submit-order-review" data-order="<?php echo $order_id; ?>">Trimite Recenzie</button>
        <div class="order-review-message"></div>
        <?php
    }

    echo '</div>';
}