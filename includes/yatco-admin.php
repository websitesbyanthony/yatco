<?php
/**
 * Admin Functions
 * 
 * Handles admin pages, settings, and import functionality.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin settings page.
 */
function yatco_add_admin_menu() {
    add_options_page(
        'YATCO API Settings',
        'YATCO API',
        'manage_options',
        'yatco_api',
        'yatco_options_page'
    );
 
    // Import page under Yachts.
    add_submenu_page(
        'edit.php?post_type=yacht',
        'YATCO Import',
        'YATCO Import',
        'manage_options',
        'yatco_import',
        'yatco_import_page'
    );
}

function yatco_settings_init() {
    register_setting( 'yatco_api', 'yatco_api_settings' );

    add_settings_section(
        'yatco_api_section',
        'YATCO API Credentials',
        'yatco_settings_section_callback',
        'yatco_api'
    );

    add_settings_field(
        'yatco_api_token',
        'API Token (Basic)',
        'yatco_api_token_render',
        'yatco_api',
        'yatco_api_section'
    );

    add_settings_field(
        'yatco_cache_duration',
        'Cache Duration (minutes)',
        'yatco_cache_duration_render',
        'yatco_api',
        'yatco_api_section'
    );

    add_settings_field(
        'yatco_auto_refresh_cache',
        'Auto-Refresh Cache',
        'yatco_auto_refresh_cache_render',
        'yatco_api',
        'yatco_api_section'
    );
}

function yatco_settings_section_callback() {
    echo '<p>Enter your YATCO API Basic token. This will be used for search and import.</p>';
}

function yatco_api_token_render() {
    $options = get_option( 'yatco_api_settings' );
    $token   = isset( $options['yatco_api_token'] ) ? $options['yatco_api_token'] : '';
    echo '<input type="text" name="yatco_api_settings[yatco_api_token]" value="' . esc_attr( $token ) . '" size="80" />';
    echo '<p class="description">Paste the Basic token exactly as provided by YATCO (do not re-encode).</p>';
}

function yatco_cache_duration_render() {
    $options = get_option( 'yatco_api_settings' );
    $cache   = isset( $options['yatco_cache_duration'] ) ? intval( $options['yatco_cache_duration'] ) : 30;
    echo '<input type="number" step="1" min="1" name="yatco_api_settings[yatco_cache_duration]" value="' . esc_attr( $cache ) . '" />';
    echo '<p class="description">How long to cache vessel listings before refreshing (default: 30 minutes).</p>';
}

function yatco_auto_refresh_cache_render() {
    $options = get_option( 'yatco_api_settings' );
    $enabled = isset( $options['yatco_auto_refresh_cache'] ) ? $options['yatco_auto_refresh_cache'] : 'no';
    echo '<input type="checkbox" name="yatco_api_settings[yatco_auto_refresh_cache]" value="yes" ' . checked( $enabled, 'yes', false ) . ' />';
    echo '<label>Automatically refresh cache every 6 hours</label>';
    echo '<p class="description">Enable this to automatically pre-load the cache every 6 hours via WP-Cron.</p>';
}

/**
 * Settings page output.
 */
