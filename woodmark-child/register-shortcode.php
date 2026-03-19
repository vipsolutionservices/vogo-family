<?php
// === CONFIG: Your reCAPTCHA Keys ===
define('MY_RECAPTCHA_V2_SITE_KEY', '6Ld1qQQrAAAAABgmV9FJJtUzb6Wp3pHLNRA23L0_');
define('MY_RECAPTCHA_V2_SECRET_KEY', '6Ld1qQQrAAAAAMolQXuyw46R2Aqflkof6cYse41J');

add_action('init', function () {
    if (!session_id() && !headers_sent()) {
        session_start();
    }

    if (isset($_GET['referral_code'])) {
        $_SESSION['referral_code'] = sanitize_text_field($_GET['referral_code']);
    }
});

add_shortcode('custom_wc_register', 'custom_wc_register_form');

function custom_wc_register_form() {
    return custom_wc_register_form_with_role('');
}
add_shortcode('custom_wc_register_expert', 'custom_wc_register_form_expert');
function custom_wc_register_form_expert() {
    return custom_wc_register_form_with_role('expert');
}
add_shortcode('custom_wc_register_provider', 'custom_wc_register_form_provider');
function custom_wc_register_form_provider() {
    return custom_wc_register_form_with_role('provider');
}

add_shortcode('custom_wc_register_transporter', 'custom_wc_register_form_transporter');
function custom_wc_register_form_transporter() {
    return custom_wc_register_form_with_role('transporter');
}


function custom_wc_register_form_with_role($role) {
    if (is_user_logged_in()) return '<p>Ești deja autentificat.</p>';

    ob_start();

    echo '<div class="woocommerce-notices-wrapper">';
    wc_print_notices();
    echo '</div>';
    ?>
    <form method="post" class="custom-register-form" action="">
        <div class="floating-group">
        <p class="form-row form-row-wide">
            <input type="email" class="input-text" placeholder=" " name="email" id="reg_email" required>
            <label for="username">Adresa de email *</label>
        </p></div>
        <div class="floating-group">
        <p class="form-row form-row-wide">
            <input type="password" class="woocommerce-Input input-text" placeholder=" " name="password" id="reg_password" autocomplete="new-password" required />
            
            <label for="password">Parolă *</label>
            <div id="password-strength" class="woocommerce-password-strength"></div>
        </p></div>
    <!-- Tag Referral Code -->
    <div class="floating-group">
        <p class="form-row form-row-wide">
            <input type="text" class="input-text" placeholder=" " name="referral_code" id="referral_code" value="<?php echo isset($_SESSION['referral_code']) ? esc_attr($_SESSION['referral_code']) : ''; ?>">
            <label for="password">Cod de Recomandare</label>
        </p></div>

        <?php 
        if (!empty($role)) {
            echo '<input type="hidden" name="custom_user_role" value="' . esc_attr($role) . '">';
        }
        ?>

        <div class="form-row form-row-wide" style="margin-bottom: 20px;">
            <div class="g-recaptcha" data-sitekey="<?php echo esc_attr(MY_RECAPTCHA_V2_SITE_KEY); ?>"></div>
        </div>
        <?php 
        $privacy_text = get_option('woocommerce_registration_privacy_policy_text');
        if ($privacy_text) {
            echo '<div class="woocommerce-privacy-policy-text" style="margin-top:15px;">' . wp_kses_post(wpautop($privacy_text)) . '</div>';
        }
        ?>

        <div class="register-form-row">
            <button type="submit" name="custom_register_submit" class="button-register">Înregistrare</button>
            <div class="mo-google-login">
    <?php echo do_shortcode('[nextend_social_login]'); ?>
</div>
        </div>

    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const urlParams = new URLSearchParams(window.location.search);
        const referralCode = urlParams.get('referral_code');
        if (referralCode && document.getElementById('referral_code')) {
          document.getElementById('referral_code').value = referralCode;
        }
      });
    </script>
    </form>

    <script src="https://www.google.com/recaptcha/api.js?onload=onloadCallback&render=explicit" async defer></script>
        <script>
        function onloadCallback() {
            if (document.querySelector('.g-recaptcha')) {
                grecaptcha.render(document.querySelector('.g-recaptcha'), {
                    'sitekey': '<?php echo esc_js(MY_RECAPTCHA_V2_SITE_KEY); ?>'
                });
            }
        }
        </script>    
    <?php
    wp_enqueue_script('password-strength-meter');
    wp_enqueue_script('wc-password-strength-meter');
    ?>
    <script>
    jQuery(function($) {
        $('#reg_password').on('input', function () {
            var password = $(this).val();
            var strength = wp.passwordStrength.meter(password, [], password);
            var strengthText = wp.passwordStrength.strengthNames[strength] || '';
            $('#password-strength')
                .removeClass()
                .addClass('woocommerce-password-strength strength-' + strengthText.toLowerCase().replace(/\s+/g, '-'))
                .text(strengthText);
        });
    });
    </script>
    <?php

    return ob_get_clean();
}

