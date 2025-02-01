<?php
/**
 * Plugin Name: Ultimate Store Credits for WooCommerce
 * Plugin URI:  https://kylealtenderfer.com
 * Description: Provide store-credit functionality in WooCommerce with annual resets (fixed or anniversary), partial usage, rollover, domain registration restrictions, etc. Now with an updated interface, hourly stale-coupon cleanup, and GitHub-based updates.
 * Version:     1.0.0
 * Author:      Kyle Altenderfer
 * Author URI:  https://kylealtenderfer.com
 * Text Domain: ultimate-store-credits
 * Domain Path: /languages
 * License:     GPL-2.0+
 *
 * GitHub Plugin URI: https://github.com/altenderfer/ultimate-store-credits
 *
 * @package ultimate-store-credits
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'USC_PLUGIN_VERSION', '1.0.0' );
define( 'USC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'USC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include the plugin-update-checker library.
require_once USC_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';

// Use the PUC namespace/class.
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// Build update checker instance - point to your GitHub repo.
$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/altenderfer/ultimate-store-credits/', // ← GitHub repo URL
    __FILE__,                                                // Full path of this file
    'ultimate-store-credits'                                 // Your plugin's "slug" (must be unique)
);
$myUpdateChecker->getVcsApi()->enableReleaseAssets();


// Now include our plugin's classes/files:
require_once USC_PLUGIN_DIR . 'includes/class-usc-activator.php';
require_once USC_PLUGIN_DIR . 'includes/class-usc-deactivator.php';
require_once USC_PLUGIN_DIR . 'includes/class-usc-cron.php';
require_once USC_PLUGIN_DIR . 'includes/class-usc-admin.php';
require_once USC_PLUGIN_DIR . 'includes/class-usc-frontend.php';
require_once USC_PLUGIN_DIR . 'includes/class-usc-partial.php';

/**
 * On plugin activation: set defaults, schedule cron, flush rewrites, etc.
 */
function usc_activate_plugin() {
    USC_Activator::activate();
    // Ensure the store-credits rewrite endpoint is added, then flush rewrites
    add_rewrite_endpoint( 'store-credits', EP_ROOT | EP_PAGES );
    flush_rewrite_rules( false );
}
register_activation_hook( __FILE__, 'usc_activate_plugin' );

/**
 * On plugin deactivation: remove cron schedules, flush rewrites, etc.
 */
function usc_deactivate_plugin() {
    USC_Deactivator::deactivate();
    flush_rewrite_rules( false );
}
register_deactivation_hook( __FILE__, 'usc_deactivate_plugin' );

/**
 * Initialize plugin logic once all plugins (and WooCommerce) are loaded.
 */
add_action( 'plugins_loaded', 'usc_init_plugin' );
function usc_init_plugin() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="error"><p><strong>Ultimate Store Credits:</strong> WooCommerce must be installed and active.</p></div>';
        } );
        return;
    }

    // Load Payment Gateway that extends WC
    require_once USC_PLUGIN_DIR . 'includes/class-usc-payment-gateway.php';

    // Initialize main classes
    new USC_Admin();
    new USC_Cron();
    new USC_Frontend();
    new USC_Partial();

    // Register the custom payment gateway
    add_filter( 'woocommerce_payment_gateways', function( $methods ) {
        $methods[] = 'USC_Payment_Gateway';
        return $methods;
    } );
}

/**
 * Enforce domain-based registration restrictions, if enabled.
 */
add_action( 'woocommerce_register_post', 'usc_validate_domain_on_registration', 10, 3 );
function usc_validate_domain_on_registration( $username, $email, $errors ) {
    $enabled = get_option( 'usc_enable_domain_restriction', 'no' );
    if ( 'yes' !== $enabled ) {
        return;
    }
    $allowed_domain = get_option( 'usc_allowed_domain', '' );
    if ( empty( $allowed_domain ) ) {
        return;
    }

    $domain = strtolower( substr( strrchr( $email, '@' ), 1 ) );
    if ( $domain !== strtolower( $allowed_domain ) ) {
        $errors->add(
            'usc_domain_error',
            sprintf(
                __( 'You must register with an @%s email address.', 'ultimate-store-credits' ),
                $allowed_domain
            )
        );
    }
}
