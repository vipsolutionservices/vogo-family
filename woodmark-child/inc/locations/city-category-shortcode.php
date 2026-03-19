<?php

function city_based_child_categories_shortcode() {
    global $wpdb;
    // Check if we are on a product category archive page
    if (!is_product_category()) {
        return '<p>Acest shortcode trebuie utilizat pe o pagină de arhivă a unei categorii de produse.</p>';
    }

    // Get the current product category
    $current_category = get_queried_object();
    if (!$current_category) {
        return '<p>Categorie Invalidă.</p>';
    }

    // Get Parent Category ID
    $parent_id = $current_category->term_id;

    // Get the parent category's display type
    $display_type = get_term_meta($parent_id, 'display_type', true);

    // If the parent category has display type "products", do not show subcategories
    if ($display_type === 'products') {
        return ''; // Return empty output
    }

    // Get selected city from cookies (override with debug value if not present)
    $selected_city = isset($_COOKIE['selected_city']) ? sanitize_text_field($_COOKIE['selected_city']) : 'debug_city';
    // echo 'Hello city';
    // Flush cache to ensure fresh term data
    wp_cache_flush();

    // Fetch all product categories and manually filter
    $all_categories = get_terms([
        'taxonomy'   => 'product_cat',
        'hide_empty' => false
    ]);

    $child_categories = array_filter($all_categories, function($cat) use ($parent_id) {
        return $cat->parent == $parent_id;
    });

    
    if (empty($child_categories)) {
        return '';
    }
    
    $output = '<div class="elementor-element elementor-element-4a415e3 cat-list elementor-grid-4 elementor-grid-tablet-3 elementor-grid-mobile-2 elementor-products-grid elementor-wc-products elementor-widget elementor-widget-wc-categories" data-element_type="widget" data-widget_type="wc-categories.default">
                <div class="woocommerce columns-4">
                    <div class="products wd-products grid-masonry wd-grid-f-col grid-columns-4 elements-grid pagination-pagination title-line-two wd-stretch-cont-lg wd-stretch-cont-md wd-stretch-cont-sm wd-products-with-bg" data-columns="4" style="--wd-col-lg: 4; --wd-col-md: 3; --wd-col-sm: 2; --wd-gap-lg: 20px; --wd-gap-sm: 10px; position: relative; min-height: 400px;" >';

    // Debug output removed

    $matching_categories = [];

    // Loop through ONLY direct child categories
    foreach ($child_categories as $index => $category) {
        // Get cities assigned to this category
        $saved_cities = get_term_meta($category->term_id, 'product_category_cities', true);
        $saved_cities = maybe_unserialize($saved_cities);

        if (!is_array($saved_cities)) {
            $saved_cities = [];
        }

        // Check if the category should be displayed
        if (empty($saved_cities) || in_array($selected_city, $saved_cities)) {
            $category_link = get_term_link((int) $category->term_id, 'product_cat');
            // Debug output: log category name and link
            error_log("Category: " . $category->name . " | Link: " . $category_link);
            $thumbnail_id = get_term_meta($category->term_id, 'thumbnail_id', true);
            $thumbnail_url = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : wc_placeholder_img_src();

            // Positioning for layout
            $left_position = $index * 121; // Adjust spacing dynamically
            $top_position = 0;

            // Debug output: show category name and link
          //  $output .= '<p>' . esc_html($category->name) . ': ' . esc_url($category_link) . '</p>';

            $matching_categories[] = '
                 <div class="wd-col category-grid-item wd-cat cat-design-alt categories-with-shadow wd-with-subcat product-category product" data-loop="' . ($index + 1) . '">
                    <div class="wd-cat-wrap">
                        <div class="wrapp-category">
                            <div class="category-image-wrapp">
                                <a href="' . esc_url($category_link) . '" class="category-image" aria-label="Category image">
                                    <img loading="lazy" width="600" height="600" src="' . esc_url($thumbnail_url) . '" class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail" alt="' . esc_attr($category->name) . '">
                                </a>
                            </div>
                            <div class="hover-mask">
                                <h3 class="wd-entities-title notranslate"><a href="' . esc_url($category_link) . '" data-ajax="false">' . esc_html($category->name) . '</a></h3>
                            </div>
                            <span class="category-link wd-fill" aria-hidden="true"></span>
                        </div>
                    </div>
                </div>';
        }
    }

    // If no categories match, show "None"
    if (empty($matching_categories)) {
        // $output .= '<p>No categories available for this city.</p>';
    } else {
        $output .= implode('', $matching_categories);
    }

    $output .= '</div></div></div>'; // Close div structure
    

    return $output;
}
add_shortcode('city_based_child_categories', 'city_based_child_categories_shortcode');

