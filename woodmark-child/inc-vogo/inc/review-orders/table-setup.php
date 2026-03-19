<?php
register_activation_hook(get_stylesheet_directory() . '/functions.php', 'create_order_reviews_table');

function create_order_reviews_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'order_reviews';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        rating INT NOT NULL,
        comment TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

add_filter('manage_woocommerce_page_wc-orders_columns', function ($columns) {
    $columns['custom_order_review'] = __('Revizuiește', 'your-textdomain');
    return $columns;
}, 20);

add_action('manage_woocommerce_page_wc-orders_custom_column', function ($column, $order) {
    if ($column !== 'custom_order_review') {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'order_reviews';
    $order_id = $order instanceof WC_Order ? $order->get_id() : intval($order);

    $review = $wpdb->get_row($wpdb->prepare(
        "SELECT rating, comment FROM $table WHERE order_id = %d ORDER BY created_at DESC LIMIT 1",
        $order_id
    ));

    if ($review) {
        echo '<strong>' . esc_html($review->rating) . '/5</strong><br>';
        echo esc_html(wp_trim_words($review->comment, 15));
    } else {
        echo '—';
    }
}, 20, 2);


add_action('woocommerce_admin_order_data_after_order_details', function ($order) {
    global $wpdb;
    $order_id = $order instanceof WC_Order ? $order->get_id() : intval($order);
    $table = $wpdb->prefix . 'order_reviews';


    // Now show the review just below the QR code
    $review = $wpdb->get_row($wpdb->prepare(
        "SELECT rating, comment, created_at FROM $table WHERE order_id = %d ORDER BY created_at DESC LIMIT 1",
        $order_id
    ));

    if ($review) {
        echo '<div class="order_customer_review" style="margin-top:20px;padding:10px 0;border-top:1px solid #ddd;">';
        echo '<h4>' . esc_html__('Recenzie de la client', 'your-textdomain') . '</h4>';
        echo '<p><strong>Evaluare:</strong> ' . esc_html($review->rating) . '/5</p>';
        echo '<p><strong>Comentariu:</strong><br>' . nl2br(esc_html($review->comment)) . '</p>';
        echo '<p><small><em>Trimis la: ' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($review->created_at))) . '</em></small></p>';
        echo '</div>';
    }
});

add_filter('manage_woocommerce_page_wc-orders_columns', function ($columns) {
    $columns['order_qr_code'] = __('Cod QR', 'your-textdomain');
    return $columns;
}, 20);

