<?php
/**
 * Plugin Name: VOGO WhatsApp Floating Button
 * Description: Site-wide floating WhatsApp actions with desktop/mobile visibility, corner or anchor positioning, identical api.whatsapp.com links across site. Minimal, fast, configurable.
 * Version: 1.2.1
 * Author: VOGO.FAMILY
 * License: GPLv2 or later
 * Text Domain: vogo-whatsapp
 */

if (!defined('ABSPATH')) exit;

/* ===== Constants ===== */
define('VOGO_WA_VER', '1.2.1');
define('VOGO_WA_SLUG', 'vogo-whatsapp');
define('VOGO_WA_OPT',  'vogo_whatsapp_options');

/* ===== Defaults ===== */
function vogo_wa_default_options(){
  return [
    'enabled'=>1,
    'desktop'=>1, 'mobile'=>1,
    'pos_h_desktop'=>'left','pos_v_desktop'=>'bottom','offset_desktop'=>16,
    'pos_h_mobile'=>'left','pos_v_mobile'=>'bottom','offset_mobile'=>16,
    'show_icon_desktop'=>1,'show_label_desktop'=>1,'show_icon_mobile'=>1,'show_label_mobile'=>0,
    'phone1'=>'40786854023','label1'=>'Assistance 24/7','msg1'=>'VOGO Family | Request for assistance. Language: <write language>; Location: <city>; Topic: <subject>; Page: {{url}}',
    'phone2'=>'40742203383','label2'=>'Verified partners','msg2'=>'VOGO Family | Request verified partners list. City: <enter city>; Domain: <enter domain>; Page: {{url}}',
    'include_url'=>1,'z_index'=>9999,
    /* Anchor mode */
    'anchor_selector'=>'',     // e.g. #wa-anchor or [data-wa-anchor]
    'anchor_offset_x'=>12,
    'anchor_offset_y'=>0,
  ];
}

/* ===== Options Helpers ===== */
function vogo_wa_get_options(){
  $saved = get_option(VOGO_WA_OPT, []);
  return wp_parse_args(is_array($saved) ? $saved : [], vogo_wa_default_options());
}

/* ===== Admin Settings ===== */
add_action('admin_menu', function(){
  add_submenu_page('vogo-brand-options','VOGO WhatsApp','VOGO WhatsApp','manage_options',VOGO_WA_SLUG,'vogo_wa_render_settings_page');
});

add_action('admin_init', function(){
  register_setting(VOGO_WA_SLUG, VOGO_WA_OPT, [
    'type'=>'array','sanitize_callback'=>'vogo_wa_sanitize_options','default'=>vogo_wa_default_options(),
  ]);

  add_settings_section('vogo_wa_main','Floating WhatsApp', function(){
    echo '<p>Configure floating WhatsApp buttons. Injected via <code>wp_footer</code>. Messages support token <code>{{url}}</code>.</p>';
  }, VOGO_WA_SLUG);

  $fields = [
    ['enabled','Enable plugin','checkbox'],
    ['desktop','Show on Desktop','checkbox'],
    ['mobile','Show on Mobile','checkbox'],
    ['pos_h_desktop','Desktop Horizontal','select',['left'=>'left','right'=>'right']],
    ['pos_v_desktop','Desktop Vertical','select',['bottom'=>'bottom','top'=>'top']],
    ['offset_desktop','Desktop Offset (px)','number'],
    ['pos_h_mobile','Mobile Horizontal','select',['left'=>'left','right'=>'right']],
    ['pos_v_mobile','Mobile Vertical','select',['bottom'=>'bottom','top'=>'top']],
    ['offset_mobile','Mobile Offset (px)','number'],
    ['show_icon_desktop','Desktop: Show icon','checkbox'],
    ['show_label_desktop','Desktop: Show label','checkbox'],
    ['show_icon_mobile','Mobile: Show icon','checkbox'],
    ['show_label_mobile','Mobile: Show label','checkbox'],
    ['phone1','Phone #1 (digits only)','text'],
    ['label1','Label #1','text'],
    ['msg1','Message #1','textarea'],
    ['phone2','Phone #2 (digits only)','text'],
    ['label2','Label #2','text'],
    ['msg2','Message #2','textarea'],
    ['include_url','Append current URL to message','checkbox'],
    ['z_index','z-index','number'],
    /* Anchor */
    ['anchor_selector','Anchor CSS Selector (ex: #wa-anchor)','text'],
    ['anchor_offset_x','Anchor offset X (px)','number'],
    ['anchor_offset_y','Anchor offset Y (px)','number'],
  ];

  foreach($fields as $f){
    add_settings_field("vogo_wa_{$f[0]}", esc_html($f[1]), 'vogo_wa_field_cb', VOGO_WA_SLUG, 'vogo_wa_main', [
      'key'=>$f[0], 'type'=>$f[2], 'choices'=> $f[3] ?? []
    ]);
  }
});