function yatco_options_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $options = get_option( 'yatco_api_settings' );
    $token   = isset( $options['yatco_api_token'] ) ? $options['yatco_api_token'] : '';

    echo '<div class="wrap">';
    echo '<h1>YATCO API Settings</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields( 'yatco_api' );
    do_settings_sections( 'yatco_api' );
    submit_button();
    echo '</form>';

    echo '<hr />';
    echo '<h2>Test API Connection</h2>';
    echo '<p>This test calls the <code>/ForSale/vessel/activevesselmlsid</code> endpoint using your Basic token.</p>';
    echo '<form method="post">';
    wp_nonce_field( 'yatco_test_connection', 'yatco_test_connection_nonce' );
    submit_button( 'Test Connection', 'secondary', 'yatco_test_connection' );
    echo '</form>';

    if ( isset( $_POST['yatco_test_connection'] ) && check_admin_referer( 'yatco_test_connection', 'yatco_test_connection_nonce' ) ) {
        if ( empty( $token ) ) {
            echo '<div class="notice notice-error"><p>Missing token.</p></div>';
        } else {
            $result = yatco_test_connection( $token );
            echo $result;
        }
    }

    echo '<hr />';
    echo '<h2>Test Single Vessel & Create Post</h2>';
    echo '<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 15px 0;">';
    echo '<p style="margin: 0; font-weight: bold; color: #856404;"><strong>📝 Test & Import:</strong> This button fetches the first active vessel from the YATCO API, displays its data structure, <strong>and creates a CPT post</strong> so you can preview how the template looks.</p>';
    echo '</div>';
    echo '<p>This is useful for testing the single yacht template before importing all vessels.</p>';
    echo '<form method="post" id="yatco-test-vessel-form">';
    wp_nonce_field( 'yatco_test_vessel', 'yatco_test_vessel_nonce' );
    // Use a unique button name to avoid conflicts with import buttons
    submit_button( '🔍 Fetch First Vessel & Create Test Post', 'secondary', 'yatco_test_vessel_data_only', false, array( 'id' => 'yatco-test-vessel-btn' ) );
    echo '</form>';

    // Check for test vessel button FIRST - handle it immediately and prevent other handlers
    // This will fetch vessel data AND create a CPT post for testing
    if ( isset( $_POST['yatco_test_vessel_data_only'] ) && ! empty( $_POST['yatco_test_vessel_data_only'] ) ) {
            // Verify nonce using wp_verify_nonce to avoid redirect issues
            if ( ! isset( $_POST['yatco_test_vessel_nonce'] ) || ! wp_verify_nonce( $_POST['yatco_test_vessel_nonce'], 'yatco_test_vessel' ) ) {
                echo '<div class="notice notice-error"><p>Security check failed. Please refresh the page and try again.</p></div>';
            } elseif ( empty( $token ) ) {
            echo '<div class="notice notice-error"><p>Missing token. Please configure your API token first.</p></div>';
        } else {
            echo '<div class="notice notice-info" style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 12px; margin: 15px 0;">';
            echo '<p><strong>🔍 Test Mode - Fetching & Importing First Vessel</strong></p>';
            echo '<p>This will fetch vessel data from the YATCO API, display it below, and <strong>create a CPT post</strong> so you can see how the template renders.</p>';
            echo '</div>';
            
            echo '<div style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin: 20px 0;">';
            echo '<h3>Step 1: Getting Active Vessel IDs (Read Only)</h3>';
            
            // Get first active vessel ID - READ ONLY operation, no import
            // IMPORTANT: This only fetches data - does NOT call yatco_import_single_vessel() or any import functions
            $vessel_ids = yatco_get_active_vessel_ids( $token, 1 ); // Get just 1 ID for testing ONLY - NO IMPORT
            
            if ( is_wp_error( $vessel_ids ) ) {
                echo '<div class="notice notice-error"><p><strong>Error getting vessel IDs:</strong> ' . esc_html( $vessel_ids->get_error_message() ) . '</p></div>';
                echo '</div>';
            } elseif ( empty( $vessel_ids ) || ! is_array( $vessel_ids ) ) {
                echo '<div class="notice notice-error"><p><strong>Error:</strong> No vessel IDs returned. The API response may be empty or invalid.</p></div>';
                echo '</div>';
            } else {
                $first_vessel_id = $vessel_ids[0];
                echo '<p><strong>✅ Success!</strong> Found first vessel ID: <code>' . esc_html( $first_vessel_id ) . '</code></p>';
                
                echo '<h3>Step 2: Fetching FullSpecsAll for Vessel ID ' . esc_html( $first_vessel_id ) . '</h3>';
                
                // Fetch full specs using direct API call to get better error details
                $endpoint = 'https://api.yatcoboss.com/api/v1/ForSale/Vessel/' . intval( $first_vessel_id ) . '/Details/FullSpecsAll';
                echo '<p style="color: #666; font-size: 13px;">Endpoint: <code>' . esc_html( $endpoint ) . '</code></p>';
                
                $response = wp_remote_get(
                    $endpoint,
                    array(
                        'headers' => array(
                            'Authorization' => 'Basic ' . $token,
                            'Accept'        => 'application/json',
                        ),
                        'timeout' => 30,
                    )
                );
                
                if ( is_wp_error( $response ) ) {
                    echo '<div class="notice notice-error"><p><strong>WP_Remote Error:</strong> ' . esc_html( $response->get_error_message() ) . '</p></div>';
                } else {
                    $response_code = wp_remote_retrieve_response_code( $response );
                    $response_body = wp_remote_retrieve_body( $response );
                    
                    echo '<p><strong>Response Code:</strong> ' . esc_html( $response_code ) . '</p>';
                    echo '<p><strong>Content-Type:</strong> ' . esc_html( wp_remote_retrieve_header( $response, 'content-type' ) ) . '</p>';
                    echo '<p><strong>Response Length:</strong> ' . strlen( $response_body ) . ' characters</p>';
                    
                    if ( 200 !== $response_code ) {
                        echo '<div class="notice notice-error">';
                        echo '<p><strong>HTTP Error:</strong> ' . esc_html( $response_code ) . '</p>';
                        echo '<p><strong>Response Body:</strong></p>';
                        echo '<div style="background: #fff; border: 1px solid #ccc; border-radius: 4px; padding: 15px; max-height: 400px; overflow: auto; font-family: monospace; font-size: 12px; white-space: pre-wrap; word-wrap: break-word;">';
                        echo esc_html( substr( $response_body, 0, 2000 ) );
                        if ( strlen( $response_body ) > 2000 ) {
                            echo '<br/><br/><em>... (truncated, total length: ' . strlen( $response_body ) . ' characters)</em>';
                        }
                        echo '</div>';
                        echo '</div>';
                    } else {
                        // Try to decode JSON
                        $fullspecs = json_decode( $response_body, true );
                        $json_error = json_last_error();
                        
                        if ( $json_error !== JSON_ERROR_NONE ) {
                            echo '<div class="notice notice-error">';
                            echo '<p><strong>JSON Parse Error:</strong> ' . esc_html( json_last_error_msg() ) . ' (Error code: ' . $json_error . ')</p>';
                            echo '<p><strong>Raw Response (first 2000 characters):</strong></p>';
                            echo '<div style="background: #fff; border: 1px solid #ccc; border-radius: 4px; padding: 15px; max-height: 400px; overflow: auto; font-family: monospace; font-size: 12px; white-space: pre-wrap; word-wrap: break-word;">';
                            echo esc_html( substr( $response_body, 0, 2000 ) );
                            if ( strlen( $response_body ) > 2000 ) {
                                echo '<br/><br/><em>... (truncated, total length: ' . strlen( $response_body ) . ' characters)</em>';
                            }
                            echo '</div>';
                            
                            // Try to detect if it's HTML (error page)
                            if ( stripos( $response_body, '<html' ) !== false || stripos( $response_body, '<!DOCTYPE' ) !== false ) {
                                echo '<p style="color: #d63638; font-weight: bold;"><strong>⚠️ Warning:</strong> The response appears to be HTML (possibly an error page) rather than JSON.</p>';
                            }
                            
                            // Check if response is empty
                            if ( empty( trim( $response_body ) ) ) {
                                echo '<p style="color: #d63638; font-weight: bold;"><strong>⚠️ Warning:</strong> The response body is empty.</p>';
                            }
                            
                            // Show first 100 chars to help diagnose
                            echo '<p><strong>First 100 characters:</strong></p>';
                            echo '<div style="background: #f9f9f9; border: 1px solid #ddd; padding: 10px; font-family: monospace; font-size: 11px; white-space: pre-wrap; word-wrap: break-word;">';
                            echo esc_html( substr( $response_body, 0, 100 ) );
                            echo '</div>';
                            
                            echo '</div>';
                        } else {
                            echo '<p><strong>✅ Success!</strong> FullSpecsAll data retrieved and parsed successfully.</p>';
                            
                            // Now import the vessel to CPT
                            echo '<h3>Step 3: Importing Vessel to CPT</h3>';
                            echo '<p>Now importing this vessel data into your Custom Post Type...</p>';
                            
                            // Require the helper function
                            require_once YATCO_PLUGIN_DIR . 'includes/yatco-helpers.php';
                            
                            // Import the vessel
                            $import_result = yatco_import_single_vessel( $token, $first_vessel_id );
                            
                            if ( is_wp_error( $import_result ) ) {
                                echo '<div class="notice notice-error">';
                                echo '<p><strong>❌ Import Failed:</strong> ' . esc_html( $import_result->get_error_message() ) . '</p>';
                                echo '</div>';
                            } else {
                                $post_id = $import_result;
                                $post_title = get_the_title( $post_id );
                                $post_permalink = get_permalink( $post_id );
                                
                                echo '<div class="notice notice-success" style="background: #d4edda; border-left: 4px solid #46b450; padding: 15px; margin: 20px 0;">';
                                echo '<p style="font-size: 16px; font-weight: bold; margin: 0 0 10px 0;"><strong>✅ Vessel Imported Successfully!</strong></p>';
                                echo '<p style="margin: 5px 0;"><strong>Post ID:</strong> ' . esc_html( $post_id ) . '</p>';
                                echo '<p style="margin: 5px 0;"><strong>Title:</strong> ' . esc_html( $post_title ) . '</p>';
                                echo '<p style="margin: 15px 0 5px 0;"><strong>View the post:</strong></p>';
                                echo '<p style="margin: 5px 0;">';
                                echo '<a href="' . esc_url( $post_permalink ) . '" target="_blank" class="button button-primary" style="margin-right: 10px;">👁️ View Post (New Tab)</a>';
                                echo '<a href="' . esc_url( admin_url( 'post.php?post=' . $post_id . '&action=edit' ) ) . '" class="button button-secondary">✏️ Edit Post</a>';
                                echo '</p>';
                                echo '</div>';
                                
                                // Show summary of what was imported
                                echo '<h4 style="margin-top: 20px;">Import Summary:</h4>';
                                echo '<div style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 15px;">';
                                echo '<ul style="list-style: disc; margin-left: 20px;">';
                                
                                // Check what meta fields were saved
                                $meta_fields_to_check = array(
                                    'yacht_vessel_id' => 'Vessel ID',
                                    'yacht_year' => 'Year',
                                    'yacht_make' => 'Builder',
                                    'yacht_model' => 'Model',
                                    'yacht_price' => 'Price',
                                    'yacht_length' => 'Length',
                                    'yacht_location_custom_rjc' => 'Location',
                                );
                                
                                foreach ( $meta_fields_to_check as $meta_key => $label ) {
                                    $meta_value = get_post_meta( $post_id, $meta_key, true );
                                    if ( ! empty( $meta_value ) ) {
                                        echo '<li><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $meta_value ) . '</li>';
                                    }
                                }
                                
                                // Check for gallery images
                                $gallery_count = 0;
                                $gallery_urls = get_post_meta( $post_id, 'yacht_image_gallery_urls', true );
                                if ( is_array( $gallery_urls ) ) {
                                    $gallery_count = count( $gallery_urls );
                                }
                                if ( $gallery_count > 0 ) {
                                    echo '<li><strong>Gallery Images:</strong> ' . esc_html( $gallery_count ) . ' images</li>';
                                }
                                
                                echo '</ul>';
                                echo '</div>';
                            }
                            
                            // Still show the raw JSON data for reference
                            echo '<h3 style="margin-top: 30px;">Raw API Response Data Structure</h3>';
                            echo '<p style="color: #666; font-size: 13px;">Below is the complete JSON response from the YATCO API for reference:</p>';
                            
                            // Display formatted JSON
                            echo '<div style="background: #fff; border: 1px solid #ccc; border-radius: 4px; padding: 15px; max-height: 400px; overflow: auto; font-family: monospace; font-size: 11px; line-height: 1.4;">';
                            echo '<pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word;">';
                            echo esc_html( wp_json_encode( $fullspecs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
                            echo '</pre>';
                            echo '</div>';
                        }
                    }
                }
                echo '</div>';
            }
        }
    }

    echo '<hr />';
    echo '<h2>CPT Import Management</h2>';
    echo '<p>Import all vessels into the Yacht Custom Post Type (CPT) for faster queries, better SEO, and individual vessel pages. This may take several minutes for 7000+ vessels.</p>';
    echo '<p><strong>Benefits of CPT import:</strong> Better performance with WP_Query, individual pages per vessel, improved SEO, easier management via WordPress admin.</p>';
    
    // Pre-check status for button display
    $pre_cache_status = get_transient( 'yatco_cache_warming_status' );
    $pre_cache_progress = get_transient( 'yatco_cache_warming_progress' );
    $pre_is_warming_scheduled = wp_next_scheduled( 'yatco_warm_cache_hook' );
    
    // SIMPLIFIED LOGIC: Buttons default to ENABLED - only disable if import is actively running RIGHT NOW
    // Check for very recent progress (within last 30 seconds) to determine if import is active
    $pre_button_disabled = false;
    
    if ( $pre_cache_progress !== false && is_array( $pre_cache_progress ) && ! empty( $pre_cache_progress ) ) {
        $pre_last_processed = isset( $pre_cache_progress['last_processed'] ) ? intval( $pre_cache_progress['last_processed'] ) : 0;
        $pre_total = isset( $pre_cache_progress['total'] ) ? intval( $pre_cache_progress['total'] ) : 0;
        $pre_timestamp = isset( $pre_cache_progress['timestamp'] ) ? intval( $pre_cache_progress['timestamp'] ) : 0;
        
        // Only disable if:
        // 1. Progress shows incomplete import (last_processed < total)
        // 2. AND progress was updated in last 30 seconds (truly active)
        if ( $pre_total > 0 && $pre_last_processed < $pre_total ) {
            if ( $pre_timestamp > 0 && ( time() - $pre_timestamp ) < 30 ) {
                $pre_button_disabled = true;
            } else {
                // Progress is stale (>30 seconds old) - auto-clear it
                if ( $pre_timestamp > 0 && ( time() - $pre_timestamp ) > 60 ) {
                    delete_transient( 'yatco_cache_warming_progress' );
                    delete_transient( 'yatco_cache_warming_status' );
                    $pre_cache_progress = false;
                    $pre_cache_status = false;
                    $pre_button_disabled = false;
                }
            }
        }
    }
    
    // Show import button at the top (prominently) before the "How Updates Work" section
    echo '<div style="background: #fff; border: 2px solid #2271b1; border-radius: 4px; padding: 20px; margin: 20px 0;">';
    echo '<h3 style="margin-top: 0; color: #2271b1;">📥 Import Vessels to CPT</h3>';
    
    // Show buttons side by side
    echo '<div style="display: flex; gap: 15px; align-items: center; margin: 15px 0; flex-wrap: wrap;">';
    
    // Import button
    echo '<form method="post" style="margin: 0;">';
    wp_nonce_field( 'yatco_warm_cache', 'yatco_warm_cache_nonce' );
    submit_button( 'Import All Vessels to CPT', 'primary', 'yatco_warm_cache', false, array( 
        'disabled' => $pre_button_disabled,
        'style' => 'font-size: 16px; padding: 10px 20px; height: auto;'
    ) );
    echo '</form>';
    
    // ALWAYS show stop/clear button when import button is disabled OR if there's any status/progress
    // This ensures users can always clear stuck states and recover
    $should_show_stop = ( $pre_button_disabled || $pre_cache_status !== false || $pre_cache_progress !== false || $pre_is_warming_scheduled );
    
    if ( $should_show_stop ) {
        echo '<form method="post" style="margin: 0;">';
        wp_nonce_field( 'yatco_clear_all', 'yatco_clear_all_nonce' );
        submit_button( '🛑 Stop & Clear All', 'secondary', 'yatco_clear_all', false, array( 
            'style' => 'background: #dc3232; border-color: #dc3232; color: #fff; font-weight: bold; font-size: 16px; padding: 10px 20px; height: auto;'
        ) );
        echo '</form>';
    }
    
    // If button is disabled but stop button didn't show, show it anyway (safety net)
    if ( $pre_button_disabled && ! $should_show_stop ) {
        echo '<form method="post" style="margin: 0;">';
        wp_nonce_field( 'yatco_clear_all', 'yatco_clear_all_nonce' );
        submit_button( '🛑 Stop & Clear All', 'secondary', 'yatco_clear_all', false, array( 
            'style' => 'background: #dc3232; border-color: #dc3232; color: #fff; font-weight: bold; font-size: 16px; padding: 10px 20px; height: auto;'
        ) );
        echo '</form>';
    }
    
    // Show warning message if buttons are disabled
    if ( $pre_button_disabled ) {
        echo '<p style="color: #d63638; font-size: 12px; margin: 5px 0 0 0; font-weight: bold;">⚠️ Buttons are disabled because import is running. Click "Stop & Clear All" above to cancel.</p>';
    } elseif ( $pre_cache_status !== false || $pre_cache_progress !== false || $pre_is_warming_scheduled ) {
        echo '<p style="color: #dc3232; font-size: 12px; margin: 5px 0 0 0; font-weight: bold;">⚠️ Status/Progress detected. If buttons are stuck disabled, click "Stop & Clear All" above to reset.</p>';
    }
    
    echo '</div>';
    
    if ( $pre_button_disabled ) {
        echo '<p style="color: #d63638; font-weight: bold; margin-top: 10px;">⚠️ Import is currently running. Use "Stop & Clear All" button above to cancel. Progress is shown below.</p>';
    } else {
        echo '<p style="color: #666; font-size: 13px; margin-top: 10px;">Click the button above to import all active vessels from YATCO API into your WordPress Custom Post Type.</p>';
    }
    
    // Show what's causing buttons to be disabled
    if ( $pre_cache_status !== false || $pre_cache_progress !== false || $pre_is_warming_scheduled ) {
        echo '<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin-top: 10px; font-size: 12px;">';
        echo '<strong>Debug Info:</strong> ';
        $debug_items = array();
        if ( $pre_cache_status !== false ) $debug_items[] = 'Status: ' . esc_html( substr( $pre_cache_status, 0, 60 ) );
        if ( $pre_cache_progress !== false ) {
            $last = isset( $pre_cache_progress['last_processed'] ) ? $pre_cache_progress['last_processed'] : 0;
            $total = isset( $pre_cache_progress['total'] ) ? $pre_cache_progress['total'] : 0;
            $debug_items[] = "Progress: {$last}/{$total}";
        }
        if ( $pre_is_warming_scheduled ) $debug_items[] = 'Scheduled event found';
        echo implode( ' | ', $debug_items );
        echo '</div>';
    }
    
    echo '</div>';
    
    echo '<div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 15px; margin: 15px 0;">';
    echo '<h3 style="margin-top: 0;">🔄 How Updates Work</h3>';
    echo '<p style="margin: 5px 0;"><strong>Automatic Updates:</strong> When you run "Import All Vessels to CPT" or enable auto-refresh, the system:</p>';
    echo '<ol style="margin: 5px 0 0 20px; padding-left: 20px;">';
    echo '<li><strong>Fetches all active vessel IDs</strong> from the YATCO API</li>';
    echo '<li><strong>Matches existing CPT posts</strong> using two methods:';
    echo '<ul style="margin: 5px 0;">';
    echo '<li><strong>Primary:</strong> Matches by MLSID (yacht_mlsid meta field)</li>';
    echo '<li><strong>Fallback:</strong> Matches by VesselID (yacht_vessel_id meta field) if MLSID is missing or changed</li>';
    echo '</ul>';
    echo '</li>';
    echo '<li><strong>Updates existing posts</strong> with latest API data (price, details, images, etc.)</li>';
    echo '<li><strong>Creates new posts</strong> for vessels that don\'t exist in CPT yet</li>';
    echo '<li><strong>Maintains post IDs</strong> so permalinks stay the same for existing vessels</li>';
    echo '<li><strong>Updates timestamp</strong> (yacht_last_updated) on every import</li>';
    echo '</ol>';
    echo '<p style="margin: 10px 0 0 0; font-size: 12px; color: #666;"><strong>Note:</strong> All metadata fields are updated with the latest data from YATCO API each time the import runs. This ensures your CPT data stays synchronized with YATCO.</p>';
    echo '</div>';
    
    // Check if import is running
    $cache_status = get_transient( 'yatco_cache_warming_status' );
    $cache_progress = get_transient( 'yatco_cache_warming_progress' );
    $is_warming_scheduled = wp_next_scheduled( 'yatco_warm_cache_hook' );
    $is_auto_refresh_scheduled = wp_next_scheduled( 'yatco_auto_refresh_cache_hook' );
    
    // Only consider running if there's actual active progress or scheduled event
    // Check for meaningful progress data (active import happening)
    // Status messages can persist after completion, so we check progress data instead
    $has_active_progress = false;
    if ( $cache_progress !== false && is_array( $cache_progress ) && ! empty( $cache_progress ) ) {
        $last_processed = isset( $cache_progress['last_processed'] ) ? intval( $cache_progress['last_processed'] ) : 0;
        $total = isset( $cache_progress['total'] ) ? intval( $cache_progress['total'] ) : 0;
        // Only consider active if there's progress data AND not completed
        $has_active_progress = ( $total > 0 && $last_processed < $total );
    }
    
    // Check if status indicates active processing (not just completion message)
    $has_active_status = false;
    if ( $cache_status !== false && ! empty( $cache_status ) && $cache_status !== 'not_run' ) {
        $status_lower = strtolower( $cache_status );
        // Only consider active if status contains indicators of active processing
        $active_indicators = array( 'starting', 'processing', 'fetching', 'vessel', 'warming', 'import' );
        $completed_indicators = array( 'completed', 'success', 'successfully', 'finished', 'done' );
        
        // Check for active indicators
        foreach ( $active_indicators as $indicator ) {
            if ( strpos( $status_lower, $indicator ) !== false ) {
                // Make sure it's not a completion message
                $is_completed = false;
                foreach ( $completed_indicators as $completed ) {
                    if ( strpos( $status_lower, $completed ) !== false ) {
                        $is_completed = true;
                        break;
                    }
                }
                if ( ! $is_completed ) {
                    $has_active_status = true;
                    break;
                }
            }
        }
    }
    
    // Only disable button if there's actually an active import, not just any scheduled event
    // Scheduled auto-refresh shouldn't disable the manual import button
    $is_running = $has_active_status || $has_active_progress || ( $is_warming_scheduled && $is_warming_scheduled > time() );
    
    // Handle stop import action
    if ( isset( $_POST['yatco_stop_import'] ) && check_admin_referer( 'yatco_stop_import', 'yatco_stop_import_nonce' ) ) {
        // Clear scheduled events
        $scheduled = wp_next_scheduled( 'yatco_warm_cache_hook' );
        if ( $scheduled ) {
            wp_unschedule_event( $scheduled, 'yatco_warm_cache_hook' );
        }
        wp_clear_scheduled_hook( 'yatco_warm_cache_hook' );
        
        // Clear progress and status
        delete_transient( 'yatco_cache_warming_progress' );
        delete_transient( 'yatco_cache_warming_status' );
        
        echo '<div class="notice notice-success"><p><strong>Import stopped!</strong> All scheduled events have been cleared and progress has been reset.</p></div>';
        $is_running = false;
        $has_active_status = false;
        $has_active_progress = false;
        $button_disabled = false;
    }
    
    // Handle clear all action (for stuck states)
    if ( isset( $_POST['yatco_clear_all'] ) && check_admin_referer( 'yatco_clear_all', 'yatco_clear_all_nonce' ) ) {
        // Clear all scheduled events
        wp_clear_scheduled_hook( 'yatco_warm_cache_hook' );
        wp_clear_scheduled_hook( 'yatco_auto_refresh_cache_hook' );
        
        // Clear all transients
        delete_transient( 'yatco_cache_warming_progress' );
        delete_transient( 'yatco_cache_warming_status' );
        
        echo '<div class="notice notice-success"><p><strong>All cleared!</strong> All scheduled events and progress data have been cleared. The import button should now be enabled.</p></div>';
        
        // Reset variables
        $cache_status = false;
        $cache_progress = false;
        $is_warming_scheduled = false;
        $has_active_status = false;
        $has_active_progress = false;
        $is_running = false;
        $button_disabled = false;
    }
    
    // REMOVED: Second import button - only one button needed at the top
    
    if ( $is_running ) {
        echo '<form method="post" style="margin-bottom: 15px;">';
        wp_nonce_field( 'yatco_stop_import', 'yatco_stop_import_nonce' );
        submit_button( '🛑 Stop Import', 'secondary', 'yatco_stop_import', false, array( 'style' => 'background: #dc3232; border-color: #dc3232; color: #fff;' ) );
        echo '<p style="font-size: 12px; color: #666; margin: 5px 0;">This will cancel the scheduled import and clear all progress. Vessels already imported to CPT will remain.</p>';
        echo '</form>';
    }
    
    // Diagnostic/Troubleshooting Section
    echo '<hr />';
    echo '<h2>🔧 Troubleshooting & Diagnostics</h2>';
    echo '<div style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin: 20px 0;">';
    
    // WP-Cron Check
    $cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
    echo '<h3>WP-Cron Status</h3>';
    echo '<table class="widefat" style="margin-bottom: 20px;">';
    echo '<tr><th style="text-align: left; width: 250px;">Check</th><th style="text-align: left;">Status</th></tr>';
    
    echo '<tr><td><strong>WP-Cron Enabled:</strong></td><td>';
    if ( $cron_disabled ) {
        echo '<span style="color: #dc3232;">❌ DISABLED</span> - WP-Cron is disabled via DISABLE_WP_CRON constant.';
        echo '<p style="margin: 5px 0; font-size: 12px; color: #666;">Your server likely uses real cron. You\'ll need to use "Run Directly" button or set up a real cron job.</p>';
    } else {
        echo '<span style="color: #46b450;">✅ ENABLED</span>';
    }
    echo '</td></tr>';
    
    // Check if spawn_cron works
    echo '<tr><td><strong>spawn_cron() Available:</strong></td><td>';
    if ( function_exists( 'spawn_cron' ) ) {
        echo '<span style="color: #46b450;">✅ Available</span>';
    } else {
        echo '<span style="color: #dc3232;">❌ Not Available</span>';
    }
    echo '</td></tr>';
    
    // Check if function exists
    echo '<tr><td><strong>Cache Warming Function:</strong></td><td>';
    if ( function_exists( 'yatco_warm_cache_function' ) ) {
        echo '<span style="color: #46b450;">✅ Available</span>';
    } else {
        echo '<span style="color: #dc3232;">❌ Not Found</span>';
    }
    echo '</td></tr>';
    
    // Check if hook is registered
    echo '<tr><td><strong>Hook Registered:</strong></td><td>';
    global $wp_filter;
    if ( isset( $wp_filter['yatco_warm_cache_hook'] ) ) {
        echo '<span style="color: #46b450;">✅ Registered</span>';
    } else {
        echo '<span style="color: #dc3232;">❌ Not Registered</span>';
    }
    echo '</td></tr>';
    
    // Check scheduled events
    $scheduled = wp_next_scheduled( 'yatco_warm_cache_hook' );
    echo '<tr><td><strong>Scheduled Events:</strong></td><td>';
    if ( $scheduled ) {
        $time_until = $scheduled - time();
        echo '<span style="color: #0073aa;">⏰ Scheduled</span> - Next run: ' . human_time_diff( time(), $scheduled );
        if ( $time_until < 0 ) {
            echo ' <span style="color: #dc3232;">(OVERDUE - Should have run ' . abs( $time_until ) . ' seconds ago)</span>';
        }
    } else {
        echo '<span style="color: #666;">None scheduled</span>';
    }
    echo '</td></tr>';
    
    // Check recent status
    $last_status = get_transient( 'yatco_cache_warming_status' );
    echo '<tr><td><strong>Last Status:</strong></td><td>';
    if ( $last_status ) {
        echo esc_html( $last_status );
    } else {
        echo '<span style="color: #666;">No status recorded</span>';
    }
    echo '</td></tr>';
    
    // Check recent progress
    $last_progress = get_transient( 'yatco_cache_warming_progress' );
    echo '<tr><td><strong>Last Progress:</strong></td><td>';
    if ( $last_progress && is_array( $last_progress ) ) {
        $processed = isset( $last_progress['last_processed'] ) ? intval( $last_progress['last_processed'] ) : 0;
        $total = isset( $last_progress['total'] ) ? intval( $last_progress['total'] ) : 0;
        $timestamp = isset( $last_progress['timestamp'] ) ? intval( $last_progress['timestamp'] ) : 0;
        if ( $total > 0 ) {
            $percent = round( ( $processed / $total ) * 100, 1 );
            echo number_format( $processed ) . ' / ' . number_format( $total ) . ' (' . $percent . '%)';
            if ( $timestamp > 0 ) {
                echo ' - ' . human_time_diff( $timestamp, time() ) . ' ago';
            }
        } else {
            echo 'Progress recorded but no data';
        }
    } else {
        echo '<span style="color: #666;">No progress recorded</span>';
    }
    echo '</td></tr>';
    
    echo '</table>';
    
    // Test button
    echo '<h3>Manual Testing</h3>';
    echo '<p>Use these buttons to test if the system is working:</p>';
    
    // Test function directly
    if ( isset( $_POST['yatco_test_function'] ) && check_admin_referer( 'yatco_test_function', 'yatco_test_function_nonce' ) ) {
        echo '<div style="background: #fff; border-left: 4px solid #0073aa; padding: 10px; margin: 10px 0;">';
        echo '<strong>Testing Function:</strong><br />';
        
        if ( ! function_exists( 'yatco_warm_cache_function' ) ) {
            echo '<span style="color: #dc3232;">❌ Function not found!</span>';
        } else {
            echo '<span style="color: #46b450;">✅ Function exists</span><br />';
            
            // Test token
            $test_token = yatco_get_token();
            if ( empty( $test_token ) ) {
                echo '<span style="color: #dc3232;">❌ API token not configured</span><br />';
            } else {
                echo '<span style="color: #46b450;">✅ API token configured</span><br />';
                
                // Test API call
                if ( function_exists( 'yatco_get_active_vessel_ids' ) ) {
                    echo 'Testing API connection...<br />';
                    $test_ids = yatco_get_active_vessel_ids( $test_token, 5 );
                    if ( is_wp_error( $test_ids ) ) {
                        echo '<span style="color: #dc3232;">❌ API Error: ' . esc_html( $test_ids->get_error_message() ) . '</span><br />';
                    } elseif ( is_array( $test_ids ) ) {
                        echo '<span style="color: #46b450;">✅ API working - Found ' . count( $test_ids ) . ' vessel IDs</span><br />';
                    }
                }
            }
        }
        echo '</div>';
    }
    
    echo '<form method="post" style="margin: 10px 0;">';
    wp_nonce_field( 'yatco_test_function', 'yatco_test_function_nonce' );
    submit_button( 'Test Function & API Connection', 'secondary', 'yatco_test_function' );
    echo '</form>';
    
    // Test WP-Cron
    if ( isset( $_POST['yatco_test_cron'] ) && check_admin_referer( 'yatco_test_cron', 'yatco_test_cron_nonce' ) ) {
        echo '<div style="background: #fff; border-left: 4px solid #0073aa; padding: 10px; margin: 10px 0;">';
        echo '<strong>Testing WP-Cron:</strong><br />';
        
        // Create a test transient
        $test_key = 'yatco_cron_test_' . time();
        set_transient( $test_key, 'not_run', 60 );
        
        // Schedule a test event
        $test_hook = 'yatco_test_cron_hook';
        wp_schedule_single_event( time(), $test_hook );
        
        // Add test action
        add_action( $test_hook, function() use ( $test_key ) {
            set_transient( $test_key, 'ran_successfully', 60 );
        } );
        
        echo '✅ Test event scheduled<br />';
        
        // Try to spawn cron
        if ( function_exists( 'spawn_cron' ) ) {
            echo '🔄 Attempting to trigger WP-Cron...<br />';
            spawn_cron();
            echo '✅ spawn_cron() called<br />';
        } else {
            echo '❌ spawn_cron() not available<br />';
        }
        
        // Also try wp-cron.php directly
        echo '🔄 Attempting to trigger wp-cron.php directly...<br />';
        $response = wp_remote_post(
            site_url( 'wp-cron.php?doing_wp_cron' ),
            array(
                'timeout'   => 0.01,
                'blocking'  => false,
                'sslverify' => false,
            )
        );
        
        if ( is_wp_error( $response ) ) {
            echo '⚠️ wp-cron.php request failed: ' . esc_html( $response->get_error_message() ) . '<br />';
        } else {
            echo '✅ wp-cron.php request sent (non-blocking)<br />';
        }
        
        // Wait and check multiple times (since wp-cron.php is non-blocking, it may take a moment)
        echo '<br />⏳ Checking if cron ran (waiting up to 8 seconds)...<br />';
        $test_result = false;
        for ( $i = 0; $i < 8; $i++ ) {
            sleep( 1 );
            $check = get_transient( $test_key );
            if ( $check === 'ran_successfully' ) {
                $test_result = true;
                echo '✓ Checked at ' . ( $i + 1 ) . ' seconds: <span style="color: #46b450;">CRON RAN!</span><br />';
                break;
            } elseif ( $check !== 'not_run' ) {
                echo '⚠️ Checked at ' . ( $i + 1 ) . ' seconds: Transient changed but value unexpected: ' . esc_html( $check ) . '<br />';
                break;
            } else {
                echo '• Checked at ' . ( $i + 1 ) . ' seconds: Still waiting...<br />';
            }
        }
        
        echo '<br />';
        if ( $test_result === true ) {
            echo '<span style="color: #46b450; font-weight: bold; font-size: 14px;">✅ SUCCESS! WP-Cron is working! The test hook ran successfully.</span><br />';
            echo '<p style="margin: 5px 0; color: #666; font-size: 12px;">Your WP-Cron is functioning correctly. The cache warming should work with the "Warm Cache" button.</p>';
        } elseif ( $test_result === false ) {
            $final_check = get_transient( $test_key );
            if ( $final_check === 'not_run' ) {
                echo '<span style="color: #dc3232; font-weight: bold; font-size: 14px;">❌ FAILED! WP-Cron did not run. The test hook did not execute.</span><br />';
                echo '<p style="margin: 5px 0; color: #666; font-size: 12px;"><strong>This means WP-Cron is likely disabled or not working on your server.</strong> You should use the "Run Directly" button or "Run Cache Warming Function NOW (Direct)" instead.</p>';
                if ( $cron_disabled ) {
                    echo '<p style="margin: 5px 0; color: #dc3232; font-size: 12px;"><strong>Note:</strong> DISABLE_WP_CRON is set in your wp-config.php. Your server likely uses real cron instead.</p>';
                }
                
                // Show real cron setup instructions
                echo '<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0;">';
                echo '<h4 style="margin-top: 0;">🔧 Setting Up Real Cron Job (Recommended for Auto-Refresh)</h4>';
                echo '<p style="margin: 5px 0;"><strong>Since WP-Cron is disabled, you can set up a real cron job to run cache warming automatically.</strong></p>';
                echo '<p style="margin: 10px 0 5px 0;"><strong>Option 1: Run WP-Cron via Real Cron (Recommended)</strong></p>';
                $wp_cron_url = site_url( 'wp-cron.php' );
                echo '<p style="margin: 5px 0; font-family: monospace; background: #f5f5f5; padding: 10px; border-radius: 4px;">';
                echo '*/15 * * * * curl -s "' . esc_html( $wp_cron_url ) . '" > /dev/null 2>&1';
                echo '</p>';
                echo '<p style="margin: 5px 0 15px 0; font-size: 12px; color: #666;">This runs WP-Cron every 15 minutes, which will trigger scheduled cache refreshes.</p>';
                
                echo '<p style="margin: 10px 0 5px 0;"><strong>Option 2: Run Cache Warming Directly via WP-CLI</strong></p>';
                $wp_path = ABSPATH;
                echo '<p style="margin: 5px 0; font-family: monospace; background: #f5f5f5; padding: 10px; border-radius: 4px;">';
                echo '0 */6 * * * cd ' . esc_html( $wp_path ) . ' && wp eval "do_action(\'yatco_auto_refresh_cache_hook\');"';
                echo '</p>';
                echo '<p style="margin: 5px 0 15px 0; font-size: 12px; color: #666;">This runs cache warming every 6 hours directly (requires WP-CLI).</p>';
                
                echo '<p style="margin: 10px 0 5px 0;"><strong>How to Add Cron Job:</strong></p>';
                echo '<ol style="margin: 5px 0 0 20px; font-size: 12px;">';
                echo '<li>SSH into your server</li>';
                echo '<li>Run: <code>crontab -e</code></li>';
                echo '<li>Add one of the cron commands above</li>';
                echo '<li>Save and exit (usually Ctrl+X, then Y, then Enter)</li>';
                echo '</ol>';
                echo '<p style="margin: 10px 0 0 0; font-size: 12px; color: #666;"><em>Note: If you don\'t have SSH access, contact your hosting provider to set up the cron job, or use the "Run Directly" button for manual cache warming.</em></p>';
                echo '</div>';
            } else {
                echo '<span style="color: #ffb900; font-weight: bold; font-size: 14px;">⚠️ UNKNOWN - Could not determine if cron ran</span><br />';
                echo '<p style="margin: 5px 0; color: #666; font-size: 12px;">Final transient value: ' . esc_html( $final_check ) . '</p>';
            }
        }
        
        // Clean up
        delete_transient( $test_key );
        wp_clear_scheduled_hook( $test_hook );
        
        echo '</div>';
    }
    
    echo '<form method="post" style="margin: 10px 0;">';
    wp_nonce_field( 'yatco_test_cron', 'yatco_test_cron_nonce' );
    submit_button( 'Test WP-Cron Trigger', 'secondary', 'yatco_test_cron' );
    echo '<p style="font-size: 12px; color: #666; margin: 5px 0;">This will schedule a test event and try to trigger WP-Cron to see if it works on your server.</p>';
    echo '</form>';
    
    // Manual trigger button
    echo '<div style="display: flex; gap: 10px; align-items: flex-start; margin: 10px 0;">';
    echo '<form method="post" style="margin: 0;">';
    wp_nonce_field( 'yatco_manual_trigger', 'yatco_manual_trigger_nonce' );
    echo '<button type="submit" name="yatco_manual_trigger" class="button button-primary">Run Cache Warming Function NOW (Direct)</button>';
    echo '</form>';
    
    // Show stop button if import is running
    if ( $is_running ) {
        echo '<form method="post" style="margin: 0;">';
        wp_nonce_field( 'yatco_stop_import', 'yatco_stop_import_nonce' );
        echo '<button type="submit" name="yatco_stop_import" class="button button-secondary" style="background: #dc3232; border-color: #dc3232; color: #fff; font-weight: bold;">🛑 Stop Import</button>';
        echo '</form>';
    }
    echo '</div>';
    echo '<p style="font-size: 12px; color: #666; margin: 5px 0;">This will run the function directly on this page (may timeout for large datasets).';
    if ( $is_running ) {
        echo ' <strong style="color: #dc3232;">⚠️ Import is currently running - use Stop button to cancel.</strong>';
    }
    echo '</p>';
    
    // Handle manual trigger
    if ( isset( $_POST['yatco_manual_trigger'] ) && check_admin_referer( 'yatco_manual_trigger', 'yatco_manual_trigger_nonce' ) ) {
        if ( ! function_exists( 'yatco_warm_cache_function' ) ) {
            echo '<div class="notice notice-error"><p>Error: Function not found!</p></div>';
        } else {
            // Set initial status BEFORE running so progress section shows immediately
            set_transient( 'yatco_cache_warming_status', 'Starting cache warm-up...', 600 );
            
            echo '<div class="notice notice-info"><p><strong>Starting cache warming directly...</strong> This may take several minutes. Progress will be saved every 20 vessels. See progress below.</p></div>';
            
            // Increase limits
            @ini_set( 'max_execution_time', 300 );
            @ini_set( 'memory_limit', '512M' );
            @set_time_limit( 300 );
            
            // Run the function (it will update progress as it runs)
            yatco_warm_cache_function();
            
            // Check if it completed or timed out
            $final_status = get_transient( 'yatco_cache_warming_status' );
            $final_progress = get_transient( 'yatco_cache_warming_progress' );
            
            if ( $final_progress && is_array( $final_progress ) ) {
                $processed = isset( $final_progress['last_processed'] ) ? intval( $final_progress['last_processed'] ) : 0;
                $total = isset( $final_progress['total'] ) ? intval( $final_progress['total'] ) : 0;
                if ( $processed >= $total && $total > 0 ) {
                    echo '<div class="notice notice-success"><p><strong>Function completed!</strong> All ' . number_format( $total ) . ' vessels have been processed.</p></div>';
                } else {
                    echo '<div class="notice notice-warning"><p><strong>Function may have timed out, but progress was saved!</strong> Processed ' . number_format( $processed ) . ' of ' . number_format( $total ) . ' vessels. Click "Run Cache Warming Function NOW (Direct)" again to continue from where it left off.</p></div>';
                }
            } else {
                echo '<div class="notice notice-success"><p><strong>Function completed!</strong> Check the status below to see progress.</p></div>';
            }
        }
        
        // Refresh status variables after manual trigger so stop button appears
        $cache_status = get_transient( 'yatco_cache_warming_status' );
        $cache_progress = get_transient( 'yatco_cache_warming_progress' );
        $is_warming_scheduled = wp_next_scheduled( 'yatco_warm_cache_hook' );
        
        // Use same logic as above to determine if running
        $has_active_progress = false;
        if ( $cache_progress !== false && is_array( $cache_progress ) && ! empty( $cache_progress ) ) {
            $last_processed = isset( $cache_progress['last_processed'] ) ? intval( $cache_progress['last_processed'] ) : 0;
            $total = isset( $cache_progress['total'] ) ? intval( $cache_progress['total'] ) : 0;
            $has_active_progress = ( $total > 0 && $last_processed < $total );
        }
        
        $has_active_status = false;
        if ( $cache_status !== false && ! empty( $cache_status ) && $cache_status !== 'not_run' ) {
            $status_lower = strtolower( $cache_status );
            $active_indicators = array( 'starting', 'processing', 'fetching', 'vessel', 'warming', 'import' );
            $completed_indicators = array( 'completed', 'success', 'successfully', 'finished', 'done' );
            
            foreach ( $active_indicators as $indicator ) {
                if ( strpos( $status_lower, $indicator ) !== false ) {
                    $is_completed = false;
                    foreach ( $completed_indicators as $completed ) {
                        if ( strpos( $status_lower, $completed ) !== false ) {
                            $is_completed = true;
                            break;
                        }
                    }
                    if ( ! $is_completed ) {
                        $has_active_status = true;
                        break;
                    }
                }
            }
        }
        
        $is_running = $has_active_status || $has_active_progress || $is_warming_scheduled;
        
        // Re-display stop button if running
        if ( $is_running ) {
            echo '<div style="margin: 15px 0;">';
            echo '<form method="post" style="display: inline-block;">';
            wp_nonce_field( 'yatco_stop_import', 'yatco_stop_import_nonce' );
            echo '<button type="submit" name="yatco_stop_import" class="button button-secondary" style="background: #dc3232; border-color: #dc3232; color: #fff; font-weight: bold; padding: 8px 16px;">🛑 Stop Import Now</button>';
            echo '</form>';
            echo '<p style="font-size: 12px; color: #666; margin: 5px 0;">Click to stop the import and clear progress. Vessels already imported will remain in CPT.</p>';
            echo '</div>';
        }
    }
    
    // Troubleshooting guide
    echo '<h3>📋 Troubleshooting Guide</h3>';
    echo '<div style="background: #fff; border-left: 4px solid #0073aa; padding: 15px; margin: 10px 0;">';
    echo '<ol style="margin: 0; padding-left: 20px;">';
    echo '<li><strong>If WP-Cron is disabled (most common):</strong>';
    echo '<ul style="margin: 5px 0 10px 20px; padding-left: 20px;">';
    echo '<li><strong>For one-time imports:</strong> Use the "Run Cache Warming Function NOW (Direct)" button above</li>';
    echo '<li><strong>For automatic refreshes:</strong> Set up a real cron job (see instructions above after running the WP-Cron test)</li>';
    echo '<li><strong>Alternative:</strong> Contact your hosting provider to enable WP-Cron or set up a cron job for you</li>';
    echo '</ul>';
    echo '</li>';
    echo '<li><strong>If function not found:</strong> Make sure all plugin files are properly uploaded and the plugin is activated</li>';
    echo '<li><strong>If API token not configured:</strong> Go to Settings → YATCO API and enter your token</li>';
    echo '<li><strong>If scheduled events are overdue:</strong> WP-Cron may not be running. Try "Run Directly" or check server cron settings</li>';
    echo '<li><strong>If progress doesn\'t appear:</strong> Check that the progress transients are being saved (see Last Progress above)</li>';
    echo '<li><strong>For large datasets:</strong> The function processes in batches of 20. If it times out, progress is saved and it will resume on the next run</li>';
    echo '</ol>';
    echo '</div>';
    
    echo '</div>';

    // Handle warm cache action
    if ( isset( $_POST['yatco_warm_cache'] ) && check_admin_referer( 'yatco_warm_cache', 'yatco_warm_cache_nonce' ) ) {
        if ( empty( $token ) ) {
            echo '<div class="notice notice-error"><p>Missing token. Please configure your API token first.</p></div>';
        } else {
            // Clear any existing progress to start fresh
            delete_transient( 'yatco_cache_warming_progress' );
            delete_transient( 'yatco_cache_warming_status' );
            
            // Set initial status immediately so user sees feedback
            if ( function_exists( 'set_transient' ) ) {
                set_transient( 'yatco_cache_warming_status', 'Starting cache warm-up...', 600 );
            }
            
            // Trigger async cache warming via WP-Cron
            wp_schedule_single_event( time(), 'yatco_warm_cache_hook' );
            
            // Also trigger immediately in background (non-blocking)
            spawn_cron();
            
            // Try to trigger directly via HTTP request (non-blocking)
            wp_remote_post(
                admin_url( 'admin-ajax.php' ),
                array(
                    'timeout'   => 0.01,
                    'blocking'  => false,
                    'sslverify' => false,
                    'body'      => array(
                        'action' => 'yatco_trigger_cache_warming',
                        'nonce'  => wp_create_nonce( 'yatco_trigger_warming' ),
                    ),
                )
            );
            
            // Get current CPT count
            $cpt_count = wp_count_posts( 'yacht' );
            $published_count = isset( $cpt_count->publish ) ? intval( $cpt_count->publish ) : 0;
            
            echo '<div class="notice notice-info"><p><strong>CPT import started!</strong> This will run in the background and may take several minutes for 7000+ vessels.</p>';
            echo '<p>Current yacht posts in CPT: <strong>' . number_format( $published_count ) . '</strong></p>';
            echo '<p>The system processes vessels in batches of 20 to prevent timeouts. Progress is saved automatically, so if interrupted, it will resume from where it left off.</p>';
            echo '<p><em>Note: If progress doesn\'t appear within 30 seconds, try clicking "Import All Vessels to CPT" again or check if WP-Cron is enabled on your server.</em></p></div>';
        }
    }
    
    // Handle clear transient cache action (CPT posts remain)
    echo '<form method="post" style="margin-top: 10px;">';
    wp_nonce_field( 'yatco_clear_cache', 'yatco_clear_cache_nonce' );
    submit_button( 'Clear Transient Cache Only', 'secondary', 'yatco_clear_cache' );
    echo '<p style="font-size: 12px; color: #666; margin: 5px 0;">This clears only transient caches. CPT posts remain unchanged.</p>';
    echo '</form>';
    
    if ( isset( $_POST['yatco_clear_cache'] ) && check_admin_referer( 'yatco_clear_cache', 'yatco_clear_cache_nonce' ) ) {
        delete_transient( 'yatco_vessels_data' );
        delete_transient( 'yatco_vessels_builders' );
        delete_transient( 'yatco_vessels_categories' );
        delete_transient( 'yatco_vessels_types' );
        delete_transient( 'yatco_vessels_conditions' );
        delete_transient( 'yatco_cache_warming_progress' );
        delete_transient( 'yatco_vessels_processing_progress' );
        
        // Clear all cached vessel outputs
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_yatco_vessels_%'" );
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_yatco_vessels_%'" );
        
        echo '<div class="notice notice-success"><p>Transient cache cleared successfully! (CPT posts remain unchanged)</p></div>';
    }

    // Check if CPT import is in progress
    $cache_status = get_transient( 'yatco_cache_warming_status' );
    $cache_progress = get_transient( 'yatco_cache_warming_progress' );
    $is_warming_scheduled = wp_next_scheduled( 'yatco_warm_cache_hook' );
    
    // Get current CPT count
    $cpt_count = wp_count_posts( 'yacht' );
    $published_count = isset( $cpt_count->publish ) ? intval( $cpt_count->publish ) : 0;
    
    // Show CPT count summary
    if ( $published_count > 0 ) {
        echo '<hr />';
        echo '<h2>CPT Status</h2>';
        echo '<div class="notice notice-success"><p><strong>Current Yacht Posts in CPT:</strong> ' . number_format( $published_count ) . '</p>';
        echo '<p>Vessels are stored in the Custom Post Type and can be managed via <a href="' . admin_url( 'edit.php?post_type=yacht' ) . '">Yachts → All Yachts</a>.</p></div>';
    }
    
    // Show progress tracker if import is active, scheduled, or has progress
    if ( $cache_status || $cache_progress || $is_warming_scheduled ) {
        echo '<hr />';
        echo '<h2>CPT Import Status</h2>';
        echo '<div class="yatco-cache-progress-section">';
        
        // Show status message
        if ( $cache_status ) {
            echo '<div class="notice notice-info yatco-cache-status"><p class="yatco-cache-status"><strong>Status:</strong> ' . esc_html( $cache_status ) . '</p></div>';
        } elseif ( $is_warming_scheduled && ! $cache_status ) {
            // Show starting message if scheduled but no status yet
            echo '<div class="notice notice-info yatco-cache-status"><p class="yatco-cache-status"><strong>Status:</strong> Cache warming is starting... Please wait for the first batch to complete.</p></div>';
        }
        
        // Show progress if available
        if ( $cache_progress && is_array( $cache_progress ) ) {
            $progress_info = $cache_progress;
            $last_processed = isset( $progress_info['last_processed'] ) ? intval( $progress_info['last_processed'] ) : 0;
            $total = isset( $progress_info['total'] ) ? intval( $progress_info['total'] ) : 0;
            $cached = isset( $progress_info['processed'] ) ? intval( $progress_info['processed'] ) : 0;
            $start_time = isset( $progress_info['start_time'] ) ? intval( $progress_info['start_time'] ) : time();
            $current_time = time();
            $elapsed = $current_time - $start_time;
            
            if ( $total > 0 ) {
                $percent = round( ( $last_processed / $total ) * 100, 1 );
                $remaining = $total - $last_processed;
                $avg_time_per_vessel = $last_processed > 0 ? $elapsed / $last_processed : 0;
                $estimated_remaining = $remaining * $avg_time_per_vessel;
                $estimated_remaining_formatted = $estimated_remaining > 0 ? human_time_diff( $current_time, $current_time + $estimated_remaining ) : 'calculating...';
                
                echo '<div class="notice notice-warning yatco-live-progress-container">';
                echo '<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">';
                echo '<span class="yatco-live-indicator" style="display: inline-block; width: 12px; height: 12px; background-color: #46b450; border-radius: 50%; animation: yatco-pulse 2s infinite;"></span>';
                echo '<p class="yatco-progress-text" style="margin: 0; flex: 1;"><strong>Progress:</strong> Processed <span class="yatco-current-processed">' . number_format( $last_processed ) . '</span> of <span class="yatco-total-vessels">' . number_format( $total ) . '</span> vessels (<span class="yatco-percent">' . $percent . '</span>%). <span class="yatco-cached-count">' . number_format( $cached ) . '</span> vessels imported to CPT so far.</p>';
                echo '<form method="post" style="margin: 0;">';
                wp_nonce_field( 'yatco_stop_import', 'yatco_stop_import_nonce' );
                echo '<button type="submit" name="yatco_stop_import" class="button button-secondary" style="background: #dc3232; border-color: #dc3232; color: #fff; padding: 5px 10px; font-size: 12px;">🛑 Stop</button>';
                echo '</form>';
                echo '</div>';
                
                // Enhanced progress bar with animation
                echo '<div class="yatco-progress-bar-container" style="width: 100%; background-color: #f0f0f0; border-radius: 4px; height: 35px; margin: 15px 0; overflow: hidden; position: relative; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);">';
                echo '<div class="yatco-progress-bar-fill" style="width: ' . esc_attr( $percent ) . '%; background: linear-gradient(90deg, #0073aa 0%, #005a87 50%, #0073aa 100%); background-size: 200% 100%; height: 100%; transition: width 0.5s ease; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 13px; text-shadow: 0 1px 2px rgba(0,0,0,0.3); animation: yatco-progress-shimmer 2s infinite;">' . esc_html( $percent ) . '%</div>';
                echo '<div class="yatco-progress-bar-label" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); pointer-events: none; color: #333; font-weight: 600; font-size: 13px; text-shadow: 0 1px 2px rgba(255,255,255,0.8);"></div>';
                echo '</div>';
                
                if ( $elapsed > 0 && $last_processed > 0 ) {
                    $rate = $last_processed / $elapsed;
                    echo '<div class="yatco-progress-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0; padding: 15px; background: #f9f9f9; border-radius: 4px;">';
                    echo '<div><strong>⚡ Rate:</strong> <span class="yatco-rate">' . number_format( $rate, 2 ) . '</span> vessels/sec</div>';
                    echo '<div><strong>⏱️ Elapsed:</strong> <span class="yatco-elapsed">' . human_time_diff( $start_time, $current_time ) . '</span></div>';
                    echo '<div><strong>⏳ Remaining:</strong> <span class="yatco-remaining">' . $estimated_remaining_formatted . '</span></div>';
                    echo '<div><strong>📦 Cached:</strong> <span class="yatco-cached">' . number_format( $cached ) . '</span> vessels</div>';
                    echo '</div>';
                }
                
                echo '<p style="margin-top: 10px; font-size: 12px; color: #666;"><em>🟢 Live updates every 2 seconds. If the process was interrupted, it will resume from where it left off on the next run.</em></p>';
                echo '</div>';
            } else {
                // Show initial state while waiting for first batch
                echo '<div class="notice notice-warning yatco-live-progress-container">';
                echo '<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">';
                echo '<span class="yatco-live-indicator" style="display: inline-block; width: 12px; height: 12px; background-color: #46b450; border-radius: 50%; animation: yatco-pulse 2s infinite;"></span>';
                echo '<p style="margin: 0; flex: 1;"><strong>Status:</strong> Cache warming is initializing... Waiting for first batch to complete.</p>';
                echo '<form method="post" style="margin: 0;">';
                wp_nonce_field( 'yatco_stop_import', 'yatco_stop_import_nonce' );
                echo '<button type="submit" name="yatco_stop_import" class="button button-secondary" style="background: #dc3232; border-color: #dc3232; color: #fff; padding: 5px 10px; font-size: 12px;">🛑 Stop</button>';
                echo '</form>';
                echo '</div>';
                echo '<div class="yatco-progress-bar-container" style="width: 100%; background-color: #f0f0f0; border-radius: 4px; height: 35px; margin: 15px 0; overflow: hidden; position: relative; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);">';
                echo '<div class="yatco-progress-bar-fill" style="width: 0%; background: linear-gradient(90deg, #0073aa 0%, #005a87 50%, #0073aa 100%); background-size: 200% 100%; height: 100%; transition: width 0.5s ease; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 13px; text-shadow: 0 1px 2px rgba(0,0,0,0.3); animation: yatco-progress-shimmer 2s infinite;">0%</div>';
                echo '</div>';
                echo '<p style="margin-top: 10px; font-size: 12px; color: #666;"><em>🟢 Live updates every 2 seconds. Progress will appear once the first batch (20 vessels) is processed.</em></p>';
                echo '</div>';
            }
        } elseif ( $is_warming_scheduled && ! $cache_progress ) {
            // Show initial state if warming is scheduled but no progress yet
            echo '<div class="notice notice-warning yatco-live-progress-container">';
            echo '<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">';
            echo '<span class="yatco-live-indicator" style="display: inline-block; width: 12px; height: 12px; background-color: #46b450; border-radius: 50%; animation: yatco-pulse 2s infinite;"></span>';
            echo '<p style="margin: 0; flex: 1;"><strong>Status:</strong> Cache warming is initializing... Waiting for first batch to complete.</p>';
            echo '<form method="post" style="margin: 0;">';
            wp_nonce_field( 'yatco_stop_import', 'yatco_stop_import_nonce' );
            echo '<button type="submit" name="yatco_stop_import" class="button button-secondary" style="background: #dc3232; border-color: #dc3232; color: #fff; padding: 5px 10px; font-size: 12px;">🛑 Stop</button>';
            echo '</form>';
            echo '</div>';
            echo '<div class="yatco-progress-bar-container" style="width: 100%; background-color: #f0f0f0; border-radius: 4px; height: 35px; margin: 15px 0; overflow: hidden; position: relative; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);">';
            echo '<div class="yatco-progress-bar-fill" style="width: 0%; background: linear-gradient(90deg, #0073aa 0%, #005a87 50%, #0073aa 100%); background-size: 200% 100%; height: 100%; transition: width 0.5s ease; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 13px; text-shadow: 0 1px 2px rgba(0,0,0,0.3); animation: yatco-progress-shimmer 2s infinite;">0%</div>';
            echo '</div>';
            echo '<p style="margin-top: 10px; font-size: 12px; color: #666;"><em>🟢 Live updates every 2 seconds. Progress will appear once the first batch (20 vessels) is processed.</em></p>';
            
            // Add manual trigger buttons if stuck
            if ( $is_warming_scheduled || ! $cache_progress ) {
                $scheduled_time = wp_next_scheduled( 'yatco_warm_cache_hook' );
                $time_since_scheduled = $scheduled_time ? ( time() - $scheduled_time ) : 0;
                
                if ( $time_since_scheduled > 60 && ! $cache_progress && ! $cache_status ) {
                    echo '<div style="margin-top: 15px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">';
                    echo '<p style="margin: 0 0 10px 0;"><strong>⚠️ Progress not detected</strong></p>';
                    echo '<p style="margin: 0 0 10px 0; font-size: 13px;">WP-Cron may not be enabled on your server. Try one of these options:</p>';
                    echo '<p style="margin: 0;">';
                    echo '<button type="button" id="yatco-force-trigger" class="button button-secondary" onclick="yatcoForceTriggerWarming()" style="margin-right: 10px;">Try WP-Cron Trigger</button>';
                    echo '<button type="button" id="yatco-direct-trigger" class="button button-primary" onclick="yatcoRunDirectWarming()">Run Directly (Recommended)</button>';
                    echo '</p>';
                    echo '<p style="margin: 10px 0 0 0; font-size: 12px; color: #666;"><em>The "Run Directly" option will start cache warming immediately without relying on WP-Cron.</em></p>';
                    echo '</div>';
                }
            }
            
            echo '</div>';
        }
        
        // Add CSS animations
        echo '<style>
        @keyframes yatco-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.2); }
        }
        @keyframes yatco-progress-shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        .yatco-progress-bar-fill {
            animation: yatco-progress-shimmer 2s infinite linear;
        }
        .yatco-live-progress-container {
            border-left: 4px solid #ffc107;
        }
        </style>';
        
        echo '</div>';
    }
    
    // Daily Statistics Section
    echo '<hr />';
    echo '<h2>Daily Statistics</h2>';
    $daily_stats = yatco_get_daily_stats();
    if ( $daily_stats ) {
        echo '<div style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin: 10px 0;">';
        echo '<table class="widefat" style="margin: 0;">';
        echo '<thead><tr><th>Date</th><th>Total Vessels</th><th>Added</th><th>Removed</th><th>Updated</th></tr></thead>';
        echo '<tbody>';
        foreach ( $daily_stats as $date => $stats ) {
            $added_class = $stats['added'] > 0 ? 'style="color: #46b450; font-weight: 600;"' : '';
            $removed_class = $stats['removed'] > 0 ? 'style="color: #dc3232; font-weight: 600;"' : '';
            $updated_class = $stats['updated'] > 0 ? 'style="color: #0073aa; font-weight: 600;"' : '';
            echo '<tr>';
            echo '<td><strong>' . esc_html( $date ) . '</strong></td>';
            echo '<td>' . number_format( $stats['total'] ) . '</td>';
            echo '<td ' . $added_class . '>+' . number_format( $stats['added'] ) . '</td>';
            echo '<td ' . $removed_class . '>-' . number_format( $stats['removed'] ) . '</td>';
            echo '<td ' . $updated_class . '>' . number_format( $stats['updated'] ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
    } else {
        echo '<p>No daily statistics available yet. Statistics will be generated after the first cache warming completes.</p>';
    }
    
    // Clear cache button
    echo '<form method="post" style="margin-top: 10px;">';
    wp_nonce_field( 'yatco_clear_cache', 'yatco_clear_cache_nonce' );
    submit_button( 'Clear Cache', 'secondary', 'yatco_clear_cache' );
    echo '</form>';

    // Add auto-refresh JavaScript for cache warming status
    if ( $cache_status || $cache_progress || $is_warming_scheduled ) {
        ?>
        <script>
        (function() {
            // Force trigger cache warming via WP-Cron
            window.yatcoForceTriggerWarming = function() {
                const btn = document.getElementById('yatco-force-trigger');
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Triggering...';
                }
                
                fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'action=yatco_trigger_cache_warming&nonce=<?php echo wp_create_nonce( 'yatco_trigger_warming' ); ?>'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (btn) {
                            btn.textContent = 'Triggered! Refreshing...';
                        }
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    } else {
                        alert('Error: ' + (data.data && data.data.message ? data.data.message : 'Unknown error'));
                        if (btn) {
                            btn.disabled = false;
                            btn.textContent = 'Try WP-Cron Trigger';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error triggering warming:', error);
                    alert('Error triggering cache warming. Try "Run Directly" instead.');
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = 'Try WP-Cron Trigger';
                    }
                });
            };
            
            // Run cache warming directly (bypasses WP-Cron)
            window.yatcoRunDirectWarming = function() {
                const btn = document.getElementById('yatco-direct-trigger');
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Starting... Please wait...';
                }
                
                // Update status immediately
                const statusEl = document.querySelector('.yatco-cache-status');
                if (statusEl) {
                    statusEl.innerHTML = '<strong>Status:</strong> Starting cache warm-up directly...';
                }
                
                fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'action=yatco_run_cache_warming_direct&nonce=<?php echo wp_create_nonce( 'yatco_run_warming_direct' ); ?>',
                    timeout: 30000
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (btn) {
                            btn.textContent = 'Started! Refreshing...';
                        }
                        // Reload after 3 seconds to show progress
                        setTimeout(function() {
                            window.location.reload();
                        }, 3000);
                    } else {
                        alert('Error: ' + (data.data && data.data.message ? data.data.message : 'Unknown error'));
                        if (btn) {
                            btn.disabled = false;
                            btn.textContent = 'Run Directly (Recommended)';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error running warming directly:', error);
                    // Even if error, try reloading as it might have started
                    alert('Request timeout - cache warming may have started. Refreshing page...');
                    window.location.reload();
                });
            };
            
            // Smooth number animation helper
            function animateValue(element, start, end, duration) {
                if (start === end || !element) return;
                const range = end - start;
                const increment = end > start ? 1 : -1;
                const stepTime = Math.abs(Math.floor(duration / range));
                let current = start;
                
                const timer = setInterval(function() {
                    current += increment;
                    if (element) {
                        element.textContent = current.toLocaleString();
                    }
                    if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
                        if (element) {
                            element.textContent = end.toLocaleString();
                        }
                        clearInterval(timer);
                    }
                }, stepTime);
            }
            
            // Auto-refresh progress every 2 seconds for live updates
            let refreshInterval = setInterval(function() {
                const progressDiv = document.querySelector('.yatco-cache-progress-section');
                if (progressDiv) {
                    // Reload only the cache warming status section via AJAX
                    fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>?action=yatco_get_cache_status&nonce=<?php echo wp_create_nonce( 'yatco_cache_status' ); ?>', {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data) {
                            const responseData = data.data;
                            
                            // Update status
                            if (responseData.status) {
                                const statusEl = document.querySelector('.yatco-cache-status');
                                if (statusEl) {
                                    statusEl.innerHTML = '<strong>Status:</strong> ' + responseData.status;
                                }
                            }
                            
                            // Update progress with smooth animations
                            if (responseData.progress && responseData.progress.total > 0) {
                                const percent = Math.round((responseData.progress.last_processed / responseData.progress.total) * 100 * 10) / 10;
                                
                                // Update progress bar with smooth transition
                                const progressBar = document.querySelector('.yatco-progress-bar-fill');
                                if (progressBar) {
                                    progressBar.style.width = percent + '%';
                                    progressBar.textContent = percent + '%';
                                    
                                    // Update label visibility
                                    const label = document.querySelector('.yatco-progress-bar-label');
                                    if (label) {
                                        label.style.display = percent > 15 ? 'none' : 'block';
                                        label.textContent = percent + '%';
                                    }
                                }
                                
                                // Update progress text with smooth number transitions
                                const currentProcessed = document.querySelector('.yatco-current-processed');
                                const percentSpan = document.querySelector('.yatco-percent');
                                const cachedCount = document.querySelector('.yatco-cached-count');
                                
                                if (currentProcessed) {
                                    animateValue(currentProcessed, parseInt(currentProcessed.textContent.replace(/,/g, '')), responseData.progress.last_processed, 500);
                                }
                                if (percentSpan) {
                                    percentSpan.textContent = percent;
                                }
                                if (cachedCount) {
                                    animateValue(cachedCount, parseInt(cachedCount.textContent.replace(/,/g, '')), responseData.progress.processed, 500);
                                }
                                
                                // Update stats grid
                                const rateEl = document.querySelector('.yatco-rate');
                                const elapsedEl = document.querySelector('.yatco-elapsed');
                                const remainingEl = document.querySelector('.yatco-remaining');
                                const cachedEl = document.querySelector('.yatco-cached');
                                
                                if (responseData.progress.rate) {
                                    if (rateEl) rateEl.textContent = parseFloat(responseData.progress.rate).toFixed(2);
                                    if (elapsedEl) elapsedEl.textContent = responseData.progress.elapsed;
                                    if (remainingEl) remainingEl.textContent = responseData.progress.estimated_remaining;
                                    if (cachedEl) {
                                        animateValue(cachedEl, parseInt(cachedEl.textContent.replace(/,/g, '')), responseData.progress.processed, 500);
                                    }
                                }
                            }
                            
                            // Stop refreshing if cache warming is complete
                            if (responseData.status && responseData.status.includes('successfully')) {
                                clearInterval(refreshInterval);
                                // Reload page after 2 seconds to show final state
                                setTimeout(function() {
                                    window.location.reload();
                                }, 2000);
                            }
                        } else {
                            // No warming in progress, stop refreshing
                            clearInterval(refreshInterval);
                        }
                    })
                    .catch(error => {
                        console.error('Error refreshing cache status:', error);
                    });
                } else {
                    // Progress section removed, stop refreshing
                    clearInterval(refreshInterval);
                }
            }, 2000); // Refresh every 2 seconds for live updates
            
            // Stop refreshing when page is hidden
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    clearInterval(refreshInterval);
                } else {
                    // Restart when page becomes visible again
                    location.reload();
                }
            });
        })();
        </script>
        <?php
    }
    
    echo '</div>';
}

