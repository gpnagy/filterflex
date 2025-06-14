<?php
/**
 * Plugin Name:       FilterFlex
 * Plugin URI:        https://wordpress.org/plugins/filterflex
 * Description:       A powerful plugin for applying filters to various WordPress elements with custom field support and dynamic tag replacement.
 * Version:           1.0.1
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            George Nagy
 * Author URI:        https://github.com/gpnagy/filterflex
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       filterflex
 * Domain Path:       /languages
 *
 * @package          FilterFlex
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define Constants
define( 'FILTERFLEX_VERSION', '1.0.0' );
define( 'FILTERFLEX_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FILTERFLEX_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoload Composer dependencies
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Include core files
require_once FILTERFLEX_PLUGIN_DIR . 'includes/core-functions.php';
require_once FILTERFLEX_PLUGIN_DIR . 'includes/class-filterflex.php';

// Include admin files
require_once FILTERFLEX_PLUGIN_DIR . 'admin/admin-menu.php';

// Instantiate the main plugin class
$filterflex = new FilterFlex();

// Plugin activation hook
register_activation_hook( __FILE__, [ $filterflex, 'activate' ] );

// Plugin deactivation hook
register_deactivation_hook( __FILE__, [ $filterflex, 'deactivate' ] );

// Plugin uninstall hook (optional - see notes)
register_uninstall_hook( __FILE__, 'filterflex_uninstall' );  // VERY CAREFUL WITH THIS!

/**
 * Uninstall function (DANGEROUS!).
 *
 * This is run when the plugin is deleted through the WordPress admin.  USE WITH EXTREME CAUTION.
 * It's typically used to remove any data the plugin created (e.g., database tables, options).
 */
function filterflex_uninstall() {
    // REMOVE ALL PLUGIN DATA HERE!  Use delete_option(), drop_table(), etc.
}

function filterflex_enqueue_admin_scripts( $hook ) {
    // Only enqueue scripts on the FilterFlex admin pages
    if ( 'toplevel_page_filterflex' !== $hook ) {
        return;
    }

    wp_enqueue_script(
        'jquery-ui-droppable'
    );

    wp_enqueue_script(
        'filterflex-admin-js',
        FILTERFLEX_PLUGIN_URL . 'admin/js/filterflex-admin.js',
        [ 'jquery', 'jquery-ui-core', 'jquery-ui-droppable' ], // Dependency: jQuery and jQuery UI
        FILTERFLEX_VERSION,
        true // Enqueue in the footer
    );

    wp_enqueue_style(
        'filterflex-admin-css',
        FILTERFLEX_PLUGIN_URL . 'admin/css/filterflex-admin.css',
        [],
        FILTERFLEX_VERSION
    );
}
add_action( 'admin_enqueue_scripts', 'filterflex_enqueue_admin_scripts' );

// Removed redundant filterflex_localize_script function and action hook.
// Localization is now handled within FilterFlex::enqueue_admin_assets for the specific edit screen.
