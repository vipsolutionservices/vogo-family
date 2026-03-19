<?php
  use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
  require_once get_stylesheet_directory() . '/inc/providers/feed_processing.php';
function download_feed($url, $provider_id) {
    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['basedir'] . "/provider_feeds/provider_{$provider_id}.csv";

    $response = wp_remote_get($url);
    if (is_wp_error($response)) return false;

    file_put_contents($file_path, wp_remote_retrieve_body($response));
    return $file_path;
}

function modify_feed_prices($csv_file, $provider_id) {
    global $wpdb;
    
    $file_data = array_map('str_getcsv', file($csv_file));
    $header = array_shift($file_data);

    // Get price and category column index
    $price_index = array_search('price', $header);
    $category_index = array_search('category', $header);

    if ($price_index === false || $category_index === false) return false;

    foreach ($file_data as &$row) {
        $category = $row[$category_index];
        $price = $row[$price_index];

        // Get coefficient
        $coefficient = $wpdb->get_var($wpdb->prepare(
            "SELECT coefficient FROM {$wpdb->prefix}provider_coefficients WHERE provider_id = %d AND category_name = %s",
            $provider_id, $category
        ));

        if ($coefficient) {
            $row[$price_index] = $price * $coefficient;
        }
    }

    array_unshift($file_data, $header);
    return $file_data;
}

function save_updated_feed($data, $provider_id) {
    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['basedir'] . "/provider_feeds/processed_{$provider_id}.csv";

    $fp = fopen($file_path, 'w');
    foreach ($data as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);
}



// function get_provider_feed_headers() {
//     global $wpdb;

//     // Get provider ID from AJAX request
//     $provider_id = isset($_GET['provider_id']) ? intval($_GET['provider_id']) : 0;
//     if (!$provider_id) {
//         wp_send_json_error(['message' => 'Invalid provider ID.']);
//     }

//     // Fetch provider feed URL from the database
//     $feed_url = $wpdb->get_var($wpdb->prepare(
//         "SELECT feed_url FROM {$wpdb->prefix}provider_feeds WHERE id = %d", 
//         $provider_id
//     ));

//     if (!$feed_url) {
//         wp_send_json_error(['message' => 'Feed URL not found.']);
//     }

//     // ✅ Step 1: Download the file temporarily
//     $temp_file = download_remote_file($feed_url);
//     if (!$temp_file) {
//         wp_send_json_error(['message' => 'Failed to download feed file.']);
//     }

//     // ✅ Step 2: Detect file extension
//     $file_extension = pathinfo($temp_file, PATHINFO_EXTENSION);
//     $file_extension = strtolower($file_extension);

//     error_log("Detected file extension: " . $file_extension);

//     // ✅ Step 3: Handle TSV files by checking content
//     if ($file_extension == 'csv' || $file_extension == 'tsv') {
//         // Attempt to detect the delimiter by reading the first line
//         $file_handle = fopen($temp_file, 'r');
//         $first_line = fgets($file_handle);
//         fclose($file_handle);

//         // If tabs are detected, treat it as TSV
//         if (strpos($first_line, "\t") !== false) {
//             error_log('Detected file as TSV (tab-separated)');
//             $delimiter = "\t";
//         } else {
//             error_log('Detected file as CSV (comma-separated)');
//             $delimiter = ",";
//         }

//         // Open the file again with the detected delimiter
//         $file_handle = fopen($temp_file, 'r');
//         $headers = fgetcsv($file_handle, 0, $delimiter);
//         fclose($file_handle);
//         unlink($temp_file); // Delete file after processing

//         if (!$headers) {
//             wp_send_json_error(['message' => 'Could not detect CSV/TSV headers.']);
//         }

//         wp_send_json_success(['headers' => $headers]);
//     }

//     // ✅ Step 4: Process XLS/XLSX files using PHPSpreadsheet
//     require_once ABSPATH . 'vendor/autoload.php';

//     try {
//         $reader = ($file_extension === 'xls') 
//             ? new \PhpOffice\PhpSpreadsheet\Reader\Xls() 
//             : new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();

//         $spreadsheet = $reader->load($temp_file);
//         unlink($temp_file); // Delete temp file after reading

//         $sheet = $spreadsheet->getActiveSheet();
//         $headers = [];

//         foreach ($sheet->getRowIterator(1, 1) as $row) {
//             $cellIterator = $row->getCellIterator();
//             $cellIterator->setIterateOnlyExistingCells(false);

//             foreach ($cellIterator as $cell) {
//                 $headers[] = $cell->getValue();
//             }
//         }

//         wp_send_json_success(['headers' => $headers]);
//     } catch (Exception $e) {
//         unlink($temp_file); // Delete the temp file if an error occurs
//         wp_send_json_error(['message' => 'Error reading XLS feed: ' . $e->getMessage()]);
//     }
// }

function get_provider_feed_headers() {
    global $wpdb;

    // Get provider ID from AJAX request
    $provider_id = isset($_GET['provider_id']) ? intval($_GET['provider_id']) : 0;
    if (!$provider_id) {
        wp_send_json_error(['message' => 'Invalid provider ID.']);
    }

    // Check if headers are already cached (cache for 12 hours, adjust as needed)
    $cache_key = 'provider_feed_headers_' . $provider_id;
    $cached_headers = get_transient($cache_key);
    if ($cached_headers && is_array($cached_headers)) {
        wp_send_json_success(['headers' => $cached_headers]);
    }

    // Fetch provider feed URL from the database
    $feed_url = $wpdb->get_var($wpdb->prepare(
        "SELECT feed_url FROM {$wpdb->prefix}provider_feeds WHERE id = %d", 
        $provider_id
    ));

    if (!$feed_url) {
        wp_send_json_error(['message' => 'Feed URL not found.']);
    }

    // ✅ Step 1: Download the file temporarily
    $temp_file = download_remote_file($feed_url);
    if (!$temp_file) {
        wp_send_json_error(['message' => 'Failed to download feed file.']);
    }

    // ✅ Step 2: Detect file extension
    $file_extension = pathinfo($temp_file, PATHINFO_EXTENSION);
    $file_extension = strtolower($file_extension);

    error_log("Detected file extension: " . $file_extension);

    // ✅ Step 3: Handle TSV files by checking content
    if ($file_extension == 'csv' || $file_extension == 'tsv') {
        // Attempt to detect the delimiter by reading the first line
        $file_handle = fopen($temp_file, 'r');
        $first_line = fgets($file_handle);
        fclose($file_handle);

        // If tabs are detected, treat it as TSV
        if (strpos($first_line, "\t") !== false) {
            error_log('Detected file as TSV (tab-separated)');
            $delimiter = "\t";
        } else {
            error_log('Detected file as CSV (comma-separated)');
            $delimiter = ",";
        }

        // Open the file again with the detected delimiter
        $file_handle = fopen($temp_file, 'r');
        $headers = fgetcsv($file_handle, 0, $delimiter);
        fclose($file_handle);
        unlink($temp_file); // Delete file after processing

        if (!$headers) {
            wp_send_json_error(['message' => 'Could not detect CSV/TSV headers.']);
        }
        // Cache the headers before sending response
        set_transient($cache_key, $headers, 12 * HOUR_IN_SECONDS);
        wp_send_json_success(['headers' => $headers]);
    }

    // ✅ Step 4: Process XLS/XLSX files using PHPSpreadsheet
    require_once ABSPATH . 'vendor/autoload.php';

    try {
        $reader = ($file_extension === 'xls') 
            ? new \PhpOffice\PhpSpreadsheet\Reader\Xls() 
            : new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();

        $spreadsheet = $reader->load($temp_file);
        unlink($temp_file); // Delete temp file after reading

        $sheet = $spreadsheet->getActiveSheet();
        $headers = [];

        foreach ($sheet->getRowIterator(1, 1) as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            foreach ($cellIterator as $cell) {
                $headers[] = $cell->getValue();
            }
        }
        if (!$headers) {
            wp_send_json_error(['message' => 'Could not detect XLS/XLSX headers.']);
        }
        // Cache the headers
        set_transient($cache_key, $headers, 12 * HOUR_IN_SECONDS);
        wp_send_json_success(['headers' => $headers]);
    } catch (Exception $e) {
        unlink($temp_file); // Delete the temp file if an error occurs
        wp_send_json_error(['message' => 'Error reading XLS feed: ' . $e->getMessage()]);
    }
}
// Function to convert TSV to CSV by replacing tabs with commas
function convert_tsv_to_csv($tsv_file) {
    // Generate a temporary CSV file name
    $csv_file = tempnam(sys_get_temp_dir(), 'converted_') . '.csv';

    // Open the TSV file for reading and the new CSV file for writing
    if (($tsv_handle = fopen($tsv_file, 'r')) !== false) {
        if (($csv_handle = fopen($csv_file, 'w')) !== false) {
            // Process each line of the TSV file
            while (($line = fgets($tsv_handle)) !== false) {
                // Replace tab characters with commas
                $csv_line = str_replace("\t", ",", $line);
                // Write the converted line to the CSV file
                fputs($csv_handle, $csv_line);
            }

            fclose($csv_handle);
        }
        fclose($tsv_handle);
    }

    return $csv_file; // Return the path to the new CSV file
}

