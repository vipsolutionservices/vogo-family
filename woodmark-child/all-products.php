<?php
/**
 * VOGO API Endpoint Registration
 * All routes use standard JWT security check: vogo_permission_check
 * Adi-tehnic: clean, organized, secure, ergonomic structure.
 */

use Twilio\Rest\Client;

add_action('rest_api_init', function () {

    // Brands & Products
    register_rest_route('vogo/v1', '/brands', [
        'methods'             => 'GET',
        'callback'            => 'brands_listing',
        'permission_callback' => 'vogo_permission_check',
    ]);

    register_rest_route('vogo/v1', '/product-list2', [
        'methods'             => 'GET',
        'callback'            => 'custom_products',
        'permission_callback' => 'vogo_permission_check',
    ]);

  register_rest_route('vogo/v1', '/product/product-list', [
    'methods' => 'POST',
    'callback' => 'product_list',
    'permission_callback' => 'vogo_permission_check',
  ]);    

    register_rest_route('vogo/v1', '/product-detail', [
        'methods'             => 'GET',
        'callback'            => 'custom_product_detail',
        'permission_callback' => 'vogo_permission_check',
    ]);

    // Product & Order Reviews
    register_rest_route('vogo/v1', '/product-rate-and-review', [
        'methods'             => 'POST',
        'callback'            => 'custom_product_rate_and_review',
        'permission_callback' => 'vogo_permission_check',
    ]);

    register_rest_route('vogo/v1', '/product-review', [
        'methods'             => 'GET',
        'callback'            => 'custom_product_review',
        'permission_callback' => 'vogo_permission_check',
    ]);

    register_rest_route('vogo/v1', '/order-review', [
        'methods'             => 'POST',
        'callback'            => 'custom_order_review',
        'permission_callback' => 'vogo_permission_check',
    ]);

}); // ✅ END of rest_api_init

/**
 * Get paginated list of product brands (taxonomy: product_brand)
 *
 * @return array JSON response with list of brands
 */
function brands_listing() {
    // STEP 1: Extract and sanitize input
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data)) {
        $data = $_REQUEST;
    }

    $page     = isset($data['page']) ? max(1, intval($data['page'])) : 1;
    $per_page = isset($data['per_page']) ? max(1, intval($data['per_page'])) : 10;

    vogo_error_log2("STEP 1: Input received for brands_listing - page: {$page}, per_page: {$per_page}");

    try {
        // STEP 2: Setup query arguments for get_terms
        $offset = ($page - 1) * $per_page;
        $args = [
            'taxonomy' => 'product_brand',
            'number'   => $per_page,
            'offset'   => $offset,
        ];

        vogo_error_log2("STEP 2: Fetching terms with offset {$offset}");

        // STEP 3: Retrieve terms
        $terms = get_terms($args);
        $total_terms = wp_count_terms('product_brand');
        $total_pages = ceil($total_terms / $per_page);

        // STEP 4: Build response
        $response = [
            'status'     => true,
            'code'       => 200,
            'message'    => 'Brands fetched successfully!',
            'page'       => $page,
            'total'      => $total_terms,
            'pages'      => $total_pages,
            'data'       => [],
        ];

        if (!empty($terms) && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $response['data'][] = [
                    'id'   => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'src'  => get_term_meta($term->term_id, 'image', true),
                ];
            }
            vogo_error_log2("STEP 4: Retrieved " . count($terms) . " terms.");
        } else {
            vogo_error_log2("STEP 4: No terms found or get_terms returned error.");
        }

        return $response;

    } catch (Throwable $e) {
        vogo_error_log2("STEP 5: Exception caught - " . $e->getMessage());
        return [
            'status'  => false,
            'code'    => 500,
            'message' => 'Internal server error: ' . $e->getMessage(),
            'data'    => [],
        ];
    }
}



