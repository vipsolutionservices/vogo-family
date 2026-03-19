<?php
/*
Plugin Name: Vogo Personalization and Referrals
Description: Handles user categories, personalization, and referrals for Vogo.me.
Version: 1.0
Author: Durgesh Tanwar
*/

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Includes
include_once plugin_dir_path(__FILE__) . 'includes/database.php';
include_once plugin_dir_path(__FILE__) . 'includes/admin.php';
include_once plugin_dir_path(__FILE__) . 'includes/frontend.php';
include_once plugin_dir_path(__FILE__) . 'includes/referrals.php';
include_once plugin_dir_path(__FILE__) . 'includes/user-categories.php';
include_once plugin_dir_path(__FILE__) . 'includes/product_recommendation.php';
include_once plugin_dir_path(__FILE__) . 'includes/woo_product_add_referral.php';

// Activation Hook
register_activation_hook(__FILE__, 'vogo_personalization_activate');
function vogo_personalization_activate() {
    vogo_create_tables();
}

add_action('wp_enqueue_scripts', 'vogo_enqueue_qrcode_js');
function vogo_enqueue_qrcode_js() {
    wp_enqueue_script(
        'qrcode-js',
        plugin_dir_url(__FILE__) . 'assets/js/qrcode.min.js',
        [],
        null,
        true
    );
}

function enqueue_swal_scripts() {
    // Enqueue SweetAlert2 from CDN
    wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), null, true);
}
add_action('wp_enqueue_scripts', 'enqueue_swal_scripts');


add_action('wp_enqueue_scripts', 'enqueue_add_to_reference_js');
function enqueue_add_to_reference_js() {

    $script_url  = plugin_dir_url(__FILE__) . 'assets/js/add-to-reference.js';
    $script_path = plugin_dir_path(__FILE__) . 'assets/js/add-to-reference.js';

    // Get the file modification time
    $script_version = file_exists($script_path) ? filemtime($script_path) : '1.0';
    wp_enqueue_script(
        'add-to-reference-js',
        plugins_url('assets/js/add-to-reference.js', __FILE__), // Adjust the path accordingly
        ['jquery'],
        $script_version,
        true
    );

    wp_localize_script('add-to-reference-js', 'addToReference', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('add_to_reference_nonce'),
    ]);
}

add_action('wp_enqueue_scripts', 'vogo_rec_enqueue_scripts');
function vogo_rec_enqueue_scripts() {
    // 1) Build the absolute filesystem path to vogo-rec.js
    $js_path = plugin_dir_path(__FILE__) . 'assets/js/vogo-rec.js';

    // 2) Check if the file exists on the server, use its modification time or a default version
    if (file_exists($js_path)) {
        $script_version = filemtime($js_path);
    } else {
        $script_version = '1.0';
    }

    // 3) Build the public URL that browsers will use to load the script
    $js_url = plugin_dir_url(__FILE__) . 'assets/js/vogo-rec.js';

    // 4) Enqueue the script
    wp_enqueue_script(
        'vogo-rec-js',
        $js_url,
        ['jquery'],
        $script_version,
        true
    );

    // 5) Localize variables for AJAX
    wp_localize_script(
        'vogo-rec-js',
        'vogoRecAjax',
        [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('vogo_remove_product_nonce'),
        ]
    );
}

add_action('wp_enqueue_scripts', 'enqueue_font_awesome_icons');
function enqueue_font_awesome_icons() {
    // Example: Font Awesome (CDN)
    wp_enqueue_style(
        'font-awesome',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css',
        array(),
        '6.0.0'
    );
}


