 <?php
// Add User Groups Admin Page
// add_action('admin_menu', function() {
//     add_menu_page(
//         'User Groups',
//         'User Groups',
//         'manage_options',
//         'user-groups',
//         'vogo_user_groups_page'
//     );
// });

// function vogo_user_groups_page() {
//     echo '<h1>Manage User Groups</h1>';
//     // Add form and table for assigning users to groups
// }

// Add Referral Code column to Users table
add_filter('manage_users_columns', 'vogo_add_referral_code_column');
function vogo_add_referral_code_column($columns) {
    $columns['referral_code'] = __('Referral Code', 'vogo');
    return $columns;
}

add_action('manage_users_custom_column', 'vogo_show_referral_code_column', 10, 3);
function vogo_show_referral_code_column($value, $column_name, $user_id) {
    if ($column_name === 'referral_code') {
        $referral_code = get_user_meta($user_id, 'referral_code', true);
        return $referral_code ? esc_html($referral_code) : __('N/A', 'vogo');
    }
    return $value;
}

// Make the Referral Code column sortable
add_filter('manage_users_sortable_columns', 'vogo_make_referral_code_sortable');
function vogo_make_referral_code_sortable($sortable_columns) {
    $sortable_columns['referral_code'] = 'referral_code';
    return $sortable_columns;
}

add_action('pre_get_users', 'vogo_sort_by_referral_code');
function vogo_sort_by_referral_code($query) {
    if (!is_admin()) {
        return;
    }

    $orderby = $query->get('orderby');
    if ($orderby === 'referral_code') {
        $query->set('meta_key', 'referral_code');
        $query->set('orderby', 'meta_value');
    }
}

// Generate Referral Code on WooCommerce User Creation
// LEGACY: dezactivat 2026-04-25 - producea AB+uid in wp_usermeta, dublura cu wp_vogo_user_info.my_referral_code (U+uid).
// Convenția canonică VOGO este U+uid in wp_vogo_user_info, gestionata de sync_vogo_user_info din vogo-plugin.
// Functia ramane definita pentru backward compatibility daca alt cod o cheama explicit, dar hook-ul nu mai ruleaza.
// add_action('woocommerce_created_customer', 'vogo_generate_referral_code_for_woocommerce');
function vogo_generate_referral_code_for_woocommerce($customer_id) {
    if (!$customer_id) {
        error_log('Invalid customer ID passed to referral code generator.');
        return;
    }

    // Retrieve user data
    $user_info = get_userdata($customer_id);
    if (!$user_info) {
        error_log('User data could not be retrieved for customer ID: ' . $customer_id);
        return;
    }

    // Generate referral code in the format AB<user_id>
    $referral_code = 'AB' . $customer_id;

    // Save the referral code in user meta
    update_user_meta($customer_id, 'referral_code', $referral_code);

    // Log for debugging
    error_log("Generated referral code for WooCommerce customer {$customer_id}: $referral_code");
}



// Add Referral Purchases Admin Page
add_action('admin_menu', 'vogo_add_referral_order_page');
function vogo_add_referral_order_page() {
    add_menu_page(
        __('Referral Purchases', 'vogo'),
        __('Referral Purchases', 'vogo'),
        'manage_options',
        'referral-purchases',
        'vogo_referral_order_page_callback'
    );
}

// 

add_action('woocommerce_checkout_order_processed', 'vogo_log_referral_order_with_details', 10, 3);