function custom_product_review(WP_REST_Request $request) {
    // STEP 1: Parse and validate input
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data)) {
        $data = $_REQUEST;
    }

    $product_id = isset($data['product_id']) ? intval($data['product_id']) : 0;

    if (!$product_id) {
        vogo_error_log2("STEP 1: Missing or invalid product_id.");
        return new WP_REST_Response([
            'status'  => false,
            'message' => 'Product ID is required.',
            'comment_count' => 0,
            'data'    => []
        ], 400);
    }

    // STEP 2: Retrieve total comment count
    $total_count = wp_count_comments($product_id);
    vogo_error_log2("STEP 2: Total comment count: " . $total_count->total_comments);

    // STEP 3: Calculate pagination offset
    $offset = 0;
    if (!empty($data['page'])) {
        $offset = intval($data['page']) * 10;
    }
    vogo_error_log2("STEP 3: Offset set to $offset");

    // STEP 4: Build query for comments
    $args = [
        'number'      => 10,
        'status'      => 'approve',
        'post_status' => 'publish',
        'post_type'   => 'product',
        'offset'      => $offset,
        'post_id'     => $product_id
    ];

    $comments = get_comments($args);
    $comment_data = [];

    // STEP 5: Process comment results
    if (!empty($comments)) {
        foreach ($comments as $comment_record) {
            $comment_data[] = [
                'comment_id'           => $comment_record->comment_ID,
                'comment_author'       => $comment_record->comment_author,
                'comment_author_email' => $comment_record->comment_author_email,
                'comment_content'      => $comment_record->comment_content,
                'rating'               => get_comment_meta($comment_record->comment_ID, 'rating', true),
                'comment_date'         => date('F jS, Y', strtotime($comment_record->comment_date)),
            ];
        }

        vogo_error_log2("STEP 5: Comments found: " . count($comment_data));
        return new WP_REST_Response([
            'status'        => true,
            'message'       => 'Total ' . $total_count->total_comments . ' reviews found.',
            'comment_count' => $total_count->total_comments,
            'data'          => $comment_data
        ], 200);
    } else {
        vogo_error_log2("STEP 5: No comments found for product_id=$product_id");
        return new WP_REST_Response([
            'status'        => false,
            'message'       => 'No comment found.',
            'comment_count' => 0,
            'data'          => []
        ], 200);
    }
}



function custom_product_rate_and_review(WP_REST_Request $request) {
    try {
        // STEP 1: Parse input
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data)) {
            $data = $_REQUEST;
        }

        $product_id       = isset($data['product_id']) ? intval($data['product_id']) : 0;
        $rating           = isset($data['rating']) ? intval($data['rating']) : 0;
        $comment          = isset($data['comment']) ? sanitize_text_field($data['comment']) : '';
        $user_id          = isset($data['access_token']) ? intval($data['access_token']) : 0;
        $comment_date     = isset($data['comment_date']) ? sanitize_text_field($data['comment_date']) : '';
        $comment_date_gmt = isset($data['comment_date_gmt']) ? sanitize_text_field($data['comment_date_gmt']) : '';

        // STEP 2: Validate required fields
        if (!$product_id || !$rating || !$comment || !$user_id) {
            vogo_error_log2("STEP 2: Missing required fields");
            return new WP_REST_Response([
                'status'  => false,
                'message' => 'Please fill all required fields.'
            ], 400);
        }

        // STEP 3: Load user by ID
        $user = get_user_by('id', $user_id);
        if (!$user || !is_a($user, 'WP_User')) {
            vogo_error_log2("STEP 3: Invalid user with ID $user_id");
            return new WP_REST_Response([
                'status'  => false,
                'message' => 'Invalid user.'
            ], 403);
        }

        // STEP 4: Authenticate user
        wp_set_current_user($user->ID, $user->user_login);
        if (!is_user_logged_in()) {
            vogo_error_log2("STEP 4: User not logged in");
            return new WP_REST_Response([
                'status'  => false,
                'message' => 'User authentication failed.'
            ], 401);
        }

        // STEP 5: Prepare comment data
        $current_user = wp_get_current_user();
        $comment_data = [
            'comment_post_ID'      => $product_id,
            'comment_author'       => $current_user->display_name,
            'comment_author_email' => $current_user->user_email,
            'comment_content'      => $comment,
            'comment_type'         => '',
            'user_id'              => $user_id,
            'comment_date'         => $comment_date,
            'comment_date_gmt'     => $comment_date_gmt,
            'comment_approved'     => 1,
        ];

        // STEP 6: Insert comment
        $comment_id = wp_insert_comment($comment_data);
        if (!$comment_id) {
            vogo_error_log2("STEP 6: Failed to insert comment for product_id=$product_id");
            return new WP_REST_Response([
                'status'  => false,
                'message' => 'Failed to insert comment.'
            ], 500);
        }

        // STEP 7: Add metadata (rating, verified)
        add_comment_meta($comment_id, 'rating', $rating);
        add_comment_meta($comment_id, 'verified', '1');
        vogo_error_log2("STEP 7: Metadata added to comment ID $comment_id");

        // STEP 8: Update product rating info
        $product     = wc_get_product($product_id);
        $data_store  = $product->get_data_store();
        $data_store->update_visibility($product, true);

        update_post_meta($product_id, '_wc_rating_count', $product->get_rating_counts('edit') ?: 1);
        update_post_meta($product_id, '_wc_average_rating', $product->get_average_rating('edit') ?: $rating);
        update_post_meta($product_id, '_wc_review_count', $product->get_review_count('edit') ?: 1);

        vogo_error_log2("STEP 8: Product rating updated for product_id=$product_id");

        // STEP 9: Return success response
        return new WP_REST_Response([
            'status'  => true,
            'message' => 'Product review and rating added successfully.'
        ], 200);

    } catch (Exception $e) {
        vogo_error_log2("EXCEPTION: " . $e->getMessage());
        return new WP_REST_Response([
            'status'  => false,
            'message' => 'An error occurred: ' . $e->getMessage()
        ], 500);
    }
}







