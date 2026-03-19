<?php

// ============================================================
// Title: sync_product_provider_to_vogo_product_vendor (VOGO providers)
// Purpose: On product save, read selected 'product_provider' term,
//          resolve vendor via wp_vogo_providers (term_id/name/slug),
//          and upsert into wp_vogo_product_vendor (one vendor per product).
// ============================================================
add_action('save_post_product', function($post_id,$post,$update){
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!$post || $post->post_type!=='product') return;

  global $wpdb; $active_db=DB_NAME; $ip=$_SERVER['REMOTE_ADDR']??'UNKNOWN'; $uid=get_current_user_id()?:0;
  vogo_error_log3("VOGO_LOG_START | IP:$ip | USER:$uid"); vogo_error_log3("ACTIVE DB: $active_db");
  vogo_error_log3("[STEP 0.1] Sync taxonomy->vogo tables | product_id=$post_id");

  // [1] read selected term (first if multiple)
  $term_ids = wp_get_object_terms($post_id,'product_provider',['fields'=>'ids']);
  $term_id  = (is_array($term_ids)&&!empty($term_ids))?intval($term_ids[0]):0;

  $map  = $wpdb->prefix.'vogo_product_vendor';
  $prov = $wpdb->prefix.'vogo_providers';

  if($term_id===0){
    // no provider selected -> remove mapping
    $qDel=$wpdb->prepare("DELETE FROM $map WHERE product_id=%d",$post_id);
    vogo_error_log3("##############SQL: $qDel"); $wpdb->query($qDel);
    if($wpdb->last_error) vogo_error_log3("[STEP ERR] SQL delete: ".$wpdb->last_error." | IP:$ip | USER:$uid");
    vogo_error_log3("VOGO_LOG_END | IP:$ip | USER:$uid"); return;
  }

  // [2] resolve vendor_id from wp_vogo_providers: by term_id, else by name, else by slug
  $vendor_id = 0;
  $q1 = $wpdb->prepare("SELECT id FROM $prov WHERE term_id=%d LIMIT 1",$term_id);
  vogo_error_log3("##############SQL: $q1"); $vendor_id = intval($wpdb->get_var($q1));
  if($wpdb->last_error) vogo_error_log3("[STEP ERR] SQL term_id lookup: ".$wpdb->last_error);

  if($vendor_id===0){
    $t = get_term($term_id,'product_provider');
    if($t && !is_wp_error($t)){
      $q2=$wpdb->prepare("SELECT id FROM $prov WHERE provider_name=%s LIMIT 1",$t->name);
      vogo_error_log3("##############SQL: $q2"); $vendor_id=intval($wpdb->get_var($q2));
      if($vendor_id===0){
        $q3=$wpdb->prepare("SELECT id FROM $prov WHERE provider_slug=%s LIMIT 1", sanitize_title($t->name));
        vogo_error_log3("##############SQL: $q3"); $vendor_id=intval($wpdb->get_var($q3));
      }
    }
  }
  if($vendor_id===0){ vogo_error_log3("[STEP WARN] Could not resolve vendor for term_id=$term_id"); vogo_error_log3("VOGO_LOG_END | IP:$ip | USER:$uid"); return; }

  // [3] upsert mapping
  $qUpsert = $wpdb->prepare(
    "INSERT INTO $map (product_id,vendor_id,status,created_by,modified_by)
     VALUES (%d,%d,'active',%d,%d)
     ON DUPLICATE KEY UPDATE vendor_id=VALUES(vendor_id), status='active', modified_by=VALUES(modified_by), modified_at=CURRENT_TIMESTAMP",
     $post_id,$vendor_id,$uid,$uid
  );
  vogo_error_log3("##############SQL: $qUpsert"); $wpdb->query($qUpsert);
  if($wpdb->last_error) vogo_error_log3("[STEP ERR] SQL upsert: ".$wpdb->last_error." | IP:$ip | USER:$uid");

  vogo_error_log3("[STEP DONE] product_id=$post_id -> vendor_id=$vendor_id"); 
  vogo_error_log3("VOGO_LOG_END | IP:$ip | USER:$uid");
}, 99, 3);

// Add provider meta to each order line item on checkout
add_action('woocommerce_checkout_create_order_line_item', function ($item) {
  global $wpdb;
  $pid = $item->get_product_id();
  $vid = (int)$wpdb->get_var($wpdb->prepare(
    "SELECT vendor_id FROM {$wpdb->prefix}vogo_product_vendor WHERE product_id=%d LIMIT 1", $pid
  ));
  if ($vid) {
    $name = $wpdb->get_var($wpdb->prepare(
      "SELECT provider_name FROM {$wpdb->prefix}vogo_providers WHERE id=%d", $vid
    ));
    $item->add_meta_data('_vendor_id', $vid, true);
    if ($name) $item->add_meta_data('_vendor_name', $name, true);
  }
}, 10, 1);

