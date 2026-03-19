<?php
if (!function_exists('get_cities_list')) {
    function get_cities_list() {
        $file_path = get_stylesheet_directory() . '/inc/data/cities.json';
        if (file_exists($file_path)) {
            $json = file_get_contents($file_path);
            $data = json_decode($json, true);
            return $data['cities'] ?? [];
        }
        return [];
    }
}

add_action('wp_footer', function() {
    if (is_account_page()) { ?>
        <script>
            jQuery(document).ready(function($) {
                // Apply Select2 to the City dropdown
                $('#city').selectWoo();
            });
        </script>
    <?php }
});

function render_custom_address_form() {
    global $wpdb;
    $user_id = get_current_user_id();
    
    // Fetch saved addresses
    $addresses = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}user_addresses WHERE user_id = %d AND status = 'active'",
        $user_id
    ));

  //  echo '<p><a href="' . esc_url(wc_get_account_endpoint_url('edit-address')) . '">← Înapoi la Adrese</a></p>';
    ?>
    <h3>Adaugă adresă nouă</h3>
    <form method="POST" class="add-additional-addresses">
        <input type="hidden" name="address_id" id="address_id" value=""> <!-- Hidden field for editing -->
        <div class="floating-group">
        <div class="address-profile">
            <input type="text" name="address_name" id="address_name" placeholder=" " required>
            <label for="address_name">Nume adresă (ex: Acasă, Birou)</label>
        </div></div>
<div class="floating-group">
        <p class="address-profile">
            <input type="text" name="street_address" id="street_address" placeholder=" " required>
            <label for="street_address">Adresă stradală:</label>
        </p></div>
<div class="floating-group">
        <p class="address-profile">
            <input type="text" name="street_address_2" id="street_address_2" placeholder=" ">
             <label for="street_address_2">Adresă stradală 2</label>
        </p></div>
        <!-- can we use shipping address county field here? -->
        <?php  $country_code = 'RO'; // Set to Romania or dynamically get from user meta
        $states = WC()->countries->get_states($country_code); ?>
        <p class="address-profile">
        <!-- <label for="billing_state">Județ:</label> -->
        <?php
        $customer = new WC_Customer(get_current_user_id()); // Get the current customer
        $country  = $customer->get_billing_country(); // Get user's country (default: billing country)
        $billing_state = $customer->get_billing_state(); // Get saved state/county

        woocommerce_form_field('billing_state', array(
            'type'     => 'state',
            'label'    => false,
            'required' => true,
            'class'    => array('wc-enhanced-select'), // WooCommerce searchable dropdown class
        ), $billing_state);
        ?>
        </p>
        <p class="address-profile">
       <!--  <label for="city">Oraș:</label> -->
        <select name="city" id="city" class="wc-enhanced-select" required>
            <option value="">Selectează Orașul</option>
            <?php
            $cities = get_cities_list(); // Fetch cities from your JSON file
            foreach ($cities as $city) :
                echo '<option value="' . esc_attr($city) . '">' . esc_html($city) . '</option>';
            endforeach;
            ?>
        </select>
     </p>
<div class="floating-group">
        <p class="address-profile">
            <input type="text" name="address_code" id="address_code" required pattern="\d{6}" title="Introduceți un cod poștal valid de 6 cifre" placeholder=" ">
            <label for="address_code">Cod Adresă</label>
        </p></div>

        <p>
            <input type="submit" name="save_address" value="Salvează adresa">
        </p>
    </form>
	<script>
	document.addEventListener("DOMContentLoaded", function () {
		const form = document.querySelector(".add-additional-addresses");
		if (form) {
			// Select and remove all <br> tags inside the form
			form.querySelectorAll("br").forEach(br => br.remove());
		}
	});
	</script>
    <h4>Adresă Salvată</h4>

        <?php if (!empty($addresses)) : ?>

     <table style="width:100%; border-collapse: collapse; " class="address-table">
        <thead>
            <tr>
                <th style="border: 1px solid #ddd; padding: 10px;">Nume Adresă</th>
                <th style="border: 1px solid #ddd; padding: 10px;">Adresă Stradală</th>
                <th style="border: 1px solid #ddd; padding: 10px;">Oraș</th>
                <th style="border: 1px solid #ddd; padding: 10px;">Acțiuni</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($addresses as $address) : ?>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 10px;"><?php echo esc_html($address->address_name ?? 'N/A'); ?></td>
                    <td style="border: 1px solid #ddd; padding: 10px;"><?php echo esc_html($address->street_address ?? 'N/A'); ?></td>
                   
                    <td style="border: 1px solid #ddd; padding: 10px;"><?php echo esc_html($address->city ?? ''); ?></td>
                
                    <td style="border: 1px solid #ddd; padding: 6px;">
                        <a href="?edit_address=<?php echo esc_attr($address->id); ?>" class="dash-btn">Editează</a>
                <a href="<?php echo esc_url(add_query_arg('delete_address', $address->id)); ?>" class="dash-delete-button" onclick="return confirm('Ești sigur(ă) că vrei să ștergi această adresă?');">Șterge</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else : ?>
       <p>Nicio adresă salvată.</p>
    <?php endif; ?>



    <?php
    // If an address is being edited, fetch it and prefill the form
    if (isset($_GET['edit_address'])) {
        $edit_id = intval($_GET['edit_address']);
        $edit_address = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}user_addresses WHERE id = %d AND user_id = %d",
            $edit_id, $user_id
        ));

        if ($edit_address) {
            ?>
                <script>
                    document.getElementById('address_id').value = "<?php echo esc_attr($edit_address->id); ?>";
                    document.getElementById('address_name').value = "<?php echo esc_js($edit_address->address_name); ?>";
                    document.getElementById('street_address').value = "<?php echo esc_js($edit_address->street_address); ?>";
                    document.getElementById('street_address_2').value = "<?php echo esc_js($edit_address->street_address_2); ?>";
                    document.getElementById('city').value = "<?php echo esc_js($edit_address->city); ?>";
                    document.getElementById('address_code').value = "<?php echo esc_js($edit_address->address_code); ?>";
            
                    // WooCommerce enhanced select fields require jQuery to set the value
                    jQuery(function($) {
                        $('#billing_state').val("<?php echo esc_js($edit_address->county); ?>").trigger('change');
                        $('#city').val("<?php echo esc_js($edit_address->city); ?>").trigger('change');
                    });
                </script>
            <?php
            }
        
    }
}