/**
 * AJAX handler to manually trigger cache warming.
 */
function yatco_ajax_trigger_cache_warming() {
    check_ajax_referer( 'yatco_trigger_warming', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        return;
    }
    
    // Set initial status
    if ( function_exists( 'set_transient' ) ) {
        set_transient( 'yatco_cache_warming_status', 'Starting cache warm-up...', 600 );
    }
    
    // Trigger cache warming function directly (not via WP-Cron)
    if ( function_exists( 'yatco_warm_cache_function' ) ) {
        // Schedule cron as backup, but also try to trigger directly
        wp_schedule_single_event( time(), 'yatco_warm_cache_hook' );
        spawn_cron();
        
        // Also trigger directly in a separate request to ensure it runs
        wp_remote_post(
            site_url( 'wp-cron.php?doing_wp_cron' ),
            array(
                'timeout'   => 0.01,
                'blocking'  => false,
                'sslverify' => false,
            )
        );
        
        wp_send_json_success( array( 'message' => 'Cache warming triggered. If progress doesn\'t appear, WP-Cron may be disabled on your server.' ) );
    } else {
        wp_send_json_error( array( 'message' => 'Function not found' ) );
    }
}

/**
 * AJAX handler to run cache warming directly (synchronous - for testing).
 */
function yatco_ajax_run_cache_warming_direct() {
    check_ajax_referer( 'yatco_run_warming_direct', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        return;
    }
    
    // Set initial status
    if ( function_exists( 'set_transient' ) ) {
        set_transient( 'yatco_cache_warming_status', 'Starting cache warm-up directly...', 600 );
    }
    
    // Run the function directly
    if ( function_exists( 'yatco_warm_cache_function' ) ) {
        // Increase execution limits
        @ini_set( 'max_execution_time', 300 ); // 5 minutes
        @ini_set( 'memory_limit', '512M' );
        @set_time_limit( 300 );
        
        // Run first batch synchronously to get progress started
        yatco_warm_cache_function();
        
        wp_send_json_success( array( 'message' => 'Cache warming started directly. Progress should appear shortly.' ) );
    } else {
        wp_send_json_error( array( 'message' => 'Function not found' ) );
    }
}