// Show provider in Admin → Order items
add_action('woocommerce_after_order_itemmeta', function ($item_id, $item) {
  if (!is_admin() || !$item->is_type('line_item')) return;
  $vid = (int) wc_get_order_item_meta($item_id, '_vendor_id', true);
  if ($vid) {
    global $wpdb;
    $name = $wpdb->get_var($wpdb->prepare(
      "SELECT provider_name FROM {$wpdb->prefix}vogo_providers WHERE id=%d", $vid
    ));
    echo '<p><strong>Provider:</strong> ' . esc_html($name ?: ('#'.$vid)) . '</p>';
  }
}, 10, 2);

// Orders list filter by provider (from wp_vogo_providers)
add_action('woocommerce_order_list_table_filters', function () {
  global $wpdb;
  $selected = isset($_GET['filter_vogo_provider']) ? intval($_GET['filter_vogo_provider']) : 0;
  $providers = $wpdb->get_results("SELECT id, provider_name FROM {$wpdb->prefix}vogo_providers WHERE status='active' ORDER BY provider_name ASC");
  echo '<select name="filter_vogo_provider"><option value="">'.__('All Providers','woocommerce').'</option>';
  foreach ($providers as $p) {
    printf('<option value="%d"%s>%s</option>',
      (int)$p->id, selected($selected, (int)$p->id, false), esc_html($p->provider_name));
  }
  echo '</select>';
});

