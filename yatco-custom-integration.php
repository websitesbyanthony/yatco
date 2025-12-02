<?php
/**
 * Plugin Name: YATCO Custom Integration
 * Description: Fetch selected YATCO vessels into a Yacht custom post type.
 * Version: 3.1
 * Author: Your Name
 * License: GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'YATCO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'YATCO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include required files
require_once YATCO_PLUGIN_DIR . 'includes/yatco-cpt.php';
require_once YATCO_PLUGIN_DIR . 'includes/yatco-api.php';
require_once YATCO_PLUGIN_DIR . 'includes/yatco-helpers.php';
require_once YATCO_PLUGIN_DIR . 'includes/yatco-admin.php';
require_once YATCO_PLUGIN_DIR . 'includes/yatco-shortcode.php';
require_once YATCO_PLUGIN_DIR . 'includes/yatco-cache.php';

// Register activation hook
register_activation_hook( __FILE__, 'yatco_create_cpt' );

// Register shortcode on init
add_action( 'init', 'yatco_register_shortcode' );

// Register cache warming hook
add_action( 'yatco_warm_cache_hook', 'yatco_warm_cache_function' );

// Schedule periodic cache refresh if enabled
add_action( 'admin_init', 'yatco_maybe_schedule_cache_refresh' );

// Admin settings page
add_action( 'admin_menu', 'yatco_add_admin_menu' );
add_action( 'admin_init', 'yatco_settings_init' );

// Add Update Vessel button to yacht edit screen
add_action( 'add_meta_boxes', 'yatco_add_update_vessel_meta_box' );
add_action( 'admin_post_yatco_update_vessel', 'yatco_handle_update_vessel' );

/**
 * Add custom column to show YATCO link in yacht list table.
 */
function yatco_add_yacht_list_columns( $columns ) {
    // Insert YATCO Link column before Date column
    $new_columns = array();
    foreach ( $columns as $key => $value ) {
        if ( $key === 'date' ) {
            $new_columns['yatco_link'] = 'YATCO Link';
        }
        $new_columns[ $key ] = $value;
    }
    if ( ! isset( $new_columns['yatco_link'] ) ) {
        $new_columns['yatco_link'] = 'YATCO Link';
    }
    return $new_columns;
}
add_filter( 'manage_yacht_posts_columns', 'yatco_add_yacht_list_columns' );

/**
 * Display YATCO link in yacht list table column.
 */
function yatco_show_yacht_list_column( $column, $post_id ) {
    if ( $column === 'yatco_link' ) {
        $listing_url = get_post_meta( $post_id, 'yacht_yatco_listing_url', true );
        if ( empty( $listing_url ) ) {
            // Try to build URL from stored IDs if meta doesn't exist
            $mlsid = get_post_meta( $post_id, 'yacht_mlsid', true );
            $vessel_id = get_post_meta( $post_id, 'yacht_vessel_id', true );
            if ( ! empty( $mlsid ) || ! empty( $vessel_id ) ) {
                $listing_id = ! empty( $mlsid ) ? $mlsid : $vessel_id;
                $listing_url = 'https://www.yatcoboss.com/yacht/' . $listing_id . '/';
            }
        }
        if ( ! empty( $listing_url ) ) {
            echo '<a href="' . esc_url( $listing_url ) . '" target="_blank" rel="noopener noreferrer" class="button button-small">View on YATCO</a>';
        } else {
            echo '<span style="color: #999;">—</span>';
        }
    }
}
add_action( 'manage_yacht_posts_custom_column', 'yatco_show_yacht_list_column', 10, 2 );