add_action( 'wp_ajax_yatco_trigger_cache_warming', 'yatco_ajax_trigger_cache_warming' );
add_action( 'wp_ajax_yatco_run_cache_warming_direct', 'yatco_ajax_run_cache_warming_direct' );

/**
 * AJAX handler for cache warming status (auto-refresh).
 */
function yatco_ajax_get_cache_status() {
    check_ajax_referer( 'yatco_cache_status', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        return;
    }
    
    $cache_status = get_transient( 'yatco_cache_warming_status' );
    $cache_progress = get_transient( 'yatco_cache_warming_progress' );
    
    $response = array();
    
    if ( $cache_status ) {
        $response['status'] = $cache_status;
    }
    
    if ( $cache_progress && is_array( $cache_progress ) ) {
        $last_processed = isset( $cache_progress['last_processed'] ) ? intval( $cache_progress['last_processed'] ) : 0;
        $total = isset( $cache_progress['total'] ) ? intval( $cache_progress['total'] ) : 0;
        $processed = isset( $cache_progress['processed'] ) ? intval( $cache_progress['processed'] ) : 0;
        $start_time = isset( $cache_progress['start_time'] ) ? intval( $cache_progress['start_time'] ) : time();
        $current_time = time();
        $elapsed = $current_time - $start_time;
        
        $response['progress'] = array(
            'last_processed' => $last_processed,
            'total'         => $total,
            'processed'     => $processed,
            'percent'       => $total > 0 ? round( ( $last_processed / $total ) * 100, 1 ) : 0,
        );
        
        if ( $elapsed > 0 && $last_processed > 0 ) {
            $rate = $last_processed / $elapsed;
            $remaining = $total - $last_processed;
            $avg_time_per_vessel = $elapsed / $last_processed;
            $estimated_remaining = $remaining * $avg_time_per_vessel;
            
            $response['progress']['rate'] = number_format( $rate, 1 );
            $response['progress']['elapsed'] = human_time_diff( $start_time, $current_time );
            $response['progress']['estimated_remaining'] = $estimated_remaining > 0 ? human_time_diff( $current_time, $current_time + $estimated_remaining ) : 'calculating...';
        }
    }
    
    wp_send_json_success( $response );
}
add_action( 'wp_ajax_yatco_get_cache_status', 'yatco_ajax_get_cache_status' );