function custom_product_detail() {
    // STEP 1: Parse input data
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data)) {
        $data = $_REQUEST;
    }

    $product_id = isset($data['product_id']) ? intval($data['product_id']) : null;
    $user_id    = isset($data['access_token']) ? intval($data['access_token']) : null;

    // STEP 2: Validate required parameter
    if (empty($product_id) || $product_id <= 0) {
        vogo_error_log2("STEP 2: Product ID is missing or invalid.");
        return [
            'status'  => false,
            'message' => 'Product id is required.'
        ];
    }

    global $wpdb;

    // STEP 3: Initialize product object
    $product = wc_get_product($product_id);
    if (!$product) {
        vogo_error_log2("STEP 3: Product with ID $product_id not found.");
        return [
            'status'  => false,
            'message' => 'Product not found.'
        ];
    }

    // STEP 4: Build basic product data
    $product_data = [
        'id'                   => $product_id,
        'name'                 => $product->get_name(),
        'url'                  => get_permalink($product_id),
        'product_type'         => $product->get_type(),
        'short_description'    => '',
        'categories'           => wp_get_post_terms($product_id, 'product_cat'),
        'price'                => $product->get_regular_price(),
        'product_sale_price'   => $product->get_sale_price(),
        'wishlist'             => false,
        'product_tax_status'   => $product->get_tax_status(),
        'product_quantity'     => $product->get_stock_quantity() ?: '',
        'product_stock_status' => $product->get_stock_status(),
        'review_count'         => $product->get_review_count(),
        'average_rating'       => number_format($product->get_average_rating(), 1),
        'image'                => get_the_post_thumbnail_url($product_id, 'full'),
        'product_weight'       => $product->get_weight(),
        'product_dimensions'   => $product->get_dimensions(),
        'description'          => $product->get_description()
    ];

    // STEP 5: Clean short description
    $desc = trim(strip_tags($product->get_short_description()));
    if (!empty($desc)) {
        $product_data['short_description'] = strlen($desc) > 100 ? substr($desc, 0, 100) : $desc;
    }

    // STEP 6: Check wishlist status for logged-in user
    if ($user_id && $product_id) {
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}custom_wishlist WHERE user_id = %d AND product_id = %d",
            $user_id, $product_id
        ));
        if ($exists > 0) {
            $product_data['wishlist'] = true;
        }
    }

    // STEP 7: Product gallery images
    $gallery_ids = $product->get_gallery_attachment_ids();
    $gallery_images = [];
    foreach ($gallery_ids as $id) {
        $gallery_images[] = [
            'product_orignal_image'   => wp_get_attachment_url($id),
            'product_full_image'      => wp_get_attachment_image_src($id, 'full')[0],
            'product_medium_image'    => wp_get_attachment_image_src($id, 'medium')[0],
            'product_thumbnail_image' => wp_get_attachment_image_src($id, 'thumbnail')[0],
        ];
    }
    $product_data['product_gallery_images'] = $gallery_images;

    // STEP 8: Count reviews
    $comment_count = wp_count_comments($product_id);

    // STEP 9: Extract Yoast SEO metadata
    $product_url = get_permalink($product_id);
    $response = wp_remote_get($product_url);

    if (!is_wp_error($response)) {
        $html = wp_remote_retrieve_body($response);
        if (preg_match('/<script type="application\/ld\+json" class="yoast-schema-graph">(.*?)<\/script>/s', $html, $matches)) {
            $product_data['yoast_head_json'] = json_decode($matches[1], true);
        }
    } else {
        vogo_error_log2("STEP 9: Failed to fetch frontend data for product ID $product_id.");
    }

    // STEP 10: Retrieve product action (e.g. WhatsApp)
    $product_action = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT ACTION_TYPE, ACTION_LABEL, ACTION_DETAILS, ICON_CODE, WHATSAPP_NUMBER
             FROM {$wpdb->prefix}vogo_product_action 
             WHERE ID_PRODUCT = %d AND ACTION_TYPE = %s",
            $product_id, 'WHATSAPP'
        ),
        ARRAY_A
    );

    if (!empty($product_action)) {
        $product_data['product_action'] = [
            'action_type'     => $product_action['ACTION_TYPE'],
            'action_label'    => $product_action['ACTION_LABEL'],
            'action_details'  => str_replace('{product name}', $product->get_name(), $product_action['ACTION_DETAILS']),
            'icon_code'       => $product_action['ICON_CODE'],
            'whatsapp_number' => $product_action['WHATSAPP_NUMBER'],
        ];
    }

    // STEP 11: Return success response
    vogo_error_log2("STEP 11: Product detail for ID $product_id retrieved successfully.");
    return [
        'status'  => true,
        'message' => 'Data Successfully provided.',
        'data'    => [
            'product_data'  => $product_data,
            'total_review'  => $comment_count->total_comments
        ]
    ];
}




