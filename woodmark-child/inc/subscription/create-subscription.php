<?php
// 1. Add Subscription Checkbox on Product Edit Page
add_action('woocommerce_product_options_general_product_data', 'add_subscription_field');

/**
 * Adds a checkbox to the product edit page under the General tab in WooCommerce.
 * This checkbox allows marking a product as a subscription product.
 */
function add_subscription_field() {
    woocommerce_wp_checkbox(array(
        'id'          => '_is_subscription', // Meta key for the subscription field
        'label'       => __('Produs cu abonament', 'text-domain'),
        'description' => __('Marcați acest produs ca fiind cu abonament.', 'text-domain'),
    ));
}

add_action('woocommerce_process_product_meta', 'save_subscription_field');

/**
 * Saves the value of the subscription checkbox when a product is saved.
 * If the checkbox is checked, it saves 'yes', otherwise 'no'.
 *
 * @param int $post_id The ID of the product being saved.
 */
function save_subscription_field($post_id) {
    $is_subscription = isset($_POST['_is_subscription']) && $_POST['_is_subscription'] === 'yes' ? 'yes' : 'no';
    update_post_meta($post_id, '_is_subscription', $is_subscription);
}

// 2. Create Subscription Table (One-time Setup)
add_action('after_setup_theme', 'create_subscription_table');

/**
 * Creates a custom database table for storing subscription data.
 * This table stores details like user ID, product ID, start date, end date, and subscription status.
 */
function create_subscription_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'subscriptions';
    $charset_collate = $wpdb->get_charset_collate();

    // Check if the table already exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
        $sql = "CREATE TABLE $table_name (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL, -- ID of the user who subscribed
            product_id BIGINT UNSIGNED NOT NULL, -- ID of the subscribed product
            order_id BIGINT UNSIGNED NOT NULL, -- ID of the order associated with the subscription
            start_date DATETIME NOT NULL, -- Start date of the subscription
            end_date DATETIME NOT NULL, -- End date of the subscription
            status ENUM('active', 'cancelled', 'expired') DEFAULT 'active', -- Current status of the subscription
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID),
            FOREIGN KEY (product_id) REFERENCES {$wpdb->posts}(ID),
            FOREIGN KEY (order_id) REFERENCES {$wpdb->posts}(ID)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql); // Create or update the table
    }
}

// 3. Create Subscription on Order Completion
add_action('woocommerce_order_status_completed', 'create_subscription_on_order_completed');

/**
 * Creates a subscription entry in the database when an order with a subscription product is completed.
 *
 * @param int $order_id The ID of the completed order.
 */
function create_subscription_on_order_completed($order_id) {
    global $wpdb;
    $order = wc_get_order($order_id);

    // Debugging to confirm order completion
    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        $is_subscription = get_post_meta($product_id, '_is_subscription', true);

        // Only create a subscription if the product is marked as a subscription
        if ($is_subscription === 'yes') {
            $user_id = $order->get_user_id();
            $start_date = current_time('mysql'); // Current date as the start date
            $end_date = date('Y-m-d H:i:s', strtotime('+365 days')); // 30 days from the start date

             // Get payment details
             $last_payment_date = current_time('mysql');
             $payment_value = $order->get_total();
             $next_payment_date = date('Y-m-d H:i:s', strtotime('+30 days', strtotime($last_payment_date)));
            // Insert subscription details into the custom table
            $wpdb->insert("{$wpdb->prefix}subscriptions", array(
                'user_id'    => $user_id,
                'product_id' => $product_id,
                'order_id'   => $order_id,
                'start_date' => $start_date,
                'end_date'   => $end_date,
                'status'     => 'active',
                'last_payment_date' => $last_payment_date,
                'payment_value' => $payment_value,
                'next_payment_date' => $next_payment_date,
            ), array('%d', '%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s'));
        }
    }
}

// 4. Add "Subscriptions" Menu in My Account
add_filter('woocommerce_account_menu_items', 'add_subscriptions_menu_item');

/**
 * Adds a "Subscriptions" menu item to the WooCommerce My Account page.
 *
 * @param array $items The existing menu items.
 * @return array Updated menu items with Subscriptions added.
 */
