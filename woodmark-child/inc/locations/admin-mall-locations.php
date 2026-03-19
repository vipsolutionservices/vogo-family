<?php
/**
 * Admin Module: Mall Locations CRUD (menu + list + filter + edit)
 * NOTE: Uses text city column as-is in wp_mall_locations to avoid breaking schema.
 * If you later normalize to city_id, swap select to ids and update insert/update accordingly.
 */

add_action('admin_menu', function(){
  if(!is_admin()) return;
  add_menu_page('Mall Locations','Mall Locations','manage_options','mall_locations','render_mall_locations_page','dashicons-location-alt');
});

function render_mall_locations_page(){
  if(!current_user_can('manage_options')) wp_die('Forbidden');
  global $wpdb; $p=$wpdb->prefix; $ip=$_SERVER['REMOTE_ADDR']??'UNKNOWN'; $uid=get_current_user_id()?:0;
  vogo_error_log3("VOGO_LOG_START | [mall-locations] render | IP:$ip | USER:$uid");

  // [STEP 0.1] Load cities list (for the city dropdown)
  $cities = function_exists('get_cities_list') ? get_cities_list() : [];
  $tbl = "{$p}mall_locations";

  // [STEP 1] Handle save (insert/update)
  if(isset($_POST['submit_mall_location']) && check_admin_referer('save_mall_location','mall_location_nonce')){
    $mall_name      = sanitize_text_field($_POST['mall_name']??'');
    $street_address = sanitize_text_field($_POST['street_address']??'');
    $city_name      = sanitize_text_field($_POST['city']??''); // keeping name (not id)
    $location_code  = sanitize_text_field($_POST['location_code']??'');
    $status         = sanitize_text_field($_POST['status']??'inactive');

    if(!empty($_POST['location_id'])){
      $location_id = intval($_POST['location_id']);
      $wpdb->update($tbl,['mall_name'=>$mall_name,'street_address'=>$street_address,'city'=>$city_name,'location_code'=>$location_code,'status'=>$status],['id'=>$location_id],['%s','%s','%s','%s','%s'],['%d']);
      if($wpdb->last_error) vogo_error_log3("[mall-locations][UPDATE][DB ERROR] {$wpdb->last_error}");
    }else{
      $wpdb->insert($tbl,['mall_name'=>$mall_name,'street_address'=>$street_address,'city'=>$city_name,'location_code'=>$location_code,'status'=>$status],['%s','%s','%s','%s','%s']);
      if($wpdb->last_error) vogo_error_log3("[mall-locations][INSERT][DB ERROR] {$wpdb->last_error}");
    }
    wp_redirect(admin_url('admin.php?page=mall_locations')); exit;
  }

  // [STEP 2] Handle delete
  if(isset($_GET['action'],$_GET['location_id']) && $_GET['action']==='delete' && current_user_can('manage_options')){
    $location_id=intval($_GET['location_id']);
    $wpdb->delete($tbl,['id'=>$location_id],['%d']);
    if($wpdb->last_error) vogo_error_log3("[mall-locations][DELETE][DB ERROR] {$wpdb->last_error}");
    wp_redirect(admin_url('admin.php?page=mall_locations')); exit;
  }

  // [STEP 3] Filters + pagination
  $city_filter   = isset($_GET['city_filter']) ? sanitize_text_field($_GET['city_filter']) : '';
  $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';
  $per_page      = 10;
  $current_page  = max(1, intval($_GET['paged']??1));
  $offset        = ($current_page-1)*$per_page;

  $where="WHERE 1=1"; $args=[];
  if($city_filter!==''){ $where.=" AND city LIKE %s"; $args[]="%{$city_filter}%"; }
  if($status_filter!==''){ $where.=" AND status=%s";   $args[]=$status_filter; }

  $sql_count = "SELECT COUNT(*) FROM $tbl $where";
  $total     = $args? $wpdb->get_var($wpdb->prepare($sql_count,...$args)) : $wpdb->get_var($sql_count);
  if($wpdb->last_error) vogo_error_log3("[mall-locations][COUNT][DB ERROR] {$wpdb->last_error}");
  $total_pages = ceil(($total?:0)/$per_page);

  $sql_list = "SELECT * FROM $tbl $where ORDER BY id DESC LIMIT %d,%d";
  $args_list=$args; $args_list[]=$offset; $args_list[]=$per_page;
  $rows = $wpdb->get_results($wpdb->prepare($sql_list,...$args_list));
  if($wpdb->last_error) vogo_error_log3("[mall-locations][LIST][DB ERROR] {$wpdb->last_error}");

  // [STEP 4] Edit row (if any)
  $edit_id = isset($_GET['edit'])?intval($_GET['edit']):0;
  $edit_row = $edit_id? $wpdb->get_row($wpdb->prepare("SELECT * FROM $tbl WHERE id=%d",$edit_id)) : null;
  if($wpdb->last_error) vogo_error_log3("[mall-locations][EDIT-LOAD][DB ERROR] {$wpdb->last_error}");
  ?>
  <div class="wrap">
    <h1><?php echo $edit_row? 'Edit Mall Location':'Add Mall Location'; ?></h1>
    <form method="post">
      <?php wp_nonce_field('save_mall_location','mall_location_nonce'); ?>
      <?php if($edit_row): ?><input type="hidden" name="location_id" value="<?php echo esc_attr($edit_row->id); ?>"><?php endif; ?>
      <table class="form-table">
        <tr><th><label for="mall_name">Mall Name</label></th>
            <td><input type="text" name="mall_name" id="mall_name" required value="<?php echo esc_attr($edit_row->mall_name??''); ?>"></td></tr>
        <tr><th><label for="street_address">Street Address</label></th>
            <td><input type="text" name="street_address" id="street_address" required value="<?php echo esc_attr($edit_row->street_address??''); ?>"></td></tr>
        <tr><th><label for="city">City</label></th>
            <td>
              <select name="city" id="city" required>
                <option value="">Select City</option>
                <?php foreach($cities as $c): $val=$c['name']; $sel=($edit_row && $edit_row->city===$val)?'selected':''; ?>
                  <option value="<?php echo esc_attr($val); ?>" <?php echo $sel; ?>><?php echo esc_html($c['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </td></tr>
        <tr><th><label for="location_code">Location Code</label></th>
            <td><input type="text" name="location_code" id="location_code" required value="<?php echo esc_attr($edit_row->location_code??''); ?>"></td></tr>
        <tr><th><label for="status">Status</label></th>
            <td>
              <?php $st=$edit_row->status??'active'; ?>
              <select name="status" id="status">
                <option value="active"   <?php selected($st,'active'); ?>>Active</option>
                <option value="inactive" <?php selected($st,'inactive'); ?>>Inactive</option>
              </select>
            </td></tr>
      </table>
      <input type="submit" name="submit_mall_location" class="button-primary" value="Save Location">
    </form>

    <h2 style="margin-top:28px;">Manage Mall Locations</h2>
    <form method="get" style="margin-bottom:12px;">
      <input type="hidden" name="page" value="mall_locations">
      <label for="city_filter">City:</label>
      <select name="city_filter" id="city_filter">
        <option value="">All Cities</option>
        <?php foreach($cities as $c): ?>
          <option value="<?php echo esc_attr($c['name']); ?>" <?php selected($city_filter,$c['name']); ?>><?php echo esc_html($c['name']); ?></option>
        <?php endforeach; ?>
      </select>
      <label for="status_filter" style="margin-left:10px;">Status:</label>
      <select name="status_filter" id="status_filter">
        <option value="">All</option>
        <option value="active"   <?php selected($status_filter,'active'); ?>>Active</option>
        <option value="inactive" <?php selected($status_filter,'inactive'); ?>>Inactive</option>
      </select>
      <input type="submit" class="button-secondary" value="Filter">
    </form>

    <table class="widefat">
      <thead><tr><th>Mall Name</th><th>City</th><th>Address</th><th>Code</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
        <?php if($rows): foreach($rows as $r): ?>
          <tr>
            <td><?php echo esc_html($r->mall_name); ?></td>
            <td><?php echo esc_html($r->city); ?></td>
            <td><?php echo esc_html($r->street_address); ?></td>
            <td><?php echo esc_html($r->location_code); ?></td>
            <td><?php echo esc_html(ucfirst($r->status)); ?></td>
            <td>
              <a href="?page=mall_locations&edit=<?php echo esc_attr($r->id); ?>">Edit</a> |
              <a href="?page=mall_locations&action=delete&location_id=<?php echo esc_attr($r->id); ?>" onclick="return confirm('Delete?');">Delete</a>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="6">No locations found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <?php if($total_pages>1): ?>
      <div class="tablenav"><div class="tablenav-pages">
        <?php echo paginate_links(['base'=>add_query_arg('paged','%#%'),'format'=>'','prev_text'=>'&laquo;','next_text'=>'&raquo;','total'=>$total_pages,'current'=>$current_page]); ?>
      </div></div>
    <?php endif; ?>
  </div>
  <?php
  vogo_error_log3("VOGO_LOG_END | [mall-locations] render");
}
