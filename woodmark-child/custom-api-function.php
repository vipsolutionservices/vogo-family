<?php
	function custom_log($message){
		//if (WP_DEBUG === true) { // Only log if debugging is enabled
			$upload_dir = wp_upload_dir();
			$filename = '/custom-log'.time().'-'.rand().'.txt';
			
		   $log_file = $upload_dir['basedir'] . $filename;
		
			$time = current_time('mysql');

			$log_entry = '[' . $time . '] ' . print_r($message, true) . PHP_EOL;
			file_put_contents($log_file, $log_entry, FILE_APPEND);
		//}
	}
	

	// Hook into admin_init to register settings
add_action('admin_init', 'register_twilio_settings');

function register_twilio_settings() {
    // Register settings for Twilio fields
    register_setting('general', 'twilio_account_sid', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => ''
    ));
    register_setting('general', 'twilio_auth_token', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => ''
    ));
    register_setting('general', 'twilio_phone_number', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => ''
    ));

    // Add settings fields to the General Settings page
    add_settings_field(
        'twilio_account_sid',
        __('Twilio Account SID', 'text_domain'),
        'twilio_account_sid_callback',
        'general',
        'default',
        array('label_for' => 'twilio_account_sid')
    );
    add_settings_field(
        'twilio_auth_token',
        __('Twilio Auth Token', 'text_domain'),
        'twilio_auth_token_callback',
        'general',
        'default',
        array('label_for' => 'twilio_auth_token')
    );
    add_settings_field(
        'twilio_phone_number',
        __('Twilio Phone Number', 'text_domain'),
        'twilio_phone_number_callback',
        'general',
        'default',
        array('label_for' => 'twilio_phone_number')
    );
}

// Callback functions to render the input fields
function twilio_account_sid_callback($args) {
    $value = get_option('twilio_account_sid');
    ?>
    <input type="text" id="twilio_account_sid" name="twilio_account_sid" value="<?php echo esc_attr($value); ?>" class="regular-text" />
    <p class="description"><?php _e('Enter your Twilio Account SID.', 'text_domain'); ?></p>
    <?php
}
// Twilio Auth token Funcations
function twilio_auth_token_callback($args) {
    $value = get_option('twilio_auth_token');
    ?>
    <input type="text" id="twilio_auth_token" name="twilio_auth_token" value="<?php echo esc_attr($value); ?>" class="regular-text" />
    <p class="description"><?php _e('Enter your Twilio Auth Token.', 'text_domain'); ?></p>
    <?php
}
// Twilio Phone Number Funcations
function twilio_phone_number_callback($args) {
    $value = get_option('twilio_phone_number');
    ?>
    <input type="text " id="twilio_phone_number" name="twilio_phone_number" value="<?php echo esc_attr($value); ?>" class="regular-text" />
    <p class="description"><?php _e('Enter your Twilio Phone Number (e.g., +1234567890).', 'text_domain'); ?></p>
    <?php
}