function add_subscriptions_menu_item($items) {
    $items['subscriptions'] = __('Abonamente și Plăți', 'text-domain');
    return $items;
}

// 5. Display Subscription Content on My Account
add_action('woocommerce_account_subscriptions_endpoint', 'display_subscriptions_page');
function display_subscriptions_page() {
    global $wpdb;
    $user_id = get_current_user_id();
    
    // Fetch user subscriptions
    $subscriptions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}subscriptions WHERE user_id = %d",
        $user_id
    ));

    if (empty($subscriptions)) {
        echo '<p>Nu s-au găsit abonamente.</p>';
        return;
    }

    echo '<h2>Abonamentele și plățile tale</h2>';
    echo '<table class="woocommerce-table woocommerce-table--subscriptions shop_table shop_table_responsive my_account_subscriptions">';
    echo '<thead><tr><th>Produs</th><th>Data de început</th><th>Data de sfârșit</th><th>Stare</th><th>Ultima plată</th><th>Următoarea plată</th><th>Valoarea plății</th><th>Istoricul plăților</th></tr></thead>';
    echo '<tbody>';

    foreach ($subscriptions as $subscription) {
        $product_name = get_the_title($subscription->product_id);
        $status_label = ucfirst($subscription->status);
        $history_link = wc_get_endpoint_url('payment-history', $subscription->id);

        echo '<tr>';
        echo '<td>' . esc_html($product_name) . '</td>';
        echo '<td>' . esc_html($subscription->start_date) . '</td>';
        echo '<td>' . esc_html($subscription->end_date) . '</td>';
        echo '<td>' . esc_html($status_label) . '</td>';
        echo '<td>' . esc_html($subscription->last_payment_date ? $subscription->last_payment_date : 'N/A') . '</td>';
        echo '<td>' . esc_html($subscription->next_payment_date ? $subscription->next_payment_date : 'N/A') . '</td>';
        echo '<td>' . esc_html($subscription->payment_value ? $subscription->payment_value . ' RON' : 'N/A') . '</td>';
        echo '<td><a href="' . esc_url($history_link) . '" class="button">Vezi</a></td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
}
// 6. Register Endpoint for Subscriptions in My Account
add_action('init', 'add_subscription_endpoint');


/**
 * Registers a custom endpoint for displaying subscriptions in My Account.
 */
function add_subscription_endpoint() {
    add_rewrite_endpoint('subscriptions', EP_ROOT | EP_PAGES);
}

add_action('init', 'add_payment_history_endpoint');
function add_payment_history_endpoint() {
    add_rewrite_endpoint('payment-history', EP_ROOT | EP_PAGES);
}

add_action('after_setup_theme', 'flush_rewrite_rules_on_activation');
function flush_rewrite_rules_on_activation() {
    add_payment_history_endpoint();
    flush_rewrite_rules();
}

add_action('woocommerce_account_payment-history_endpoint', 'display_payment_history_page');
// function display_payment_history_page() {
//     global $wpdb;

//      // Ensure 'subscription_id' is set in the URL
//      $subscription_id = isset($_GET['subscription_id']) ? intval($_GET['subscription_id']) : 0;

//      if ($subscription_id <= 0) {
//          echo '<p style="color: red;">Invalid Subscription ID</p>';
//          return;
//      }


//     // Fetch subscription details
//     $subscription = $wpdb->get_row($wpdb->prepare(
//         "SELECT * FROM {$wpdb->prefix}subscriptions WHERE id = %d",
//         $subscription_id
//     ));

//     if (!$subscription) {
//         echo '<p>No payment history found for this subscription.</p>';
//         return;
//     }

//     echo '<h2>Payment History for ' . esc_html(get_the_title($subscription->product_id)) . '</h2>';

//     echo '<table class="woocommerce-table woocommerce-table--subscription-payments shop_table shop_table_responsive">';
//     echo '<thead><tr><th>Last Payment</th><th>Amount</th><th>Next Payment</th></tr></thead>';
//     echo '<tbody>';

