<?php
// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete all FilterFlex filters (posts)
$filters = get_posts([
    'post_type' => 'filterflex_filter',
    'numberposts' => -1,
    'post_status' => 'any'
]);

foreach ($filters as $filter) {
    wp_delete_post($filter->ID, true);
}

// Delete plugin options
delete_option('filterflex_settings');

// Delete any transients
delete_transient('filterflex_cache');

// Clean up post meta
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_filterflex_%'");

// Clear any cached data
wp_cache_flush();
