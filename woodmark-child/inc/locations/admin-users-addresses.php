<?php
/**
 * Admin Module: Show user additional addresses in profile
 * Reads from wp_user_addresses (status='active'). Purely informational.
 */

add_action('show_user_profile','vogo_show_user_addresses_in_admin');
add_action('edit_user_profile','vogo_show_user_addresses_in_admin');
function vogo_show_user_addresses_in_admin($user){
  if(!current_user_can('edit_users')) return;
  global $wpdb; $p=$wpdb->prefix;
  $ip=$_SERVER['REMOTE_ADDR']??'UNKNOWN'; $admin_uid=get_current_user_id()?:0;

  vogo_error_log3("VOGO_LOG_START | [user-addresses] view user_id:{$user->ID} | IP:$ip | ADMIN:$admin_uid");

  $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$p}user_addresses WHERE user_id=%d AND status='active' ORDER BY id DESC",$user->ID));
  if($wpdb->last_error) vogo_error_log3("[user-addresses][DB ERROR] {$wpdb->last_error}");
  ?>
  <h2>Additional Addresses</h2>
  <table class="form-table">
    <tr><th>Address Name</th><th>Street Address</th><th>City</th></tr>
    <?php if($rows): foreach($rows as $r): ?>
      <tr>
        <td><?php echo esc_html($r->address_name ?? 'N/A'); ?></td>
        <td><?php echo esc_html($r->street_address ?? 'N/A'); ?></td>
        <td><?php echo esc_html($r->city ?? ''); ?></td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="3">No additional addresses saved.</td></tr>
    <?php endif; ?>
  </table>
  <?php
  vogo_error_log3("VOGO_LOG_END | [user-addresses] view user_id:{$user->ID}");
}