function custom_products($request) {
    // STEP 1: Parse input data
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty($data)) {
        $data = $_REQUEST;
    }

    global $wpdb;

    // STEP 2: Initialize query parts
    $tax_query  = [];
    $meta_query = [];
    $orderby    = isset($data['orderby']) ? $data['orderby'] : 'ID';
    $order      = isset($data['order']) ? $data['order'] : 'DESC';
    $per_page   = isset($data['per_page']) ? intval($data['per_page']) : 10;
    $paged      = isset($data['page']) ? intval($data['page']) : 1;

    // STEP 3: Filter by category
    if (!empty($data['category'])) {
        $cat_ids = is_array($data['category']) ? $data['category'] : explode(',', $data['category']);
        $tax_query[] = [
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => $cat_ids,
            'operator' => 'IN',
        ];
    }

    // STEP 4: Filter by brand
    if (!empty($data['product_brand'])) {
        $brand_ids = is_array($data['product_brand']) ? $data['product_brand'] : explode(',', $data['product_brand']);
        $tax_query[] = [
            'taxonomy'         => 'product_brand',
            'field'            => 'term_id',
            'terms'            => $brand_ids,
            'operator'         => 'IN',
            'include_children' => false,
        ];
    }

    // STEP 5: Handle search by product title
    if (!empty($data['search'])) {
        add_filter('posts_where', function ($where, $query) use ($data) {
            global $wpdb;
            $search_term = esc_sql($data['search']);
            $where .= " AND {$wpdb->posts}.post_title LIKE '%{$search_term}%'";
            return $where;
        }, 10, 2);
    }

    // STEP 6: Build query args
    $args = [
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        'tax_query'      => $tax_query,
        'meta_query'     => $meta_query,
        'orderby'        => $orderby,
        'order'          => $order,
    ];

    // STEP 7: Special ordering cases
    if ($orderby === 'price') {
        $args['meta_key'] = '_price';
        $args['orderby']  = 'meta_value_num';
    } elseif ($orderby === 'rating') {
        $args['meta_key'] = '_wc_average_rating';
        $args['orderby']  = 'meta_value_num';
    } elseif ($orderby === 'popularity') {
        $args['meta_key'] = 'total_sales';
        $args['orderby']  = 'meta_value_num';
    }

    // STEP 8: Execute query
    $loop = new WP_Query($args);
    $products = [];
    $currency_symbol = get_woocommerce_currency_symbol();
    $currency_code   = get_woocommerce_currency();

    // STEP 9: Loop through results
    if ($loop->have_posts()) {
        while ($loop->have_posts()) : $loop->the_post();
            $product = wc_get_product(get_the_ID());

            $products[] = [
                'id'              => $product->get_id(),
                'name'            => $product->get_name(),
                'slug'            => $product->get_slug(),
                'price'           => $product->get_price(),
                'regular_price'   => $product->get_regular_price(),
                'sale_price'      => $product->get_sale_price(),
                'currency_symbol' => html_entity_decode($currency_symbol),
                'currency_code'   => $currency_code,
                'image'           => wp_get_attachment_url($product->get_image_id()),
                'categories'      => array_map(function ($id) {
                    $term = get_term($id, 'product_cat');
                    return ['id' => $term->term_id, 'name' => $term->name];
                }, $product->get_category_ids())
            ];
        endwhile;
        wp_reset_postdata();

        // STEP 10: Append optional extra fields
        $request_new_product = (!empty($data['category']) && defined('REQUEST_NEW_PRODUCT')) ? REQUEST_NEW_PRODUCT : '';
        $new_service         = (!empty($data['category']) && defined('NEW_SERVICES')) ? NEW_SERVICES : '';

        vogo_error_log2("STEP 10: Fetched " . count($products) . " products.");

        return [
            'status'             => true,
            'code'               => 200,
            'message'            => 'Products fetched successfully',
            'currentPage'        => $paged,
            'total_count'        => $loop->found_posts,
            'currency_code'      => $currency_code,
            'currency_symbol'    => html_entity_decode($currency_symbol),
            'data'               => $products,
            'request_new_product'=> $request_new_product,
            'new_service'        => $new_service,
        ];
    }

    // STEP 11: No products found
    vogo_error_log2("STEP 11: No products found for current query.");
    return [
        'status'             => false,
        'code'               => 400,
        'message'            => 'No records found',
        'currentPage'        => $paged,
        'total_count'        => 0,
        'currency_code'      => $currency_code,
        'currency_symbol'    => html_entity_decode($currency_symbol),
        'data'               => [],
        'request_new_product'=> '',
        'new_service'        => '',
    ];
}

