<?php
/**
 * Handles plugin deactivation tasks.
 *
 * @package ultimate-store-credits
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class USC_Deactivator {

    public static function deactivate() {
        // Unschedule our new hourly event
        $timestamp = wp_next_scheduled( 'usc_hourly_cron_event' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'usc_hourly_cron_event' );
        }
    }
}
