<?php 

/**
 * 1) Register new menu items in My Account menu
 */
function wc_custom_my_account_menu_items( $items ) {
    // Insert new menu items as desired (the array key is the slug, the value is the label)
    $items['my-cities']             = __( 'Orașele mele', 'text-domain' );
    $items['my-area-of-interest']   = __( 'Domeniile mele de interes', 'text-domain' );
    $items['my-user-group']         = __( 'Grupul meu de utilizatori', 'text-domain' );
    
    return $items;
}
add_filter( 'woocommerce_account_menu_items', 'wc_custom_my_account_menu_items' );

/**
 * 2) Register custom endpoints
 */
function wc_custom_my_account_endpoints() {
    add_rewrite_endpoint( 'my-cities', EP_ROOT | EP_PAGES );
    add_rewrite_endpoint( 'my-area-of-interest', EP_ROOT | EP_PAGES );
    add_rewrite_endpoint( 'my-user-group', EP_ROOT | EP_PAGES );
}
add_action( 'init', 'wc_custom_my_account_endpoints' );

/**
 * 3) Flush rewrite rules the first time (only do this once in development)
 *    Alternatively, you can go to Settings > Permalinks and just save to force a flush.
 */
// flush_rewrite_rules(); // Uncomment only once if needed, or do it manually in WP Admin

/**
 * 4) Display content for each custom endpoint
 */
function wc_my_cities_endpoint_content() {
    // Security first
    if ( ! is_user_logged_in() ) {
        echo '<p>Trebuie să fii autentificat pentru a-ți gestiona orașele.</p>';
        return;
    }

    // Retrieve current user data
    $current_user = wp_get_current_user();
    $user_id      = $current_user->ID;
    $first_name   = $current_user->user_firstname;
    $last_name    = $current_user->user_lastname;

    // Path to your JSON file:
    $cities_json_path = get_stylesheet_directory() . '/inc/data/cities.json';

    // Attempt to retrieve & decode the JSON
    $cities_array = array();
    if ( file_exists( $cities_json_path ) ) {
        $cities_content = file_get_contents( $cities_json_path );
        $decoded        = json_decode( $cities_content, true );
        // Validate structure: { "cities": [...] }
        if ( isset( $decoded['cities'] ) && is_array( $decoded['cities'] ) ) {
            $cities_array = $decoded['cities'];
        }
    }

    // If the form was submitted, process the data.
    if ( isset( $_POST['wc_my_cities_nonce'] )
         && wp_verify_nonce( $_POST['wc_my_cities_nonce'], 'wc_my_cities_action' ) ) {

        // Get the current saved cities from user meta (array or empty array)
        $saved_cities = get_user_meta( $user_id, 'user_saved_cities', true );
        if ( ! is_array( $saved_cities ) ) {
            $saved_cities = array();
        }

        // Handle "add" action
        if ( isset( $_POST['wc_action'] ) && $_POST['wc_action'] === 'add_city' ) {
            if ( ! empty( $_POST['selected_city'] ) ) {
                $city_to_add = sanitize_text_field( $_POST['selected_city'] );
                // Only add if it’s in the official list and not already saved
                if ( in_array( $city_to_add, $cities_array, true )
                     && ! in_array( $city_to_add, $saved_cities, true ) ) {
                    $saved_cities[] = $city_to_add;
                }
            }
        }

        // Handle "remove" action
        if ( isset( $_POST['wc_action'] ) && $_POST['wc_action'] === 'remove_city' ) {
            if ( ! empty( $_POST['city_to_remove'] ) ) {
                $city_to_remove = sanitize_text_field( $_POST['city_to_remove'] );
                // Remove it if it exists in the array
                $saved_cities = array_filter( $saved_cities, function( $c ) use ( $city_to_remove ) {
                    return $c !== $city_to_remove;
                } );
            }
        }

        // Update user meta with the new array
        update_user_meta( $user_id, 'user_saved_cities', $saved_cities );
    }

    // Retrieve the latest version of saved cities to show
    $user_saved_cities = get_user_meta( $user_id, 'user_saved_cities', true );
    if ( ! is_array( $user_saved_cities ) ) {
        $user_saved_cities = array();
    }

    // --- OUTPUT STARTS HERE ---
    echo '<h3>Orașele mele</h3>';

    // Display the user’s name
    // #NO_TRANSLATE_CITY
    echo '<p><strong>Nume utilizator:</strong> ' . esc_html( $first_name ) . ' ' . esc_html( $last_name ) . '</p>';

    // FORM: Add a city
    if ( empty( $cities_array ) ) {
        echo '<p><em>Nu s-au găsit date despre orașe. Verifică fișierul JSON.</em></p>';
    } else {
        ?>
       <form method="post">
            <?php wp_nonce_field( 'wc_my_cities_action', 'wc_my_cities_nonce' ); ?>
            <input type="hidden" name="wc_action" value="add_city" />
            <label for="selected_city">Selectează un oraș de adăugat:</label>	
            <select id="selected_city" name="selected_city" class="my-cities-select"><option value="">-- Alege orașul --</option>
                <?php foreach ( $cities_array as $city ) : ?>
                    <option value="<?php echo esc_attr( $city ); ?>" class="notranslate">
                        <?php echo esc_html( $city ); ?>
                    </option>
                <?php endforeach; ?>
            </select><button type="submit" class="my-cities-button"><span class="material-icons notranslate">add</span></button>
        </form>
        <?php
    }

    // List of user's saved cities
    if ( ! empty( $user_saved_cities ) ) {
        echo '<h4>Orașele tale salvate</h4>';
        echo '<table class="my-cities-table" style="border-collapse: collapse; width: 100%; margin-top: 1em;">';
        echo '  <thead>';
        echo '    <tr>';
        echo '      <th style="border: 1px solid #ddd; padding: 8px;">Oraș</th>';
        echo '      <th style="border: 1px solid #ddd; padding: 8px;">Acțiune</th>';
        echo '    </tr>';
        echo '  </thead>';
        echo '  <tbody>';
        foreach ( $user_saved_cities as $saved_city ) {
            echo '<tr>';
            echo '  <td style="border: 1px solid #ddd; padding: 8px;" class="notranslate">' . esc_html( $saved_city ) . '</td>';
            echo '  <td style="border: 1px solid #ddd; padding: 8px;">';
            ?>
            <form method="post" style="display: inline;">
				<div class="city-td"><?php wp_nonce_field( 'wc_my_cities_action', 'wc_my_cities_nonce' ); ?>
                <input type="hidden" name="wc_action" value="remove_city" /><input type="hidden" name="city_to_remove" value="<?php echo esc_attr( $saved_city ); ?>" /><button type="submit" class="my-cities-button" onclick="return confirm('Remove this city?');"><span class="material-icons notranslate">delete</span></button></div>
            </form>
            <?php
            echo '  </td>';
            echo '</tr>';
        }
        echo '  </tbody>';
        echo '</table>';
    } else {
        echo '<p>Nu ai salvat încă niciun oraș.</p>';
    }
}
add_action( 'woocommerce_account_my-cities_endpoint', 'wc_my_cities_endpoint_content' );

