<?php

require_once get_theme_file_path('/inc/providers/management.php');  // Provider CRUD
require_once get_theme_file_path('/inc/providers/settings.php');    // Field Mapping UI
require_once get_theme_file_path('/inc/providers/cron.php');        // Cron Jobs
require_once get_theme_file_path('/inc/providers/helpers.php');     // Utility Functions
require_once get_theme_file_path('/inc/providers/feed.php'); 
require_once get_theme_file_path('/inc/providers/wizard/wizard.php'); // Feed Wizard
function texacom_feed_menu() {
  add_submenu_page(
      'woocommerce', 
      'Texacom Feed', 
      'Texacom Feed', 
      'manage_options', 
      'texacom-feed', 
      'texacom_feed_page'
  );
}
add_action('admin_menu', 'texacom_feed_menu');

function texacom_feed_page() {
  ?>
  <div class="wrap">
      <h1 class="text-3xl font-bold">Texacom Feed Processing</h1>
      <p>Click the button below to process the Texacom feed and generate a new CSV file.</p>

      <form method="post">
          <button type="submit" name="process_texacom_feed" class="button button-primary">Process Texacom Feed</button>
      </form>

      <?php
      if (isset($_POST['process_texacom_feed'])) {
          $csv_url = process_texacom_feed();

          if ($csv_url) {
              echo "<p style='color: green; font-weight: bold;'>✅ Texacom Feed Processed Successfully!</p>";
              echo "<p><a href='$csv_url' target='_blank' class='button button-secondary'>📥 Download Updated CSV</a></p>";
          } else {
              echo "<p style='color: red; font-weight: bold;'>❌ Failed to process the Texacom feed. Check error logs.</p>";
          }
      }
      ?>
  </div>
  <?php
}
add_action('admin_menu', function () {
    add_menu_page(
        'Manual Feed Upload',
        'Upload Feed',
        'manage_options',
        'upload-provider-feed',
        'render_provider_feed_upload_page',
        'dashicons-upload',
        58 // Position in menu
    );
});