function custom_order_review(WP_REST_Request $request) {
    try {
        // STEP 1: Read and decode JSON input
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data)) {
            $data = $_REQUEST;
        }

        // STEP 2: Extract and sanitize input fields
        $order_id = isset($data['order_id']) ? intval($data['order_id']) : 0;
        $rating   = isset($data['rating']) ? intval($data['rating']) : 0;
        $comment  = isset($data['comment']) ? sanitize_text_field($data['comment']) : '';
        $user_id  = isset($data['access_token']) ? intval($data['access_token']) : 0;

        // STEP 3: Validate required fields
        if (!$order_id || !$rating || !$comment || !$user_id) {
            vogo_error_log2("STEP 3: Missing required fields. order_id={$order_id}, rating={$rating}, user_id={$user_id}");
            return new WP_REST_Response([
                'status'  => false,
                'message' => 'Missing required fields.'
            ], 400);
        }

        // STEP 4: Prepare database table and check for duplicates
        global $wpdb;
        $table = $wpdb->prefix . 'order_reviews';

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE order_id = %d AND user_id = %d",
            $order_id, $user_id
        ));

        if ($existing) {
            vogo_error_log2("STEP 4: Duplicate review found for order_id={$order_id}, user_id={$user_id}");
            return new WP_REST_Response([
                'status'  => false,
                'message' => 'You have already submitted a review for this order.'
            ], 409);
        }

        // STEP 5: Insert the new review into the database
        $inserted = $wpdb->insert($table, [
            'order_id' => $order_id,
            'user_id'  => $user_id,
            'rating'   => $rating,
            'comment'  => $comment
        ]);

        if ($inserted) {
            vogo_error_log2("STEP 5: Review inserted successfully for order_id={$order_id}, user_id={$user_id}");
            return new WP_REST_Response([
                'status'  => true,
                'message' => 'Review submitted successfully.'
            ], 200);
        } else {
            vogo_error_log2("STEP 5: Failed to insert review. order_id={$order_id}, user_id={$user_id}");
            return new WP_REST_Response([
                'status'  => false,
                'message' => 'Failed to submit review. Please try again later.'
            ], 500);
        }

    } catch (Exception $e) {
        // STEP 6: Log unexpected exception
        vogo_error_log2("STEP 6: Exception - " . $e->getMessage());
        return new WP_REST_Response([
            'status'  => false,
            'message' => 'An unexpected error occurred: ' . $e->getMessage()
        ], 500);
    }
}