function wc_my_area_of_interest_endpoint_content() {
    // Make sure user is logged in
    if ( ! is_user_logged_in() ) {
        echo '<p>Trebuie să fii autentificat pentru a gestiona domeniile tale de interes.</p>';
        return;
    }

    // Get the current user and user meta
    $current_user = wp_get_current_user();
    $user_id      = $current_user->ID;
    $first_name   = $current_user->user_firstname;
    $last_name    = $current_user->user_lastname;

    // Define all possible interests in an array
    $all_interests = array(
        'Restaurant',
        'Hotel',
        'Agentie de turism',
        'Transport',
        'Livrarea alimentelor',
        'Asistență medicală',
        'Casa si gradina',
        'Coaching',
        'Activitati copii',
        'Psiholog',
        'Altele'
    );

    // Handle form submissions (add/remove)
    if ( isset( $_POST['wc_my_interest_nonce'] )
         && wp_verify_nonce( $_POST['wc_my_interest_nonce'], 'wc_my_interest_action' ) ) {

        // Get user’s saved interests (an array) or empty
        $saved_interests = get_user_meta( $user_id, 'user_areas_of_interest', true );
        if ( ! is_array( $saved_interests ) ) {
            $saved_interests = array();
        }

        // Determine which action: add_interest or remove_interest
        $action = isset( $_POST['wc_action'] ) ? sanitize_text_field( $_POST['wc_action'] ) : '';

        // ADD interest
        if ( $action === 'add_interest' && ! empty( $_POST['selected_interest'] ) ) {
            $interest_to_add = sanitize_text_field( $_POST['selected_interest'] );
            // Make sure the interest is valid and not already saved
            if ( in_array( $interest_to_add, $all_interests, true ) && ! in_array( $interest_to_add, $saved_interests, true ) ) {
                $saved_interests[] = $interest_to_add;
            }
        }

        // REMOVE interest
        if ( $action === 'remove_interest' && ! empty( $_POST['interest_to_remove'] ) ) {
            $interest_to_remove = sanitize_text_field( $_POST['interest_to_remove'] );
            // Filter out the removed interest
            $saved_interests = array_filter( $saved_interests, function( $item ) use ( $interest_to_remove ) {
                return $item !== $interest_to_remove;
            });
        }

        // Update user meta
        update_user_meta( $user_id, 'user_areas_of_interest', $saved_interests );
    }

    // Now retrieve the updated list of saved interests
    $user_saved_interests = get_user_meta( $user_id, 'user_areas_of_interest', true );
    if ( ! is_array( $user_saved_interests ) ) {
        $user_saved_interests = array();
    }

    // --- Output HTML for the endpoint ---
    echo '<h3>Domeniul meu de interes</h3>';

    echo '<p><strong>Nume utilizator:</strong> ' . esc_html( $first_name ) . ' ' . esc_html( $last_name ) . '</p>';

    // Display the form to Add an interest
    ?>
    <form method="post">
        <?php wp_nonce_field( 'wc_my_interest_action', 'wc_my_interest_nonce' ); ?>
        <input type="hidden" name="wc_action" value="add_interest" />

        <label for="selected_interest">Selectează un domeniu de interes:</label>
        <select id="selected_interest" name="selected_interest" class="my-interests-select">
            <option value="">Selectați unul</option>
            <?php foreach ( $all_interests as $interest ): ?><option value="<?php echo esc_attr( $interest ); ?>"><?php echo esc_html( $interest ); ?></option><?php endforeach; ?></select><button type="submit" class="my-cities-button"><span class="material-icons notranslate">add</span></button>
    </form>
    <?php

    // Display currently saved interests
    if ( ! empty( $user_saved_interests ) ) {
        echo '<h4>Interesele tale salvate</h4>';
        echo '<table class="my-interests-table" style="border-collapse: collapse; width: 100%; margin-top: 1em;">';
        echo '  <thead>';
        echo '    <tr>';
        echo '      <th style="border: 1px solid #ddd; padding: 8px;">Interes</th>';
        echo '      <th style="border: 1px solid #ddd; padding: 8px;">Acțiune</th>';
        echo '    </tr>';
        echo '  </thead>';
        echo '  <tbody>';
        foreach ( $user_saved_interests as $saved_item ) {
            echo '<tr>';
            echo '  <td style="border: 1px solid #ddd; padding: 8px;">' . esc_html( $saved_item ) . '</td>';
            echo '  <td style="border: 1px solid #ddd; padding: 8px;">';
            ?>
            <form method="post" style="display: inline;">
                <?php wp_nonce_field( 'wc_my_interest_action', 'wc_my_interest_nonce' ); ?>
                <input type="hidden" name="wc_action" value="remove_interest" />
                <input type="hidden" name="interest_to_remove" value="<?php echo esc_attr( $saved_item ); ?>" />
                <button type="submit" class="my-cities-button" onclick="return confirm('Elimină acest interes?');"><span class="material-icons notranslate">delete</span></button>
            </form>
            <?php
            echo '  </td>';
            echo '</tr>';
        }
        echo '  </tbody>';
        echo '</table>';
    } else {
        echo '<p>Nu ai salvat încă niciun domeniu de interes.</p>';
    }
}
add_action( 'woocommerce_account_my-area-of-interest_endpoint', 'wc_my_area_of_interest_endpoint_content' );

