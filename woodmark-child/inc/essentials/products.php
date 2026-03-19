<?php

// --- Helper (must be defined before hooks use it)
if (!function_exists('vogo_get_vendor_for_product')) {
    function vogo_get_vendor_for_product(int $product_or_variation_id): ?array {
        static $cache = [];
        $pid = $product_or_variation_id;

        if ($pid && function_exists('wc_get_product')) {
            $p = wc_get_product($pid);
            if ($p && $p->is_type('variation')) {
                $pid = (int) $p->get_parent_id();
            }
        }

        if (isset($cache[$pid])) return $cache[$pid];

        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT pv.vendor_id, vp.provider_name
             FROM {$wpdb->prefix}vogo_product_vendor pv
             JOIN {$wpdb->prefix}vogo_providers vp ON vp.id = pv.vendor_id
             WHERE pv.product_id = %d
             LIMIT 1",
            $pid
        ), ARRAY_A);

        return $cache[$pid] = ($row
            ? ['id' => (int)$row['vendor_id'], 'name' => $row['provider_name']]
            : null);
    }
}

// Add Mobile Background Image Field in Category Edit Page
function add_category_mobile_bg_field($term) {
    $mobile_bg = get_term_meta($term->term_id, 'mobile_background', true);
    ?>
    <tr class="form-field">
        <th scope="row"><label for="mobile_background"><?php _e('Mobile Background Image', 'your-textdomain'); ?></label></th>
        <td>
            <input type="text" name="mobile_background" id="mobile_background" value="<?php echo esc_attr($mobile_bg); ?>" style="width: 60%;" />
            <button class="upload_mobile_bg button"><?php _e('Upload/Add image', 'your-textdomain'); ?></button>
            <p class="description"><?php _e('Upload a different background image for mobile view.', 'your-textdomain'); ?></p>
        </td>
    </tr>
    <script>
        jQuery(document).ready(function($){
            $('.upload_mobile_bg').click(function(e) {
                e.preventDefault();
                var custom_uploader = wp.media({
                    title: '<?php _e('Select Mobile Background Image', 'your-textdomain'); ?>',
                    button: { text: '<?php _e('Use this image', 'your-textdomain'); ?>' },
                    multiple: false
                }).on('select', function() {
                    var attachment = custom_uploader.state().get('selection').first().toJSON();
                    $('#mobile_background').val(attachment.url);
                }).open();
            });
        });
    </script>
    <?php
}
add_action('product_cat_edit_form_fields', 'add_category_mobile_bg_field', 10, 2);

// Save Mobile Background Image Field
function save_category_mobile_bg_field($term_id) {
    if (isset($_POST['mobile_background'])) {
        update_term_meta($term_id, 'mobile_background', esc_url($_POST['mobile_background']));
    }
}
add_action('edited_product_cat', 'save_category_mobile_bg_field', 10, 2);

function custom_category_mobile_bg() {
    if (is_product_category()) {
        $term = get_queried_object();
        $mobile_bg = get_term_meta($term->term_id, 'mobile_background', true);
        
        if ($mobile_bg) {
            ?>
            <style>
                @media (max-width: 768px) {
                    .page-title-wrapper {
                        background-image: url('<?php echo esc_url($mobile_bg); ?>') !important;
                    }
                }
            </style>
            <?php
        }
    }
}
function custom_wc_no_products_found() {
    echo '<p class="woocommerce-info" style="font-size:15px; font-weight:bold">Nu s-au găsit produse care să se potrivească cu selecția dvs. Puteți recomanda utilizarea acestuia<a href="https://test07.vogo.family" style="color:black; text-decoration: underline;">Formă</a></p>';
}
remove_action( 'woocommerce_no_products_found', 'wc_no_products_found' );
add_action( 'woocommerce_no_products_found', 'custom_wc_no_products_found' );

