<?php
/**
 * Renders the Groups Admin Page with Tabs: Groups, Users, Categories
 */

/**
 * Main callback for the "Groups" page
 */
function my_render_groups_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Allowed tabs
    $allowed_tabs = ['groups', 'users', 'categories'];
    $current_tab = isset($_GET['tab']) && in_array($_GET['tab'], $allowed_tabs)
        ? $_GET['tab']
        : 'groups'; // Default

    echo '<div class="wrap">';
    echo '<h1>Gestionarea Grupurilor</h1>';

    // Tab navigation
    echo '<h2 class="nav-tab-wrapper">';
    foreach ($allowed_tabs as $tab_slug) {
        $active_class = ($current_tab === $tab_slug) ? ' nav-tab-active' : '';
        $label = ucfirst($tab_slug); // Simple label (Groups, Users, Categories)
        echo '<a href="?page=my-groups-page&tab=' . $tab_slug . '" class="nav-tab' . $active_class . '">';
        echo esc_html($label);
        echo '</a>';
    }
    echo '</h2>';

    // Render the tab
    switch ($current_tab) {
        case 'groups':
            my_render_groups_tab();
            break;
        case 'users':
            my_render_users_tab();
            break;
        case 'categories':
            my_render_categories_tab();
            break;
    }

    echo '</div>'; // end .wrap
}

/**
 * Tab 1: Groups (Create and List Groups)
 */
function my_render_groups_tab() {
    global $wpdb;
    $table_groups = $wpdb->prefix . 'user_groups';

    // Handle form submission (create group)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_group_name'], $_POST['new_group_label'])) {
    $group_name = sanitize_text_field($_POST['new_group_name']);
    $group_label = sanitize_text_field($_POST['new_group_label']);
    $created_by = get_current_user_id(); // Get the current logged-in user's ID

    if (!empty($group_name)) {
        $wpdb->insert($table_groups, [
            'name'       => $group_name,
            'label'      => $group_label,
            'created_at' => current_time('mysql'),
            'user_id'    => $created_by, // Store the creator's user ID in the existing user_id field
        ]);
        echo '<div class="updated notice"><p>Grup nou adăugat!</p></div>';
    }
}


    // Handle group deletion
    if (isset($_GET['action'], $_GET['group_id']) && $_GET['action'] === 'delete') {
        $group_id = intval($_GET['group_id']);
        $user_id = get_current_user_id();
    
        // Fetch the old data before deleting
        $old_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_groups WHERE id = %d", $group_id), ARRAY_A);
    
        // Delete the group
        $deleted = $wpdb->delete($table_groups, ['id' => $group_id]);
    
        if ($deleted) {
            // Log the deletion
            $wpdb->insert($wpdb->prefix . 'user_groups_audit_log', [
                'group_id' => $group_id,
                'action'   => 'delete',
                'old_data' => json_encode($old_data),
                'new_data' => null,
                'user_id'  => $user_id,
            ]);
    
            echo '<div class="updated notice"><p>Grup șters cu succes!</p></div>';
        } else {
            echo '<div class="error notice"><p>Ștergerea grupului a eșuat și ea.</p></div>';
        }
    }
    

    // Handle group edit
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_group_id'], $_POST['edit_group_name'], $_POST['edit_group_label'])) {
        $group_id = intval($_POST['edit_group_id']);
        $group_name = sanitize_text_field($_POST['edit_group_name']);
        $group_label = sanitize_text_field($_POST['edit_group_label']);
        $user_id = get_current_user_id();
    
        // Fetch the old data before updating
        $old_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_groups WHERE id = %d", $group_id), ARRAY_A);
    
        if (!empty($group_id) && !empty($group_name)) {
            // Update the group
            $wpdb->update($table_groups, ['name' => $group_name, 'label' => $group_label], ['id' => $group_id]);
    
            // Log the update
            $wpdb->insert($wpdb->prefix . 'user_groups_audit_log', [
                'group_id' => $group_id,
                'action'   => 'update',
                'old_data' => json_encode($old_data),
                'new_data' => json_encode(['name' => $group_name, 'label' => $group_label]),
                'user_id'  => $user_id,
            ]);
    
            echo '<div class="updated notice"><p>Grupul a fost actualizat cu succes!</p></div>';
        }
    }
    // Pagination settings
    $items_per_page = 10; // Number of groups per page
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $items_per_page;

    // Get Search Term
    $search_term = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

    // Fetch total groups count
    $total_groups_query = "SELECT COUNT(*) FROM $table_groups";
    if (!empty($search_term)) {
        $total_groups_query .= $wpdb->prepare(" WHERE name LIKE %s", '%' . $wpdb->esc_like($search_term) . '%');
    }
    $total_groups = $wpdb->get_var($total_groups_query);

    // Fetch groups with pagination and search
    $groups_query = "
    SELECT g.*, u.display_name AS created_by_name
    FROM $table_groups g
    LEFT JOIN {$wpdb->users} u ON g.user_id = u.ID