add_action('wp_ajax_get_provider_feed_headers', 'get_provider_feed_headers');

function download_remote_file($url) {
    $upload_dir = wp_upload_dir();
    
    $temp_path = $upload_dir['basedir'] . '/temp_feed.csv'; // Save as CSV

    // Fetch file contents
    $file_contents = file_get_contents($url);
    error_log('file_url: ' . $url);
    if (!$file_contents || strlen($file_contents) < 100) { // If too small, it's an error
        error_log("Invalid file content from URL: " . $url);
        return false;
    }

   // error_log("First 500 chars of response: " . substr($file_contents, 0, 500));

   // Detect format based on content
   if (strpos($file_contents, "\ ") !== false) {
    $temp_path .= '.csv'; // Convert TSV to CSV
    $file_contents = str_replace("\t", ",", $file_contents);
    error_log("Detected file format: TSV (converted to CSV)");
} elseif (strpos($file_contents, ',') !== false) {
    $temp_path .= '.csv'; // Keep CSV as it is
    error_log("Detected file format: CSV");
} else {
    $temp_path .= '.xls'; // Assume Excel if no clear delimiters
    error_log("Detected file format: XLS/XLSX (assumed)");
}

// Save the file
if (!file_put_contents($temp_path, $file_contents)) {
    error_log("Failed to save file at: " . $temp_path);
    return false;
}

    return file_exists($temp_path) ? $temp_path : false;
}

add_action('wp_ajax_get_provider_default_category', function() {
    global $wpdb;
    $provider_id = intval($_GET['provider_id']);
    $provider = $wpdb->get_row($wpdb->prepare(
        "SELECT default_woocommerce_category FROM {$wpdb->prefix}provider_feeds WHERE id = %d",
        $provider_id
    ));

    if ($provider) {
        wp_send_json_success(['default_category' => $provider->default_woocommerce_category]);
    } else {
        wp_send_json_error(['message' => 'Provider not found']);
    }
});


function test_phpspreadsheet() {
    require_once ABSPATH . 'vendor/autoload.php';

    if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
        wp_send_json_success(['message' => 'PHPSpreadsheet is loaded successfully.']);
    } else {
        wp_send_json_error(['message' => 'PHPSpreadsheet is NOT loaded!']);
    }
}
add_action('wp_ajax_test_phpspreadsheet', 'test_phpspreadsheet');

function save_field_mapping() {
    global $wpdb;

    $provider_id = isset($_POST['provider_id']) ? intval($_POST['provider_id']) : 0;
    if (!$provider_id) {
        wp_send_json_error(['message' => 'Invalid provider ID.']);
    }

    // Log incoming data
    error_log("🔍 Incoming Mapping Data: " . print_r($_POST, true));

    $fields = ['product_name', 'category', 'price'];
    $mapping = [];

    foreach ($fields as $field) {
        if (!empty($_POST["mapping"][$field])) {
            $mapping[$field] = sanitize_text_field($_POST["mapping"][$field]);
        }
    }

    if (empty($mapping)) {
        error_log("❌ No fields mapped.");
        wp_send_json_error(['message' => 'No fields mapped.']);
    }

    // Save mapping in database
    $mapping_json = json_encode($mapping);
    
    $wpdb->update(
        "{$wpdb->prefix}provider_feeds",
        ['mapping' => $mapping_json],
        ['id' => $provider_id],
        ['%s'],
        ['%d']
    );

    error_log("✅ Mapping saved: " . $mapping_json);

    wp_send_json_success(['message' => 'Mapping saved successfully!']);
}
add_action('wp_ajax_save_field_mapping', 'save_field_mapping');

// Assuming detect_feed_type() is implemented somewhere in the codebase
// The function will return 'csv', 'tsv', 'xls', or 'xlsx' based on the file content

function get_unique_categories($provider_id) {
    global $wpdb;

    // Get provider feed info
    $provider = $wpdb->get_row($wpdb->prepare(
        "SELECT feed_url, mapping FROM {$wpdb->prefix}provider_feeds WHERE id = %d", 
        $provider_id
    ));

    if (!$provider || empty($provider->mapping)) {
        error_log("❌ Provider or mapping not found.");
        return false;
    }

    // Decode mapping
    $mapping = json_decode($provider->mapping, true);
    if (empty($mapping['category'])) {
        error_log("❌ No category mapping found.");
        return false;
    }

    $temp_file = download_remote_file($provider->feed_url);
    if (!$temp_file) {
        error_log("❌ Failed to download feed file.");
        return false;
    }

    // Detect feed type instead of relying on file extension
    $feed_type = detect_feed_type($temp_file);
    error_log("Detected feed type: " . $feed_type);
    if (!$feed_type) {
        error_log("❌ Could not detect feed type.");
        return false;
    }

    $categories = [];

    // Handle CSV/TSV files
    if (in_array($feed_type, ['csv', 'tsv'])) {
        $delimiter = ($feed_type === 'tsv') ? "\t" : ",";
        $file_handle = fopen($temp_file, 'r');
        $headers = fgetcsv($file_handle, 0, $delimiter);
        $header_indexes = array_flip($headers);
        
        while (($row = fgetcsv($file_handle, 0, $delimiter)) !== false) {
            $category = $row[$header_indexes[$mapping['category']]] ?? '';
            if (!empty($category) && !in_array($category, $categories)) {
                $categories[] = $category;
            }
        }
        fclose($file_handle);
    }

    // Handle XLS/XLSX files
    elseif (in_array($feed_type, ['xls', 'xlsx'])) {
        require_once ABSPATH . 'vendor/autoload.php';

        try {
            $reader = ($feed_type === 'xls') 
                ? new \PhpOffice\PhpSpreadsheet\Reader\Xls() 
                : new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();

            $spreadsheet = $reader->load($temp_file);
            $sheet = $spreadsheet->getActiveSheet();
            $header_indexes = [];

            foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                if ($rowIndex === 1) { // First row is headers
                    foreach ($cellIterator as $cellIndex => $cell) {
                        $header_indexes[$cell->getValue()] = $cellIndex;
                    }
                    continue;
                }

                $rowData = [];
                foreach ($cellIterator as $cellIndex => $cell) {
                    $rowData[$cellIndex] = $cell->getValue();
                }

                $category = $rowData[$header_indexes[$mapping['category']]] ?? '';
                if (!empty($category) && !in_array($category, $categories)) {
                    $categories[] = $category;
                }
            }
        } catch (Exception $e) {
            error_log("❌ Error reading Excel: " . $e->getMessage());
            return false;
        }
    }

    unlink($temp_file);
    return $categories;
}

function get_provider_categories() {
    global $wpdb;

    // Ensure provider ID is received
    $provider_id = isset($_GET['provider_id']) ? intval($_GET['provider_id']) : 0;
    $force_refresh = isset($_GET['refresh']) ? true : false; // ✅ Initialize the variable properly

    if (!$provider_id) {
        wp_send_json_error(['message' => 'Invalid provider ID.']);
    }

    if (!$force_refresh) {
        // ✅ Check if cached categories exist
        $cached_categories = $wpdb->get_var($wpdb->prepare(
            "SELECT categories FROM {$wpdb->prefix}provider_feeds WHERE id = %d", 
            $provider_id
        ));

        if ($cached_categories) {
            wp_send_json_success(['categories' => json_decode($cached_categories, true)]);
        }
    }

    // ✅ Extract categories from feed if refresh is requested
    $categories = get_unique_categories($provider_id);
    error_log("Extracted categories: " . print_r($categories, true));
    if (!$categories || count($categories) === 0) {
        wp_send_json_error(['message' => 'No categories found.']);
    }

    // ✅ Cache categories in database
    $wpdb->update(
        "{$wpdb->prefix}provider_feeds",
        ['categories' => json_encode($categories)],
        ['id' => $provider_id],
        ['%s'],
        ['%d']
    );

    // ✅ Force a clean JSON response without PHP warnings
    ob_clean();
    wp_send_json_success(['categories' => $categories]);
}
add_action('wp_ajax_get_provider_categories', 'get_provider_categories');

