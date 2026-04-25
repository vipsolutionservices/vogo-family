<?php
function vogo_display_recommended_products($user_id) {
    $referrer_id = get_user_meta($user_id, 'referred_by', true);
    if ($referrer_id) {
        $recommended_products = get_user_meta($referrer_id, 'recommended_products', true);
        if ($recommended_products) {
            echo '<h3>Recomandat de referentul tău</h3>';
            foreach ($recommended_products as $product_id) {
                echo '<div>' . get_the_title($product_id) . '</div>';
            }
        }
    }
}

add_action('wp_head', function() {
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        vogo_display_recommended_products($user_id);
    }
});

add_action('woocommerce_register_form', 'vogo_add_woocommerce_referral_field',20);
// Add the referral code field to the WooCommerce registration form
function vogo_add_woocommerce_referral_field() {
    ?>
    <p class="form-row form-row-wide">
        <label for="referral_code"><?php _e('Cod de recomandare <span style="color:red">*</span>', 'vogo'); ?></label>
        <input type="text" name="referral_code" id="referral_code" class="input-text" required />
    </p>
    <?php
}

// Add referral code field to WooCommerce registration form
add_action('woocommerce_register_form', 'vogo_add_woocommerce_referral_field', 20);

// Validate referral code during registration
// add_filter('woocommerce_registration_errors', 'vogo_validate_woocommerce_referral_code', 10, 3);
// function vogo_validate_woocommerce_referral_code($errors, $username, $email) {
//     if (!empty($_POST['referral_code'])) {
//         $referral_code = sanitize_text_field($_POST['referral_code']);
//         $referrer = get_users([
//             'meta_key' => 'referral_code',
//             'meta_value' => $referral_code,
//             'number' => 1
//         ]);

//         if (empty($referrer)) {
//             // Store the error message in a transient
//             set_transient('referral_code_error', __('Cod de recomandare invalid. Vă rugăm să introduceți un cod valid.', 'vogo'), 30);
//             // Redirect back to the registration page
//             wp_safe_redirect(wp_get_referer() ?: wc_get_page_permalink('myaccount'));
//             exit;
//         }
//     }
//     return $errors;
// }

add_filter('woocommerce_registration_errors', 'vogo_validate_woocommerce_referral_code', 10, 3);
function vogo_validate_woocommerce_referral_code($errors, $username, $email) {
    // Validate referral code
    if (!empty($_POST['referral_code'])) {
        $referral_code = sanitize_text_field($_POST['referral_code']);
        $referrer = get_users([
            'meta_key' => 'referral_code',
            'meta_value' => $referral_code,
            'number' => 1
        ]);

        if (empty($referrer)) {
            $errors->add('invalid_referral', __('Cod de recomandare invalid. Vă rugăm să introduceți un cod valid.', 'vogo'));
        }
    }

    // ✅ Custom error if email already exists
    if (email_exists($email)) {
        $errors->add('email_exists', __('Această adresă de email este deja folosită. Încercați să vă conectați.', 'vogo'));
    }

    return $errors;
}

// DIAGNOSTIC 2026-04-25: dezactivat - duplica logica din register-shortcode.php (parent_user_id in vogo_user_info).
// Suspect ca poate cauza side-effect la register cu referral. Inlocuit complet de wp_vogo_user_info.parent_user_id.
// add_action('woocommerce_created_customer', 'vogo_save_woocommerce_referral_code');
function vogo_save_woocommerce_referral_code($customer_id) {
    if (!empty($_POST['referral_code'])) {
        $referral_code = sanitize_text_field($_POST['referral_code']);
        $referrer = get_users([
            'meta_key' => 'referral_code',
            'meta_value' => $referral_code,
            'number' => 1
        ]);

        if (!empty($referrer)) {
            // Save the referring user ID in the referred user's meta
            update_user_meta($customer_id, 'referred_by', $referrer[0]->ID);

            // Optionally log the referral in a custom table
            global $wpdb;
            $wpdb->insert("{$wpdb->prefix}referrals", [
                'referrer_id' => $referrer[0]->ID,
                'referred_user_id' => $customer_id,
                'referral_date' => current_time('mysql')
            ]);
        }
    }
}
add_action('show_user_profile', 'vogo_show_referral_information');
add_action('edit_user_profile', 'vogo_show_referral_information');
function vogo_show_referral_information($user) {
    $referrer_id = get_user_meta($user->ID, 'referred_by', true);
    $referrer_name = $referrer_id ? get_userdata($referrer_id)->display_name : __('None', 'vogo');
    ?>
    <h3><?php _e('Informații despre recomandare', 'vogo'); ?></h3>
    <table class="form-table">
        <tr>
            <th><label><?php _e('Recomandat de', 'vogo'); ?></label></th>
            <td><?php echo esc_html($referrer_name); ?></td>
        </tr>
    </table>
    <?php
}

