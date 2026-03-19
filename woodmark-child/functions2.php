<?php
// Add "Position" column to product categories admin list
add_filter('manage_edit-product_cat_columns', function ($columns) {
    $columns['term_order'] = __('Position', 'woocommerce');
    return $columns;
});

// Populate the "Position" column
add_filter('manage_product_cat_custom_column', function ($out, $column, $term_id) {
    if ($column === 'term_order') {
        $term = get_term($term_id, 'product_cat');
        return esc_html($term->term_order);
    }
    return $out;
}, 10, 3);

// Make the "Position" column sortable
add_filter('manage_edit-product_cat_sortable_columns', function ($sortable_columns) {
    $sortable_columns['term_order'] = 'term_order';
    return $sortable_columns;
});

// Add the "Position" field when adding a category
add_action('product_cat_add_form_fields', function () {
    ?>
    <div class="form-field">
        <label for="cat_position"><?php _e('Position (Menu Order)', 'woocommerce'); ?></label>
        <input type="number" name="cat_position" id="cat_position" value="9999" />
        <p class="description">Enter a number to set the position of the category. Defaults to 9999 if left empty.</p>
    </div>
    <?php
});

// Show the "Position" field when editing a category
add_action('product_cat_edit_form_fields', function ($term) {
    $term_data = get_term($term->term_id);
    $menu_order = $term_data->term_order;
    ?>
    <tr class="form-field">
        <th scope="row"><label for="cat_position"><?php _e('Position (Menu Order)', 'woocommerce'); ?></label></th>
        <td>
            <input type="number" name="cat_position" id="cat_position" value="<?php echo esc_attr($menu_order); ?>" />
            <p class="description">Introduceți un număr pentru a seta poziția categoriei. Categoriile fără poziție vor fi afișate automat după categoriile poziționate.</p>
        </td>
    </tr>
    <?php
});
?>


<?php
// Save the custom position as menu_order
add_action('create_product_cat', 'save_product_cat_position');
add_action('edited_product_cat', 'save_product_cat_position');

function save_product_cat_position($term_id) {
    if (isset($_POST['cat_position'])) {
        global $wpdb;
        $position = intval($_POST['cat_position']); // If blank, will save 0
        $wpdb->update(
            $wpdb->terms,
            ['term_order' => $position],
            ['term_id' => $term_id]
        );
    }
}

// Ensure product categories with position appear first, others after
add_filter('get_terms_args', function ($args, $taxonomies) {
    if (!empty($taxonomies) && in_array('product_cat', $taxonomies)) {
        if (isset($_GET['orderby']) && $_GET['orderby'] === 'term_order') {
            $args['orderby'] = 'menu_order';
            $args['order'] = isset($_GET['order']) ? $_GET['order'] : 'ASC';
        } else {
            $args['orderby'] = 'menu_order';
            $args['order'] = 'ASC';
        }
    }
    return $args;
}, 20, 2);

add_filter('get_terms', function ($terms, $taxonomies, $args, $term_query) {
    if (is_admin()) {
        return $terms;
        $screen = get_current_screen();

        // Don't apply on product edit/add screens
        if ($screen && $screen->post_type === 'product' && in_array($screen->base, ['post', 'edit'])) {
            return $terms;
        }
    }

    if (!empty($taxonomies) && in_array('product_cat', (array) $taxonomies)) {
        // Apply sorting only when explicitly requested or always
        $is_sorting_by_position = isset($_GET['orderby']) && $_GET['orderby'] === 'term_order';

        if (!isset($_GET['orderby']) || $is_sorting_by_position) {
            $with_position = [];
            $without_position = [];

            foreach ($terms as $term) {
                if (!is_object($term) || !property_exists($term, 'term_order')) {
                    continue;
                }

                if (!empty($term->term_order)) {
                    $with_position[] = $term;
                } else {
                    $without_position[] = $term;
                }
            }

            // Always push 0 to the end
            usort($with_position, function ($a, $b) {
                $a_order = ($a->term_order == 0) ? PHP_INT_MAX : $a->term_order;
                $b_order = ($b->term_order == 0) ? PHP_INT_MAX : $b->term_order;
                return $a_order <=> $b_order;
            });

            $terms = array_merge($with_position, $without_position);
        }
    }

    return $terms;
}, 20, 4);



add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'woocommerce_page_wc-orders') return;

    wp_add_inline_script('jquery-core', "
        jQuery(document).ready(function($) {
            const tableWrapper = $('.wrap .wp-list-table').closest('.wrap');
            if (!tableWrapper.length) return;

            // Create a scroll wrapper div if not exists
            if ($('#orders-scroll-wrapper').length === 0) {
                const scrollDiv = $('<div id=\"orders-scroll-wrapper\" style=\"overflow-x:auto; max-width:100%;\"></div>');
                $('.wp-list-table').wrap(scrollDiv);
            }
        });
    ");
});

// add_action('admin_head', function () {
//     global $pagenow;
//     if (
//         !is_admin() ||
//         !isset($_GET['page'], $_GET['action'], $_GET['id']) ||
//         $_GET['page'] !== 'wc-orders' ||
//         $_GET['action'] !== 'edit'
//     ) {
//         return;
//     }
//     echo '<style>
//         /* Hide the "Date created" section in HPOS */
//         .woocommerce-order-date .components-base-control {
//             display: none !important;
//         }

//         /* Backup: hide any field labeled "Date created:" */
//         label[for="order_date"],
//         input[name="order_date"],
//         input[name="order_date_hour"],
//         input[name="order_date_minute"],
//         input[name="order_date_second"] {
//             display: none !important;
//         }
//     </style>';
// });
add_action('wp_enqueue_scripts', function () {
    // You can replace the URL with your preferred Font Awesome version if needed
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css', [], '6.7.2');
});
add_action('admin_footer', function () {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const firstField = document.querySelector('p.form-field.form-field-wide');
        if (firstField) {
            firstField.remove();
        }
    });
    </script>
    <?php
});

add_action('save_post_product', function ($post_id) {
    if (!is_admin()) return;

    if (
        isset($_REQUEST['action']) && $_REQUEST['action'] === 'edit' &&
        isset($_REQUEST['post_type']) && $_REQUEST['post_type'] === 'product' &&
        isset($_REQUEST['tax_input']['product_provider']) &&
        is_array($_REQUEST['tax_input']['product_provider'])
    ) {
        $new_providers = array_filter($_REQUEST['tax_input']['product_provider']);

        // Only replace if user selected one or more providers
        if (!empty($new_providers)) {
            wp_set_object_terms($post_id, $new_providers, 'product_provider', false); // false = replace
        }
    }
}, 20);

function vogo_show_provider_inline_in_account_order_details() {
    add_filter('woocommerce_order_item_name', function($name, $item, $is_visible) {
        // Only affect frontend My Account > Order details, not emails
        if (!is_admin() && !isset($GLOBALS['email'])) {
            $product_id = $item->get_product_id();
            $terms = get_the_terms($product_id, 'product_provider');

            if (!empty($terms) && !is_wp_error($terms)) {
                $provider_names = implode(', ', wp_list_pluck($terms, 'name'));
                $name .= '<br><small><strong>Furnizor:</strong> ' . esc_html($provider_names) . '</small>';
            }
        }

        return $name;
    }, 10, 3);
}
add_action('init', 'vogo_show_provider_inline_in_account_order_details');