// function get_provider_coefficients() {
//     global $wpdb;

//     $provider_id = isset($_GET['provider_id']) ? intval($_GET['provider_id']) : 0;
//     if (!$provider_id) {
//         wp_send_json_error(['message' => 'Invalid provider ID.']);
//     }

//     $coefficients = $wpdb->get_results($wpdb->prepare(
//         "SELECT category_name, coefficient FROM {$wpdb->prefix}provider_coefficients WHERE provider_id = %d",
//         $provider_id
//     ), ARRAY_A);

//     if (!$coefficients) {
//         wp_send_json_success(['coefficients' => []]); // No coefficients yet
//     }

//     $coeff_array = [];
//     foreach ($coefficients as $row) {
//         $coeff_array[$row['category_name']] = $row['coefficient'];
//     }

//     wp_send_json_success(['coefficients' => $coeff_array]);
// }
// add_action('wp_ajax_get_provider_coefficients', 'get_provider_coefficients');

function get_provider_coefficients() {
    global $wpdb;

    $provider_id = isset($_GET['provider_id']) ? intval($_GET['provider_id']) : 0;
    if (!$provider_id) {
        wp_send_json_error(['message' => 'Invalid provider ID.']);
    }

    $coefficients = $wpdb->get_results($wpdb->prepare(
        "SELECT category_name, coefficient, woocommerce_category_ids FROM {$wpdb->prefix}provider_coefficients WHERE provider_id = %d",
        $provider_id
    ), ARRAY_A);

    if (!$coefficients) {
        wp_send_json_success(['data' => ['coefficients' => [], 'mappings' => []]]); // ✅ Ensure correct structure
    }

    $coeff_array = [];
    $mapping_array = [];
    $data = [
        'coefficients' => [],
        'mappings' => []
    ];

    foreach ($coefficients as $coefficient) {
        $category = trim($coefficient['category_name']);
        $data['coefficients'][$category] = $coefficient['coefficient'];
        
        // Decode WooCommerce categories
        $data['mappings'][$category] = !empty($coefficient['woocommerce_category_ids'])
            ? json_decode($coefficient['woocommerce_category_ids'], true)
            : [];
    }
    // foreach ($coefficients as $row) {
    //     $trimmedCategory = trim($row['category_name']); // ✅ Trim spaces
    //     $coeff_array[$trimmedCategory] = $row['coefficient'];

    //     // ✅ Decode JSON and ensure it's an array
    //     $mapped_categories = json_decode($row['woocommerce_category_ids'], true);
    //     if (!is_array($mapped_categories)) {
    //         $mapped_categories = [];
    //     }
    //     $mapping_array[$trimmedCategory] = $mapped_categories;
    // }
    wp_send_json_success(['data' => $data]);
   // wp_send_json_success(['data' => ['coefficients' => $coeff_array, 'mappings' => $mapping_array]]);
}
add_action('wp_ajax_get_provider_coefficients', 'get_provider_coefficients');



function save_provider_coefficients() {
    global $wpdb;

    // Debugging: Log request data
    error_log("📌 Incoming Data: " . print_r($_POST, true));

    $provider_id = isset($_POST['provider_id']) ? intval($_POST['provider_id']) : 0;
    if (!$provider_id) {
        wp_send_json_error(['message' => 'Invalid provider ID.']);
    }

    if (!isset($_POST['coefficients']) || !is_array($_POST['coefficients'])) {
        wp_send_json_error(['message' => 'No coefficient data received.']);
    }

    foreach ($_POST['coefficients'] as $category => $coefficient) {
        $category = trim(sanitize_text_field($category));
        $coefficient = floatval($coefficient);

        // ✅ Get selected WooCommerce categories (handling JSON properly)
        $woocommerce_category_ids = isset($_POST['woocommerce_mapping'][$category]) ? $_POST['woocommerce_mapping'][$category] : [];

        if (!is_array($woocommerce_category_ids)) {
            $woocommerce_category_ids = []; // Ensure it remains an array
        }

        // Debugging: Log each category and its WooCommerce mapping
        error_log("📌 Saving category: $category | Coefficient: $coefficient | WooCommerce Categories: " . print_r($woocommerce_category_ids, true));

        // ✅ Check if the category already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}provider_coefficients WHERE provider_id = %d AND category_name = %s",
            $provider_id, $category
        ));

        if ($exists) {
            // ✅ Update existing row
            $wpdb->update(
                "{$wpdb->prefix}provider_coefficients",
                [
                    'coefficient' => $coefficient,
                    'woocommerce_category_ids' => json_encode($woocommerce_category_ids)
                ],
                [
                    'provider_id' => $provider_id,
                    'category_name' => $category
                ],
                ['%f', '%s'],
                ['%d', '%s']
            );
        } else {
            // ✅ Insert new row if it doesn't exist
            $wpdb->insert(
                "{$wpdb->prefix}provider_coefficients",
                [
                    'provider_id' => $provider_id,
                    'category_name' => $category,
                    'coefficient' => $coefficient,
                    'woocommerce_category_ids' => json_encode($woocommerce_category_ids)
                ],
                ['%d', '%s', '%f', '%s']
            );
        }
    }

    wp_send_json_success(['message' => 'Mappings and coefficients saved successfully!']);
}
add_action('wp_ajax_save_provider_coefficients', 'save_provider_coefficients');

function get_woocommerce_categories() {
    $categories = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
    ]);

    if (is_wp_error($categories)) {
        wp_send_json_error(['message' => 'Failed to fetch categories']);
    }

    $category_list = [];
    foreach ($categories as $category) {
        $category_list[] = [
            'id' => $category->term_id,
            'name' => $category->name,
        ];
    }

    wp_send_json_success(['categories' => $category_list]);
}
add_action('wp_ajax_get_woocommerce_categories', 'get_woocommerce_categories');

