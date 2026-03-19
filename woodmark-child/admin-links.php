<?php
add_action('after_setup_theme', 'vogo_create_project_links_table_once');
function vogo_create_project_links_table_once() {
    $installed = get_option('vogo_project_links_table_created');
    if (!$installed) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'vogo_project_links';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            url TEXT NOT NULL,
            description TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        update_option('vogo_project_links_table_created', 1);
    }
}

function vogo_render_project_links_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vogo_project_links';

    // Handle form submission
    if (isset($_POST['vogo_add_link'])) {
        $title = sanitize_text_field($_POST['title']);
        $url = esc_url_raw($_POST['url']);
        $desc = sanitize_textarea_field($_POST['description']);

        $wpdb->insert($table_name, [
            'title' => $title,
            'url' => $url,
            'description' => $desc,
        ]);
        echo '<div class="notice notice-success"><p>Link added.</p></div>';
    }

    // Handle delete
    if (isset($_GET['delete']) && current_user_can('manage_options')) {
        $wpdb->delete($table_name, ['id' => intval($_GET['delete'])]);
        echo '<div class="notice notice-success"><p>Link deleted.</p></div>';
    }

    $links = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");

    ?>
    <div class="wrap">
        <h1>Project Links</h1>
        <form method="post">
            <h2>Add New Link</h2>
            <table class="form-table">
                <tr>
                    <th><label for="title">Title</label></th>
                    <td><input name="title" type="text" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="url">URL</label></th>
                    <td><input name="url" type="url" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="description">Description</label></th>
                    <td><textarea name="description" rows="3" class="large-text"></textarea></td>
                </tr>
            </table>
            <p><input type="submit" name="vogo_add_link" class="button button-primary" value="Add Link"></p>
        </form>

        <hr>

        <h2>Saved Links</h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>URL</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($links): foreach ($links as $link): ?>
                    <tr>
                        <td><?php echo esc_html($link->title); ?></td>
                        <td><a href="<?php echo esc_url($link->url); ?>" target="_blank"><?php echo esc_url($link->url); ?></a></td>
                        <td><?php echo esc_html($link->description); ?></td>
                        <td><a href="?page=vogo-project-links&delete=<?php echo $link->id; ?>" onclick="return confirm('Delete this link?')">Delete</a></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="4">No links added yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}