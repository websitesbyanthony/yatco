<?php
/**
 * Custom Post Type Registration
 * 
 * Registers the 'yacht' custom post type.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register Yacht CPT on activation.
 */
function yatco_create_cpt() {
    register_post_type(
        'yacht',
        array(
            'labels'       => array(
                'name'          => 'Yachts',
                'singular_name' => 'Yacht',
            ),
            'public'       => true,
            'has_archive'  => true,
            'rewrite'      => array( 'slug' => 'yachts' ),
            'supports'     => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
            'show_in_rest' => true,
        )
    );
    flush_rewrite_rules();
}

/**
 * Load single yacht template from plugin if theme doesn't have one.
 * 
 * WordPress will use single-yacht.php from your theme if it exists.
 * Otherwise, it will use this plugin's template.
 */
function yatco_load_single_yacht_template( $template ) {
    global $post;
    
    // Only for yacht post type
    if ( ! $post || $post->post_type !== 'yacht' ) {
        return $template;
    }
    
    // Check if theme has a single-yacht.php template
    $theme_template = locate_template( array( 'single-yacht.php' ) );
    
    // If theme has a template, use it (theme takes priority)
    if ( $theme_template ) {
        return $theme_template;
    }
    
    // Otherwise, use plugin template
    $plugin_template = YATCO_PLUGIN_DIR . 'templates/single-yacht.php';
    if ( file_exists( $plugin_template ) ) {
        return $plugin_template;
    }
    
    // Fallback to default single template
    return $template;
}
add_filter( 'single_template', 'yatco_load_single_yacht_template' );

