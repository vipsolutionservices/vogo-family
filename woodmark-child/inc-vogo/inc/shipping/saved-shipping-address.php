<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Show dropdown for additional shipping addresses in checkout
function add_other_shipping_address_dropdown($checkout) {
    if (!is_user_logged_in()) {
        return;
    }

    global $wpdb;
    $user_id = get_current_user_id();

    // Fetch additional addresses
    $addresses = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}user_addresses WHERE user_id = %d AND status = 'active'",
        $user_id
    ));

    if (!empty($addresses)) {
        echo '<h3>Folosește o adresă salvată</h3>';
        echo '<p>Selectează o adresă din adresele tale salvate pentru a completa automat câmpurile de livrare.</p>';
        echo '<select id="saved_shipping_addresses" class="wc-enhanced-select" style="width: 100%;">';
        echo '<option value="">Selectează o adresă</option>';

        foreach ($addresses as $address) {
            echo '<option value="' . esc_attr(json_encode($address)) . '">'
                . esc_html($address->address_name . ' - ' . $address->street_address . ', ' . $address->city)
                . '</option>';
        }

        echo '</select>';
    }
}
add_action('woocommerce_before_checkout_shipping_form', 'add_other_shipping_address_dropdown');

// Prefill shipping fields when selecting a saved address
function autofill_shipping_address_script() {
  if (!is_checkout()) {
      return;
  }
  ?>
  <script>
      jQuery(document).ready(function($) {
          $('#saved_shipping_addresses').change(function() {
              var addressData = $(this).val();
              if (addressData) {
                  var address = JSON.parse(addressData);

                  // Prefill WooCommerce shipping fields
                  $('#shipping_address_1').val(address.street_address);
                  $('#shipping_address_2').val(address.street_address_2);
                  $('#shipping_postcode').val(address.address_code);

                  // Update WooCommerce State (County) Select Box
                  $('#shipping_state').val(address.county).trigger('change');

                  // Update WooCommerce City Select Box Properly
                  $('#shipping_city').val(address.city).trigger('change');

                  // Debugging - Check if the city value is set properly
                  console.log("Setting City: ", address.city);
              }
          });
      });
  </script>
  <?php
}
add_action('wp_footer', 'autofill_shipping_address_script');



?>