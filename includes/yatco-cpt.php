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
            'taxonomies'   => array(), // Explicitly don't register default taxonomies (category, post_tag)
        )
    );
    
    // Register custom taxonomies for archives
    yatco_register_taxonomies();
    
    flush_rewrite_rules();
}

/**
 * Remove default WordPress taxonomies (category, post_tag) from yacht post type.
 * Runs after init to ensure other plugins (like ACF) have finished registering taxonomies.
 */
function yatco_unregister_default_taxonomies() {
    // Unregister default category taxonomy for yacht post type (if registered)
    if ( taxonomy_exists( 'category' ) && is_object_in_taxonomy( 'yacht', 'category' ) ) {
        unregister_taxonomy_for_object_type( 'category', 'yacht' );
    }
    // Unregister default post_tag taxonomy for yacht post type (if registered)
    if ( taxonomy_exists( 'post_tag' ) && is_object_in_taxonomy( 'yacht', 'post_tag' ) ) {
        unregister_taxonomy_for_object_type( 'post_tag', 'yacht' );
    }
}
// Run after init to ensure other plugins have registered their taxonomies first
add_action( 'init', 'yatco_unregister_default_taxonomies', 99 );

/**
 * Register custom taxonomies for Builder, Vessel Type, and Category.
 * These enable archive pages and better organization.
 */
function yatco_register_taxonomies() {
    // Builder Taxonomy
    register_taxonomy(
        'yacht_builder',
        'yacht',
        array(
            'labels'            => array(
                'name'          => 'Builders',
                'singular_name' => 'Builder',
                'menu_name'     => 'Builders',
            ),
            'public'            => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
            'hierarchical'      => false, // Non-hierarchical (like tags)
            'rewrite'           => array( 'slug' => 'yacht-builder' ),
            'query_var'         => true,
            'show_admin_column' => true, // Show in post list table
        )
    );
    
    // Vessel Type Taxonomy
    register_taxonomy(
        'yacht_vessel_type',
        'yacht',
        array(
            'labels'            => array(
                'name'          => 'Vessel Types',
                'singular_name' => 'Vessel Type',
                'menu_name'     => 'Vessel Types',
            ),
            'public'            => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
            'hierarchical'      => false,
            'rewrite'           => array( 'slug' => 'yacht-type' ),
            'query_var'         => true,
            'show_admin_column' => true, // Show in post list table
        )
    );
    
    // Category Taxonomy
    register_taxonomy(
        'yacht_category',
        'yacht',
        array(
            'labels'            => array(
                'name'          => 'Yacht Categories',
                'singular_name' => 'Yacht Category',
                'menu_name'     => 'Yacht Categories', // Changed from 'Categories' to avoid confusion
            ),
            'public'            => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
            'hierarchical'      => true, // Hierarchical (like categories) - allows sub-categories
            'rewrite'           => array( 'slug' => 'yacht-category' ),
            'query_var'         => true,
            'meta_box_cb'       => 'post_categories_meta_box', // Use standard category meta box style
            'show_admin_column' => true, // Show in post list table
        )
    );
}

// Register taxonomies on init (not just on activation)
add_action( 'init', 'yatco_register_taxonomies', 0 );

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