add_action('woocommerce_account_dashboard', 'vogo_show_referrer_info_in_dashboard');

function vogo_show_referrer_info_in_dashboard() {
    // Get the current user ID
    $current_user_id = get_current_user_id();

    // Ensure the user is logged in
    if (!$current_user_id) {
        return;
    }

    // Get the referrer ID from user meta
    $referrer_id = get_user_meta($current_user_id, 'referred_by', true);

    // Check if the user was referred by someone
    if ($referrer_id) {
        // Get the referrer's username
        $referrer_info = get_userdata($referrer_id);
        if ($referrer_info) {
            $referrer_name = $referrer_info->user_login;

            // Display the referrer information
            echo '<p><strong>' . __('Ai fost recomandat de:', 'vogo') . '</strong> ' . esc_html($referrer_name) . '</p>';
        } else {
            echo '<p>' . __('Informațiile despre recomandare nu au fost găsite.', 'vogo') . '</p>';
        }
    } else {
       // echo '<p>' . __('You were not referred by anyone.', 'vogo') . '</p>';
    }
}
add_action('init', 'vogo_add_my_account_endpoints');

function vogo_add_my_account_endpoints() {
    add_rewrite_endpoint('referrals', EP_ROOT | EP_PAGES);
    add_rewrite_endpoint('referral-purchases', EP_ROOT | EP_PAGES);
}

add_filter('woocommerce_account_menu_items', 'vogo_add_my_account_menu_items');

function vogo_add_my_account_menu_items($items) {
    $items['referrals'] = __('Referrals', 'vogo');
    $items['referral-purchases'] = __('Achiziții din recomandări', 'vogo');
    return $items;
}

add_action('woocommerce_account_referrals_endpoint', 'vogo_display_referrals');

