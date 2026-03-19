<?php
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Custom WP_List_Table to show and bulk-assign Users to Groups
 */
class My_Users_List_Table extends WP_List_Table {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct([
            'singular' => 'user',  // singular name of the listed records
            'plural'   => 'users', // plural name of the listed records
            'ajax'     => false    // does this table support ajax?
        ]);
    }

    /**
     * Define the columns that are going to be used in the table
     */
    public function get_columns() {
        $columns = [
            'cb'         => '<input type="checkbox" />', // Render a checkbox instead of text
            'display_name' => 'Name',
            'user_email'   => 'Email',
            'user_role'    => 'Role',
        ];
        return $columns;
    }

    /**
     * Optional: Define which columns are sortable
     */
    public function get_sortable_columns() {
        return [
            'display_name' => ['display_name', false],
            'user_email'   => ['user_email',   false],
        ];
    }

    /**
     * Render the checkbox for each row
     */
    protected function column_cb($item) {
        // $item is a WP_User object or custom array
        return sprintf(
            '<input type="checkbox" name="bulk_user_ids[]" value="%d" />',
            $item->ID
        );
    }

    /**
     * Default Column Rendering
     */
    protected function column_default($item, $column_name) {
        switch ($column_name) {
            case 'display_name':
                return esc_html($item->display_name);
            case 'user_email':
                return esc_html($item->user_email);
            case 'user_role':
                // Show the first role, or all roles
                return esc_html(implode(', ', $item->roles));
            default:
                return print_r($item, true); // For debugging
        }
    }

    /**
     * Prepare the table's items, handle pagination, ordering, etc.
     */
    public function prepare_items() {
        // 1. Set up pagination parameters
        $per_page     = 20; // number of users per page
        $current_page = $this->get_pagenum();

        // 2. Check for any sorting request
        $orderby = (!empty($_REQUEST['orderby'])) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'display_name';
        $order   = (!empty($_REQUEST['order']))   ? sanitize_text_field($_REQUEST['order'])   : 'ASC';

        // 3. Build your query to fetch users
        $args = [
            'number'     => $per_page,
            'offset'     => ($current_page - 1) * $per_page,
            'orderby'    => $orderby,
            'order'      => $order,
        ];

        // You can add search or role filters if you want:
        if (!empty($_REQUEST['s'])) {
            // WP_User_Query automatically does partial matches for 'search' with wildcard
            $args['search']         = '*' . sanitize_text_field($_REQUEST['s']) . '*';
            $args['search_columns'] = ['user_login', 'user_email', 'user_nicename', 'display_name'];
        }

        // 4. Query the users
        $user_query = new WP_User_Query($args);
        $items      = $user_query->get_results();
        $total_items = $user_query->get_total();

        // 5. Pass the final data to the items property
        $this->items = $items;

        // 6. Set the pagination arguments
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ]);
    }

    /**
     * Bulk actions are optional. We'll skip them if we're using a separate
     * "Assign to Group" approach. But let's define them for demonstration.
     */
    public function get_bulk_actions() {
        $actions = [
            'assign_group' => 'Assign to Group',
        ];
        return $actions;
    }
}
