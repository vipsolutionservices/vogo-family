<?php
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
    echo '<p class="woocommerce-info" style="font-size:15px; font-weight:bold">Nu s-au găsit produse care să se potrivească cu selecția dvs. Puteți recomanda utilizarea acestuia<a href="https://vogo.family" style="color:black; text-decoration: underline;">Formă</a></p>';
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
        if (is_shop() || is_product_category() || is_product_tag()) {
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
                window.location.href = referrer || 'https://vogo.family';
            });
        }
    });
    </script>
    <?php
}, 30);