<?php
/**
 * Shortcode: [vogo_mobile_categories]
 * Afiseaza pe homepage-ul web gridul de categorii mobile — EXACT aceeasi sursa
 * ca endpoint-ul mobile POST /wp-json/vogo/v1/category-list
 * (handler: rest/all-categories.php -> custom_category_list_all()).
 *
 * Sursa de date canonica (Backend confirmat 2026-04-21, mesaje-woo-backend.txt):
 *   - tabela: `{prefix}vogo_mobile_categories` (term_id, position DECIMAL(10,2))
 *   - alimentata din admin /wp-admin/admin.php?page=vogo-mobile-categories (drag&drop)
 *   - INNER JOIN pe wp_term_taxonomy (parent=0 pentru homepage top-level)
 *   - ordine canonica: ORDER BY vmc.position ASC, vmc.term_id ASC
 *   - thumbnail: term_meta thumbnail_id -> wp_get_attachment_url()
 *   - acces anonim direct din DB, fara JWT
 *   - cache scurt (5min) — se schimba prin drag&drop in admin
 *
 * WARNING (Backend, bug confirmat prod):
 *   NU folosi `get_terms(['include'=>$ids,'orderby'=>'include'])` — un filter
 *   3rd-party (Woodmart/Woo) se agata pe `get_terms_orderby` si rescrie ORDER
 *   BY. Folosim SQL raw + get_term() per ID pentru a pastra ordinea exacta.
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
        'parent'         => 0,     // doar nivel 1 pe homepage
        'columns'        => 4,     // coloane desktop (>= 1024px)
        'columns_tablet' => '',    // coloane tablet (600-1024px) — default: min(4, columns)
        'columns_mobile' => '',    // coloane mobile (< 600px) — default: 2
        'limit'          => 100,   // hard cap conform contract
        'debug'          => 0,     // debug=1 afiseaza position+term_id sub fiecare card + bypass cache
    ], $atts, 'vogo_mobile_categories' );

    $parent  = (int) $atts['parent'];
    $columns = max( 1, min( 6, (int) $atts['columns'] ) );
    $limit   = max( 1, min( 200, (int) $atts['limit'] ) );
    $debug   = (int) $atts['debug'] === 1;

    // Coloane per breakpoint — calcul responsive sanatos
    // Tablet default = min(4, columns) ca sa nu fie stramt pe iPad
    // Mobile default = 2 (fix — nu incap mai mult de 2 card-uri 1:1 pe ecran <600px)
    $grid_lg = $columns;
    $grid_md = ( $atts['columns_tablet'] !== '' )
        ? max( 1, min( 6, (int) $atts['columns_tablet'] ) )
        : min( 4, $grid_lg );
    $grid_sm = ( $atts['columns_mobile'] !== '' )
        ? max( 1, min( 6, (int) $atts['columns_mobile'] ) )
        : 2;

    // Cache 5min — ordinea se poate schimba frecvent prin drag&drop in admin
    // Versionare cheie — bump la schimbari de query/markup/CSS pentru invalidare
    // Debug mode = bypass cache complet (vezi intotdeauna starea live din DB)
    $cache_key = 'vogo_mobile_cats_v8_' . md5( $parent . '_' . $columns . '_' . $grid_md . '_' . $grid_sm . '_' . $limit );
    if ( ! $debug ) {
        $cached = wp_cache_get( $cache_key, 'vogo_mobile_categories' );
        if ( $cached !== false ) {
            return $cached;
        }
    }

    global $wpdb;
    $vmc_table = $wpdb->prefix . 'vogo_mobile_categories';

    // Pas 1: ia term_id + position DIN tabela canonica, ordonate dupa position
    // Inner join pe wp_term_taxonomy asigura filtrul parent=0 (top-level homepage)
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT vmc.term_id, vmc.position
         FROM {$vmc_table} vmc
         INNER JOIN {$wpdb->term_taxonomy} tt
            ON tt.term_id = vmc.term_id AND tt.taxonomy = 'product_cat'
         WHERE tt.parent = %d
         ORDER BY vmc.position ASC, vmc.term_id ASC
         LIMIT %d",
        $parent,
        $limit
    ) );

    if ( empty( $rows ) ) {
        return $debug ? '<pre style="padding:12px;background:#fee;color:#900;">[vogo_mobile_categories DEBUG] Tabela ' . esc_html( $vmc_table ) . ' e GOALA sau nu contine categorii product_cat cu parent=' . (int) $parent . '</pre>' : '';
    }

    // Pas 2: fetch WP_Term per ID pastrand ordinea din SQL
    // NU get_terms+orderby=include — Woodmart rescrie ORDER BY (bug confirmat prod)
    $terms = [];
    $positions = []; // term_id => position, pentru debug overlay
    foreach ( $rows as $row ) {
        $tid = (int) $row->term_id;
        $t = get_term( $tid, 'product_cat' );
        if ( $t && ! is_wp_error( $t ) ) {
            $terms[] = $t;
            $positions[ $tid ] = $row->position;
        }
    }

    if ( empty( $terms ) ) {
        return '';
    }

    // CSS scoped — aplicat DOAR pe gridul nostru prin .vogo-mobile-categories-grid
    // 1. Eticheta categoriei: culoare vogoBlue (#1565C0) + font 14px bold, uniform
    // 2. Imaginile: aspect patrat 1:1 fortat prin aspect-ratio + object-fit cover
    // 3. Responsive: grid cu media queries explicite — nu lasam WoodMart sa decida
    //    cate coloane pe tablet/mobile, suprascriem cu valorile cerute de user.
    // Aspect patrat 1:1 forțat pentru imagini uniforme
    $css  = '.vogo-mobile-categories-grid .category-image-wrapp{aspect-ratio:1/1;overflow:hidden;position:relative;}';
    $css .= '.vogo-mobile-categories-grid .vogo-mob-cat-img-link{display:block;width:100%;height:100%;}';
    $css .= '.vogo-mobile-categories-grid .vogo-mob-cat-img-link img{width:100%;height:100%;object-fit:cover;display:block;}';
    // Titlu categoriei — vogoBlue, bold, centrat
    $css .= '.vogo-mobile-categories-grid .vogo-mob-cat-title,.vogo-mobile-categories-grid .vogo-mob-cat-title-link{color:#1565C0 !important;font-size:14px !important;font-weight:600 !important;line-height:1.3 !important;text-align:center;text-decoration:none;}';
    $css .= '.vogo-mobile-categories-grid .wd-cat-wrap,.vogo-mobile-categories-grid .vogo-mob-cat-title-wrap{text-align:center;}';
    // Overlay clickabil pe tot cardul — absolut, peste imagine + titlu
    $css .= '.vogo-mobile-categories-grid .wrapp-category{position:relative;}';
    $css .= '.vogo-mobile-categories-grid .vogo-mob-cat-overlay{position:absolute;inset:0;z-index:5;}';
    // Grid responsive fortat — CSS Grid cu breakpoint-uri standard Elementor
    $css .= '.vogo-mobile-categories-grid .products.wd-products{display:grid !important;grid-template-columns:repeat(' . (int) $grid_lg . ',1fr) !important;gap:16px !important;min-height:0 !important;}';
    $css .= '.vogo-mobile-categories-grid .wd-col{width:auto !important;max-width:100% !important;margin:0 !important;padding:0 !important;}';
    $css .= '@media (max-width:1024px){.vogo-mobile-categories-grid .products.wd-products{grid-template-columns:repeat(' . (int) $grid_md . ',1fr) !important;gap:12px !important;}}';
    $css .= '@media (max-width:600px){.vogo-mobile-categories-grid .products.wd-products{grid-template-columns:repeat(' . (int) $grid_sm . ',1fr) !important;gap:10px !important;}';
    $css .= '.vogo-mobile-categories-grid .vogo-mob-cat-title,.vogo-mobile-categories-grid .vogo-mob-cat-title-link{font-size:12px !important;}}';
    $output  = '<style>' . $css . '</style>';

    // Debug banner — afiseaza primele 20 iteme exact asa cum le intoarce SQL-ul
    if ( $debug ) {
        $output .= '<pre style="padding:12px;background:#eef;color:#003;font-size:12px;border:2px solid #003;margin:10px 0;">';
        $output .= "[vogo_mobile_categories DEBUG]\n";
        $output .= 'Tabela: ' . esc_html( $vmc_table ) . "\n";
        $output .= 'SQL WHERE tt.parent = ' . (int) $parent . "\n";
        $output .= 'Total rows returnate: ' . count( $rows ) . "\n";
        $output .= "--- ORDINEA EXACTA DIN SQL ---\n";
        $output .= sprintf( "%-4s %-8s %-8s %-30s %s\n", '#', 'term_id', 'position', 'name', 'url_from_get_term_link' );
        foreach ( $rows as $i => $row ) {
            $t = get_term( (int) $row->term_id, 'product_cat' );
            $nm = ( $t && ! is_wp_error( $t ) ) ? $t->name : '(term missing)';
            $lk = ( $t && ! is_wp_error( $t ) ) ? get_term_link( (int) $row->term_id, 'product_cat' ) : '(no term)';
            if ( is_wp_error( $lk ) ) { $lk = 'ERR: ' . $lk->get_error_message(); }
            $output .= sprintf( "%-4d %-8d %-8s %-30s %s\n", $i + 1, $row->term_id, $row->position, $nm, $lk );
        }
        $output .= '</pre>';
    }
    $output .= '<div class="vogo-mobile-categories-grid elementor-element cat-list elementor-grid-' . esc_attr( $grid_lg ) . ' elementor-grid-tablet-' . esc_attr( $grid_md ) . ' elementor-grid-mobile-' . esc_attr( $grid_sm ) . ' elementor-products-grid elementor-wc-products elementor-widget elementor-widget-wc-categories" data-element_type="widget" data-widget_type="wc-categories.default">';
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

        // FIX BUG 3rd-party JS: un script WoodMart/Woo itera toate elementele
        // cu class-urile standard (.category-image, .wd-entities-title a, .category-link)
        // si le seta toate la ULTIMUL href (classic closure bug). Solutia: scoatem
        // class-urile standard de pe <a>-uri — le pastram doar pe wrapper-ele <div>.
        // Pe link-uri folosim class-uri custom unice (.vogo-mob-cat-*) care NU sunt
        // targetate de niciun script extern → navigare nativa HTML, zero JS.
        //
        // Atributul data-ajax="false" previne AJAX shop interception WoodMart/Elementor.
        $link_attrs = 'href="' . esc_url( $category_link ) . '" data-ajax="false" data-shop-ajax="no"';

        $output .= '<div class="wd-col category-grid-item wd-cat cat-design-alt categories-with-shadow wd-with-subcat product-category product" data-loop="' . ( $index + 1 ) . '">';
        $output .=     '<div class="wd-cat-wrap">';
        $output .=         '<div class="wrapp-category">';
        $output .=             '<div class="category-image-wrapp">';
        $output .=                 '<a ' . $link_attrs . ' class="vogo-mob-cat-img-link" aria-label="' . esc_attr( $term->name ) . '">';
        $output .=                     '<img loading="lazy" width="600" height="600" src="' . esc_url( $thumbnail_url ) . '" class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail" alt="' . esc_attr( $term->name ) . '">';
        $output .=                 '</a>';
        $output .=             '</div>';
        $output .=             '<div class="vogo-mob-cat-title-wrap">';
        $output .=                 '<h3 class="vogo-mob-cat-title notranslate"><a ' . $link_attrs . ' class="vogo-mob-cat-title-link">' . esc_html( $term->name ) . '</a></h3>';
        // Debug overlay pe card — pos:X.XX | id:NNN (doar cand debug=1)
        if ( $debug ) {
            $pos_val = isset( $positions[ $term->term_id ] ) ? $positions[ $term->term_id ] : '?';
            $output .=             '<div style="font-size:10px;color:#900;background:#ffe;padding:2px;text-align:center;">pos:' . esc_html( $pos_val ) . ' | id:' . (int) $term->term_id . '</div>';
        }
        $output .=             '</div>';
        // Overlay clickabil pe toata suprafata cardului cu clasa unica (nu .category-link)
        $output .=             '<a ' . $link_attrs . ' class="vogo-mob-cat-overlay" aria-label="' . esc_attr( $term->name ) . '"></a>';
        $output .=         '</div>';
        $output .=     '</div>';
        $output .= '</div>';
    }

    $output .= '</div></div></div>';

    // Nu mai emitam JS safety net — era posibil sa intre in conflict cu scripturile
    // 3rd-party. Fiecare <a> are deja onclick inline care navigheaza corect la
    // href-ul sau specific (citit hardcoded cu esc_js la generare, nu prin .href).

    // Salveaza in cache pentru 5 minute (scurt — ordinea se schimba prin drag&drop admin)
    // Skip cache cand debug=1 ca sa vezi mereu starea live
    if ( ! $debug ) {
        wp_cache_set( $cache_key, $output, 'vogo_mobile_categories', 5 * MINUTE_IN_SECONDS );
    }

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
