<?php
/**
 * Hourly cron: resets credits on the specified date or user anniversary date; cleans up stale "credits-xxx" coupons.
 *
 * @package ultimate-store-credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class USC_Cron {

    public function __construct() {
        add_action( 'usc_hourly_cron_event', array( $this, 'handle_hourly_cron' ) );
        add_action( 'user_register', array( $this, 'handle_user_registration' ) );
    }

    /**
     * Runs every hour. Checks if it's the correct day for a fixed reset or if any user is at their anniversary date.
     * Also removes stale partial-credit coupons.
     */
    public function handle_hourly_cron() {
        $reset_method = get_option( 'usc_reset_method', 'fixed' );

        if ( 'fixed' === $reset_method ) {
            // Check once per day if it's the correct date. If not the day, do nothing.
            $month = get_option( 'usc_yearly_credit_reset_month', '1' );
            $day   = get_option( 'usc_yearly_credit_reset_day', '1' );

            $target_md = sprintf( '%02d-%02d', intval( $month ), intval( $day ) );
            $today_md  = date( 'm-d', current_time( 'timestamp' ) );

            if ( $today_md === $target_md ) {
                $amount = get_option( 'usc_yearly_credit_amount', 400 );
                self::reset_all_user_credits( $amount );
            }

        } else {
            // Anniversary date approach
            $all_users = get_users( array( 'fields' => array( 'ID' ) ) );
            foreach ( $all_users as $user ) {
                $anniversary_date = get_user_meta( $user->ID, 'usc_anniversary_date', true );
                if ( ! $anniversary_date ) {
                    continue;
                }
                $md       = date( 'm-d', strtotime( $anniversary_date ) );
                $today_md = date( 'm-d', current_time( 'timestamp' ) );

                if ( $md === $today_md ) {
                    $this->reset_or_rollover_user( $user->ID );
                }
            }
        }

        // Cleanup stale "credits-xxx" coupons
        self::cleanup_stale_coupons();
    }

    /**
     * Resets or rolls over credits for a single user, depending on admin settings.
     */
    private function reset_or_rollover_user( $user_id ) {
        $current_balance = (float) get_user_meta( $user_id, 'usc_store_credits', true );
        $yearly_amount   = (float) get_option( 'usc_yearly_credit_amount', 400 );
        $allow_rollover  = get_option( 'usc_allow_rollover', 'no' );
        $rollover_max    = get_option( 'usc_rollover_max', '' );

        if ( 'yes' === $allow_rollover ) {
            $new_balance = $current_balance + $yearly_amount;
            if ( $rollover_max ) {
                $max = floatval( $rollover_max );
                if ( $max > 0 ) {
                    $new_balance = min( $new_balance, $max );
                }
            }
        } else {
            $new_balance = $yearly_amount;
        }

        update_user_meta( $user_id, 'usc_store_credits', $new_balance );
    }

    /**
     * Resets all user credits (for a fixed date or manual reset).
     * @param float $amount The new amount to set or to add (depending on rollover).
     * @param bool  $forceReplace If true, forcibly replace the user’s balance with $amount.
     */
    public static function reset_all_user_credits( $amount, $forceReplace = false ) {
        $all_users = get_users( array( 'fields' => array( 'ID' ) ) );
        $allow_rollover = get_option( 'usc_allow_rollover', 'no' );
        $rollover_max   = get_option( 'usc_rollover_max', '' );

        foreach ( $all_users as $user ) {
            $user_id = $user->ID;
            $current_balance = (float) get_user_meta( $user_id, 'usc_store_credits', true );

            if ( $forceReplace ) {
                update_user_meta( $user_id, 'usc_store_credits', $amount );
                continue;
            }

            if ( 'yes' === $allow_rollover ) {
                $new_balance = $current_balance + $amount;
                if ( $rollover_max ) {
                    $max = floatval( $rollover_max );
                    if ( $max > 0 ) {
                        $new_balance = min( $new_balance, $max );
                    }
                }
                update_user_meta( $user_id, 'usc_store_credits', $new_balance );
            } else {
                update_user_meta( $user_id, 'usc_store_credits', $amount );
            }
        }
    }

    /**
     * Initialize user credit on registration, plus set "anniversary date" to today if not set.
     */
    public function handle_user_registration( $user_id ) {
        $amount = get_option( 'usc_yearly_credit_amount', 400 );
        update_user_meta( $user_id, 'usc_store_credits', $amount );

        $existing_anniv = get_user_meta( $user_id, 'usc_anniversary_date', true );
        if ( ! $existing_anniv ) {
            $today = date( 'Y-m-d', current_time( 'timestamp' ) );
            update_user_meta( $user_id, 'usc_anniversary_date', $today );
        }
    }

    /**
     * Remove stale "credits-xxx" coupons older than 1 day with usage_count = 0
     */
    private static function cleanup_stale_coupons() {
        global $wpdb;
        $one_day_ago = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - DAY_IN_SECONDS );

        $results = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT p.ID
                   FROM {$wpdb->posts} p
                   INNER JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id)
                  WHERE p.post_type = 'shop_coupon'
                    AND p.post_title LIKE %s
                    AND pm.meta_key = '_usage_count'
                    AND pm.meta_value = '0'
                    AND p.post_date < %s",
                'credits-%',
                $one_day_ago
            )
        );

        if ( ! empty( $results ) ) {
            foreach ( $results as $coupon_id ) {
                wp_delete_post( $coupon_id, true );
            }
        }
    }
}
