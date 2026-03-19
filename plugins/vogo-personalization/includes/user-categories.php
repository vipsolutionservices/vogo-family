<?php
function vogo_assign_user_to_group($user_id, $group_id) {
    global $wpdb;
    $wpdb->insert("{$wpdb->prefix}user_groups", [
        'user_id' => $user_id,
        'group_id' => $group_id
    ]);
}

function vogo_get_user_categories($user_id) {
    global $wpdb;
    $categories = $wpdb->get_col("
        SELECT category_id 
        FROM {$wpdb->prefix}group_categories gc
        INNER JOIN {$wpdb->prefix}user_groups ug ON gc.group_id = ug.group_id
        WHERE ug.user_id = $user_id
    ");
    return $categories;
}