function add_searchable_and_collapsible_parent_category_dropdown() {
    $screen = get_current_screen();
    if ($screen->id !== 'edit-product_cat') {
        return;
    }

    // Fetch categories
    $categories = get_terms(array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'parent'     => 0
    ));

    // Recursive function to generate category options
    function generate_category_options($parent_id = 0, $prefix = '') {
        $categories = get_terms(array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'parent'     => $parent_id
        ));

        if (!empty($categories)) {
            foreach ($categories as $category) {
                echo '<option value="' . $category->term_id . '">' . $prefix . esc_html($category->name) . '</option>';
                generate_category_options($category->term_id, $prefix . '- ');
            }
        }
    }
    ?>

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Convert Parent Category dropdown to Select2
            if ($('#parent').length > 0) {
                $('#parent').select2({
                    placeholder: "Search a category...",
                    allowClear: true,
                    width: '100%'
                });
            }

            // Hide child categories initially inside the dropdown
            $('.category-dropdown option').each(function() {
                if ($(this).text().startsWith('- ')) {
                    $(this).addClass('child-category').hide();
                }
            });

            // Expand/collapse on select
            $('#parent').on('select2:select', function(e) {
                let selectedOption = $(this).val();
                $('.category-dropdown option').each(function() {
                    if ($(this).val() == selectedOption) {
                        $(this).nextUntil(':not(.child-category)').toggle();
                    }
                });
            });
        });
    </script>

    <style>
        .child-category {
            display: none;
        }
    </style>

    <div>
        <label for="parent"><strong>Parent Category:</strong></label>
        <select id="parent" name="parent" class="category-dropdown">
            <option value="">None</option>
            <?php generate_category_options(); ?>
        </select>
    </div>

    <?php
}
add_action('admin_footer', 'add_searchable_and_collapsible_parent_category_dropdown');

add_action('add_meta_boxes', function() {
    add_meta_box(
        'product_position_meta', // ID
        __('Product Position', 'woocommerce'), // Title
        'render_product_position_meta_box', // Callback
        'product', // Screen (post type)
        'side', // Context (side metabox)
        'default' // Priority
    );
});

// Render the Position field inside the meta box
function render_product_position_meta_box($post) {
    $menu_order = $post->menu_order ?: 9999; // Default to 9999 if not set
    ?>
    <label for="product_menu_order"><?php _e('Position (Menu Order)', 'woocommerce'); ?></label>
    <input type="number" name="product_menu_order" id="product_menu_order" value="<?php echo esc_attr($menu_order); ?>" style="width:100%; margin-top:5px;" min="0" step="1" />
    <p class="description">Lower number = Higher position on shop/archive page.</p>
    <?php
}

// Save the Position field when product is saved
add_action('save_post_product', function($post_id) {
    if (isset($_POST['product_menu_order'])) {
        global $wpdb;
        $menu_order = intval($_POST['product_menu_order']);
        if (!$menu_order) {
            $menu_order = 9999;
        }

        // Direct database update, faster, no extra hooks triggered
        $wpdb->update(
            $wpdb->posts,
            ['menu_order' => $menu_order],
            ['ID' => $post_id]
        );
    }
});

add_action('pre_get_posts', function($query) {
    if (!is_admin() && $query->is_main_query()) {
        if (function_exists('is_shop') && (is_shop() || is_product_category() || is_product_tag())) {
            $query->set('orderby', 'menu_order title'); // First by menu_order, then title
            $query->set('order', 'ASC');
        }
    }
});

// 1. Add "Position" column to Products table
add_filter('manage_edit-product_columns', function($columns) {
    $columns['menu_order'] = __('Position', 'woocommerce');
    return $columns;
});

// 2. Show menu_order value in the Position column
add_action('manage_product_posts_custom_column', function($column, $post_id) {
    if ($column === 'menu_order') {
        echo (int) get_post_field('menu_order', $post_id);
    }
}, 10, 2);

// 3. Make the Position column sortable
add_filter('manage_edit-product_sortable_columns', function($columns) {
    $columns['menu_order'] = 'menu_order';
    return $columns;
});

// 4. Modify query to sort by menu_order when requested
add_action('pre_get_posts', function($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    $orderby = $query->get('orderby');
    if ($orderby === 'menu_order') {
        $query->set('orderby', 'menu_order');
        // Let WordPress handle the ASC/DESC from the UI
        $query->set('order', $query->get('order') === 'desc' ? 'DESC' : 'ASC');
    }
});

// Set a custom marker for sorting
add_action('pre_get_posts', function($query) {
    if (is_admin() && $query->is_main_query() && $query->get('orderby') === 'menu_order') {
        $query->set('orderby', 'custom_menu_order');
    }
});

