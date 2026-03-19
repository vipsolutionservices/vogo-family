<?php
// ───────────────────────────────────────────────────────────
// TAG#UTILS-BOOT (adi-tehnic)
// ───────────────────────────────────────────────────────────

/**
 * Invalidate cache for cities list.
 */
function vogo_cities_cache_invalidate() {
    wp_cache_delete('vogo_cities_list', 'vogo');
    if (function_exists('vogo_error_log3')) {
        vogo_error_log3('[CITIES] cache invalidated');
    }
}

/**
 * Enforces table schema. Throws if required columns are missing.
 */
function db_require_columns(string $table, array $required): void {
    global $wpdb;

    $cols = $wpdb->get_col($wpdb->prepare(
        "SELECT COLUMN_NAME
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s",
        $table
    ));

    if (!$cols) {
        throw new RuntimeException("Table not found: {$table}");
    }

    $missing = array_values(array_diff($required, $cols));
    if ($missing) {
        throw new RuntimeException(
            "Invalid schema for {$table}. Missing: " . implode(', ', $missing)
        );
    }
}

/**
 * Returns cities as: [ ['id'=>int, 'name'=>string], ... ]
 * Source: wp_cities(id, city_name, status, position).
 */
/*
function get_cities_list(): array {
    global $wpdb;

    $table = $wpdb->prefix . 'cities';
    db_require_columns($table, ['id', 'city_name', 'status', 'position']);

    // Only active cities; sort by position then by name for stable UX.
    $rows = $wpdb->get_results(
        "SELECT id, city_name
         FROM {$table}
         WHERE status = 1
         ORDER BY position ASC, city_name ASC",
        ARRAY_A
    );

    if (!is_array($rows)) {
        throw new RuntimeException("Failed to fetch cities from {$table}");
    }

    $out = [];
    foreach ($rows as $r) {
        $id = (int)$r['id'];
        $name = (string)$r['city_name'];
        if ($id > 0 && $name !== '') {
            // Normalize to ['id','name'] to keep all call sites unchanged.
            $out[] = ['id' => $id, 'name' => $name];
        }
    }
    return $out;
}
*/

/**
 * Normalize mixed input (CSV / serialized / array) into a unique int[] > 0.
 */
function vogo_normalize_int_list($raw) {
    if (is_string($raw)) {
        $maybe = maybe_unserialize($raw);
        if (is_array($maybe)) {
            $raw = $maybe;
        }
    }
    if (!is_array($raw)) {
        $raw = preg_split('/[,\s]+/', (string)$raw, -1, PREG_SPLIT_NO_EMPTY);
    }
    $raw = array_map('intval', (array)$raw);
    $raw = array_values(array_unique(array_filter($raw, fn($v) => $v > 0)));
    return $raw;
}
/*
function get_mall_list() {
    global $wpdb;
    $table = $wpdb->prefix . 'mall_locations';
    $malls = [];

    vogo_error_log3("VOGO_LOG_START | Fetching mall list | table: $table", 'mall-load');

    $results = $wpdb->get_results("SELECT id, mall_name FROM $table WHERE status = 'active' ORDER BY position ASC", ARRAY_A);

    if ($wpdb->last_error) {
        vogo_error_log3("[STEP 1] DB ERROR: {$wpdb->last_error}", 'mall-load');
    } else {
        foreach ($results as $row) {
            $malls[] = [
                'id'   => (int)$row['id'],
                'name' => sanitize_text_field($row['mall_name']),
            ];
        }
        vogo_error_log3("[STEP 2] Loaded " . count($malls) . " malls", 'mall-load');
    }

    vogo_error_log3("VOGO_LOG_END", 'mall-load');
    return $malls;
}
*/

/** get_vendor_id_for_product
 * Resolve vendor_id from mapping table (unchanged).
 */
function get_vendor_id_for_product($product_id){
  global $wpdb; $table=$wpdb->prefix.'vogo_product_vendor';
  $vid = $wpdb->get_var($wpdb->prepare("SELECT vendor_id FROM $table WHERE product_id=%d LIMIT 1",$product_id));
  return $vid ? intval($vid) : 0;
}

/** get_vendor_id_for_user
 * Map current WP user -> vendor_id via wp_vogo_providers.user_id.
 */
function get_vendor_id_for_user($user_id){
  global $wpdb; $table=$wpdb->prefix.'vogo_providers';
  $vid = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE user_id=%d LIMIT 1",$user_id));
  return $vid ? intval($vid) : 0;
}

/** get_vendor_name
 * Small helper to display provider name from wp_vogo_providers.
 */
function get_vendor_name($vendor_id){
  global $wpdb; $table=$wpdb->prefix.'vogo_providers';
  return $wpdb->get_var($wpdb->prepare("SELECT provider_name FROM $table WHERE id=%d",$vendor_id));
}