function vogo_wa_field_cb($args){
  $o = vogo_wa_get_options(); $k = $args['key']; $t = $args['type']; $choices = $args['choices']; $name = VOGO_WA_OPT."[$k]";
  if($t==='checkbox'){
    echo '<label><input type="checkbox" name="'.esc_attr($name).'" value="1" '.checked(1, !empty($o[$k]), false).'/> Enabled</label>';
  } elseif($t==='select'){
    echo '<select name="'.esc_attr($name).'">'; foreach($choices as $val=>$label){
      echo '<option value="'.esc_attr($val).'" '.selected($o[$k]??'', $val, false).'>'.esc_html($label).'</option>'; } echo '</select>';
  } elseif($t==='number'){
    echo '<input type="number" min="0" step="1" name="'.esc_attr($name).'" value="'.esc_attr(intval($o[$k]??0)).'"/>';
  } elseif($t==='textarea'){
    echo '<textarea name="'.esc_attr($name).'" rows="4" style="width: 600px;">'.esc_textarea($o[$k]??'').'</textarea>';
    if(in_array($k, ['msg1','msg2'], true)){ echo '<p class="description">Token: <code>{{url}}</code> will be replaced with the current page URL.</p>'; }
  } else {
    echo '<input type="text" name="'.esc_attr($name).'" value="'.esc_attr($o[$k]??'').'" style="width: 400px;"/>';
  }
}

function vogo_wa_render_settings_page(){
  if(!current_user_can('manage_options')) return;
  echo '<div class="wrap"><h1>VOGO WhatsApp</h1><form method="post" action="options.php">';
  settings_fields(VOGO_WA_SLUG); do_settings_sections(VOGO_WA_SLUG); submit_button(); echo '</form></div>';
}

/* ===== Sanitize ===== */
function vogo_wa_sanitize_options($in){
  $d = vogo_wa_default_options(); $out = [];
  $out['enabled'] = empty($in['enabled']) ? 0 : 1;
  $out['desktop'] = empty($in['desktop']) ? 0 : 1;
  $out['mobile']  = empty($in['mobile'])  ? 0 : 1;
  $out['pos_h_desktop'] = in_array(($in['pos_h_desktop'] ?? $d['pos_h_desktop']), ['left','right'], true) ? $in['pos_h_desktop'] : $d['pos_h_desktop'];
  $out['pos_v_desktop'] = in_array(($in['pos_v_desktop'] ?? $d['pos_v_desktop']), ['bottom','top'], true) ? $in['pos_v_desktop'] : $d['pos_v_desktop'];
  $out['offset_desktop']= max(0, intval($in['offset_desktop'] ?? $d['offset_desktop']));
  $out['pos_h_mobile']  = in_array(($in['pos_h_mobile'] ?? $d['pos_h_mobile']), ['left','right'], true) ? $in['pos_h_mobile'] : $d['pos_h_mobile'];
  $out['pos_v_mobile']  = in_array(($in['pos_v_mobile'] ?? $d['pos_v_mobile']), ['bottom','top'], true) ? $in['pos_v_mobile'] : $d['pos_v_mobile'];
  $out['offset_mobile'] = max(0, intval($in['offset_mobile'] ?? $d['offset_mobile']));
  $out['show_icon_desktop']  = empty($in['show_icon_desktop'])  ? 0 : 1;
  $out['show_label_desktop'] = empty($in['show_label_desktop']) ? 0 : 1;
  $out['show_icon_mobile']   = empty($in['show_icon_mobile'])   ? 0 : 1;
  $out['show_label_mobile']  = empty($in['show_label_mobile'])  ? 0 : 1;
  $out['phone1'] = preg_replace('/\D+/', '', strval($in['phone1'] ?? $d['phone1']));
  $out['label1'] = sanitize_text_field($in['label1'] ?? $d['label1']);
  $out['msg1']   = wp_kses_post($in['msg1'] ?? $d['msg1']);
  $out['phone2'] = preg_replace('/\D+/', '', strval($in['phone2'] ?? $d['phone2']));
  $out['label2'] = sanitize_text_field($in['label2'] ?? $d['label2']);
  $out['msg2']   = wp_kses_post($in['msg2'] ?? $d['msg2']);
  $out['include_url'] = empty($in['include_url']) ? 0 : 1;
  $out['z_index']     = max(1, intval($in['z_index'] ?? $d['z_index']));
  /* Anchor */
  $out['anchor_selector'] = sanitize_text_field($in['anchor_selector'] ?? $d['anchor_selector']);
  $out['anchor_offset_x'] = intval($in['anchor_offset_x'] ?? $d['anchor_offset_x']);
  $out['anchor_offset_y'] = intval($in['anchor_offset_y'] ?? $d['anchor_offset_y']);
  return $out;
}