function vogo_log_referral_order_with_details($order_id, $posted_data, $order) {
    global $wpdb;

    // Get the user ID who made the purchase
    $customer_id = $order->get_user_id();
    if (!$customer_id) {
        error_log("Order $order_id: No customer ID (guest order).");
        return;
    }

    // Get the account username (user's login name)
    $account_username = get_userdata($customer_id)->user_login;

    // Check if the user was referred
    $referrer_id = get_user_meta($customer_id, 'referred_by', true);
    if (!$referrer_id) {
        error_log("Order $order_id: Customer $customer_id was not referred.");
        return;
    }

    // Get the parent account username (referrer's login name)
    $parent_account_username = get_userdata($referrer_id)->user_login;

    // Log the order in the referral_orders table
    $table_name = $wpdb->prefix . 'referral_orders';
    $wpdb->insert($table_name, [
        'order_id' => $order_id,
        'order_date' => current_time('mysql'),
        'order_value' => $order->get_total(),
        'account_username' => $account_username,
        'parent_account_username' => $parent_account_username
    ]);

    // Log for debugging
    error_log("Order $order_id: Referral purchase logged with details.");
}
/**
 * Retrieves a list of referral orders from the database.
 *
 * This function queries the 'referral_orders' table to retrieve details about
 * each referral order, including the order ID, date, value, account username,
 * and parent account username.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 * @return array List of referral orders, each containing order details.
 */

function vogo_get_referral_orders() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'referral_orders';

    return $wpdb->get_results("SELECT order_id, order_date, order_value, account_username, parent_account_username FROM $table_name");
}

/**
 * Callback function to display the referral order page in the admin area.
 *
 * This function generates an HTML page that displays a list of referral orders
 * with pagination and filtering options. It fetches data from the database,
 * applies filters, and displays the results in a table format. It also includes
 * pagination controls to navigate through the list of referral orders.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function vogo_referral_order_page_callback() {
    global $wpdb;

    // Table name
    $table_name = $wpdb->prefix . 'referral_orders';

    // Pagination parameters
    $items_per_page = 10;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $items_per_page;

    // Fetch distinct usernames for dropdown filters
    $referrers = $wpdb->get_col("SELECT DISTINCT parent_account_username FROM $table_name ORDER BY parent_account_username");
    $referred_users = $wpdb->get_col("SELECT DISTINCT account_username FROM $table_name ORDER BY account_username");

    // Filters
    $filter_referrer = isset($_GET['referrer_username']) ? sanitize_text_field($_GET['referrer_username']) : '';
    $filter_referred_user = isset($_GET['referred_username']) ? sanitize_text_field($_GET['referred_username']) : '';

    // Build the query
    $where = 'WHERE 1=1';
    if ($filter_referrer) {
        $where .= $wpdb->prepare(' AND parent_account_username = %s', $filter_referrer);
    }
    if ($filter_referred_user) {
        $where .= $wpdb->prepare(' AND account_username = %s', $filter_referred_user);
    }

    $results = $wpdb->get_results("
        SELECT * FROM $table_name
        $where
        ORDER BY order_date DESC
        LIMIT $offset, $items_per_page
    ");

    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where");
    $total_pages = ceil($total_items / $items_per_page);

    // HTML output
    echo '<div class="wrap">';
    echo '<h1>' . __('Referral Purchases', 'vogo') . '</h1>';

    // Filters form with select boxes
    echo '<form method="GET" action="" style="margin-bottom: 20px;">';
    echo '<input type="hidden" name="page" value="referral-purchases">';

    echo '<label style="margin-right: 10px;">' . __('Referrer Username:', 'vogo') . '</label>';
    echo '<select name="referrer_username" style="margin-right: 20px;">';
    echo '<option value="">' . __('All', 'vogo') . '</option>';
    foreach ($referrers as $referrer) {
        $selected = ($referrer === $filter_referrer) ? 'selected' : '';
        echo '<option value="' . esc_attr($referrer) . '" ' . $selected . '>' . esc_html($referrer) . '</option>';
    }
    echo '</select>';

    echo '<label style="margin-right: 10px;">' . __('Referred Username:', 'vogo') . '</label>';
    echo '<select name="referred_username" style="margin-right: 20px;">';
    echo '<option value="">' . __('All', 'vogo') . '</option>';
    foreach ($referred_users as $user) {
        $selected = ($user === $filter_referred_user) ? 'selected' : '';
        echo '<option value="' . esc_attr($user) . '" ' . $selected . '>' . esc_html($user) . '</option>';
    }
    echo '</select>';

    echo '<button type="submit" class="button button-primary">' . __('Filter', 'vogo') . '</button>';
    echo '<a href="?page=referral-purchases" class="button">' . __('Clear Filters', 'vogo') . '</a>';
    echo '</form>';

    // Table
    echo '<table class="widefat fixed" cellspacing="0">
        <thead>
            <tr>
                <th>' . __('Order Number', 'vogo') . '</th>
                <th>' . __('Order Date', 'vogo') . '</th>
                <th>' . __('Value', 'vogo') . '</th>
                <th>' . __('Account Username', 'vogo') . '</th>
                <th>' . __('Parent Account', 'vogo') . '</th>
            </tr>
        </thead>
        <tbody>';

    if ($results) {
        foreach ($results as $row) {
            $order_link = admin_url('post.php?post=' . $row->order_id . '&action=edit');
            echo '<tr>
                <td><a href="' . esc_url($order_link) . '">' . esc_html($row->order_id) . '</a></td>
                <td>' . esc_html($row->order_date) . '</td>
                <td>' . esc_html($row->order_value) . '</td>
                <td>' . esc_html($row->account_username) . '</td>
                <td>' . esc_html($row->parent_account_username) . '</td>
            </tr>';
        }
    } else {
        echo '<tr><td colspan="5">' . __('No referral purchases found.', 'vogo') . '</td></tr>';
    }

    echo '</tbody></table>';

    // Pagination
    $base_url = admin_url('admin.php?page=referral-purchases');
    if ($filter_referrer) {
        $base_url .= '&referrer_username=' . urlencode($filter_referrer);
    }
    if ($filter_referred_user) {
        $base_url .= '&referred_username=' . urlencode($filter_referred_user);
    }

    echo '<div class="tablenav"><div class="tablenav-pages">';
    echo paginate_links([
        'base'      => $base_url . '&paged=%#%',
        'format'    => '&paged=%#%',
        'current'   => $current_page,
        'total'     => $total_pages,
        'prev_text' => __('« Previous', 'vogo'),
        'next_text' => __('Next »', 'vogo'),
    ]);
    echo '</div></div>';

    echo '</div>';
}


add_action('woocommerce_admin_order_data_after_order_details', 'vogo_add_referrer_name_to_order_page');

/**
 * Displays the referrer's name on the WooCommerce order page in the admin area.
 *
 * This function retrieves and displays the name of the referrer for a customer
 * who placed an order. If the customer was referred by another user, the
 * referrer's display name is shown. If no referrer is found or the customer
 * ID is invalid, appropriate messages are displayed instead.
 *
 * @param WC_Order $order WooCommerce order object.
 */

