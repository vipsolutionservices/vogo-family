<?php

// Add custom admin page for adding mall locations
function add_mall_location_admin_page() {
    add_menu_page(
        'Mall Locations',           // Page Title
        'Mall Locations',           // Menu Title
        'manage_options',           // Capability
        'mall_locations',           // Menu Slug
        'render_mall_locations_page', // Callback to render the page
        'dashicons-location-alt' // Icon
    );
}
add_action('admin_menu', 'add_mall_location_admin_page');

function render_mall_locations_page() {
    global $wpdb;

    // Load cities dynamically from JSON file
    $cities = get_cities_list();

    // Handle form submission (Add or Edit)
    if (isset($_POST['submit_mall_location'])) {
        $mall_name = sanitize_text_field($_POST['mall_name']);
        $street_address = sanitize_text_field($_POST['street_address']);
        $city = sanitize_text_field($_POST['city']);
        $location_code = sanitize_text_field($_POST['location_code']);
        $status = sanitize_text_field($_POST['status']);

        if (isset($_POST['location_id']) && !empty($_POST['location_id'])) {
            // Edit existing location
            $location_id = intval($_POST['location_id']);
            $wpdb->update(
                $wpdb->prefix . 'mall_locations',
                [
                    'mall_name' => $mall_name,
                    'street_address' => $street_address,
                    'city' => $city,
                    'location_code' => $location_code,
                    'status' => $status
                ],
                ['id' => $location_id],
                ['%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );
        } else {
            // Add new location
            $wpdb->insert(
                $wpdb->prefix . 'mall_locations',
                [
                    'mall_name' => $mall_name,
                    'street_address' => $street_address,
                    'city' => $city,
                    'location_code' => $location_code,
                    'status' => $status
                ],
                ['%s', '%s', '%s', '%s', '%s']
            );
        }
        // Redirect to refresh the page
        wp_redirect(admin_url('admin.php?page=mall_locations'));
        exit;
    }

    // Handle filtering
    $city_filter = isset($_GET['city_filter']) ? sanitize_text_field($_GET['city_filter']) : '';
    $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';

    // Pagination
    $per_page = 10;
    $current_page = isset($_GET['paged']) ? (int)$_GET['paged'] : 1;
    $offset = ($current_page - 1) * $per_page;

    // Query mall locations
    $query = "SELECT * FROM {$wpdb->prefix}mall_locations WHERE 1=1";
    $query_args = [];

    if (!empty($city_filter)) {
        $query .= " AND city LIKE %s";
        $query_args[] = "%" . $city_filter . "%";
    }

    if (!empty($status_filter)) {
        $query .= " AND status = %s";
        $query_args[] = $status_filter;
    }

    $query .= " LIMIT %d, %d";
    $query_args[] = $offset;
    $query_args[] = $per_page;

    $locations = $wpdb->get_results($wpdb->prepare($query, ...$query_args));

    // Get total count for pagination
    $total_locations = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}mall_locations WHERE 1=1");
    $total_pages = ceil($total_locations / $per_page);

    // Handle delete action
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['location_id'])) {
        $location_id = intval($_GET['location_id']);
        $wpdb->delete($wpdb->prefix . 'mall_locations', ['id' => $location_id], ['%d']);
        wp_redirect(admin_url('admin.php?page=mall_locations'));
        exit;
    }

    ?>
    <div class="wrap">
        <h1><?php echo isset($_GET['edit']) ? 'Edit Mall Location' : 'Add Mall Location'; ?></h1>
        <form method="post">
            <table class="form-table">
                <?php if (isset($_GET['edit'])) : ?>
                    <input type="hidden" name="location_id" value="<?php echo esc_attr($_GET['edit']); ?>" />
                <?php endif; ?>
                <tr>
                    <th><label for="mall_name">Mall Name</label></th>
                    <td><input type="text" name="mall_name" id="mall_name" required></td>
                </tr>
                <tr>
                    <th><label for="street_address">Street Address</label></th>
                    <td><input type="text" name="street_address" id="street_address" required></td>
                </tr>
                <tr>
                    <th><label for="city">City</label></th>
                    <td>
                        <select name="city" id="city" required>
                            <option value="">Select City</option>
                            <?php foreach ($cities as $city) : ?>
                                <option value="<?php echo esc_attr($city); ?>"><?php echo esc_html($city); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="location_code">Location Code</label></th>
                    <td><input type="text" name="location_code" id="location_code" required></td>
                </tr>
                <tr>
                    <th><label for="status">Status</label></th>
                    <td>
                        <select name="status" id="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </td>
                </tr>
            </table>
            <input type="submit" name="submit_mall_location" class="button-primary" value="Save Location">
        </form>

        <h2>Manage Mall Locations</h2>
        <form method="get">
            <input type="hidden" name="page" value="mall_locations">
            <label for="city_filter">City:</label>
            <select name="city_filter">
                <option value="">All Cities</option>
                <?php foreach ($cities as $city) : ?>
                    <option value="<?php echo esc_attr($city); ?>" <?php selected($city_filter, $city); ?>><?php echo esc_html($city); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="submit" value="Filter" class="button-secondary">
        </form>

        <table class="widefat">
            <thead>
                <tr><th>Mall Name</th><th>City</th><th>Address</th><th>Code</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($locations as $location) : ?>
                    <tr>
                        <td><?php echo esc_html($location->mall_name); ?></td>
                        <td><?php echo esc_html($location->city); ?></td>
                        <td><?php echo esc_html($location->street_address); ?></td>
                        <td><?php echo esc_html($location->location_code); ?></td>
                        <td><?php echo esc_html(ucfirst($location->status)); ?></td>
                        <td><a href="?page=mall_locations&edit=<?php echo esc_attr($location->id); ?>">Edit</a> | <a href="?page=mall_locations&action=delete&location_id=<?php echo esc_attr($location->id); ?>" onclick="return confirm('Delete?');">Delete</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

