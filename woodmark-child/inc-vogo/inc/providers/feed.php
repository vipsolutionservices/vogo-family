<?php
function process_uploaded_provider_file($provider_id) {
    global $wpdb;
    require_once ABSPATH . 'vendor/autoload.php';

    $provider = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}provider_feeds WHERE id = %d",
        $provider_id
    ));

    if (!$provider || empty($provider->uploaded_file_path) || !file_exists($provider->uploaded_file_path)) {
        error_log("❌ Uploaded file not found for provider ID: $provider_id");
        return false;
    }

    $mapping = json_decode($provider->mapping ?? '', true);
    if (empty($mapping['product_name']) || empty($mapping['category']) || empty($mapping['price'])) {
        error_log("❌ Missing mapping info for provider $provider_id.");
        return false;
    }

    $file_path = $provider->uploaded_file_path;
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $upload_dir = wp_upload_dir();
    $output_path = $upload_dir['basedir'] . "/provider_feeds/uploaded_provider_{$provider_id}_processed.csv";
    $default_wc_category_id = $provider->default_woocommerce_category ?? null;

    $coefficients = $wpdb->get_results($wpdb->prepare(
        "SELECT category_name, coefficient, woocommerce_category_ids FROM {$wpdb->prefix}provider_coefficients WHERE provider_id = %d",
        $provider_id
    ), OBJECT_K);

    $modified_rows = [];

    // 🟩 XLS/XLSX Processing
    if (in_array($ext, ['xls', 'xlsx'])) {
        $reader = ($ext === 'xls') 
            ? new PhpOffice\PhpSpreadsheet\Reader\Xls()
            : new PhpOffice\PhpSpreadsheet\Reader\Xlsx();

        $spreadsheet = $reader->load($file_path);
        $sheet = $spreadsheet->getActiveSheet();

        // Extract headers
        $headers = [];
        foreach ($sheet->getRowIterator(1, 1) as $row) {
            foreach ($row->getCellIterator() as $i => $cell) {
                $headers[$i] = trim($cell->getFormattedValue());
            }
        }

        $header_indexes = [
            'product_name' => array_search($mapping['product_name'], $headers, true),
            'category'     => array_search($mapping['category'], $headers, true),
            'price'        => array_search($mapping['price'], $headers, true)
        ];

        if (in_array(false, $header_indexes, true)) {
            error_log("❌ Mapping headers not found in Excel.");
            return false;
        }

        $modified_rows[] = array_merge($headers, ["Original Price", "Modified Price", "WooCommerce Categories"]);

        foreach ($sheet->getRowIterator(2) as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $cells = iterator_to_array($cellIterator);
            $row_data = array_map(fn($cell) => $cell->getFormattedValue(), $cells);

            $category = trim($row_data[$header_indexes['category']] ?? '');
            $original_price = floatval($row_data[$header_indexes['price']] ?? 0);
            $modified_price = $original_price;
            $woo_category = '';

            // Slab logic
            if ($original_price > 0 && $category !== '') {
                $slab = $wpdb->get_row($wpdb->prepare(
                    "SELECT coefficient FROM {$wpdb->prefix}provider_category_slabs
                     WHERE provider_id = %d AND category_name = %s
                     AND %f BETWEEN min_price AND IFNULL(max_price, 9999999)
                     ORDER BY min_price ASC LIMIT 1",
                    $provider_id, $category, $original_price
                ));

                if ($slab) {
                    $modified_price = round($original_price * $slab->coefficient, 2);
                } elseif (isset($coefficients[$category])) {
                    $modified_price = round($original_price * $coefficients[$category]->coefficient, 2);
                }
            }

            // Woo category logic
            if (isset($coefficients[$category])) {
                $ids = json_decode($coefficients[$category]->woocommerce_category_ids, true);
                if (!empty($ids)) {
                    $names = [];
                    foreach ($ids as $id) {
                        $term = get_term((int)$id, 'product_cat');
                        if (!is_wp_error($term) && $term && isset($term->name)) {
                            $names[] = $term->name;
                        }
                    }
                    $woo_category = implode(", ", $names);
                }
            }

            if (empty($woo_category) && $default_wc_category_id) {
                $term = get_term((int)$default_wc_category_id, 'product_cat');
                if (!is_wp_error($term) && isset($term->name)) {
                    $woo_category = $term->name;
                }
            }

            $row_data[] = $original_price;
            $row_data[] = $modified_price;
            $row_data[] = $woo_category;
            $modified_rows[] = $row_data;
        }
    }

    // 🟦 CSV/TSV Processing
    elseif (in_array($ext, ['csv', 'tsv'])) {
        $delimiter = ($ext === 'tsv') ? "\t" : ",";
        $handle = fopen($file_path, 'r');
        $headers = fgetcsv($handle, 0, $delimiter);

        $header_indexes = [
            'product_name' => array_search($mapping['product_name'], $headers, true),
            'category'     => array_search($mapping['category'], $headers, true),
            'price'        => array_search($mapping['price'], $headers, true)
        ];

        $modified_rows[] = array_merge($headers, ["Original Price", "Modified Price", "WooCommerce Categories"]);

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $category = trim($row[$header_indexes['category']] ?? '');
            $original_price = floatval($row[$header_indexes['price']] ?? 0);
            $modified_price = $original_price;
            $woo_category = '';

            if ($original_price > 0 && $category !== '') {
                $slab = $wpdb->get_row($wpdb->prepare(
                    "SELECT coefficient FROM {$wpdb->prefix}provider_category_slabs
                     WHERE provider_id = %d AND category_name = %s
                     AND %f BETWEEN min_price AND IFNULL(max_price, 9999999)
                     ORDER BY min_price ASC LIMIT 1",
                    $provider_id, $category, $original_price
                ));

                if ($slab) {
                    $modified_price = round($original_price * $slab->coefficient, 2);
                } elseif (isset($coefficients[$category])) {
                    $modified_price = round($original_price * $coefficients[$category]->coefficient, 2);
                }
            }

            if (isset($coefficients[$category])) {
                $ids = json_decode($coefficients[$category]->woocommerce_category_ids, true);
                if (!empty($ids)) {
                    $names = [];
                    foreach ($ids as $id) {
                        $term = get_term((int)$id, 'product_cat');
                        if (!is_wp_error($term) && isset($term->name)) {
                            $names[] = $term->name;
                        }
                    }
                    $woo_category = implode(", ", $names);
                }
            }

            if (empty($woo_category) && $default_wc_category_id) {
                $term = get_term((int)$default_wc_category_id, 'product_cat');
                if (!is_wp_error($term) && isset($term->name)) {
                    $woo_category = $term->name;
                }
            }

            $row[] = $original_price;
            $row[] = $modified_price;
            $row[] = $woo_category;
            $modified_rows[] = $row;
        }

        fclose($handle);
    }

    // ✅ Write the final CSV
    $out = fopen($output_path, 'w');
    foreach ($modified_rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);

    $url = $upload_dir['baseurl'] . '/provider_feeds/' . basename($output_path);
    error_log("✅ Uploaded feed processed: $url");
    return $url;
}
function run_import_from_csv($csv_path, $import_id) {
    if (!file_exists($csv_path)) {
        error_log("❌ CSV file not found at path: $csv_path");
        return false;
    }

    if (!class_exists('PMXI_Import_Record')) {
        error_log("❌ WP All Import Pro plugin is not active.");
        return false;
    }

    $import = new PMXI_Import_Record();
    $import->getById($import_id);

    if ($import->isEmpty()) {
        error_log("❌ WP All Import Import ID $import_id not found.");
        return false;
    }

    $import->set(['path' => $csv_path])->save();
    do_action('pmxi_execute_import', $import_id);
    error_log("✅ WP All Import triggered for Import ID: $import_id with file: $csv_path");

    return true;
}

