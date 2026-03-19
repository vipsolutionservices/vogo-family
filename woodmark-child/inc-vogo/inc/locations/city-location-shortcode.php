<?php

function city_selection_shortcode() {
    ob_start();
    global $wpdb;

    // Fetch cities from the database (mall_locations table)
    $db_cities = $wpdb->get_results("SELECT DISTINCT city FROM {$wpdb->prefix}mall_locations");
    $db_cities_list = [];
    foreach ($db_cities as $city_obj) {
        $db_cities_list[] = sanitize_text_field($city_obj->city);
    }

    // Fetch cities from cities.json
    $json_cities_list = [];
    $cities_json_path = get_stylesheet_directory() . '/inc/data/cities.json'; // Path in child theme
    if (file_exists($cities_json_path)) {
        $json_data = file_get_contents($cities_json_path);
        $decoded_data = json_decode($json_data, true);
        if (isset($decoded_data['cities']) && is_array($decoded_data['cities'])) {
            $json_cities_list = array_map('sanitize_text_field', $decoded_data['cities']);
        }
    }

    // Merge and remove duplicates
    $all_cities = array_unique(array_merge($db_cities_list, $json_cities_list));
    sort($all_cities); // Sort alphabetically

    // Retrieve the selected city from cookie
    $selected_city = isset($_COOKIE['selected_city']) ? sanitize_text_field($_COOKIE['selected_city']) : '';
    ?>
   
    <div class="city-selection-container">
        <select class="global_city_select" name="city">
           <!--Tag Select City-->
            <option value="" class="notranslate">City</option>
            <?php foreach ($all_cities as $city) : ?>
                <option value="<?php echo esc_attr($city); ?>" <?php echo ($selected_city === $city) ? 'selected' : ''; ?> class="notranslate">
                    <?php echo esc_html($city); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <script>
    jQuery(document).ready(function($) {
       // console.log("jQuery Loaded and Ready!"); // Debugging
       // console.log("Cookie Value in JS:", document.cookie); // Debugging cookie value

        $(document).on("change", ".global_city_select", function() {
            var city = $(this).val();
         //   console.log("Selected City:", city); // Debugging

            if (!city) {
           //     console.log("City is empty. Aborting AJAX.");
                return;
            }

            $.ajax({
                url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                type: "POST",
                data: {
                    action: "set_selected_city",
                    city: city
                },
                success: function(response) {
             //       console.log("AJAX Response:", response); // Debugging
                    if (response.success) {
                        document.cookie = "selected_city=" + city + "; path=/"; // Manually set cookie
                        console.log("Cookie Set in JS:", document.cookie);
                        // setTimeout(function() {
                        //     location.reload(); // Reload after successful city change
                        // }, 300);
                        if ($("body").hasClass("single-product") || $("body").hasClass("archive") || $("body").hasClass("post-type-archive-product")) {
                        setTimeout(function() {
                            location.reload(); // Reload only on product pages or archives
                        }, 300);
                    }
                    } else {
               //         console.log("Error setting city:", response.data);
                    }
                },
                error: function(xhr, status, error) {
                 //   console.log("AJAX request failed. Error:", error);
                }
            });
        });
    });
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('city_selection', 'city_selection_shortcode');



function set_selected_city() {
    if (!isset($_POST['city']) || empty($_POST['city'])) {
        wp_send_json_error(['message' => 'Orașul nu a fost furnizat.']);
    }

    $city = sanitize_text_field($_POST['city']);

    // Debugging
 //   error_log("City received in AJAX: " . $city);

    // Set cookie with improved security settings
    setcookie('selected_city', $city, time() + (7 * 24 * 60 * 60), "/", $_SERVER['HTTP_HOST'], false, false);
    
    // Set city in PHP session (if needed)
    $_SESSION['selected_city'] = $city;

    // Send success response
    wp_send_json_success(['city' => $city, 'cookie' => $_COOKIE['selected_city'] ?? 'Not Set']);
}
add_action('wp_ajax_set_selected_city', 'set_selected_city');
add_action('wp_ajax_nopriv_set_selected_city', 'set_selected_city');