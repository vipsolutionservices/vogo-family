<?php
/**
 * Shortcodes for Groups - Show user-specific products
 */
/**
 * Shortcode: [user_group_products]
 */
function my_shortcode_show_user_group_products($atts) {
    global $wpdb;

    // 1. Check if user is logged in
    $user_id = get_current_user_id();
    if (!$user_id) {
        return '';
    }

    // 2. Get all group IDs and their names for this user
    $table_user_groups = $wpdb->prefix . 'user_group_users';
    $table_groups = $wpdb->prefix . 'user_groups';
    $groups = $wpdb->get_results($wpdb->prepare("
        SELECT g.id, g.name
        FROM $table_user_groups ug
        INNER JOIN $table_groups g ON ug.group_id = g.id
        WHERE ug.user_id = %d
    ", $user_id));

    if (empty($groups)) {
        return '<p>Nu Ești Atribuit(ă) Niciunui Grup.</p>';
    }

    // 3. Collect group names and category IDs
    $group_names = [];
    $category_ids = [];
    foreach ($groups as $group) {
        $group_names[] = $group->name;

        // Get category IDs linked to this group
        $table_group_cats = $wpdb->prefix . 'user_group_categories';
        $categories = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT category_id
            FROM $table_group_cats
            WHERE group_id = %d
        ", $group->id));

        // Merge category IDs
        if (!empty($categories)) {
            $category_ids = array_merge($category_ids, $categories);
        }
    }

    // Remove duplicate category IDs
    $category_ids = array_unique($category_ids);

    if (empty($category_ids)) {
        return '<p>Nici o categorie nu este atribuită grupurilor tale.</p>';
    }

    // 4. Query WooCommerce products in these categories
    $args = array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 14, // Show all products
        'tax_query'      => array(
            array(
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $category_ids,
                'operator' => 'IN',
            ),
        ),
    );

    $products = new WP_Query($args);

    // 5. Render the output
    ob_start();

    // Display group names in a single line
    echo '<h1 class="recommended-products-title" style="font-size:24px; margin-bottom: 20px;">' . esc_html__('
Produse selectate și recomandate pentru dvs', 'your-textdomain') . '</h1>';
    echo '<div class="group-names" style="margin-bottom: 20px;">';
    //echo '<strong>Groups:</strong> ' . implode(', ', array_map('esc_html', $group_names));
    echo '</div>';

    // Display products
    if ($products->have_posts()) {
        echo '<ul class="recommended-products products">';
        while ($products->have_posts()) {
            $products->the_post();
            // Use WooCommerce's built-in product template part
            wc_get_template_part('content', 'product');
        }
        echo '</ul>';
    } else {
        echo '<p>Nu s-au găsit produse în categoriile tale atribuite.</p>';
    }

    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('user_group_products', 'my_shortcode_show_user_group_products');