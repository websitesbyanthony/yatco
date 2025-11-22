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