/* ===== Public helpers: href / renderer / shortcodes ===== */
function vogo_wa_get_href(int $id = 1): string {
  $o = vogo_wa_get_options();
  $i = ($id === 2) ? 1 : 0;
  $phone = preg_replace('/\D+/', '', $o[ $i ? 'phone2':'phone1' ] ?? '');
  if(!$phone) return '#';
  $msg = (string)($o[ $i ? 'msg2':'msg1' ] ?? '');
  if(!empty($o['include_url'])){
    $scheme = is_ssl() ? 'https://' : 'http://';
    $current = $scheme . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');
    $msg = str_replace('{{url}}', $current, $msg);
  } else { $msg = str_replace('{{url}}','',$msg); }
  // Format standard api.whatsapp.com cu phone prefix %2B (+) + type + app_absent
  return 'https://api.whatsapp.com/send/?phone=%2B' . $phone . '&text=' . rawurlencode($msg) . '&type=phone_number&app_absent=0';
}

function vogo_wa_render_link(int $id = 1, string $label = 'Solicită asistență acum', array $attrs = []): string {
  $href = vogo_wa_get_href($id);
  $defaults = ['class'=>'vogo-wa-link','target'=>'_blank','rel'=>'noopener'];
  $attrs = array_merge($defaults, $attrs);
  $attr_html=''; foreach($attrs as $k=>$v){ $attr_html .= ' '.esc_attr($k).'="'.esc_attr($v).'"'; }
  return '<a href="'.esc_url($href).'"'.$attr_html.'>'.esc_html($label).'</a>';
}

add_shortcode('vogo_wa_link', function($atts){
  $a = shortcode_atts(['id'=>'1','label'=>'Solicită asistență acum','class'=>'vogo-wa-link'], $atts, 'vogo_wa_link');
  return vogo_wa_render_link((int)$a['id'], (string)$a['label'], ['class'=>$a['class']]);
});

/* Optional anchor shortcode */
add_shortcode('vogo_wa_anchor', function($atts){
  $a = shortcode_atts(['id'=>'wa-anchor','class'=>''], $atts, 'vogo_wa_anchor');
  return '<span id="'.esc_attr($a['id']).'" class="'.esc_attr($a['class']).'"></span>';
});

