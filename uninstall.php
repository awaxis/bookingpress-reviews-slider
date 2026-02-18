<?php
/**
 * Uninstall BookingPress Reviews Slider
 *
 * Removes all plugin data when plugin is deleted
 */

// Exit if accessed directly or if uninstall is not called by WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete plugin options
delete_option( 'bpr_slider_limit' );
delete_option( 'bpr_slider_staff_id' );
delete_option( 'bpr_slider_min_rating' );
delete_option( 'bpr_slider_autoplay' );
delete_option( 'bpr_slider_autoplay_speed' );
delete_option( 'bpr_slider_show_date' );
delete_option( 'bpr_slider_show_title' );
delete_option( 'bpr_slider_show_arrows' );
delete_option( 'bpr_slider_columns' );
delete_option( 'bpr_slider_card_bg_color' );
delete_option( 'bpr_slider_card_border_color' );
delete_option( 'bpr_slider_card_border_radius' );

// For multisite
if ( is_multisite() ) {
    global $wpdb;

    $blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );

    foreach ( $blog_ids as $blog_id ) {
        switch_to_blog( $blog_id );

        delete_option( 'bpr_slider_limit' );
        delete_option( 'bpr_slider_staff_id' );
        delete_option( 'bpr_slider_min_rating' );
        delete_option( 'bpr_slider_autoplay' );
        delete_option( 'bpr_slider_autoplay_speed' );
        delete_option( 'bpr_slider_show_date' );
        delete_option( 'bpr_slider_show_title' );
        delete_option( 'bpr_slider_show_arrows' );
        delete_option( 'bpr_slider_columns' );
        delete_option( 'bpr_slider_card_bg_color' );
        delete_option( 'bpr_slider_card_border_color' );
        delete_option( 'bpr_slider_card_border_radius' );

        restore_current_blog();
    }
}

// Note: We don't delete any review data as it belongs to BookingPress
