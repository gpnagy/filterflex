<?php
// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Get plugin settings
$settings = get_option( 'filterflex_settings' );

// Check if we should remove all data
if ( isset( $settings['remove_data_on_uninstall'] ) && $settings['remove_data_on_uninstall'] ) {
    // Delete all FilterFlex filters
    $filters = get_posts( array(
        'post_type'      => 'filterflex_filter',
        'post_status'    => 'any',
        'numberposts'    => -1,
        'fields'         => 'ids',
    ) );

    foreach ( $filters as $filter_id ) {
        wp_delete_post( $filter_id, true );
    }

    // Delete all plugin options
    delete_option( 'filterflex_settings' );

    // Delete all plugin meta data
    $known_meta_keys = array(
        '_filterflex_filterable_element',
        '_filterflex_priority',
        '_filterflex_location_rules',
        '_filterflex_output_config',
        '_filterflex_transformations',
        '_filterflex_apply_area'
    );

    foreach ( $known_meta_keys as $meta_key ) {
        delete_post_meta_by_key( $meta_key );
    }

    // Delete any transients
    delete_transient('filterflex_cache');

    // Clear any cached data
    wp_cache_flush();
}