//     echo '<tr>';
//         echo '<td>' . esc_html($subscription->last_payment_date ? date('Y-m-d', strtotime($subscription->last_payment_date)) : 'N/A') . '</td>';
//         echo '<td>' . esc_html($subscription->payment_value ? $subscription->payment_value . ' RON' : 'N/A') . '</td>';
//         echo '<td>' . esc_html($subscription->next_payment_date ? date('Y-m-d', strtotime($subscription->next_payment_date)) : 'N/A') . '</td>';
//         echo '</tr>';


//     echo '</tbody>';
//     echo '</table>';
// }

function display_payment_history_page() {
    global $wpdb;

    // Get the subscription ID from the WooCommerce endpoint
    $subscription_id = get_query_var('payment-history');

    // Ensure it's a valid ID
    if (!$subscription_id || !is_numeric($subscription_id)) {
        echo '<p style="color: red;">ID de abonament invalid</p>';
        return;
    }

    // Fetch subscription details from the database
    $subscription = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}subscriptions WHERE id = %d",
        intval($subscription_id)
    ));

    // If no subscription is found, show error
    if (!$subscription) {
        echo '<p style="color: red;">Nu s-a găsit istoric de plăți pentru acest abonament.</p>';
        return;
    }

    // Fetch user details
    $user = get_userdata($subscription->user_id);
    $user_name = $user ? $user->display_name : 'Unknown User';

    echo '<div class="wrap">';
    echo '<h2>Istoric plăți pentru ' . esc_html($user_name) . '</h2>';

    echo '<table class="woocommerce-table woocommerce-table--subscription-payments shop_table shop_table_responsive">';
    echo '<thead><tr><th>Ultima plată</th><th>Sumă</th><th>Următoarea plată</th></tr></thead>';
    echo '<tbody>';

    echo '<tr>';
    echo '<td>' . (!empty($subscription->last_payment_date) ? esc_html(date_i18n('Y-m-d', strtotime($subscription->last_payment_date))) : 'N/A') . '</td>';
    echo '<td>' . (!empty($subscription->payment_value) ? esc_html($subscription->payment_value . ' RON') : 'N/A') . '</td>';
    echo '<td>' . (!empty($subscription->next_payment_date) ? esc_html(date_i18n('Y-m-d', strtotime($subscription->next_payment_date))) : 'N/A') . '</td>';
    echo '</tr>';

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}




// 7. Flush Rewrite Rules
// add_action('after_setup_theme', 'flush_rewrite_rules_on_activation');

// /**
//  * Flushes rewrite rules to ensure the custom endpoint works correctly.
//  */
// function flush_rewrite_rules_on_activation() {
//     add_subscription_endpoint();
//     flush_rewrite_rules();
// }

// 8. Automatically Renew Subscriptions via Cron
if (!wp_next_scheduled('renew_subscriptions_event')) {
    wp_schedule_event(time(), 'daily', 'renew_subscriptions_event');
}

add_action('renew_subscriptions_event', 'renew_subscriptions');

/**
 * Automatically renews active subscriptions by extending their end date by 30 days.
 */
// function renew_subscriptions() {
//     global $wpdb;
//     $subscriptions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}subscriptions WHERE status = 'active'");

//     foreach ($subscriptions as $subscription) {
//         $current_date = current_time('mysql');
//         if (strtotime($current_date) >= strtotime($subscription->end_date)) {
//             $new_end_date = date('Y-m-d H:i:s', strtotime('+30 days', strtotime($subscription->end_date)));

//             // Update the subscription's end date
//             $wpdb->update(
//                 "{$wpdb->prefix}subscriptions",
//                 array('end_date' => $new_end_date),
//                 array('id' => $subscription->id),
//                 array('%s'),
//                 array('%d')
//             );
//         }
//     }
// }