add_action('wp_ajax_get_provider_mapping', function() {
    global $wpdb;
    $provider_id = intval($_GET['provider_id']);
    
    // Fetch the mapping data from the database
    $mapping = $wpdb->get_var($wpdb->prepare(
        "SELECT mapping FROM {$wpdb->prefix}provider_feeds WHERE id = %d", 
        $provider_id
    ));

    if ($mapping) {
        // Convert the JSON mapping data into an array
        $mapping_data = json_decode($mapping, true);
        wp_send_json_success(['data' => $mapping_data]);
    } else {
        wp_send_json_error(['message' => 'Mapping not found.']);
    }
});
// add_action('init', function () {
//     register_taxonomy('product_provider', 'product', [
//         'labels' => [
//             'name'              => 'Product Providers',
//             'singular_name'     => 'Product Provider',
//             'search_items'      => 'Search Providers',
//             'all_items'         => 'All Providers',
//             'edit_item'         => 'Edit Provider',
//             'update_item'       => 'Update Provider',
//             'add_new'           => 'Add New Provider',
//             'add_new_item'      => 'Add New Provider',
//             'new_item_name'     => 'New Provider Name',
//             'menu_name'         => 'Product Providers',
//         ],
//         'public'            => true,
//         'show_ui'           => true,
//         'show_in_menu'      => true,
//         'show_admin_column' => true,
//         'show_in_nav_menus' => true,
//         'show_tagcloud'     => false,
//         'hierarchical'      => true,
//         'rewrite'           => ['slug' => 'product-provider'],
//         'show_in_rest'      => true,
//         'meta_box_cb'       => 'post_categories_meta_box',
//         'capabilities' => [
//             'manage_terms' => 'manage_categories', // standard WP role capability
//             'edit_terms'   => 'manage_categories',
//             'delete_terms' => 'manage_categories',
//             'assign_terms' => 'edit_posts',
//         ],
//     ]);
// }, 20);
add_action('init', function () {
    // Step 1: Unregister existing taxonomy if already registered
    unregister_taxonomy('product_provider');

    // Step 2: Re-register with new args
    register_taxonomy('product_provider', 'product', [
        'labels' => [
            'name'              => 'Product Providers',
            'singular_name'     => 'Product Provider',
            'search_items'      => 'Search Providers',
            'all_items'         => 'All Providers',
            'edit_item'         => 'Edit Provider',
            'update_item'       => 'Update Provider',
            'add_new'           => 'Add New Provider',
            'add_new_item'      => 'Add New Provider',
            'new_item_name'     => 'New Provider Name',
            'menu_name'         => 'Product Providers',
        ],
        'public'            => true,
        'show_ui'           => true,
        'show_in_menu'      => true,
        'show_admin_column' => true,
        'show_in_nav_menus' => true,
        'show_tagcloud'     => false,
        'hierarchical'      => true,
        'rewrite'           => ['slug' => 'product-provider'],
        'show_in_rest'      => true,
        'meta_box_cb'       => 'post_categories_meta_box',
        'capabilities' => [
            'manage_terms' => 'manage_categories',
            'edit_terms'   => 'manage_categories',
            'delete_terms' => 'manage_categories',
            'assign_terms' => 'edit_posts',
        ],
    ]);
}, 11); // Run AFTER default taxonomies (default is priority 10)
// add_action('created_product_provider', 'save_provider_email_meta');
// add_action('edited_product_provider',  'save_provider_email_meta');