function vogo_display_referrals() {
    global $wpdb;

    // Items per page
    $items_per_page = 10;

    // Current page number
    $current_page = get_query_var('paged') ? absint(get_query_var('paged')) : 1;

    // Offset for SQL query
    $offset = ($current_page - 1) * $items_per_page;

    // Debugging
    error_log("Current Page: $current_page");
    error_log("Offset: $offset");

    // Fetch the logged-in user's ID
    $current_user_id = get_current_user_id();

    // Fetch referrals with pagination
    $results = $wpdb->get_results($wpdb->prepare("
        SELECT 
            u1.user_login AS referred_username,
            u1.user_registered AS account_creation
        FROM 
            {$wpdb->users} u1
        INNER JOIN 
            {$wpdb->usermeta} um ON u1.ID = um.user_id
        WHERE 
            um.meta_key = 'referred_by' 
            AND um.meta_value = %d
        ORDER BY 
            u1.user_registered DESC
        LIMIT %d OFFSET %d
    ", $current_user_id, $items_per_page, $offset));

    // Get total number of referrals for pagination
    $total_items = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM 
            {$wpdb->users} u1
        INNER JOIN 
            {$wpdb->usermeta} um ON u1.ID = um.user_id
        WHERE 
            um.meta_key = 'referred_by' 
            AND um.meta_value = %d
    ", $current_user_id));

    $total_pages = ceil($total_items / $items_per_page);

    echo '<h2>' . __('Utilizatori înregistrați care au folosit codul tău ca recomandare la înregistrare', 'vogo') . '</h2>';

    if ($results) {
        echo '<table style="width:100%; border-collapse:collapse;" border="1">
            <thead>
                <tr>
                    <th>' . __('Nume de utilizator recomandat', 'vogo') . '</th>
                    <th>' . __('Data creării contului', 'vogo') . '</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($results as $row) {
            echo '<tr>
                <td>' . esc_html($row->referred_username) . '</td>
                <td>' . esc_html($row->account_creation) . '</td>
            </tr>';
        }

        echo '</tbody></table>';

        // Add pagination links
        echo '<p>Total Referrals '.$total_items.'</p>';
        echo '<div class="pagination" style="margin-top: 20px;">';
        echo paginate_links([
            'base'      => esc_url(trailingslashit(wc_get_endpoint_url('referrals'))) . 'page/%#%',
            'format'    => 'page/%#%',
            'current'   => $current_page,
            'total'     => $total_pages,
            'prev_text' => __('« Anterior', 'vogo'),
            'next_text' => __('Următor »', 'vogo'),
        ]);
        echo '</div>';
    } else {
        echo '<p>' . __('Niciun utilizator încă', 'vogo') . '</p>';
    }
}

add_action('woocommerce_account_referral-purchases_endpoint', 'vogo_display_referral_purchases');

function vogo_display_referral_purchases() {
    global $wpdb;

    // Items per page
    $items_per_page = 10;

    // Current page
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

    // Offset for the SQL query
    $offset = ($current_page - 1) * $items_per_page;

    // Get the current user's username
    $current_user = wp_get_current_user();

    // Fetch referral purchases with pagination
    $results = $wpdb->get_results($wpdb->prepare("
        SELECT order_id, order_value, order_date, account_username
        FROM {$wpdb->prefix}referral_orders
        WHERE parent_account_username = %s
        ORDER BY order_date DESC
        LIMIT %d OFFSET %d
    ", $current_user->user_login, $items_per_page, $offset));

    $total_revenue = $wpdb->get_var($wpdb->prepare("
        SELECT SUM(order_value) 
        FROM {$wpdb->prefix}referral_orders
        WHERE parent_account_username = %s
    ", $current_user->user_login));

    // Get the total count for pagination
    $total_items = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM {$wpdb->prefix}referral_orders
        WHERE parent_account_username = %s
    ", $current_user->user_login));

    $total_pages = ceil($total_items / $items_per_page);

    // Display table
    echo '<h2>' . __('Achiziții efectuate de persoanele pe care le-ai recomandat', 'vogo') . '</h2>';

    if ($results) {
        echo '<div style="overflow-x:auto;"><table style="width:100%; border-collapse:collapse; min-width:600px;" border="1">
            <thead>
                <tr>
                    <th>' . __('Produs(e)', 'vogo') . '</th>
                    <th>' . __('Valoarea Comenzii', 'vogo') . '</th>
                    <th>' . __('Data Comenzii', 'vogo') . '</th>
                    <th>' . __('Utilizator Recomandat', 'vogo') . '</th>
                </tr>
            </thead>
            <tbody>';

       foreach ($results as $row) {
    $order = wc_get_order($row->order_id);
    $product_names = [];

    if ($order && !is_wp_error($order)) {
        foreach ($order->get_items() as $item) {
            $product_names[] = $item->get_name();
        }
    }

    $product_list = implode(', ', $product_names);
    $order_link = admin_url('post.php?post=' . $row->order_id . '&action=edit');

    echo '<tr>
        <td><a href="' . esc_url($order_link) . '">' . esc_html($product_list) . '</a></td>
        <td>' . esc_html(strip_tags(wc_price($row->order_value))) . '</td>
        <td>' . esc_html($row->order_date) . '</td>
        <td>' . esc_html($row->account_username) . '</td>
    </tr>';
}

        echo '</tbody></table></div>';
        echo '<p>Tu ai ' . intval($total_items) . ' comenzi de recomandare.</p>';
        echo '<p>Valoarea totală a comenzii '. $total_revenue .' lei </p>';
        // Pagination links
        echo '<div class="pagination" style="margin-top: 20px;">';
        echo paginate_links([
            'base'      => add_query_arg('paged', '%#%'),
            'format'    => '?paged=%#%',
            'current'   => $current_page,
            'total'     => $total_pages,
            'prev_text' => __('« Anterioară', 'vogo'),
            'next_text' => __('Următorul  »', 'vogo'),
        ]);
        echo '</div>';
    } else {
        echo '<p>' . __('Nu au fost făcute achiziții de către recomandările tale.', 'vogo') . '</p>';
    }
}


register_activation_hook(__FILE__, 'vogo_flush_rewrite_rules');
function vogo_flush_rewrite_rules() {
    vogo_add_my_account_endpoints();
    flush_rewrite_rules();
}

add_action('woocommerce_account_dashboard', 'vogo_show_referral_id_in_dashboard');

function vogo_show_referral_id_in_dashboard() {
    // Get the current user ID
    $current_user_id = get_current_user_id();
    $current_user = wp_get_current_user();
    // Ensure the user is logged in
    if (!$current_user_id) {
        return;
    }

    // Get the referral code from user meta
    $referral_code = get_user_meta($current_user_id, 'referral_code', true);
    if ($current_user->exists()) {
        echo '<h3>Detalii Cont</h3>';
        echo '<p><strong>Nume de utilizator:</strong> ' . esc_html($current_user->user_login) . '</p>';
        echo '<p><strong>Email:</strong> ' . esc_html($current_user->user_email) . '</p>';
        echo '<p><a href="' . esc_url(wc_get_account_endpoint_url('edit-account')) . '" class="elementor-button-edit">Editează Detaliile Tale  </a></p>';
    }
    // Display the referral code if it exists
    if ($referral_code) {
        echo '<p><strong>' . __('ID-ul tău de Recomandare:', 'vogo') . '</strong> ' . esc_html($referral_code) . '</p>';
    } else {
        echo '<p>' . __('Nu ai încă un ID de Recomandare.', 'vogo') . '</p>';
    }
}

// // Payments link in my account area
// /**
//  * 1) Add "Payments" menu item in My Account
//  */
// add_filter('woocommerce_account_menu_items', 'add_payments_menu_item');
// function add_payments_menu_item($menu_items) {
//     $menu_items['payments'] = __('Payments', 'textdomain');
//     return $menu_items;
// }

// /**
//  * 2) Register endpoint
//  */
// add_action('init', 'add_payments_endpoint');
// function add_payments_endpoint() {
//     add_rewrite_endpoint('payments', EP_ROOT | EP_PAGES);
// }

// /**
//  * 3) Display content for /my-account/payments/
//  */
// add_action('woocommerce_account_payments_endpoint', 'payments_endpoint_content');
// function payments_endpoint_content() {
//     echo '<h3>Payments</h3>';
//     echo '<p>This is where you can display payment-related data or actions.</p>';
// }

// 1. Add referral code field to "My Account"
add_action('woocommerce_edit_account_form', function () {
    $user_id = get_current_user_id();
    $referral_code = get_user_meta($user_id, 'referral_code', true);
    ?>
    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="referral_code">Cod de Recomandare <span class="optional">(must be unique)</span></label>
        <input type="text" class="woocommerce-Input input-text" name="referral_code" id="referral_code" value="<?php echo esc_attr($referral_code); ?>" readonly />
    </p>
    <?php
});

// 2. Validate referral code uniqueness on form submission
add_action('woocommerce_save_account_details_errors', function ($args, $user) {
    if (isset($_POST['referral_code'])) {
        $new_code = sanitize_text_field($_POST['referral_code']);
        $user_id = get_current_user_id();

        if (empty($new_code)) {
            $args->add('referral_code_empty', 'Codul de recomandare nu poate fi gol.');
        } else {
            // Check uniqueness
            $users = get_users([
                'meta_key' => 'referral_code',
                'meta_value' => $new_code,
                'exclude' => [$user_id],
                'number' => 1,
                'fields' => 'ID',
            ]);

            if (!empty($users)) {
                $args->add('referral_code_taken', 'Acest cod de recomandare este deja folosit. Vă rugăm să alegeți altul.');
            }
        }
    }
}, 10, 2);

// 3. Save referral code if it's valid
add_action('woocommerce_save_account_details', function ($user_id) {
    if (isset($_POST['referral_code'])) {
        $new_code = sanitize_text_field($_POST['referral_code']);
        update_user_meta($user_id, 'referral_code', $new_code);
    }
});