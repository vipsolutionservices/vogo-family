<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Elementor_City_Address_Widget extends \Elementor\Widget_Base {

    // Widget Name
    public function get_name() {
        return 'city_address_widget';
    }

    // Widget Title
    public function get_title() {
        return 'Selectarea orașului și adresei';
    }

    // Widget Icon
    public function get_icon() {
        return 'eicon-select';
    }

    // Widget Categories
    public function get_categories() {
        return ['general'];
    }

    // Register widget controls (Currently no additional settings)
    protected function _register_controls() {}

    // Render the widget (Frontend)
    protected function render() {
        ?>
        <div class="woocommerce-city-address">
            <!-- Address Dropdown -->
            <label for="address_select">Selectează Adresa:</label>
            <?php echo $this->get_address_dropdown(); ?>

            <!-- Product Categories Grid -->
            <div id="category_grid_container">
                <!-- Categories will be loaded here via AJAX -->
            </div>
        </div>
        <?php
    }

    // Fetch Selected City from Session/Cookie
    private function get_selected_city() {
        if (isset($_SESSION['selected_city'])) {
            return sanitize_text_field($_SESSION['selected_city']);
        } elseif (isset($_COOKIE['selected_city'])) {
            return sanitize_text_field($_COOKIE['selected_city']);
        }
        return ''; // No city selected
    }

    // Get User's Saved Addresses from the user_addresses Table
    private function get_address_dropdown() {
        $user_id = get_current_user_id();
    
        // Check if user is logged in
        if ($user_id == 0) {
            return '<p>Te Rog <a href="' . esc_url(wp_login_url()) . '">log in</a> pentru a selecta o adresă.</p>';
        }
    
        $selected_city = $this->get_selected_city(); // Get selected city from session/cookie
    
        // Fetch and unserialize user addresses from user meta
        $addresses = maybe_unserialize(get_user_meta($user_id, '_user_addresses', true));
    
        // Ensure the addresses variable is a valid array
        if (!is_array($addresses)) {
            $addresses = [];
        }
    
        // Filter addresses matching the selected city
        $filtered_addresses = array_filter($addresses, function ($address) use ($selected_city) {
            return isset($address['city']) && strtolower(trim($address['city'])) === strtolower(trim($selected_city));
        });
    
        if (!empty($filtered_addresses)) {
            $dropdown = '<select id="address_select" name="address" class="woocommerce-input-wrapper">';
            $dropdown .= '<option value="">Select Address</option>';
    
            foreach ($filtered_addresses as $address) {
                $dropdown .= "<option value='" . esc_attr($address['address']) . "'>" . esc_html($address['address']) . "</option>";
            }
    
            $dropdown .= '</select>';
        } else {
            $dropdown = '<p>Nicio adresă salvată  a găsită pentru ' . esc_html($selected_city) . '.</p>';
        }
    
        return $dropdown;
    }
    
}