function renew_subscriptions() {
    global $wpdb;

    $subscriptions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}subscriptions WHERE status = 'active'");

    foreach ($subscriptions as $subscription) {
        $current_date = current_time('mysql');

        if (strtotime($current_date) >= strtotime($subscription->end_date)) {
            $new_end_date = date('Y-m-d H:i:s', strtotime('+30 days', strtotime($subscription->end_date)));
            $last_payment_date = current_time('mysql');
            $payment_value = get_post_meta($subscription->product_id, '_price', true);
            $next_payment_date = date('Y-m-d H:i:s', strtotime('+30 days'));

            // Update subscription
            $wpdb->update(
                "{$wpdb->prefix}subscriptions",
                array(
                    'end_date' => $new_end_date,
                    'last_payment_date' => $last_payment_date,
                    'payment_value' => $payment_value,
                    'next_payment_date' => $next_payment_date
                ),
                array('id' => $subscription->id),
                array('%s', '%s', '%f', '%s'),
                array('%d')
            );
        }
    }
}


// 9. Add Subscriptions Admin Page
add_action('admin_menu', 'add_subscription_admin_page');

/**
 * Adds a "Subscriptions" page to the WordPress admin menu for managing subscriptions.
 */
function add_subscription_admin_page() {
    add_menu_page(
        'Subscriptions', // Page title
        'Subscriptions', // Menu title
        'manage_options', // Capability required
        'subscriptions', // Menu slug
        'render_subscription_admin_page', // Callback function to display content
        'dashicons-list-view', // Icon
        25 // Position in the menu
    );
}

/**
 * Renders the subscriptions table in the WordPress admin area.
 */
// function render_subscription_admin_page() {
//     global $wpdb;
//     $subscriptions_per_page = 10; // Number of subscriptions per page

//     // Get the current page number from the query parameter
//     $current_page = isset($_GET['paged']) && intval($_GET['paged']) > 0 ? intval($_GET['paged']) : 1;
//     $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
//     $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
//     $offset = ($current_page - 1) * $subscriptions_per_page;

//     // Build WHERE clause for filters
//     $where = "1=1";
//     if (!empty($search_query)) {
//         $where .= $wpdb->prepare(" AND (product_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE %s) OR user_id IN (SELECT ID FROM {$wpdb->users} WHERE display_name LIKE %s))", "%$search_query%", "%$search_query%");
//     }
//     if (!empty($status_filter)) {
//         $where .= $wpdb->prepare(" AND status = %s", $status_filter);
//     }

//     // Fetch subscriptions with filters
//     $subscriptions = $wpdb->get_results($wpdb->prepare(
//         "SELECT * FROM {$wpdb->prefix}subscriptions WHERE $where LIMIT %d OFFSET %d",
//         $subscriptions_per_page,
//         $offset
//     ));

//     // Count total subscriptions
//     $total_subscriptions = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}subscriptions");

//     echo '<div class="wrap">';

//     // Render the admin page
//     echo '<div class="wrap">';
//     echo '<h1>Subscriptions</h1>';

//     // Search and filter form
//     echo '<form method="get" action="">';
//     echo '<input type="hidden" name="page" value="subscriptions">';
//     echo '<input type="search" name="s" value="' . esc_attr($search_query) . '" placeholder="Search by product or user">';
//     echo '<select name="status">';
//     echo '<option value="">All Statuses</option>';
//     echo '<option value="active" ' . selected($status_filter, 'active', false) . '>Active</option>';
//     echo '<option value="pending_cancellation" ' . selected($status_filter, 'pending_cancellation', false) . '>Pending Cancellation</option>';
//     echo '<option value="cancelled" ' . selected($status_filter, 'cancelled', false) . '>Cancelled</option>';
//     echo '<option value="expired" ' . selected($status_filter, 'expired', false) . '>Expired</option>';
//     echo '</select>';
//     echo '<button type="submit" class="button">Filter</button>';
//     echo '</form>';

//     echo '<h1>Subscriptions</h1>';
//     if (empty($subscriptions)) {
//         echo '<p>No subscriptions found.</p>';
//         return;
//     }

//     echo '<table class="widefat fixed striped">';
//     echo '<thead>';
//     echo '<tr>';
//     echo '<th>User</th>';
//     echo '<th>Product</th>';
//     echo '<th>Order ID</th>';
//     echo '<th>Start Date</th>';
//     echo '<th>End Date</th>';
//     echo '<th>Status</th>';
//     echo '</tr>';
//     echo '</thead>';
//     echo '<tbody>';