function save_single_coefficient() {
    global $wpdb;

    $provider_id = isset($_POST['provider_id']) ? intval($_POST['provider_id']) : 0;
    $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
    $coefficient = isset($_POST['coefficient']) ? floatval($_POST['coefficient']) : 0;
    $woocommerce_categories = isset($_POST['woocommerce_mapping'][$category]) ? $_POST['woocommerce_mapping'][$category] : [];

    error_log("🔍 Incoming Data - Provider ID: $provider_id, Category: $category, Coefficient: $coefficient, WooCommerce Categories: " . json_encode($woocommerce_categories));

    if (!$provider_id || !$category || $coefficient <= 0) {
        wp_send_json_error(['message' => 'Invalid input.']);
    }

    // Convert WooCommerce categories to JSON
   // $woocommerce_categories_json = !empty($woocommerce_categories) ? json_encode($woocommerce_categories) : json_encode([]);
    if (!is_array($woocommerce_categories)) {
        $woocommerce_categories = []; // Ensure it remains an array
    }
    // Check if coefficient already exists
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}provider_coefficients WHERE provider_id = %d AND category_name = %s",
        $provider_id, $category
    ));

    if ($exists) {
        // Update existing coefficient and WooCommerce categories
        $wpdb->update(
            "{$wpdb->prefix}provider_coefficients",
            ['coefficient' => $coefficient, 'woocommerce_category_ids' => $woocommerce_categories_json],
            ['provider_id' => $provider_id, 'category_name' => $category],
            ['%f', '%s'],
            ['%d', '%s']
        );
    } else {
        // Insert new coefficient and WooCommerce categories
        $wpdb->insert(
            "{$wpdb->prefix}provider_coefficients",
            [
                'provider_id' => $provider_id,
                'category_name' => $category,
                'coefficient' => $coefficient,
                'woocommerce_category_ids' => $woocommerce_categories_json
            ],
            ['%d', '%s', '%f', '%s']
        );
    }

    error_log("✅ Data Saved Successfully: " . json_encode(['provider_id' => $provider_id, 'category' => $category, 'coefficient' => $coefficient, 'woocommerce_categories' => $woocommerce_categories_json]));

    wp_send_json_success(['message' => 'Coefficient and WooCommerce categories saved successfully!']);
}
add_action('wp_ajax_save_single_coefficient', 'save_single_coefficient');
function save_single_provider_coefficient() {
    global $wpdb;

    // Debugging: Log request data
    error_log("📌 Incoming Data: " . print_r($_POST, true));

    $provider_id = isset($_POST['provider_id']) ? intval($_POST['provider_id']) : 0;
    $category = isset($_POST['category']) ? trim(sanitize_text_field($_POST['category'])) : '';
    $coefficient = isset($_POST['coefficient']) ? floatval($_POST['coefficient']) : 0;
    $woocommerce_category_ids = isset($_POST['woocommerce_categories']) ? json_decode(stripslashes($_POST['woocommerce_categories']), true) : [];

    if (!$provider_id || !$category || $coefficient <= 0) {
        wp_send_json_error(['message' => 'Invalid input.']);
    }

    if (!is_array($woocommerce_category_ids)) {
        $woocommerce_category_ids = []; // Ensure it remains an array
    }

    // Debugging: Log category being updated
    error_log("📌 Saving category: $category | Coefficient: $coefficient | WooCommerce Categories: " . print_r($woocommerce_category_ids, true));

    // ✅ Check if the category already exists
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}provider_coefficients WHERE provider_id = %d AND category_name = %s",
        $provider_id, $category
    ));

    if ($exists) {
        // ✅ Update existing row
        $wpdb->update(
            "{$wpdb->prefix}provider_coefficients",
            [
                'coefficient' => $coefficient,
                'woocommerce_category_ids' => json_encode($woocommerce_category_ids)
            ],
            [
                'provider_id' => $provider_id,
                'category_name' => $category
            ],
            ['%f', '%s'],
            ['%d', '%s']
        );
    } else {
        // ✅ Insert new row if it doesn't exist
        $wpdb->insert(
            "{$wpdb->prefix}provider_coefficients",
            [
                'provider_id' => $provider_id,
                'category_name' => $category,
                'coefficient' => $coefficient,
                'woocommerce_category_ids' => json_encode($woocommerce_category_ids)
            ],
            ['%d', '%s', '%f', '%s']
        );
    }

    wp_send_json_success(['message' => "✅ Mapping and coefficient saved for $category!"]);
}
add_action('wp_ajax_save_single_provider_coefficient', 'save_single_provider_coefficient');
function save_provider_default_category() {
    global $wpdb;

    $provider_id = isset($_POST['provider_id']) ? intval($_POST['provider_id']) : 0;
    $default_category = isset($_POST['default_category']) ? intval($_POST['default_category']) : 0;

    if (!$provider_id || !$default_category) {
        wp_send_json_error(['message' => 'Invalid provider or category.']);
    }

    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}provider_feeds WHERE id = %d",
        $provider_id
    ));

    if (!$exists) {
        wp_send_json_error(['message' => 'Provider not found.']);
    }

    $wpdb->update(
        "{$wpdb->prefix}provider_feeds",
        ['default_woocommerce_category' => $default_category],
        ['id' => $provider_id],
        ['%d'],
        ['%d']
    );

    wp_send_json_success(['message' => 'Default category saved successfully!']);
}
add_action('wp_ajax_save_provider_default_category', 'save_provider_default_category');


function modify_provider_feed_prices($provider_id) {
    global $wpdb;
    error_log("🔍 Modify provider feeds chal rah ahai ");
    error_log("🔍 Running Price Modification for Provider ID: $provider_id");

    // 1) Fetch provider row
    $provider = $wpdb->get_row($wpdb->prepare(
        "SELECT feed_url, mapping, default_woocommerce_category
         FROM {$wpdb->prefix}provider_feeds
         WHERE id = %d",
        $provider_id
    ));
    if (!$provider) {
        error_log("❌ Provider ID: $provider_id not found in database.");
        return false;
    }

    // 2) Parse the mapping (we need product_name, category, price)
    $mapping = json_decode($provider->mapping, true);
    if (empty($mapping['product_name']) || empty($mapping['category']) || empty($mapping['price'])) {
        error_log("❌ Mapping fields are missing for Provider ID: $provider_id");
        return false;
    }

    // 3) Retrieve per-category coefficients + woo mappings
    $coefficients = $wpdb->get_results($wpdb->prepare(
        "SELECT category_name, coefficient, woocommerce_category_ids
         FROM {$wpdb->prefix}provider_coefficients
         WHERE provider_id = %d",
        $provider_id
    ), OBJECT_K);
    if (!$coefficients) {
        error_log("❌ No coefficients found for Provider ID: $provider_id");
        return false;
    }

    // 4) Download feed file
    $temp_file = download_remote_file($provider->feed_url);
    if (!$temp_file) {
        error_log("❌ Failed to download feed file for Provider ID: $provider_id");
        return false;
    }

    require_once ABSPATH . 'vendor/autoload.php';
    $file_extension = strtolower(pathinfo($temp_file, PATHINFO_EXTENSION));

    // 5) Handle CSV/TSV
    if (in_array($file_extension, ['csv', 'tsv'])) {
        $delimiter = ($file_extension === 'tsv') ? "\t" : ",";
        $file_handle = fopen($temp_file, 'r');
        if (!$file_handle) {
            error_log("❌ Failed to open $file_extension file: $temp_file");
            return false;
        }

        // Read the headers
        $headers = fgetcsv($file_handle, 0, $delimiter);
        if (!$headers) {
            error_log("❌ Failed to read CSV/TSV headers.");
            fclose($file_handle);
            return false;
        }

        $header_indexes = array_flip($headers);
        // Optionally rename columns to WP All Import style
        if (isset($mapping['product_name'])) {
            $headers[$header_indexes[$mapping['product_name']]] = 'post_title';
        }
        if (isset($mapping['category'])) {
            $headers[$header_indexes[$mapping['category']]] = 'tax:product_cat';
        }

        // We collect updated rows
        $all_rows = [];
        $max_category_count = 0; // tracks how many Woo categories we might have
        while (($row = fgetcsv($file_handle, 0, $delimiter)) !== false) {
            $category = trim($row[$header_indexes[$mapping['category']]] ?? '');
            $raw_price = $row[$header_indexes[$mapping['price']]] ?? '';
            $normalized_price = str_replace(',', '.', $raw_price);
            $original_price = floatval($normalized_price);
            $modified_price = $original_price;
            $used_coefficient = '';
            $woo_category_names = [];

            if ($original_price > 0 && $category !== '') {
                // 1) Try category-specific slab first
                $slab = $wpdb->get_row($wpdb->prepare(
                    "SELECT coefficient
                     FROM {$wpdb->prefix}provider_category_slabs
                     WHERE provider_id = %d
                       AND category_name = %s
                       AND %f BETWEEN min_price AND IFNULL(max_price,9999999)
                     ORDER BY min_price ASC
                     LIMIT 1",
                    $provider_id, $category, $original_price
                ));
                if ($slab) {
                    $used_coefficient = $slab->coefficient;
                    $modified_price = round($original_price * $slab->coefficient, 2);
                } elseif (isset($coefficients[$category])) {
                    // 2) Use direct coefficient if slab not found
                    $used_coefficient = $coefficients[$category]->coefficient;
                    $modified_price = round($original_price * $used_coefficient, 2);
                } else {
                    // 3) Fallback to default slab
                    $default_slab = $wpdb->get_row($wpdb->prepare(
                        "SELECT coefficient
                         FROM {$wpdb->prefix}provider_category_slabs
                         WHERE provider_id = %d
                           AND category_name = %s
                           AND %f BETWEEN min_price AND IFNULL(max_price,9999999)
                         ORDER BY min_price ASC
                         LIMIT 1",
                        $provider_id, '__DEFAULT__', $original_price
                    ));
                    error_log("Default slab: " . print_r($default_slab, true));
                    if ($default_slab) {
                        $used_coefficient = $default_slab->coefficient;
                        $modified_price = round($original_price * $used_coefficient, 2);
                    } elseif (isset($coefficients['__DEFAULT__'])) {
                        // 4) Fallback to default coefficient
                        $used_coefficient = $coefficients['__DEFAULT__']->coefficient;
                        $modified_price = round($original_price * $used_coefficient, 2);
                    }
                }
            }

            // Gather mapped WooCommerce categories
            if (isset($coefficients[$category])) {
                $woo_cat_ids = json_decode($coefficients[$category]->woocommerce_category_ids, true);
                if (!empty($woo_cat_ids) && is_array($woo_cat_ids)) {
                    foreach ($woo_cat_ids as $woo_category_id) {
                        $term = get_term(intval($woo_category_id), 'product_cat');
                        if (!is_wp_error($term) && isset($term->name)) {
                            $woo_category_names[] = strip_tags($term->name);
                        }
                    }
                }
            }
            // Fallback if no mapped categories
            if (empty($woo_category_names) && !empty($provider->default_woocommerce_category)) {
                $term = get_term(intval($provider->default_woocommerce_category), 'product_cat');
                if (!is_wp_error($term) && isset($term->name)) {
                    $woo_category_names[] = strip_tags($term->name);
                }
            }
            $max_category_count = max($max_category_count, count($woo_category_names));

            $all_rows[] = [
                'row'             => $row,
                'original_price'  => $original_price,
                'modified_price'  => $modified_price,
                'used_coefficient'=> $used_coefficient,
                'categories'      => $woo_category_names
            ];
        }
        fclose($file_handle);

        // Build new headers, appending extra columns
        $modified_headers = $headers;
        $modified_headers[] = "Original Price";
        $modified_headers[] = "regular_price";
        $modified_headers[] = "Coefficient Used";
        for ($i = 1; $i <= $max_category_count; $i++) {
            $modified_headers[] = "Category $i";
        }
        $modified_rows = [];
        $modified_rows[] = $modified_headers;

        // Build final CSV rows
        foreach ($all_rows as $item) {
            $row = $item['row'];
            $row[] = $item['original_price'];
            $row[] = $item['modified_price'];
            $row[] = $item['used_coefficient'];

            // Append each WooCommerce category
            $cats = array_pad($item['categories'], $max_category_count, '');
            foreach ($cats as $catName) {
                $row[] = $catName;
            }
            $modified_rows[] = $row;
        }

        // Write updated CSV to provider_feeds folder
        $upload_dir = wp_upload_dir();
        $modified_dir = $upload_dir['basedir'] . '/provider_feeds';
        $relative_path = "provider_feeds/provider_{$provider_id}.csv";
        $modified_file = $modified_dir . "/provider_{$provider_id}.csv";

        if (!file_exists($modified_dir)) {
            mkdir($modified_dir, 0755, true);
        }
        @chmod($modified_dir, 0755);

        $fh = fopen($modified_file, 'w');
        if (!$fh) {
            error_log("❌ Failed to create modified CSV file at $modified_file.");
            return false;
        }
        foreach ($modified_rows as $record) {
            if (empty(array_filter($record))) continue;
            fputcsv($fh, $record);
        }
        fclose($fh);
        @chmod($modified_file, 0644);

        $csv_url = $upload_dir['baseurl'] . "/provider_feeds/provider_{$provider_id}.csv";
        error_log("✅ Modified CSV Saved: $csv_url");
        return $csv_url;
    }

    // If not CSV/TSV, you could handle XLS/XLSX similarly
    // or return false for unrecognized formats
    return false;
}


