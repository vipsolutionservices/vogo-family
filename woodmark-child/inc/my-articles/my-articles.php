<?php
// Register the custom post type
function wrac_register_my_article_cpt() {
    register_post_type('my_article', array(
        'labels' => array(
            'name' => __('Articole Utilizator', 'textdomain'),
            'singular_name' => __('Articol', 'textdomain'),
        ),
        'public' => true,
        'has_archive' => true,
        'rewrite' => array('slug' => 'community-articles'),
        'supports' => array('title', 'editor', 'author', 'comments', 'thumbnail'),
        'capability_type' => 'post',
        'show_in_rest' => true,
    ));

    // Register City taxonomy
    register_taxonomy('article_city', 'my_article', array(
        'label'        => __('Orașe', 'textdomain'),
        'rewrite'      => array('slug' => 'article-city'),
        'hierarchical' => true,
        'show_in_rest' => true,
        'show_ui' => true,
    ));

    // Register Domain taxonomy
    register_taxonomy('article_domain', 'my_article', array(
        'label'        => __('Domenii', 'textdomain'),
        'rewrite'      => array('slug' => 'article-domain'),
        'hierarchical' => true,
        'show_in_rest' => true,
        'show_ui' => true,
    ));
}
add_action('init', 'wrac_register_my_article_cpt');

// Add new endpoint to WooCommerce My Account menu
add_filter('woocommerce_account_menu_items', 'wrac_add_my_articles_menu');
function wrac_add_my_articles_menu($items) {
    $items['my-articles'] = __('Forum', 'textdomain');
    return $items;
}

// Register the endpoint
add_action('init', 'wrac_register_my_articles_endpoint');
function wrac_register_my_articles_endpoint() {
    add_rewrite_endpoint('my-articles', EP_ROOT | EP_PAGES);
	add_action('template_redirect', function() {
        global $wp_query;
        if (isset($wp_query->query_vars['my-articles'])) {
            wp_redirect(home_url('/forum'));
            exit;
        }
    });
}

// Handle endpoint content
add_action('woocommerce_account_my-articles_endpoint', 'wrac_my_articles_content');
function wrac_my_articles_content() {
    if (!is_user_logged_in()) {
        echo '<p>Trebuie să fii autentificat pentru a trimite articole.</p>';
        return;
    }

    if (isset($_GET['submitted']) && $_GET['submitted'] === '1') {
        echo '<div class="woocommerce-message">Articolul tău a fost trimis cu succes și așteaptă aprobarea administratorului.</div>';
    }

    $current_user_id = get_current_user_id();

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wrac_article_nonce'])) {
        if (
            wp_verify_nonce($_POST['wrac_article_nonce'], 'wrac_submit_article') &&
            !empty($_POST['article_title']) &&
            !empty($_POST['article_content']) &&
            !empty($_POST['article_city']) &&
            !empty($_POST['article_domain'])
        ) {
            $title   = sanitize_text_field($_POST['article_title']);
            $content = wp_kses_post($_POST['article_content']);
            $city    = sanitize_text_field($_POST['article_city']);
            $domain  = sanitize_text_field($_POST['article_domain']);

            $attachment_id = 0;
            if (!empty($_FILES['featured_image']['name'])) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';
                $upload = media_handle_upload('featured_image', 0);
                if (!is_wp_error($upload)) {
                    $attachment_id = $upload;
                }
            }

            $post_id = wp_insert_post([
                'post_type'    => 'my_article',
                'post_title'   => $title,
                'post_content' => $content,
                'post_status'  => 'pending',
                'post_author'  => $current_user_id,
            ]);

            if ($post_id && !is_wp_error($post_id)) {
                if ($attachment_id) {
                    set_post_thumbnail($post_id, $attachment_id);
                }
                $city_term = term_exists($city, 'article_city');
                if (!$city_term) {
                    $city_term = wp_insert_term($city, 'article_city');
                }
                $city_term_id = is_array($city_term) ? $city_term['term_id'] : $city_term;
                wp_set_post_terms($post_id, [$city_term_id], 'article_city', false);

                $domain_term = term_exists($domain, 'article_domain');
                if (!$domain_term) {
                    $domain_term = wp_insert_term($domain, 'article_domain');
                }
                $domain_term_id = is_array($domain_term) ? $domain_term['term_id'] : $domain_term;
                wp_set_post_terms($post_id, [$domain_term_id], 'article_domain', false);
                wp_safe_redirect( add_query_arg('submitted', '1') );
                exit;
            } else {
                echo '<div class="woocommerce-error">Ceva nu a mers bine. Te rugăm să încerci din nou.</div>';
            }
        }
    }

    // Include the form template
    $form_path = get_stylesheet_directory() . '/inc/my-articles/templates/my-accounts/form-my-article.php';
    if (file_exists($form_path)) {
        include $form_path;
    }

    // Show user-submitted articles
    $args = [
        'post_type'      => 'my_article',
        'author'         => $current_user_id,
        'post_status'    => ['publish', 'pending'],
        'posts_per_page' => -1,
    ];
    $query = new WP_Query($args);

    echo '<h3>Articolele tale trimise</h3>';
    echo '<p><a href="https://test07.vogo.family/forum/" target="_blank" class="forum-link">Mergi la forum</a></p>';
    if ($query->have_posts()) {
        echo '<ul>';
        while ($query->have_posts()) {
            $query->the_post();
            echo '<li><strong>' . get_the_title() . '</strong>';
            if (get_post_status() === 'publish') {
                echo ' – <a href="' . get_permalink() . '" target="_blank">Vizualizează articolul</a>';
            } else {
                echo ' – ' . ucfirst(get_post_status());
            }
            echo '</li>';
        }
        echo '</ul>';
        wp_reset_postdata();
    } else {
        echo '<p>Nu au fost trimise articole încă.</p>';
    }
}

