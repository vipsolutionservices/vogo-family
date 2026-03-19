<?php
// meta boxes

add_action('add_meta_boxes', function () {
    add_meta_box('product_city_meta', 'Available Cities', 'render_product_city_meta', 'product', 'side');
    add_meta_box('product_mall_meta', 'Available Mall',  'render_product_mall_meta',  'product', 'side');
});


/**
 * Save product cities + malls (meta + optimized tables)
 */
add_action('save_post_product', function ($post_id) {
    global $wpdb;
    $user_id = get_current_user_id();

    // CITIES
    if (isset($_POST['available_cities']) && is_array($_POST['available_cities'])) {
        $available_cities = array_map('intval', $_POST['available_cities']); // store IDs
        update_post_meta($post_id, '_available_cities', $available_cities);

        $table = $wpdb->prefix . 'product_cities';
        $wpdb->delete($table, ['product_id' => (int)$post_id]);
        foreach ($available_cities as $city_id) {
            $wpdb->insert($table, [
                'product_id'  => (int)$post_id,
                'city_id'     => (int)$city_id,
                'created_by'  => $user_id,
                'modified_by' => $user_id,
            ]);
        }
    } else {
        delete_post_meta($post_id, '_available_cities');
        $wpdb->delete($wpdb->prefix . 'product_cities', ['product_id' => (int)$post_id]);
    }

    // MALLS
    if (isset($_POST['available_malls']) && is_array($_POST['available_malls'])) {
        $available_malls = array_map('intval', $_POST['available_malls']); // store IDs
        update_post_meta($post_id, '_available_malls', $available_malls);

        $table = $wpdb->prefix . 'product_malls';
        $wpdb->delete($table, ['product_id' => (int)$post_id]);
        foreach ($available_malls as $mall_id) {
            $wpdb->insert($table, [
                'product_id'  => (int)$post_id,
                'mall_id'     => (int)$mall_id,
                'created_by'  => $user_id,
                'modified_by' => $user_id,
            ]);
        }
    } else {
        delete_post_meta($post_id, '_available_malls');
        $wpdb->delete($wpdb->prefix . 'product_malls', ['product_id' => (int)$post_id]);
    }
});

/**
 * Resolve city ID by cookie value and filter catalog by wp_product_cities.
 * Rule: if a product has no rows in wp_product_cities → it's global (show it).
 *       if it has rows → show only if a row matches the selected city_id.
 */
function vogo_city_id_from_cookie(): int {
    global $wpdb;
    $raw  = isset($_COOKIE['selected_city']) ? urldecode($_COOKIE['selected_city']) : '';
    $city = trim($raw);
    if ($city === '') return 0;
    $table = $wpdb->prefix . 'cities';
    $id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE LOWER(city_name)=LOWER(%s) LIMIT 1", $city));
    return $id;
}

add_filter('posts_where', function($where, $query) {
    if (!is_admin() && $query->is_main_query() && is_product_category()) {
        global $wpdb;
        $city_id = vogo_city_id_from_cookie();
        if ($city_id > 0) {
            $pc = $wpdb->prefix . 'product_cities';
            // NOT EXISTS any row → global; OR EXISTS row with requested city_id
            $where .= $wpdb->prepare(
                " AND ( NOT EXISTS (SELECT 1 FROM {$pc} pc0 WHERE pc0.product_id = {$wpdb->posts}.ID)
                        OR EXISTS (SELECT 1 FROM {$pc} pc1 WHERE pc1.product_id = {$wpdb->posts}.ID AND pc1.city_id = %d) )",
                $city_id
            );
        }
    }
    return $where;
}, 10, 2);


//////////////////////////////

/**
 * Defensive helpers to normalize rows coming from get_cities_list()/get_mall_list()
 */
if (!function_exists('vogo_first_scalar')) {
    // Return the first scalar from a value (handles array/object/scalar); fallback to empty string.
    function vogo_first_scalar($value) {
        if (is_scalar($value) || $value === null) { return $value ?? ''; }
        if (is_array($value)) {
            foreach ($value as $v) { if (is_scalar($v)) return $v; } // pick first scalar
            return '';
        }
        if (is_object($value)) {
            foreach (get_object_vars($value) as $v) { if (is_scalar($v)) return $v; }
            return '';
        }
        return '';
    }
}

