<?php
/**
 * Frontend Module: Category badges/info block
 * Provides a shortcode [category_location_info] to display Mall Locations + Cities on category pages.
 * You can also hook it automatically if desired.
 */

add_shortcode('category_location_info', function($atts){
  if(!is_product_category()) return '';
  global $wpdb; $p=$wpdb->prefix;

  $term_id = get_queried_object_id();
  $mall_locations = get_term_meta($term_id,'mall_location',true);
  $mall_locations = maybe_unserialize($mall_locations);
  if(!is_array($mall_locations)) $mall_locations=[];

  // Cities (names) resolved from IDs in mapping table
  $city_ids = array_map('intval',$wpdb->get_col($wpdb->prepare("SELECT city_id FROM {$p}product_category_cities WHERE category_id=%d",$term_id)));
  $cities = function_exists('get_cities_list') ? get_cities_list() : [];
  $city_map = [];
  foreach($cities as $c){ $city_map[(int)$c['id']] = $c['name']; }
  $city_names = [];
  foreach($city_ids as $cid){ if(isset($city_map[$cid])) $city_names[] = $city_map[$cid]; }

  ob_start(); ?>
  <div class="category-location-info" style="margin:10px 0;">
    <?php if(!empty($mall_locations)): ?>
      <p><strong>Mall Locations:</strong> <?php echo esc_html(implode(', ', $mall_locations)); ?></p>
    <?php endif; ?>
    <?php if(!empty($city_names)): ?>
      <p><strong>Available in Cities:</strong> <?php echo esc_html(implode(', ', $city_names)); ?></p>
    <?php endif; ?>
  </div>
  <?php
  return ob_get_clean();
});

/** (Optional) Auto-inject below category description */
// add_action('woocommerce_archive_description', function(){
//   echo do_shortcode('[category_location_info]');
// }, 20);