// function save_provider_email_meta($term_id) {
//     if (isset($_POST['provider_email'])) {
//         update_term_meta($term_id, 'provider_email', sanitize_email($_POST['provider_email']));
//     }
// }
/* --------------------------------------------------------------------------
 *  PROVIDER TAXONOMY – ADD EMAIL FIELD
 * -------------------------------------------------------------------------- */

/**
 * Add the extra field on the “Add new Provider” screen
 */
add_action( 'product_provider_add_form_fields', function () {
    ?>
    <div class="form-field term-email-wrap">
        <label for="provider_email"><?php _e( 'Provider Email', 'textdomain' ); ?></label>
        <input name="provider_email" id="provider_email" type="email" value="" />
        <p class="description"><?php _e( 'Address that will receive order‑item notifications.', 'textdomain' ); ?></p>
    </div>
    <?php
} );

/**
 * Add the extra field on the “Edit Provider” screen
 */
add_action( 'product_provider_edit_form_fields', function ( $term ) {
    $email = get_term_meta( $term->term_id, 'provider_email', true );
    ?>
    <tr class="form-field term-email-wrap">
        <th scope="row"><label for="provider_email"><?php _e( 'Provider Email', 'textdomain' ); ?></label></th>
        <td>
            <input name="provider_email" id="provider_email" type="email" value="<?php echo esc_attr( $email ); ?>" />
            <p class="description"><?php _e( 'Address that will receive order‑item notifications.', 'textdomain' ); ?></p>
        </td>
    </tr>
    <?php
}, 10, 1 );

/**
 * Save the email when a term is created or edited
 */
add_action( 'created_product_provider', 'save_provider_email_meta' );
add_action( 'edited_product_provider',  'save_provider_email_meta' );
function save_provider_email_meta( $term_id ) {
    ob_start();
    try {
        if ( isset( $_POST['provider_email'] ) ) {
            update_term_meta( $term_id, 'provider_email', sanitize_email( $_POST['provider_email'] ) );
        }
    } catch (Throwable $e) {
        error_log('Error saving provider_email: ' . $e->getMessage());
    }
    ob_end_clean();
}
// Show Product Provider in WooCommerce Admin Order View
add_action('woocommerce_after_order_itemmeta', function ($item_id, $item, $product) {
    // Only run in admin area, and only for product line items
    if (!is_admin() || !$item->is_type('line_item')) return;

    $product_id = $item->get_product_id();
    if (!$product_id) return;

    // Get product_provider terms
    $terms = get_the_terms($product_id, 'product_provider');

    if (!empty($terms) && !is_wp_error($terms)) {
        $names = wp_list_pluck($terms, 'name');
        echo '<p><strong>' . __('Product Provider', 'your-textdomain') . ':</strong> ' . esc_html(implode(', ', $names)) . '</p>';
    }
}, 10, 3);

add_action('woocommerce_admin_order_item_headers', function () {
    echo '<th class="product-provider-column">' . esc_html__('Provider', 'your-textdomain') . '</th>';
}, 100);


