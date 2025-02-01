<?php
/**
 * Handles plugin activation tasks.
 *
 * @package ultimate-store-credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class USC_Activator {

    public static function activate() {
        // Changed 400 to 50 here.
        if ( false === get_option( 'usc_yearly_credit_amount' ) ) {
            update_option( 'usc_yearly_credit_amount', 50 );
        }
        if ( false === get_option( 'usc_allow_partial_usage' ) ) {
            update_option( 'usc_allow_partial_usage', 'no' );
        }
        if ( false === get_option( 'usc_yearly_credit_reset_month' ) ) {
            update_option( 'usc_yearly_credit_reset_month', '1' );
        }
        if ( false === get_option( 'usc_yearly_credit_reset_day' ) ) {
            update_option( 'usc_yearly_credit_reset_day', '1' );
        }
        if ( false === get_option( 'usc_partial_button_bg' ) ) {
            update_option( 'usc_partial_button_bg', '#0073aa' );
        }
        if ( false === get_option( 'usc_partial_button_text_color' ) ) {
            update_option( 'usc_partial_button_text_color', '#ffffff' );
        }
        if ( false === get_option( 'usc_partial_disclaimer_text' ) ) {
            update_option( 'usc_partial_disclaimer_text', 'Credits are only deducted if you complete the order.' );
        }

        if ( false === get_option( 'usc_reset_method' ) ) {
            update_option( 'usc_reset_method', 'fixed' ); // 'fixed' or 'anniversary'
        }
        if ( false === get_option( 'usc_allow_rollover' ) ) {
            update_option( 'usc_allow_rollover', 'no' );
        }
        if ( false === get_option( 'usc_rollover_max' ) ) {
            update_option( 'usc_rollover_max', '' );
        }
        if ( false === get_option( 'usc_enable_domain_restriction' ) ) {
            update_option( 'usc_enable_domain_restriction', 'no' );
        }
        if ( false === get_option( 'usc_allowed_domain' ) ) {
            update_option( 'usc_allowed_domain', '' );
        }

        // Ensure an hourly cron for stale coupons.
        if ( ! wp_next_scheduled( 'usc_hourly_cron_event' ) ) {
            wp_schedule_event( time(), 'hourly', 'usc_hourly_cron_event' );
        }
    }
}