function vogo_add_referrer_name_to_order_page($order) {
    // Get the customer ID from the order
    $customer_id = $order->get_user_id();

    // Ensure the customer ID exists
    if (!$customer_id) {
        echo '<p><strong>' . __('Referrer Name:', 'vogo') . '</strong> ' . __('No referrer (guest checkout)', 'vogo') . '</p>';
        return;
    }

    // Get the referrer ID from the user's metadata
    $referrer_id = get_user_meta($customer_id, 'referred_by', true);

    if (!$referrer_id) {
        echo '<p><strong>' . __('Referrer Name:', 'vogo') . '</strong> ' . __('No referrer', 'vogo') . '</p>';
        return;
    }

    // Get the referrer's name
    $referrer = get_userdata($referrer_id);

    if (!$referrer) {
        echo '<p><strong>' . __('Referrer Name:', 'vogo') . '</strong> ' . __('Referrer not found', 'vogo') . '</p>';
        return;
    }

    $referrer_name = $referrer->display_name;

    // Display the referrer's name
    echo '<p><strong>' . __('Referrer Name:', 'vogo') . '</strong> ' . esc_html($referrer_name) . '</p>';
}

add_action('admin_menu', 'vogo_add_referral_users_menu');

function vogo_add_referral_users_menu() {
    add_menu_page(
        __('Referral Users', 'vogo'),
        __('Referral Users', 'vogo'),
        'manage_options',
        'referral-users',
        'vogo_referral_users_page_callback'
    );
}