function sanitize_feed_data($text) {
    if (empty($text)) return '';

    // ✅ Decode HTML entities like &nbsp;, &amp;, &lt;, etc.
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    // ✅ Remove all HTML tags
    $text = strip_tags($text);

    // ✅ Normalize white spaces and remove newlines
    $text = preg_replace('/\s+/', ' ', $text);

    // ✅ Trim spaces
    return trim($text);
}

// Feed generation for texacom

function process_texacom_feed() {
    global $wpdb;
    require_once ABSPATH . 'vendor/autoload.php'; // PhpSpreadsheet

    $provider_id = 1;

    // $provider = $wpdb->get_row($wpdb->prepare(
    //     "SELECT feed_url, mapping FROM {$wpdb->prefix}provider_feeds WHERE id = %d",
    //     $provider_id
    // ));
    $provider = $wpdb->get_row($wpdb->prepare(
        "SELECT feed_url, mapping, default_woocommerce_category FROM {$wpdb->prefix}provider_feeds WHERE id = %d",
        $provider_id
    ));
    $default_wc_category_id = $provider->default_woocommerce_category ?? null;


    if (!$provider || empty($provider->mapping)) {
        error_log("❌ Feed URL or mapping not found.");
        return false;
    }

    $mapping = json_decode($provider->mapping, true);
    error_log("✅ Using Mapping: " . print_r($mapping, true));

    $upload_dir = wp_upload_dir();
    $xls_file = $upload_dir['basedir'] . "/provider_feeds/texacom_latest.xls";
    $csv_file = $upload_dir['basedir'] . "/provider_feeds/texacom_updated.csv";

    $data = file_get_contents($provider->feed_url);
    if (!$data) {
        error_log("❌ Failed to download feed file.");
        return false;
    }
    file_put_contents($xls_file, $data);

    $spreadsheet = PhpOffice\PhpSpreadsheet\IOFactory::load($xls_file);
    $sheet = $spreadsheet->getActiveSheet();

    // Extract headers
    $headers = [];
    foreach ($sheet->getRowIterator(1, 1) as $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);
        foreach ($cellIterator as $cellIndex => $cell) {
            $headers[$cellIndex] = trim($cell->getFormattedValue());
        }
    }
    error_log("✅ Detected Headers: " . print_r($headers, true));

    // Match indexes from header names
    $header_indexes = [
        'product_name' => array_search($mapping['product_name'], $headers, true),
        'category' => array_search($mapping['category'], $headers, true),
        'price' => array_search($mapping['price'], $headers, true)
    ];
    error_log("✅ Header Indexes: " . print_r($header_indexes, true));

    foreach ($header_indexes as $key => $index) {
        if ($index === false) {
            error_log("❌ Column not found for mapping key: $key");
            return false;
        }
    }

    $coefficients = $wpdb->get_results($wpdb->prepare(
        "SELECT category_name, coefficient, woocommerce_category_ids FROM {$wpdb->prefix}provider_coefficients WHERE provider_id = %d",
        $provider_id
    ), OBJECT_K);

    // Update prices in XLS
    foreach ($sheet->getRowIterator(2) as $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);

        try {
            $categoryCell = $cellIterator->seek($header_indexes['category'])->current();
            $priceCell = $cellIterator->seek($header_indexes['price'])->current();
        } catch (Exception $e) {
            error_log("❌ Error accessing category/price cell: " . $e->getMessage());
            continue;
        }

        $category = trim($categoryCell->getValue());
      //  $original_price = floatval($priceCell->getValue());
        $raw_price = $priceCell->getValue();
        $normalized_price = str_replace(',', '.', $raw_price); // Handle comma decimals
        $original_price = floatval($normalized_price);  
      $modified_price = $original_price;

        // if (isset($coefficients[$category]) && $original_price > 0) {
        //     $modified_price = round($original_price * $coefficients[$category]->coefficient, 2);
        //     $priceCell->setValue($modified_price);
        // }
        if ($original_price > 0 && $category !== '') {
            // 🔍 1. Try slab-based coefficient first
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
                // 🔁 Fallback to flat coefficient
                $modified_price = round($original_price * $coefficients[$category]->coefficient, 2);
            }
        
            $priceCell->setValue($modified_price);
        }
        
      //  error_log("🔍 Category: $category | Original: $original_price | New: $modified_price");
    }

    // Save XLS as CSV (no categories yet)
    $writer = new PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
    $writer->setDelimiter(",");
    $writer->setEnclosure('"');
    $writer->setLineEnding("\r\n");
    $writer->setSheetIndex(0);
    $writer->save($csv_file);

    // Read CSV and add WooCommerce categories
    $csv_data = [];
    if (($handle = fopen($csv_file, "r")) !== false) {
        while (($row = fgetcsv($handle, 0, ",")) !== false) {
            $csv_data[] = $row;
        }
        fclose($handle);
    }

    if (!in_array("WooCommerce Categories", $csv_data[0])) {
        $csv_data[0][] = "WooCommerce Categories";
    }
    $csv_header = $csv_data[0]; // First row = headers

        $csv_indexes = [
            'product_name' => array_search($mapping['product_name'], $csv_header, true),
            'category'     => array_search($mapping['category'], $csv_header, true),
            'price'        => array_search($mapping['price'], $csv_header, true)
        ];
        if (!in_array("WooCommerce Categories", $csv_data[0])) {
            $csv_data[0][] = "WooCommerce Categories";
        }
    foreach ($csv_data as $key => $row) {
        if ($key === 0) continue;
        // $category = isset($row[$header_indexes['category']]) ? trim($row[$header_indexes['category']]) : '';
        
        $category = isset($row[$csv_indexes['category']]) ? trim($row[$csv_indexes['category']]) : '';

        $woo_cat = '';
        
        if (!empty($category) && isset($coefficients[$category]->woocommerce_category_ids)) {
            $ids = json_decode($coefficients[$category]->woocommerce_category_ids, true);
            if (!empty($ids)) {
                $names = [];
                foreach ($ids as $id) {
                    $term = get_term((int)$id, 'product_cat');
                    if (!is_wp_error($term) && $term && isset($term->name)) {
                        $names[] = $term->name;
                    }
                }
                $woo_cat = implode(", ", $names);
            }
        }

    // 🛡️ Use default if no Woo categories assigned
    if (empty($woo_cat) && $default_wc_category_id) {
        $term = get_term((int)$default_wc_category_id, 'product_cat');
        if (!is_wp_error($term) && $term && isset($term->name)) {
            $woo_cat = $term->name;
            error_log("🔁 Row $key | Using default Woo category: $woo_cat");
        }
    }

        error_log("🛒 Row $key | Category: '$category' | Woo: '$woo_cat'");
       // ✅ Add WooCommerce category to the row
         $csv_data[$key][] = $woo_cat;

    // ✅ Align row length to header length
        while (count($csv_data[$key]) < count($csv_data[0])) {
            $csv_data[$key][] = ''; // pad if needed
        }
    }

    // Save final CSV
    $handle = fopen($csv_file, "w");
    foreach ($csv_data as $row) {
        fputcsv($handle, $row, ",");
    }
    fclose($handle);

    error_log("✅ Final CSV with WooCommerce Categories saved: $csv_file");
    return $upload_dir['baseurl'] . "/provider_feeds/texacom_updated.csv";
}