";
if (!empty($search_term)) {
    $groups_query .= $wpdb->prepare(" WHERE g.name LIKE %s", '%' . $wpdb->esc_like($search_term) . '%');
}
$groups_query .= $wpdb->prepare(" ORDER BY g.id DESC LIMIT %d OFFSET %d", $items_per_page, $offset);
$groups = $wpdb->get_results($groups_query);

    // Calculate total pages
    $total_pages = ceil($total_groups / $items_per_page);
    // Fetch existing groups
    ?>
    <h2>Create New Group</h2>
    <form method="post">
        <table class="form-table">
            <tr>
                <th><label for="new_group_name">Cod grup</label></th>
                <td><input type="text" id="new_group_name" name="new_group_name" required></td>
            </tr>
            <tr>
                <th><label for="new_group_label">Etichetă Grup</label></th>
                <td><input type="text" id="new_group_label" name="new_group_label" required></td>
            </tr>
        </table>
        <p><button type="submit" class="button button-primary">Adaugă Grup</button></p>
    </form>

    <hr>

    <h2>Grupuri existente</h2>
     <!-- Search Form -->
     <form method="get" style="margin-bottom: 20px;">
        <input type="hidden" name="page" value="my-groups-page">
        <input type="text" name="s" value="<?php echo esc_attr($search_term); ?>" placeholder="Search groups..." style="width: 300px; padding: 5px;">
        <button type="submit" class="button">Search</button>
        <?php if (!empty($search_term)): ?>
            <a href="<?php echo admin_url('admin.php?page=my-groups-page'); ?>" class="button">Clear</a>
        <?php endif; ?>
    </form>

    <table class="widefat">
        <thead>
            <tr>
                <th>ID</th>
                <th>Cod Grup</th>
                <th>Etichetă Grup</th>
                <th>Creat la</th>
                <th> Creat de</th>
                <th>Acțiuni</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($groups)): ?>
            <?php foreach ($groups as $group): ?>
                <tr>
                    <td><?php echo esc_html($group->id); ?></td>
                    <td><?php echo esc_html($group->name); ?></td>
                    <td><?php echo esc_html($group->label); ?></td>
                    <td><?php echo esc_html($group->created_at); ?></td>
                    <td><?php echo esc_html($group->created_by_name ?: 'Unknown'); ?></td>
                    <td>
                        <button class="button button-secondary view-users-button" 
                                data-group-id="<?php echo esc_attr($group->id); ?>" 
                                data-group-name="<?php echo esc_attr($group->name); ?>">
                            View Users
                        </button>
                        <button class="button button-secondary edit-group-button" 
                                data-group-id="<?php echo esc_attr($group->id); ?>" 
                                data-group-name="<?php echo esc_attr($group->name); ?>"
                                data-group-label="<?php echo esc_attr($group->label); ?>">
                                
                            Edit
                        </button>
                        <a href="<?php echo esc_url(add_query_arg([
                            'action'   => 'delete',
                            'group_id' => $group->id,
                        ], admin_url('admin.php?page=my-groups-page'))); ?>" 
                           class="button button-secondary" 
                           onclick="return confirm('Ești sigur(ă) că vrei să ștergi acest grup?');">
                            Delete
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="4">Nu s-au găsit grupuri.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination Links -->
    <div class="tablenav-pages">
    <div class="pagination-container">
    <?php
    if ($total_pages > 1) {
        $pagination_base = admin_url('admin.php?page=my-groups-page%_%');
        $pagination_format = '&paged=%#%';

        echo paginate_links(array(
            'base'      => $pagination_base,
            'format'    => $pagination_format,
            'current'   => $current_page,
            'total'     => $total_pages,
            'prev_text' => '&laquo; Previous',
            'next_text' => 'Next &raquo;',
            'type'      => 'list', // Outputs pagination as a <ul>
        ));
    }
    ?>
