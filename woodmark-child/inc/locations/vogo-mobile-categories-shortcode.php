<?php
/**
 * Shortcode: [vogo_mobile_categories]
 * Afiseaza pe homepage-ul web gridul de categorii mobile — EXACT aceeasi sursa
 * ca endpoint-ul mobile POST /wp-json/vogo/v1/category-list
 * (handler: rest/all-categories.php -> custom_category_list_all()).
 *
 * Sursa de date confirmata de Backend in mesaje-woo-backend.txt (2026-04-21):
 *   - filtru: term_meta mobile_category = '1' (string)
 *   - sortare: term_id ASC (NU term_order — nu mai exista in WP core)
 *   - thumbnail: term_meta thumbnail_id -> wp_get_attachment_url()
 *   - acces anonim direct din DB, fara JWT
 *   - cache recomandat TTL 15min
 *
 * HTML-ul respecta aceleasi clase WoodMart/Elementor ca in
 * city-category-shortcode.php — layout identic cu widget-ul existent.
 *
 * @AI:SOURCE — unica sursa de adevar pentru gridul de categorii mobile pe web.
 *              Nu duplica logica in alt fisier. Modificari aici = afecteaza homepage.
 * @AI:DEPENDS — contractul vogo-contracts/catalog/category-list.md. Daca Backend
 *              schimba filtrul mobile_category, actualizeaza si acest fisier.
 */

// Inregistreaza shortcode-ul [vogo_mobile_categories]
add_shortcode( 'vogo_mobile_categories', 'vogo_mobile_categories_shortcode' );

function vogo_mobile_categories_shortcode( $atts = [] ) {

    // Atribute opționale — parent_id pentru top-level (0) sau sub-tree
    $atts = shortcode_atts( [
        'parent'  => 0,      // doar nivel 1 pe homepage
        'columns' => 4,      // coloane desktop
        'limit'   => 100,    // hard cap conform contract
    ], $atts, 'vogo_mobile_categories' );

    $parent  = (int) $atts['parent'];
    $columns = max( 1, min( 6, (int) $atts['columns'] ) );
    $limit   = max( 1, min( 200, (int) $atts['limit'] ) );

    // Cache 15min — recomandat de Backend (categoriile se schimba rar)
    $cache_key = 'vogo_mobile_cats_' . md5( $parent . '_' . $columns . '_' . $limit );
    $cached = wp_cache_get( $cache_key, 'vogo_mobile_categories' );
    if ( $cached !== false ) {
        return $cached;
    }

    // Query identic cu custom_category_list_all() — meta_query pe mobile_category='1'
    $terms = get_terms( [
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'parent'     => $parent,
        'orderby'    => 'term_id',
        'order'      => 'ASC',
        'number'     => $limit,
        'meta_query' => [
            [
                'key'     => 'mobile_category',
                'value'   => '1',
                'compare' => '=',
            ],
        ],
    ] );

    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        return '';
    }

    // Wrapper identic cu city-category-shortcode.php pentru consistenta vizuala
    $grid_lg = $columns;
    $grid_md = max( 1, $columns - 1 );
    $grid_sm = max( 1, $columns - 2 );

    $output  = '<div class="elementor-element cat-list elementor-grid-' . esc_attr( $grid_lg ) . ' elementor-grid-tablet-' . esc_attr( $grid_md ) . ' elementor-grid-mobile-' . esc_attr( $grid_sm ) . ' elementor-products-grid elementor-wc-products elementor-widget elementor-widget-wc-categories" data-element_type="widget" data-widget_type="wc-categories.default">';
    $output .= '<div class="woocommerce columns-' . esc_attr( $grid_lg ) . '">';
    $output .= '<div class="products wd-products grid-masonry wd-grid-f-col grid-columns-' . esc_attr( $grid_lg ) . ' elements-grid pagination-pagination title-line-two wd-stretch-cont-lg wd-stretch-cont-md wd-stretch-cont-sm wd-products-with-bg" data-columns="' . esc_attr( $grid_lg ) . '" style="--wd-col-lg: ' . esc_attr( $grid_lg ) . '; --wd-col-md: ' . esc_attr( $grid_md ) . '; --wd-col-sm: ' . esc_attr( $grid_sm ) . '; --wd-gap-lg: 20px; --wd-gap-sm: 10px; position: relative; min-height: 400px;">';

    // Construieste un card per categorie
    foreach ( $terms as $index => $term ) {

        // Link canonic Woo catre arhiva categoriei
        $category_link = get_term_link( (int) $term->term_id, 'product_cat' );
        if ( is_wp_error( $category_link ) ) {
            continue;
        }

        // Thumbnail: term_meta thumbnail_id -> attachment URL, fallback placeholder Woo
        $thumbnail_id  = get_term_meta( $term->term_id, 'thumbnail_id', true );
        $thumbnail_url = $thumbnail_id ? wp_get_attachment_url( (int) $thumbnail_id ) : wc_placeholder_img_src();

        $output .= '<div class="wd-col category-grid-item wd-cat cat-design-alt categories-with-shadow wd-with-subcat product-category product" data-loop="' . ( $index + 1 ) . '">';
        $output .=     '<div class="wd-cat-wrap">';
        $output .=         '<div class="wrapp-category">';
        $output .=             '<div class="category-image-wrapp">';
        $output .=                 '<a href="' . esc_url( $category_link ) . '" class="category-image" aria-label="' . esc_attr( $term->name ) . '">';
        $output .=                     '<img loading="lazy" width="600" height="600" src="' . esc_url( $thumbnail_url ) . '" class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail" alt="' . esc_attr( $term->name ) . '">';
        $output .=                 '</a>';
        $output .=             '</div>';
        $output .=             '<div class="hover-mask">';
        $output .=                 '<h3 class="wd-entities-title notranslate"><a href="' . esc_url( $category_link ) . '" data-ajax="false">' . esc_html( $term->name ) . '</a></h3>';
        $output .=             '</div>';
        $output .=             '<span class="category-link wd-fill" aria-hidden="true"></span>';
        $output .=         '</div>';
        $output .=     '</div>';
        $output .= '</div>';
    }

    $output .= '</div></div></div>';

    // Salveaza in cache pentru 15 minute
    wp_cache_set( $cache_key, $output, 'vogo_mobile_categories', 15 * MINUTE_IN_SECONDS );

    return $output;
}

// Invalideaza cache-ul cand se modifica o categorie product_cat sau term_meta relevant
add_action( 'edited_product_cat',       'vogo_mobile_categories_flush_cache' );
add_action( 'created_product_cat',      'vogo_mobile_categories_flush_cache' );
add_action( 'delete_product_cat',       'vogo_mobile_categories_flush_cache' );
add_action( 'updated_term_meta',        'vogo_mobile_categories_flush_cache' );
add_action( 'added_term_meta',          'vogo_mobile_categories_flush_cache' );
add_action( 'deleted_term_meta',        'vogo_mobile_categories_flush_cache' );

function vogo_mobile_categories_flush_cache() {
    // Sterge intregul grup de cache — cheile sunt md5, nu le enumeram
    wp_cache_flush_group( 'vogo_mobile_categories' );
}