/* ===== Front Assets ===== */
add_action('wp_enqueue_scripts', function(){
  $o = vogo_wa_get_options(); if(empty($o['enabled'])) return;

  wp_register_style('vogo-wa-front', plugins_url('assets/css/front.css', __FILE__), [], VOGO_WA_VER);
  wp_enqueue_style('vogo-wa-front');

  /* Minimal inline CSS fallback */
  $inline_css = '
  #vogo-wa-root{position:fixed;inset:auto;z-index:'.intval($o['z_index']).'}
  .vogo-wa-wrap{display:flex;gap:10px}
  .vogo-wa-btn{display:inline-block;padding:10px 14px;border-radius:10px;background:#25D366;color:#fff;text-decoration:none;font-weight:600;box-shadow:0 6px 20px rgba(0,0,0,.15)}
  .vogo-wa-btn:hover{background:#1ebe57}
  ';
  wp_add_inline_style('vogo-wa-front', $inline_css);

  wp_register_script('vogo-wa-front', plugins_url('assets/js/front.js', __FILE__), [], VOGO_WA_VER, true);

  wp_localize_script('vogo-wa-front', 'VOGO_WA', [
    'visible'=>['desktop'=>(int)$o['desktop'],'mobile'=>(int)$o['mobile']],
    'position'=>[
      'desktop'=>['h'=>$o['pos_h_desktop'],'v'=>$o['pos_v_desktop'],'offset'=>intval($o['offset_desktop'])],
      'mobile'=> ['h'=>$o['pos_h_mobile'], 'v'=>$o['pos_v_mobile'], 'offset'=>intval($o['offset_mobile'])],
    ],
    'ui'=>[
      'desktop'=>['icon'=>(int)$o['show_icon_desktop'],'label'=>(int)$o['show_label_desktop']],
      'mobile'=> ['icon'=>(int)$o['show_icon_mobile'], 'label'=>(int)$o['show_label_mobile']],
    ],
    'includeUrl'=>(int)$o['include_url'],
    'zIndex'=>intval($o['z_index']),
    'items'=>[
      ['phone'=>$o['phone1'],'label'=>$o['label1'],'msg'=>$o['msg1']],
      ['phone'=>$o['phone2'],'label'=>$o['label2'],'msg'=>$o['msg2']],
    ],
    'anchor'=>[
      'selector'=>(string)$o['anchor_selector'],
      'dx'=>intval($o['anchor_offset_x']),
      'dy'=>intval($o['anchor_offset_y']),
    ],
  ]);

  wp_enqueue_script('vogo-wa-front');

  /* Inline JS fallback (runs if file missing) */
  $inline_js = "(function(){
    if(!window.VOGO_WA){return;}
    var cfg=window.VOGO_WA,root=document.getElementById('vogo-wa-root'); if(!root){return;}
    root.style.zIndex=String(cfg.zIndex||9999);
    function btn(item){var a=document.createElement('a');a.className='vogo-wa-btn';a.target='_blank';a.rel='noopener';
      var phone=(item.phone||'').replace(/\\D+/g,'');var msg=String(item.msg||'');
      if(cfg.includeUrl){msg=msg.replace('{{url}}',location.href);} else {msg=msg.replace('{{url}}','');}
      a.href='https://api.whatsapp.com/send/?phone=%2B'+phone+'&text='+encodeURIComponent(msg)+'&type=phone_number&app_absent=0'; a.textContent=item.label||'WhatsApp'; return a;}
    root.innerHTML=''; var wrap=document.createElement('div'); wrap.className='vogo-wa-wrap';
    (cfg.items||[]).forEach(function(it){ if(it && it.phone){ wrap.appendChild(btn(it)); } }); root.appendChild(wrap);
    var isMobile=matchMedia('(max-width: 767px)').matches; var visible=isMobile?cfg.visible.mobile:cfg.visible.desktop;
    if(!visible){root.style.display='none'; return;}
    var sel=(cfg.anchor&&cfg.anchor.selector)?cfg.anchor.selector.trim():'';
    if(sel){ var anchor=document.querySelector(sel); if(anchor){
        root.style.position='fixed'; root.style.left='0px'; root.style.top='0px';
        var dx=(cfg.anchor&&typeof cfg.anchor.dx==='number')?cfg.anchor.dx:12;
        var dy=(cfg.anchor&&typeof cfg.anchor.dy==='number')?cfg.anchor.dy:0;
        var upd=function(){var r=anchor.getBoundingClientRect(); var left=Math.max(0,Math.round(r.right+dx)); var top=Math.max(0,Math.round(r.top+dy)); root.style.transform='translate('+left+'px,'+top+'px)';};
        upd(); window.addEventListener('scroll',upd,{passive:true}); window.addEventListener('resize',upd);
        var mo=new MutationObserver(upd); mo.observe(document.body,{attributes:true,childList:true,subtree:true}); return;
    }}
    root.style.position='fixed';
    var pos=isMobile?cfg.position.mobile:cfg.position.desktop, h=(pos&&pos.h)||'left', v=(pos&&pos.v)||'bottom', off=(pos&&typeof pos.offset==='number')?pos.offset:16;
    root.style.left=''; root.style.right=''; root.style.top=''; root.style.bottom='';
    root.style[h]=off+'px'; root.style[v]=off+'px';
  })();";
  wp_add_inline_script('vogo-wa-front', $inline_js);
});

/* ===== Footer Root Container ===== */
add_action('wp_footer', function(){
  $o = vogo_wa_get_options(); if(empty($o['enabled'])) return;
  echo '<div id="vogo-wa-root" aria-live="polite"></div>';
});