///end mall locationsm page

//start product category
//save selected
/**
 * Admin: Save custom fields for product_cat taxonomy (ADD + EDIT)
 * - Saves Mall Locations into wp_category_malls (category_id ↔ mall_id)
 * - Saves Cities into wp_product_category_cities (category_id ↔ city_id)
 * - Deletes old links before inserting new ones
 * - Handles fallback from old mall_location[] names by mapping to mall IDs
 * - Includes capability check, sanitization, logging and SQL debug
 */
function save_location_fields_for_product_category($term_id){
  if(!current_user_can('manage_product_terms')) return; // capability check
  global $wpdb; $p=$wpdb->prefix;
  $ip=$_SERVER['REMOTE_ADDR']??'UNKNOWN'; $uid=get_current_user_id()?:0;

  vogo_error_log3("VOGO_LOG_START | [product_cat save] term_id:$term_id | IP:$ip | USER:$uid");
  vogo_error_log3("[STEP 0.1] Raw POST keys: ".implode(',', array_keys($_POST)));

  /* =========================
   * [STEP 1] Save MALLS (wp_category_malls)
   * Expected POST: category_malls[] = [mall_id,...]
   * Fallback: mall_location[] = [mall_name,...] -> map to ids
   * ========================= */
  $table_malls = "{$p}category_malls";

  // Preferred: IDs from category_malls[]
  $posted_mall_ids = isset($_POST['category_malls']) ? (array)$_POST['category_malls'] : [];

  // Fallback path: old mall_location[] by names -> map to ids
  if(empty($posted_mall_ids) && !empty($_POST['mall_location']) && is_array($_POST['mall_location'])){
    $names = array_values(array_unique(array_filter(array_map('sanitize_text_field', $_POST['mall_location']))));
    if(!empty($names)){
      // Build IN clause safely
      $placeholders = implode(',', array_fill(0, count($names), '%s'));
      $sql_map = "SELECT id FROM {$p}mall_locations WHERE mall_name IN ($placeholders)";
      $mapped = $wpdb->get_col($wpdb->prepare($sql_map, ...$names));
      if($wpdb->last_error) vogo_error_log3("[STEP 1][MALLS MAP ERROR] {$wpdb->last_error}");
      $posted_mall_ids = $mapped ? array_map('intval',$mapped) : [];
    }
  }

  // Normalize mall ids
  $mall_ids = array_values(array_unique(array_filter(array_map('intval',$posted_mall_ids))));
  // Delete existing links
  $sql_del_m = $wpdb->prepare("DELETE FROM $table_malls WHERE category_id=%d", $term_id);
  vogo_error_log3("##############SQL: $sql_del_m");
  $wpdb->query($sql_del_m);
  if($wpdb->last_error) vogo_error_log3("[STEP 1][MALLS DEL ERROR] {$wpdb->last_error}");

  // Insert new links
  foreach($mall_ids as $mid){
    if($mid<=0) continue;
    $sql_ins_m = $wpdb->prepare("INSERT INTO $table_malls (category_id, mall_id, created_by, modified_by) VALUES (%d,%d,%d,%d)", $term_id,$mid,$uid,$uid);
    vogo_error_log3("##############SQL: $sql_ins_m");
    $wpdb->query($sql_ins_m);
    if($wpdb->last_error) vogo_error_log3("[STEP 1][MALLS INS ERROR] {$wpdb->last_error}");
  }
  vogo_error_log3("[STEP 1] malls saved count: ".count($mall_ids));

  /* =========================
   * [STEP 2] Save CITIES (wp_product_category_cities)
   * Expected POST: product_category_cities[] = [city_id,...]
   * ========================= */
  $table_cities = "{$p}product_category_cities";

  // Delete existing links
  $sql_del_c = $wpdb->prepare("DELETE FROM $table_cities WHERE category_id=%d", $term_id);
  vogo_error_log3("##############SQL: $sql_del_c");
  $wpdb->query($sql_del_c);
  if($wpdb->last_error) vogo_error_log3("[STEP 2][CITIES DEL ERROR] {$wpdb->last_error}");

  // Insert new from POST ids
  $posted_city_ids = isset($_POST['product_category_cities']) ? (array)$_POST['product_category_cities'] : [];
  $city_ids = array_values(array_unique(array_filter(array_map('intval',$posted_city_ids))));

  foreach($city_ids as $cid){
    if($cid<=0) continue;
    $sql_ins_c = $wpdb->prepare("INSERT INTO $table_cities (category_id, city_id, created_by, modified_by) VALUES (%d,%d,%d,%d)", $term_id,$cid,$uid,$uid);
    vogo_error_log3("##############SQL: $sql_ins_c");
    $wpdb->query($sql_ins_c);
    if($wpdb->last_error) vogo_error_log3("[STEP 2][CITIES INS ERROR] {$wpdb->last_error}");
  }
  vogo_error_log3("[STEP 2] cities saved count: ".count($city_ids));

  vogo_error_log3("VOGO_LOG_END | [product_cat save] term_id:$term_id");
}