add_action('admin_enqueue_scripts', function () {
    wp_register_script('qrcodejs', 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js', [], null, true);
    wp_enqueue_script('qrcodejs');
});

add_action('manage_woocommerce_page_wc-orders_custom_column', function ($column, $order) {
    if ($column !== 'order_qr_code') return;

    if (!($order instanceof WC_Order)) {
        $order = wc_get_order($order);
    }

    $order_id = $order->get_id();
    $feedback_url = site_url('/my-account/view-order/' . $order_id);
    $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($feedback_url) . '&size=100x100';

    echo '<div id="qr_box_' . $order_id . '" style="text-align:center">';
    echo '<button type="button" class="button" style="margin-top:4px;" onclick="printOrderQR_' . $order_id . '()">Print QR</button>';
    echo '</div>';

    echo "<script>
    function printOrderQR_{$order_id}() {
        const html = `
            <!DOCTYPE html>
            <html>
            <head>
                <title>Print QR</title>
                <style>
                    body { font-family: sans-serif; text-align: center; padding: 20px; }
                    img { max-width: 100%; height: auto; }
                </style>
            </head>
            <body>
                <h2>Order #{$order_id} Feedback QR</h2>
                <img src='{$qr_url}' width='100' height='100' alt='QR Code'><br>
                <p><a href='{$feedback_url}' target='_blank'>{$feedback_url}</a></p>
            </body>
            </html>
        `;

        const win = window.open('', '', 'width=400,height=600');
        win.document.open();
        win.document.write(html);
        win.document.close();

        setTimeout(() => {
            win.focus();
            win.print();
            win.close();
        }, 500);
    }
    </script>";
}, 20, 2);

add_filter('bulk_actions-woocommerce_page_wc-orders', function ($bulk_actions) {
    $bulk_actions['bulk_print_qr'] = __('Print QR Codes', 'your-textdomain');
    return $bulk_actions;
}, 20);

add_filter('handle_bulk_actions-edit-shop_order', function ($redirect_url, $action, $post_ids) {
    if ($action !== 'bulk_print_qr') return $redirect_url;

    $ids = implode(',', array_map('intval', $post_ids));
    wp_redirect(admin_url('admin.php?bulk_qr_print=1&order_ids=' . urlencode($ids)));
    exit;
}, 10, 3);

add_filter('handle_bulk_actions-woocommerce_page_wc-orders', function ($redirect_url, $action, $post_ids) {
    if ($action !== 'bulk_print_qr') return $redirect_url;

    $ids = implode(',', array_map('intval', $post_ids));
    wp_redirect(admin_url('admin.php?bulk_qr_print=1&order_ids=' . urlencode($ids)));
    exit;
}, 10, 3);



add_action('admin_init', function () {
    if (!isset($_GET['bulk_qr_print'], $_GET['order_ids'])) return;
    if (!current_user_can('manage_woocommerce')) return;

    $order_ids = array_map('intval', explode(',', $_GET['order_ids']));

    echo '<!DOCTYPE html><html><head><title>Print QR Codes</title>
        <style>
            body { font-family: sans-serif; padding: 20px; }
            .qr-block { page-break-after: always; margin-bottom: 40px; text-align: center; }
            img { max-width: 150px; height: auto; }
            h3 { margin-bottom: 10px; }
            a { display: block; font-size: 12px; word-break: break-all; }
        </style>
    </head><body onload="window.print()">';

    foreach ($order_ids as $order_id) {
        $url = esc_url(site_url('/my-account/view-order/' . $order_id));
        $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($url) . '&size=150x150';

        echo '<div class="qr-block">';
        echo '<h3>Order #' . esc_html($order_id) . '</h3>';
        echo '<img src="' . esc_url($qr_url) . '" alt="QR Code"><br>';
        echo '<a href="' . esc_url($url) . '" target="_blank">' . esc_html($url) . '</a>';
        echo '</div>';
    }

    echo '</body></html>';
    exit;
});

// export orders with product providers
add_filter('bulk_actions-woocommerce_page_wc-orders', function ($bulk_actions) {
    $bulk_actions['bulk_export_orders'] = __('Export Orders (CSV)', 'your-textdomain');
    return $bulk_actions;
}, 20);

add_filter('handle_bulk_actions-woocommerce_page_wc-orders', function ($redirect_url, $action, $post_ids) {
    if ($action !== 'bulk_export_orders') return $redirect_url;

    $ids = implode(',', array_map('intval', $post_ids));
    wp_redirect(admin_url('admin.php?bulk_order_export=1&order_ids=' . urlencode($ids)));
    exit;
}, 10, 3);


/**
 * Exports WooCommerce orders as a CSV file based on provided order IDs via admin action.
 * Includes order details, product info, notes, and custom reviews.
 */
// add_action('admin_init', function () {
//     if (!isset($_GET['bulk_order_export'], $_GET['order_ids'])) return;
//     if (!current_user_can('manage_woocommerce')) return;

//     global $wpdb;
//     $table_reviews = $wpdb->prefix . 'order_reviews';

//     $order_ids = array_map('intval', explode(',', $_GET['order_ids']));

//     header('Content-Type: text/csv');
//     header('Content-Disposition: attachment;filename=exported-orders.csv');
//     header('Cache-Control: no-store, no-cache');

//     $output = fopen('php://output', 'w');

//     // Column headers
//     fputcsv($output, [
//         'Order ID', 'Date', 'Customer', 'Email', 'Total', 'Status',
//         'Product Names', 'Product Providers', 'Order Notes',
//         'Shop Manager Notes', 'Customer Review', 'Review Link', 'Order Lines'
//     ]);

//     foreach ($order_ids as $order_id) {
//         $order = wc_get_order($order_id);
//         if (!$order) continue;

//         // Core fields
//         $name = $order->get_formatted_billing_full_name();
//         $email = $order->get_billing_email();
//         $total = $order->get_total();
//         $status = $order->get_status();
//         $date = $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i') : '';
//         $qr_link = site_url('/my-account/view-order/' . $order_id);

//         // Products and providers
//         $product_names = [];
//         $provider_names = [];

//         foreach ($order->get_items('line_item') as $item) {
//             $product_names[] = $item->get_name();
//             $product_id = $item->get_product_id();
//             $terms = get_the_terms($product_id, 'product_provider');
//             if (!empty($terms) && !is_wp_error($terms)) {
//                 foreach ($terms as $term) {
//                     $provider_names[] = $term->name;
//                 }
//             }
//         }

//         $order_lines = [];
//         foreach ($order->get_items('line_item') as $item) {
//             $product_name = $item->get_name();
//             $qty = $item->get_quantity();
//             $line_total = str_replace("\xc2\xa0", ' ', strip_tags(wc_price($item->get_total())));
//             $order_lines[] = "$product_name (x$qty, $line_total)";
//         }

//         // Notes
//         $order_notes = $order->get_customer_note();
//         $manager_note = get_post_meta($order_id, '_shop_manager_note', true);

//         // Custom review
//         $review = $wpdb->get_var($wpdb->prepare(
//             "SELECT comment FROM $table_reviews WHERE order_id = %d ORDER BY created_at DESC LIMIT 1",
//             $order_id
//         ));

//         fputcsv($output, [
//             $order_id,
//             $date,
//             $name,
//             $email,
//             $total,
//             $status,
//             implode(', ', $product_names),
//             implode(', ', array_unique($provider_names)),
//             $order_notes ?: '—',
//             $manager_note ?: '—',
//             $review ?: '—',
//             $qr_link,
//             implode(' | ', $order_lines)
//         ]);
//     }

//     fclose($output);
//     exit;
// });

// add_action('admin_init', function () {
//     if (!isset($_GET['bulk_order_export'], $_GET['order_ids'])) return;
//     if (!current_user_can('manage_woocommerce')) return;

//     global $wpdb;
//     $table_reviews = $wpdb->prefix . 'order_reviews';

//     $order_ids = array_map('intval', explode(',', $_GET['order_ids']));

//     header('Content-Type: text/csv');
//     header('Content-Disposition: attachment;filename=exported-order-lines.csv');
//     header('Cache-Control: no-store, no-cache');

//     // Output UTF-8 BOM for Excel compatibility with special characters
//     echo "\xEF\xBB\xBF";
//     $output = fopen('php://output', 'w');

//     // Column headers
//     fputcsv($output, [
//         'Order ID', 'Date', 'Customer', 'Email', 'Order Total', 'Status',
//         'Product Code', 'Product Name', 'Qty', 'Unit Price', 'Total Price', 'VAT %',
//         'Product Providers', 'Order Notes', 'Shop Manager Notes', 'Customer Review', 'QR Link'
//     ]);

//     foreach ($order_ids as $order_id) {
//         $order = wc_get_order($order_id);
//         if (!$order) continue;

//         $name = $order->get_formatted_billing_full_name();
//         $email = $order->get_billing_email();
//         $order_total = $order->get_total();
//         $status = $order->get_status();
//         $date = $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i') : '';
//         $order_notes = $order->get_customer_note();
//         $manager_note = get_post_meta($order_id, '_shop_manager_note', true);
//         $qr_link = site_url('/my-account/view-order/' . $order_id);

//         // Get review
//         $review = $wpdb->get_var($wpdb->prepare(
//             "SELECT comment FROM $table_reviews WHERE order_id = %d ORDER BY created_at DESC LIMIT 1",
//             $order_id
//         ));

//         foreach ($order->get_items('line_item') as $item) {
//             $product = $item->get_product();
//             $sku = $product ? $product->get_sku() : '';
//             $product_name = $item->get_name();
//             $qty = $item->get_quantity();
//             $line_total = $item->get_total();
//             $unit_price = $qty ? round($line_total / $qty, 2) : 0;

//             // VAT %
//             $taxes = $item->get_taxes();
//             $vat_percent = 0;
//             if (!empty($taxes['total'])) {
//                 $tax_total = array_sum(array_map('floatval', $taxes['total']));
//                 $vat_percent = $line_total > 0 ? round(($tax_total / $line_total) * 100, 2) : 0;
//             }

//             // Product providers
//             $provider_names = [];
//             $product_id = $item->get_product_id();
//             $terms = get_the_terms($product_id, 'product_provider');
//             if (!empty($terms) && !is_wp_error($terms)) {
//                 foreach ($terms as $term) {
//                     $provider_names[] = $term->name;
//                 }
//             }

//             fputcsv($output, [
//                 $order_id,
//                 $date,
//                 $name,
//                 $email,
//                 number_format($order_total, 2, '.', ''),
//                 $status,
//                 $sku,
//                 $product_name,
//                 $qty,
//                 number_format($unit_price, 2, '.', ''),
//                 number_format($line_total, 2, '.', ''),
//                 $vat_percent,
//                 implode(', ', array_unique($provider_names)),
//                 $order_notes ?: '-',
//                 $manager_note ?: '-',
//                 $review ?: '-',
//                 $qr_link
//             ]);
//         }
//     }

//     fclose($output);
//     exit;
// });


//TAG#EXPORT-ORDERS
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

add_action('admin_init', function () {
    if (!isset($_GET['bulk_order_export'], $_GET['order_ids'])) return;
    if (!current_user_can('manage_woocommerce')) return;

    if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
        require_once ABSPATH . 'vendor/autoload.php';
    }

    global $wpdb;
    $table_reviews = $wpdb->prefix . 'order_reviews';

    $order_ids = array_map('intval', explode(',', $_GET['order_ids']));

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Header row
    $headers = [
        'Order ID', 'Date', 'Customer', 'Email', 'Order Total', 'Status',
        'Product Code', 'Product Name', 'Qty', 'Unit Price', 'Total Price', 'VAT %',
        'Product Providers', 'Order Notes', 'Shop Manager Notes', 'Customer Review', 'QR Link'
    ];
    $sheet->fromArray($headers, null, 'A1');

    $row = 2;

    foreach ($order_ids as $order_id) {
        $order = wc_get_order($order_id);
        if (!$order) continue;

        $name = $order->get_formatted_billing_full_name();
        $email = $order->get_billing_email();
        $order_total = $order->get_total();
        $status = $order->get_status();
        $date = $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i') : '';
        $order_notes = $order->get_customer_note();
        $manager_note = get_post_meta($order_id, '_shop_manager_note', true);
        $qr_link = site_url('/my-account/view-order/' . $order_id);

        // Get review
        $review = $wpdb->get_var($wpdb->prepare(
            "SELECT comment FROM $table_reviews WHERE order_id = %d ORDER BY created_at DESC LIMIT 1",
            $order_id
        ));

        foreach ($order->get_items('line_item') as $item) {
            $product = $item->get_product();
            $sku = $product ? $product->get_sku() : '';
            $product_name = $item->get_name();
            $qty = $item->get_quantity();
            $line_total = $item->get_total();
            $unit_price = $qty ? round($line_total / $qty, 2) : 0;

            $taxes = $item->get_taxes();
            $vat_percent = 0;
            if (!empty($taxes['total'])) {
                $tax_total = array_sum(array_map('floatval', $taxes['total']));
                $vat_percent = $line_total > 0 ? round(($tax_total / $line_total) * 100, 2) : 0;
            }

            $provider_names = [];
            $product_id = $item->get_product_id();
            $terms = get_the_terms($product_id, 'product_provider');
            if (!empty($terms) && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $provider_names[] = $term->name;
                }
            }

            $sheet->fromArray([
                $order_id,
                $date,
                $name,
                $email,
                number_format($order_total, 2, '.', ''),
                $status,
                $sku,
                $product_name,
                $qty,
                number_format($unit_price, 2, '.', ''),
                number_format($line_total, 2, '.', ''),
                $vat_percent,
                implode(', ', array_unique($provider_names)),
                $order_notes ?: '-',
                $manager_note ?: '-',
                $review ?: '-',
                $qr_link
            ], null, 'A' . $row);

            $row++;
        }
    }

    // Output as XLSX
    // Clean all output buffers to prevent any output before XLSX file
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="exported-orders.xlsx"');
    header('Cache-Control: max-age=0');
    header('Expires: 0');
    header('Pragma: public');

    $writer = new Xlsx($spreadsheet);
    try {
        $writer->save('php://output');
    } catch (Exception $e) {
        error_log("XLSX export error: " . $e->getMessage());
        wp_die('Could not generate XLSX file.');
    }
    exit;
});