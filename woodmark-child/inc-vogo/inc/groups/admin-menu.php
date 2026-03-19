<?php
/**
 * Register the Groups admin menu
 */

add_action('admin_menu', 'my_register_groups_menu');
function my_register_groups_menu() {
    add_menu_page(
        'Groups Management',
        'User Groups',
        'manage_options',     // Only admins (or roles with manage_options) can see it
        'my-groups-page',
        'my_render_groups_page',  // Callback (defined in tabs.php)
        'dashicons-groups',
        50
    );
}

add_action('admin_menu', function () {
    add_submenu_page(
        'my-groups-page',     // Parent menu slug
        'Audit Log',          // Page title
        'Audit Log',          // Menu title
        'manage_options',     // Capability
        'audit-log',          // Menu slug
        'my_render_audit_log_tab' // Callback function
    );
});
function my_enqueue_select2() {
    wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0', true);
    wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css"', [], '4.1.0');
}
add_action('admin_enqueue_scripts', 'my_enqueue_select2');

add_action('wp_ajax_my_user_search', 'my_user_search');
function my_user_search() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 403);
    }
    
    $search_query = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    $page = isset($_GET['page']) ? absint($_GET['page']) : 1;
    $per_page = 20; // how many users to show per "page"
   // Build query args
   $args = [
    'number'         => $per_page,
    'paged'          => $page,
];
if (!empty($search_query)) {
    // If the user typed something, we do a "search"
    $args['search']         = "*{$search_query}*";
    $args['search_columns'] = ['user_login', 'user_nicename', 'user_email', 'display_name'];
} else {
    // If there's no search term, just fetch the first page of users sorted by display_name
    $args['orderby'] = 'display_name';
    $args['order']   = 'ASC';
}

    $user_query = new WP_User_Query($args);
    $users = $user_query->get_results();

    $results = [];
    foreach ($users as $user) {
        $results[] = [
            'id'   => $user->ID,
            'text' => $user->display_name . ' (' . $user->user_email . ')'
        ];
    }

    $pagination = [
        'more' => (count($users) >= $per_page),
    ];

    // Return the JSON
    wp_send_json([
        'results'   => $results,
        'pagination'=> $pagination,
    ]);
}
function my_select2_init_script() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        $('.user-search').select2({
            placeholder: 'Search User',
            width: '100%', // Ensure dropdown takes up 100% width
            minimumInputLength: 0, // Trigger AJAX call on click or empty input
            ajax: {
                url: ajaxurl + '?action=my_user_search', // The WP AJAX endpoint
                dataType: 'json',
                delay: 250, // Add a small delay to reduce server load
                data: function(params) {
                    // "params.term" is the current search query.
                    // If empty, we pass an empty string.
                    return {
                        q: params.term || '', 
                        page: params.page || 1
                    };
                },
                processResults: function(data, params) {
                    // Handle pagination if necessary
                    params.page = params.page || 1;

                    return {
                        results: data.results,
                        pagination: data.pagination
                    };
                }
            }
        });
    });
    </script>
    <?php
}
add_action('admin_footer', 'my_select2_init_script');

//add_action('admin_enqueue_scripts', 'fix_select2_overlap_and_gap');
function my_render_audit_log_tab() {
    global $wpdb;
    $table_audit_log = $wpdb->prefix . 'user_groups_audit_log';
    $table_users = $wpdb->users;

    // Fetch the logs
    $logs = $wpdb->get_results("
        SELECT l.*, u.display_name AS user_name
        FROM $table_audit_log l
        LEFT JOIN $table_users u ON l.user_id = u.ID
        ORDER BY l.timestamp DESC
    ");

    ?>
    <h2>Audit Log</h2>
    <table class="widefat">
        <thead>
            <tr>
                <th>ID</th>
                <th>Group ID</th>
                <th>Action</th>
                <th>Old Data</th>
                <th>New Data</th>
                <th>Performed By</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($logs)): ?>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo esc_html($log->id); ?></td>
                    <td><?php echo esc_html($log->group_id); ?></td>
                    <td><?php echo esc_html($log->action); ?></td>
                    <td><pre><?php echo esc_html($log->old_data); ?></pre></td>
                    <td><pre><?php echo esc_html($log->new_data); ?></pre></td>
                    <td><?php echo esc_html($log->user_name ?: 'Unknown'); ?></td>
                    <td><?php echo esc_html($log->timestamp); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="7">No log entries found.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php
}

function vogo_assign_new_woocommerce_user_to_public_group($user_id) {
    global $wpdb;

    // Define the group name to assign
    $public_group_name = 'PUBLIC';

    // Fetch the PUBLIC group ID from the user groups table
    $table_groups = $wpdb->prefix . 'user_groups';
    $table_user_groups = $wpdb->prefix . 'user_group_users';

    $group_id = $wpdb->get_var($wpdb->prepare("
        SELECT id FROM $table_groups WHERE name = %s LIMIT 1
    ", $public_group_name));

    // If PUBLIC group exists, assign the new user
    if ($group_id) {
        // Check if the user is already assigned (prevents duplicates)
        $exists = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $table_user_groups WHERE user_id = %d AND group_id = %d
        ", $user_id, $group_id));

        if (!$exists) {
            $wpdb->insert($table_user_groups, [
                'user_id'  => $user_id,
                'group_id' => $group_id,
            ]);
        }
    }
}

// Hook into WooCommerce user registration
add_action('woocommerce_created_customer', 'vogo_assign_new_woocommerce_user_to_public_group');
