<?php
// Add custom admin page for adding mall locations
function add_mall_location_admin_page() {
    add_menu_page(
        'Mall Locations',           // Page Title
        'Mall Locations',           // Menu Title
        'manage_options',           // Capability
        'mall_locations',           // Menu Slug
        'render_mall_locations_page', // Callback to render the page
        'dashicons-location-alt' // Icon
    );
}
add_action('admin_menu', 'add_mall_location_admin_page');
// function get_cities_list() {
//     $file_path = get_stylesheet_directory() . '/inc/data/cities.json'; // Path in child theme

//     if (file_exists($file_path)) {
//         $json = file_get_contents($file_path);
//         $data = json_decode($json, true);
//         return $data['cities'] ?? [];
//     }

//     return []; // Return empty array if file not found
// }


// Render the Mall Locations Page
function render_mall_locations_page() {
    global $wpdb;

    // Load cities dynamically from JSON file
    $cities = get_cities_list();

    // Handle form submission (Add or Edit)
    if (isset($_POST['submit_mall_location'])) {
        $mall_name = sanitize_text_field($_POST['mall_name']);
        $street_address = sanitize_text_field($_POST['street_address']);
        $city = sanitize_text_field($_POST['city']);
        $location_code = sanitize_text_field($_POST['location_code']);
        $status = sanitize_text_field($_POST['status']);

        if (isset($_POST['location_id']) && !empty($_POST['location_id'])) {
            // Edit existing location
            $location_id = intval($_POST['location_id']);
            $wpdb->update(
                $wpdb->prefix . 'mall_locations',
                [
                    'mall_name' => $mall_name,
                    'street_address' => $street_address,
                    'city' => $city,
                    'location_code' => $location_code,
                    'status' => $status
                ],
                ['id' => $location_id],
                ['%s', '%s', '%s', '%s', '%s'],
                ['%d']
            );
        } else {
            // Add new location
            $wpdb->insert(
                $wpdb->prefix . 'mall_locations',
                [
                    'mall_name' => $mall_name,
                    'street_address' => $street_address,
                    'city' => $city,
                    'location_code' => $location_code,
                    'status' => $status
                ],
                ['%s', '%s', '%s', '%s', '%s']
            );
        }
        // Redirect to refresh the page
        wp_redirect(admin_url('admin.php?page=mall_locations'));
        exit;
    }

    // Handle filtering
    $city_filter = isset($_GET['city_filter']) ? sanitize_text_field($_GET['city_filter']) : '';
    $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : '';

    // Pagination
    $per_page = 10;
    $current_page = isset($_GET['paged']) ? (int)$_GET['paged'] : 1;
    $offset = ($current_page - 1) * $per_page;

    // Query mall locations
    $query = "SELECT * FROM {$wpdb->prefix}mall_locations WHERE 1=1";
    $query_args = [];

    if (!empty($city_filter)) {
        $query .= " AND city LIKE %s";
        $query_args[] = "%" . $city_filter . "%";
    }

    if (!empty($status_filter)) {
        $query .= " AND status = %s";
        $query_args[] = $status_filter;
    }

    $query .= " LIMIT %d, %d";
    $query_args[] = $offset;
    $query_args[] = $per_page;

    $locations = $wpdb->get_results($wpdb->prepare($query, ...$query_args));

    // Get total count for pagination
    $total_locations = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}mall_locations WHERE 1=1");
    $total_pages = ceil($total_locations / $per_page);

    // Handle delete action
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['location_id'])) {
        $location_id = intval($_GET['location_id']);
        $wpdb->delete($wpdb->prefix . 'mall_locations', ['id' => $location_id], ['%d']);
        wp_redirect(admin_url('admin.php?page=mall_locations'));
        exit;
    }

    ?>
    <div class="wrap">
        <h1><?php echo isset($_GET['edit']) ? 'Edit Mall Location' : 'Add Mall Location'; ?></h1>
        <form method="post">
            <table class="form-table">
                <?php if (isset($_GET['edit'])) : ?>
                    <input type="hidden" name="location_id" value="<?php echo esc_attr($_GET['edit']); ?>" />
                <?php endif; ?>
                <tr>
                    <th><label for="mall_name">Mall Name</label></th>
                    <td><input type="text" name="mall_name" id="mall_name" required></td>
                </tr>
                <tr>
                    <th><label for="street_address">Street Address</label></th>
                    <td><input type="text" name="street_address" id="street_address" required></td>
                </tr>
                <tr>
                    <th><label for="city">City</label></th>
                    <td>
                        <select name="city" id="city" required>
                            <option value="">Select City</option>
                            <?php foreach ($cities as $city) : ?>
                                <option value="<?php echo esc_attr($city); ?>"><?php echo esc_html($city); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="location_code">Location Code</label></th>
                    <td><input type="text" name="location_code" id="location_code" required></td>
                </tr>
                <tr>
                    <th><label for="status">Status</label></th>
                    <td>
                        <select name="status" id="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </td>
                </tr>
            </table>
            <input type="submit" name="submit_mall_location" class="button-primary" value="Save Location">
        </form>

        <h2>Manage Mall Locations</h2>
        <form method="get">
            <input type="hidden" name="page" value="mall_locations">
            <label for="city_filter">City:</label>
            <select name="city_filter">
                <option value="">All Cities</option>
                <?php foreach ($cities as $city) : ?>
                    <option value="<?php echo esc_attr($city); ?>" <?php selected($city_filter, $city); ?>><?php echo esc_html($city); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="submit" value="Filter" class="button-secondary">
        </form>

        <table class="widefat">
            <thead>
                <tr><th>Mall Name</th><th>City</th><th>Address</th><th>Code</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($locations as $location) : ?>
                    <tr>
                        <td><?php echo esc_html($location->mall_name); ?></td>
                        <td><?php echo esc_html($location->city); ?></td>
                        <td><?php echo esc_html($location->street_address); ?></td>
                        <td><?php echo esc_html($location->location_code); ?></td>
                        <td><?php echo esc_html(ucfirst($location->status)); ?></td>
                        <td><a href="?page=mall_locations&edit=<?php echo esc_attr($location->id); ?>">Edit</a> | <a href="?page=mall_locations&action=delete&location_id=<?php echo esc_attr($location->id); ?>" onclick="return confirm('Delete?');">Delete</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Add Mall Location dropdown to the product category page with multi-select