//     foreach ($subscriptions as $subscription) {
//         $user = get_userdata($subscription->user_id);
//         $product_name = get_the_title($subscription->product_id);
//         $order_link = admin_url('post.php?post=' . $subscription->order_id . '&action=edit');

//         echo '<tr>';
//         echo '<td>' . esc_html($user->display_name) . '</td>';
//         echo '<td>' . esc_html($product_name) . '</td>';
//         echo '<td><a href="' . esc_url($order_link) . '" target="_blank">#' . esc_html($subscription->order_id) . '</a></td>';
//         echo '<td>' . esc_html($subscription->start_date) . '</td>';
//         echo '<td>' . esc_html($subscription->end_date) . '</td>';
//         echo '<td>' . esc_html(ucfirst($subscription->status)) . '</td>';
//         echo '</tr>';
//     }

//     echo '</tbody>';
//     echo '</table>';
//     // Add pagination links
//     $total_pages = ceil($total_subscriptions / $subscriptions_per_page);

//     if ($total_pages > 1) {
//         echo '<div class="pagination">';
//         for ($i = 1; $i <= $total_pages; $i++) {
//             $class = ($i === $current_page) ? ' class="current-page"' : '';
//             echo '<a href="' . esc_url(add_query_arg('paged', $i)) . '"' . $class . '>' . $i . '</a> ';
//         }
//         echo '</div>';
//     }
//     echo '</div>';
// }

function render_subscription_admin_page() {
    global $wpdb;
    $subscriptions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}subscriptions");

    echo '<div class="wrap">';
    echo '<h1>Abonamente</h1>';
    echo '<table class="widefat fixed striped">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Utilizator</th>';
    echo '<th>Produs</th>';
    echo '<th>ID comandă</th>';
    echo '<th>Data de început</th>';
    echo '<th>Data de sfârșit</th>';
    echo '<th>Stare</th>';
    echo '<th>Ultima plată</th>';
    echo '<th>Valoarea plății</th>';
    echo '<th>Valoarea plății</th>';
    echo '<th>Istoricul plăților</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ($subscriptions as $subscription) {
        $user = get_userdata($subscription->user_id);
        $product_name = get_the_title($subscription->product_id);
        $order_link = admin_url('post.php?post=' . $subscription->order_id . '&action=edit');
        $history_link = admin_url('admin.php?page=subscription_payment_history&subscription_id=' . $subscription->id);

        echo '<tr>';
        echo '<td>' . esc_html($user->display_name) . '</td>';
        echo '<td>' . esc_html($product_name) . '</td>';
        echo '<td><a href="' . esc_url($order_link) . '" target="_blank">#' . esc_html($subscription->order_id) . '</a></td>';
        echo '<td>' . esc_html($subscription->start_date) . '</td>';
        echo '<td>' . esc_html($subscription->end_date) . '</td>';
        echo '<td>' . esc_html(ucfirst($subscription->status)) . '</td>';
        echo '<td>' . esc_html($subscription->last_payment_date ? date('Y-m-d', strtotime($subscription->last_payment_date)) : 'N/A') . '</td>';
echo '<td>' . esc_html($subscription->payment_value ? $subscription->payment_value . ' RON' : 'N/A') . '</td>';
echo '<td>' . esc_html($subscription->next_payment_date ? date('Y-m-d', strtotime($subscription->next_payment_date)) : 'N/A') . '</td>';
        echo '<td><a href="' . esc_url($history_link) . '">Vezi istoricul</a></td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}


// // Handle Unsubscribe and Cancel Unsubscribe actions
 add_action('init', 'handle_subscription_actions');