add_action('wp_ajax_save_category_slab', function () {
    global $wpdb;

    $provider_id = intval($_POST['provider_id']);
    $category = sanitize_text_field($_POST['category']);
    $min_price = floatval($_POST['min_price']);
    $max_price = $_POST['max_price'] !== '' ? floatval($_POST['max_price']) : null;
    $coefficient = floatval($_POST['coefficient']);

    if (!$provider_id || $category === '' || $coefficient <= 0 || $min_price < 0) {
        wp_send_json_error(['message' => 'Missing or invalid fields.']);
    }

    if ($max_price !== null && $max_price < $min_price) {
        wp_send_json_error(['message' => '❌ Max price must be greater than Min price.']);
    }

    $table = $wpdb->prefix . 'provider_category_slabs';

    // ✅ New overlap logic
    $overlap_query = "
        SELECT COUNT(*) FROM {$table}
        WHERE provider_id = %d AND category_name = %s
        AND NOT (
            IFNULL(%f, 9999999) < min_price OR
            %f > IFNULL(max_price, 9999999)
        )
    ";

    $conflict = $wpdb->get_var($wpdb->prepare(
        $overlap_query,
        $provider_id,
        $category,
        $max_price ?? null, // new_max
        $min_price          // new_min
    ));

    if ($conflict > 0) {
        wp_send_json_error(['message' => '❌ Overlapping slab exists for this category.']);
    }

    $wpdb->insert($table, [
        'provider_id'   => $provider_id,
        'category_name' => $category,
        'min_price'     => $min_price,
        'max_price'     => $max_price,
        'coefficient'   => $coefficient,
        'created_at'    => current_time('mysql')
    ]);

    if ($wpdb->insert_id) {
        wp_send_json_success(['message' => '✅ Slab saved successfully.', 'slab_id' => $wpdb->insert_id]);
    } else {
        wp_send_json_error(['message' => '❌ Failed to save slab.']);
    }
});


add_action('wp_ajax_get_category_slabs', function () {
    global $wpdb;

    $provider_id = intval($_GET['provider_id']);
    $category = sanitize_text_field($_GET['category']);

    if (!$provider_id || !$category) {
        wp_send_json_error(['message' => 'Missing provider or category.']);
    }

    $table = $wpdb->prefix . 'provider_category_slabs';

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT id, min_price, max_price, coefficient 
         FROM $table 
         WHERE provider_id = %d AND category_name = %s 
         ORDER BY min_price ASC",
        $provider_id,
        $category
    ));

    wp_send_json_success(['slabs' => $results]);
});

add_action('wp_ajax_delete_category_slab', function () {
    global $wpdb;

    $slab_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$slab_id) {
        wp_send_json_error(['message' => 'Invalid slab ID.']);
    }

    $table = $wpdb->prefix . 'provider_category_slabs';
    $deleted = $wpdb->delete($table, ['id' => $slab_id]);

    if ($deleted) {
        wp_send_json_success(['message' => 'Slab deleted.']);
    } else {
        wp_send_json_error(['message' => 'Failed to delete slab.']);
    }
});

add_action('wp_ajax_update_category_slab', function () {
    global $wpdb;

    $slab_id     = intval($_POST['id']);
    $provider_id = intval($_POST['provider_id']);
    $category    = sanitize_text_field($_POST['category']);
    $min_price   = floatval($_POST['min_price']);
    $max_price   = $_POST['max_price'] !== '' ? floatval($_POST['max_price']) : null;
    $coefficient = floatval($_POST['coefficient']);

    if (!$slab_id || !$provider_id || $category === '' || $coefficient <= 0 || $min_price < 0) {
        wp_send_json_error(['message' => 'Missing or invalid fields.']);
    }

    if ($max_price !== null && $max_price < $min_price) {
        wp_send_json_error(['message' => '❌ Max price must be greater than Min price.']);
    }

    $table = $wpdb->prefix . 'provider_category_slabs';

    // ✅ Check for overlaps with other slabs (exclude this slab)
    $conflict = $wpdb->get_var($wpdb->prepare(
        "
        SELECT COUNT(*) FROM $table
        WHERE provider_id = %d AND category_name = %s AND id != %d
        AND NOT (
            IFNULL(%f, 9999999) < min_price OR
            %f > IFNULL(max_price, 9999999)
        )
        ",
        $provider_id,
        $category,
        $slab_id,
        $max_price ?? null, // New max
        $min_price          // New min
    ));

    if ($conflict > 0) {
        wp_send_json_error(['message' => '❌ Overlapping slab exists for this category.']);
    }

    $result = $wpdb->update(
        $table,
        [
            'min_price'   => $min_price,
            'max_price'   => $max_price,
            'coefficient' => $coefficient,
        ],
        ['id' => $slab_id]
    );

    if ($result !== false) {
        wp_send_json_success(['message' => '✅ Slab updated successfully.']);
    } else {
        wp_send_json_error(['message' => '❌ Failed to update slab.']);
    }
});


