<?php
defined('ABSPATH') || exit; global $wpdb;

/* [STEP 1] Fetch orders for current user */
$uid=get_current_user_id(); if(!$uid){ wc_print_notice(esc_html__('You must be logged in to view orders.','woocommerce'),'error'); return; }
$t=$wpdb->prefix.'orders';
$sql=$wpdb->prepare(
  "SELECT id,parent_order_id,status,total_amount,currency,payment_method_title,notes_customer,city_id,delivery_type,order_short_description,relevant_image,provider_name,created_at
   FROM {$t} WHERE client_id=%d AND parent_order_id=0 ORDER BY id DESC",$uid
);
if(defined('WP_DEBUG')&&WP_DEBUG){ error_log('[VOGO][orders-sql] '.$sql); }
$rows=$wpdb->get_results($sql);
$acc=wc_get_page_permalink('myaccount');
$has=!empty($rows);
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer" />
<style>
:root{--vogo-green:#0e7f4e;--ink:#0e1116;--line:#e4efe8;--muted:#5a6072;--bg:#fff}
.vogo-orders{width:100%;border:1px solid var(--line);border-radius:14px;background:var(--bg);box-shadow:0 4px 20px rgba(0,0,0,.04);overflow:hidden}
.vogo-orders table{width:100%;border-collapse:collapse}
.vogo-th{background:#fff;color:var(--vogo-green);font-weight:700;font-size:.95rem;padding:12px;border-bottom:2px solid var(--vogo-green);text-align:left}
.vogo-td,.vogo-th{padding:12px;vertical-align:middle}
.vogo-row:not(:last-child) .vogo-td{border-bottom:1px solid var(--line)}
.vogo-col-number a{display:inline-flex;align-items:center;gap:8px;color:var(--ink);font-weight:600;text-decoration:none}
.vogo-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:#eaf7f0;color:#066f40;font-weight:600;font-size:.78rem}
.vogo-total{font-weight:800;color:var(--ink)}
.vogo-pm{font-size:.82rem;color:var(--muted);margin-left:6px}
.vogo-actions{text-align:right;width:60px}
.vogo-btn{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:10px;border:1px solid var(--line);background:#fff;text-decoration:none}
.vogo-btn i{font-size:14px;color:var(--vogo-green)}
.vogo-img{width:60px;height:60px;object-fit:cover;border:1px solid #ccc;border-radius:5px;background:#f8f9fb}
.vogo-short{color:var(--ink);font-size:.92rem;line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;max-width:520px}
.vogo-meta{color:var(--muted);font-size:.88rem}

/* MOBILE stacked without duplicate <tr> */
@media(max-width:860px){
  .vogo-orders thead{display:none}
  .vogo-orders table,.vogo-orders tbody,.vogo-orders tr,.vogo-orders td{display:block;width:100%}
  .vogo-row{border-bottom:1px solid var(--line);padding:10px 12px}
  .vogo-td{border:none;padding:6px 0}
  .vogo-td[data-label]::before{content:attr(data-label);display:block;font-size:.78rem;color:var(--muted);margin-bottom:2px}
  .vogo-short{-webkit-line-clamp:3;max-width:100%}
  .vogo-actions{text-align:left}
}
</style>

<?php if($has): ?>
<div class="vogo-orders">
  <table class="woocommerce-orders-table">
    <thead>
      <tr class="vogo-row">
        <th class="vogo-th">#Nr</th>
        <th class="vogo-th">Image</th>
        <th class="vogo-th">Date</th>
        <th class="vogo-th">Status</th>
        <th class="vogo-th">Total</th>
        <th class="vogo-th">Short description</th>
        <th class="vogo-th"></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach($rows as $r):
      $oid=(int)$r->id;
      $status_slug=ltrim((string)$r->status,'wc-');
      $status_label=wc_get_order_status_name($status_slug);
      $total_txt=number_format((float)$r->total_amount,2,',',' ').' '.strtoupper((string)$r->currency);
      $pm=trim((string)$r->payment_method_title);
      $img_raw=trim((string)$r->relevant_image);
      $img_url=$img_raw!==''?$img_raw:wc_placeholder_img_src();
      $short=trim((string)$r->order_short_description); if($short==='') $short=(string)$r->notes_customer;
      $provider=trim((string)$r->provider_name);
      $dt = !empty($r->created_at) ? date_i18n('Y-m-d H:i', strtotime($r->created_at)) : '';
      $view=wc_get_endpoint_url('view-order',$oid,$acc);
    ?>
      <tr class="vogo-row woocommerce-orders-table__row woocommerce-orders-table__row--status-<?php echo esc_attr($status_slug); ?>">
        <td class="vogo-td vogo-col-number" data-label="#Nr">
          <a href="<?php echo esc_url($view); ?>">#<?php echo esc_html($oid); ?></a>
          <?php if($provider!==''): ?><div class="vogo-meta"><?php echo esc_html($provider); ?></div><?php endif; ?>
        </td>
        <td class="vogo-td vogo-col-image" data-label="Image" style="text-align:center">
          <a href="<?php echo esc_url($view); ?>"><img class="vogo-img" src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr__('Order image','woocommerce'); ?>"></a>
        </td>
        <td class="vogo-td" data-label="Date"><span class="vogo-meta"><?php echo esc_html($dt); ?></span></td>
        <td class="vogo-td vogo-col-status" data-label="Status">
          <span class="vogo-badge"><i class="fa-solid fa-circle-dot" aria-hidden="true"></i><?php echo esc_html($status_label); ?></span>
        </td>
        <td class="vogo-td vogo-col-total" data-label="Total">
          <span class="vogo-total"><?php echo esc_html($total_txt); ?></span><?php if($pm!==''): ?><span class="vogo-pm"> · <?php echo esc_html($pm); ?></span><?php endif; ?>
        </td>
        <td class="vogo-td" data-label="Short description"><div class="vogo-short"><?php echo esc_html($short); ?></div></td>
        <td class="vogo-td vogo-actions" data-label="">
          <a class="vogo-btn" href="<?php echo esc_url($view); ?>" aria-label="<?php echo esc_attr(sprintf(__('View order %s','woocommerce'),$oid)); ?>"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else:
  wc_print_notice(esc_html__('No order has been made yet.','woocommerce'),'notice');
endif;
