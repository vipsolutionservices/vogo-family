<?php
/*
add_action('woocommerce_login_form_start', function () {
	echo '<div class="both-login"  style="display: flex; flex-wrap: wrap;">';
 echo '</div>';
});
*/
add_shortcode('wc_login_form_custom', function () {
    if (is_user_logged_in()) return '<p>Ești deja autentificat</p>';

    ob_start();

    echo '<script src="https://www.google.com/recaptcha/api.js" async defer></script>';
    do_action('woocommerce_before_customer_login_form');

    echo '<form method="post" class="woocommerce-form woocommerce-form-login login">';

    // Username field
    echo'<div class="floating-group">';
    echo '<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">';
    //echo '<label for="username">' . __('Username or email address') . '&nbsp;<span class="required">*</span></label>';
    echo '<input type="text" name="username" id="username" placeholder=" " autocomplete="username" />';
    echo ' <label for="username">Adresa de email *</label>';
    echo '</p></div>';

    // Password field
     echo'<div class="floating-group">';
    echo '<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">';
    //echo '<label for="password">' . __('Parolă ') . '&nbsp;<span class="required">*</span></label>';
    echo '<input type="password" name="password" id="password" placeholder=" " autocomplete="current-password" />';
    echo '<label for="password">Parolă *</label>';
    echo '</p></div>';

    // Remember me checkbox and register link row
    echo '<p class="form-row" style="display: flex; justify-content: space-between; align-items: center;">';

    // Remember me
    echo '<label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme">';
    echo '<input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" />';
    echo '<span>Ține-mă minte</span>';
    echo '</label>';

    // Register and forgot password links
    echo '<span class="woocommerce-links">';
    echo '<a href="https://test07.vogo.family/register/" class="woocommerce-register-link">Înregistrare</a>';
    echo ' | ';
    echo '<a href="' . esc_url(wp_lostpassword_url()) . '" class="woocommerce-lostpassword-link">Ai uitat parola?</a>';
    echo '</span>';

    echo '</p>';

    // Row with login button, recaptcha, and social login
    echo '<div class="custom-login-footer" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center; margin-top: 20px;">';

    echo '<div class="g-recaptcha" data-sitekey="' . esc_attr(MY_RECAPTCHA_V2_SITE_KEY) . '" style="flex: 1; min-width: 200px;"></div>';

    echo '<button type="submit" class="woocommerce-button button woocommerce-form-login__submit" name="login" value="' . esc_attr__('Autentificare', 'woocommerce') . '" style="flex: 1; min-width: 150px;">Autentificare</button>';

    echo '<div class="mo-google-login" style="flex: 1; min-width: 200px;">';
    echo do_shortcode('[nextend_social_login]');
    echo '</div>';

    echo '</div>';

    echo '<input type="hidden" name="woocommerce-login-nonce" value="' . wp_create_nonce('woocommerce-login') . '" />';
    echo '<input type="hidden" name="_wp_http_referer" value="' . esc_url($_SERVER['REQUEST_URI']) . '" />';
    echo '<input type="hidden" name="login" value="Login" />';

    echo '</form>';

    return ob_get_clean();
});

// Redirect logged-in users away from the login page
add_action('template_redirect', function () {
    if (is_page() && is_user_logged_in() &&
        (has_shortcode(get_the_content(), 'wc_login_form_custom') || has_shortcode(get_the_content(), 'wc_reg_form_bbloomer'))) {
        wp_safe_redirect(wc_get_page_permalink('myaccount'));
        exit;
    }
});

// Validate reCAPTCHA on login form submission
add_action('woocommerce_process_login_form', function ($username) {
    if (!isset($_POST['g-recaptcha-response']) || empty($_POST['g-recaptcha-response'])) {
        wc_add_notice(__('Vă rugăm să completați reCAPTCHA.', 'your-textdomain'), 'error');
        return;
    }

    $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
        'body' => [
            'secret'   => MY_RECAPTCHA_V2_SECRET_KEY,
            'response' => sanitize_text_field($_POST['g-recaptcha-response']),
            'remoteip' => $_SERVER['REMOTE_ADDR'],
        ],
    ]);

    if (is_wp_error($response)) {
        wc_add_notice(__('Nu s-a putut verifica reCAPTCHA. Vă rugăm să încercați din nou.', 'your-textdomain'), 'error');
        return;
    }

    $result = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($result['success'])) {
        wc_add_notice(__('reCAPTCHA a eșuat. Vă rugăm să încercați din nou.', 'your-textdomain'), 'error');
    }
}, 10, 1);

add_filter('authenticate', function ($user, $username, $password) {
    // Only run on login form, not XML-RPC, REST, etc.
    if (defined('DOING_AJAX') && DOING_AJAX) return $user;
    if (defined('REST_REQUEST') && REST_REQUEST) return $user;
    if (wp_doing_cron()) return $user;

    // Only block if not already errored (e.g. wrong password)
    if (is_wp_error($user)) {
        return $user;
    }

    // Skip reCAPTCHA for logged-in users or non-login pages
    if (is_user_logged_in() || !isset($_POST['g-recaptcha-response'])) {
        return $user;
    }

    // Verify reCAPTCHA
    $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
        'body' => [
            'secret'   => MY_RECAPTCHA_V2_SECRET_KEY,
            'response' => sanitize_text_field($_POST['g-recaptcha-response']),
            'remoteip' => $_SERVER['REMOTE_ADDR'],
        ]
    ]);

    if (is_wp_error($response)) {
        return new WP_Error('recaptcha_failed', __('Nu s-a putut verifica reCAPTCHA. Vă rugăm să încercați din nou.', 'your-textdomain'));
    }

    $result = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($result['success'])) {
        return new WP_Error('recaptcha_invalid', __('Validarea reCAPTCHA a eșuat. Vă rugăm să încercați din nou.', 'your-textdomain'));
    }

    return $user;
}, 30, 3);
