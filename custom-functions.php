<?php
add_filter('woocommerce_form_field', 'custom_floating_checkout_fields', 10, 4);
function custom_floating_checkout_fields($field, $key, $args, $value) {

    // Apply only on these WooCommerce pages
    if (
        is_checkout() ||
        (is_account_page() && (is_wc_endpoint_url('edit-address')))
    ) {
        $required = !empty($args['required']) ? '<abbr class="required" title="required">*</abbr>' : '';
        $label    = $args['label'] ? esc_html($args['label']) : '';

        if ($args['type'] === 'textarea') {
            $field = sprintf(
                '<p class="form-row %s floating-group" id="%s_field" data-priority="%s">
                    <span class="woocommerce-input-wrapper">
                        <textarea class="input-text" name="%s" id="%s" placeholder=" ">%s</textarea>
                        <label for="%s" class="floating-label">%s %s</label>
                    </span>
                </p>',
                esc_attr(implode(' ', $args['class'])),
                esc_attr($key),
                esc_attr($args['priority']),
                esc_attr($key),
                esc_attr($key),
                esc_textarea($value),
                esc_attr($key),
                $label,
                $required
            );
        } elseif (in_array($args['type'], ['text', 'email', 'tel', 'password', 'number'])) {
            $custom_attrs = '';
            if (!empty($args['custom_attributes']) && is_array($args['custom_attributes'])) {
                foreach ($args['custom_attributes'] as $attr => $attr_val) {
                    $custom_attrs .= esc_attr($attr) . '="' . esc_attr($attr_val) . '" ';
                }
            }

            $field = sprintf(
                '<p class="form-row %s floating-group" id="%s_field" data-priority="%s">
                    <span class="woocommerce-input-wrapper">
                        <input type="%s" class="input-text" name="%s" id="%s" placeholder=" " value="%s" %s />
                        <label for="%s" class="floating-label">%s %s</label>
                    </span>
                </p>',
                esc_attr(implode(' ', $args['class'])),
                esc_attr($key),
                esc_attr($args['priority']),
                esc_attr($args['type']),
                esc_attr($key),
                esc_attr($key),
                esc_attr($value),
                $custom_attrs,
                esc_attr($key),
                $label,
                $required
            );
        }
    }

    return $field;
}


add_action('woocommerce_edit_account_form_start', 'custom_floating_labels_edit_account');
function custom_floating_labels_edit_account() {
    ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('.woocommerce-EditAccountForm');
            if (!form) return;

            const fields = form.querySelectorAll('p.woocommerce-form-row');

            fields.forEach(function (field) {
                const input = field.querySelector('input');
                const label = field.querySelector('label');
                if (input && label) {
                    const wrapper = document.createElement('div');
                    wrapper.classList.add('floating-group');

                    input.setAttribute('placeholder', ' ');

                    // Move input and label into wrapper
                    field.insertBefore(wrapper, input);
                    wrapper.appendChild(input);
                    wrapper.appendChild(label);
                }
            });
        });
    </script>
    <?php
}

add_filter( 'woocommerce_account_menu_items', 'custom_rename_logout_menu_item_with_class', 99 );
function custom_rename_logout_menu_item_with_class( $menu_links ) {
    if ( isset( $menu_links['customer-logout'] ) ) {
        $menu_links['customer-logout'] = '<span class="notranslate">Logout</span>';
    }
    return $menu_links;
}

add_action('wp_footer', function () {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const logoutDiv = document.querySelector('.customer-logout-link a');
        if (logoutDiv) {
            logoutDiv.innerHTML = '<span class="notranslate">Logout</span>';
        }
    });
    </script>
    <?php
});