// Apply the provider filter to the orders query
add_action('pre_get_posts', function ($query) {
  if (!is_admin() || !$query->is_main_query()) return;
  global $pagenow;
  if ($pagenow !== 'edit.php' || $query->get('post_type') !== 'shop_order') return;

  $vendor_id = isset($_GET['filter_vogo_provider']) ? intval($_GET['filter_vogo_provider']) : 0;
  if ($vendor_id <= 0) return;

  global $wpdb;
  $order_ids = $wpdb->get_col($wpdb->prepare("
    SELECT DISTINCT oi.order_id
    FROM {$wpdb->prefix}woocommerce_order_items oi
    JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
      ON oim.order_item_id = oi.order_item_id
     AND oim.meta_key = '_vendor_id'
     AND oim.meta_value = %d
    WHERE oi.order_item_type = 'line_item'
  ", $vendor_id));

  $query->set('post__in', $order_ids ?: [0]);
});

//THANK YOU and ORDER CONFIRMATION PAGE
/**
 * Thank you + Emails: show Product Suppliers, resolved from product-level tables.
 * Source: wp_vogo_product_vendor (product_id -> vendor_id) + wp_vogo_providers (id -> provider_name).
 * No writes, only reads.
 */
add_action('woocommerce_order_details_after_order_table', 'vogo_render_suppliers_from_products', 15);
function vogo_render_suppliers_from_products( $order ){
    if ( ! $order instanceof WC_Order ) return;

    global $wpdb;
    $items = $order->get_items( 'line_item' );
    if ( empty( $items ) ) return;

    $map_tbl   = $wpdb->prefix . 'vogo_product_vendor';
    $prov_tbl  = $wpdb->prefix . 'vogo_providers';
    // detect optional `active` column
    $has_active = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = %s AND column_name = 'active'",
        $prov_tbl
    ));
    $where_active = $has_active ? "status='active' AND active=1" : "status='active'";

    $rows = [];
    $has_vendor = false;

    foreach ( $items as $item ) {
        $name = $item->get_name();
        $pid  = (int) $item->get_product_id();
        $vid  = (int) $item->get_variation_id();
        $lookup_id = $vid ?: $pid;

        // 1) product -> vendor_id (try variation then parent)
        $vendor_id = (int)$wpdb->get_var( $wpdb->prepare(
            "SELECT vendor_id FROM {$map_tbl} WHERE product_id = %d LIMIT 1", $lookup_id
        ));
        if ( ! $vendor_id && $vid ) {
            $vendor_id = (int)$wpdb->get_var( $wpdb->prepare(
                "SELECT vendor_id FROM {$map_tbl} WHERE product_id = %d LIMIT 1", $pid
            ));
        }

        // 2) vendor_id -> provider_name (active only)
        $vendor_name = '';
        if ( $vendor_id ) {
            $vendor_name = (string)$wpdb->get_var( $wpdb->prepare(
                "SELECT provider_name FROM {$prov_tbl} WHERE id=%d AND {$where_active} LIMIT 1", $vendor_id
            ));
            if ( $vendor_name !== '' ) $has_vendor = true;
        }

        $rows[] = [$name, $vendor_name ?: '—'];
    }

    if ( ! $has_vendor ) return; // nu afișăm tabelul dacă niciun item nu are vendor

    ?>
    <h2 style="margin-top:2rem;"><?php echo esc_html__('Product Suppliers','woocommerce'); ?></h2>
    <table class="shop_table shop_table_responsive">
        <thead>
            <tr>
                <th><?php esc_html_e('Product','woocommerce'); ?></th>
                <th><?php esc_html_e('Supplier(s)','woocommerce'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as [$p,$v]) : ?>
            <tr>
                <td><?php echo esc_html($p); ?></td>
                <td><?php echo esc_html($v); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

/* Include the same block in customer emails (after the order table). */
add_action('woocommerce_email_after_order_table', function($order, $sent_to_admin, $plain_text, $email){
    if ( $sent_to_admin ) return;
    vogo_render_suppliers_from_products( $order );
}, 15, 4);

/**
 * Ensure Street Number shows in Delivery address.
 * Priority: order meta `_shipping_street_number`; fallback: user_meta `shipping_street_number`.
 */
add_filter('woocommerce_order_formatted_shipping_address', function($address, $order){
    if ( ! $order instanceof WC_Order ) return $address;

    $no = $order->get_meta('_shipping_street_number');
    if ( ! $no ) {
        $uid = $order->get_user_id();
        if ( $uid ) $no = get_user_meta($uid, 'shipping_street_number', true);
    }

    if ( $no && !empty($address['address_1']) && strpos($address['address_1'], $no) === false ) {
        $address['address_1'] .= ' ' . $no;
    }
    return $address;
}, 10, 2);

// OPTIONAL: dacă ai rulat anterior varianta cu tabelul "Product Suppliers", îl dezactivăm:
remove_action('woocommerce_order_details_after_order_table', 'vogo_render_suppliers_from_products', 15);
remove_action('woocommerce_email_after_order_table', function(){}, 15);

// Helper: rezolvă vendorul pentru un product/variation din tabele
if (!function_exists('vogo_resolve_vogo_product_vendor')) {
  function vogo_resolve_vogo_product_vendor($product_id){
    static $cache = [];
    if (isset($cache[$product_id])) return $cache[$product_id];

    global $wpdb;
    $map_tbl  = $wpdb->prefix . 'vogo_product_vendor';
    $prov_tbl = $wpdb->prefix . 'vogo_providers';

    $vendor_id = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT vendor_id FROM {$map_tbl} WHERE product_id = %d LIMIT 1", $product_id
    ));

    $vendor_name = '';
    if ($vendor_id) {
      static $has_active = null;
      if ($has_active === null) {
        $has_active = (int)$wpdb->get_var($wpdb->prepare(
          "SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema=DATABASE() AND table_name=%s AND column_name='active'", $prov_tbl
        ));
      }
      $where = $has_active ? "status='active' AND active=1" : "status='active'";
      $vendor_name = (string)$wpdb->get_var($wpdb->prepare(
        "SELECT provider_name FROM {$prov_tbl} WHERE id=%d AND {$where} LIMIT 1", $vendor_id
      ));
    }

    return $cache[$product_id] = [
      'id'   => $vendor_id ?: 0,
      'name' => $vendor_name ?: ''
    ];
  }
}

// 1) Afișează vendorul sub fiecare produs în Order Received / View Order / Email
add_action('woocommerce_order_item_meta_end', function($item_id, $item, $order, $plain_text){
  if ($item->get_type() !== 'line_item') return;

  $pid = (int)$item->get_product_id();
  $vid = (int)$item->get_variation_id();
  $lookup = $vid ?: $pid;

  // întâi pe variație, apoi fallback pe părinte
  $v = vogo_resolve_vogo_product_vendor($lookup);
  if (!$v['id'] && $vid) $v = vogo_resolve_vogo_product_vendor($pid);
  if (empty($v['name'])) return;

  echo '<div class="vogo-item-vendor" style="margin-top:4px;font-size:12px;color:#6b7280;">'
     . esc_html__('Supplier:', 'woocommerce') . ' '
     . '<strong style="color:#111827;">' . esc_html($v['name']) . '</strong>'
     . '</div>';
}, 10, 4);

