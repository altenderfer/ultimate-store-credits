<?php
/**
 * "Store Credits" tab in My Account, shows current user credit balance.
 *
 * @package ultimate-store-credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class USC_Frontend {

    public function __construct() {
        add_filter( 'woocommerce_account_menu_items', array( $this, 'add_store_credits_endpoint' ) );
        add_action( 'init', array( $this, 'add_store_credits_rewrite_endpoint' ) );
        add_action( 'woocommerce_account_store-credits_endpoint', array( $this, 'store_credits_content' ) );

        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
    }

    public function add_store_credits_endpoint( $items ) {
        $new = array();
        foreach ( $items as $key => $title ) {
            $new[ $key ] = $title;
            if ( 'orders' === $key ) {
                // Insert after "Orders"
                $new['store-credits'] = __( 'Store Credits', 'ultimate-store-credits' );
            }
        }
        return $new;
    }

    public function add_store_credits_rewrite_endpoint() {
        add_rewrite_endpoint( 'store-credits', EP_ROOT | EP_PAGES );
    }

    public function store_credits_content() {
        if ( ! is_user_logged_in() ) {
            echo '<p>' . esc_html__( 'You must be logged in to view your store credits.', 'ultimate-store-credits' ) . '</p>';
            return;
        }
        wc_get_template(
            'my-account/store-credits.php',
            array(),
            '',
            USC_PLUGIN_DIR . 'templates/'
        );
    }

    public function enqueue_frontend_assets() {
        if ( is_account_page() || is_checkout() || is_cart() ) {
            wp_enqueue_style(
                'usc-front',
                USC_PLUGIN_URL . 'assets/css/usc-front.css',
                array(),
                USC_PLUGIN_VERSION
            );
            wp_enqueue_script(
                'usc-front',
                USC_PLUGIN_URL . 'assets/js/usc-front.js',
                array( 'jquery' ),
                USC_PLUGIN_VERSION,
                true
            );
        }
    }
}