function product_list(WP_REST_Request $request) {
  global $wpdb;

  // ---- logs bootstrap
  $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  vogo_error_log3("VOGO_LOG_START | IP: $ip | USER: ?");
  $active_db = $wpdb->get_var('SELECT DATABASE()'); vogo_error_log3("ACTIVE DB: {$active_db} | IP: $ip | USER: ?");
  $raw_input = file_get_contents('php://input'); vogo_error_log3("[STEP 0.1] Raw JSON payload received: $raw_input | IP: $ip | USER: ?");

  // ---- JWT user
  $user_or_err = extract_user_from_jwt_token($request);
  if ($user_or_err instanceof WP_REST_Response) { vogo_error_log3("[STEP 0.2] JWT extraction failed | IP: $ip | USER: ?"); return $user_or_err; }
  $user_id = (int)$user_or_err; $user = get_userdata($user_id); $user_login = $user ? $user->user_login : 'unknown';
  vogo_error_log3("[STEP 1] User resolved: user_id=$user_id, user_login=$user_login | IP: $ip | USER: $user_id");

  // ---- payload
  $data = json_decode($raw_input, true); if (empty($data)) $data = $request->get_params();
  $orderby   = isset($data['orderby']) ? sanitize_text_field($data['orderby']) : 'ID';               // ID|price|rating|popularity|date|title
  $order     = strtoupper(isset($data['order']) ? sanitize_text_field($data['order']) : 'DESC');     // ASC|DESC
  $order     = in_array($order, ['ASC','DESC'], true) ? $order : 'DESC';
  $per_page  = isset($data['per_page']) ? max(1, min(100, (int)$data['per_page'])) : 10;
  $page      = isset($data['page']) ? max(1, (int)$data['page']) : 1;
  $offset    = ($page - 1) * $per_page;
  $search    = isset($data['search']) ? trim(wp_unslash($data['search'])) : '';

  // multi filters
  $cat_ids=[]; if (!empty($data['category'])) { $tmp=is_array($data['category'])?$data['category']:explode(',', $data['category']); foreach($tmp as $id){ $id=(int)$id; if($id>0)$cat_ids[]=$id; } }
  $brand_ids=[]; if (!empty($data['product_brand'])) { $tmp=is_array($data['product_brand'])?$data['product_brand']:explode(',', $data['product_brand']); foreach($tmp as $id){ $id=(int)$id; if($id>0)$brand_ids[]=$id; } }

  vogo_error_log3("[STEP 2] Filters parsed | orderby=$orderby | order=$order | per_page=$per_page | page=$page | search='".esc_sql($search)."' | cat_ids=[".implode(',',$cat_ids)."] | brand_ids=[".implode(',',$brand_ids)."] | IP: $ip | USER: $user_id");

  // ---- tables
  $p  = $wpdb->posts;
  $tr = $wpdb->term_relationships;
  $tt = $wpdb->term_taxonomy;
  $lookup = $wpdb->prefix . 'wc_product_meta_lookup'; // WooCommerce indexed meta table

  // ---- base WHERE + params
  $where   = ["p.post_type='product'", "p.post_status='publish'"];
  $params  = [];

  if ($search !== '') { $where[] = "p.post_title LIKE %s"; $params[] = '%' . $wpdb->esc_like($search) . '%'; }

  if (!empty($cat_ids)) {
    $ids = implode(',', array_map('intval', $cat_ids));
    $where[] = "EXISTS (SELECT 1 FROM {$tr} trc JOIN {$tt} ttc ON ttc.term_taxonomy_id=trc.term_taxonomy_id WHERE trc.object_id=p.ID AND ttc.taxonomy='product_cat' AND ttc.term_id IN ($ids))";
  }
  if (!empty($brand_ids)) {
    $ids = implode(',', array_map('intval', $brand_ids));
    $where[] = "EXISTS (SELECT 1 FROM {$tr} trb JOIN {$tt} ttb ON ttb.term_taxonomy_id=trb.term_taxonomy_id WHERE trb.object_id=p.ID AND ttb.taxonomy='product_brand' AND ttb.term_id IN ($ids))";
  }

  $where_sql = implode(' AND ', $where);

  // ---- ORDER BY (no aliases, direct columns; no aggregates)
  // lookup columns: min_price, average_rating, total_sales; posts: p.post_date, p.post_title, p.ID
  $order_col = "p.ID";
  if ($orderby === 'price')       $order_col = "l.min_price";
  elseif ($orderby === 'rating')  $order_col = "l.average_rating";
  elseif ($orderby === 'popularity') $order_col = "l.total_sales";
  elseif ($orderby === 'date')    $order_col = "p.post_date";
  elseif ($orderby === 'title')   $order_col = "p.post_title";

  // ---- COUNT
  $count_sql_base = "SELECT COUNT(1) FROM {$p} p JOIN {$lookup} l ON l.product_id = p.ID WHERE $where_sql";
  $sql_count = !empty($params) ? $wpdb->prepare($count_sql_base, $params) : $count_sql_base;
  vogo_error_log3("##############SQL: " . preg_replace('/\s+/', ' ', $sql_count) . " | IP: $ip | USER: $user_id");
  $total_count = (int)$wpdb->get_var($sql_count);
  if ($wpdb->last_error) {
    vogo_error_log3("[STEP 3.E] SQL count error: {$wpdb->last_error} | IP: $ip | USER: $user_id");
    return new WP_REST_Response(['success'=>false,'error'=>'DB error on count','sql_error'=>$wpdb->last_error,'user_id'=>$user_id,'user_login'=>$user_login], 500);
  }

  // ---- MAIN
  $select = "
    p.ID,
    p.post_title,
    p.post_name AS slug,
    l.min_price     AS price,
    l.max_price     AS max_price,
    l.on_sale       AS on_sale,
    l.stock_quantity,
    l.stock_status,
    l.average_rating AS rating,
    l.total_sales    AS popularity
  ";

  $main_sql_base = "
    SELECT $select
    FROM {$p} p
    JOIN {$lookup} l ON l.product_id = p.ID
    WHERE $where_sql
    ORDER BY $order_col $order
    LIMIT %d OFFSET %d
  ";
  $bind_main = array_merge($params, [$per_page, $offset]);
  $sql_main  = $wpdb->prepare($main_sql_base, $bind_main);
  vogo_error_log3("##############SQL: " . preg_replace('/\s+/', ' ', $sql_main) . " | IP: $ip | USER: $user_id");

  $rows = $wpdb->get_results($sql_main, ARRAY_A);
  if ($wpdb->last_error) {
    vogo_error_log3("[STEP 4.E] SQL main error: {$wpdb->last_error} | IP: $ip | USER: $user_id");
    return new WP_REST_Response(['success'=>false,'error'=>'DB error on fetch','sql_error'=>$wpdb->last_error,'user_id'=>$user_id,'user_login'=>$user_login], 500);
  }

  // ---- currency
  $currency_symbol = html_entity_decode(get_woocommerce_currency_symbol());
  $currency_code   = get_woocommerce_currency();

  // ---- build response items (image + categories via helpers)
  $data = [];
  foreach ($rows as $r) {
    $pid = (int)$r['ID'];

    // image url
    $thumb_id = (int)get_post_thumbnail_id($pid);
    $img = $thumb_id ? wp_get_attachment_url($thumb_id) : '';

    // categories
    $cats=[]; $terms = get_the_terms($pid, 'product_cat');
    if ($terms && !is_wp_error($terms)) { foreach ($terms as $term) { $cats[] = ['id'=>$term->term_id,'name'=>$term->name]; } }

    $data[] = [
      'id'            => $pid,
      'name'          => $r['post_title'],
      'slug'          => $r['slug'],
      'price'         => isset($r['price']) ? (string)$r['price'] : null,
      'max_price'     => isset($r['max_price']) ? (string)$r['max_price'] : null,
      'on_sale'       => (int)$r['on_sale'],
      'stock_quantity'=> isset($r['stock_quantity']) ? (int)$r['stock_quantity'] : null,
      'stock_status'  => $r['stock_status'],
      'rating'        => isset($r['rating']) ? (float)$r['rating'] : null,
      'popularity'    => isset($r['popularity']) ? (int)$r['popularity'] : 0,
      'currency_symbol'=> $currency_symbol,
      'currency_code'  => $currency_code,
      'image'         => $img,
      'post_status'   => 'active',
      'categories'    => $cats
    ];
  }

  // ---- response
  return new WP_REST_Response([
    'success'        => true,
    'code'           => 200,
    'message'        => $total_count>0 ? 'Products fetched successfully' : 'No records found',
    'currentPage'    => $page,
    'per_page'       => $per_page,
    'total_count'    => $total_count,
    'currency_code'  => $currency_code,
    'currency_symbol'=> $currency_symbol,
    'data'           => $data,
    'user_id'        => $user_id,
    'user_login'     => $user_login
  ], 200);
}
//end file