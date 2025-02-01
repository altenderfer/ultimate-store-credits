<?php
/**
 * A custom payment gateway for full store credit usage (no partial).
 *
 * @package ultimate-store-credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
    return; // in case WooCommerce isn't loaded
}

class USC_Payment_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'usc_store_credits';
        $this->method_title       = __( 'Store Credits (Full)', 'ultimate-store-credits' );
        $this->method_description = __( 'Lets customers pay entirely with their store credit if they have enough. Hidden if a partial-credit coupon is in use.', 'ultimate-store-credits' );
        $this->title              = __( 'Store Credits', 'ultimate-store-credits' );
        $this->icon               = '';
        $this->has_fields         = true;

        $this->init_form_fields();
        $this->init_settings();

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => __( 'Enable/Disable', 'ultimate-store-credits' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable Full Store Credits Payment', 'ultimate-store-credits' ),
                'default'     => 'yes',
            ),
            'title' => array(
                'title'       => __( 'Title', 'ultimate-store-credits' ),
                'type'        => 'text',
                'description' => __( 'Controls the payment method title at checkout.', 'ultimate-store-credits' ),
                'default'     => __( 'Store Credits', 'ultimate-store-credits' ),
                'desc_tip'    => true,
            ),
        );
    }

    public function payment_fields() {
        if ( ! is_user_logged_in() ) {
            echo '<p>' . esc_html__( 'You must be logged in to use Store Credits.', 'ultimate-store-credits' ) . '</p>';
            return;
        }
        $user_id = get_current_user_id();
        $balance = (float) get_user_meta( $user_id, 'usc_store_credits', true );
        echo '<div class="usc-store-credit-gateway-note">';
        printf(
            '<p>%s</p>',
            sprintf(
                esc_html__( 'Current Store Credit Balance: $%s', 'ultimate-store-credits' ),
                number_format_i18n( $balance, 2 )
            )
        );
        echo '</div>';
    }

    /**
     * Hide if user not logged in, or doesn't have enough credits, or partial coupon is in use.
     */
    public function is_available() {
        if ( 'yes' !== $this->get_option( 'enabled' ) ) {
            return false;
        }
        if ( ! is_user_logged_in() ) {
            return false;
        }
        if ( ! WC()->cart || ! method_exists( WC()->cart, 'get_total' ) ) {
            return false;
        }

        $applied_coupons = WC()->cart->get_applied_coupons();
        foreach ( $applied_coupons as $cp ) {
            if ( 0 === strpos( $cp, 'credits-' ) ) {
                // partial usage is in effect
                return false;
            }
        }

        $user_id      = get_current_user_id();
        $user_credits = (float) get_user_meta( $user_id, 'usc_store_credits', true );

        $cart_total_str = WC()->cart->get_total( 'edit' );
        $cart_total     = (float) ( $cart_total_str ? $cart_total_str : 0 );

        // If user doesn't have enough to cover entire cart, hide
        if ( $user_credits < $cart_total ) {
            return false;
        }
        return true;
    }

    public function process_payment( $order_id ) {
        $order       = wc_get_order( $order_id );
        $user_id     = $order->get_user_id();
        $order_total = (float) $order->get_total();

        if ( ! $user_id ) {
            wc_add_notice( __( 'You must be logged in to use Store Credits.', 'ultimate-store-credits' ), 'error' );
            return;
        }

        $user_credits = (float) get_user_meta( $user_id, 'usc_store_credits', true );
        if ( $user_credits < $order_total ) {
            wc_add_notice( __( 'Not enough store credits for full payment.', 'ultimate-store-credits' ), 'error' );
            return;
        }

        // Deduct
        $new_balance = $user_credits - $order_total;
        update_user_meta( $user_id, 'usc_store_credits', $new_balance );

        // Mark order paid
        $order->payment_complete();
        wc_reduce_stock_levels( $order_id );

        $order->add_order_note( sprintf(
            __( 'Customer used $%s in store credits (full payment).', 'ultimate-store-credits' ),
            number_format_i18n( $order_total, 2 )
        ) );

        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order ),
        );
    }
}