// Override ORDER BY clause
add_filter('posts_orderby', function($orderby, $query) {
    if (is_admin() && $query->is_main_query() && $query->get('orderby') === 'custom_menu_order') {
        global $wpdb;

        $direction = strtoupper($query->get('order')) === 'DESC' ? 'DESC' : 'ASC';

        if ($direction === 'ASC') {
            return "(CASE WHEN {$wpdb->posts}.menu_order = 0 THEN 999999 ELSE {$wpdb->posts}.menu_order END) ASC";
        } else {
            return "(CASE WHEN {$wpdb->posts}.menu_order = 0 THEN -1 ELSE {$wpdb->posts}.menu_order END) DESC";
        }
    }

    return $orderby;
}, 99, 2);

add_action('wp_footer', function() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
    const referrer = document.referrer;
    const backButtons = document.querySelectorAll('.back-to-category');

    backButtons.forEach(function (button) {
        // Optional: only show if user came from a category page
        if (referrer.includes('/product-category/')) {
            button.style.display = 'block';

            button.addEventListener('click', function (e) {
                e.preventDefault();
                history.back();
            });
        } else {
            // If not from category, hide the button (optional)
            button.style.display = 'none';
        }
    });
});
    </script>
    <?php
});

// Back to previous page for small-screen button
add_action('wp_footer', function () {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const referrer = document.referrer;
        const backButton = document.querySelector('.small-screen-back-to-category');

        if (backButton) {
            backButton.style.display = 'block';
            backButton.addEventListener('click', function (e) {
                e.preventDefault();
                window.location.href = referrer || 'https://test07.vogo.family';
            });
        }
    });
    </script>
    <?php
}, 30);

/** -----------------------------------------------
 *  Vogo Provider (Product edit sidebar)
 *  Source: wp_vogo_providers
 *  Saves into: wp_vogo_product_vendor (one vendor per product)
 *  ----------------------------------------------- */

// Add the provider dropdown meta box
add_action('add_meta_boxes_product', function () {
    add_meta_box(
        'vogo_provider_meta',
        __('Vogo Provider', 'woocommerce'),
        'render_vogo_provider_meta_box',
        'product',
        'side',
        'default'
    );
});

// Render dropdown
function render_vogo_provider_meta_box(WP_Post $post) {
    global $wpdb;
    wp_nonce_field('vogo_provider_meta', 'vogo_provider_meta_nonce');

    // Current mapping
    $current_vendor_id = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT vendor_id FROM {$wpdb->prefix}vogo_product_vendor WHERE product_id=%d LIMIT 1",
        $post->ID
    ));

    // Providers list
    $providers = $wpdb->get_results(
        "SELECT id, provider_name
           FROM {$wpdb->prefix}vogo_providers
          WHERE status='active'
          ORDER BY provider_name ASC",
        ARRAY_A
    );

    echo '<label for="vogo_provider_id"><strong>'.esc_html__('Select Provider','your-textdomain').'</strong></label>';
    echo '<select id="vogo_provider_id" name="vogo_provider_id" style="width:100%;margin-top:6px;">';
    echo '<option value="0">'.esc_html__('(None)','your-textdomain').'</option>';
    foreach ($providers as $p) {
        printf('<option value="%d"%s>%s</option>',
            (int)$p['id'],
            selected($current_vendor_id, (int)$p['id'], false),
            esc_html($p['provider_name'])
        );
    }
    echo '</select>';
}

// =========================================================
// Vogo Provider save — resilient against legacy deletes
// =========================================================

