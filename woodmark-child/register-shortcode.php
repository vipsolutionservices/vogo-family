<?php
// === CONFIG: Your reCAPTCHA Keys ===
define('MY_RECAPTCHA_V2_SITE_KEY', '6Ld1qQQrAAAAABgmV9FJJtUzb6Wp3pHLNRA23L0_');
define('MY_RECAPTCHA_V2_SECRET_KEY', '6Ld1qQQrAAAAAMolQXuyw46R2Aqflkof6cYse41J');

// SMOKE-TEST: dovedeste ca fisierul a fost incarcat de WordPress (nu doar copiat pe disk)
error_log('[VOGO-REG] FILE-LOADED ' . __FILE__);

// FATAL CATCHER: prinde orice error fatal la sfarsitul requestului si il logheaza inainte sa moara procesul.
// Critic pentru cazul cand executia se opreste intre S5 si S6 fara sa apara niciun log de eroare.
register_shutdown_function(function() {
    if (empty($_POST['custom_register_submit'])) return; // doar pe register POST
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR])) {
        error_log(sprintf('[VOGO-REG] FATAL captured | type=%d | msg=%s | file=%s | line=%d', $err['type'], $err['message'], $err['file'], $err['line']));
    } else {
        error_log('[VOGO-REG] SHUTDOWN clean | last_err=' . ($err ? $err['message'] : 'none'));
    }
});

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
    // SMOKE-TEST: dovedeste ca handlerul ruleaza la fiecare request init si arata ce POST keys vin
    if (!empty($_POST)) {
        error_log('[VOGO-REG] S0 handler-init | POST_keys=' . implode(',', array_keys($_POST)) . ' | uri=' . ($_SERVER['REQUEST_URI'] ?? '?'));
    }
    if (isset($_POST['custom_register_submit'])) {

        $email     = sanitize_email($_POST['email']);
        $password  = $_POST['password'];
        $referral  = sanitize_text_field($_POST['referral_code']);
        $recaptcha = sanitize_text_field($_POST['g-recaptcha-response']);
        $role      = sanitize_text_field($_POST['custom_user_role'] ?? '');

        // LOG: submit primit - inputuri sanitizate (parola si recaptcha NU se logheaza)
        error_log(sprintf('[VOGO-REG] S1 submit | email=%s | ref=%s | role=%s | ip=%s', $email, $referral, $role ?: 'customer', $_SERVER['REMOTE_ADDR'] ?? 'unknown'));

        if (empty($recaptcha)) {
            error_log('[VOGO-REG] S2 FAIL recaptcha-empty');
            wc_add_notice(__('Vă rugăm să confirmați că nu sunteți un robot.', 'vogo'), 'error');
            return;
        }

        // Basic required fields
        if (empty($email) || empty($password)) {
            error_log(sprintf('[VOGO-REG] S2 FAIL fields-empty | email_set=%d | pass_set=%d', !empty($email)?1:0, !empty($password)?1:0));
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
            $errs = !empty($response_body['error-codes']) ? implode(',', $response_body['error-codes']) : 'no-error-codes';
            error_log('[VOGO-REG] S3 FAIL recaptcha-verify | errors=' . $errs);
            if (!empty($response_body['error-codes'])) {
                foreach ($response_body['error-codes'] as $code) {
                    wc_add_notice(__('reCAPTCHA error: ', 'vogo') . esc_html($code), 'error');
                }
            } else {
                wc_add_notice(__('Verificarea ReCAPTCHA a eșuat.', 'vogo'), 'error');
            }
            return;
        }
        error_log('[VOGO-REG] S3 OK recaptcha-verified');

        // Check if email already exists
        if (email_exists($email)) {
            error_log('[VOGO-REG] S4 FAIL email-exists | email=' . $email);
            wc_add_notice(__('Adresa de email există deja. Vă rugăm să vă autentificați.', 'vogo'), 'error');
            return;
        }

        // Validate referral code OPTIONAL against wp_vogo_user_info.
        // Gol = register fara referral (OK, $referrer_user_id ramane 0).
        // Completat dar invalid = eroare si return.
        // Match case-insensitive pe my_referral_code (UNIQUE) sau client_nickname.
        global $wpdb;
        $referrer_user_id = 0;
        if (!empty($referral)) {
            $referrer_user_id = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->prefix}vogo_user_info
                 WHERE LOWER(my_referral_code) = LOWER(%s)
                    OR LOWER(client_nickname)  = LOWER(%s)
                 LIMIT 1",
                $referral, $referral
            ));
            error_log(sprintf('[VOGO-REG] S5 referral-lookup | input=%s | referrer_user_id=%d', $referral, $referrer_user_id));

            if (!$referrer_user_id) {
                error_log('[VOGO-REG] S5 FAIL referral-invalid');
                wc_add_notice(__('Codul de recomandare introdus nu este valid.', 'vogo'), 'error');
                return;
            }
        } else {
            error_log('[VOGO-REG] S5 SKIP referral-empty - register fara referral');
        }

        // S5b: dovedeste ca executia continua dincolo de blocul S5 (lookup + if/else)
        error_log(sprintf('[VOGO-REG] S5b post-lookup | referrer_user_id=%d | email=%s', $referrer_user_id, $email));

        // Create username from email
        $username = sanitize_user(current(explode('@', $email)));
        // S5c: dovedeste ca sanitize_user a rulat OK + timestamp microsecunde pentru diagnostic timing
        $t_start = microtime(true);
        error_log(sprintf('[VOGO-REG] S5c username-ready | username=%s | about-to-call wc_create_new_customer | t=%.4f', $username, $t_start));

        // DEBUG ADI 1: oprire EXPLICITA cu mesaj pe ecran inainte de wc_create_new_customer.
        // Daca vezi mesajul = codul ajunge pana aici. Daca NU vezi = ceva opreste executia mai devreme.
        wp_die(
            '<h1 style="color:#fff;background:#1A3D2B;padding:30px;font-size:28px;text-align:center;">'
            . 'DEBUG ADI 1 - executie ajunsa la S5c (inainte de wc_create_new_customer)<br><br>'
            . 'email: ' . esc_html($email) . '<br>'
            . 'username: ' . esc_html($username) . '<br>'
            . 'referral: ' . esc_html($referral) . '<br>'
            . 'referrer_user_id: ' . (int)$referrer_user_id
            . '</h1>',
            'DEBUG VOGO REG',
            ['response' => 200]
        );

        // Try/catch capturez orice Throwable (Exception + Error PHP 7+) - fatal-uri normale prinse de shutdown
        try {
            $user_id = wc_create_new_customer($email, $username, $password);
        } catch (\Throwable $e) {
            $t_caught = microtime(true);
            error_log(sprintf('[VOGO-REG] S5c-EXCEPTION | type=%s | msg=%s | file=%s:%d | dt=%.3fs', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine(), $t_caught - $t_start));
            wc_add_notice(__('Eroare interna la creare cont. Va rugam reincercati.', 'vogo'), 'error');
            return;
        }

        // S5d: dovedeste ca wc_create_new_customer a returnat (nu fatal/exit). Loghez tipul rezultatului + timing.
        $t_end = microtime(true);
        $rt = is_wp_error($user_id) ? 'WP_Error' : (is_int($user_id) || ctype_digit((string)$user_id) ? 'int:'.(int)$user_id : 'unknown:'.gettype($user_id));
        error_log(sprintf('[VOGO-REG] S5d wc_create_new_customer-returned | result_type=%s | dt=%.3fs', $rt, $t_end - $t_start));

        if (is_wp_error($user_id)) {
            error_log('[VOGO-REG] S6 FAIL wc_create_new_customer | err=' . $user_id->get_error_message());
            wc_add_notice($user_id->get_error_message(), 'error');
            return;
        }
        error_log(sprintf('[VOGO-REG] S6 OK user-created | user_id=%d | username=%s', (int)$user_id, $username));

        // Persist referral relationship in wp_vogo_user_info (canonical).
        // sync_vogo_user_info() ensures a row exists and seeds my_referral_code = 'U' + uid.
        // Then we stamp parent_user_id + used_refferal_code on the newly created row.
        $sync_available = function_exists('sync_vogo_user_info');
        error_log(sprintf('[VOGO-REG] S7 sync_vogo_user_info | available=%d | uid=%d', $sync_available?1:0, (int)$user_id));
        if ($sync_available) {
            sync_vogo_user_info($user_id);
            // Verifica daca rand a fost creat in vogo_user_info dupa sync
            $row_after_sync = $wpdb->get_row($wpdb->prepare(
                "SELECT id, my_referral_code FROM {$wpdb->prefix}vogo_user_info WHERE user_id = %d LIMIT 1",
                (int)$user_id
            ), ARRAY_A);
            if ($row_after_sync) {
                error_log(sprintf('[VOGO-REG] S7 OK row-exists-after-sync | id=%d | my_referral_code=%s', (int)$row_after_sync['id'], $row_after_sync['my_referral_code'] ?? 'NULL'));
            } else {
                error_log('[VOGO-REG] S7 FAIL row-missing-after-sync | uid=' . (int)$user_id . ' | wpdb_err=' . ($wpdb->last_error ?: 'none'));
            }
        } else {
            error_log('[VOGO-REG] S7 SKIP sync-not-available - vogo-plugin probabil dezactivat');
        }

        // Populeaza user_name + email in vogo_user_info (sync_vogo_user_info nu le seteaza - schema extinsa pe prod).
        $upd_user = $wpdb->update(
            "{$wpdb->prefix}vogo_user_info",
            ['user_name' => $username, 'email' => $email],
            ['user_id' => (int)$user_id],
            ['%s', '%s'],
            ['%d']
        );
        error_log(sprintf('[VOGO-REG] S7b update-user_name+email | rows_affected=%s | username=%s | email=%s | wpdb_err=%s', var_export($upd_user, true), $username, $email, $wpdb->last_error ?: 'none'));

        // Stamp parent_user_id + used_refferal_code DOAR daca avem referrer valid.
        // Daca register fara referral - sync_vogo_user_info a creat deja randul cu my_referral_code='U'+uid si parent NULL.
        if ($referrer_user_id > 0) {
            $upd_result = $wpdb->update(
                "{$wpdb->prefix}vogo_user_info",
                ['parent_user_id' => $referrer_user_id, 'used_refferal_code' => $referral],
                ['user_id' => (int)$user_id],
                ['%d', '%s'],
                ['%d']
            );
            error_log(sprintf('[VOGO-REG] S8 update-vogo_user_info | rows_affected=%s | parent=%d | used_ref=%s | wpdb_err=%s', var_export($upd_result, true), $referrer_user_id, $referral, $wpdb->last_error ?: 'none'));
        } else {
            error_log('[VOGO-REG] S8 SKIP update-vogo_user_info - fara referrer (register fara cod)');
        }

        if (!is_wp_error($user_id)) {
            // Auto-login the user
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id, true); // Remember me
            do_action('wp_login', $email, get_user_by('ID', $user_id));

            if (!empty($role) && in_array($role, ['expert', 'provider','transporter'])) {
                wp_update_user(['ID' => $user_id, 'role' => $role]);
                error_log(sprintf('[VOGO-REG] S9 role-set | uid=%d | role=%s', (int)$user_id, $role));
            }

            wc_add_notice(__('Înregistrarea a fost realizată cu succes. Ești acum autentificat.', 'vogo'), 'success');
            error_log(sprintf('[VOGO-REG] S10 DONE auto-login + redirect home | uid=%d', (int)$user_id));

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