add_action('woocommerce_admin_order_item_values', function ($product, $item, $item_id) {
    // Only run for product line items
    if (!$item->is_type('line_item')) {
        echo '<td class="product-provider-column">—</td>';
        return;
    }

    $product_id = $item->get_product_id();
    $terms = get_the_terms($product_id, 'product_provider');

    echo '<td class="product-provider-column">';
    if (!empty($terms) && !is_wp_error($terms)) {
        $names = wp_list_pluck($terms, 'name');
        echo esc_html(implode(', ', $names));
    } else {
        echo '&mdash;'; // Dash if no provider
    }
    echo '</td>';
}, 100, 3);

// Save product provider info to order item meta on checkout
add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values, $order) {
    $product_id = $item->get_product_id();
    $terms = get_the_terms($product_id, 'product_provider');

    if (!empty($terms) && !is_wp_error($terms)) {
        $provider_names = wp_list_pluck($terms, 'name');
        $item->add_meta_data('_product_provider', implode(', ', $provider_names), true);
    }
}, 10, 4);


add_action('woocommerce_checkout_order_created', function ($order) {
    if (! $order instanceof WC_Order) {
        return;
    }

    $provider_ids = [];

    foreach ($order->get_items('line_item') as $item) {
        $product = $item->get_product();
        if (! $product instanceof WC_Product) {
            continue;
        }

        $terms = get_the_terms($product->get_id(), 'product_provider');
        if (! empty($terms) && ! is_wp_error($terms)) {
            foreach ($terms as $term) {
                $provider_ids[] = (int) $term->term_id;
            }
        }
    }

    if (! empty($provider_ids)) {
        $provider_ids = array_unique($provider_ids);
        $meta_value = implode(',', $provider_ids);

        try {
            $order->add_meta_data('_product_provider_ids', $meta_value, true);
        } catch (Throwable $e) {
            error_log('Error saving product_provider_ids meta: ' . $e->getMessage());
        }
    }
});


add_action('woocommerce_order_list_query', function ($query) {
    if (!is_admin()) return;

    $taxonomy = 'product_provider';

    if (!empty($_GET[$taxonomy]) && is_numeric($_GET[$taxonomy])) {
        $provider_id = (int) $_GET[$taxonomy];

        $meta_query = $query->get('meta_query') ?: [];

        $meta_query[] = [
            'key'     => '_product_provider_ids',
            'value'   => '"' . $provider_id . '"',
            'compare' => 'LIKE',
        ];

        $query->set('meta_query', $meta_query);
    }
});


add_action('woocommerce_order_list_table_filters', function () {
    $taxonomy = 'product_provider';
    $terms = get_terms([
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
    ]);

    if (empty($terms) || is_wp_error($terms)) {
        return;
    }

    $selected = isset($_GET[$taxonomy]) ? (int) $_GET[$taxonomy] : '';

    echo '<select name="' . esc_attr($taxonomy) . '">';
    echo '<option value="">' . esc_html__('All Providers', 'your-textdomain') . '</option>';

    foreach ($terms as $term) {
        echo '<option value="' . esc_attr($term->term_id) . '" ' . selected($selected, $term->term_id, false) . '>';
        echo esc_html($term->name) . '</option>';
    }

    echo '</select>';
});

add_filter( 'wpseo_enable_taxonomy_columns', '__return_false' );

add_action('admin_footer-edit-tags.php', function () {
    $screen = get_current_screen();
    if ($screen->taxonomy !== 'product_provider') return;
    ?>
    <script>
    (function() {
       // console.log('✅ JS injected on correct screen');

        // Intercept XMLHttpRequest sends
        const origOpen = XMLHttpRequest.prototype.open;
        const origSend = XMLHttpRequest.prototype.send;

        XMLHttpRequest.prototype.open = function(method, url) {
            this._url = url;
            return origOpen.apply(this, arguments);
        };

        XMLHttpRequest.prototype.send = function(body) {
            this.addEventListener('load', function() {
                if (this._url.includes('admin-ajax.php') && body && body.includes('action=add-tag') && body.includes('taxonomy=product_provider')) {
                    console.log('✅ AJAX fired for product_provider');

                    const raw = this.responseText;
                    console.log('📦 RAW response:', raw);

                    const match = raw.match(/<response_data><!\[CDATA\[(.*?)\]\]><\/response_data>/);
                    if (match && match[1]) {
                        const responseData = match[1].trim();
                        console.log('📦 Extracted response_data:', responseData);

                        if (responseData === 'Item added.') {
                            console.log('🔁 Reloading page...');
                            location.reload();
                        }
                    } else {
                        console.warn('⚠️ response_data not found');
                    }
                }
            });
            return origSend.apply(this, arguments);
        };
    })();
    </script>
    <?php
});