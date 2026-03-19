<?php

function process_provider_feed() {
    global $wpdb;
    
    $providers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}provider_feeds");

    foreach ($providers as $provider) {
        $feed_url = $provider->feed_url;
        $provider_id = $provider->id;

        // Download the feed
        $csv_file = download_feed($feed_url, $provider_id);

        // Apply price modifications
        $updated_csv = modify_feed_prices($csv_file, $provider_id);

        // Save processed CSV for WP All Import
        save_updated_feed($updated_csv, $provider_id);
    }
}

// Schedule Cron Job
if (!wp_next_scheduled('run_provider_feed_cron')) {
    wp_schedule_event(time(), 'daily', 'run_provider_feed_cron');
}
add_action('run_provider_feed_cron', 'process_provider_feed');


add_filter('cron_schedules', function($schedules) {
    $schedules['monthly'] = [
        'interval' => 30 * DAY_IN_SECONDS,
        'display'  => __('Once Monthly')
    ];
    return $schedules;
});

add_action('init', function () {
    if (!wp_next_scheduled('run_provider_feed_cron')) {
        wp_schedule_event(time(), 'hourly', 'run_provider_feed_cron');
    }
});

add_action('run_provider_feed_cron', function () {
    global $wpdb;

    $providers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}provider_feeds");
    $now = time();

    foreach ($providers as $provider) {
        $last_run = strtotime($provider->last_processed_at ?? '2000-01-01');

        $interval = match($provider->cron_schedule) {
            'daily'   => DAY_IN_SECONDS,
            'weekly'  => WEEK_IN_SECONDS,
            'monthly' => 30 * DAY_IN_SECONDS,
            default   => DAY_IN_SECONDS
        };

        $scheduled_time = strtotime(date('Y-m-d') . ' ' . ($provider->cron_time ?? '03:00:00'));

        // ✅ Check both time match and interval passed
        if (($now >= $scheduled_time) && ($now - $last_run >= $interval)) {
            error_log("🔁 Running feed for provider {$provider->id}: {$provider->provider_name}");

            $result = modify_provider_feed_prices($provider->id);

            if ($result) {
                $wpdb->update(
                    "{$wpdb->prefix}provider_feeds",
                    ['last_processed_at' => current_time('mysql')],
                    ['id' => $provider->id]
                );
            }
        }
    }
});