function vogo_referral_users_page_callback() {
    global $wpdb;

    // Pagination setup
    $items_per_page = 10;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $items_per_page;

    // Filters
    $filter_parent_username = isset($_GET['parent_username']) ? sanitize_text_field($_GET['parent_username']) : '';
    $filter_child_username = isset($_GET['child_username']) ? sanitize_text_field($_GET['child_username']) : '';

    // Base query
    $where = 'WHERE child_meta.meta_key = "referred_by" AND parent_meta.meta_key = "referral_code"';
    if ($filter_parent_username) {
        $where .= $wpdb->prepare(' AND parent_user.user_login LIKE %s', '%' . $wpdb->esc_like($filter_parent_username) . '%');
    }
    if ($filter_child_username) {
        $where .= $wpdb->prepare(' AND child_user.user_login LIKE %s', '%' . $wpdb->esc_like($filter_child_username) . '%');
    }

    // Fetch total count for pagination
    $total_items = $wpdb->get_var("
        SELECT COUNT(*)
        FROM {$wpdb->usermeta} AS child_meta
        INNER JOIN {$wpdb->users} AS child_user ON child_meta.user_id = child_user.ID
        INNER JOIN {$wpdb->usermeta} AS parent_meta ON parent_meta.user_id = child_meta.meta_value
        INNER JOIN {$wpdb->users} AS parent_user ON parent_meta.user_id = parent_user.ID
        $where
    ");

    $total_pages = ceil($total_items / $items_per_page);

    // Fetch data with limit
    $results = $wpdb->get_results("
        SELECT
            parent_user.user_login AS parent_username,
            parent_meta.meta_value AS referral_code,
            child_user.user_login AS child_username,
            child_user.user_registered AS account_creation
        FROM
            {$wpdb->usermeta} AS child_meta
        INNER JOIN
            {$wpdb->users} AS child_user ON child_meta.user_id = child_user.ID
        INNER JOIN
            {$wpdb->usermeta} AS parent_meta ON parent_meta.user_id = child_meta.meta_value
        INNER JOIN
            {$wpdb->users} AS parent_user ON parent_meta.user_id = parent_user.ID
        $where
        ORDER BY child_user.user_registered DESC
        LIMIT $offset, $items_per_page
    ");

    // HTML output
    echo '<div class="wrap">';
    echo '<h1>' . __('Referral Users', 'vogo') . '</h1>';

    // Filters form
    echo '<form method="GET" action="" style="margin-bottom: 20px;">';
    echo '<input type="hidden" name="page" value="referral-users">';
    echo '<label style="margin-right: 10px;">' . __('Parent Username:', 'vogo') . '</label>';
    echo '<input type="text" name="parent_username" placeholder="Search Parent Username" value="' . esc_attr($filter_parent_username) . '" style="margin-right: 20px;">';
    echo '<label style="margin-right: 10px;">' . __('Child Username:', 'vogo') . '</label>';
    echo '<input type="text" name="child_username" placeholder="Search Child Username" value="' . esc_attr($filter_child_username) . '" style="margin-right: 20px;">';
    echo '<button type="submit" class="button button-primary">' . __('Filter', 'vogo') . '</button>';
    echo '<a href="?page=referral-users" class="button">' . __('Clear Filters', 'vogo') . '</a>';
    echo '</form>';

    // Table
    echo '<table class="widefat fixed" cellspacing="0">
        <thead>
            <tr>
                <th>' . __('Parent Username', 'vogo') . '</th>
                <th>' . __('Referral Code', 'vogo') . '</th>
                <th>' . __('Child Username', 'vogo') . '</th>
                <th>' . __('Account Creation', 'vogo') . '</th>
            </tr>
        </thead>
        <tbody>';

    if ($results) {
        foreach ($results as $row) {
            echo '<tr>
                <td>' . esc_html($row->parent_username) . '</td>
                <td>' . esc_html($row->referral_code) . '</td>
                <td>' . esc_html($row->child_username) . '</td>
                <td>' . esc_html($row->account_creation) . '</td>
            </tr>';
        }
    } else {
        echo '<tr><td colspan="4">' . __('No referral users found.', 'vogo') . '</td></tr>';
    }

    echo '</tbody></table>';

    // Pagination
    $base_url = admin_url('admin.php?page=referral-users');
    if ($filter_parent_username) {
        $base_url .= '&parent_username=' . urlencode($filter_parent_username);
    }
    if ($filter_child_username) {
        $base_url .= '&child_username=' . urlencode($filter_child_username);
    }

    echo '<div class="tablenav"><div class="tablenav-pages">';
    echo paginate_links([
        'base'      => $base_url . '&paged=%#%',
        'format'    => '&paged=%#%',
        'current'   => $current_page,
        'total'     => $total_pages,
        'prev_text' => __('« Previous', 'vogo'),
        'next_text' => __('Next »', 'vogo'),
    ]);
    echo '</div></div>';

    echo '</div>';
}

// Add "Referred By" field in User Profile (Admin)

// Add new columns to the Users table
add_filter('manage_users_columns', 'vogo_add_user_columns');
function vogo_add_user_columns($columns) {
    $columns['referred_by'] = __('Referred By', 'vogo');
    $columns['account_creation'] = __('Account Created', 'vogo');
    return $columns;
}

// Populate the new columns
add_action('manage_users_custom_column', 'vogo_show_user_columns', 10, 3);
function vogo_show_user_columns($value, $column_name, $user_id) {
    if ($column_name === 'referred_by') {
        $referrer_id = get_user_meta($user_id, 'referred_by', true);
        if ($referrer_id) {
            $referrer_user = get_userdata($referrer_id);
            return $referrer_user ? esc_html($referrer_user->display_name) : __('Unknown', 'vogo');
        }
        return __('Not Referred', 'vogo');
    }

    if ($column_name === 'account_creation') {
        $user_data = get_userdata($user_id);
        return esc_html($user_data->user_registered);
    }

    return $value;
}

// Make the new columns sortable
add_filter('manage_users_sortable_columns', 'vogo_make_user_columns_sortable');
function vogo_make_user_columns_sortable($sortable_columns) {
    $sortable_columns['account_creation'] = 'user_registered';
    return $sortable_columns;
}

// Add Referral Code Field in User Profile (Admin)
add_action('show_user_profile', 'vogo_edit_referral_code_in_profile');
add_action('edit_user_profile', 'vogo_edit_referral_code_in_profile');

function vogo_edit_referral_code_in_profile($user) {
    $referral_code = get_user_meta($user->ID, 'referral_code', true);
    ?>
    <h3><?php _e('Referral Information', 'vogo'); ?></h3>
    <table class="form-table">
        <tr>
            <th><label for="referral_code"><?php _e('Referral Code', 'vogo'); ?></label></th>
            <td>
                <input type="text" name="referral_code" id="referral_code" value="<?php echo esc_attr($referral_code); ?>" class="regular-text" />
                <p class="description"><?php _e('Enter a unique referral code for this user.', 'vogo'); ?></p>
            </td>
        </tr>
    </table>
    <?php
}

// Save the updated referral code
add_action('personal_options_update', 'vogo_save_referral_code_in_profile');
add_action('edit_user_profile_update', 'vogo_save_referral_code_in_profile');

function vogo_save_referral_code_in_profile($user_id) {
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }

    if (isset($_POST['referral_code'])) {
        $new_referral_code = sanitize_text_field($_POST['referral_code']);

        // Ensure the referral code is unique
        $existing_user = get_users([
            'meta_key'   => 'referral_code',
            'meta_value' => $new_referral_code,
            'exclude'    => [$user_id],
            'number'     => 1
        ]);

        if (!empty($existing_user)) {
            add_action('admin_notices', function () {
                echo '<div class="error"><p>' . __('This referral code is already in use by another user.', 'vogo') . '</p></div>';
            });
        } else {
            update_user_meta($user_id, 'referral_code', $new_referral_code);
        }
    }
}
// Show additional addresses in the User Profile (Admin Panel)