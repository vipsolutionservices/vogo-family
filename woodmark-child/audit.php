<?php
//https://www.vogo.family/wp-admin/?vogo_plugin_audit=1
function vogo_display_plugin_versions() {
    if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['vogo_plugin_audit'] ) ) {
        return;
    }

    if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $plugins = get_plugins();
    $data = [];

    foreach ( $plugins as $plugin_file => $plugin_data ) {
        $data[] = [
            'Name'    => $plugin_data['Name'],
            'Version' => $plugin_data['Version'],
            'Active'  => is_plugin_active( $plugin_file ) ? 'YES' : 'NO',
            'File'    => $plugin_file,
        ];
    }

    header( 'Content-Type: application/json' );
    echo json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    exit;
}
add_action( 'admin_init', 'vogo_display_plugin_versions' );