add_action('wp_footer', function () {
    ?>
    <script>
        function getCookie(name) {
            const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
            if (match) return match[2];
        }

        function setLanguageCookie(lang) {
            console.log("Setting language cookie to:", lang);
            document.cookie = `googtrans=/auto/${lang};path=/;max-age=31536000`;
            document.cookie = `googtrans=/auto/${lang};domain=${window.location.hostname};path=/;max-age=31536000`;
        }

        function applyLanguageFromCookie() {
            const lang = getCookie("googtrans");
            console.log("Applying language from cookie:", lang);
            if (lang && typeof window.doGTranslate === 'function') {
                window.doGTranslate(lang);
            }
        }

        document.addEventListener("DOMContentLoaded", function () {
            // Log and apply stored language from cookie
            applyLanguageFromCookie();

            // Log changes and set cookie on language selection
            document.querySelectorAll(".gt_selector select").forEach(sel => {
                sel.addEventListener("change", function () {
                    const selectedLang = this.value;
                    console.log("Language selected from dropdown:", selectedLang);
                    if (selectedLang) {
                        setLanguageCookie(selectedLang);
                        if (typeof window.doGTranslate === 'function') {
                            window.doGTranslate(`/auto/${selectedLang}`);
                        }
                    }
                });
            });
        });
    </script>
    <?php
});

// Allow custom user avatars
add_filter('get_avatar', 'custom_user_avatar', 10, 5);
function custom_user_avatar($avatar, $id_or_email, $size, $default, $alt) {
    $user = false;

    if (is_numeric($id_or_email)) {
        $user = get_user_by('id', $id_or_email);
    } elseif (is_object($id_or_email)) {
        if (!empty($id_or_email->user_id)) {
            $user = get_user_by('id', $id_or_email->user_id);
        }
    } else {
        $user = get_user_by('email', $id_or_email);
    }

    if ($user && is_object($user)) {
        $custom_avatar = get_user_meta($user->ID, 'custom_avatar', true);
        if ($custom_avatar) {
            return "<img src='" . esc_url($custom_avatar) . "' alt='" . esc_attr($alt) . "' width='{$size}' height='{$size}' />";
        }
    }

    return $avatar;
}
add_filter('woocommerce_edit_account_form_tag', 'add_enctype_to_edit_account_form');
function add_enctype_to_edit_account_form() {
    echo ' enctype="multipart/form-data"';
}
// Add upload field to My Account > Edit Account
add_action('woocommerce_edit_account_form', 'add_avatar_upload_field_to_my_account');
function add_avatar_upload_field_to_my_account() {
    $user_id = get_current_user_id();
    $avatar = get_user_meta($user_id, 'custom_avatar', true);
    ?>
    <p class="form-row form-row-wide">
        <label for="custom_avatar">Poza de profil</label><br>
        <input type="file" name="custom_avatar" id="custom_avatar" accept="image/*" />
        <?php if ($avatar): ?>
            <br><img src="<?php echo esc_url($avatar); ?>" width="96" height="96" style="margin-top:10px;">
            <p>
                <label><input type="checkbox" name="remove_custom_avatar" value="1"> Eliminați imaginea curentă</label>
            </p>
        <?php endif; ?>
    </p>
    <?php
}

add_action('woocommerce_save_account_details', 'save_custom_avatar_upload', 12, 1);
function save_custom_avatar_upload($user_id) {
    error_log('Starting avatar upload for user ID: ' . $user_id);

    if (!empty($_POST['remove_custom_avatar'])) {
        delete_user_meta($user_id, 'custom_avatar');
        error_log('Custom avatar removed for user ID: ' . $user_id);
    }

    if (!empty($_FILES['custom_avatar']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Handle upload
        $uploaded_file = wp_handle_upload($_FILES['custom_avatar'], ['test_form' => false]);

        error_log('Upload result: ' . print_r($uploaded_file, true));

        if (!isset($uploaded_file['url']) || isset($uploaded_file['error'])) {
            error_log('Avatar Upload Error: ' . $uploaded_file['error']);
            return;
        }

        // Create attachment
        $file_type = wp_check_filetype($uploaded_file['file'], null);
        $attachment = [
            'post_mime_type' => $file_type['type'],
            'post_title'     => sanitize_file_name($uploaded_file['file']),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_author'    => $user_id
        ];

        $attachment_id = wp_insert_attachment($attachment, $uploaded_file['file']);
        error_log('Attachment created with ID: ' . $attachment_id);

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attachment_id, $uploaded_file['file']);
        wp_update_attachment_metadata($attachment_id, $attach_data);

        // Save URL in user meta
        update_user_meta($user_id, 'custom_avatar', wp_get_attachment_url($attachment_id));
        error_log('Avatar URL saved to user_meta: ' . wp_get_attachment_url($attachment_id));
    }
}