if (!function_exists('vogo_row_scalar')) {
    // Try keys in order and return the first scalar value found on $row (array/object).
    function vogo_row_scalar($row, array $keys, $default = '') {
        foreach ($keys as $k) {
            if (is_array($row) && array_key_exists($k, $row)) {
                $val = vogo_first_scalar($row[$k]);
                return ($val === '' ? $default : $val);
            }
            if (is_object($row) && isset($row->{$k})) {
                $val = vogo_first_scalar($row->{$k});
                return ($val === '' ? $default : $val);
            }
        }
        return $default;
    }
}

/**
 * Product → Cities (multi) — FIXED to avoid "Array to string conversion"
 */
function render_product_city_meta($post) {
    global $wpdb;

    // selected IDs already saved
    $table = $wpdb->prefix . 'product_cities';
    $saved = array_map('intval', (array)$wpdb->get_col($wpdb->prepare("SELECT city_id FROM {$table} WHERE product_id = %d", $post->ID)));

    // all cities (can be array of arrays/objects; names may be multilingual arrays)
    $cities = (array)get_cities_list();

    echo '<div style="margin-bottom:5px;">
        <button type="button" onclick="selectAllCities()" style="margin-right:5px;">Select All</button>
        <button type="button" onclick="deselectAllCities()">Deselect All</button>
    </div>';

    echo '<select multiple name="available_cities[]" style="width:100%;height:180px;">';

    foreach ($cities as $row) {
        // Robust extraction
        $id_raw   = vogo_row_scalar($row, ['id','city_id','ID'], 0);
        $name_raw = vogo_row_scalar($row, ['name','city_name','label','title'], '');

        $id   = intval($id_raw);
        $name = trim((string)$name_raw);

        // Skip invalid rows safely
        if ($id <= 0 || $name === '') { continue; }

        $sel = in_array($id, $saved, true) ? 'selected' : '';
        echo '<option value="' . esc_attr((string)$id) . '" ' . $sel . '>' . esc_html($name) . '</option>';
    }
    echo '</select>';

    // Small helpers
    echo '<script>
    function selectAllCities(){const s=document.querySelector("select[name=\'available_cities[]\']");if(s){for(const o of s.options){o.selected=true;}}}
    function deselectAllCities(){const s=document.querySelector("select[name=\'available_cities[]\']");if(s){for(const o of s.options){o.selected=false;}}}
    </script>';
}

/**
 * Product → Malls (multi) — FIXED to avoid "Array to string conversion"
 */
function render_product_mall_meta($post) {
    global $wpdb;

    // selected mall ids
    $table = $wpdb->prefix . 'product_malls';
    $saved = array_map('intval', (array)$wpdb->get_col($wpdb->prepare("SELECT mall_id FROM {$table} WHERE product_id = %d", $post->ID)));

    // all active malls
    $locations = (array)get_mall_list();

    echo '<div style="margin-bottom:5px;">
        <button type="button" onclick="selectAllMalls()" style="margin-right:5px;">Select All</button>
        <button type="button" onclick="deselectAllMalls()">Deselect All</button>
    </div>';

    echo '<select multiple name="available_malls[]" id="available_malls_select" style="width:100%;height:180px;">';

    foreach ($locations as $row) {
        // Robust extraction
        $id_raw   = vogo_row_scalar($row, ['id','mall_id','ID'], 0);
        $name_raw = vogo_row_scalar($row, ['name','mall_name','label','title'], '');

        $id   = intval($id_raw);
        $name = trim((string)$name_raw);

        if ($id <= 0 || $name === '') { continue; }

        $sel = in_array($id, $saved, true) ? 'selected' : '';
        echo '<option value="' . esc_attr((string)$id) . '" ' . $sel . '>' . esc_html($name) . '</option>';
    }
    echo '</select>';

    echo '<script>
    function selectAllMalls(){const s=document.querySelector("select[name=\'available_malls[]\']");if(s){for(const o of s.options){o.selected=true;}}}
    function deselectAllMalls(){const s=document.querySelector("select[name=\'available_malls[]\']");if(s){for(const o of s.options){o.selected=false;}}}
    </script>';
}