</div>
    </div>

     <!-- Modal for Editing Group -->
     <div id="edit-group-modal" style="display:none;">
        <div class="group-modal-content">
            <h2>Edit Group</h2>
            <form method="post" id="edit-group-form">
                <input type="hidden" id="edit_group_id" name="edit_group_id">
                <table class="form-table">
                    <tr>
                        <th><label for="edit_group_name">Nume Grup</label></th>
                        <td><input type="text" id="edit_group_name" name="edit_group_name" required></td>
                    </tr>
                    <tr>
                        <th><label for="edit_group_label">Etichetă Grup</label></th>
                        <td><input type="text" id="edit_group_label" name="edit_group_label" required></td>
                    </tr>
                </table>
                <p><button type="submit" class="button button-primary"> Salvează modificările</button></p>
            </form>
            <p><button id="close-edit-modal" class="button">Close</button></p>
        </div>
    </div>
    <div id="group-users-modal" style="display:none;">
    <div class="group-users-modal-content">
        <h2>Utilizatorii Grup: <span id="group-name"></span></h2>

        <!-- Search Box -->
        <input type="text" id="user-search" placeholder="Search users..." style="width: 100%; margin-bottom: 10px;">

        <table class="widefat">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                </tr>
            </thead>
            <tbody id="group-users-list">
                <tr>
                    <td colspan="3">Loading...</td>
                </tr>
            </tbody>
        </table>

        <!-- Pagination Controls -->
        <div id="pagination" style="margin-top: 10px; text-align: right;"></div>

        <p><button id="close-modal" class="button">Close</button></p>
    </div>
    </div>


    <style>
        /* Modal styling */
        #group-users-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .group-users-modal-content {
            background: #fff;
            padding: 20px;
            width: 80%;
            max-width: 600px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        #edit-group-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .group-modal-content {
            background: #fff;
            padding: 20px;
            width: 400px;
            border-radius: 8px;
        }
    </style>
    <script>
    jQuery(document).ready(function ($) {
        let currentPage = 1;
        let currentSearch = '';
        let currentGroupId = null; // Store the selected group ID globally

        // Function to fetch users
        function fetchUsers(groupId) {
            $.ajax({
                url: ajaxurl,
                method: 'GET',
                data: {
                    action: 'get_group_users',
                    group_id: groupId,
                    search: currentSearch,
                    page: currentPage,
                },
                beforeSend: function () {
                    $('#group-users-list').html('<tr><td colspan="3">Loading...</td></tr>');
                },
                success: function (response) {
                    if (response.success) {
                        const { users, total_users, per_page, current_page } = response.data;
                        let rows = '';

                        if (users.length > 0) {
                            users.forEach(user => {
                                rows += `
                                    <tr>
                                        <td>${user.ID}</td>
                                        <td>${user.display_name}</td>
                                        <td>${user.user_email}</td>
                                    </tr>
                                `;
                            });
                        } else {
                            rows = '<tr><td colspan="3">Nu s-au găsit utilizatori.</td></tr>';
                        }

                        $('#group-users-list').html(rows);

                        // Update pagination
                        const totalPages = Math.ceil(total_users / per_page);
                        $('#pagination').html('');
                        if (totalPages > 1) {
                            if (current_page > 1) {
                                $('#pagination').append('<button class="pagination-btn" data-page="' + (current_page - 1) + '">Previous</button>');
                            }
                            if (current_page < totalPages) {
                                $('#pagination').append('<button class="pagination-btn" data-page="' + (current_page + 1) + '">Next</button>');
                            }
                        }
                    } else {
                        $('#group-users-list').html('<tr><td colspan="3">Eroare la încărcarea utilizatorilor.</td></tr>');
                    }
                },
                error: function () {
                    $('#group-users-list').html('<tr><td colspan="3">Eroare la încărcarea utilizatorilor.</td></tr>');
                }
            });
        }
        $('.edit-group-button').click(function () {
            const groupId = $(this).data('group-id');
            const groupName = $(this).data('group-name');
            const groupLabel = $(this).data('group-label');

            $('#edit_group_id').val(groupId);
            $('#edit_group_name').val(groupName);
            $('#edit_group_label').val(groupLabel);

            $('#edit-group-modal').fadeIn();
        });

        // Close edit modal
        $('#close-edit-modal').click(function () {
            $('#edit-group-modal').fadeOut();
        });

        // Handle "View Users" button click
        $('.view-users-button').click(function () {
            currentGroupId = $(this).data('group-id'); // Store the selected group ID
            const groupName = $(this).data('group-name');
            currentPage = 1; // Reset page
            currentSearch = ''; // Reset search

            // Update modal title
            $('#group-name').text(groupName);

            // Show modal
            $('#group-users-modal').fadeIn();

            // Fetch users for the selected group
            fetchUsers(currentGroupId);
        });

        // Handle pagination button clicks
        $(document).on('click', '.pagination-btn', function () {
            currentPage = $(this).data('page');
            console.log('Current Page:', currentPage); // Debugging
            console.log('Current Group ID:', currentGroupId); // Debugging
            fetchUsers(currentGroupId); // Use the stored group ID
        });

        // Handle search input
        $('#user-search').on('input', function () {
            currentSearch = $(this).val();
            currentPage = 1; // Reset to the first page
            console.log('Current Search:', currentSearch); // Debugging
            fetchUsers(currentGroupId); // Use the stored group ID
        });

        // Close modal
        $('#close-modal').click(function () {
            $('#group-users-modal').fadeOut();
        });
    });
    </script>

    <?php
}

