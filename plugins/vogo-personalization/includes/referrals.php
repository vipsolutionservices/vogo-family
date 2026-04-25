<?php
function vogo_generate_referral_code($user_id) {
    $referral_code = 'USER' . $user_id;
    update_user_meta($user_id, 'referral_code', $referral_code);
    return $referral_code;
}

// LEGACY: dezactivat 2026-04-25 - scria in wp_referrals (tabel legacy, marcat pentru stergere) si user_meta 'referred_by'.
// Inlocuit complet de wp_vogo_user_info.parent_user_id + used_refferal_code, gestionat de register-shortcode.php.
// add_action('user_register', function($user_id) {
//     if (!empty($_POST['referral_code'])) {
//         $referrer = get_users([
//             'meta_key' => 'referral_code',
//             'meta_value' => sanitize_text_field($_POST['referral_code']),
//             'number' => 1
//         ]);
//         if (!empty($referrer)) {
//             update_user_meta($user_id, 'referred_by', $referrer[0]->ID);
//             global $wpdb;
//             $wpdb->insert("{$wpdb->prefix}referrals", [
//                 'referrer_id' => $referrer[0]->ID,
//                 'referred_user_id' => $user_id
//             ]);
//         }
//     }
// });

add_action('woocommerce_account_dashboard', 'vogo_show_referral_qr_in_dashboard');


function vogo_show_referral_qr_in_dashboard() {
    // Get the current user ID
    $current_user_id = get_current_user_id();

    // Ensure the user is logged in
    if (!$current_user_id) {
        return;
    }

    // Get the referral code from user meta
    $referral_code = get_user_meta($current_user_id, 'referral_code', true);

    if ($referral_code) {
        // Generate the WooCommerce My Account URL with the referral code
        $registration_url = get_permalink(4537) . '?referral_code=' . urlencode($referral_code);

        // echo '<p><strong>' . __('Your Referral ID:', 'vogo') . '</strong> ' . esc_html($referral_code) . '</p>';
//         echo '<p><strong>' . __('Link de Recomandare:', 'vogo') . '</strong> <a href="' . esc_url($registration_url) . '">' . esc_html($registration_url) . '</a></p>';
//         echo '<p><strong>' . __('Codul Dumneavoastră QR de Recomandare:', 'vogo') . '</strong></p>';

        // QR code container
        echo '<div id="qrcode" style="margin: 20px 0;"></div>';

        // Inline script to generate the QR code
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                var qrcode = new QRCode(document.getElementById("qrcode"), {
                    text: "' . esc_js($registration_url) . '",
                    width: 128,
                    height: 128,
                    colorDark : "#000000",
                    colorLight : "#ffffff",
                });
            });
        </script>';
    } else {
        echo '<p>' . __('Nu ai încă un ID de recomandare.', 'vogo') . '</p>';
    }
    $referrer_id = get_user_meta($current_user_id, 'referred_by', true);
    if ($referrer_id) {
        $referrer = get_userdata($referrer_id);
        if ($referrer) {
            $first_name = get_user_meta($referrer_id, 'first_name', true);
            $last_name = get_user_meta($referrer_id, 'last_name', true);
            $nickname = $referrer->nickname;

            echo '<h4>' . __('Ai fost recomandat de:', 'vogo') . '</h4>';
            echo '<p><strong>First Name:</strong> ' . esc_html($first_name) . '</p>';
            echo '<p><strong>Last Name:</strong> ' . esc_html($last_name) . '</p>';
            echo '<p><strong>Nickname:</strong> ' . esc_html($nickname) . '</p>';
        }
    }
}
add_action('woocommerce_register_form', 'vogo_populate_referral_code_field');


function vogo_populate_referral_code_field() {
    // Check if a referral code exists in the URL
    $referral_code = isset($_GET['referral_code']) ? sanitize_text_field($_GET['referral_code']) : '';

    // Output the referral code field with the value pre-filled
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const referralInput = document.getElementById('referral_code');
            if (referralInput && "<?php echo esc_js($referral_code); ?>") {
                referralInput.value = "<?php echo esc_js($referral_code); ?>";
            }
        });
    </script>
    <?php
}
// Add Share Buttons to My Account Dashboard
add_action('woocommerce_account_dashboard', 'vogo_display_referral_share_options');

function vogo_display_referral_share_options() {
    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        return;
    }

    $referral_code = get_user_meta($current_user_id, 'referral_code', true);
    if (!$referral_code) {
        return;
    }

    // Generate the referral link
    $referral_url = esc_url(home_url("/register?referral_code={$referral_code}"));
    $encoded_url = urlencode($referral_url);
    $share_text = urlencode("Join VOGO using my referral link: ");

    ?>

    <h3><?php _e('Distribuie linkul tău de Recomandare', 'vogo'); ?></h3>
    <p><?php _e('Invită-ți prietenii folosind linkurile de mai jos.', 'vogo'); ?></p>

    <style>
        .referral-share-buttons a {
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
            width: 150px;
            text-align: center;
            text-transform: capitalize;
        }
		i.fab {
    margin-right: 8px;
}
        .whatsapp { background-color: #25D366; }
        .facebook { background-color: #3b5998; }
        .twitter { background-color: #1DA1F2; }
        .linkedin { background-color: #0077b5; }
        .email { background-color: #c42220; }
    </style>
    <div class="referral-share-buttons" style="display: flex; gap: 5px; flex-wrap: wrap; margin-bottom: 10px;">
        <a href="https://api.whatsapp.com/send?text=<?php echo $share_text . $encoded_url; ?>" target="_blank" class="whatsapp"><i class="fab fa-whatsapp"></i> WhatsApp</a>
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $encoded_url; ?>" target="_blank" class="facebook"> <i class="fab fa-facebook-f"></i> Facebook</a>
        <a href="https://twitter.com/intent/tweet?text=<?php echo $share_text; ?>&url=<?php echo $encoded_url; ?>" target="_blank" class="twitter"><i class="bi bi-x"></i>X  Register</a>
        <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo $encoded_url; ?>" target="_blank" class="linkedin"><i class="fab fa-linkedin-in"></i> LinkedIn</a>
        <a href="mailto:?subject=Join%20VOGO%20Today!&body=<?php echo $share_text . $encoded_url; ?>" target="_blank" class="email"><i class="fas fa-envelope" style=" margin-right: 8px;"></i> Email</a>
    </div>
    <p>
        <strong><?php _e('Linkul tău de recomandare:', 'vogo'); ?></strong> 
        <input type="text" value="<?php echo $referral_url; ?>" readonly style="width: 100%;" onclick="this.select();" />
    </p>
    <?php

}