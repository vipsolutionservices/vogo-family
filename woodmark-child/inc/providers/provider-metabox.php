<?php
// inc/providers/provider-metabox.php
// NOT USED

// --- Hide legacy taxonomy boxes on Product edit (defense in depth)
add_action('add_meta_boxes', function () {
  remove_meta_box('product_providerdiv', 'product', 'side');        // hierarchical
  remove_meta_box('tagsdiv-product_provider', 'product', 'side');   // non-hierarchical
}, 100);

// --- Add our meta box (providers from wp_vogo_providers)
add_action('add_meta_boxes_product', function () {
  add_meta_box('vogo_provider_box', 'Vogo Provider', 'vogo_render_provider_box', 'product', 'side', 'high');
});

/** Render provider dropdown */
function vogo_render_provider_box(WP_Post $post) {
  global $wpdb;

  wp_nonce_field('vogo_provider_box', 'vogo_provider_box_nonce');

  // Current mapping
  $current_vendor_id = (int)$wpdb->get_var($wpdb->prepare(
    "SELECT vendor_id FROM {$wpdb->prefix}vogo_product_vendor WHERE product_id=%d LIMIT 1",
    $post->ID
  ));

  // Providers list
  $providers = $wpdb->get_results(
    "SELECT id, provider_name
       FROM {$wpdb->prefix}vogo_providers
      WHERE status='active'
      ORDER BY provider_name ASC",
    ARRAY_A
  );

  echo '<p><label for="vogo_provider_id"><strong>Select provider</strong></label></p>';
  echo '<select id="vogo_provider_id" name="vogo_provider_id" style="width:100%">';
  echo '<option value="0">(None)</option>';
  foreach ($providers as $p) {
    printf(
      '<option value="%d"%s>%s</option>',
      (int)$p['id'],
      selected($current_vendor_id, (int)$p['id'], false),
      esc_html($p['provider_name'])
    );
  }
  echo '</select>';

  if ($current_vendor_id) {
    $name = $wpdb->get_var($wpdb->prepare(
      "SELECT provider_name FROM {$wpdb->prefix}vogo_providers WHERE id=%d", $current_vendor_id
    ));
    echo '<p style="margin-top:8px;color:#555">Current: <code>'.esc_html($name ?: ('#'.$current_vendor_id)).'</code></p>';
  }
}