<?php
/**
 * VOGO.FAMILY — SEO essentials (autonom version)
 * JSON-LD + OG + optional hreflang + breadcrumbs shortcode
 * Safe for any WordPress theme
 * completeaza in seo.php - care este inclus deja in functions.php 
 */

add_action('wp_head', function () {
  if (!is_front_page()) return;

  $site_url  = home_url('/');
  $site_name = get_bloginfo('name') ?: 'VOGO.FAMILY';
  $logo_url  = 'https://vogo.family/wp-content/uploads/logo-vogo.png';
  $og_image  = 'https://vogo.family/wp-content/uploads/hero-vogo.png';
  ?>
  <!-- JSON-LD structured data -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "Organization",
        "name": "<?php echo esc_js($site_name); ?>",
        "url": "<?php echo esc_url($site_url); ?>",
        "logo": "<?php echo esc_url($logo_url); ?>"
      },
      {
        "@type": "WebSite",
        "url": "<?php echo esc_url($site_url); ?>",
        "name": "<?php echo esc_js($site_name); ?>",
        "potentialAction": {
          "@type": "SearchAction",
          "target": "<?php echo esc_url($site_url); ?>?s={search_term_string}",
          "query-input": "required name=search_term_string"
        }
      },
      {
        "@type": "LocalBusiness",
        "name": "<?php echo esc_js($site_name); ?>",
        "image": "<?php echo esc_url($logo_url); ?>",
        "priceRange": "$$",
        "address": { "@type": "PostalAddress", "addressCountry": "RO" },
        "openingHours": "Mo-Su 00:00-23:59",
        "url": "<?php echo esc_url($site_url); ?>"
      }
    ]
  }
  </script>

  <!-- Open Graph + Twitter fallback -->
  <meta property="og:title" content="<?php echo esc_attr(wp_get_document_title()); ?>" />
  <meta property="og:description" content="<?php echo esc_attr(get_bloginfo('description')); ?>" />
  <meta property="og:image" content="<?php echo esc_url($og_image); ?>" />
  <meta property="og:url" content="<?php echo esc_url($site_url); ?>" />
  <meta property="og:type" content="website" />
  <meta property="og:site_name" content="<?php echo esc_attr($site_name); ?>" />
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="<?php echo esc_attr(wp_get_document_title()); ?>" />
  <meta name="twitter:description" content="<?php echo esc_attr(get_bloginfo('description')); ?>" />
  <meta name="twitter:image" content="<?php echo esc_url($og_image); ?>" />
  <?php
}, 99);

/* Breadcrumb shortcode for easy use in page builders */
add_shortcode('vogo_breadcrumbs', function () {
  if (function_exists('yoast_breadcrumb')) {
    ob_start();
    yoast_breadcrumb('<p id="breadcrumbs">', '</p>');
    return ob_get_clean();
  }
  return '';
});


/* FAVICON */

// Favicon + platform icons for all browsers
if ( ! function_exists( 'vogo_output_favicons' ) ) {
    function vogo_output_favicons() {
        // Base URL for root-level favicon files
        $base = home_url( '/' );
        ?>
        <!-- VOGO Favicons & Platform Icons -->
        <link rel="icon" type="image/png" sizes="16x16" href="<?php echo esc_url( $base . 'favicon-16x16.png' ); ?>" />
        <link rel="icon" type="image/png" sizes="32x32" href="<?php echo esc_url( $base . 'favicon-32x32.png' ); ?>" />
        <link rel="icon" type="image/png" sizes="48x48" href="<?php echo esc_url( $base . 'favicon-48x48.png' ); ?>" />
        <link rel="icon" type="image/png" sizes="96x96" href="<?php echo esc_url( $base . 'favicon-96x96.png' ); ?>" />

        <link rel="icon" type="image/svg+xml" href="<?php echo esc_url( $base . 'favicon.svg' ); ?>" />
        <link rel="shortcut icon" href="<?php echo esc_url( $base . 'favicon.ico' ); ?>" />

        <!-- iOS Apple Touch Icons (reusing same file if needed) -->
        <link rel="apple-touch-icon" sizes="76x76" href="<?php echo esc_url( $base . 'apple-touch-icon.png' ); ?>" />
        <link rel="apple-touch-icon" sizes="120x120" href="<?php echo esc_url( $base . 'apple-touch-icon.png' ); ?>" />
        <link rel="apple-touch-icon" sizes="152x152" href="<?php echo esc_url( $base . 'apple-touch-icon.png' ); ?>" />
        <link rel="apple-touch-icon" sizes="180x180" href="<?php echo esc_url( $base . 'apple-touch-icon.png' ); ?>" />

        <!-- PWA manifest -->
        <link rel="manifest" href="<?php echo esc_url( $base . 'site.webmanifest' ); ?>" />

        <!-- Safari pinned tab (optional, reuses SVG) -->
        <link rel="mask-icon" href="<?php echo esc_url( $base . 'favicon.svg' ); ?>" color="#0c542d" />

        <!-- Windows tiles -->
        <meta name="msapplication-TileColor" content="#0c542d" />
        <meta name="msapplication-TileImage" content="<?php echo esc_url( $base . 'mstile-150x150.png' ); ?>" />
        <!-- End VOGO Favicons -->
        <?php
    }
}

add_action( 'wp_head', 'vogo_output_favicons', 5 );

add_action('wp_head', function () {
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
}, 1);

add_filter( 'language_attributes', function( $output ) {
    return 'lang="ro"';
});