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

add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values, $order) {
  global $wpdb;
  $pid = $item->get_product_id();
  $vid = (int)$wpdb->get_var($wpdb->prepare(
    "SELECT vendor_id FROM {$wpdb->prefix}vogo_product_vendor WHERE product_id=%d LIMIT 1", $pid
  ));
  if ($vid) {
    $name = $wpdb->get_var($wpdb->prepare(
      "SELECT provider_name FROM {$wpdb->prefix}vogo_providers WHERE id=%d", $vid
    ));
    $item->add_meta_data('_vendor_id', $vid, true);
    $item->add_meta_data('_vendor_name', $name ?: ('#'.$vid), true);
  }
}, 10, 4);

add_action('woocommerce_after_order_itemmeta', function ($item_id, $item) {
  if (!is_admin() || !$item->is_type('line_item')) return;
  $vid = (int) wc_get_order_item_meta($item_id, '_vendor_id', true);
  if ($vid) {
    global $wpdb;
    $name = $wpdb->get_var($wpdb->prepare(
      "SELECT provider_name FROM {$wpdb->prefix}vogo_providers WHERE id=%d", $vid
    ));
    echo '<p><strong>Provider:</strong> ' . esc_html($name ?: ('#'.$vid)) . '</p>';
  }
}, 10, 2);

add_action('woocommerce_order_list_table_filters', function () {
  global $wpdb;
  $selected = isset($_GET['filter_vogo_provider']) ? intval($_GET['filter_vogo_provider']) : 0;
  $providers = $wpdb->get_results(
    "SELECT id, provider_name FROM {$wpdb->prefix}vogo_providers WHERE status='active' ORDER BY provider_name ASC"
  );

  echo '<select name="filter_vogo_provider">';
  echo '<option value="">' . __('All Providers','woocommerce') . '</option>';
  foreach ($providers as $p) {
    printf('<option value="%d"%s>%s</option>',
      $p->id,
      selected($selected, $p->id, false),
      esc_html($p->provider_name)
    );
  }
  echo '</select>';
});

// Apply filter to query
add_action('pre_get_posts', function ($query) {
  if (!is_admin() || !$query->is_main_query()) return;
  global $pagenow;
  if ($pagenow !== 'edit.php' || $query->get('post_type') !== 'shop_order') return;

  $vendor_id = isset($_GET['filter_vogo_provider']) ? intval($_GET['filter_vogo_provider']) : 0;
  if ($vendor_id <= 0) return;

  global $wpdb;
  $order_ids = $wpdb->get_col($wpdb->prepare("
    SELECT DISTINCT oi.order_id
    FROM {$wpdb->prefix}woocommerce_order_items oi
    INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
      ON oim.order_item_id=oi.order_item_id
     AND oim.meta_key='_vendor_id'
     AND oim.meta_value=%d
    WHERE oi.order_item_type='line_item'
  ", $vendor_id));

  $query->set('post__in', $order_ids ?: [0]);
});