// AJAX handler to fetch users in a group
function my_get_group_users() {
    global $wpdb;
    $group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;
    $search   = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $page     = isset($_GET['page']) && intval($_GET['page']) > 0 ? intval($_GET['page']) : 1;
    $per_page = 10; // Number of users per page
    $offset   = ($page - 1) * $per_page;

    if (!$group_id) {
        wp_send_json_error('ID grup invalid.');
    }

    $table_user_groups = $wpdb->prefix . 'user_group_users';
    $users_table       = $wpdb->users;

    // Debugging
    error_log('Group ID: ' . $group_id);
    error_log('Page: ' . $page);
    error_log('Offset: ' . $offset);

    // Query to fetch users with optional search
    $query = $wpdb->prepare("
        SELECT u.ID, u.display_name, u.user_email
        FROM $table_user_groups ug
        INNER JOIN $users_table u ON ug.user_id = u.ID
        WHERE ug.group_id = %d
        AND (u.display_name LIKE %s OR u.user_email LIKE %s)
        LIMIT %d OFFSET %d
    ", $group_id, "%$search%", "%$search%", $per_page, $offset);

    $users = $wpdb->get_results($query);

    // Get total user count for pagination
    $total_users = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM $table_user_groups ug
        INNER JOIN $users_table u ON ug.user_id = u.ID
        WHERE ug.group_id = %d
        AND (u.display_name LIKE %s OR u.user_email LIKE %s)
    ", $group_id, "%$search%", "%$search%"));

    error_log('Total Users: ' . $total_users);

    wp_send_json_success([
        'users'        => $users,
        'total_users'  => intval($total_users),
        'per_page'     => $per_page,
        'current_page' => $page,
    ]);
}
add_action('wp_ajax_get_group_users', 'my_get_group_users');




/**
 * Tab 2: Users (Assign Users to Groups)
 */
function my_render_users_tab() {
    global $wpdb;
    $table_groups      = $wpdb->prefix . 'user_groups';
    $table_user_groups = $wpdb->prefix . 'user_group_users';

    // Handle form submission (update user-to-group assignments)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_ids'], $_POST['group_ids'])) {
        $user_ids  = array_map('intval', $_POST['user_ids']);
        $group_ids = array_map('intval', $_POST['group_ids']);

        foreach ($user_ids as $user_id) {
            // Remove all existing group assignments for this user
            $wpdb->delete($table_user_groups, ['user_id' => $user_id]);

            // Add new group assignments
            foreach ($group_ids as $group_id) {
                $wpdb->insert($table_user_groups, [
                    'user_id'  => $user_id,
                    'group_id' => $group_id,
                ]);
            }
        }

        echo '<div class="updated notice"><p>Utilizatorii au fost actualizați cu grupurile selectate!</p></div>';
    }

    // Fetch all groups
    $groups = $wpdb->get_results("SELECT * FROM $table_groups ORDER BY name ASC");

    // Fetch all user-to-group relationships
    $user_group_map = [];
    $assignments = $wpdb->get_results("
        SELECT u.ID as user_id, u.display_name, u.user_email, g.id as group_id, g.name as group_name
        FROM $table_user_groups ug
        INNER JOIN $wpdb->users u ON ug.user_id = u.ID
        INNER JOIN $table_groups g ON ug.group_id = g.id
        ORDER BY u.display_name, g.name
    ");

    foreach ($assignments as $assignment) {
        $user_group_map[$assignment->user_id][] = [
            'group_id'   => $assignment->group_id,
            'group_name' => $assignment->group_name,
        ];
    }

    ?>
    <h2>Atribuie utilizatori grupurilor</h2>
    <form method="post">
        <table class="form-table">
            <tr>
                <th><label for="user_id">Users</label></th>
                <td>
                    <!-- Multi-select dropdown for users -->
                    <select name="user_ids[]" id="user_id" class="user-search" multiple style="width: 300px;">
                        <?php
                        $users = get_users();
                        foreach ($users as $user): ?>
                            <option value="<?php echo esc_attr($user->ID); ?>">
                                <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th>Groups</th>
                <td id="group-checkboxes">
                    <?php if (!empty($groups)): ?>
                        <?php foreach ($groups as $group): ?>
                            <label style="display:block;">
                                <input type="checkbox" name="group_ids[]" value="<?php echo esc_attr($group->id); ?>">
                                <?php echo esc_html($group->name); ?>
                            </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>Nu s-au găsit grupuri. Creează mai întâi câteva în fila Grupuri.</p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <input type="submit" class="button button-primary" value="Assign Users">
    </form>

    <!-- Existing Assignments -->
    <h2>Atribuiri Existente</h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>Utilizator</th>
                <th>Email</th>
                <th>Grupuri atribuite</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($user_group_map)): ?>
                <?php foreach ($user_group_map as $user_id => $groups): ?>
                    <tr>
                        <td>
                            <?php 
                            $user_info = get_user_by('id', $user_id);
                            echo esc_html($user_info->display_name); 
                            ?>
                        </td>
                        <td><?php echo esc_html($user_info->user_email); ?></td>
                        <td>
                            <?php echo esc_html(implode(', ', array_column($groups, 'group_name'))); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3">Nu s-au găsit atribuiri.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <script>
        (function ($) {
            $(document).ready(function () {
                const userGroupMap = <?php echo json_encode($user_group_map); ?>;

                // When a user is selected, check the corresponding group checkboxes
                $('#user_id').change(function () {
                    const selectedUserIds = $(this).val(); // Array of selected user IDs
                    const selectedGroups = new Set();

                    // Collect all groups for selected users
                    selectedUserIds.forEach(function (userId) {
                        const userGroups = userGroupMap[userId] || [];
                        userGroups.forEach(group => selectedGroups.add(group.group_id));
                    });

                    // Clear all checkboxes
                    $('#group-checkboxes input[type="checkbox"]').prop('checked', false);

                    // Check the ones matching the selected users' groups
                    selectedGroups.forEach(function (groupId) {
                        $('#group-checkboxes input[value="' + groupId + '"]').prop('checked', true);
                    });
                });

                // Initialize Select2 for multi-select
                $('#user_id').select2({
                    placeholder: 'Search and select users',
                    allowClear: true,
                });
            });
        })(jQuery);
    </script>
    <?php
}