function vogo_show_product_providers_in_account_order_details($order) {
    echo '<h3>Furnizori de Produse</h3>';
    echo '<table style="width:100%; border-collapse: collapse;">';
    echo '<thead><tr><th style="border:1px solid #ccc;padding:6px;">Produs</th><th style="border:1px solid #ccc;padding:6px;">Furnizor(s)</th></tr></thead><tbody>';

    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if (!$product) continue;

        $terms = get_the_terms($product->get_id(), 'product_provider');
        $provider_names = !empty($terms) && !is_wp_error($terms) ? implode(', ', wp_list_pluck($terms, 'name')) : '—';

        echo '<tr>';
        echo '<td style="border:1px solid #ccc;padding:6px;">' . esc_html($product->get_name()) . '</td>';
        echo '<td style="border:1px solid #ccc;padding:6px;">' . esc_html($provider_names) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}
add_action('woocommerce_order_details_after_order_table', 'vogo_show_product_providers_in_account_order_details');

add_action('woocommerce_before_thankyou', function ($order_id) {
    do_shortcode('[custom_slider]');
}, 10, 1);

add_action('wp_footer', function () {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const playBtn = document.querySelector('.radio-play-pause');
        // Secondary play button (mirrors the main one)
        const altPlayBtn = document.querySelector('.vogo-audio-button');

        // SVGs for play/pause
        const playSVG = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M73 39c-14.8-9.1-33.4-9.4-48.5-.9S0 62.6 0 80L0 432c0 17.4 9.4 33.4 24.5 41.9s33.7 8.1 48.5-.9L361 297c14.3-8.7 23-24.2 23-41s-8.7-32.2-23-41L73 39z"/></svg>`;
        const pauseSVG = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path d="M48 64C21.5 64 0 85.5 0 112L0 400c0 26.5 21.5 48 48 48l32 0c26.5 0 48-21.5 48-48l0-288c0-26.5-21.5-48-48-48L48 64zm192 0c-26.5 0-48 21.5-48 48l0 288c0 26.5 21.5 48 48 48l32 0c26.5 0 48-21.5 48-48l0-288c0-26.5-21.5-48-48-48l-32 0z"/></svg>`;

        // Ensure an icon is present and initialize it in the paused state
        if (altPlayBtn) {
            // Initial state: show playSVG (paused)
            altPlayBtn.innerHTML = playSVG;
            altPlayBtn.querySelector('svg')?.setAttribute('style', 'fill: white; width: 16px; height: 16px;');
        }

        const volumeSlider = document.querySelector('.volume-slider');
        const volumeBar = document.querySelector('.volume-slider-bar');
        const audio = document.querySelector('.radio-player audio');

        const wasPlaying = localStorage.getItem('radio-playing') === '1';
        const savedVolume = localStorage.getItem('radio-volume');

        // Restore volume
        if (savedVolume !== null && audio) {
            audio.volume = parseFloat(savedVolume);
            updateVolumeUI(audio.volume);
        }

        // Restore play state using improved logic to ensure stream/handlers are ready
        if (wasPlaying && playBtn) {
            const maxAttempts = 30;
            let attempts = 0;

            const interval = setInterval(() => {
                attempts++;

                // Wait for the native script to inject .src or initialize handlers
                const hasSrc = audio.src && audio.src.trim() !== '';

                if (hasSrc || attempts > 5) {
                    playBtn.click(); // trigger native logic
                    clearInterval(interval);
                }

                if (attempts >= maxAttempts) {
                    clearInterval(interval);
                }
            }, 300);
        }

        // Ensure play is retried when audio is ready
        audio?.addEventListener('canplay', () => {
            if (wasPlaying && audio.paused) {
                playBtn.click();
            }
        });

        let isNavigatingAway = false;
        window.addEventListener('beforeunload', function () {
            isNavigatingAway = true;
        });

        // Track real playback state using audio events
        audio?.addEventListener('play', () => {
            localStorage.setItem('radio-playing', '1');
            localStorage.setItem('radio-src', audio.src);
            if (altPlayBtn) {
                // Show pauseSVG when playing
                altPlayBtn.innerHTML = pauseSVG;
                altPlayBtn.querySelector('svg')?.setAttribute('style', 'fill: white; width: 20px; height: 20px;');
            }
        });

        audio?.addEventListener('pause', () => {
            if (!isNavigatingAway) {
                localStorage.setItem('radio-playing', '0');
                if (altPlayBtn) {
                    // Show playSVG when paused
                    altPlayBtn.innerHTML = playSVG;
                    altPlayBtn.querySelector('svg')?.setAttribute('style', 'fill: white; width: 20px; height: 20px;');
                }
            }
        });

        // Keep a second play/pause control in sync with the primary button
        if (altPlayBtn && playBtn) {
            altPlayBtn.addEventListener('click', (e) => {
                e.preventDefault(); // stop the anchor from navigating
                playBtn.click();
            });
        }

        // Volume control
        volumeSlider?.addEventListener('click', function (e) {
            if (!audio) return;
            const rect = this.getBoundingClientRect();
            const percent = (e.clientX - rect.left) / rect.width;
            const volume = Math.min(1, Math.max(0, percent));
            audio.volume = volume;
            localStorage.setItem('radio-volume', volume.toFixed(2));
            updateVolumeUI(volume);
        });

        function updateVolumeUI(vol) {
            const percent = Math.round(vol * 100) + '%';
            volumeBar?.style.setProperty('--radio-player-volume-slider', percent);
            volumeSlider?.setAttribute('aria-valuenow', Math.round(vol * 100));
            volumeSlider?.setAttribute('aria-valuetext', percent);
        }
    });

    // --- Elementor Mini Cart Quantity Buttons Injection ---
    function observeMiniCart() {
        const observer = new MutationObserver(() => {
            const cartOpen = document.querySelector('.elementor-menu-cart__main[aria-hidden="false"]');
            const hasQuantities = document.querySelectorAll('.elementor-menu-cart__product .product-quantity').length > 0;
            if (cartOpen && hasQuantities) {
         //       console.log("Mini cart is open and quantities found.");
                injectMiniCartButtons();
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }
    observeMiniCart();

    // Ensure buttons are re-injected after WooCommerce finishes refreshing the mini cart
    jQuery(document.body).on('updated_wc_div', function () {
    //  console.log('WooCommerce updated_wc_div triggered – reinjecting mini cart buttons');
      injectMiniCartButtons();
    });

    // Re-inject buttons when a product is added via AJAX
    jQuery(document.body).on('added_to_cart', function () {
    //  console.log('WooCommerce added_to_cart triggered – reinjecting mini cart buttons');
      setTimeout(() => {
        injectMiniCartButtons();
      }, 300);
    });

    // Ensure mini cart quantity buttons persist after WooCommerce fragment updates
    jQuery(document.body).on('wc_fragments_loaded', function () {
   //   console.log('wc_fragments_loaded triggered — scheduling button injection');
      requestAnimationFrame(() => {
        setTimeout(() => {
          const cartOpen = document.querySelector('.elementor-menu-cart__main[aria-hidden="false"]');
          const products = document.querySelectorAll('.elementor-menu-cart__product');
          if (cartOpen && products.length) {
        //    console.log('Cart DOM is ready — injecting buttons');
            injectMiniCartButtons();
          } else {
         //   console.warn('Cart DOM not ready, will retry...');
            setTimeout(() => injectMiniCartButtons(), 300);
          }
        }, 150);
      });
    });

    // IMPROVED: Reinjection after WooCommerce fragment refresh, waiting for DOM to stabilize and buttons to be missing
    jQuery(document.body).on('wc_fragment_refresh', function () {
        console.log('wc_fragment_refresh triggered — watching for buttons to be gone');
        const interval = setInterval(() => {
            const containers = document.querySelectorAll('.elementor-menu-cart__product .product-quantity');
            const anyMissingButtons = [...containers].some(c => !c.querySelector('.vogo-qty-number'));
            if (anyMissingButtons) {
             //   console.log('Some cart items are missing buttons — reinjecting now');
                injectMiniCartButtons();
                clearInterval(interval);
            }
        }, 300);
    });

    function injectMiniCartButtons() {
        console.log("injectMiniCartButtons called");
        const qtyElements = document.querySelectorAll('.elementor-menu-cart__product .product-quantity');
        console.log("Found .product-quantity elements:", qtyElements.length);
        qtyElements.forEach(container => {
            const alreadyHasButtons = container.querySelector('.vogo-qty-number');
            if (alreadyHasButtons) {
            //    console.log("Buttons already present, skipping:", container);
                return;
            }
            const textContent = container.textContent.trim();
            const qtyMatch = textContent.match(/\d+/);
            const qty = qtyMatch ? parseInt(qtyMatch[0]) : 1;
            const cartItem = container.closest('.elementor-menu-cart__product');
            const cartKeyEl = cartItem?.querySelector('[data-cart_item_key]');
            const cartKey = cartKeyEl?.dataset?.cart_item_key;
            if (!cartKey) {
             //   console.warn("Cart key not found for", container);
                return;
            }
            container.innerHTML = `
                <button class="vogo-qty-minus" data-cart-key="${cartKey}" style="margin-right:4px;">−</button>
                <span class="vogo-qty-number">${qty}</span>
                <button class="vogo-qty-plus" data-cart-key="${cartKey}" style="margin-left:4px;">+</button>
            `;
        });
    }
    // --- End Elementor Mini Cart Quantity Buttons Injection ---

    // --- Begin AJAX mini cart quantity update ---
    document.addEventListener('click', function (e) {
      if (e.target.classList.contains('vogo-qty-plus') || e.target.classList.contains('vogo-qty-minus')) {
        const wrapper = e.target.closest('.vogo-qty-wrapper') || e.target.closest('.quantity');
        const cartKey = e.target.dataset.cartKey || wrapper?.dataset?.cartKey;
        const qtyNumber = wrapper?.querySelector('.vogo-qty-number');
        if (!cartKey || !qtyNumber) return;
        const currentQty = parseInt(qtyNumber.textContent);
        const newQty = e.target.classList.contains('vogo-qty-plus') ? currentQty + 1 : currentQty - 1;
        if (newQty < 1) return;

        // Update quantity immediately
        if (qtyNumber) {
          qtyNumber.textContent = newQty;
        }

        // Show a spinner and disable button
        e.target.disabled = true;
        const originalContent = e.target.innerHTML;
        e.target.innerHTML = '⏳'; // Or use a spinner SVG/icon

        fetch(wc_cart_params.ajax_url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `action=woocommerce_update_cart_item_quantity&cart_item_key=${cartKey}&quantity=${newQty}`
        }).then(() => {
          e.target.innerHTML = originalContent;
          e.target.disabled = false;

          jQuery(document.body).trigger('wc_fragment_refresh');
        });
      }
    });
    // --- End AJAX mini cart quantity update ---

     function startMiniCartMutationObserver() {
            const cartContent = document.querySelector('.widget_shopping_cart_content');
            if (!cartContent) {
                console.warn('Mini cart container not found for observer.');
                return;
            }

            const observer = new MutationObserver((mutationsList) => {
                for (const mutation of mutationsList) {
                    if (mutation.type === 'childList') {
                        console.log('Mini cart content mutated — reinjecting buttons');
                        injectMiniCartButtons();
                        break;
                    }
                }
            });

            observer.observe(cartContent, { childList: true, subtree: true });
        }

        // Wait for the mini cart DOM to exist before starting the observer
        let attempts = 0;
        const maxAttempts = 10;

        const waitForMiniCart = setInterval(() => {
          const cartContent = document.querySelector('.widget_shopping_cart_content');
          if (cartContent) {
            console.log("Mini cart found. Starting MutationObserver.");
            startMiniCartMutationObserver();
            clearInterval(waitForMiniCart);
          } else {
            attempts++;
            if (attempts >= maxAttempts) {
              console.warn("Mini cart not found for observer after multiple attempts.");
              clearInterval(waitForMiniCart);
            }
          }
        }, 500);
    </script>
    <?php
});


// 2. Add custom fields to the ADD form
function product_provider_add_form_fields() {
    ?>
    <div class="form-field">
        <label for="camera_broadcast_url">Camera Broadcast URL</label>
        <input type="url" name="camera_broadcast_url" id="camera_broadcast_url">
    </div>
    <div class="form-field">
        <label for="zoom_live_call_link">Zoom Live Call Link</label>
        <input type="url" name="zoom_live_call_link" id="zoom_live_call_link">
    </div>
    <div class="form-field">
        <label for="whatsapp_contact_url">WhatsApp Contact Link</label>
        <input type="url" name="whatsapp_contact_url" id="whatsapp_contact_url">
    </div>
    <?php
}
add_action('product_provider_add_form_fields', 'product_provider_add_form_fields');

// 3. Add custom fields to the EDIT form
function product_provider_edit_form_fields($term) {
    $camera = get_term_meta($term->term_id, 'camera_broadcast_url', true);
    $zoom = get_term_meta($term->term_id, 'zoom_live_call_link', true);
    $whatsapp = get_term_meta($term->term_id, 'whatsapp_contact_url', true);
    ?>
    <tr class="form-field">
        <th><label for="camera_broadcast_url">Camera Broadcast URL</label></th>
        <td><input type="url" name="camera_broadcast_url" value="<?php echo esc_attr($camera); ?>"></td>
    </tr>
    <tr class="form-field">
        <th><label for="zoom_live_call_link">Zoom Live Call Link</label></th>
        <td><input type="url" name="zoom_live_call_link" value="<?php echo esc_attr($zoom); ?>"></td>
    </tr>
    <tr class="form-field">
        <th><label for="whatsapp_contact_url">WhatsApp Contact Link</label></th>
        <td><input type="url" name="whatsapp_contact_url" value="<?php echo esc_attr($whatsapp); ?>"></td>
    </tr>

    <?php
}
add_action('product_provider_edit_form_fields', 'product_provider_edit_form_fields');

// 4. Save the custom field values on add/edit
function save_product_provider_term_meta($term_id) {
    foreach (['camera_broadcast_url', 'zoom_live_call_link', 'whatsapp_contact_url'] as $field) {
        if (isset($_POST[$field])) {
            update_term_meta($term_id, $field, esc_url_raw($_POST[$field]));
        }
    }
}
add_action('created_product_provider', 'save_product_provider_term_meta');
add_action('edited_product_provider', 'save_product_provider_term_meta');

// 5. Show the links on product detail page (WooCommerce)
function show_product_provider_links_on_product_page() {
    global $post;

    $product_camera = get_post_meta($post->ID, 'camera_broadcast_url', true);
    $product_zoom = get_post_meta($post->ID, 'zoom_live_call_link', true);
    $product_whatsapp = get_post_meta($post->ID, 'whatsapp_contact_url', true);

    $terms = get_the_terms($post->ID, 'product_provider');

    $camera = $product_camera;
    $zoom = $product_zoom;
    $whatsapp = $product_whatsapp;

    if (!$camera || !$zoom || !$whatsapp) {
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                if (!$camera) {
                    $camera = get_term_meta($term->term_id, 'camera_broadcast_url', true);
                }
                if (!$zoom) {
                    $zoom = get_term_meta($term->term_id, 'zoom_live_call_link', true);
                }
                if (!$whatsapp) {
                    $whatsapp = get_term_meta($term->term_id, 'whatsapp_contact_url', true);
                }
            }
        }
    }

    if ($camera || $zoom || $whatsapp) {
        echo '<div class="product-provider-links" style="margin-top:20px;">';
        echo '<h4>Live Communication Options:</h4>';
        if ($camera) {
            echo '<p><a href="' . esc_url($camera) . '" target="_blank">📹 View Camera Feed</a></p>';
        }
        if ($zoom) {
            echo '<p><a href="' . esc_url($zoom) . '" target="_blank">🎥 Join Zoom Call</a></p>';
        }
        if ($whatsapp) {
            echo '<p><a href="' . esc_url($whatsapp) . '" target="_blank">💬 Chat on WhatsApp</a></p>';
        }
        echo '</div>';
    }
}
add_action('woocommerce_single_product_summary', 'show_product_provider_links_on_product_page', 25);

// Shortcode to display product_provider communication links
// #Tag Product Provider Links
function shortcode_product_provider_links() {
    global $post;

    if (!is_singular('product')) return '';

    $product_camera = get_post_meta($post->ID, 'camera_broadcast_url', true);
    $product_zoom = get_post_meta($post->ID, 'zoom_live_call_link', true);
    $product_whatsapp = get_post_meta($post->ID, 'whatsapp_contact_url', true);

    $camera = $product_camera;
    $zoom = $product_zoom;
    $whatsapp = $product_whatsapp;

    if (!$camera || !$zoom || !$whatsapp) {
        $terms = get_the_terms($post->ID, 'product_provider');
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                if (!$camera) {
                    $camera = get_term_meta($term->term_id, 'camera_broadcast_url', true);
                }
                if (!$zoom) {
                    $zoom = get_term_meta($term->term_id, 'zoom_live_call_link', true);
                }
                if (!$whatsapp) {
                    $whatsapp = get_term_meta($term->term_id, 'whatsapp_contact_url', true);
                }
            }
        }
    }

    // Always render all three buttons, with links or SweetAlert fallback
    $output = '<div class="product-provider-links" style="display:flex; justify-content:space-between; width:100%">';
    $button_style = 'padding:10px 10px;background: #e0e0e0; color:#000; text-decoration:none; border-radius:3px; display:flex;';
    // Help popup button always visible (especially for mobile users)
    // $output .= '<a href="#" class="small-screen-back-to-category" title="Ajutor"><img src="https://test07.vogo.family/wp-content/uploads/2025/06/window-close.256x256.png" style="width:30px; height:30px" class="product-close-button"></a>';
    $output .= '<a href="#" class="small-screen-back-to-category" title="Ajutor"><span class="material-icons notranslate" style="    background: #e0e0e0;height: 42px; width: 42px;display: flex;justify-content: center;font-weight: bold;color: green;">close</span></a>';
    $output .= '<div style="display:flex; gap:3px;" class="product-provider-links-buttons">';

        $default_camera = 'https://www.youtube.com/watch?v=klfxQuXT66s';
        if (!empty($camera)) {
            $output .= '<a href="' . esc_url($camera) . '" target="_blank" class="provider-link-custom" style="' . $button_style . '" title="Vizualizează camera live"><span class="material-icons notranslate" style="color:green;">live_tv</span></a>';
        } else {
            /*
            $output .= '<button type="button" onclick="Swal.fire({ html: `<div style=&quot;display: flex; 
            align-items: center; gap: 10px;&quot;><span class=&quot;material-icons notranslate&quot; style=&quot;font-size: 40px; 
            color: green;&quot;>info</span><div>
            Informația este in curs de actualizare la vendor!</div></div>` })" style="' . 
            $button_style . 
            '" title="Vizualizează camera live"><span class="material-icons notranslate" style="color:green;">live_tv</span></button>';
            */
            $output .= '<a href="' . esc_url($default_camera) . '" target="_blank" class="provider-link-custom" style="' . $button_style . '" title="Vizualizează camera live"><span class="material-icons notranslate" style="color:green;">live_tv</span></a>';
        }



    $default_videoconference = 'https://meet.google.com/wqk-pwmg-kox';
    if ($zoom) {
        $output .= '<a href="' . esc_url($zoom) . '" target="_blank" class="provider-link-custom" style="' . $button_style . '" title="Intră în apelul Zoom"><span class="material-icons notranslate" style="color:green;">videocam</span></a>';
    } else {
        /*
        $output .= '<button type="button" onclick="Swal.fire({ html: `<div style=&quot;display: flex; align-items: center; 
        gap: 10px;&quot;><span class=&quot;material-icons notranslate&quot; 
        style=&quot;font-size: 40px; color: green;&quot;>info</span><div>
        Informația este in curs de actualizare la vendor.
        </div></div>` })" style="' . $button_style . '" title="Intră în apelul Zoom"><span class="material-icons notranslate" style="color:green;">videocam</span></button>';
        */
        $output .= '<a href="' . esc_url($default_videoconference) . 
        '" target="_blank" class="provider-link-custom" style="' 
        . $button_style . 
        '" title="Intră în videoconferință"><span class="material-icons notranslate" style="color:green;">videocam</span></a>';
    }

$default_whatsapp = 'https://wa.me/40786854023?text=' . rawurlencode('Hello, I contact you from vogo.family. I need info related to ');
if (!empty($whatsapp)) {
    $output .= '<a href="' . esc_url($whatsapp) . '" target="_blank" class="provider-link-custom" style="' . $button_style . '" title="Chat pe WhatsApp"><span style="color:green;" class="notranslate material-icons no-boder">chat</span></a>';
} else {
    /*
    $output .= '<button type="button" onclick="Swal.fire({ html: `<div style=&quot;display: flex; align-items: center; gap: 10px;&quot;><span class=&quot;material-icons notranslate&quot; style=&quot;font-size: 40px; color: green;&quot;>info</span><div>
    Informația este in curs de actualizare la vendor.</div>
    </div>` })" style="' . $button_style . '" title="Chat pe WhatsApp"><span style="color:green;" class="notranslate material-icons">chat</span></button>';
    */
    $output .= '<a href="' . esc_url($default_whatsapp) . '" target="_blank" class="provider-link-custom" style="' . $button_style . '" title="Chat pe WhatsApp"><span style="color:green;" class="notranslate material-icons no-boder">chat</span></a>';
}


    $output .= '<button type="button" id="vogo-fav-btn" class="provider-link-custom" style="' . $button_style . '" title="Adaugă la favorite"><span class="material-icons notranslate" style="color:green;">favorite</span></button>';
    $output .= '<a href="https://test07.vogo.family/recommend-new-service/" target="_blank" class="provider-link-custom" style="' . $button_style . '" title="Recomandă un nou serviciu"><span class="material-icons notranslate no-boder" style="color:green;">check_circle</span></a>';

   
    $output .= '<button type="button" class="provider-link-custom nopopup" style="' . $button_style . '" title="Deschide explicațiile pentru pictograme" onclick="Swal.fire({title: &quot;Ce înseamnă aceste pictograme?&quot;, html: `<div style=&quot;text-align: left;&quot;><p class=&quot;pop-paragraph&quot;><span class=&quot;material-icons notranslate&quot; style=&quot;color:green;&quot;>live_tv</span><span> Vizualizează transmisiunea live</span></p><p class=&quot;pop-paragraph&quot;><span class=&quot;material-icons notranslate&quot; style=&quot;color:green;&quot;>videocam</span> <span>Participă la o întâlnire video</span></p><p class=&quot;pop-paragraph&quot;><span class=&quot;material-icons notranslate&quot; style=&quot;color:green;&quot;>chat</span><span> Contactează direct prin chat</span></p><p class=&quot;pop-paragraph&quot;><span class=&quot;material-icons notranslate&quot; style=&quot;color:green;&quot;>favorite</span><span> Salvează pentru mai târziu</span></p><p class=&quot;pop-paragraph&quot;><span class=&quot;material-icons notranslate&quot; style=&quot;color:green;&quot;>check_circle</span><span> Sugerează un serviciu nou<span></p></div>`, confirmButtonText: &quot;Închide&quot;})"><span style="color:green;" class="notranslate material-icons">help</span></button>';

     $output .= '</div>';
    $output .= '</div>';
    return $output;
}
// Ensure SweetAlert2 is loaded in the footer (if not already loaded)
add_action('wp_footer', function () {
    ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php
}, 20);
add_shortcode('product_provider_links', 'shortcode_product_provider_links');

// Add SweetAlert2 favorite button logic in footer
add_action('wp_footer', function () {
    if (!is_product()) return;
    ?>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const favBtn = document.getElementById("vogo-fav-btn");
        const isLoggedIn = <?php echo is_user_logged_in() ? 'true' : 'false'; ?>;

        if (favBtn) {
            favBtn.addEventListener("click", function () {
                if (!isLoggedIn) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Autentificare necesară',
                        text: 'Trebuie să fii autentificat pentru a folosi această funcție.',
                        confirmButtonText: 'OK'
                    });
                } else {
                    document.querySelector('.add-to-reference')?.click();
                }
            });
        }
    });
    </script>
    <?php
});

// 1. Define your list of predefined interest tags
function my_custom_get_interests_list() {
    return array(
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
    );
}

// 2. Show dropdown on bbPress New Topic form
function my_custom_interest_dropdown_on_topic_form() {

    $interests = my_custom_get_interests_list();
    ?>
    <p>
        <label for="bbp_interest_tag"><strong>Domeniu de interes:</strong></label><br>
        <select name="bbp_interest_tag" id="bbp_interest_tag" style="width: 300px;">
            <option value="">-- Selectează --</option>
            <?php foreach ( $interests as $interest ) : ?>
                <option value="<?php echo esc_attr( $interest ); ?>"><?php echo esc_html( $interest ); ?></option>
            <?php endforeach; ?>
        </select>
    </p>
    <?php
}
add_action( 'bbp_theme_before_topic_form_tags', 'my_custom_interest_dropdown_on_topic_form' );

// 3. Save the selected interest as a bbPress tag on topic creation
function my_custom_save_interest_tag_on_topic( $topic_id ) {
    if ( isset( $_POST['bbp_interest_tag'] ) && ! empty( $_POST['bbp_interest_tag'] ) ) {
        $interest = sanitize_text_field( $_POST['bbp_interest_tag'] );
        wp_set_post_terms( $topic_id, array( $interest ), 'topic-tag', true );
    }
}
add_action( 'bbp_new_topic', 'my_custom_save_interest_tag_on_topic' );

function vogo_add_create_topic_link_after_forum_title() {
    $forum_id = bbp_get_forum_id();

    // Only show for forums that allow posting (not categories)
    if ( ! bbp_is_forum_category( $forum_id ) ) {
        echo '<div class="vogo-create-topic-link" style="margin-top: 5px;">';

        if ( is_user_logged_in() ) {
            echo '<a href="' . esc_url( bbp_get_forum_permalink( $forum_id ) ) . '#new-post">📝 Creează un subiect nou</a>';
        } else {
            echo '<a href="' . esc_url( wp_login_url( bbp_get_forum_permalink( $forum_id ) ) ) . '">🔒 Autentifică-te pentru a posta</a>';
        }

        echo '</div>';
    }
}
function vogo_show_topic_tags_above_content() {
    if ( bbp_is_single_topic() ) {
        $tags = get_the_terms( get_the_ID(), 'topic-tag' );

        if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
            echo '<div class="vogo-topic-tags" style="margin-bottom: 10px;"><strong>Domenii de interes:</strong> ';
            foreach ( $tags as $tag ) {
                echo '<span class="vogo-tag">' . esc_html( $tag->name ) . '</span> ';
            }
            echo '</div>';
        }
    }
}

add_action( 'bbp_theme_before_reply_content', 'vogo_show_topic_tags_above_content' );

// WooCommerce AJAX handler for updating mini cart item quantity
add_action('wp_ajax_woocommerce_update_cart_item_quantity', 'vogo_ajax_update_cart_qty');
add_action('wp_ajax_nopriv_woocommerce_update_cart_item_quantity', 'vogo_ajax_update_cart_qty');

function vogo_ajax_update_cart_qty() {
    if (!isset($_POST['cart_item_key'], $_POST['quantity'])) {
        wp_send_json_error('Missing data');
    }

    $key = sanitize_text_field($_POST['cart_item_key']);
    $qty = max(1, intval($_POST['quantity'])); // minimum quantity 1

    if (WC()->cart->set_quantity($key, $qty, true)) {
        WC()->cart->calculate_totals();
        wp_send_json_success('Quantity updated');
    }

    wp_send_json_error('Failed to update quantity');
}

// Fallback definition for wc_cart_params to prevent undefined JS errors
add_action('wp_footer', function () {
    if (!wp_script_is('wc-cart-fragments', 'enqueued')) return;
    ?>
    <script>
        if (typeof wc_cart_params === 'undefined') {
            var wc_cart_params = {
                ajax_url: "<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
            };
        }
    </script>
    <?php
}, 9);
// Ensure WooCommerce's wc-cart-fragments script is always enqueued so the mini cart and subtotal update properly after AJAX actions.
add_action('wp_enqueue_scripts', function () {
    if (function_exists('WC') && (is_cart() || is_checkout() || is_product() || is_shop())) {
        wp_enqueue_script('wc-cart-fragments');
    }
});



// Show custom provider fields in product edit screen
add_action('woocommerce_product_options_general_product_data', function () {
    echo '<div class="options_group">';
    
    woocommerce_wp_text_input([
        'id' => 'camera_broadcast_url',
        'label' => __('Camera Broadcast URL', 'woocommerce'),
        'desc_tip' => true,
        'description' => __('Link to a live camera feed.', 'woocommerce'),
        'type' => 'url'
    ]);

    woocommerce_wp_text_input([
        'id' => 'zoom_live_call_link',
        'label' => __('Zoom Live Call Link', 'woocommerce'),
        'desc_tip' => true,
        'description' => __('Zoom call link for this product.', 'woocommerce'),
        'type' => 'url'
    ]);

    woocommerce_wp_text_input([
        'id' => 'whatsapp_contact_url',
        'label' => __('WhatsApp Contact Link', 'woocommerce'),
        'desc_tip' => true,
        'description' => __('WhatsApp link for this product.', 'woocommerce'),
        'type' => 'url'
    ]);

    echo '</div>';
});

// Save custom provider fields from product edit screen
add_action('woocommerce_process_product_meta', function ($post_id) {
    foreach (['camera_broadcast_url', 'zoom_live_call_link', 'whatsapp_contact_url'] as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, esc_url_raw($_POST[$field]));
        }
    }
});

// --- VOGO_PRODUCT_ACTION Meta Box for Product Edit Screen ---
// Register the meta box
add_action('add_meta_boxes', function () {
    add_meta_box(
        'vogo_product_action_box',
        __('Product Actions (VOGO)', 'vogo'),
        'vogo_render_product_action_box',
        'product',
        'normal',
        'default'
    );
});

// Render the meta box
// TAG VOGO_PRODUCT_ACTION HTML FORM
function vogo_render_product_action_box($post) {
    global $wpdb;
    $actions = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}vogo_product_action
        WHERE ID_PRODUCT = %d
    ", $post->ID));

    echo '<div id="vogo-action-wrapper">';
    wp_nonce_field('vogo_save_actions', 'vogo_actions_nonce');

    // Always show 2 groups: fill with existing actions, or empty if none
    $max_entries = 2;
    for ($i = 0; $i < $max_entries; $i++) {
        $action = $actions[$i] ?? (object)[
            'ID' => '',
            'ACTION_TYPE' => '',
            'ACTION_LABEL' => '',
            'ACTION_DETAILS' => '',
            'ICON_CODE' => '',
            'WHATSAPP_NUMBER' => ''
        ];
        echo '<div class="vogo-action-group" style="margin-bottom:20px;border:1px solid #ccc;padding:10px;">';
        echo '<input type="hidden" name="vogo_action_ids[]" value="' . esc_attr($action->ID) . '">';
        echo '<p><label style="width:100%; max-width:300px;">Type:
            <select name="vogo_action_type[]">
              <option value="WHATSAPP" ' . selected($action->ACTION_TYPE, 'WHATSAPP', false) . '>WHATSAPP</option>
              <option value="LINK" ' . selected($action->ACTION_TYPE, 'LINK', false) . '>LINK</option>
            </select>
        </label></p>';
        echo '<p style="margin-top:10px;"><label style="width:100%; max-width:300px;">Label: <input type="text" name="vogo_action_label[]" value="' . esc_attr($action->ACTION_LABEL) . '" style="width:100%; max-width:300px;"></label></p>';
        echo '<p style="margin-top:10px;"><label style="width:100%; max-width:300px;">Details: <input type="text" name="vogo_action_details[]" value="' . esc_attr($action->ACTION_DETAILS) . '" style="width:100%; max-width:300px;"></label></p>';
        echo '<p style="margin-top:10px;"><label style="width:100%; max-width:300px;">Icon Code: <input type="text" name="vogo_action_icon[]" value="' . esc_attr($action->ICON_CODE) . '" style="width:100%; max-width:300px;"></label></p>';
        echo '<p style="margin-top:10px;"><label style="width:100%; max-width:300px;">WhatsApp Number: <input type="text" name="vogo_action_whatsapp[]" value="' . esc_attr($action->WHATSAPP_NUMBER) . '" style="width:100%; max-width:300px;"></label></p>';
        echo '</div>';
    }
    echo '</div>';
}

// Save the data
// #TAG VOGO_PRODUCT_ACTION Actions
add_action('save_post_product', function ($post_id) {
    if (!isset($_POST['vogo_actions_nonce']) || !wp_verify_nonce($_POST['vogo_actions_nonce'], 'vogo_save_actions')) {
        return;
    }

    global $wpdb;

    $ids = $_POST['vogo_action_ids'] ?? [];
    $types = $_POST['vogo_action_type'] ?? [];
    $labels = $_POST['vogo_action_label'] ?? [];
    $details = $_POST['vogo_action_details'] ?? [];
    $icons = $_POST['vogo_action_icon'] ?? [];
    $whatsapps = $_POST['vogo_action_whatsapp'] ?? [];

    for ($i = 0; $i < count($types); $i++) {
        $data = [
            'ID_PRODUCT' => $post_id,
            'ACTION_TYPE' => sanitize_text_field($types[$i]),
            'ACTION_LABEL' => sanitize_text_field($labels[$i]),
            'ACTION_DETAILS' => sanitize_text_field($details[$i]),
            'ICON_CODE' => sanitize_text_field($icons[$i]),
            'WHATSAPP_NUMBER' => sanitize_text_field($whatsapps[$i]),
        ];

        $id = intval($ids[$i]);
        if ($id > 0) {
            $wpdb->update("{$wpdb->prefix}vogo_product_action", $data, ['ID' => $id]);
        } else {
            $wpdb->insert("{$wpdb->prefix}vogo_product_action", $data);
        }
    }
}, 20);
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css', [], '6.7.2');
});

add_action('wp_footer', function () {
?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    let fixAttempts = 0;
    const maxFixAttempts = 10;

    function fixAllGTranslateDropdowns() {
        const selectors = document.querySelectorAll("select.gt_selector");

        if (!selectors.length) {
            if (++fixAttempts < maxFixAttempts) {
                return setTimeout(fixAllGTranslateDropdowns, 500);
            }
            console.warn("❌ No GTranslate dropdowns found.");
            return;
        }

        selectors.forEach(selector => {
            // Remove from <select>
            selector.classList.remove("notranslate");

            // Apply to options
            Array.from(selector.options).forEach(option => {
                if (option.value === "") {
                    option.classList.remove("notranslate"); // Allow default to be translated
                } else {
                    option.classList.add("notranslate"); // Prevent translation
                }
            });

            // Observer to keep it clean if GTranslate re-adds class
            const observer = new MutationObserver(mutations => {
                mutations.forEach(mutation => {
                    if (
                        mutation.attributeName === "class" &&
                        selector.classList.contains("notranslate")
                    ) {
                        selector.classList.remove("notranslate");
                        console.log("♻️ Re-removed .notranslate from <select>");
                    }
                });
            });

            observer.observe(selector, { attributes: true });

            console.log("✅ Patched GTranslate dropdown:", selector);
        });
    }

    fixAllGTranslateDropdowns();
});
</script>
<?php
});


add_action('wp_footer', 'vogo_validate_review_form_full');
function vogo_validate_review_form_full() {
    if (is_product()) {
        ?>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('commentform');

            if (!form) return;

            form.addEventListener('submit', function (e) {
                const rating = document.getElementById('rating');
                const comment = document.getElementById('comment');
                const author = document.getElementById('author'); // name field
                const email = document.getElementById('email');   // email field

                const ratingValue = parseInt(rating?.value || 0);
                const commentText = comment?.value.trim() || '';
                const authorText = author?.value.trim() || '';
                const emailText = email?.value.trim() || '';

                // Check rating
                if (!ratingValue || ratingValue < 1 || ratingValue > 5) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Eroare',
                        text: 'Te rugăm să selectezi o evaluare (stele).',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                // Check comment
                if (commentText === '') {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Eroare',
                        text: 'Câmpul "Recenzia ta" este obligatoriu.',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                // Only check name/email if fields exist (user is not logged in)
                if (author && authorText === '') {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Eroare',
                        text: 'Te rugăm să introduci numele tău.',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                if (email && emailText === '') {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Eroare',
                        text: 'Te rugăm să introduci adresa ta de email.',
                        confirmButtonText: 'OK'
                    });
                    return;
                }
            });
        });
        </script>
        <?php
    }
}

add_action('wp_footer', 'hide_add_to_cart_if_no_price');
function hide_add_to_cart_if_no_price() {
    if (is_product()) {
        global $product;
        if (!$product->get_price()) {
            ?>
            <style>
                .add-to-cart-section {
                    display: none !important;
                }
            </style>
            <?php
        }
    }
}
add_action('wp_footer', function () {
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // When any SweetAlert2 modal is opened
    document.body.addEventListener('click', function (e) {
        // Delegate click that might trigger Swal
        setTimeout(() => {
            const iconContent = document.querySelector('.swal2-icon-content');
            if (iconContent) {
                if (!iconContent.classList.contains('notranslate')) {
                    iconContent.classList.add('notranslate');
                    console.log("✅ Added 'notranslate' to .swal2-icon-content");
                }
                const nestedFonts = iconContent.querySelectorAll('font');
                nestedFonts.forEach(font => {
                    font.classList.add('notranslate');
                });
            }
        }, 100); // wait for the modal DOM to render
    });
});
</script>
<?php
});

function wrac_reorder_menu_items( $menu_links ) {
    // Save logout link to add at the end
    $logout = $menu_links['customer-logout'];
    unset($menu_links['customer-logout']);

    // Remove custom items to reinsert in correct order
    unset($menu_links['my-requests']);
    unset($menu_links['my-contributions']);

    // Build new array with desired order
    $new_links = [];

    foreach ( $menu_links as $key => $label ) {
        $new_links[ $key ] = $label;

        // After dashboard, insert our custom items
        if ( $key === 'my-articles' ) {
            $new_links['my-requests'] = __( 'Cererile mele', 'woo-requests-contributions' );
            $new_links['my-contributions'] = __( 'Contribuțiile mele', 'woo-requests-contributions' );
        }
    }

    // Re-append logout at the end
    $new_links['customer-logout'] = $logout;

    return $new_links;
}
add_filter( 'woocommerce_account_menu_items', 'wrac_reorder_menu_items', 10 );

function shortcode_product_action_buttons_with_labels() {
    global $wpdb;
    $product_id = get_the_ID();

    $actions = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$wpdb->prefix}vogo_product_action
        WHERE ID_PRODUCT = %d
    ", $product_id));

    // Use the same button style as [vogo_product_add_to_cart] for consistency
    $button_style = 'background:#008001; color:#fff; padding:10px 20px; border:none; border-radius:5px; font-weight:bold; display:flex; align-items:center; gap:8px;';
    $output = '<div class="product-action-buttons-with-labels" style="display:flex; gap:10px; flex-wrap:wrap;">';

    foreach ($actions as $action) {
        $label = esc_html($action->ACTION_LABEL);
        $icon = esc_html($action->ICON_CODE);
        $type = esc_html($action->ACTION_TYPE);
        $details = esc_html($action->ACTION_DETAILS);
        $whatsapp = esc_html($action->WHATSAPP_NUMBER);

      if ($type === 'WHATSAPP' && $whatsapp) {
            $product_name = get_the_title($product_id);
            $product_name = html_entity_decode($product_name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $product_name = preg_replace('/\s*[\x{2013}\x{2014}\-]\s*/u', ' ', $product_name);
            $product_name = trim(preg_replace('/\s+/', ' ', $product_name));
            $message = str_replace('{product name}', $product_name, $details);
            $message = html_entity_decode($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $message = preg_replace('/\s*[\x{2013}\x{2014}\-]\s*/u', ' ', $message);
            $encoded_message = rawurlencode($message);
            $wa_link = "https://wa.me/$whatsapp?text=$encoded_message";

            $output .= '<a href="' . esc_url($wa_link) . '" target="_blank" class="provider-link-custom-product-action" style="' . $button_style . '" title="' . esc_attr($label) . '">';
            if ($icon) {
                $icon_class = preg_match('/^fa[a-z]* /', $icon) ? $icon : 'fa-solid ' . $icon;
                $output .= '<i class="' . esc_attr($icon_class) . '" style="color:white;"></i>';
            }
            $output .= '<span>' . $label . '</span>';
            $output .= '</a>';
        }

        if ($type === 'LINK' && $details) {
            $output .= '<a href="' . esc_url($details) . '" target="_blank" class="provider-link-custom-product-action" style="' . $button_style . '" title="' . esc_attr($label) . '">';
            if ($icon) {
                $icon_class = preg_match('/^fa[a-z]* /', $icon) ? $icon : 'fa-solid ' . $icon;
                $output .= '<i class="' . esc_attr($icon_class) . '" style="color:white;"></i>';
            }
            $output .= '<span>' . $label . '</span>';
            $output .= '</a>';
        }
    }

    $output .= '</div>';
    return $output;
}
add_shortcode('product_action_buttons_with_labels', 'shortcode_product_action_buttons_with_labels');

add_filter('woocommerce_package_rates', 'vogo_hide_shipping_when_free_is_available', 100);

function vogo_hide_shipping_when_free_is_available($rates) {
    $free = [];

    foreach ($rates as $rate_id => $rate) {
        if ($rate->method_id === 'free_shipping') {
            $free[$rate_id] = $rate;
            break; // Only need one free shipping method
        }
    }

    return !empty($free) ? $free : $rates;
}

// Shortcode for quantity input and price
add_shortcode('vogo_product_quantity_price', function() {
    global $product;

    if (!is_product()) return '';

    if (!$product || !$product instanceof WC_Product) {
        $product_id = get_the_ID();
        $product = wc_get_product($product_id);
        if (!$product) return '';
    }

    // Hide everything if no price is set
    if (!$product->get_price()) {
        return '';
    }

    $min_qty = $product->get_min_purchase_quantity();
    $max_qty = $product->get_max_purchase_quantity();
    if (!$max_qty || $max_qty < $min_qty) {
        $max_qty = 999;
    }
    $max_qty = 1000;

    ob_start();
    ?>
    <div class="vogo-inline-qty-price" style="display:flex; width: 100%; align-items:center; gap:60px; margin:0px 0;">
        <!-- Debug output below quantity input -->
        <div class="product-price custom-price">
            <?php echo $product->get_price_html(); ?>
        </div>
        <div class="vogo-quantity-wrapper" style="display:flex; width:100%; align-items:center; border-radius:4px;">
            <button type="button" class="vogo-product-qty-minus vogo-qty-btn" style="padding:6px 12px;">−</button>
            <input 
                type="number" 
                id="vogo_quantity" 
                class="vogo_quantity" 
                name="vogo_quantity" 
                min="<?php echo esc_attr($min_qty); ?>" 
                max="<?php echo esc_attr($max_qty); ?>" 
                value="<?php echo esc_attr($min_qty); ?>" 
                style="width:60px; text-align:center; border:none;" 
            />
            <button type="button" class="vogo-product-qty-plus vogo-qty-btn" style="padding:6px 12px;">+</button>
        </div>
    </div>
<!-- RAVI 12.06.2025 - update for quantity -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const container = document.querySelector('.vogo-inline-qty-price');
        if (!container) return;

        const minus = container.querySelector('.vogo-product-qty-minus');
        const input = container.querySelector('.vogo_quantity');
        const plus = container.querySelector('.vogo-product-qty-plus');

        minus.addEventListener('click', () => {
            let val = Number(input.value) || 0;
            let min = Number(input.min) || 1;
            if (val > min) {
                input.value = val - 1;
            }
            // Trigger change event to update linked quantity
            input.dispatchEvent(new Event('change'));
        });

        plus.addEventListener('click', () => {
            let val = Number(input.value) || 0;
            let max = Number(input.max);
            if (!input.max || val < max) {
                input.value = val + 1;
            }
            // Trigger change event to update linked quantity
            input.dispatchEvent(new Event('change'));
        });
    });
    </script>
    <?php
    return ob_get_clean();
});

// Shortcode for add to cart button only
add_shortcode('vogo_product_add_to_cart', function() {
    global $product;

    if (!is_product()) return '';

    if (!$product || !$product instanceof WC_Product) {
        $product_id = get_the_ID();
        $product = wc_get_product($product_id);
        if (!$product) return '';
    }

    // Check if product has a price set
    if (!$product->get_price()) {
        return '<div class="vogo-no-price-action-wrapper">' . do_shortcode('[product_action_buttons_with_labels]') . '</div>';
    }

    ob_start();
    ?>
    <form class="cart" action="<?php echo esc_url(apply_filters('woocommerce_add_to_cart_form_action', $product->get_permalink())); ?>" method="post" enctype="multipart/form-data">
        <input type="hidden" name="quantity" class="vogo-linked-quantity" value="<?php echo esc_attr($product->get_min_purchase_quantity()); ?>" />
        <input type="hidden" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>" />
        <button type="submit" class="single_add_to_cart_button button alt" style="background:#28a745; color:#fff; padding:10px 20px; border:none; border-radius:5px; font-weight:bold;">
            <!-- RAVI 12.06.2025 - update for add to cart button -->
			<span class="vogo-add-to-cart-text">Adaugă în coș</span>
            <span class="vogo-added-text" style="display: none;">Adăugat!</span>
        </button>
    </form>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const customQty = document.getElementById('vogo_quantity');
        const cartForm = document.querySelector('form.cart');

        if (!cartForm) return;

        const linkedQty = cartForm.querySelector('input[name="quantity"]');
        const submitButton = cartForm.querySelector('button[type="submit"]');

        function updateLinkedQty() {
            if (customQty && linkedQty) {
                linkedQty.value = customQty.value;
            }
        }

        if (customQty && linkedQty) {
            updateLinkedQty();
            customQty.addEventListener('change', updateLinkedQty);
            customQty.addEventListener('input', updateLinkedQty);
        }

        cartForm.addEventListener('submit', function (e) {
            e.preventDefault();

            updateLinkedQty(); // Ensure quantity is current
            // --- RAVI: After updating linked qty, fire change event on linkedQty ---
            if (linkedQty) {
                linkedQty.dispatchEvent(new Event('change', { bubbles: true }));
            }

            const button = jQuery(submitButton);
            const formData = new FormData(cartForm);

            button.addClass('loading');
            submitButton.disabled = true;

            jQuery.ajax({
                url: `?wc-ajax=add_to_cart`,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function (response) {
                    button.removeClass('loading');
                    submitButton.disabled = false;

                    if (response.error && response.product_url) {
                        window.location = response.product_url;
                        return;
                    }

                    jQuery(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash, button]);
                    // --- RAVI: After added_to_cart, force wc_fragment_refresh to update side cart/mini cart ---
                    jQuery(document.body).trigger('wc_fragment_refresh');

                    const originalTextSpan = submitButton.querySelector('.vogo-add-to-cart-text');
                    const addedTextSpan = submitButton.querySelector('.vogo-added-text');

                    if (originalTextSpan && addedTextSpan) {
                        originalTextSpan.style.display = 'none';
                        addedTextSpan.style.display = 'inline';
                        setTimeout(() => {
                           originalTextSpan.style.display = 'inline';
                           addedTextSpan.style.display = 'none';
                        }, 2500);
                    }
                },
                error: function () {
                    button.removeClass('loading');
                    submitButton.disabled = false;
                    alert('A apărut o eroare. Vă rugăm să încercați din nou.');
                }
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
});

add_filter('body_class', function($classes) {
    if (is_product()) {
        global $product;
        if (!$product instanceof WC_Product) {
            $product = wc_get_product(get_the_ID());
        }

        if ($product && $product->get_price()) {
            $classes[] = 'product-has-price';
        } else {
            $classes[] = 'product-no-price';
        }
    }

    return $classes;
});

add_action('wp_footer', function () {
    ?>
    <script>
    function applyVogoCustomMobileGalleryStyles() {
        if (window.innerWidth > 767) return; // Only on mobile

        const galleryInner = document.querySelectorAll('.woocommerce-product-gallery .wd-carousel-inner');
        const galleryWraps = document.querySelectorAll('.wd-show-product-gallery-wrap');

        if (galleryInner.length) {
            galleryInner.forEach(el => {
                el.style.margin = '-20px';
                el.style.borderRadius = '0';
            });
        }

        if (galleryWraps.length) {
            galleryWraps.forEach(el => {
                el.style.position = 'absolute';
                el.style.right = '10px';
                el.style.top = '-50px';
                el.style.zIndex = '10';
            });
        }
    }

    // Initial load
    document.addEventListener('DOMContentLoaded', () => {
        applyVogoCustomMobileGalleryStyles();

        // Retry for late-loaded DOM
        let tries = 0;
        const interval = setInterval(() => {
            applyVogoCustomMobileGalleryStyles();
            tries++;
            if (tries >= 10) clearInterval(interval);
        }, 400);
    });

    // WooCommerce AJAX triggers
    jQuery(document.body).on('updated_wc_div wc_fragments_loaded', applyVogoCustomMobileGalleryStyles);

    // Elementor popup trigger (if used)
    document.addEventListener('elementor/popup/show', applyVogoCustomMobileGalleryStyles);

    // MutationObserver for future dynamic changes
    const observer = new MutationObserver(() => applyVogoCustomMobileGalleryStyles());
    const observerInterval = setInterval(() => {
        const target = document.querySelector('.woocommerce-product-gallery');
        if (target) {
            observer.observe(target, { childList: true, subtree: true });
            clearInterval(observerInterval);
        }
    }, 300);
    </script>
    <?php
});

add_action('wp_footer', function () {
    ?>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        if (window.innerWidth > 767) return;

        function styleGalleryButton() {
            document.querySelectorAll('.wd-show-product-gallery-wrap').forEach(el => {
                el.style.position = 'absolute';
                el.style.right = '10px';
                el.style.top = '-50px';
                el.style.zIndex = '10';
                // Optional: log to verify
                console.log("✅ Styled .wd-show-product-gallery-wrap");
            });
        }

        // Fallback: retry scan
        function runWithRetry(attempt = 0) {
            styleGalleryButton();

            if (attempt >= 10) return;
            setTimeout(() => runWithRetry(attempt + 1), 500);
        }

        runWithRetry(); // Start retry cycle

        // Also observe the gallery for dynamic DOM changes
        const observer = new MutationObserver(styleGalleryButton);
        function startObserver() {
            const target = document.querySelector('.woocommerce-product-gallery');
            if (target) {
                observer.observe(target, { childList: true, subtree: true });
                console.log("👁️ Observer started");
            } else {
                setTimeout(startObserver, 300); // Retry if gallery not ready yet
            }
        }
        startObserver();

        // Also apply after WooCommerce events
        jQuery(document.body).on('updated_wc_div wc_fragments_loaded', styleGalleryButton);
    });
    </script>
    <?php
});

function extend_googtrans_cookie_expiration() {
    ?>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const cookieName = "googtrans";
        const cookieValue = getCookie(cookieName);
        if (cookieValue) {
            // Set cookie to expire in 30 days
            const date = new Date();
            date.setTime(date.getTime() + (30 * 24 * 60 * 60 * 1000));
            const expires = "expires=" + date.toUTCString();
            document.cookie = cookieName + "=" + cookieValue + "; " + expires + "; path=/";
        }

        function getCookie(name) {
            const match = document.cookie.match(new RegExp("(^| )" + name + "=([^;]+)"));
            return match ? match[2] : null;
        }
    });
    </script>
    <?php
}
add_action('wp_footer', 'extend_googtrans_cookie_expiration', 100);
add_action( 'wp_enqueue_scripts', 'load_dashicons_frontend' );
function load_dashicons_frontend() {
    wp_enqueue_style( 'dashicons' );
}

// Suppress native WooCommerce "Product added to cart" notice globally
add_filter('wc_add_to_cart_message_html', '__return_empty_string');