add_action('edited_product_cat', 'save_location_fields_for_product_category');
add_action('create_product_cat', 'save_location_fields_for_product_category');


// display UI
/**
 * Admin: Add custom fields to product_cat taxonomy form (ADD + EDIT)
 * - Shows multi-select for Mall Locations (wp_category_malls)
 * - Shows multi-select for Cities (wp_product_category_cities)
 * - Preselects current values for given category
 * - Includes JS helpers for Select All / Clear All
 */
function add_location_fields_to_product_category($term){
  if(!current_user_can('manage_product_terms')) return;
  global $wpdb; $p=$wpdb->prefix;
  $ip=$_SERVER['REMOTE_ADDR']??'UNKNOWN'; $uid=get_current_user_id()?:0;
  $term_id = (is_object($term) && property_exists($term,'term_id')) ? (int)$term->term_id : 0;

  vogo_error_log3("VOGO_LOG_START | [product_cat form] term_id:$term_id | IP:$ip | USER:$uid");

  // [STEP 1] Load active malls (id + name) from wp_mall_locations
  $malls = $wpdb->get_results("SELECT id, mall_name FROM {$p}mall_locations WHERE status='active' ORDER BY position ASC, mall_name ASC");
  if($wpdb->last_error) vogo_error_log3("[DB ERROR malls] {$wpdb->last_error}");

  // [STEP 2] Current selected mall ids from wp_category_malls
  $current_malls = ($term_id>0) ? array_map('intval',$wpdb->get_col($wpdb->prepare("SELECT mall_id FROM {$p}category_malls WHERE category_id=%d",$term_id))) : [];
  if($wpdb->last_error) vogo_error_log3("[DB ERROR category_malls] {$wpdb->last_error}");

  // [STEP 3] Cities list via helper (expects [['id'=>..,'name'=>..], ...])
  $cities = function_exists('get_cities_list') ? get_cities_list() : [];

  // [STEP 4] Current selected city ids from wp_product_category_cities
  $current_cities = ($term_id>0) ? array_map('intval',$wpdb->get_col($wpdb->prepare("SELECT city_id FROM {$p}product_category_cities WHERE category_id=%d",$term_id))) : [];
  if($wpdb->last_error) vogo_error_log3("[DB ERROR category_cities] {$wpdb->last_error}");
  ?>

  <!-- Mall Locations (IDs) -->
  <tr class="form-field">
    <th scope="row" valign="top"><label for="category_malls">Mall Locations</label></th>
    <td>
      <button type="button" id="select_all_malls" class="button">Select All</button>
      <button type="button" id="clear_all_malls" class="button">Clear All</button>

      <select name="category_malls[]" id="category_malls" multiple="multiple" style="width:300px;height:200px;">
        <?php if($malls): foreach($malls as $m): ?>
          <option value="<?php echo esc_attr($m->id); ?>" <?php echo in_array((int)$m->id,$current_malls,true)?'selected':''; ?>>
            <?php echo esc_html($m->mall_name); ?>
          </option>
        <?php endforeach; endif; ?>
      </select>

      <?php wp_nonce_field('save_product_category_malls','product_category_malls_nonce'); ?>
      <p class="description">Hold Ctrl/Cmd to select multiple.</p>
    </td>
  </tr>

  <!-- Cities (IDs) -->
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
      $('#select_all_malls').on('click', ()=> $('#category_malls option').prop('selected', true));
      $('#clear_all_malls').on('click', ()=> $('#category_malls option').prop('selected', false));
      $('#select_all_cities').on('click', ()=> $('#product_category_cities option').prop('selected', true));
      $('#clear_all_cities').on('click', ()=> $('#product_category_cities option').prop('selected', false));
    });
  </script>

  <?php
  vogo_error_log3("VOGO_LOG_END | [product_cat form] term_id:$term_id");
}


