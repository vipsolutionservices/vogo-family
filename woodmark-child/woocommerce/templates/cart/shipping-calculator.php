<?php
// Shipping Calculator (Cart) with floating labels for: country, state, city, postcode, address_1, address_2, street_number, phone, email

$customer   = WC()->customer;
$country    = $customer ? $customer->get_shipping_country() : '';
$state      = $customer ? $customer->get_shipping_state() : '';
$city       = $customer ? $customer->get_shipping_city() : '';
$postcode   = $customer ? $customer->get_shipping_postcode() : '';
$address_1  = $customer ? $customer->get_shipping_address_1() : '';
$address_2  = $customer ? $customer->get_shipping_address_2() : '';
$street_no  = WC()->session ? WC()->session->get('vogo_shipping_street_number') : get_user_meta(get_current_user_id(),'shipping_street_number',true);
$phone      = $customer ? $customer->get_billing_phone() : get_user_meta(get_current_user_id(),'billing_phone',true);
$email      = $customer ? $customer->get_billing_email() : get_user_meta(get_current_user_id(),'billing_email',true);
?>

<section class="woocommerce-shipping-calculator">
  <h2 class="woocommerce-shipping-calculator__title"><?php esc_html_e( 'Change address', 'woocommerce' ); ?></h2>

  <form class="woocommerce-shipping-calculator__form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
    
    <p class="form-row form-row-wide vogo-float">
      <label for="calc_shipping_country"><?php esc_html_e('Country','woocommerce'); ?></label>
      <select id="calc_shipping_country" name="calc_shipping_country" class="country_to_state" rel="calc_shipping_state">
        <?php foreach ( WC()->countries->get_shipping_countries() as $ckey => $cname ) : ?>
          <option value="<?php echo esc_attr( $ckey ); ?>" <?php selected( $country, $ckey ); ?>><?php echo esc_html( $cname ); ?></option>
        <?php endforeach; ?>
      </select>
    </p>

    <p class="form-row form-row-wide vogo-float">
      <label for="calc_shipping_state"><?php esc_html_e('State / County','woocommerce'); ?></label>
      <input id="calc_shipping_state" type="text" name="calc_shipping_state" placeholder=" " value="<?php echo esc_attr( $state ); ?>" />
    </p>

    <p class="form-row form-row-wide vogo-float">
      <label for="calc_shipping_city"><?php esc_html_e('City','woocommerce'); ?></label>
      <input id="calc_shipping_city" type="text" name="calc_shipping_city" placeholder=" " value="<?php echo esc_attr( $city ); ?>" />
    </p>

    <p class="form-row form-row-wide vogo-float">
      <label for="calc_shipping_postcode"><?php esc_html_e('Postal code','woocommerce'); ?></label>
      <input id="calc_shipping_postcode" type="text" name="calc_shipping_postcode" placeholder=" " value="<?php echo esc_attr( $postcode ); ?>" />
    </p>

    <p class="form-row form-row-wide vogo-float">
      <label for="calc_shipping_address_1"><?php esc_html_e('Street address','woocommerce'); ?></label>
      <input id="calc_shipping_address_1" type="text" name="calc_shipping_address_1" placeholder=" " value="<?php echo esc_attr( $address_1 ); ?>" />
    </p>

    <p class="form-row form-row-wide vogo-float">
      <label for="calc_shipping_address_2"><?php esc_html_e('Apartment, suite, etc. (optional)','woocommerce'); ?></label>
      <input id="calc_shipping_address_2" type="text" name="calc_shipping_address_2" placeholder=" " value="<?php echo esc_attr( $address_2 ); ?>" />
    </p>

    <p class="form-row form-row-first vogo-float">
      <label for="calc_shipping_street_number"><?php esc_html_e('Street number','woocommerce'); ?></label>
      <input id="calc_shipping_street_number" type="text" name="calc_shipping_street_number" placeholder=" " value="<?php echo esc_attr( $street_no ); ?>" />
    </p>

    <p class="form-row form-row-last vogo-float">
      <label for="calc_contact_phone"><?php esc_html_e('Contact phone','woocommerce'); ?></label>
      <input id="calc_contact_phone" type="tel" name="calc_contact_phone" placeholder=" " value="<?php echo esc_attr( $phone ); ?>" />
    </p>

    <p class="form-row form-row-wide vogo-float">
      <label for="calc_contact_email"><?php esc_html_e('Contact email','woocommerce'); ?></label>
      <input id="calc_contact_email" type="email" name="calc_contact_email" placeholder=" " value="<?php echo esc_attr( $email ); ?>" />
    </p>

    <p>
      <button type="submit" name="calc_shipping" value="1" class="button"><?php esc_html_e( 'Update', 'woocommerce' ); ?></button>
    </p>

    <?php wp_nonce_field( 'woocommerce-shipping-calculator', 'woocommerce-shipping-calculator-nonce' ); ?>
    <input type="hidden" name="vogo_cart_extra_fields" value="1" />
  </form>
</section>

<!-- Tiny helper to toggle floating label states -->
<script>
(function(){
  function initFloat(el){
    var wrap = el.closest('.vogo-float'); if(!wrap) return;
    function update(){
      if(el.value && String(el.value).trim()!==''){ wrap.classList.add('has-value'); }
      else { wrap.classList.remove('has-value'); }
    }
    el.addEventListener('focus', function(){ wrap.classList.add('is-focus'); });
    el.addEventListener('blur',  function(){ wrap.classList.remove('is-focus'); update(); });
    el.addEventListener('input', update);
    el.addEventListener('change', update);
    update();
  }
  document.querySelectorAll('.vogo-float input, .vogo-float select').forEach(initFloat);
})();
</script>