// 2) Tabel sumar cu sumele pe vendor (Total = total + tax pe fiecare linie)
add_action('woocommerce_order_details_after_order_table', function($order){
  if (!$order instanceof WC_Order) return;

  $groups = []; // vendor_name => amount
  foreach ($order->get_items('line_item') as $item) {
    $pid = (int)$item->get_product_id();
    $vid = (int)$item->get_variation_id();
    $lookup = $vid ?: $pid;

    $v = vogo_resolve_vogo_product_vendor($lookup);
    if (!$v['id'] && $vid) $v = vogo_resolve_vogo_product_vendor($pid);
    if (empty($v['name'])) continue; // ignorăm fără vendor

    $line_total = (float)$item->get_total() + (float)$item->get_total_tax();
    if (!isset($groups[$v['name']])) $groups[$v['name']] = 0.0;
    $groups[$v['name']] += $line_total;
  }

  if (empty($groups)) return;

  echo '<h2 style="margin-top:2rem;">'.esc_html__('Suppliers summary','woocommerce').'</h2>';
  echo '<table class="shop_table shop_table_responsive"><thead><tr>';
  echo '<th>'.esc_html__('Supplier','woocommerce').'</th>';
  echo '<th style="text-align:right;">'.esc_html__('Total','woocommerce').'</th>';
  echo '</tr></thead><tbody>';

  foreach ($groups as $name => $amount) {
    echo '<tr><td>'.esc_html($name).'</td><td style="text-align:right;">'
       . wc_price($amount, ['currency'=>$order->get_currency()])
       . '</td></tr>';
  }

  echo '</tbody></table>';
}, 25);


// Remove old "Product Suppliers" renderers on Thank you & emails
add_action('plugins_loaded', function () {
  // nume cunoscut (dacă există)
  remove_action('woocommerce_order_details_after_order_table', 'vogo_render_suppliers_from_products', 15);
  remove_action('woocommerce_email_after_order_table', 'vogo_render_suppliers_from_products', 15);

  // fallback: elimină closures care conțin textul "Product Suppliers"
  vogo_kill_legacy_suppliers('woocommerce_order_details_after_order_table');
  vogo_kill_legacy_suppliers('woocommerce_email_after_order_table');
}, 99);

/**
 * Kill only the legacy "Product Suppliers" renderer that uses taxonomy (product_provider/vendor).
 * We detect callbacks pe baza conținutului sursă: get_the_terms/wp_get_post_terms/get_terms + sluguri de taxonomie.
 * Nu atinge implementarea noastră (care folosește DB), pentru că aceea NU folosește funcțiile de taxonomie.
 */
add_action('woocommerce_order_details_before_order_table', function () {
  vogo_remove_taxonomy_suppliers('woocommerce_order_details_after_order_table');
}, 0);

add_action('woocommerce_email_before_order_table', function () {
  vogo_remove_taxonomy_suppliers('woocommerce_email_after_order_table');
}, 0);

function vogo_remove_taxonomy_suppliers($hook){
  global $wp_filter;
  if (empty($wp_filter[$hook]) || empty($wp_filter[$hook]->callbacks)) return;

  foreach ($wp_filter[$hook]->callbacks as $prio => $cbs){
    foreach ($cbs as $id => $cb){
      $fn   = $cb['function'];
      $ref  = null;
      $file = '';

      try {
        if ($fn instanceof \Closure) {
          $ref = new \ReflectionFunction($fn);
        } elseif (is_string($fn) && function_exists($fn)) {
          $ref = new \ReflectionFunction($fn);
        } elseif (is_array($fn) && isset($fn[0], $fn[1]) && is_callable($fn)) {
          $ref = new \ReflectionMethod(is_object($fn[0]) ? get_class($fn[0]) : $fn[0], $fn[1]);
        }
        if (!$ref) continue;
        $file = $ref->getFileName();
      } catch (\Throwable $e) {
        continue;
      }

      if (!$file) continue;
      $src = @file_get_contents($file);
      if ($src === false) continue;

      // Detectăm callback-ul vechi: folosește termeni/taxonomie
      $uses_terms = stripos($src, 'get_the_terms') !== false
                 || stripos($src, 'wp_get_post_terms') !== false
                 || stripos($src, 'get_terms') !== false;

      $mentions_tax = stripos($src, 'product_provider') !== false
                   || stripos($src, 'vogo_product_vendor')   !== false
                   || stripos($src, 'supplier')         !== false
                   || stripos($src, 'vendor')           !== false;

      // Nu ștergem implementarea noastră (DB): conține semnături proprii
      $is_ours = stripos($src, 'vogo_resolve_vogo_product_vendor') !== false
              || stripos($src, 'wp_vogo_providers') !== false
              || stripos($src, 'vogo_product_vendor') !== false && stripos($src, '$wpdb->prefix . \'vogo_product_vendor\'') !== false;

      if ($uses_terms && $mentions_tax && !$is_ours) {
        unset($wp_filter[$hook]->callbacks[$prio][$id]);
        if (function_exists('vogo_error_log3')) {
          vogo_error_log3("[VOGO] Removed legacy taxonomy suppliers callback on {$hook} (prio {$prio}) from {$file}");
        }
      }
    }
  }
}