function save_location_fields_for_product_category($term_id) {
    // Save Mall Locations
    if (!empty($_POST['mall_location']) && is_array($_POST['mall_location'])) {
        $mall_locations = array_map('sanitize_text_field', $_POST['mall_location']);
        update_term_meta($term_id, 'mall_location', maybe_serialize($mall_locations)); // Use serialization for safe storage
    } else {
        delete_term_meta($term_id, 'mall_location'); // Remove if empty
    }

    // Save Cities
    if (!empty($_POST['product_category_cities']) && is_array($_POST['product_category_cities'])) {
        $selected_cities = array_map('sanitize_text_field', $_POST['product_category_cities']);
        update_term_meta($term_id, 'product_category_cities', maybe_serialize($selected_cities)); // Use serialization
    } else {
        delete_term_meta($term_id, 'product_category_cities'); // Remove if empty
    }
}
add_action('edited_product_cat', 'save_location_fields_for_product_category');
add_action('create_product_cat', 'save_location_fields_for_product_category');


// Load Cities from JSON
function get_cities_list() {
    $file_path = get_stylesheet_directory() . '/inc/data/cities.json'; // Path in child theme

    if (file_exists($file_path)) {
        $json = file_get_contents($file_path);
        $data = json_decode($json, true);
        return isset($data['cities']) ? $data['cities'] : [];
    }

    return []; 
}