// function wc_my_user_group_endpoint_content() {
//     if ( ! is_user_logged_in() ) {
//         echo '<p>You must be logged in to see your user groups.</p>';
//         return;
//     }

//     global $wpdb;
//     $user_id = get_current_user_id();
//     $table_user_group_users = $wpdb->prefix . 'user_group_users';
//     $table_groups = $wpdb->prefix . 'user_groups';

//     // Get all group IDs the user is part of
//     $group_ids = $wpdb->get_col( $wpdb->prepare("
//         SELECT group_id FROM $table_user_group_users WHERE user_id = %d
//     ", $user_id ) );

//     if ( empty( $group_ids ) ) {
//         echo '<p>You are not assigned to any user groups.</p>';
//         return;
//     }

//     // Get group details
//     $placeholders = implode(',', array_fill(0, count($group_ids), '%d'));
//     $groups = $wpdb->get_results( $wpdb->prepare("
//         SELECT name FROM $table_groups WHERE id IN ($placeholders)
//     ", ...$group_ids ) );

//     if ( isset($_POST['leave_group_btn']) && isset($_POST['group_to_leave']) && wp_verify_nonce($_POST['wc_leave_group_nonce'], 'wc_leave_group_action') ) {
//         $group_to_leave = sanitize_text_field($_POST['group_to_leave']);
//         $group_id_to_leave = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table_groups WHERE name = %s", $group_to_leave));