/**
 * Import page (Yachts → YATCO Import).
 */
function yatco_import_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $token = yatco_get_token();
    echo '<div class="wrap"><h1>YATCO Import</h1>';

    if ( empty( $token ) ) {
        echo '<div class="notice notice-error"><p>Please set your Basic token in <a href="' . esc_url( admin_url( 'options-general.php?page=yatco_api' ) ) . '">Settings → YATCO API</a> first.</p></div>';
        echo '</div>';
        return;
    }

    // Parse criteria - treat empty strings and 0 as "no filter"
    $criteria_price_min = isset( $_POST['price_min'] ) && $_POST['price_min'] !== '' && $_POST['price_min'] !== '0' ? floatval( $_POST['price_min'] ) : '';
    $criteria_price_max = isset( $_POST['price_max'] ) && $_POST['price_max'] !== '' && $_POST['price_max'] !== '0' ? floatval( $_POST['price_max'] ) : '';
    $criteria_year_min  = isset( $_POST['year_min'] ) && $_POST['year_min'] !== '' && $_POST['year_min'] !== '0' ? intval( $_POST['year_min'] ) : '';
    $criteria_year_max  = isset( $_POST['year_max'] ) && $_POST['year_max'] !== '' && $_POST['year_max'] !== '0' ? intval( $_POST['year_max'] ) : '';
    $criteria_loa_min   = isset( $_POST['loa_min'] ) && $_POST['loa_min'] !== '' && $_POST['loa_min'] !== '0' ? floatval( $_POST['loa_min'] ) : '';
    $criteria_loa_max   = isset( $_POST['loa_max'] ) && $_POST['loa_max'] !== '' && $_POST['loa_max'] !== '0' ? floatval( $_POST['loa_max'] ) : '';
    $max_records        = isset( $_POST['max_records'] ) && $_POST['max_records'] !== '' && $_POST['max_records'] > 0 ? intval( $_POST['max_records'] ) : 50;

    $preview_results = array();
    $message         = '';

    // Handle import action.
    if ( isset( $_POST['yatco_import_selected'] ) && ! empty( $_POST['vessel_ids'] ) && is_array( $_POST['vessel_ids'] ) && check_admin_referer( 'yatco_import_action', 'yatco_import_nonce' ) ) {
        $imported = 0;
        foreach ( $_POST['vessel_ids'] as $vessel_id ) {
            $vessel_id = intval( $vessel_id );
            if ( $vessel_id <= 0 ) {
                continue;
            }
            $result = yatco_import_single_vessel( $token, $vessel_id );
            if ( ! is_wp_error( $result ) ) {
                $imported++;
            }
        }
        $message = sprintf( '%d vessel(s) imported/updated.', $imported );
        echo '<div class="notice notice-success"><p>' . esc_html( $message ) . '</p></div>';
    }

    // Handle preview action.
    if ( isset( $_POST['yatco_preview_listings'] ) && check_admin_referer( 'yatco_import_action', 'yatco_import_nonce' ) ) {

        // Fetch more IDs than needed to account for filtering (5x the desired results, max 100)
        $ids_to_fetch = min( $max_records * 5, 100 );
        $ids = yatco_get_active_vessel_ids( $token, $ids_to_fetch );

        if ( is_wp_error( $ids ) ) {
            echo '<div class="notice notice-error"><p>Error fetching active vessel IDs: ' . esc_html( $ids->get_error_message() ) . '</p></div>';
        } elseif ( empty( $ids ) ) {
            echo '<div class="notice notice-warning"><p>No active vessels returned from YATCO.</p></div>';
        } else {
            foreach ( $ids as $id ) {
                // Stop if we've reached the desired number of results
                if ( count( $preview_results ) >= $max_records ) {
                    break;
                }

                $full = yatco_fetch_fullspecs( $token, $id );
                if ( is_wp_error( $full ) ) {
                    continue;
                }

                $brief = yatco_build_brief_from_fullspecs( $id, $full );

                // Apply basic filtering in PHP based on criteria.
                $price = ! empty( $brief['Price'] ) ? floatval( $brief['Price'] ) : null;
                $year  = ! empty( $brief['Year'] ) ? intval( $brief['Year'] ) : null;
                // LOA might be a formatted string, extract numeric value.
                $loa_raw = $brief['LOA'];
                if ( is_string( $loa_raw ) && preg_match( '/([0-9.]+)/', $loa_raw, $matches ) ) {
                    $loa = floatval( $matches[1] );
                } elseif ( ! empty( $loa_raw ) && is_numeric( $loa_raw ) ) {
                    $loa = floatval( $loa_raw );
                } else {
                    $loa = null;
                }

                // Apply filters only if criteria are set (not empty string).
                // Skip vessels with null/0 values only if a filter is set.
                if ( $criteria_price_min !== '' ) {
                    if ( is_null( $price ) || $price <= 0 || $price < $criteria_price_min ) {
                        continue;
                    }
                }
                if ( $criteria_price_max !== '' ) {
                    if ( is_null( $price ) || $price <= 0 || $price > $criteria_price_max ) {
                        continue;
                    }
                }
                if ( $criteria_year_min !== '' ) {
                    if ( is_null( $year ) || $year <= 0 || $year < $criteria_year_min ) {
                        continue;
                    }
                }
                if ( $criteria_year_max !== '' ) {
                    if ( is_null( $year ) || $year <= 0 || $year > $criteria_year_max ) {
                        continue;
                    }
                }
                if ( $criteria_loa_min !== '' ) {
                    if ( is_null( $loa ) || $loa <= 0 || $loa < $criteria_loa_min ) {
                        continue;
                    }
                }
                if ( $criteria_loa_max !== '' ) {
                    if ( is_null( $loa ) || $loa <= 0 || $loa > $criteria_loa_max ) {
                        continue;
                    }
                }

                $preview_results[] = $brief;
            }

            if ( empty( $preview_results ) ) {
                echo '<div class="notice notice-warning"><p>No vessels matched your criteria after filtering FullSpecsAll data.</p></div>';
            } elseif ( count( $preview_results ) < $max_records ) {
                echo '<div class="notice notice-info"><p>Found ' . count( $preview_results ) . ' vessel(s) matching your criteria (requested up to ' . $max_records . ').</p></div>';
            }
        }
    }

    ?>
    <h2>Import Criteria</h2>
    <form method="post">
        <?php wp_nonce_field( 'yatco_import_action', 'yatco_import_nonce' ); ?>
        <table class="form-table">
            <tr>
                <th scope="row">Price (USD)</th>
                <td>
                    Min: <input type="number" step="1" name="price_min" value="<?php echo $criteria_price_min !== '' ? esc_attr( $criteria_price_min ) : ''; ?>" />
                    Max: <input type="number" step="1" name="price_max" value="<?php echo $criteria_price_max !== '' ? esc_attr( $criteria_price_max ) : ''; ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row">Length (LOA)</th>
                <td>
                    Min: <input type="number" step="0.1" name="loa_min" value="<?php echo $criteria_loa_min !== '' ? esc_attr( $criteria_loa_min ) : ''; ?>" />
                    Max: <input type="number" step="0.1" name="loa_max" value="<?php echo $criteria_loa_max !== '' ? esc_attr( $criteria_loa_max ) : ''; ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row">Year Built</th>
                <td>
                    Min: <input type="number" step="1" name="year_min" value="<?php echo $criteria_year_min !== '' ? esc_attr( $criteria_year_min ) : ''; ?>" />
                    Max: <input type="number" step="1" name="year_max" value="<?php echo $criteria_year_max !== '' ? esc_attr( $criteria_year_max ) : ''; ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row">Max Results</th>
                <td>
                    <input type="number" step="1" name="max_records" value="<?php echo esc_attr( $max_records ); ?>" />
                    <p class="description">Maximum number of matching vessels to display (default 50). The system will fetch up to 5x this number to find matches.</p>
                </td>
            </tr>
        </table>

        <?php submit_button( 'Preview Listings', 'primary', 'yatco_preview_listings' ); ?>

        <?php if ( ! empty( $preview_results ) ) : ?>
            <h2>Preview Results</h2>
            <p>Select the vessels you want to import or update.</p>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th class="manage-column column-cb check-column"><input type="checkbox" onclick="jQuery('.yatco-vessel-checkbox').prop('checked', this.checked);" /></th>
                        <th>Vessel ID</th>
                        <th>MLS ID</th>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Year</th>
                        <th>LOA</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $preview_results as $row ) : ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input class="yatco-vessel-checkbox" type="checkbox" name="vessel_ids[]" value="<?php echo esc_attr( $row['VesselID'] ); ?>" />
                            </th>
                            <td><?php echo esc_html( $row['VesselID'] ); ?></td>
                            <td><?php echo esc_html( $row['MLSId'] ); ?></td>
                            <td><?php echo esc_html( $row['Name'] ); ?></td>
                            <td><?php echo esc_html( $row['Price'] ); ?></td>
                            <td><?php echo esc_html( $row['Year'] ); ?></td>
                            <td><?php echo esc_html( $row['LOA'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php submit_button( 'Import Selected', 'primary', 'yatco_import_selected' ); ?>
        <?php endif; ?>
    </form>
    <?php

    echo '</div>';
}

