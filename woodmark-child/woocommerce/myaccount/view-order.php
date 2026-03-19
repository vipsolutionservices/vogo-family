<?php
defined('ABSPATH')||exit;
global $wpdb; $parent_id=(int)$order_id;

$tbl_o=$wpdb->prefix.'orders';
$children=$wpdb->get_results($wpdb->prepare(
 "SELECT id AS order_id, provider_name, total_amount, currency, status, created_at, payment_method_title
  FROM {$tbl_o}
  WHERE parent_order_id=%d
  ORDER BY created_at DESC",$parent_id
));
if(!$children) return;
?>
<style>
:root{--vogo-green:#0e7f4e;--ink:#0e1116;--muted:#5a6072;--line:#e4efe8;--bg:#fff;--chip:#eef7f1}

/* container */
.vogo-suborders{margin-top:22px;border:1px solid var(--line);border-radius:14px;background:#fff;box-shadow:0 6px 24px rgba(0,0,0,.05);overflow:hidden}
.vogo-suborders .vogo-h{padding:14px 18px;font-weight:700;color:var(--ink);border-bottom:1px solid var(--line)}

.vogo-acc{border-top:1px solid var(--line)} .vogo-acc:first-of-type{border-top:none}
.vogo-acc summary{list-style:none;cursor:pointer;display:flex;align-items:center;gap:12px;padding:14px 18px}
.vogo-acc summary::-webkit-details-marker{display:none}
.vogo-acc .chev{transition:transform .2s ease} .vogo-acc[open] .chev{transform:rotate(180deg)}
.vogo-acc .ttl{font-weight:700;color:var(--ink)}
.vogo-acc .vendor{font-weight:600;color:#066f40;background:var(--chip);border:1px solid #d9eadf;border-radius:999px;padding:3px 8px}
.vogo-acc .meta{color:var(--muted);font-size:.92rem}
.vogo-acc .total{margin-left:auto;font-weight:800;color:var(--vogo-green);white-space:nowrap}
.vogo-acc .body{padding:0 18px 14px}

/* ROW: 3 coloane – thumb | text | prices */
.vogo-suborders .vogo-item{display:grid !important;grid-template-columns:56px minmax(0,1fr) max-content !important;align-items:center !important;column-gap:12px !important;padding:12px 0 !important;border-bottom:1px solid var(--line) !important}
.vogo-suborders .vogo-item:last-child{border-bottom:none !important}
.vogo-suborders .vogo-thumb{width:56px;height:56px;object-fit:cover;border:1px solid #cfd6cf;border-radius:8px;background:#f8f9fb;display:block}
.vogo-suborders .vogo-main{min-width:0;overflow:hidden}
.vogo-suborders .vogo-name{font-weight:600;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

/* RIGHT: one-line prices. Hard override global blocks. */
.vogo-suborders .vogo-prices{justify-self:end !important;display:inline-flex !important;align-items:center !important;gap:10px !important;white-space:nowrap !important;min-width:max-content !important;margin-left:auto !important}
.vogo-suborders .vogo-prices>*{display:inline-block !important;margin:0 !important;padding:0 !important}
.vogo-suborders .vogo-qty{font-weight:700;color:var(--ink)}
.vogo-suborders .vogo-mul,.vogo-suborders .vogo-eq{color:var(--muted);font-weight:600}
.vogo-suborders .vogo-unit{font-weight:700;color:var(--ink)}
.vogo-suborders .vogo-line{font-weight:800;color:var(--vogo-green)}

/* Safety: prevenim împingerea pe rând nou a prețurilor */
.vogo-suborders .vogo-item > *{min-width:0}
.vogo-suborders .vogo-main, .vogo-suborders .vogo-name{overflow:hidden}

/* Mobile */
@media(max-width:860px){
  .vogo-suborders .vogo-item{grid-template-columns:48px minmax(0,1fr) max-content !important;column-gap:10px !important}
  .vogo-suborders .vogo-thumb{width:48px;height:48px}
  .vogo-suborders .vogo-prices{gap:8px !important}
}
</style>
<style>
.vogo-item{display:flex; align-items:center;}
.vogo-qty{display:inline-flex; align-items:center; gap:8px; white-space:nowrap; line-height:1;}
.vogo-thumb{width:32px; height:32px; object-fit:cover; display:block;}
</style>

<div class="vogo-suborders">
  <div class="vogo-h">Sub-orders</div>

  <?php
  $tbl_l=$wpdb->prefix.'orders_lines';
  foreach($children as $sub):
    $sid=(int)$sub->order_id;
    $vendor=trim((string)$sub->provider_name);
    $date=$sub->created_at?date_i18n('Y-m-d H:i',strtotime($sub->created_at)):'';
    $stat=wc_get_order_status_name(ltrim((string)$sub->status,'wc-'));
    $tot=number_format((float)$sub->total_amount,2,',',' ').' '.strtoupper((string)$sub->currency);

    $lines=$wpdb->get_results($wpdb->prepare(
      "SELECT order_item_id,product_id,product_name,qty,line_subtotal,line_subtotal_tax,line_total,line_tax
       FROM {$tbl_l}
       WHERE order_id=%d
       ORDER BY order_item_id ASC",$sid
    ));
  ?>
  <details class="vogo-acc">
    <summary>
      <span class="chev"><svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
      <span class="ttl">#<?php echo esc_html($sid); ?></span>
      <?php if($vendor!==''): ?><span class="vendor"><?php echo esc_html($vendor); ?></span><?php endif; ?>
      <span class="meta">· <?php echo esc_html($date); ?> · <?php echo esc_html($stat); ?></span>
      <span class="total"><?php echo esc_html($tot); ?></span>
    </summary>

    <div class="body">
      <?php foreach($lines as $it):
        $qty=max(1,(int)$it->qty);

        $unit_incl=((float)$it->line_subtotal+(float)$it->line_subtotal_tax);
        $unit_incl=$qty?($unit_incl/$qty):0.0;
        if(!$unit_incl && ($it->line_total || $it->line_tax)){
          $unit_incl=((float)$it->line_total+(float)$it->line_tax);
          $unit_incl=$qty?($unit_incl/$qty):0.0;
        }
        $line_incl=(float)$it->line_total+(float)$it->line_tax;
        if(!$line_incl && ($it->line_subtotal || $it->line_subtotal_tax)){
          $line_incl=(float)$it->line_subtotal+(float)$it->line_subtotal_tax;
        }

        $thumb=wc_placeholder_img_src();
        $pid=(int)$it->product_id;
        if($pid){ $img_id=get_post_thumbnail_id($pid); $src=$img_id?wp_get_attachment_image_url($img_id,'thumbnail'):''; if($src) $thumb=$src; }
      ?>
	  
	  
<div class="vogo-item">
  <span class="vogo-qty">
    <img class="vogo-thumb" src="<?php echo esc_url($thumb); ?>" alt="">
    <?php echo esc_html($it->product_name) . ' - ' . (int)$qty . ' × ' . number_format($unit_incl,2,'.','') . ' = ' . number_format($line_incl,2,'.',''); ?>
  </span>
</div>


      <?php endforeach; ?>
    </div>
  </details>
  <?php endforeach; ?>
</div>