function save_user_address() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_address'])) {
        error_log("Saving user address for user ID: " . get_current_user_id());
        error_log(print_r($_POST, true));

        global $wpdb;
        $user_id = get_current_user_id();
        $address_id = isset($_POST['address_id']) ? intval($_POST['address_id']) : 0;
        $address_name = isset($_POST['address_name']) ? sanitize_text_field($_POST['address_name']) : '';
        $street_address = isset($_POST['street_address']) ? sanitize_text_field($_POST['street_address']) : '';
        $street_address_2 = isset($_POST['street_address_2']) ? sanitize_text_field($_POST['street_address_2']) : '';
        $county = isset($_POST['billing_state']) ? sanitize_text_field($_POST['billing_state']) : '';
        $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
        $address_code = isset($_POST['address_code']) ? sanitize_text_field($_POST['address_code']) : '';

        if (!empty($address_name) && !empty($street_address) && !empty($city) && !empty($address_code)) {
            if ($address_id > 0) {
                // Update existing address
                $wpdb->update(
                    $wpdb->prefix . 'user_addresses',
                    [
                        'address_name' => $address_name,
                        'street_address' => $street_address,
                        'street_address_2' => $street_address_2,
                        'county' => $county,
                        'city' => $city,
                        'address_code' => $address_code
                    ],
                    ['id' => $address_id, 'user_id' => $user_id],
                    ['%s', '%s', '%s', '%s', '%s', '%s'],
                    ['%d', '%d']
                );
            } else {
                // Insert new address
                $wpdb->insert(
                    $wpdb->prefix . 'user_addresses',
                    [
                        'user_id' => $user_id,
                        'address_name' => $address_name,
                        'street_address' => $street_address,
                        'street_address_2' => $street_address_2,
                        'county' => $county,
                        'city' => $city,
                        'address_code' => $address_code,
                        'status' => 'active'
                    ],
                    ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
                );
            }

            // Redirect after saving
            wp_redirect(wc_get_account_endpoint_url('edit-address'));
            exit;
        } else {
            error_log("Lipsesc câmpuri obligatorii, adresa nu a fost salvată.");
        }
    }
}
add_action('wp', 'save_user_address'); // Always hook this function


function delete_user_address() {
    if (isset($_GET['delete_address'])) {
        global $wpdb;
        $user_id = get_current_user_id();
        $address_id = intval($_GET['delete_address']);

        // Ensure only the owner can delete
        $wpdb->delete(
            $wpdb->prefix . 'user_addresses',
            ['id' => $address_id, 'user_id' => $user_id],
            ['%d', '%d']
        );

        echo '<p style="color:green;">Adresa a fost ștearsă cu succes!</p>';
        wp_redirect(wc_get_account_endpoint_url('edit-address'));

    }
}
add_action('wp', 'delete_user_address'); 


add_action('woocommerce_account_edit-address_endpoint', function() {
    // Only render on /my-account/edit-address/ (not /billing or /shipping)
    $current_sub_endpoint = get_query_var('edit-address');
    if (empty($current_sub_endpoint)) {
        render_custom_address_form();
    }
});