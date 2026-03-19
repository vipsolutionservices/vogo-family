<?php
add_action('admin_head-user-edit.php', 'replace_role_dropdown_with_multiselect');
add_action('admin_head-profile.php', 'replace_role_dropdown_with_multiselect');

function replace_role_dropdown_with_multiselect() {
    ob_start('custom_filter_role_field');
}

function custom_filter_role_field($content) {
    if (strpos($content, '<label for="role">Role</label>') !== false) {
        // Remove default role field (entire table row)
        $content = preg_replace('/<tr class="user-role-wrap".*?<\/tr>/s', '', $content);
    }
    return $content;
}

add_action('edit_user_profile', 'custom_multiselect_roles_field', 10);
add_action('show_user_profile', 'custom_multiselect_roles_field', 10);

function custom_multiselect_roles_field($user) {
    if (!current_user_can('manage_options')) return;

    global $wp_roles;
    $roles = $wp_roles->roles;
    $user_roles = (array) $user->roles;
    //echo '<h2>Roles</h2>';
    ?>
    <table class="form-table">
        <tr class="user-role-wrap">
            <th><label for="custom_user_roles">Roles</label></th>
            <td>
                <select name="custom_user_roles[]" multiple style="height: 150px; width: 250px;">
                    <?php foreach ($roles as $role_key => $role_details) : ?>
                        <option value="<?php echo esc_attr($role_key); ?>" <?php selected(in_array($role_key, $user_roles)); ?>>
                            <?php echo esc_html($role_details['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">You can assign multiple roles here.</p>
            </td>
        </tr>
    </table>
    <?php
}

add_action('personal_options_update', 'save_custom_user_roles_override');
add_action('edit_user_profile_update', 'save_custom_user_roles_override');

function save_custom_user_roles_override($user_id) {
    if (!current_user_can('manage_options')) return;

    if (!isset($_POST['custom_user_roles'])) return;

    $new_roles = array_map('sanitize_text_field', $_POST['custom_user_roles']);

    $user = new WP_User($user_id);

    // Remove all current roles
    foreach ($user->roles as $role) {
        $user->remove_role($role);
    }

    // Add all selected roles
    foreach ($new_roles as $role) {
        $user->add_role($role);
    }
}