/**
 * Tab 3: Categories (Assign Categories to Groups)
 */
function my_render_categories_tab() {
    global $wpdb;
    $table_groups       = $wpdb->prefix . 'user_groups';
    $table_group_cats   = $wpdb->prefix . 'user_group_categories';

    // Handle remove assignment action
    if (isset($_GET['action'], $_GET['group_id'], $_GET['category_id']) && $_GET['action'] === 'remove_assignment') {
        $group_id = intval($_GET['group_id']);
        $category_id = intval($_GET['category_id']);

        // Delete the specific assignment
        $deleted = $wpdb->delete($table_group_cats, [
            'group_id'    => $group_id,
            'category_id' => $category_id,
        ]);

        if ($deleted) {
            echo '<div class="updated notice"><p>Atribuirea categoriei a fost eliminată cu succes!</p></div>';
        } else {
            echo '<div class="error notice"><p>Eliminarea atribuirii categoriei a eșuat.</p></div>';
        }
    }

    // Handle form submission (assign categories to a group)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_id'], $_POST['category_ids'])) {
        $group_id     = intval($_POST['group_id']);
        $category_ids = array_map('intval', $_POST['category_ids']);

        // Remove existing categories for that group
        $wpdb->delete($table_group_cats, ['group_id' => $group_id]);

        // Insert new category assignments
        foreach ($category_ids as $cat_id) {
            $wpdb->insert($table_group_cats, [
                'group_id'    => $group_id,
                'category_id' => $cat_id,
            ]);
        }

        echo '<div class="updated notice"><p>Categorii atribuite acestui grup!</p></div>';
    }

    // Get all groups
    $groups = $wpdb->get_results("SELECT * FROM $table_groups ORDER BY name ASC");

    ?>
    <h2>Atribuie și categorii grupului</h2>
    <form method="post" id="assign-categories-form">
        <table class="form-table">
            <tr>
                <th><label for="group_id">Selectează Grupul</label></th>
                <td>
                    <select name="group_id" id="group_id" required>
                        <option value="">-- Select Group --</option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?php echo esc_attr($group->id); ?>">
                                <?php echo esc_html($group->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th>Categorii de produse</th>
                <td id="categories-checkboxes">
                    <!-- Categories checkboxes will be dynamically loaded here -->
                    <p>Selectează un grup pentru a vedea categoriile.</p>
                </td>
            </tr>
        </table>
        <p><button type="submit" class="button button-primary">Atribuie categorii</button></p>
    </form>

    <hr>

    <h2>Atribuiri existente de categorii</h2>
    <table class="widefat">
        <thead>
            <tr>
                <th>Nume grup</th>
                <th>Nume categorie</th>
                <th>Acțiuni</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Fetch all existing assignments
            $assignments = $wpdb->get_results("
                SELECT g.name as group_name, t.name as category_name, g.id as group_id, t.term_id as category_id
                FROM $table_group_cats gc
                INNER JOIN $table_groups g ON gc.group_id = g.id
                INNER JOIN {$wpdb->prefix}terms t ON gc.category_id = t.term_id
                ORDER BY g.name, t.name
            ");

            if (!empty($assignments)) {
                foreach ($assignments as $assignment) {
                    ?>
                    <tr>
                        <td><?php echo esc_html($assignment->group_name); ?></td>
                        <td><?php echo esc_html($assignment->category_name); ?></td>
                        <td>
                        <a href="<?php echo esc_url(add_query_arg([
                            'page'       => 'my-groups-page', // Main menu slug
                            'tab'        => 'categories',    // Tab to maintain context
                            'group_id'   => $assignment->group_id,
                            'category_id'=> $assignment->category_id,
                            'action'     => 'remove_assignment',
                        ], admin_url('admin.php'))); ?>" 
                        class="button button-secondary remove-assignment">
                        Remove
                        </a>
                        </td>
                    </tr>
                    <?php
                }
            } else {
                echo '<tr><td colspan="3">Nu s-au găsit atribuiri.</td></tr>';
            }
            ?>
        </tbody>
    </table>
    <script>