// Shared UPSERT helper (insert/update mapping; optional controlled delete)
if (!function_exists('vogo_upsert_vogo_product_vendor')) {
    /**
     * Insert/Update vendor mapping for a product.
     * If $allow_delete === true and $vendor_id === 0 => delete mapping.
     */
    function vogo_upsert_vogo_product_vendor(int $product_id, int $vendor_id, bool $allow_delete = false): void {
        global $wpdb;

        $table = $wpdb->prefix . 'vogo_product_vendor';
        $uid   = get_current_user_id() ?: 0;

        // Optional logging (if your logger exists)
        if (function_exists('vogo_error_log3')) {
            vogo_error_log3("[VOGO SAVE] product_id=$product_id vendor_id=$vendor_id allow_delete=" . ($allow_delete ? '1' : '0'));
        }

        if ($vendor_id > 0) {
            // Upsert row (one vendor per product enforced by UNIQUE(product_id))
            $sql = $wpdb->prepare(
                "INSERT INTO $table (product_id, vendor_id, status, created_by, modified_by)
                 VALUES (%d,%d,'active',%d,%d)
                 ON DUPLICATE KEY UPDATE vendor_id=VALUES(vendor_id), status='active',
                     modified_by=VALUES(modified_by), modified_at=CURRENT_TIMESTAMP",
                $product_id, $vendor_id, $uid, $uid
            );
            $wpdb->query($sql);
            if ($wpdb->last_error && function_exists('vogo_error_log3')) {
                vogo_error_log3('[VOGO SAVE][SQL ERR] ' . $wpdb->last_error);
            }
        } elseif ($allow_delete) {
            // Delete only when our form explicitly posted "None"
            $sql = $wpdb->prepare("DELETE FROM $table WHERE product_id=%d", $product_id);
            $wpdb->query($sql);
            if ($wpdb->last_error && function_exists('vogo_error_log3')) {
                vogo_error_log3('[VOGO DELETE][SQL ERR] ' . $wpdb->last_error);
            }
        } else {
            // Silent no-op (do not delete if our field wasn't posted)
            if (function_exists('vogo_error_log3')) {
                vogo_error_log3('[VOGO SAVE] No delete performed (allow_delete=0).');
            }
        }
    }
}

// ---------------------------------------------------------
// PRIMARY save path (WooCommerce admin product save)
// Fires reliably for classic + new product editor
// ---------------------------------------------------------
add_action('woocommerce_admin_process_product_object', function ($product) {
    if (!isset($_POST['vogo_provider_meta_nonce']) ||
        !wp_verify_nonce($_POST['vogo_provider_meta_nonce'], 'vogo_provider_meta')) {
        return; // our form not posted
    }

    $vendor_id  = isset($_POST['vogo_provider_id']) ? absint($_POST['vogo_provider_id']) : 0;
    $product_id = (int) $product->get_id();

    // Allow delete only if our field was posted
    vogo_upsert_vogo_product_vendor($product_id, $vendor_id, true);
}, 20);

// ---------------------------------------------------------
// FINAL safety net — run LAST on save_post_product
// Ensures our upsert wins over any legacy delete
// ---------------------------------------------------------
add_action('save_post_product', 'vogo_provider_save_final', PHP_INT_MAX, 3);
function vogo_provider_save_final($post_id, $post, $update) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;
    if (!isset($_POST['vogo_provider_meta_nonce']) ||
        !wp_verify_nonce($_POST['vogo_provider_meta_nonce'], 'vogo_provider_meta')) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $vendor_id = isset($_POST['vogo_provider_id']) ? absint($_POST['vogo_provider_id']) : 0;

    // Allow delete only if our field was posted
    vogo_upsert_vogo_product_vendor((int)$post_id, $vendor_id, true);
}

// 1) Add column to Products table
add_filter('manage_edit-product_columns', function($cols){
    // Insert after the product title ("name")
    $new = [];
    foreach ($cols as $key => $label) {
        $new[$key] = $label;
        if ($key === 'name' || $key === 'title') {
            $new['vogo_vendor'] = __('Vendor', 'your-textdomain');
        }
    }
    return $new;
});

// 2) Render the "Vendor" column
add_action('manage_product_posts_custom_column', function($col, $post_id){
    if ($col !== 'vogo_vendor') return;

    global $wpdb;

    // Get vendor_id for this product
    $vid = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT vendor_id FROM {$wpdb->prefix}vogo_product_vendor WHERE product_id=%d LIMIT 1",
        $post_id
    ));
    if (!$vid) { echo '—'; return; }

    // Resolve vendor name
    $name = $wpdb->get_var($wpdb->prepare(
        "SELECT provider_name FROM {$wpdb->prefix}vogo_providers WHERE id=%d",
        $vid
    ));
    echo esc_html($name ?: ('#'.$vid));
}, 10, 2);

// 3) Add a "Vendor" filter to the Products list (admin)
add_action('restrict_manage_posts', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'product') return;

    global $wpdb;
    $selected = isset($_GET['filter_vogo_provider']) ? absint($_GET['filter_vogo_provider']) : 0;

    $providers = $wpdb->get_results(
        "SELECT id, provider_name
           FROM {$wpdb->prefix}vogo_providers
          WHERE status IN ('active','hidden')  /* show all; adjust if needed */
          ORDER BY provider_name ASC"
    );

    echo '<select name="filter_vogo_provider" style="max-width:220px">';
    echo '<option value="">' . esc_html__('All Vendors', 'your-textdomain') . '</option>';
    foreach ($providers as $p) {
        printf(
            '<option value="%d"%s>%s</option>',
            (int)$p->id,
            selected($selected, (int)$p->id, false),
            esc_html($p->provider_name)
        );
    }
    echo '</select>';
});

