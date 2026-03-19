<?php
/**
 * Admin Module: product_cat fields for Mall Locations (term_meta) + Cities (custom table)
 * Cities are stored in wp_product_category_cities (category_id, city_id).
 * Includes a tiny installer to ensure the custom table exists.
 */

add_action('admin_init', function(){
  if(!is_admin()) return;
  ensure_product_category_cities_table();
});

/** Ensure custom mapping table exists (dbDelta safe) */
function ensure_product_category_cities_table(){
  global $wpdb; $p=$wpdb->prefix; $charset = $wpdb->get_charset_collate();
  require_once ABSPATH.'wp-admin/includes/upgrade.php';
  $sql_main = "CREATE TABLE {$p}product_category_cities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id BIGINT UNSIGNED NOT NULL,
    city_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT DEFAULT NULL,
    modified_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    modified_by BIGINT DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_category_city (category_id,city_id),
    KEY idx_category (category_id),
    KEY idx_city (city_id)
  ) $charset;";
  dbDelta($sql_main);
}

/** Render fields (ADD + EDIT) */
add_action('product_cat_add_form_fields','vogo_product_cat_fields');
add_action('product_cat_edit_form_fields','vogo_product_cat_fields');
function vogo_product_cat_fields($term){
  if(!current_user_can('manage_product_terms')) return;
  global $wpdb; $p=$wpdb->prefix; $ip=$_SERVER['REMOTE_ADDR']??'UNKNOWN'; $uid=get_current_user_id()?:0;
  $term_id = (is_object($term) && isset($term->term_id)) ? (int)$term->term_id : 0;

  vogo_error_log3("VOGO_LOG_START | [product_cat form] term_id:$term_id | IP:$ip | USER:$uid");

  // [STEP 1] Mall Locations list (distinct city names from mall_locations)
  $mall_locations = $wpdb->get_results("SELECT DISTINCT city FROM {$p}mall_locations WHERE city<>'' ORDER BY city ASC");
  if($wpdb->last_error) vogo_error_log3("[product_cat form][DB ERROR mall_locations] {$wpdb->last_error}");

  // [STEP 2] Current mall locations (term_meta string array)
  $current_mall_locations = $term_id ? get_term_meta($term_id,'mall_location',true) : [];
  $current_mall_locations = maybe_unserialize($current_mall_locations);
  if(!is_array($current_mall_locations)) $current_mall_locations=[];

  // [STEP 3] Cities list (id+name) via helper
  $cities = function_exists('get_cities_list') ? get_cities_list() : [];

  // [STEP 4] Current cities via custom table
  $current_cities = ($term_id>0) ? array_map('intval',$wpdb->get_col($wpdb->prepare("SELECT city_id FROM {$p}product_category_cities WHERE category_id=%d",$term_id))) : [];
  if($wpdb->last_error) vogo_error_log3("[product_cat form][DB ERROR category_cities] {$wpdb->last_error}");
  ?>
  <tr class="form-field">
    <th scope="row" valign="top"><label for="mall_location">Mall Locations</label></th>
    <td>
      <button type="button" id="select_all_malls" class="button">Select All</button>
      <button type="button" id="clear_all_malls" class="button">Clear All</button>
      <select name="mall_location[]" id="mall_location" multiple="multiple" style="width:300px;height:200px;">
        <?php if($mall_locations): foreach($mall_locations as $loc): ?>
          <option value="<?php echo esc_attr($loc->city); ?>" <?php echo in_array($loc->city,$current_mall_locations,true)?'selected':''; ?>>
            <?php echo esc_html($loc->city); ?>
          </option>
        <?php endforeach; endif; ?>
      </select>
      <?php wp_nonce_field('save_product_category_locations','product_category_locations_nonce'); ?>
      <p class="description">Hold Ctrl/Cmd to select multiple.</p>
    </td>
  </tr>

  <tr class="form-field">
    <th scope="row" valign="top"><label for="product_category_cities">Cities</label></th>
    <td>
      <button type="button" id="select_all_cities" class="button">Select All</button>
      <button type="button" id="clear_all_cities" class="button">Clear All</button>
      <select name="product_category_cities[]" id="product_category_cities" multiple="multiple" style="width:300px;height:200px;">
        <?php foreach($cities as $city): ?>
          <option value="<?php echo esc_attr($city['id']); ?>" <?php echo in_array((int)$city['id'],$current_cities,true)?'selected':''; ?>>
            <?php echo esc_html($city['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php wp_nonce_field('save_product_category_cities','product_category_cities_nonce'); ?>
      <p class="description">Hold Ctrl/Cmd to select multiple.</p>
    </td>
  </tr>

  <script>
    jQuery(function($){
      $('#select_all_malls').on('click', ()=> $('#mall_location option').prop('selected', true));
      $('#clear_all_malls').on('click', ()=> $('#mall_location option').prop('selected', false));
      $('#select_all_cities').on('click', ()=> $('#product_category_cities option').prop('selected', true));
      $('#clear_all_cities').on('click', ()=> $('#product_category_cities option').prop('selected', false));
    });
  </script>
  <?php
  vogo_error_log3("VOGO_LOG_END | [product_cat form] term_id:$term_id");
}

/** Save handler for ADD + EDIT */
add_action('create_product_cat','vogo_product_cat_save');
add_action('edited_product_cat','vogo_product_cat_save');
function vogo_product_cat_save($term_id){
  if(!current_user_can('manage_product_terms')) return;
  global $wpdb; $p=$wpdb->prefix;
  $ip=$_SERVER['REMOTE_ADDR']??'UNKNOWN'; $uid=get_current_user_id()?:0;
  vogo_error_log3("VOGO_LOG_START | [product_cat save] term_id:$term_id | IP:$ip | USER:$uid");

  // [STEP 1] Save Mall Locations in term_meta
  if(isset($_POST['product_category_locations_nonce']) && wp_verify_nonce($_POST['product_category_locations_nonce'],'save_product_category_locations')){
    if(!empty($_POST['mall_location']) && is_array($_POST['mall_location'])){
      $mall_locations = array_map('sanitize_text_field', $_POST['mall_location']);
      update_term_meta($term_id,'mall_location', maybe_serialize($mall_locations));
    } else {
      delete_term_meta($term_id,'mall_location');
    }
  } else {
    vogo_error_log3("[product_cat save] mall locations nonce invalid/missing");
  }

  // [STEP 2] Save Cities in custom table
  if(isset($_POST['product_category_cities_nonce']) && wp_verify_nonce($_POST['product_category_cities_nonce'],'save_product_category_cities')){
    $table = "{$p}product_category_cities";

    // delete existing
    $sql_del = $wpdb->prepare("DELETE FROM $table WHERE category_id=%d",$term_id);
    vogo_error_log3("##############SQL: $sql_del");
    $wpdb->query($sql_del);
    if($wpdb->last_error) vogo_error_log3("[product_cat save][DEL ERROR] {$wpdb->last_error}");

    // insert new
    $posted = isset($_POST['product_category_cities']) ? (array)$_POST['product_category_cities'] : [];
    $city_ids = array_values(array_unique(array_map('intval', $posted)));

    foreach($city_ids as $cid){
      if($cid<=0) continue;
      $ins = $wpdb->prepare("INSERT INTO $table (category_id,city_id,created_by,modified_by) VALUES (%d,%d,%d,%d)", $term_id,$cid,$uid,$uid);
      vogo_error_log3("##############SQL: $ins");
      $wpdb->query($ins);
      if($wpdb->last_error) vogo_error_log3("[product_cat save][INS ERROR] {$wpdb->last_error}");
    }
    vogo_error_log3("[product_cat save] saved cities count: ".count($city_ids)." for term=$term_id");
  } else {
    vogo_error_log3("[product_cat save] cities nonce invalid/missing");
  }

  vogo_error_log3("VOGO_LOG_END | [product_cat save] term_id:$term_id");
}