jQuery(document).ready(function ($) {
    // When the group dropdown changes
    $('#group_id').change(function () {
        const groupId = $(this).val();

        if (!groupId) {
            $('#categories-checkboxes').html('<p>Selectează un grup pentru a vedea categoriile.</p>');
            return;
        }

        // Make the AJAX request to fetch categories
        $.ajax({
            url: ajaxurl,
            method: 'GET',
            data: {
                action: 'my_get_group_categories', // Matches the PHP handler
                group_id: groupId,
            },
            beforeSend: function () {
                $('#categories-checkboxes').html('<p>Se încarcă categoriile...</p>');
            },
            success: function (response) {
                if (response.success) {
                    const categories = response.data.categories;
                    let checkboxes = '';

                    if (categories.length > 0) {
                        // Create checkboxes for all categories
                        categories.forEach(category => {
                            checkboxes += `
                                <label style="display:block;">
                                    <input type="checkbox" name="category_ids[]" value="${category.term_id}" ${category.assigned ? 'checked' : ''}>
                                    ${category.name}
                                </label>
                            `;
                        });
                    } else {
                        checkboxes = '<p>Nu s-au găsit categorii.</p>';
                    }

                    $('#categories-checkboxes').html(checkboxes);
                } else {
                    $('#categories-checkboxes').html('<p>Eroare la încărcarea categoriilor.</p>');
                    console.error(response.data);
                }
            },
            error: function () {
                $('#categories-checkboxes').html('<p>Eroare la încărcarea categoriilor.</p>');
            }
        });
    });
});