// 4) Apply the vendor filter to the query
add_action('pre_get_posts', function ($q) {
    if (!is_admin() || !$q->is_main_query()) return;
    if ($q->get('post_type') !== 'product') return;

    $vendor_id = isset($_GET['filter_vogo_provider']) ? absint($_GET['filter_vogo_provider']) : 0;
    if ($vendor_id <= 0) return;

    global $wpdb;
    // fetch product IDs mapped to the selected vendor
    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT product_id FROM {$wpdb->prefix}vogo_product_vendor WHERE vendor_id=%d",
        $vendor_id
    ));

    // If none, force empty result
    $q->set('post__in', !empty($ids) ? array_map('intval', $ids) : [0]);
});
/* - not used this - because show it online
// CART: Add "Vendor" line in the item meta block on Cart/Checkout
add_filter('woocommerce_get_item_data', function ($item_data, $cart_item) {
    $product = isset($cart_item['data']) ? $cart_item['data'] : null;
    if (!$product) return $item_data;

    $pid = $product->get_id();
    $vendor = vogo_get_vendor_for_product($pid);
    if (!$vendor) return $item_data;

    $item_data[] = [
        'key'   => __('Vendor', 'your-textdomain'),
        'value' => esc_html($vendor['name']),
        'display' => esc_html($vendor['name']),
    ];
    return $item_data;
}, 10, 2);
*/

// CART - Inline badge under product title in Cart table
// show it inline (right under the product title), use this instead or as well:
add_filter('woocommerce_cart_item_name', function ($name, $cart_item, $cart_item_key) {
    $p = isset($cart_item['data']) ? $cart_item['data'] : null;
    if (!$p) return $name;

    $vendor = vogo_get_vendor_for_product($p->get_id());
    if (!$vendor) return $name;

    $badge = '<div class="vogo-vendor-badge" style="font-size:12px;color:#555;margin-top:2px;">'
           . esc_html__('Vendor:', 'your-textdomain') . ' <strong>'
           . esc_html($vendor['name']) . '</strong></div>';
    return $name . $badge;
}, 10, 3);

// CART - Renders the grouped-by-vendor summary box
function vogo_render_vendor_groups_box() {
    if (!WC()->cart) return;

    $groups = [];
    foreach (WC()->cart->get_cart() as $cart_item) {
        if (empty($cart_item['data'])) continue;
        $product = $cart_item['data'];

        if (!function_exists('vogo_get_vendor_for_product')) continue;
        $vendor = vogo_get_vendor_for_product($product->get_id()) ?: ['id'=>0,'name'=>__('(No vendor)','your-textdomain')];

        $vid   = (int) $vendor['id'];
        $vname = $vendor['name'];

        if (!isset($groups[$vid])) $groups[$vid] = ['name'=>$vname, 'subtotal'=>0.0];

        $line = (float) ($cart_item['line_total'] ?? 0);
        if (wc_prices_include_tax()) $line += (float) ($cart_item['line_tax'] ?? 0);
        $groups[$vid]['subtotal'] += $line;
    }

    if (!$groups) return;

    echo '<div class="vogo-vendor-groups" style="margin:18px 0;border:1px solid #e3e3e3;padding:12px;border-radius:6px;background:#fafafa;">';
    echo '<div style="font-weight:600;margin-bottom:6px;">' . esc_html__('Order suppliers', 'your-textdomain') . '</div>';
    echo '<ul style="list-style:none;margin:0;padding:0;">';
    foreach ($groups as $g) {
        printf(
            '<li style="display:flex;justify-content:space-between;padding:4px 0;border-top:1px dashed #e6e6e6;"><span>%s</span><strong>%s</strong></li>',
            esc_html($g['name']),
            wc_price($g['subtotal'])
        );
    }
    echo '</ul>';
    echo '</div>';
}

// CART Place box **below** the cart table
add_action('woocommerce_after_cart_table', 'vogo_render_vendor_groups_box', 5);

// CART If you previously hooked it above the table, ensure it's removed (safe even if not added)
remove_action('woocommerce_before_cart_table', 'vogo_render_vendor_groups_box', 9);