function add_custom_js_to_product_category_page() {
    if (is_product_category()) {
        ?>
        <style>
        .category-link.wd-fill[aria-hidden="true"] {
            pointer-events: none;
        }
        body.reloading {
            opacity: 0.5;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        </style>
        <script>
            document.addEventListener("DOMContentLoaded", function () {
                let productGrid = document.querySelector(".products.wd-products");
                if (!productGrid) return; // Exit if not on the correct page

                let images = productGrid.querySelectorAll("img");
                let totalImages = images.length;
                let loadedImages = 0;

                images.forEach((img) => {
                    if (img.complete) {
                        loadedImages++;
                    } else {
                        img.onload = () => {
                            loadedImages++;
                            if (loadedImages === totalImages) {
                                productGrid.classList.add("loaded");
                            }
                        };
                    }
                });

                if (loadedImages === totalImages) {
                    productGrid.classList.add("loaded");
                }

                // Dynamically fix each category box link
                const boxes = document.querySelectorAll(".category-grid-item");
                boxes.forEach(box => {
                    const mainLink = box.querySelector("a.category-image");
                    const overlayLink = box.querySelector("a.category-link");
                    if (mainLink && overlayLink) {
                        overlayLink.setAttribute("href", mainLink.getAttribute("href"));
                    }
                });

                // Force full reload when clicking on category title links
                document.querySelectorAll('.wd-entities-title a').forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation(); // Prevent AJAX interception
                        document.body.classList.add('reloading');
                        window.location.replace(this.href);
                    });
                });

                // Watch for URL changes and force reload
                let currentUrl = window.location.href;
                const observer = new MutationObserver(() => {
                    if (window.location.href !== currentUrl) {
                        window.location.href = window.location.href; // Force full reload
                    }
                });
                observer.observe(document, { childList: true, subtree: true });
            });
        </script>
        <?php
    }
}
add_action('wp_footer', 'add_custom_js_to_product_category_page');





