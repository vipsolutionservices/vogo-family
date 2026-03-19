<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fetch email templates.
 *
 * @param string $template_name The template name (e.g., 'email-confirmation').
 * @param array $data Data to replace placeholders.
 * @return string The processed email template.
 */
function vogo_get_email_template($template_name, $data = array()) {
    $templates = array(
        'email-confirmation' => "
            <p>Hi {user_name},</p>
            <p>Please click the link below to confirm your email address:</p>
            <p><a href='{confirm_url}'>Confirm Email</a></p>
            <p>If you didn’t register, please ignore this email.</p>
        ",
        'welcome-email' => "
            <p>Hi {user_name},</p>
            <p>Welcome to Vogo! We’re excited to have you on board.</p>
            <p>Start exploring our platform today.</p>
        "
    );

    if (!isset($templates[$template_name])) {
        return '';
    }

    $template = $templates[$template_name];

    foreach ($data as $key => $value) {
        $template = str_replace('{' . $key . '}', $value, $template);
    }

    return $template;
}