// Import cities to taxonomy
function wrac_import_cities_to_taxonomy() {
    if (get_option('wracc_cities_imported')) {
        return;
    }

    $cities_json = file_get_contents(get_stylesheet_directory(). '/inc/data/cities.json');
    $cities_data = json_decode($cities_json, true);

    if (!empty($cities_data['cities']) && is_array($cities_data['cities'])) {
        foreach ($cities_data['cities'] as $city) {
            if (!term_exists($city, 'article_city')) {
                wp_insert_term($city, 'article_city');
            }
        }
    }

    update_option('wracc_cities_imported', true);
}
add_action('admin_init', 'wrac_import_cities_to_taxonomy');

// Import domains to taxonomy
function wrac_import_domains_to_taxonomy() {
    if (get_option('wracc_domains_imported')) {
        return;
    }

    $domains = [
        'Restaurant',
        'Hotel',
        'Agentie de turism',
        'Transport',
        'Livrarea alimentelor',
        'Asistență medicală',
        'Casa si gradina',
        'Coaching',
        'Activitati copii',
        'Psiholog',
        'Altele'
    ];

    foreach ($domains as $domain) {
        if (!term_exists($domain, 'article_domain')) {
            wp_insert_term($domain, 'article_domain');
        }
    }

    update_option('wracc_domains_imported', true);
}
add_action('admin_init', 'wrac_import_domains_to_taxonomy');

// Add dropdown filters for city and domain in admin post list
function wrac_add_article_filters_to_admin() {
    global $typenow;
    if ($typenow === 'my_article') {
        $taxonomies = ['article_city' => 'City', 'article_domain' => 'Domain'];
        foreach ($taxonomies as $taxonomy => $label) {
            $selected = isset($_GET[$taxonomy]) ? $_GET[$taxonomy] : '';
            wp_dropdown_categories([
                'show_option_all' => "All $label",
                'taxonomy'        => $taxonomy,
                'name'            => $taxonomy,
                'orderby'         => 'name',
                'selected'        => $selected,
                'hierarchical'    => true,
                'depth'           => 1,
                'show_count'      => false,
                'hide_empty'      => false,
            ]);
        }
    }
}
add_action('restrict_manage_posts', 'wrac_add_article_filters_to_admin');

// Apply taxonomy filters to admin query
function wrac_filter_articles_by_taxonomy($query) {
    global $pagenow;
    $post_type = isset($_GET['post_type']) ? $_GET['post_type'] : '';
    if ($pagenow === 'edit.php' && $post_type === 'my_article') {
        $taxonomies = ['article_city', 'article_domain'];
        foreach ($taxonomies as $taxonomy) {
            if (!empty($_GET[$taxonomy]) && is_numeric($_GET[$taxonomy])) {
                $term = get_term_by('id', $_GET[$taxonomy], $taxonomy);
                if ($term) {
                    $query->query_vars[$taxonomy] = $term->slug;
                }
            }
        }
    }
}
add_filter('parse_query', 'wrac_filter_articles_by_taxonomy');