<?php
// adaugă în perf.php, în același wp_footer callback unde emiți JSON-ul
add_action('wp_footer', function () {
    if (!isset($_GET['perf']) || is_admin()) return;
    global $wp_scripts, $wp_styles;

    if ($wp_scripts instanceof WP_Scripts) {
        foreach ((array)$wp_scripts->queue as $h) {
            $r = $wp_scripts->registered[$h] ?? null; if (!$r) continue;
            $src = $r->src; if (strpos($src,'//')===0) $src='https:'.$src; if (strpos($src,'http')!==0) $src=site_url($src);
            error_log("PERF_JS|$h|$src");
            if (stripos($src,'recaptcha')!==false) error_log("PERF_HIT_RECAPTCHA|$h|$src");
        }
    }
    if ($wp_styles instanceof WP_Styles) {
        foreach ((array)$wp_styles->queue as $h) {
            $r = $wp_styles->registered[$h] ?? null; if (!$r) continue;
            $src = $r->src; if (strpos($src,'//')===0) $src='https:'.$src; if (strpos($src,'http')!==0) $src=site_url($src);
            error_log("PERF_CSS|$h|$src");
        }
    }
}, 10000);
