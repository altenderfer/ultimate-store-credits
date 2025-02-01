<?php
/**
 * Store Credits tab content in My Account.
 *
 * @package ultimate-store-credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$user_id = get_current_user_id();
$credits = get_user_meta( $user_id, 'usc_store_credits', true );
$credits = $credits ? floatval( $credits ) : 0.0;
?>
<div class="usc-store-credits-container">
    <h3><?php esc_html_e( 'Your Store Credits', 'ultimate-store-credits' ); ?></h3>
    <p>
        <?php
        printf(
            esc_html__( 'You currently have $%s in store credits.', 'ultimate-store-credits' ),
            '<span class="usc-store-credit-amount">' . number_format_i18n( $credits, 2 ) . '</span>'
        );
        ?>
    </p>
    <p>
        <?php esc_html_e( 'Use them at checkout if you have enough for a full payment, or generate a partial coupon if your credits are less than the total.', 'ultimate-store-credits' ); ?>
    </p>
</div>