//         if ( $group_id_to_leave ) {
//             // Ensure it's not the Public group
//             $is_public = strtolower($group_to_leave) === 'public';
//             if ( ! $is_public ) {
//                 $wpdb->delete($table_user_group_users, [
//                     'user_id'  => $user_id,
//                     'group_id' => $group_id_to_leave
//                 ]);
//                 echo '<div class="woocommerce-message">You have left the group: ' . esc_html($group_to_leave) . '</div>';
//                 // Refresh the group_ids after removal
//                 $group_ids = $wpdb->get_col( $wpdb->prepare("SELECT group_id FROM $table_user_group_users WHERE user_id = %d", $user_id ) );
//                 if ( empty($group_ids) ) {
//                     echo '<p>You are not assigned to any user groups.</p>';
//                     return;
//                 }
//                 $placeholders = implode(',', array_fill(0, count($group_ids), '%d'));
//                 $groups = $wpdb->get_results( $wpdb->prepare("SELECT name FROM $table_groups WHERE id IN ($placeholders)", ...$group_ids ) );
//             }
//         }
//     }

//     echo '<h3>My User Groups</h3>';
//     echo '<ul>';
//     foreach ( $groups as $group ) {
//         echo '<li>' . esc_html( $group->name );
//         if ( strtolower($group->name) !== 'public' ) {
//             echo ' <form method="post" style="display:inline; margin-left: 10px;">';
//             wp_nonce_field('wc_leave_group_action', 'wc_leave_group_nonce');
//             echo '<input type="hidden" name="group_to_leave" value="' . esc_attr( $group->name ) . '"/>';
//             echo '<button type="submit" name="leave_group_btn">Leave</button>';
//             echo '</form>';
//         }
//         echo '</li>';
//     }
//     echo '</ul>';
// }
function wc_my_user_group_endpoint_content() {
    if ( ! is_user_logged_in() ) {
        echo '<p>Trebuie să fii autentificat pentru a vedea grupurile tale de utilizatori.</p>';
        return;
    }

    global $wpdb;
    $user_id = get_current_user_id();
    $table_user_group_users = $wpdb->prefix . 'user_group_users';
    $table_groups = $wpdb->prefix . 'user_groups';

    // Get all group IDs the user is part of
    $user_group_ids = $wpdb->get_col( $wpdb->prepare("
        SELECT group_id FROM $table_user_group_users WHERE user_id = %d
    ", $user_id ) );

    // Handle leave
    if ( isset($_POST['leave_group_btn'], $_POST['group_id'], $_POST['wc_leave_group_nonce']) && wp_verify_nonce($_POST['wc_leave_group_nonce'], 'wc_leave_group_action') ) {
        $group_id = intval($_POST['group_id']);
        $group_name = $wpdb->get_var($wpdb->prepare("SELECT name FROM $table_groups WHERE id = %d", $group_id));
        if ( strtolower($group_name) !== 'public' ) {
            $wpdb->delete($table_user_group_users, ['user_id' => $user_id, 'group_id' => $group_id]);
            echo '<div class="woocommerce-message">You left the group: ' . esc_html($group_name) . '</div>';
            $user_group_ids = array_diff($user_group_ids, [$group_id]); // Refresh
        }
    }

    // Handle join
    if ( isset($_POST['join_group_btn'], $_POST['group_id'], $_POST['wc_join_group_nonce']) && wp_verify_nonce($_POST['wc_join_group_nonce'], 'wc_join_group_action') ) {
        $group_id = intval($_POST['group_id']);
        if ( !in_array($group_id, $user_group_ids) ) {
            $wpdb->insert($table_user_group_users, ['user_id' => $user_id, 'group_id' => $group_id]);
            echo '<div class="woocommerce-message">Te-ai alăturat grupului cu succes.</div>';
            $user_group_ids[] = $group_id;
        }
    }

    // Fetch all groups
    $all_groups = $wpdb->get_results("SELECT * FROM $table_groups ORDER BY name ASC");

    echo '<h3>Grupurile Mele de Utilizatori</h3>';
    echo '<table class="widefat"><thead><tr><th>Numele Grupului</th><th>Acțiuni</th></tr></thead><tbody>';
    foreach ( $all_groups as $group ) {
        $in_group = in_array($group->id, $user_group_ids);
        echo '<tr>';
        echo '<td>' . esc_html($group->name) . '</td>';
        echo '<td>';
        if ( $in_group ) {
            if ( strtolower($group->name) === 'public' ) {
                echo '<em>Întotdeauna alăturat</em>';
            } else {
                echo '<form method="post" style="display:inline;">';
                wp_nonce_field('wc_leave_group_action', 'wc_leave_group_nonce');
                echo '<input type="hidden" name="group_id" value="' . esc_attr($group->id) . '">';
                echo '<button type="submit" class="button my-cities-button" name="leave_group_btn"><span class="material-icons notranslate">logout</span></button>';
                echo '</form>';
            }
        } else {
            echo '<form method="post" style="display:inline;">';
            wp_nonce_field('wc_join_group_action', 'wc_join_group_nonce');
            echo '<input type="hidden" name="group_id" value="' . esc_attr($group->id) . '">';
            echo '<button class="button button-primary my-cities-button" type="submit" name="join_group_btn"><span class="material-icons notranslate">group_add</span></button>';
            echo '</form>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table>';
}
add_action( 'woocommerce_account_my-user-group_endpoint', 'wc_my_user_group_endpoint_content' );


// Action hook: Send email to users when a new question matches their city and interest
add_action('vogo_new_question_submitted', 'vogo_email_interested_users', 10, 1);
function vogo_email_interested_users($question_id) {
    $city = get_post_meta($question_id, 'city', true);
    $interest = get_post_meta($question_id, 'interest', true);

    if (!$city || !$interest) return;

    $users = get_users(['fields' => ['ID', 'user_email']]);
    foreach ($users as $user) {
        $user_cities = get_user_meta($user->ID, 'user_saved_cities', true);
        $user_interests = get_user_meta($user->ID, 'user_areas_of_interest', true);

        if (!is_array($user_cities)) $user_cities = [];
        if (!is_array($user_interests)) $user_interests = [];

        if (in_array($city, $user_cities, true) && in_array($interest, $user_interests, true)) {
            $subject = 'Întrebare nouă legată de interesele tale';
            $message = sprintf(
                '<html><body>
                    <h2 style="color:#1d72b8;">Întrebare nouă în %s despre %s</h2>
                    <p><strong>Titlu:</strong> %s</p>
                    <p><a href="%s" style="display:inline-block; padding:10px 16px; background-color:#1d72b8; color:#fff; text-decoration:none; border-radius:4px;">Vezi întrebarea</a></p>
                    <br><p style="font-size:12px; color:#666;">Acest email a fost trimis deoarece ai salvat acest oraș și domeniu ca interes.</p>
                    <p style="font-size:12px; color:#888;">Echipa Vogo</p>
                </body></html>',
                esc_html($city),
                esc_html($interest),
                esc_html(get_the_title($question_id)),
                esc_url(home_url('/question-thread/?question_id=' . $question_id))
            );
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            wp_mail($user->user_email, $subject, $message, $headers);
        }
    }
}
// Action hook: Notify question author when a new reply is submitted
add_action('vogo_new_reply_submitted', 'vogo_notify_question_author', 10, 2);
function vogo_notify_question_author($reply_id, $question_id) {
    $question_post = get_post($question_id);
    if (!$question_post || $question_post->post_type !== 'question') return;

    $author_id = $question_post->post_author;
    $author_email = get_userdata($author_id)->user_email;

    $subject = 'Ai primit un răspuns la întrebarea ta';
    $message = sprintf(
        '<html><body>
            <h2 style="color:#1d72b8;">Ai primit un răspuns nou</h2>
            <p>Întrebarea ta: <strong>%s</strong> a primit un răspuns.</p>
            <p><a href="%s" style="display:inline-block; padding:10px 16px; background-color:#1d72b8; color:#fff; text-decoration:none; border-radius:4px;">Vezi răspunsul</a></p>
            <br><p style="font-size:12px; color:#888;">Echipa Vogo</p>
        </body></html>',
        esc_html($question_post->post_title),
        esc_url(home_url('/question-thread/?question_id=' . $question_id))
    );

    $headers = ['Content-Type: text/html; charset=UTF-8'];
    wp_mail($author_email, $subject, $message, $headers);
}

// Ensure Material Icons are loaded in the theme
add_action( 'wp_head', function() {
    echo '<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">';
});