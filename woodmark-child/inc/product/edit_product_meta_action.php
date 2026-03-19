<?php
/**
 * WooCommerce Admin: Product "Action Links" tab (custom table: wp_vogo_product_meta_action)
 * - Adds a Product Data tab to edit action links (camera/zoom/whatsapp/etc)
 * - Loads/saves rows in wp_vogo_product_meta_action (1 row / product)
 * - Uses UPSERT via UNIQUE KEY (product_id)
 *
 * Requirements:
 *   - Table `wp_vogo_product_meta_action` exists and has UNIQUE KEY (`product_id`)
 *   - WooCommerce active
 */

if (!defined('ABSPATH')) { exit; }

/** Safety: load only in admin + Woo active */
if (is_admin() && class_exists('WooCommerce')) {

  /**
   * Add custom tab in Product data
   */
  add_filter('woocommerce_product_data_tabs', function($tabs){
    $tabs['vogo_action_links'] = [
      'label'    => __('Action Links', 'vogo'),
      'target'   => 'vogo_action_links_data',
      'class'    => ['show_if_simple','show_if_variable','show_if_external','show_if_grouped'],
      'priority' => 80,
    ];
    return $tabs;
  });

  /**
   * Render panel with fields
   */
  add_action('woocommerce_product_data_panels', function(){
    global $post, $wpdb; $pfx = $wpdb->prefix;
    $product_id = (int)$post->ID;

    // Load current values from custom table
    $row = $wpdb->get_row($wpdb->prepare("
      SELECT product_main_category_id,
             camera_broadcast_url, zoom_live_call_link, whatsapp_contact_url, suggest_service_link,
             action_whatsapp_label, action_whatsapp_number, action_whatsapp_message,
             action_url_label, action_url_link
      FROM {$pfx}vogo_product_meta_action
      WHERE product_id=%d
      LIMIT 1
    ", $product_id), ARRAY_A);

    $def = array_fill_keys([
      'product_main_category_id',
      'camera_broadcast_url','zoom_live_call_link','whatsapp_contact_url','suggest_service_link',
      'action_whatsapp_label','action_whatsapp_number','action_whatsapp_message',
      'action_url_label','action_url_link'
    ], '');

    $vals = wp_parse_args($row ?: [], $def);

    // Build category dropdown (optional: choose the "main" category for fallbacks)
    $terms = get_the_terms($product_id, 'product_cat') ?: [];
    ?>
    <div id="vogo_action_links_data" class="panel woocommerce_options_panel">
      <?php wp_nonce_field('vogo_save_action_links','vogo_action_links_nonce'); ?>

      <div class="options_group">
        <?php
        // Main Category selector (optional but useful)
        ?>
        <p class="form-field">
          <label for="product_main_category_id"><?php esc_html_e('Main Category (fallback source)', 'vogo'); ?></label>
          <select id="product_main_category_id" name="product_main_category_id" class="wc-enhanced-select" style="min-width: 280px;">
            <option value=""><?php esc_html_e('— Auto (top-level/first) —', 'vogo'); ?></option>
            <?php foreach($terms as $t): ?>
              <option value="<?php echo esc_attr($t->term_id); ?>" <?php selected((int)$vals['product_main_category_id'], (int)$t->term_id); ?>>
                <?php echo esc_html($t->name . ' (ID:'.$t->term_id.')'); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <span class="description"><?php esc_html_e('If empty, API will infer main category automatically.', 'vogo'); ?></span>
        </p>

        <?php
        // URL fields
        woocommerce_wp_text_input([
          'id'=>'camera_broadcast_url',
          'label'=>__('Camera Broadcast URL','vogo'),
          'value'=>esc_attr($vals['camera_broadcast_url']),
          'desc_tip'=>true,
          'description'=>__('URL for live camera broadcast.','vogo'),
          'type'=>'url',
        ]);
        woocommerce_wp_text_input([
          'id'=>'zoom_live_call_link',
          'label'=>__('Zoom Live Call Link','vogo'),
          'value'=>esc_attr($vals['zoom_live_call_link']),
          'desc_tip'=>true,
          'description'=>__('Zoom/Meet link for live video call.','vogo'),
          'type'=>'url',
        ]);
        woocommerce_wp_text_input([
          'id'=>'whatsapp_contact_url',
          'label'=>__('WhatsApp Contact URL','vogo'),
          'value'=>esc_attr($vals['whatsapp_contact_url']),
          'desc_tip'=>true,
          'description'=>__('WhatsApp deeplink (e.g. https://wa.me/...).','vogo'),
          'type'=>'url',
        ]);
        woocommerce_wp_text_input([
          'id'=>'suggest_service_link',
          'label'=>__('Suggest Service Link','vogo'),
          'value'=>esc_attr($vals['suggest_service_link']),
          'desc_tip'=>true,
          'description'=>__('Public page where users can suggest a new service.','vogo'),
          'type'=>'url',
        ]);
      ?>
      </div>

      <div class="options_group">
        <?php
        // WhatsApp action fields
        woocommerce_wp_text_input([
          'id'=>'action_whatsapp_label',
          'label'=>__('WhatsApp Button Label','vogo'),
          'value'=>esc_attr($vals['action_whatsapp_label']),
        ]);
        woocommerce_wp_text_input([
          'id'=>'action_whatsapp_number',
          'label'=>__('WhatsApp Number','vogo'),
          'value'=>esc_attr($vals['action_whatsapp_number']),
          'placeholder'=>'+40...'
        ]);
        woocommerce_wp_textarea_input([
          'id'=>'action_whatsapp_message',
          'label'=>__('WhatsApp Default Message','vogo'),
          'value'=>esc_textarea($vals['action_whatsapp_message']),
        ]);
        woocommerce_wp_text_input([
          'id'=>'action_url_label',
          'label'=>__('Action URL Label','vogo'),
          'value'=>esc_attr($vals['action_url_label']),
        ]);
        woocommerce_wp_text_input([
          'id'=>'action_url_link',
          'label'=>__('Action URL Link','vogo'),
          'value'=>esc_attr($vals['action_url_link']),
          'type'=>'url',
        ]);
        ?>
      </div>
    </div>
    <?php
  });

  /**
   * Save handler (UPSERT into wp_vogo_product_meta_action)
   */
  add_action('woocommerce_admin_process_product_object', function($product){
    if(!current_user_can('edit_products')) return;
    if(!isset($_POST['vogo_action_links_nonce']) || !wp_verify_nonce($_POST['vogo_action_links_nonce'],'vogo_save_action_links')) return;

    global $wpdb; $pfx = $wpdb->prefix;
    $product_id = (int)$product->get_id();
    $uid = get_current_user_id() ?: null;

    // Sanitize incoming fields
    $fields = [
      'product_main_category_id' => 'intval',
      'camera_broadcast_url'     => 'esc_url_raw',
      'zoom_live_call_link'      => 'esc_url_raw',
      'whatsapp_contact_url'     => 'esc_url_raw',
      'suggest_service_link'     => 'esc_url_raw',
      'action_whatsapp_label'    => 'sanitize_text_field',
      'action_whatsapp_number'   => 'sanitize_text_field',
      'action_whatsapp_message'  => 'wp_kses_post',
      'action_url_label'         => 'sanitize_text_field',
      'action_url_link'          => 'esc_url_raw',
    ];
    $data = [];
    foreach($fields as $key=>$cb){
      $val = $_POST[$key] ?? '';
      $data[$key] = $cb ? call_user_func($cb, $val) : $val;
    }

    // Defaults
    $pmc = (int)($data['product_main_category_id'] ?? 0);
    $camera  = $data['camera_broadcast_url'] ?? '';
    $zoom    = $data['zoom_live_call_link'] ?? '';
    $wa_url  = $data['whatsapp_contact_url'] ?? '';
    $sugg    = $data['suggest_service_link'] ?? '';
    $wa_lbl  = $data['action_whatsapp_label'] ?? '';
    $wa_num  = $data['action_whatsapp_number'] ?? '';
    $wa_msg  = $data['action_whatsapp_message'] ?? '';
    $act_lbl = $data['action_url_label'] ?? '';
    $act_url = $data['action_url_link'] ?? '';

    // UPSERT (requires UNIQUE KEY on product_id)
    $sql = $wpdb->prepare("
      INSERT INTO {$pfx}vogo_product_meta_action
        (product_id, product_main_category_id,
         camera_broadcast_url, zoom_live_call_link, whatsapp_contact_url, suggest_service_link,
         action_whatsapp_label, action_whatsapp_number, action_whatsapp_message,
         action_url_label, action_url_link,
         created_by, modified_by)
      VALUES
        (%d, %s,
         %s, %s, %s, %s,
         %s, %s, %s,
         %s, %s,
         %d, %d)
      ON DUPLICATE KEY UPDATE
         product_main_category_id = VALUES(product_main_category_id),
         camera_broadcast_url     = VALUES(camera_broadcast_url),
         zoom_live_call_link      = VALUES(zoom_live_call_link),
         whatsapp_contact_url     = VALUES(whatsapp_contact_url),
         suggest_service_link     = VALUES(suggest_service_link),
         action_whatsapp_label    = VALUES(action_whatsapp_label),
         action_whatsapp_number   = VALUES(action_whatsapp_number),
         action_whatsapp_message  = VALUES(action_whatsapp_message),
         action_url_label         = VALUES(action_url_label),
         action_url_link          = VALUES(action_url_link),
         modified_by              = VALUES(modified_by),
         modified_at              = CURRENT_TIMESTAMP
    ",
      $product_id,
      $pmc ? $pmc : null, // allow NULL
      $camera, $zoom, $wa_url, $sugg,
      $wa_lbl, $wa_num, $wa_msg,
      $act_lbl, $act_url,
      $uid, $uid
    );

    // Because prepare() with %s on NULL converts to empty string, we force NULL via str_replace when needed
    // Quick fix: replace "'NULL'" with NULL if pmc is 0.
    if(!$pmc){
      $sql = preg_replace("/'NULL'/", "NULL", $sql);
    }

    vogo_error_log3("##############SQL UPSERT PMA: " . preg_replace('/\s+/', ' ', $sql));
    $wpdb->query($sql);
    if($wpdb->last_error){
      vogo_error_log3("[PMA][UPSERT ERROR] {$wpdb->last_error}");
    }
  });

}