// Add Fields to Product Categories (With Proper Preselection)
function add_location_fields_to_product_category($term) {
    global $wpdb;

    // Fetch DISTINCT mall locations from database
    $mall_locations = $wpdb->get_results("SELECT DISTINCT city FROM {$wpdb->prefix}mall_locations");

    // Get previously selected mall locations
   // $current_mall_locations = get_term_meta($term->term_id, 'mall_location', true);
    $current_mall_locations = (is_object($term) && property_exists($term, 'term_id')) 
    ? get_term_meta($term->term_id, 'mall_location', true) 
    : null;

    $current_mall_locations = maybe_unserialize($current_mall_locations); // Deserialize
    if (!is_array($current_mall_locations)) {
        $current_mall_locations = [];
    }

    // Load cities from child theme's cities.json
    $cities = get_cities_list();

    // Get previously selected cities
   // $current_cities = get_term_meta($term->term_id, 'product_category_cities', true);
   $current_cities = (is_object($term) && property_exists($term, 'term_id')) 
    ? get_term_meta($term->term_id, 'product_category_cities', true) 
    : null;

    $current_cities = maybe_unserialize($current_cities); // Deserialize
    if (!is_array($current_cities)) {
        $current_cities = [];
    }
    ?>

    <!-- Mall Locations Multi-Select -->
    <tr class="form-field">
        <th scope="row" valign="top"><label for="mall_location">Mall Locations</label></th>
        <td>
            <button type="button" id="select_all_malls" class="button">Select All</button>
            <button type="button" id="clear_all_malls" class="button">Clear All</button>

            <select name="mall_location[]" id="mall_location" multiple="multiple" style="width: 300px; height:200px;">
                <option value="">Select Mall Locations</option>
                <?php foreach ($mall_locations as $location) : ?>
                    <option value="<?php echo esc_attr($location->city); ?>" 
                        <?php echo in_array($location->city, $current_mall_locations) ? 'selected' : ''; ?>>
                        <?php echo esc_html($location->city); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <p class="description">Hold down Ctrl (or Cmd) to select multiple locations, or use the Select All button.</p>
        </td>
    </tr>

    <!-- Cities Multi-Select -->
    <tr class="form-field">
        <th scope="row" valign="top"><label for="product_category_cities">Cities</label></th>
        <td>
            <button type="button" id="select_all_cities" class="button">Select All</button>
            <button type="button" id="clear_all_cities" class="button">Clear All</button>

            <select name="product_category_cities[]" id="product_category_cities" multiple="multiple" style="width: 300px; height:200px;">
                <option value="">Select Cities</option>
                <?php foreach ($cities as $city) : ?>
                    <option value="<?php echo esc_attr($city); ?>" 
                        <?php echo in_array($city, $current_cities) ? 'selected' : ''; ?>>
                        <?php echo esc_html($city); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <p class="description">Hold down Ctrl (or Cmd) to select multiple cities, or use the Select All button.</p>
        </td>
    </tr>

    <script>
        jQuery(document).ready(function($) {
            // Select All Button Functionality for Mall Locations
            $('#select_all_malls').click(function() {
                $('#mall_location option').prop('selected', true);
            });

            $('#clear_all_malls').click(function() {
                $('#mall_location option').prop('selected', false);
            });

            // Select All Button Functionality for Cities
            $('#select_all_cities').click(function() {
                $('#product_category_cities option').prop('selected', true);
            });

            $('#clear_all_cities').click(function() {
                $('#product_category_cities option').prop('selected', false);
            });
        });
    </script>

    <?php
}
add_action('product_cat_edit_form_fields', 'add_location_fields_to_product_category');
add_action('product_cat_add_form_fields', 'add_location_fields_to_product_category');


// Display Mall Locations & Cities in Category Pages
function display_mall_locations_for_category() {
    if (is_product_category()) {
        $term_id = get_queried_object_id();
        $mall_locations = explode(',', get_term_meta($term_id, 'mall_location', true));
        $cities = explode(',', get_term_meta($term_id, 'product_category_cities', true));

        echo '<div class="category-location-info">';
        
        if (!empty($mall_locations)) {
            echo '<p><strong>Mall Locations:</strong> ' . implode(', ', array_filter($mall_locations)) . '</p>';
        }

        if (!empty($cities)) {
            echo '<p><strong>Available in Cities:</strong> ' . implode(', ', array_filter($cities)) . '</p>';
        }

        echo '</div>';
    }
}
add_action('woocommerce_archive_description', 'display_mall_locations_for_category', 20);

function show_user_addresses_in_admin($user) {
    if (!current_user_can('edit_users')) {
        return;
    }

    global $wpdb;
    $user_id = $user->ID;

    // Fetch additional addresses from the custom table
    $addresses = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}user_addresses WHERE user_id = %d AND status = 'active'",
        $user_id
    ));

    ?>
    <h2>Additional Addresses</h2>
    <table class="form-table">
        <tr>
            <th>Address Name</th>
            <th>Street Address</th>
            <th>City</th>
        </tr>
        <?php if (!empty($addresses)) : ?>
            <?php foreach ($addresses as $address) : ?>
                <tr>
                    <td><?php echo esc_html($address->address_name ?? 'N/A'); ?></td>
                    <td><?php echo esc_html($address->street_address ?? 'N/A'); ?></td>
                    <td><?php echo esc_html($address->city ?? ''); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else : ?>
            <tr><td colspan="4">No additional addresses saved.</td></tr>
        <?php endif; ?>
    </table>
    <?php
}
add_action('show_user_profile', 'show_user_addresses_in_admin');
add_action('edit_user_profile', 'show_user_addresses_in_admin');