function filter_transport_products_by_city( $query ) {
    // Only modify front-end main queries on product category archive pages.
    if ( is_admin() || ! $query->is_main_query() || ! is_product_category() ) {
        return;
    }

    global $wpdb;

    // Get the current queried category.
    $queried_category = get_queried_object();

    // Get "Transport" parent category object by slug.
    $transport_category = get_term_by( 'slug', 'transport', 'product_cat' );
    if ( ! $transport_category || ! $queried_category ) {
        return;
    }

    $transport_category_id = $transport_category->term_id;

    // Check if we are on the Transport category page or its subcategory.
    $is_transport_page = ( $queried_category->term_id == $transport_category_id || $queried_category->parent == $transport_category_id );
    if ( ! $is_transport_page ) {
        return;
    }

    // Get the selected city from cookies.
    $selected_city = isset( $_COOKIE['selected_city'] ) ? sanitize_text_field( $_COOKIE['selected_city'] ) : '';

    // Retrieve all direct child (sub) category IDs of Transport.
    $child_categories = get_terms( array(
        'taxonomy'   => 'product_cat',
        'parent'     => $transport_category_id,
        'hide_empty' => false,
        'fields'     => 'ids'
    ) );

    // Find which child categories have an association with the selected city.
    $valid_category_ids = array();
    if ( ! empty( $selected_city ) && ! is_wp_error( $child_categories ) && ! empty( $child_categories ) ) {
        $matching_category_ids = $wpdb->get_col( $wpdb->prepare( "
            SELECT term_id FROM {$wpdb->prefix}termmeta
            WHERE meta_key = 'product_category_cities'
              AND meta_value LIKE %s
        ", '%' . $wpdb->esc_like( $selected_city ) . '%' ) );
        // Keep only those child categories that match.
        $valid_category_ids = array_intersect( $child_categories, $matching_category_ids );
    }

    // Compute the "invalid" child categories: those that are children of Transport but not associated with the selected city.
    $invalid_child_categories = array_diff( $child_categories, $valid_category_ids );

    /*
      Build the tax_query:
      
      When at least one child category is valid (associated with the selected city) we want to include products if:
      
      [Global Exclusion] The product is NOT assigned to any invalid child category.
      
      AND then EITHER:
      
      Option A: The product is assigned to at least one valid child category.
      OR
      Option B: The product is directly assigned to the parent category (and not assigned to any child category).
      
      If no valid child exists then we simply require the product be in the parent and not in any child.
    */
    if ( ! empty( $valid_category_ids ) ) {
        // Case A: At least one child category is associated with the selected city.
        $tax_query = array(
            'relation' => 'AND',
            // Exclude any product that is assigned to an invalid child category.
            array(
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $invalid_child_categories,
                'operator' => 'NOT IN',
            ),
            // Then include products that satisfy either Option A or Option B.
            array(
                'relation' => 'OR',
                // Option A: Product in a valid child category.
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $valid_category_ids,
                    'operator' => 'IN',
                ),
                // Option B: Product directly in the parent (and not in any child).
                array(
                    'relation' => 'AND',
                    array(
                        'taxonomy' => 'product_cat',
                        'field'    => 'term_id',
                        'terms'    => array( $transport_category_id ),
                        'operator' => 'IN',
                    ),
                    array(
                        'taxonomy' => 'product_cat',
                        'field'    => 'term_id',
                        'terms'    => $child_categories,
                        'operator' => 'NOT IN',
                    ),
                ),
            ),
        );
    } else {
        // Case B: No child category is associated with the selected city.
        // In this case only show products that are directly in the parent category.
        $tax_query = array(
            'relation' => 'AND',
            array(
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $child_categories,
                'operator' => 'NOT IN',
            ),
            array(
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => array( $transport_category_id ),
                'operator' => 'IN',
            ),
        );
    }

    $query->set( 'tax_query', $tax_query );
}
add_action( 'pre_get_posts', 'filter_transport_products_by_city' );



function filter_elementor_transport_travel_products_by_city( $query ) {
    global $wpdb;

    // Get the selected city from cookies.
    $selected_city = isset( $_COOKIE['selected_city'] ) ? sanitize_text_field( $_COOKIE['selected_city'] ) : '';

    // Retrieve parent categories.
    $transport_category = get_term_by( 'slug', 'transport', 'product_cat' );
    $travel_category    = get_term_by( 'slug', 'travel-guide', 'product_cat' );
    if ( ! $transport_category || ! $travel_category ) {
        return;
    }

    $transport_category_id = $transport_category->term_id;
    $travel_category_id    = $travel_category->term_id;

    // Get the current queried category.
    $queried_category = get_queried_object();

    // Determine if we are in a Transport or Travel archive.
    $is_transport_page = ( $queried_category->term_id == $transport_category_id || $queried_category->parent == $transport_category_id );
    $is_travel_page    = ( $queried_category->term_id == $travel_category_id    || $queried_category->parent == $travel_category_id );
    if ( ! $is_transport_page && ! $is_travel_page ) {
        return;
    }
    // Use the correct parent category.
    $parent_category_id = $is_transport_page ? $transport_category_id : $travel_category_id;

    // Retrieve all direct child category IDs for the chosen parent.
    $child_categories = get_terms( array(
        'taxonomy'   => 'product_cat',
        'parent'     => $parent_category_id,
        'hide_empty' => false,
        'fields'     => 'ids'
    ) );

    // Determine which child categories have the selected city association.
    $valid_category_ids = array();
    if ( ! empty( $selected_city ) && ! is_wp_error( $child_categories ) && ! empty( $child_categories ) ) {
        $matching_category_ids = $wpdb->get_col( $wpdb->prepare( "
            SELECT term_id FROM {$wpdb->prefix}termmeta
            WHERE meta_key = 'product_category_cities'
              AND meta_value LIKE %s
        ", '%' . $wpdb->esc_like( $selected_city ) . '%' ) );
        $valid_category_ids = array_intersect( $child_categories, $matching_category_ids );
    }
    $invalid_child_categories = array_diff( $child_categories, $valid_category_ids );

    if ( ! empty( $valid_category_ids ) ) {
        // Case A: Some child categories are associated with the selected city.
        $tax_query = array(
            'relation' => 'AND',
            // Exclude products in any invalid child category.
            array(
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $invalid_child_categories,
                'operator' => 'NOT IN',
            ),
            // Then include products that are either in a valid child category or directly in the parent.
            array(
                'relation' => 'OR',
                // Products in a valid child category.
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $valid_category_ids,
                    'operator' => 'IN',
                ),
                // Products directly in the parent category.
                array(
                    'relation' => 'AND',
                    array(
                        'taxonomy' => 'product_cat',
                        'field'    => 'term_id',
                        'terms'    => array( $parent_category_id ),
                        'operator' => 'IN',
                    ),
                    array(
                        'taxonomy' => 'product_cat',
                        'field'    => 'term_id',
                        'terms'    => $child_categories,
                        'operator' => 'NOT IN',
                    ),
                ),
            ),
        );
    } else {
        // Case B: No child category associated with the selected city.
        $tax_query = array(
            'relation' => 'AND',
            array(
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => $child_categories,
                'operator' => 'NOT IN',
            ),
            array(
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => array( $parent_category_id ),
                'operator' => 'IN',
            ),
        );
    }

    $query->set( 'tax_query', $tax_query );
}
add_action( 'elementor/query/products', 'filter_elementor_transport_travel_products_by_city' );

