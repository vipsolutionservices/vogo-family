<?php

// [ADI-ADD] Mobile app view switch via query param.
function vogo_is_mobile_app_view(): bool {
    return isset($_GET['from_mobile_app']) && $_GET['from_mobile_app'] === 'true';
}

// [ADI-ADD] Add a body class we can target with CSS.
add_filter('body_class', function ($classes) {
    if (vogo_is_mobile_app_view()) { $classes[] = 'vogo-mobile-app-view'; }
    return $classes;
}, 50);

// [ADI-ADD] Inject CSS to hide theme header/footer + WoodMart mobile toolbar.
add_action('wp_enqueue_scripts', function () {

    if (!vogo_is_mobile_app_view()) { return; }

    $css = '
/* === VOGO Mobile App View: hide header/footer/toolbars === */
body.vogo-mobile-app-view header,
body.vogo-mobile-app-view footer,
body.vogo-mobile-app-view .whb-header,               /* WoodMart header builder */
body.vogo-mobile-app-view .whb-header-bottom,
body.vogo-mobile-app-view .whb-sticked,
body.vogo-mobile-app-view .page-title,
body.vogo-mobile-app-view .wd-page-title,
body.vogo-mobile-app-view .wd-header,
body.vogo-mobile-app-view .site-header,
body.vogo-mobile-app-view .main-header,
body.vogo-mobile-app-view .footer-container,
body.vogo-mobile-app-view .site-footer,
body.vogo-mobile-app-view .wd-footer,
body.vogo-mobile-app-view .woodmart-footer,
body.vogo-mobile-app-view .wd-toolbar,              /* WoodMart mobile bottom nav */
body.vogo-mobile-app-view .woodmart-toolbar,
body.vogo-mobile-app-view .wd-bottom-toolbar,
body.vogo-mobile-app-view .wd-sticky-nav,
body.vogo-mobile-app-view .wd-sticky-social,
body.vogo-mobile-app-view .wd-tools,
body.vogo-mobile-app-view .mobile-nav,
body.vogo-mobile-app-view .mobile-nav-wrap {
    display: none !important;
}

/* Remove spacing added by theme for sticky header/toolbars */
body.vogo-mobile-app-view {
    padding-top: 0 !important;
    margin-top: 0 !important;
}
body.vogo-mobile-app-view .main-page-wrapper,
body.vogo-mobile-app-view #content,
body.vogo-mobile-app-view .site-content {
    margin-top: 0 !important;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
}
';

    wp_register_style('vogo-mobile-app-view', false);
    wp_enqueue_style('vogo-mobile-app-view');
    wp_add_inline_style('vogo-mobile-app-view', $css);

}, 50);