add_action('product_cat_edit_form_fields', 'add_location_fields_to_product_category');
add_action('product_cat_add_form_fields', 'add_location_fields_to_product_category');


/**
 * Frontend: Display Mall Locations and Cities on product category archive page
 * - Reads malls from wp_category_malls (joined to wp_mall_locations)
 * - Reads cities from wp_product_category_cities (mapped via get_cities_list())
 * - Outputs compact info block if data exists
 */
function display_mall_locations_for_category(){
  if(!is_product_category()) return;
  global $wpdb; $p=$wpdb->prefix;
  $ip=$_SERVER['REMOTE_ADDR']??'UNKNOWN'; $uid=get_current_user_id()?:0;
  $term_id = get_queried_object_id();
  if(!$term_id) return;

  vogo_error_log3("VOGO_LOG_START | [category-display] term_id:$term_id | IP:$ip | USER:$uid");

  // [STEP 1] Mall names via join (category_malls -> mall_locations)
  $sql_m = "SELECT ml.mall_name FROM {$p}category_malls cm INNER JOIN {$p}mall_locations ml ON cm.mall_id=ml.id WHERE cm.category_id=%d AND ml.status='active' ORDER BY ml.position ASC, ml.mall_name ASC";
  $mall_names = $wpdb->get_col($wpdb->prepare($sql_m,$term_id));
  if($wpdb->last_error) vogo_error_log3("[DB ERROR malls] {$wpdb->last_error}");

  // [STEP 2] City IDs -> names
  $city_ids = array_map('intval',$wpdb->get_col($wpdb->prepare("SELECT city_id FROM {$p}product_category_cities WHERE category_id=%d",$term_id)));
  if($wpdb->last_error) vogo_error_log3("[DB ERROR cities] {$wpdb->last_error}");

  $city_names=[];
  if($city_ids){
    $cities = function_exists('get_cities_list') ? get_cities_list() : [];
    $map=[]; foreach($cities as $c){ $map[(int)$c['id']]=$c['name']; }
    foreach($city_ids as $cid){ if(isset($map[$cid])) $city_names[]=$map[$cid]; }
  }

  // [STEP 3] Render only if something
  if(!empty($mall_names) || !empty($city_names)){
    echo '<div class="category-location-info" style="margin:10px 0;">';
    if(!empty($mall_names)){
      echo '<p><strong>Mall Locations:</strong> '.esc_html(implode(', ',$mall_names)).'</p>';
    }
    if(!empty($city_names)){
      echo '<p><strong>Available in Cities:</strong> '.esc_html(implode(', ',$city_names)).'</p>';
    }
    echo '</div>';
  }

  vogo_error_log3("VOGO_LOG_END | [category-display] term_id:$term_id");
}

add_action('woocommerce_archive_description', 'display_mall_locations_for_category', 20);

//end product category

//start user profile address

function show_user_addresses_in_admin($user) {
    if (!current_user_can('edit_users')) {
        return;
    }

    global $wpdb;
    $user_id = $user->ID;

    // Fetch additional addresses from the custom table
    $addresses = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}user_addresses WHERE user_id = %d AND status = 'active'",
        $user_id
    ));

    ?>
    <h2>Additional Addresses</h2>
    <table class="form-table">
        <tr>
            <th>Address Name</th>
            <th>Street Address</th>
            <th>City</th>
        </tr>
        <?php if (!empty($addresses)) : ?>
            <?php foreach ($addresses as $address) : ?>
                <tr>
                    <td><?php echo esc_html($address->address_name ?? 'N/A'); ?></td>
                    <td><?php echo esc_html($address->street_address ?? 'N/A'); ?></td>
                    <td><?php echo esc_html($address->city ?? ''); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr><td colspan="4">No additional addresses saved.</td></tr>
        <?php endif; ?>
    </table>
    <?php
}
add_action('show_user_profile', 'show_user_addresses_in_admin');
add_action('edit_user_profile', 'show_user_addresses_in_admin');

//end user profile address