function handle_subscription_actions() {
    global $wpdb;

    // Handle Unsubscribe request
    if (isset($_POST['unsubscribe']) && isset($_POST['subscription_id'])) {
        $subscription_id = intval($_POST['subscription_id']);
        $user_id = get_current_user_id();

        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}subscriptions WHERE id = %d AND user_id = %d AND status = 'active'",
            $subscription_id,
            $user_id
        ));

        if ($subscription) {
            // Update status to pending_cancellation
            $wpdb->update(
                "{$wpdb->prefix}subscriptions",
                array('status' => 'pending_cancellation'),
                array('id' => $subscription_id),
                array('%s'),
                array('%d')
            );

            wc_add_notice('Abonamentul tău este acum în așteptarea anulării. Va rămâne activ până la ' . esc_html($subscription->end_date) . '.', 'success');
            wp_safe_redirect(wc_get_account_endpoint_url('subscriptions'));
            exit;
        }
    }

    // Handle Cancel Unsubscribe request
    if (isset($_POST['cancel_unsubscribe']) && isset($_POST['subscription_id'])) {
        $subscription_id = intval($_POST['subscription_id']);
        $user_id = get_current_user_id();

        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}subscriptions WHERE id = %d AND user_id = %d AND status = 'pending_cancellation'",
            $subscription_id,
            $user_id
        ));

        if ($subscription) {
            // Revert status to active
            $wpdb->update(
                "{$wpdb->prefix}subscriptions",
                array('status' => 'active'),
                array('id' => $subscription_id),
                array('%s'),
                array('%d')
            );

            wc_add_notice('Cererea ta de dezabonare a fost anulată. Abonamentul tău rămâne activ.', 'success');
            wp_safe_redirect(wc_get_account_endpoint_url('subscriptions'));
            exit;
        }
    }
}

add_action('wp_enqueue_scripts', 'custom_subscription_styles');

add_action('admin_menu', 'add_subscription_payment_history_page');
function add_subscription_payment_history_page() {
    add_submenu_page(
        'subscriptions',
        'Payment History',
        'Payment History',
        'manage_options',
        'subscription_payment_history',
        'render_subscription_payment_history_page'
    );
}

function render_subscription_payment_history_page() {
    global $wpdb;

    if (!isset($_GET['subscription_id'])) {
        echo '<p>ID de abonament invalid</p>';
        return;
    }

    $subscription_id = intval($_GET['subscription_id']);

    // Fetch subscription details including user_id
    $subscription = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}subscriptions WHERE id = %d",
        $subscription_id
    ));

    if (!$subscription) {
        echo '<p>Nu s-a găsit istoricul plăților pentru acest abonament.</p>';
        return;
    }

    // Get user details
    $user = get_userdata($subscription->user_id);
    $user_name = $user ? $user->display_name : 'Unknown User';

    echo '<div class="wrap">';
    echo '<h1>Istoricul plăților pentru ' . esc_html($user_name) . '</h1>';

    // Display Payment History
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>Nume utilizator</th><th>Ultima plată</th><th>Sumă</th><th>Următoarea plată</th></tr></thead>';
    echo '<tbody>';

    echo '<tr>';
    echo '<td>' . esc_html($user_name) . '</td>';
    echo '<td>' . esc_html($subscription->last_payment_date) . '</td>';
    echo '<td>' . esc_html($subscription->payment_value) . ' RON</td>';
    echo '<td>' . esc_html($subscription->next_payment_date) . '</td>';
    echo '</tr>';

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}


function custom_subscription_styles() {
    if (is_account_page()) {
        wp_add_inline_style('woocommerce-general', '
            .button {
                background-color: #f44336; /* Red */
                color: #ffffff;
                border: none;
                padding: 5px 10px;
                border-radius: 4px;
                cursor: pointer;
            }
            .button:hover {
                background-color: #d32f2f;
            }
        ');
    }
}

function update_subscriptions_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'subscriptions';

    // Get existing columns
    $existing_columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name", ARRAY_A);
    $column_names = array_column($existing_columns, 'Field');

    // Add column only if it doesn't exist
    if (!in_array('last_payment_date', $column_names)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN last_payment_date DATETIME NULL");
    }

    if (!in_array('payment_value', $column_names)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN payment_value DECIMAL(10,2) NULL");
    }

    if (!in_array('next_payment_date', $column_names)) {
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN next_payment_date DATETIME NULL");
    }
}
add_action('after_setup_theme', 'update_subscriptions_table');