function process_and_modify_texacom_feed($provider_id) {
    global $wpdb;
    require_once ABSPATH . 'vendor/autoload.php';

    // Fetch provider data (URL, mapping, default Woo category)
    $provider = $wpdb->get_row($wpdb->prepare(
        "SELECT feed_url, mapping, default_woocommerce_category 
         FROM {$wpdb->prefix}provider_feeds 
         WHERE id = %d",
        $provider_id
    ));

    if (!$provider || empty($provider->mapping)) {
        error_log("❌ Provider or mapping missing.");
        return false;
    }

    // Decode mapping
    $mapping = json_decode($provider->mapping, true);
    $default_wc_cat_id = $provider->default_woocommerce_category ?? null;

    // Prepare file paths
    $upload_dir = wp_upload_dir();
    $xls_file = $upload_dir['basedir'] . "/provider_feeds/texacom_latest.xls";
    $csv_file = $upload_dir['basedir'] . "/provider_feeds/texacom_updated.csv";

    // Download the XLS file from feed_url
    file_put_contents($xls_file, file_get_contents($provider->feed_url));

    // Load spreadsheet
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($xls_file);
    $sheet = $spreadsheet->getActiveSheet();

    // Extract headers (first row)
    $headers = [];
    foreach ($sheet->getRowIterator(1, 1) as $row) {
        foreach ($row->getCellIterator() as $i => $cell) {
            $headers[$i] = trim($cell->getFormattedValue());
        }
    }

    // Find indexes for name / category / price
    $indexes = [
        'product_name' => array_search($mapping['product_name'], $headers),
        'category'     => array_search($mapping['category'], $headers),
        'price'        => array_search($mapping['price'], $headers),
    ];

    // Get per-category coefficients & woo mappings
    $coeffs = $wpdb->get_results($wpdb->prepare(
        "SELECT category_name, coefficient, woocommerce_category_ids 
         FROM {$wpdb->prefix}provider_coefficients 
         WHERE provider_id = %d",
        $provider_id
    ), OBJECT_K);

    // We'll store row-by-row data here
    $extra_data = [];

    // Modify prices row by row (start from row 2)
    foreach ($sheet->getRowIterator(2) as $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);

        try {
            // Identify category cell
            $catCellValue = $cellIterator->seek($indexes['category'])->current()->getValue();
            $category = trim(is_string($catCellValue) ? $catCellValue : '');

            // Identify price cell
            $priceCell = $cellIterator->seek($indexes['price'])->current();
            $raw_price = (string) $priceCell->getValue();
            $normalized = str_replace(',', '.', $raw_price);
            $original_price = floatval($normalized);

            $coefficient = null;
            $woo_cats    = [];

            if ($original_price > 0 && $category !== '') {
                // 1. Try a slab for this category
                $slab = $wpdb->get_row($wpdb->prepare(
                    "SELECT coefficient 
                     FROM {$wpdb->prefix}provider_category_slabs
                     WHERE provider_id = %d
                       AND category_name = %s
                       AND %f BETWEEN min_price AND IFNULL(max_price, 9999999)
                     ORDER BY min_price ASC
                     LIMIT 1",
                    $provider_id, $category, $original_price
                ));
                if ($slab) {
                    $coefficient = $slab->coefficient;
                } elseif (isset($coeffs[$category])) {
                    // 2. If no slab, use direct coefficient if found
                    $coefficient = $coeffs[$category]->coefficient;
                } else {
                    // 3. Fallback to a default slab
                    $default_slab = $wpdb->get_row($wpdb->prepare(
                        "SELECT coefficient 
                         FROM {$wpdb->prefix}provider_category_slabs
                         WHERE provider_id = %d
                           AND category_name = %s
                           AND %f BETWEEN min_price AND IFNULL(max_price, 9999999)
                         ORDER BY min_price ASC
                         LIMIT 1",
                        $provider_id, '__DEFAULT__', $original_price
                    ));
                    if ($default_slab !== null) {
                        error_log('default slab ' . print_r($default_slab, true));
                    } else {
                        error_log('default slab is null');
                    }
                    if ($default_slab) {
                        $coefficient = $default_slab->coefficient;
                    } elseif (isset($coeffs['__DEFAULT__'])) {
                        // 4. If still no slab, fallback to default coefficient
                        $coefficient = $coeffs['__DEFAULT__']->coefficient;
                    }
                }

                // If we never set anything, default to 1.0
                if (!$coefficient) {
                    $coefficient = 1.0;
                }

                // Apply the multiplier
                $modified_price = round($original_price * $coefficient, 2);
                $priceCell->setValue($modified_price);

                // Gather mapped WooCommerce categories
                if (isset($coeffs[$category])) {
                    $ids = json_decode($coeffs[$category]->woocommerce_category_ids, true);
                    if (is_array($ids)) {
                        foreach ($ids as $id) {
                            $term = get_term((int)$id, 'product_cat');
                            if (!is_wp_error($term) && isset($term->name)) {
                                $woo_cats[] = $term->name;
                            }
                        }
                    }
                }
                // If empty, fallback to provider's default WC category
                if (empty($woo_cats) && $default_wc_cat_id) {
                    $term = get_term((int)$default_wc_cat_id, 'product_cat');
                    if (!is_wp_error($term) && isset($term->name)) {
                        $woo_cats[] = strip_tags($term->name);
                    }
                }

                // Save data for final CSV columns
                $extra_data[$row->getRowIndex()] = [
                    'category'    => $category,
                    'original'    => $original_price,
                    'modified'    => $modified_price,
                    'coefficient' => $coefficient,
                    'categories'  => $woo_cats
                ];
            }
        } catch (\Exception $e) {
            // Skip any row that failed
            continue;
        }
    }

    // 1) Write the spreadsheet back to CSV (without extra columns)
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
    $writer->setDelimiter(',');
    $writer->setEnclosure('"');
    $writer->setLineEnding("\r\n");
    $writer->save($csv_file);

    // 2) Read that CSV, store it in an array
    $csv_data = [];
    if (($h = fopen($csv_file, 'r')) !== false) {
        while (($row_data = fgetcsv($h, 0, ",")) !== false) {
            $csv_data[] = $row_data;
        }
        fclose($h);
    }

    // Determine how many categories in each row's data
    $max_category_count = 0;
    foreach ($extra_data as $info) {
        $count = count($info['categories'] ?? []);
        if ($count > $max_category_count) {
            $max_category_count = $count;
        }
    }

    // Insert new columns into header row
    $csv_data[0][] = "Original Price";
    $csv_data[0][] = "Modified Price";
    $csv_data[0][] = "Coefficient Used";
    for ($i = 1; $i <= $max_category_count; $i++) {
        $csv_data[0][] = "Category $i";
    }

    // Fill the new data in each row
    foreach ($csv_data as $index => &$row_data) {
        // Skip header
        if ($index === 0) continue;

        // Row indexes in $sheet start at 1, but our CSV array starts at 0
        $rowIndex = $index + 1;

        $orig = 0.0;
        $mod  = 0.0;
        $coef = '';
        $cats = [];

        if (isset($extra_data[$rowIndex])) {
            $orig = $extra_data[$rowIndex]['original'];
            $mod  = $extra_data[$rowIndex]['modified'];
            $coef = $extra_data[$rowIndex]['coefficient'];
            $cats = $extra_data[$rowIndex]['categories'];
        }

        // Add the new columns
        $row_data[] = $orig;
        $row_data[] = $mod;
        $row_data[] = $coef;

        // Fill each cat
        for ($i = 0; $i < $max_category_count; $i++) {
            $row_data[] = isset($cats[$i]) ? $cats[$i] : '';
        }
    }

    // 3) Write the final CSV
    $final = fopen($csv_file, 'w');
    foreach ($csv_data as $row_item) {
        fputcsv($final, $row_item);
    }
    fclose($final);

    // Return the URL of the final CSV
    return $upload_dir['baseurl'] . "/provider_feeds/texacom_updated.csv";
}


