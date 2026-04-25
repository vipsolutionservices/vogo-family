<?php
// When the plugin is deleted from WP Admin, remove options.
if (!defined('WP_UNINSTALL_PLUGIN')) exit;
delete_option('vogo_whatsapp_options');
