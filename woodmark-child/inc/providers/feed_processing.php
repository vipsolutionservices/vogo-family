<?php
function generate_wp_all_import_template($provider_id, $csv_filename) {

    if (!class_exists('PMXI_Import_Record')) {
        error_log("❌ WP All Import plugin is not active.");
        return false;
    }

    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['basedir'] . '/wpallimport/files/' . $csv_filename;

    if (!file_exists($file_path)) {
        error_log("❌ CSV file not found: $file_path");
        return false;
    }

    include_once WP_ALL_IMPORT_ROOT_DIR . '/libraries/XmlImportCsvParse.php';

    try {
        $parser = new PMXI_CsvParser([
            'filename'  => $file_path,
            'delimiter' => ',',
            'targetDir' => dirname($file_path),
            'xpath'     => '/node',
        ]);

        $parser->analyse_file($file_path);
        $xml_path = $parser->xml_path;

        if(empty($xml_path) || !file_exists($xml_path)){
            error_log("❌ XML file not generated from CSV parser.");
            return false;
        }

    } catch (Exception $e) {
        error_log("❌ Parser error: " . $e->getMessage());
        return false;
    }

    // Explicitly set xml_path and match WP All Import's internal handling
    $options = [
        'custom_type'                  => 'product',
        'is_multiple_page_parent'      => 'no',
        'title'                        => '{title[1]}',
        'single_product_regular_price' => '{Modified Price[1]}',
        'tax_single_xpath'             => ['product_cat' => '{Category[1]}'],
        'multiple_term_assign'         => ['product_cat' => 'yes'],
        'custom_name'                  => [
            'original_price'   => '_original_price',
            'modified_price'   => '_modified_price',
            'coefficient_used' => '_coefficient_used',
            'provider_name'    => '_provider_name',
        ],
        'custom_value'                 => [
            'original_price'   => '{Original Price[1]}',
            'modified_price'   => '{Modified Price[1]}',
            'coefficient_used' => '{Coefficient[1]}',
            'provider_name'    => '{Provider[1]}',
        ],
        'unique_key'                   => '{title[1]}',
        'update_all_data'              => 'yes',
        'is_delete_missing'            => 'no',
        'delimiter'                    => ',',
        'enclosure'                    => '"',
        'fix_characters'               => 0,
        'is_leave_html'                => 0,
        'root_element'                 => 'node',
        'xml_path'                     => wp_all_import_get_relative_path($xml_path),
    ];

    $import = new PMXI_Import_Record();

    $import->set([
        'name'          => 'Provider ' . $provider_id . ' Automated Import',
        'type'          => 'upload',
        'path'          => wp_all_import_get_relative_path($xml_path), // Correctly references XML
        'root_element'  => 'node',
        'count'         => 0,
        'options'       => maybe_serialize($options),
    ]);

    $import->save();

    if (!$import->id) {
        error_log("❌ Failed to save import record.");
        return false;
    }

    error_log("✅ WP All Import template successfully created: ID {$import->id}");

    return $import->id;
}

function process_and_modify_provider_feed_wrapper($provider_id) {
    global $wpdb;
    
    // Get provider details.
    $provider = $wpdb->get_row($wpdb->prepare(
        "SELECT feed_url FROM {$wpdb->prefix}provider_feeds WHERE id = %d",
        $provider_id
    ));
    if (!$provider) {
        error_log("❌ Provider not found.");
        return false;
    }
    
    $feed_url = $provider->feed_url;
    $extension = '';
    
    // Check if the feed URL is remote.
    if (filter_var($feed_url, FILTER_VALIDATE_URL)) {
        // Download remote file to a temporary local file.
        $temp_file = download_remote_file($feed_url);
        if (!$temp_file) {
            error_log("❌ Failed to download remote feed file.");
            return false;
        }
        $extension = strtolower(pathinfo($temp_file, PATHINFO_EXTENSION));
    } else {
        // Feed URL is local.
        $extension = strtolower(pathinfo($feed_url, PATHINFO_EXTENSION));
    }
    
    // Call the appropriate function based on file extension.
    if (in_array($extension, ['xls', 'xlsx'])) {
        return process_and_modify_texacom_feed($provider_id);
    } elseif (in_array($extension, ['csv', 'tsv'])) {
        return modify_provider_feed_prices($provider_id);
    } else {
        error_log("❌ Unsupported file extension: $extension");
        return false;
    }
}