</script>

    <?php
}



function my_get_group_categories() {
    global $wpdb;
    error_log('AJAX request received.'); // Log to check if the function is triggered
    $group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;

    if (!$group_id) {
        wp_send_json_error('Invalid group ID.');
    }

    $table_group_cats = $wpdb->prefix . 'user_group_categories';

    // Fetch all WooCommerce product categories
    $product_cats = get_terms([
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
    ]);

    if (is_wp_error($product_cats)) {
        wp_send_json_error('Error fetching product categories: ' . $product_cats->get_error_message());
    }

    // Fetch assigned categories for the group
    $assigned_cats = $wpdb->get_col($wpdb->prepare("
        SELECT category_id 
        FROM $table_group_cats 
        WHERE group_id = %d
    ", $group_id));

    // Mark categories as assigned or not
    $categories = [];
    foreach ($product_cats as $cat) {
        $categories[] = [
            'term_id'  => $cat->term_id,
            'name'     => $cat->name,
            'assigned' => in_array($cat->term_id, $assigned_cats),
        ];
    }

    wp_send_json_success(['categories' => $categories]);
}
add_action('wp_ajax_my_get_group_categories', 'my_get_group_categories'); // For logged-in users
add_action('wp_ajax_nopriv_my_get_group_categories', 'my_get_group_categories'); // For guests (if applicable)