add_action('init', 'custom_handle_registration_form');
function custom_handle_registration_form() {
    if (isset($_POST['custom_register_submit'])) {

        $email     = sanitize_email($_POST['email']);
        $password  = $_POST['password'];
        $referral  = sanitize_text_field($_POST['referral_code']);
        $recaptcha = sanitize_text_field($_POST['g-recaptcha-response']);
        $role      = sanitize_text_field($_POST['custom_user_role'] ?? '');

        if (empty($recaptcha)) {
            wc_add_notice(__('Vă rugăm să confirmați că nu sunteți un robot.', 'vogo'), 'error');
            return;
        }

        // Basic required fields
        if (empty($email) || empty($password)) {
            wc_add_notice(__('Toate câmpurile, sunt obligatorii.', 'vogo'), 'error');
            return;
        }

        // Validate reCAPTCHA
        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret'   => MY_RECAPTCHA_V2_SECRET_KEY,
                'response' => $recaptcha,
                'remoteip' => $_SERVER['REMOTE_ADDR'],
            ],
        ]);

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($response_body['success'])) {
            if (!empty($response_body['error-codes'])) {
                foreach ($response_body['error-codes'] as $code) {
                    wc_add_notice(__('reCAPTCHA error: ', 'vogo') . esc_html($code), 'error');
                }
            } else {
                wc_add_notice(__('Verificarea ReCAPTCHA a eșuat.', 'vogo'), 'error');
            }
            return;
        }

        // Check if email already exists
        if (email_exists($email)) {
            wc_add_notice(__('Adresa de email există deja. Vă rugăm să vă autentificați.', 'vogo'), 'error');
            return;
        }

        // ✅ Validate referral code (now required)
        $referrer = get_users([
            'meta_key'   => 'referral_code',
            'meta_value' => $referral,
            'number'     => 1,
            'fields'     => 'ID',
        ]);

        if (empty($referrer)) {
            wc_add_notice(__('Invalid referral code. Please enter a valid one.', 'vogo'), 'error');
            return;
        }

        // Create username from email
        $username = sanitize_user(current(explode('@', $email)));
        $user_id = wc_create_new_customer($email, $username, $password);

        if (is_wp_error($user_id)) {
            wc_add_notice($user_id->get_error_message(), 'error');
            return;
        }

        // ✅ Save referral code and referrer user ID
        update_user_meta($user_id, 'referred_by', $referrer[0]);
        update_user_meta($user_id, 'referral_code_used', $referral);

        if (!is_wp_error($user_id)) {
            // Auto-login the user
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id, true); // Remember me
            do_action('wp_login', $email, get_user_by('ID', $user_id));

            if (!empty($role) && in_array($role, ['expert', 'provider','transporter'])) {
                wp_update_user(['ID' => $user_id, 'role' => $role]);
            }
        
            wc_add_notice(__('Înregistrarea a fost realizată cu succes. Ești acum autentificat.', 'vogo'), 'success');
        
            // Redirect to homepage
            wp_redirect(home_url('/'));
            exit;
        }

        wc_add_notice(__('Înregistrarea a fost realizată cu succes. Poți acum să te autentifici.', 'vogo'), 'success');

        // Optional: redirect or auto-login
        // wp_redirect(home_url('/thank-you'));
        // exit;
    }
}

add_action('woocommerce_created_customer', function($customer_id) {
    if (!empty($_POST['custom_user_role']) && in_array($_POST['custom_user_role'], ['expert', 'provider','transporter'])) {
        $user = new WP_User($customer_id);
        $user->set_role(sanitize_text_field($_POST['custom_user_role']));
    }
}, 5);

add_action('init', function () {
    if (!get_role('expert')) {
        add_role('expert', 'Expert', ['read' => true]);
    }

    if (!get_role('provider')) {
        add_role('provider', 'Provider', ['read' => true]);
    }

    if (!get_role('transporter')) {
        add_role('transporter', 'Transporter', ['read' => true]);
    }
});