function process_and_modify_provider_feed($provider_id) {
    global $wpdb;
    
    require_once ABSPATH . 'vendor/autoload.php'; // PhpSpreadsheet
    
    // Step 1: Fetch provider details
    $provider = $wpdb->get_row($wpdb->prepare(
        "SELECT feed_url, mapping, default_woocommerce_category FROM {$wpdb->prefix}provider_feeds WHERE id = %d",
        $provider_id
    ));
    if (!$provider) {
        error_log("❌ Provider not found.");
        return false;
    }
    
    $mapping = json_decode($provider->mapping, true);
    if (empty($mapping['product_name']) || empty($mapping['category']) || empty($mapping['price'])) {
        error_log("❌ Mapping fields are missing.");
        return false;
    }
    
    // Step 2: Get coefficients for price modifications and WooCommerce categories
    $coefficients = $wpdb->get_results($wpdb->prepare(
        "SELECT category_name, coefficient, woocommerce_category_ids FROM {$wpdb->prefix}provider_coefficients WHERE provider_id = %d",
        $provider_id
    ), OBJECT_K);
    if (!$coefficients) {
        error_log("❌ No coefficients found for Provider ID: $provider_id");
        return false;
    }
    
    // Step 3: Download provider feed file
    $temp_file = download_remote_file($provider->feed_url);
    if (!$temp_file) {
        error_log("❌ Failed to download feed file.");
        return false;
    }
    $file_extension = strtolower(pathinfo($temp_file, PATHINFO_EXTENSION));
    
    $upload_dir = wp_upload_dir();
    $modified_dir = $upload_dir['basedir'] . '/provider_feeds';
    if (!file_exists($modified_dir)) {
        mkdir($modified_dir, 0755, true);
    }
    $relative_path = "provider_feeds/provider_{$provider_id}.csv";
    $modified_file = $modified_dir . "/provider_{$provider_id}.csv";
    
    $modified_rows = [];
    
    // CSV/TSV Branch
    if (in_array($file_extension, ['csv', 'tsv'])) {
        $delimiter = ($file_extension === 'tsv') ? "\t" : ",";
        $file_handle = fopen($temp_file, 'r');
        if (!$file_handle) {
            error_log("❌ Failed to open CSV file.");
            return false;
        }
        // Step 4: Read headers from CSV
        $headers = fgetcsv($file_handle, 0, $delimiter);
        if (!$headers) {
            error_log("❌ Failed to read CSV headers.");
            fclose($file_handle);
            return false;
        }
        $header_indexes = array_flip($headers);
    
        // Optional: Map header names to WP All Import compatible names
        $mapped_headers = $headers;
        if (isset($mapping['product_name']) && isset($header_indexes[$mapping['product_name']])) {
            $mapped_headers[$header_indexes[$mapping['product_name']]] = 'post_title';
        }
        if (isset($mapping['category']) && isset($header_indexes[$mapping['category']])) {
            $mapped_headers[$header_indexes[$mapping['category']]] = 'tax:product_cat';
        }
        $headers = $mapped_headers;
    
        // Append additional columns
        $headers[] = "Original Price";
        $headers[] = "regular_price";
        $headers[] = "Coefficient Used";
        $headers[] = "WooCommerce Categories";
        $modified_rows[] = $headers;
    
        // Step 5: Process each CSV row
        while (($row = fgetcsv($file_handle, 0, $delimiter)) !== false) {
            // Get the category and raw price using mapping
            $category = trim($row[$header_indexes[$mapping['category']]] ?? '');
            $raw_price = $row[$header_indexes[$mapping['price']]] ?? '';
            $normalized_price = str_replace(',', '.', $raw_price);
            $original_price = floatval($normalized_price);
            $modified_price = $original_price;
            $used_coefficient = '';
    
            // Determine the coefficient and modify the price
            if ($original_price > 0 && $category !== '') {
                $slab = $wpdb->get_row($wpdb->prepare(
                    "SELECT coefficient FROM {$wpdb->prefix}provider_category_slabs 
                     WHERE provider_id = %d AND category_name = %s 
                     AND %f BETWEEN min_price AND IFNULL(max_price, 9999999)
                     ORDER BY min_price ASC LIMIT 1",
                    $provider_id, $category, $original_price
                ));
                if ($slab) {
                    $used_coefficient = $slab->coefficient;
                    $modified_price = round($original_price * $used_coefficient, 2);
                } elseif (isset($coefficients[$category])) {
                    $used_coefficient = $coefficients[$category]->coefficient;
                    $modified_price = round($original_price * $used_coefficient, 2);
                }
            }
    
            // Retrieve WooCommerce categories
            $woocommerce_categories = '';
            if (isset($coefficients[$category])) {
                $woo_category_ids = json_decode($coefficients[$category]->woocommerce_category_ids, true);
                if (!empty($woo_category_ids) && is_array($woo_category_ids)) {
                    $woo_names = [];
                    foreach ($woo_category_ids as $woo_cat_id) {
                        $term = get_term(intval($woo_cat_id), 'product_cat');
                        if (!is_wp_error($term) && isset($term->name)) {
                            $woo_names[] = strip_tags($term->name);
                        }
                    }
                    $woocommerce_categories = implode(", ", $woo_names);
                }
            }
            if (empty($woocommerce_categories) && $provider->default_woocommerce_category) {
                $default_term = get_term($provider->default_woocommerce_category, 'product_cat');
                if (!is_wp_error($default_term) && isset($default_term->name)) {
                    $woocommerce_categories = strip_tags($default_term->name);
                }
            }
    
            // Append extra columns to the row
            $row[] = $original_price;
            $row[] = $modified_price;
            $row[] = $used_coefficient;
            $row[] = $woocommerce_categories;
            $modified_rows[] = $row;
        }
        fclose($file_handle);
    
        // Step 6: Write the modified CSV file
        $fh = fopen($modified_file, 'w');
        if (!$fh) {
            error_log("❌ Failed to create modified CSV file at $modified_file.");
            return false;
        }
        foreach ($modified_rows as $r) {
            if (empty(array_filter($r))) continue;
            fputcsv($fh, $r);
        }
        fclose($fh);
        @chmod($modified_file, 0644);
        $csv_url = $upload_dir['baseurl'] . "/provider_feeds/provider_{$provider_id}.csv";
        error_log("✅ Modified CSV Saved: $csv_url");
        return $csv_url;
    
    // Excel (XLS/XLSX) Branch
    } elseif (in_array($file_extension, ['xls', 'xlsx'])) {
        $reader = ($file_extension === 'xls') 
            ? new \PhpOffice\PhpSpreadsheet\Reader\Xls() 
            : new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $spreadsheet = $reader->load($temp_file);
        $sheet = $spreadsheet->getActiveSheet();
    
        // Step 4: Extract headers from the first row
        $headers = [];
        foreach ($sheet->getRowIterator(1, 1) as $row) {
            foreach ($row->getCellIterator() as $i => $cell) {
                $headers[$i] = trim($cell->getFormattedValue());
            }
        }
        // Determine column indexes based on mapping
        $indexes = [
            'product_name' => array_search($mapping['product_name'], $headers),
            'category'     => array_search($mapping['category'], $headers),
            'price'        => array_search($mapping['price'], $headers),
        ];
    
        $extra_data = [];
        // Step 5: Process each row (starting at row 2)
        foreach ($sheet->getRowIterator(2) as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            try {
                $category_value = $cellIterator->seek($indexes['category'])->current()->getValue();
                $category = trim(is_string($category_value) ? $category_value : '');
                $priceCell = $cellIterator->seek($indexes['price'])->current();
                $raw_price = $priceCell->getValue();
                $normalized = str_replace(',', '.', (string) $raw_price);
                $original_price = floatval($normalized);
                $modified_price = $original_price;
                $used_coefficient = '';
    
                if ($original_price > 0 && $category !== '') {
                    $slab = $wpdb->get_row($wpdb->prepare(
                        "SELECT coefficient FROM {$wpdb->prefix}provider_category_slabs
                         WHERE provider_id = %d AND category_name = %s
                         AND %f BETWEEN min_price AND IFNULL(max_price, 9999999)
                         ORDER BY min_price ASC LIMIT 1",
                        $provider_id, $category, $original_price
                    ));
                    if ($slab) {
                        $used_coefficient = $slab->coefficient;
                        $modified_price = round($original_price * $used_coefficient, 2);
                    } elseif (isset($coefficients[$category])) {
                        $used_coefficient = $coefficients[$category]->coefficient;
                        $modified_price = round($original_price * $used_coefficient, 2);
                    }
                }
                // Set modified price into the spreadsheet
                $priceCell->setValue($modified_price);
    
                // Get WooCommerce categories
                $woo_cat = '';
                if (isset($coefficients[$category])) {
                    $ids = json_decode($coefficients[$category]->woocommerce_category_ids, true);
                    if (is_array($ids)) {
                        $names = array_map(function ($id) {
                            $term = get_term((int) $id, 'product_cat');
                            return !is_wp_error($term) ? $term->name : '';
                        }, $ids);
                        $woo_cat = implode(', ', array_filter($names));
                    }
                }
                if (!$woo_cat && $provider->default_woocommerce_category) {
                    $term = get_term((int) $provider->default_woocommerce_category, 'product_cat');
                    $woo_cat = !is_wp_error($term) ? $term->name : '';
                }
    
                $rowIndex = $row->getRowIndex();
                $extra_data[$rowIndex] = [
                    'original_price' => $original_price,
                    'modified_price' => $modified_price,
                    'coefficient'    => $used_coefficient,
                    'woo_cat'        => $woo_cat,
                ];
            } catch (Exception $e) {
                continue;
            }
        }
    
        // Convert the spreadsheet to an array for CSV export
        $csv_data = $sheet->toArray();
        // Append new headers to the first row
        $csv_data[0][] = "Original Price";
        $csv_data[0][] = "regular_price";
        $csv_data[0][] = "Coefficient Used";
        $csv_data[0][] = "WooCommerce Categories";
    
        // Append extra data for each subsequent row
        foreach ($csv_data as $k => &$row) {
            if ($k === 0) continue;
            $rowIndex = $k + 1;
            $original_price = '';
            $modified_price = '';
            $used_coefficient = '';
            $woo_cat = '';
            if (isset($extra_data[$rowIndex])) {
                $data = $extra_data[$rowIndex];
                $original_price = $data['original_price'];
                $modified_price = $data['modified_price'];
                $used_coefficient = $data['coefficient'];
                $woo_cat = $data['woo_cat'];
            }
            $row[] = $original_price;
            $row[] = $modified_price;
            $row[] = $used_coefficient;
            $row[] = $woo_cat;
        }
    
        // Step 6: Write final CSV file
        $fh = fopen($modified_file, 'w');
        if (!$fh) {
            error_log("❌ Failed to create modified CSV file at $modified_file.");
            return false;
        }
        foreach ($csv_data as $r) {
            if (empty(array_filter($r))) continue;
            fputcsv($fh, $r);
        }
        fclose($fh);
        @chmod($modified_file, 0644);
        $csv_url = $upload_dir['baseurl'] . "/provider_feeds/provider_{$provider_id}.csv";
        error_log("✅ Modified CSV Saved: $csv_url");
        return $csv_url;
    }
    return false;
}