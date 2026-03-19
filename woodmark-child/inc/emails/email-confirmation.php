<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Send confirmation email on WooCommerce registration
function vogo_send_email_confirmation($user_id) {
    $user = get_userdata($user_id);
    $email = $user->user_email;

    // Generate a unique token
    $token = wp_generate_password(32, false);

    // Store token in user meta
    update_user_meta($user_id, '_email_confirmation_token', $token);
    update_user_meta($user_id, '_email_confirmed', 0);

    // Generate confirmation link
    $confirm_url = add_query_arg(array(
        'confirm_email' => $token,
        'user_id' => $user_id
    ), site_url('/confirm-email/'));

    // Get email template
    $message = vogo_get_email_template('email-confirmation', array(
        'user_name' => $user->display_name,
        'confirm_url' => $confirm_url
    ));

    // Send email
    $subject = "Confirm Your Email Address";
    wp_mail($email, $subject, $message, array('Content-Type: text/html; charset=UTF-8'));
}
add_action('woocommerce_created_customer', 'vogo_send_email_confirmation');

// Email verification handler
function vogo_verify_email_confirmation() {
    if (!isset($_GET['confirm_email']) || !isset($_GET['user_id'])) {
        return;
    }

    $user_id = intval($_GET['user_id']);
    $token = sanitize_text_field($_GET['confirm_email']);

    // Retrieve stored token
    $stored_token = get_user_meta($user_id, '_email_confirmation_token', true);

    if ($token === $stored_token) {
        // Confirm the email
        update_user_meta($user_id, '_email_confirmed', 1);
        delete_user_meta($user_id, '_email_confirmation_token');

        // Redirect to success page
        wp_redirect(site_url('/email-confirmed/'));
        exit;
    } else {
        wp_redirect(site_url('/email-confirmation-failed/'));
        exit;
    }
}
add_action('init', 'vogo_verify_email_confirmation');

// Restrict login for unconfirmed users
function vogo_restrict_login_for_unconfirmed_email($user, $username, $password) {
    if ($user instanceof WP_User) {
        $confirmed = get_user_meta($user->ID, '_email_confirmed', true);

        if ($confirmed != 1) {
            return new WP_Error('email_not_confirmed', 'Your email is not confirmed. Please check your inbox.');
        }
    }
    return $user;
}
add_filter('wp_authenticate_user', 'vogo_restrict_login_for_unconfirmed_email', 10, 3);
