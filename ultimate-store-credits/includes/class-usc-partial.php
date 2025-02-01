<?php
/**
 * Partial usage logic: creates a one-time coupon code (credits-XXX) if user has some credit but not enough for the entire cart.
 *
 * @package ultimate-store-credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class USC_Partial {

    public function __construct() {
        // Only at Checkout
        add_action( 'woocommerce_before_checkout_form', array( $this, 'maybe_render_partial_ui' ), 5 );

        // Handle coupon creation/removal
        add_action( 'wp_loaded', array( $this, 'handle_create_remove_credit_coupon' ), 20 );

        // Store partial usage in order meta
        add_action( 'woocommerce_checkout_create_order', array( $this, 'record_coupon_usage_in_order_meta' ), 20 );

        // Immediately deduct used credits after order is placed
        add_action( 'woocommerce_checkout_order_processed', array( $this, 'deduct_used_credits' ), 20 );

        // Inline CSS for partial usage button
        add_action( 'wp_head', array( $this, 'inject_custom_styles' ), 999 );

        // Real-time detection of coupon removal
        add_action( 'wp_footer', array( $this, 'add_real_time_removal_script' ) );
    }

    public function maybe_render_partial_ui() {
        if ( 'yes' !== get_option( 'usc_allow_partial_usage', 'no' ) ) {
            return;
        }
        if ( ! is_user_logged_in() || ! WC()->cart || ! is_checkout() ) {
            return;
        }

        $user_id        = get_current_user_id();
        $balance        = floatval( get_user_meta( $user_id, 'usc_store_credits', true ) );
        $cart_total_str = WC()->cart->get_total( 'edit' );
        $cart_total     = (float) ( $cart_total_str ? $cart_total_str : 0 );

        // Show only if balance > 0 but less than cart total
        if ( $balance <= 0 || $balance >= $cart_total ) {
            return;
        }

        $existing_credit_coupon = $this->get_existing_credit_coupon_code_in_cart();

        echo '<div class="usc-partial-usage-ui" style="margin:1em 0; padding:1em; border:1px solid #eee; border-radius:4px;">';
        echo '<h3 style="margin-top:0;">' . esc_html__( 'Store Credit (Partial)', 'ultimate-store-credits' ) . '</h3>';

        if ( $existing_credit_coupon ) {
            printf(
                '<p class="usc-partial-desc">%s</p>',
                sprintf(
                    esc_html__( 'A store-credit coupon (%s) is currently applied. Click below to remove it.', 'ultimate-store-credits' ),
                    '<span class="usc-credit-coupon-code">' . esc_html( $existing_credit_coupon ) . '</span>'
                )
            );
            ?>
            <form method="post" style="display:inline;">
                <?php wp_nonce_field( 'usc_remove_coupon_action', 'usc_remove_coupon_nonce' ); ?>
                <input type="hidden" name="usc_remove_coupon" value="<?php echo esc_attr( $existing_credit_coupon ); ?>" />
                <input type="hidden" name="usc_page_source" value="checkout" />
                <button type="submit" class="button usc-remove-credit-coupon-btn">
                    <?php esc_html_e( 'Remove Store Credit Coupon', 'ultimate-store-credits' ); ?>
                </button>
            </form>
            <?php
        } else {
            ?>
            <p class="usc-partial-desc">
                <?php
                printf(
                    esc_html__( 'Your current credit is $%s. Generate a one-time coupon to apply partial credits.', 'ultimate-store-credits' ),
                    number_format_i18n( $balance, 2 )
                );
                ?>
            </p>
            <form method="post" style="display:inline;">
                <?php wp_nonce_field( 'usc_create_coupon_action', 'usc_create_coupon_nonce' ); ?>
                <input type="hidden" name="usc_create_coupon" value="1" />
                <input type="hidden" name="usc_page_source" value="checkout" />
                <button type="submit" class="button usc-create-credit-coupon-btn">
                    <?php esc_html_e( 'Apply Store Credits', 'ultimate-store-credits' ); ?>
                </button>
            </form>
            <?php
        }

        $disclaimer = get_option( 'usc_partial_disclaimer_text', '' );
        if ( ! empty( $disclaimer ) ) {
            printf(
                '<p class="usc-partial-disclaimer" style="margin-top:0.8em; font-size:0.9em;">%s</p>',
                esc_html( $disclaimer )
            );
        }
        echo '</div>';
    }

    public function handle_create_remove_credit_coupon() {
        // Remove coupon
        if ( isset( $_POST['usc_remove_coupon'] ) && ! empty( $_POST['usc_remove_coupon'] ) ) {
            if ( isset( $_POST['usc_remove_coupon_nonce'] ) &&
                 wp_verify_nonce( $_POST['usc_remove_coupon_nonce'], 'usc_remove_coupon_action' ) ) {

                $coupon_code = sanitize_text_field( $_POST['usc_remove_coupon'] );
                if ( WC()->cart ) {
                    WC()->cart->remove_coupon( $coupon_code );
                }
            }
            wp_safe_redirect( wc_get_checkout_url() );
            exit;
        }

        // Create coupon
        if ( isset( $_POST['usc_create_coupon'] ) ) {
            if ( isset( $_POST['usc_create_coupon_nonce'] ) &&
                 wp_verify_nonce( $_POST['usc_create_coupon_nonce'], 'usc_create_coupon_action' ) ) {

                $existing_coupon = $this->get_existing_credit_coupon_code_in_cart();
                if ( ! $existing_coupon ) {
                    $user_id     = get_current_user_id();
                    $balance     = floatval( get_user_meta( $user_id, 'usc_store_credits', true ) );
                    if ( ! WC()->cart ) {
                        return;
                    }
                    $cart_total_str = WC()->cart->get_total( 'edit' );
                    $cart_total     = (float) ( $cart_total_str ? $cart_total_str : 0 );

                    if ( $balance > 0 && $cart_total > 0 && $balance < $cart_total ) {
                        $coupon_amount = min( $balance, $cart_total );
                        $random_suffix = substr( md5( uniqid( '', true ) ), 0, 10 );
                        $coupon_code   = 'credits-' . $random_suffix;

                        $coupon_id = wp_insert_post( array(
                            'post_title'   => $coupon_code,
                            'post_content' => __( 'Auto-generated one-time store-credit coupon.', 'ultimate-store-credits' ),
                            'post_status'  => 'publish',
                            'post_author'  => 1,
                            'post_type'    => 'shop_coupon',
                        ) );

                        if ( ! is_wp_error( $coupon_id ) ) {
                            update_post_meta( $coupon_id, 'discount_type', 'fixed_cart' );
                            update_post_meta( $coupon_id, 'coupon_amount', $coupon_amount );
                            update_post_meta( $coupon_id, 'individual_use', 'no' );
                            update_post_meta( $coupon_id, 'usage_limit', '1' );
                            update_post_meta( $coupon_id, 'usage_limit_per_user', '1' );
                            update_post_meta( $coupon_id, 'free_shipping', 'no' );
                            update_post_meta( $coupon_id, '_usc_coupon_note', sprintf(
                                __( 'Store-credit partial usage for user ID #%d', 'ultimate-store-credits' ),
                                $user_id
                            ) );
                            WC()->cart->add_discount( sanitize_text_field( $coupon_code ) );
                        }
                    }
                }
            }
            wp_safe_redirect( wc_get_checkout_url() );
            exit;
        }
    }

    private function get_existing_credit_coupon_code_in_cart() {
        if ( ! WC()->cart ) {
            return false;
        }
        $coupons = WC()->cart->get_applied_coupons();
        foreach ( $coupons as $code ) {
            if ( 0 === strpos( $code, 'credits-' ) ) {
                return $code;
            }
        }
        return false;
    }

    public function record_coupon_usage_in_order_meta( $order ) {
        if ( ! $order ) {
            return;
        }
        $used_coupons = $order->get_used_coupons();

        $store_credit_used = 0.0;
        if ( ! empty( $used_coupons ) ) {
            foreach ( $used_coupons as $code ) {
                if ( 0 === strpos( $code, 'credits-' ) ) {
                    $coupon_post = get_page_by_title( $code, OBJECT, 'shop_coupon' );
                    if ( $coupon_post ) {
                        $amt = get_post_meta( $coupon_post->ID, 'coupon_amount', true );
                        $store_credit_used += floatval( $amt );
                    }
                }
            }
        }

        if ( $store_credit_used > 0 ) {
            $order->update_meta_data( '_usc_partial_used', $store_credit_used );
        }
    }

    public function deduct_used_credits( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }
        $user_id = $order->get_user_id();
        if ( ! $user_id ) {
            return;
        }

        $already_deducted = get_post_meta( $order_id, '_usc_partial_already_deducted', true );
        if ( $already_deducted ) {
            return;
        }

        $used_credit = floatval( $order->get_meta( '_usc_partial_used', true ) );
        if ( $used_credit <= 0 ) {
            return;
        }

        $current_balance = floatval( get_user_meta( $user_id, 'usc_store_credits', true ) );
        $new_balance     = max( 0, $current_balance - $used_credit );
        update_user_meta( $user_id, 'usc_store_credits', $new_balance );

        $order->add_order_note( sprintf(
            __( 'Customer used $%s of store credits (partial). Deducted immediately.', 'ultimate-store-credits' ),
            number_format_i18n( $used_credit, 2 )
        ) );
        update_post_meta( $order_id, '_usc_partial_already_deducted', 1 );
    }

    public function inject_custom_styles() {
        if ( ! is_checkout() ) {
            return;
        }
        $bg_color   = get_option( 'usc_partial_button_bg', '#0073aa' );
        $text_color = get_option( 'usc_partial_button_text_color', '#ffffff' );
        echo '<style>
            .usc-partial-usage-ui button.usc-create-credit-coupon-btn {
                background-color: ' . esc_html( $bg_color ) . ';
                color: ' . esc_html( $text_color ) . ';
                border-color: ' . esc_html( $bg_color ) . ';
            }
            .usc-partial-usage-ui button.usc-create-credit-coupon-btn:hover {
                opacity: 0.9;
            }
            .usc-partial-usage-ui button.usc-remove-credit-coupon-btn {
                background-color: #888;
                color: #fff;
                border-color: #666;
            }
            .usc-partial-usage-ui button.usc-remove-credit-coupon-btn:hover {
                opacity: 0.9;
            }
        </style>';
    }

    public function add_real_time_removal_script() {
        if ( ! is_checkout() ) {
            return;
        }
        ?>
        <script>
        (function($){
            $(document).on('removed_coupon', function(e, data){
                if( data.coupon.indexOf('credits-') === 0 ){
                    location.reload();
                }
            });
        })(jQuery);
        </script>
        <?php
    }
}
