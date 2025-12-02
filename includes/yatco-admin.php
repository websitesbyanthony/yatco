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
    submit_button( '🔍 Fetch First Vessel & Create Test Post', 'secondary', 'yatco_test_vessel_data_only', false, array( 'id' => 'yatco-test-vessel-btn' ) );
    echo '</form>';

    if ( isset( $_POST['yatco_test_vessel_data_only'] ) && ! empty( $_POST['yatco_test_vessel_data_only'] ) ) {
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
            
            // Get multiple vessel IDs so we can try different ones if the first doesn't have FullSpecsAll data
            $vessel_ids = yatco_get_active_vessel_ids( $token, 10 );
            
            if ( is_wp_error( $vessel_ids ) ) {
                echo '<div class="notice notice-error"><p><strong>Error getting vessel IDs:</strong> ' . esc_html( $vessel_ids->get_error_message() ) . '</p></div>';
                echo '</div>';
            } elseif ( empty( $vessel_ids ) || ! is_array( $vessel_ids ) ) {
                echo '<div class="notice notice-error"><p><strong>Error:</strong> No vessel IDs returned. The API response may be empty or invalid.</p></div>';
                echo '</div>';
            } else {
                echo '<p><strong>✅ Success!</strong> Found ' . count( $vessel_ids ) . ' vessel ID(s). Will try each one until we find one with accessible FullSpecsAll data.</p>';
                
                // Try multiple vessel IDs until we find one with FullSpecsAll data
                $found_vessel_id = null;
                $fullspecs = null;
                $response = null;
                $tried_vessels = array();
                
                foreach ( $vessel_ids as $vessel_id ) {
                    $tried_vessels[] = $vessel_id;
                    echo '<h3>Step 2: Trying Vessel ID ' . esc_html( $vessel_id ) . '</h3>';
                    
                    $endpoint = 'https://api.yatcoboss.com/api/v1/ForSale/Vessel/' . intval( $vessel_id ) . '/Details/FullSpecsAll';
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
                        echo '<p style="color: #dc3232;">❌ WP_Remote Error: ' . esc_html( $response->get_error_message() ) . ' - Trying next vessel...</p>';
                        continue;
                    }
                    
                    $response_code = wp_remote_retrieve_response_code( $response );
                    $response_body = wp_remote_retrieve_body( $response );
                    
                    if ( 200 !== $response_code ) {
                        echo '<p style="color: #dc3232;">❌ HTTP Error ' . esc_html( $response_code ) . ' - Trying next vessel...</p>';
                        continue;
                    }
                    
                    $fullspecs = json_decode( $response_body, true );
                    $json_error = json_last_error();
                    
                    if ( $json_error !== JSON_ERROR_NONE ) {
                        echo '<p style="color: #dc3232;">❌ JSON Parse Error: ' . esc_html( json_last_error_msg() ) . ' - Trying next vessel...</p>';
                        continue;
                    }
                    
                    if ( $fullspecs === null || empty( $fullspecs ) ) {
                        echo '<p style="color: #ff9800;">⚠️ API returned null for vessel ID ' . esc_html( $vessel_id ) . ' - Trying next vessel...</p>';
                        continue;
                    }
                    
                    // Found one with data!
                    $found_vessel_id = $vessel_id;
                    echo '<p style="color: #46b450; font-weight: bold;">✅ Found vessel with accessible FullSpecsAll data: Vessel ID ' . esc_html( $found_vessel_id ) . '</p>';
                    
                    if ( count( $tried_vessels ) > 1 ) {
                        echo '<p style="color: #666; font-size: 13px;">Note: Tried ' . count( $tried_vessels ) . ' vessel(s) before finding one with accessible data: ' . esc_html( implode( ', ', array_slice( $tried_vessels, 0, -1 ) ) ) . '</p>';
                    }
                    break;
                }
                
                if ( ! $found_vessel_id || ! $fullspecs ) {
                    echo '<div class="notice notice-error" style="background: #fce8e6; border-left: 4px solid #dc3232; padding: 15px; margin: 20px 0;">';
                    echo '<p style="font-size: 16px; font-weight: bold; margin: 0 0 10px 0;"><strong>❌ No Accessible Vessels Found</strong></p>';
                    echo '<p>Tried ' . count( $tried_vessels ) . ' vessel ID(s): <code>' . esc_html( implode( ', ', $tried_vessels ) ) . '</code></p>';
                    echo '<p>None of these vessels have accessible FullSpecsAll data. This may indicate:</p>';
                    echo '<ul style="margin-left: 20px; margin-top: 10px;">';
                    echo '<li>Your API token may not have permissions to access FullSpecsAll data</li>';
                    echo '<li>The vessels may have been removed or are restricted</li>';
                    echo '<li>There may be a temporary API issue</li>';
                    echo '</ul>';
                    echo '<p style="margin-top: 15px;">Please verify your API token permissions or contact YATCO support.</p>';
                    echo '</div>';
                } else {
                    // Found a vessel with accessible FullSpecsAll data
                    echo '<p><strong>Response Code:</strong> ' . esc_html( wp_remote_retrieve_response_code( $response ) ) . '</p>';
                    echo '<p><strong>Content-Type:</strong> ' . esc_html( wp_remote_retrieve_header( $response, 'content-type' ) ) . '</p>';
                    echo '<p><strong>Response Length:</strong> ' . strlen( wp_remote_retrieve_body( $response ) ) . ' characters</p>';
                    echo '<p><strong>✅ Success!</strong> FullSpecsAll data retrieved and parsed successfully.</p>';
                    
                    if ( is_array( $fullspecs ) && ! empty( $fullspecs ) ) {
                        $sections_found = array_keys( $fullspecs );
                        echo '<p style="color: #666; font-size: 13px;">Data sections found: <strong>' . esc_html( count( $sections_found ) ) . '</strong> sections (' . esc_html( implode( ', ', array_slice( $sections_found, 0, 5 ) ) ) . ( count( $sections_found ) > 5 ? ', ...' : '' ) . ')</p>';
                    }
                    
                    echo '<h3>Step 3: Importing Vessel to CPT</h3>';
                    echo '<p>Now importing vessel ID ' . esc_html( $found_vessel_id ) . ' data into your Custom Post Type...</p>';
                    
                    require_once YATCO_PLUGIN_DIR . 'includes/yatco-helpers.php';
                    
                    $import_result = yatco_import_single_vessel( $token, $found_vessel_id );
                    
                    if ( is_wp_error( $import_result ) ) {
                        echo '<div class="notice notice-error">';
                        echo '<p><strong>❌ Import Failed:</strong> ' . esc_html( $import_result->get_error_message() ) . '</p>';
                        echo '<p>This might be due to missing or invalid data in the API response. Check the raw JSON below for details.</p>';
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
                        echo '<a href="' . esc_url( $post_permalink ) . '" target="_blank" class="button button-primary" style="margin-right: 10px; display: inline-block; padding: 8px 16px; text-decoration: none; background: #2271b1; color: #fff; border-radius: 3px; font-weight: bold;">👁️ View Post (New Tab)</a>';
                        echo '<a href="' . esc_url( admin_url( 'post.php?post=' . $post_id . '&action=edit' ) ) . '" class="button button-secondary" style="display: inline-block; padding: 8px 16px; text-decoration: none; background: #f0f0f1; color: #2c3338; border-radius: 3px; border: 1px solid #8c8f94;">✏️ Edit Post</a>';
                        echo '</p>';
                        echo '</div>';
                        
                        echo '<h4 style="margin-top: 20px;">Import Summary:</h4>';
                        echo '<div style="background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 15px;">';
                        echo '<ul style="list-style: disc; margin-left: 20px;">';
                        
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
                    
                    echo '<h3 style="margin-top: 30px;">Raw API Response Data Structure</h3>';
                    echo '<p style="color: #666; font-size: 13px;">Below is the complete JSON response from the YATCO API for reference:</p>';
                    
                    echo '<div style="background: #fff; border: 1px solid #ccc; border-radius: 4px; padding: 15px; max-height: 400px; overflow: auto; font-family: monospace; font-size: 11px; line-height: 1.4;">';
                    echo '<pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word;">';
                    echo esc_html( wp_json_encode( $fullspecs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
                    echo '</pre>';
                    echo '</div>';
                }
                echo '</div>';
            }
        }
    }

    echo '<hr />';
    echo '<h2>CPT Import Management</h2>';
    echo '<p>Import all vessels into the Yacht Custom Post Type (CPT) for faster queries, better SEO, and individual vessel pages. This may take several minutes for 7000+ vessels.</p>';
    echo '<p><strong>Benefits of CPT import:</strong> Better performance with WP_Query, individual pages per vessel, improved SEO, easier management via WordPress admin.</p>';
    
    $pre_cache_status = get_transient( 'yatco_cache_warming_status' );
    $pre_cache_progress = get_transient( 'yatco_cache_warming_progress' );
    $pre_is_warming_scheduled = wp_next_scheduled( 'yatco_warm_cache_hook' );
    
    $pre_button_disabled = false;
    $pre_is_stuck = false;
    
    if ( $pre_cache_progress !== false && is_array( $pre_cache_progress ) && ! empty( $pre_cache_progress ) ) {
        $pre_last_processed = isset( $pre_cache_progress['last_processed'] ) ? intval( $pre_cache_progress['last_processed'] ) : 0;
        $pre_total = isset( $pre_cache_progress['total'] ) ? intval( $pre_cache_progress['total'] ) : 0;
        $pre_timestamp = isset( $pre_cache_progress['timestamp'] ) ? intval( $pre_cache_progress['timestamp'] ) : 0;
        
        if ( $pre_total > 0 && $pre_last_processed < $pre_total ) {
            // Check if progress is stuck (hasn't been updated in more than 2 minutes)
            if ( $pre_timestamp > 0 && ( time() - $pre_timestamp > 120 ) ) {
                // Progress is stuck - auto-clear it
                $pre_is_stuck = true;
                delete_transient( 'yatco_cache_warming_progress' );
                delete_transient( 'yatco_cache_warming_status' );
                $pre_cache_progress = false;
                $pre_cache_status = false;
                $pre_button_disabled = false;
            } elseif ( $pre_timestamp > 0 && ( time() - $pre_timestamp ) < 30 ) {
                // Progress is recent (less than 30 seconds) - assume it's running
                $pre_button_disabled = true;
            } else {
                // Progress is 30-120 seconds old - might be stuck, but don't disable
                $pre_button_disabled = false;
            }
        } elseif ( $pre_total > 0 && $pre_last_processed >= $pre_total ) {
            // Import appears complete - clear progress data
            delete_transient( 'yatco_cache_warming_progress' );
            delete_transient( 'yatco_cache_warming_status' );
            $pre_cache_progress = false;
            $pre_cache_status = false;
            $pre_button_disabled = false;
        } elseif ( empty( $pre_cache_progress ) || ( $pre_total === 0 && $pre_last_processed === 0 ) ) {
            // Empty or invalid progress - clear it
            delete_transient( 'yatco_cache_warming_progress' );
            delete_transient( 'yatco_cache_warming_status' );
            $pre_cache_progress = false;
            $pre_cache_status = false;
            $pre_button_disabled = false;
        }
    }
    
    // Also check if there's a scheduled event that's in the past (shouldn't disable button)
    if ( $pre_is_warming_scheduled && $pre_is_warming_scheduled < time() ) {
        // Scheduled event is in the past - clear it
        wp_clear_scheduled_hook( 'yatco_warm_cache_hook' );
        $pre_is_warming_scheduled = false;
    }
    
    echo '<div style="background: #fff; border: 2px solid #2271b1; border-radius: 4px; padding: 20px; margin: 20px 0;">';
    echo '<h3 style="margin-top: 0; color: #2271b1;">📥 Import Vessels to CPT</h3>';
    
    // Show notice if stuck progress was auto-cleared
    if ( $pre_is_stuck ) {
        echo '<div class="notice notice-warning" style="margin-bottom: 15px;"><p><strong>⚠️ Stuck progress detected and cleared.</strong> The previous import appears to have stopped. You can now start a new import.</p></div>';
    }
    
    echo '<div style="display: flex; gap: 15px; align-items: center; margin: 15px 0; flex-wrap: wrap;">';
    
    echo '<form method="post" style="margin: 0;">';
    wp_nonce_field( 'yatco_warm_cache', 'yatco_warm_cache_nonce' );
    submit_button( 'Import All Vessels to CPT', 'primary', 'yatco_warm_cache', false, array( 
        'disabled' => $pre_button_disabled,
        'style' => 'font-size: 16px; padding: 10px 20px; height: auto;'
    ) );
    echo '</form>';
    
    $should_show_stop = ( $pre_button_disabled || $pre_cache_status !== false || $pre_cache_progress !== false || $pre_is_warming_scheduled );
    
    if ( $should_show_stop ) {
        echo '<form method="post" style="margin: 0;">';
        wp_nonce_field( 'yatco_clear_all', 'yatco_clear_all_nonce' );
        submit_button( '🛑 Stop & Clear All', 'secondary', 'yatco_clear_all', false, array( 
            'style' => 'background: #dc3232; border-color: #dc3232; color: #fff; font-weight: bold; font-size: 16px; padding: 10px 20px; height: auto;'
        ) );
        echo '</form>';
    }
    
    if ( $pre_button_disabled && ! $should_show_stop ) {
        echo '<form method="post" style="margin: 0;">';
        wp_nonce_field( 'yatco_clear_all', 'yatco_clear_all_nonce' );
        submit_button( '🛑 Stop & Clear All', 'secondary', 'yatco_clear_all', false, array( 
            'style' => 'background: #dc3232; border-color: #dc3232; color: #fff; font-weight: bold; font-size: 16px; padding: 10px 20px; height: auto;'
        ) );
        echo '</form>';
    }
    
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
    
    $cache_status = get_transient( 'yatco_cache_warming_status' );
    $cache_progress = get_transient( 'yatco_cache_warming_progress' );
    $is_warming_scheduled = wp_next_scheduled( 'yatco_warm_cache_hook' );
    $is_auto_refresh_scheduled = wp_next_scheduled( 'yatco_auto_refresh_cache_hook' );
    
    $has_active_progress = false;
    $is_stuck = false;
    if ( $cache_progress !== false && is_array( $cache_progress ) && ! empty( $cache_progress ) ) {
        $last_processed = isset( $cache_progress['last_processed'] ) ? intval( $cache_progress['last_processed'] ) : 0;
        $total = isset( $cache_progress['total'] ) ? intval( $cache_progress['total'] ) : 0;
        $has_active_progress = ( $total > 0 && $last_processed < $total );
        
        // Check if progress is stuck (hasn't been updated in 30 minutes)
        $timestamp = isset( $cache_progress['timestamp'] ) ? intval( $cache_progress['timestamp'] ) : 0;
        if ( $has_active_progress && $timestamp > 0 && ( time() - $timestamp > 1800 ) ) {
            $is_stuck = true;
        }
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
    
    $is_running = ( $has_active_status || $has_active_progress || ( $is_warming_scheduled && $is_warming_scheduled > time() ) ) && ! $is_stuck;
    
    if ( isset( $_POST['yatco_stop_import'] ) && check_admin_referer( 'yatco_stop_import', 'yatco_stop_import_nonce' ) ) {
        // Set stop flag for running processes - keep it active for 5 minutes so running processes can detect it
        set_transient( 'yatco_cache_warming_stop', true, 300 );
        
        // Cancel any scheduled cron jobs
        $scheduled = wp_next_scheduled( 'yatco_warm_cache_hook' );
        if ( $scheduled ) {
            wp_unschedule_event( $scheduled, 'yatco_warm_cache_hook' );
        }
        wp_clear_scheduled_hook( 'yatco_warm_cache_hook' );
        
        // Clear progress and status - the running process will detect the stop flag and stop itself
        delete_transient( 'yatco_cache_warming_progress' );
        delete_transient( 'yatco_cache_warming_status' );
        
        // Flush rewrite rules to ensure permalinks work correctly after stopping
        flush_rewrite_rules( false );
        
        echo '<div class="notice notice-success"><p><strong>Stop signal sent!</strong> Scheduled events have been cancelled. The import process will stop at the next check point (within 1-5 seconds). Progress has been reset.</p></div>';
        $is_running = false;
        $has_active_status = false;
        $has_active_progress = false;
    }
    
    if ( isset( $_POST['yatco_clear_all'] ) && check_admin_referer( 'yatco_clear_all', 'yatco_clear_all_nonce' ) ) {
        // Set stop flag for running processes - keep it active for 5 minutes
        set_transient( 'yatco_cache_warming_stop', true, 300 );
        
        wp_clear_scheduled_hook( 'yatco_warm_cache_hook' );
        wp_clear_scheduled_hook( 'yatco_auto_refresh_cache_hook' );
        
        delete_transient( 'yatco_cache_warming_progress' );
        delete_transient( 'yatco_cache_warming_status' );
        
        // Flush rewrite rules to ensure permalinks work correctly after stopping
        flush_rewrite_rules( false );
        
        echo '<div class="notice notice-success"><p><strong>All cleared!</strong> All scheduled events and progress data have been cleared. Stop signal sent to any running processes. Permalinks have been refreshed. The import button should now be enabled.</p></div>';
        
        $cache_status = false;
        $cache_progress = false;
        $is_warming_scheduled = false;
        $has_active_status = false;
        $has_active_progress = false;
        $is_running = false;
    }
    
    // Check for stuck progress and show clear button
    $has_stuck_progress = $is_stuck || ( $has_active_progress && isset( $cache_progress['timestamp'] ) && ( time() - intval( $cache_progress['timestamp'] ) > 60 ) );
    
    if ( $has_stuck_progress ) {
        echo '<div class="notice notice-warning" style="margin-bottom: 15px;">';
        echo '<p><strong>⚠️ Stuck progress detected!</strong> The previous import appears to have crashed or stopped. Please clear it before starting a new import.</p>';
        echo '<form method="post" style="margin-top: 10px; display: inline-block;">';
        wp_nonce_field( 'yatco_clear_all', 'yatco_clear_all_nonce' );
        submit_button( '🛑 Clear Stuck Progress & Start Fresh', 'primary', 'yatco_clear_all', false, array( 
            'style' => 'background: #dc3232; border-color: #dc3232; color: #fff; font-weight: bold;'
        ) );
        echo '</form>';
        echo '</div>';
    }
    
    echo '<div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 15px; margin: 15px 0;">';
    echo '<h3 style="margin-top: 0;">Run Cache Warming Directly</h3>';
    echo '<p>This will run the cache warming function synchronously. <strong>Warning:</strong> This may take a long time and will block the page until complete.</p>';
    echo '<form method="post" action="' . esc_url( admin_url( 'options-general.php?page=yatco_api' ) ) . '" style="margin-top: 15px;" id="yatco_cache_warming_direct_form">';
    wp_nonce_field( 'yatco_warm_cache', 'yatco_warm_cache_nonce' );
    submit_button( '▶️ Run Cache Warming Function NOW (Direct)', 'primary', 'yatco_run_cache_warming_direct', false, array( 
        'id' => 'yatco_direct_cache_warming_btn',
        'style' => 'font-size: 14px; padding: 10px 20px; height: auto; font-weight: bold;'
    ) );
    echo '</form>';
    echo '</div>';
    
    // Debug: Check if form was submitted
    if ( isset( $_POST['yatco_run_cache_warming_direct'] ) ) {
        // Check nonce
        if ( ! check_admin_referer( 'yatco_warm_cache', 'yatco_warm_cache_nonce' ) ) {
            echo '<div class="notice notice-error"><p><strong>Security check failed!</strong> Please refresh the page and try again.</p></div>';
        } elseif ( empty( $token ) ) {
            echo '<div class="notice notice-error"><p><strong>Error:</strong> Missing API token. Please configure your API token in the settings above.</p></div>';
        } else {
            if ( ! function_exists( 'yatco_warm_cache_function' ) ) {
                require_once YATCO_PLUGIN_DIR . 'includes/yatco-cache.php';
            }
            
            // Mark this as a direct run
            if ( ! defined( 'YATCO_DIRECT_RUN' ) ) {
                define( 'YATCO_DIRECT_RUN', true );
            }
            
            // Clear any stale progress data first
            delete_transient( 'yatco_cache_warming_progress' );
            delete_transient( 'yatco_cache_warming_status' );
            delete_transient( 'yatco_cache_warming_stop' );
            
            set_transient( 'yatco_cache_warming_status', 'Starting direct import (limited to 50 vessels per run)...', 600 );
            set_transient( 'yatco_cache_warming_progress', array( 
                'last_processed' => 0, 
                'total' => 0, 
                'timestamp' => time(),
                'vessel_ids' => array()
            ), 600 );
            
            echo '<div class="notice notice-info">';
            echo '<p><strong>Starting direct cache warming...</strong></p>';
            echo '<p><strong>Note:</strong> Direct runs are limited to 50 vessels at a time to prevent timeouts. Click the button again to continue processing more vessels, or use WP-Cron for automatic full imports.</p>';
            echo '<p>This will run synchronously (blocking) and may take a few minutes. <strong>Do not close this page.</strong></p>';
            echo '</div>';
            
            // Force output buffering to show message immediately
            if ( ob_get_level() > 0 ) {
                ob_flush();
            }
            flush();
            
            $is_running = true;
            
            // Try to call the function and catch any fatal errors
            try {
                yatco_warm_cache_function();
            } catch ( Exception $e ) {
                set_transient( 'yatco_cache_warming_status', 'Error: ' . $e->getMessage(), 300 );
                echo '<div class="notice notice-error">';
                echo '<p><strong>Error during cache warming:</strong> ' . esc_html( $e->getMessage() ) . '</p>';
                echo '</div>';
            } catch ( Error $e ) {
                set_transient( 'yatco_cache_warming_status', 'Fatal Error: ' . $e->getMessage(), 300 );
                echo '<div class="notice notice-error">';
                echo '<p><strong>Fatal error during cache warming:</strong> ' . esc_html( $e->getMessage() ) . '</p>';
                echo '</div>';
            }
            
            echo '<div class="notice notice-success">';
            echo '<p><strong>Cache warming completed!</strong> Check the progress section below for details.</p>';
            echo '</div>';
            
            $cache_status = get_transient( 'yatco_cache_warming_status' );
            $cache_progress = get_transient( 'yatco_cache_warming_progress' );
        }
    }
    
    if ( $is_running ) {
        echo '<form method="post" style="margin-bottom: 15px;">';
        wp_nonce_field( 'yatco_stop_import', 'yatco_stop_import_nonce' );
        submit_button( '🛑 Stop Import', 'secondary', 'yatco_stop_import', false, array( 'style' => 'background: #dc3232; border-color: #dc3232; color: #fff;' ) );
        echo '<p style="font-size: 12px; color: #666; margin: 5px 0;">This will cancel the scheduled import and clear all progress. Vessels already imported to CPT will remain.</p>';
        echo '</form>';
    }
    
    echo '<hr />';
    echo '<h2>Cache Status & Progress</h2>';
    
    if ( $cache_status !== false ) {
        echo '<div class="notice notice-info">';
        echo '<p><strong>Status:</strong> ' . esc_html( $cache_status ) . '</p>';
        echo '</div>';
    } else {
        echo '<p>No status recorded.</p>';
    }
    
    if ( $cache_progress !== false && is_array( $cache_progress ) ) {
        $last_processed = isset( $cache_progress['last_processed'] ) ? intval( $cache_progress['last_processed'] ) : 0;
        $total = isset( $cache_progress['total'] ) ? intval( $cache_progress['total'] ) : 0;
        $timestamp = isset( $cache_progress['timestamp'] ) ? intval( $cache_progress['timestamp'] ) : 0;
        $is_progress_stuck = false;
        
        // Check if progress is stuck (hasn't been updated in 30 minutes)
        if ( $timestamp > 0 && ( time() - $timestamp > 1800 ) && $last_processed < $total ) {
            $is_progress_stuck = true;
        }
        
        if ( $total > 0 ) {
            $percent = round( ( $last_processed / $total ) * 100, 1 );
            echo '<div style="background: #f9f9f9; border: 1px solid #ddd; padding: 20px; margin: 20px 0;">';
            echo '<h3>Progress</h3>';
            
            if ( $is_progress_stuck ) {
                echo '<div class="notice notice-warning" style="margin: 10px 0; padding: 10px;">';
                echo '<p><strong>⚠️ Import appears to be stuck!</strong> Progress hasn\'t been updated in over 30 minutes. The process may have crashed. Please click "Stop Import" below and restart if needed.</p>';
                echo '</div>';
            }
            
            echo '<p>Processed: <strong>' . number_format( $last_processed ) . ' / ' . number_format( $total ) . '</strong> vessels (' . $percent . '%)</p>';
            
            if ( $timestamp > 0 ) {
                $last_update = human_time_diff( $timestamp, time() );
                echo '<p style="color: #666; font-size: 12px;">Last updated: ' . $last_update . ' ago</p>';
            }
            
            $progress_width = min( $percent, 100 );
            $progress_color = $is_progress_stuck ? '#dc3232' : '#2271b1';
            echo '<div style="background: #e0e0e0; border-radius: 4px; height: 30px; width: 100%; position: relative; overflow: hidden;">';
            echo '<div style="background: ' . $progress_color . '; height: 100%; width: ' . $progress_width . '%; transition: width 0.3s ease; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: bold; font-size: 12px;">' . $percent . '%</div>';
            echo '</div>';
            
            if ( ( $is_running || $is_progress_stuck ) && $last_processed < $total ) {
                echo '<form method="post" style="margin-top: 15px;">';
                wp_nonce_field( 'yatco_stop_import', 'yatco_stop_import_nonce' );
                echo '<button type="submit" name="yatco_stop_import" class="button button-secondary" style="background: #dc3232; border-color: #dc3232; color: #fff; font-weight: bold; padding: 8px 16px;">🛑 Stop Import Now</button>';
                echo '</form>';
            }
            
            // Support both old format (vessels) and new format (vessel_ids)
            $has_vessel_list = false;
            if ( isset( $cache_progress['vessels'] ) && is_array( $cache_progress['vessels'] ) && ! empty( $cache_progress['vessels'] ) ) {
                $has_vessel_list = true;
                $recent_vessels = array_slice( $cache_progress['vessels'], -10 );
                echo '<h4 style="margin-top: 20px;">Recently Processed Vessels</h4>';
                echo '<table class="widefat fixed striped" style="margin-top: 10px;">';
                echo '<thead><tr><th>Vessel ID</th><th>Name</th><th>Price</th><th>Year</th><th>LOA</th></tr></thead>';
                echo '<tbody>';
                foreach ( $recent_vessels as $vessel ) {
                    echo '<tr>';
                    echo '<td>' . esc_html( isset( $vessel['id'] ) ? $vessel['id'] : '' ) . '</td>';
                    echo '<td>' . esc_html( isset( $vessel['name'] ) ? $vessel['name'] : '' ) . '</td>';
                    echo '<td>' . esc_html( isset( $vessel['price'] ) ? $vessel['price'] : '' ) . '</td>';
                    echo '<td>' . esc_html( isset( $vessel['year'] ) ? $vessel['year'] : '' ) . '</td>';
                    echo '<td>' . esc_html( isset( $vessel['loa'] ) ? $vessel['loa'] : '' ) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody>';
                echo '</table>';
                
                if ( count( $cache_progress['vessels'] ) > 10 ) {
                    echo '<p style="font-size: 12px; color: #666; margin-top: 10px;">Showing last 10 vessels. Total processed: ' . count( $cache_progress['vessels'] ) . '</p>';
                }
            }
            
            echo '</div>';
        } else {
            echo '<p>Progress data exists but total is 0.</p>';
        }
    } else {
        echo '<p>No progress recorded.</p>';
    }
    
    echo '<hr />';
    echo '<h2>Cache Management</h2>';
    
    if ( isset( $_POST['yatco_warm_cache'] ) && check_admin_referer( 'yatco_warm_cache', 'yatco_warm_cache_nonce' ) ) {
        if ( empty( $token ) ) {
            echo '<div class="notice notice-error"><p>Missing token. Please configure your API token first.</p></div>';
        } else {
            delete_transient( 'yatco_cache_warming_progress' );
            delete_transient( 'yatco_cache_warming_status' );
            
            if ( function_exists( 'set_transient' ) ) {
                set_transient( 'yatco_cache_warming_status', 'Starting cache warm-up...', 600 );
            }
            
            wp_schedule_single_event( time(), 'yatco_warm_cache_hook' );
            
            spawn_cron();
            
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
            
            $cpt_count = wp_count_posts( 'yacht' );
            $published_count = isset( $cpt_count->publish ) ? intval( $cpt_count->publish ) : 0;
            
            echo '<div class="notice notice-info"><p><strong>CPT import started!</strong> This will run in the background and may take several minutes for 7000+ vessels.</p>';
            echo '<p>Current yacht posts in CPT: <strong>' . number_format( $published_count ) . '</strong></p>';
            echo '<p>The system processes vessels in batches of 20 to prevent timeouts. Progress is saved automatically, so if interrupted, it will resume from where it left off.</p>';
            echo '<p><em>Note: If progress doesn\'t appear within 30 seconds, try clicking "Import All Vessels to CPT" again or check if WP-Cron is enabled on your server.</em></p></div>';
        }
    }
    
    echo '<form method="post" style="margin-top: 10px;">';
    wp_nonce_field( 'yatco_clear_cache', 'yatco_clear_cache_nonce' );
    submit_button( 'Clear Transient Cache Only', 'secondary', 'yatco_clear_cache' );
    echo '<p style="font-size: 12px; color: #666; margin: 5px 0;">This clears only transient caches. CPT posts remain unchanged.</p>';
    echo '</form>';
    
    if ( isset( $_POST['yatco_clear_cache'] ) && check_admin_referer( 'yatco_clear_cache', 'yatco_clear_cache_nonce' ) ) {
        delete_transient( 'yatco_cache_warming_progress' );
        delete_transient( 'yatco_cache_warming_status' );
        echo '<div class="notice notice-success"><p><strong>Cache cleared!</strong> Transient caches have been cleared. CPT posts remain unchanged.</p></div>';
    }
    
    echo '<hr />';
    echo '<h2>🔧 Troubleshooting & Diagnostics</h2>';
    echo '<div style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin: 20px 0;">';
    
    $cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
    echo '<h3>WP-Cron Status</h3>';
    echo '<table class="widefat" style="margin-bottom: 20px;">';
    echo '<tr><th style="text-align: left; width: 250px;">Check</th><th style="text-align: left;">Status</th></tr>';
    
    echo '<tr><td><strong>WP-Cron Enabled:</strong></td><td>';
    if ( $cron_disabled ) {
        echo '<span style="color: #dc3232;">❌ DISABLED</span>';
    } else {
        echo '<span style="color: #46b450;">✔ ENABLED</span>';
    }
    echo '</td></tr>';
    
    echo '<tr><td><strong>spawn_cron() Available:</strong></td><td>';
    if ( function_exists( 'spawn_cron' ) ) {
        echo '<span style="color: #46b450;">✔ Available</span>';
    } else {
        echo '<span style="color: #dc3232;">❌ Not Available</span>';
    }
    echo '</td></tr>';
    
    echo '<tr><td><strong>Cache Warming Function:</strong></td><td>';
    if ( function_exists( 'yatco_warm_cache_function' ) ) {
        echo '<span style="color: #46b450;">✔ Available</span>';
    } else {
        echo '<span style="color: #dc3232;">❌ Not Available</span>';
    }
    echo '</td></tr>';
    
    echo '<tr><td><strong>Hook Registered:</strong></td><td>';
    if ( isset( $wp_filter['yatco_warm_cache_hook'] ) ) {
        echo '<span style="color: #46b450;">✔ Registered</span>';
    } else {
        echo '<span style="color: #dc3232;">❌ Not Registered</span>';
    }
    echo '</td></tr>';
    
    $scheduled = wp_next_scheduled( 'yatco_warm_cache_hook' );
    echo '<tr><td><strong>Scheduled Events:</strong></td><td>';
    if ( $scheduled ) {
        echo '<span style="color: #ff9800;">⚠ Scheduled for ' . date( 'Y-m-d H:i:s', $scheduled ) . '</span>';
    } else {
        echo 'None scheduled';
    }
    echo '</td></tr>';
    
    echo '<tr><td><strong>Last Status:</strong></td><td>';
    if ( $cache_status !== false ) {
        echo esc_html( $cache_status );
    } else {
        echo 'No status recorded';
    }
    echo '</td></tr>';
    
    echo '<tr><td><strong>Last Progress:</strong></td><td>';
    if ( $cache_progress !== false && is_array( $cache_progress ) ) {
        $last_processed = isset( $cache_progress['last_processed'] ) ? intval( $cache_progress['last_processed'] ) : 0;
        $total = isset( $cache_progress['total'] ) ? intval( $cache_progress['total'] ) : 0;
        echo number_format( $last_processed ) . ' / ' . number_format( $total );
    } else {
        echo 'No progress recorded';
    }
    echo '</td></tr>';
    
    echo '</table>';
    
    echo '<h3>Manual Testing</h3>';
    echo '<p>Use these buttons to test if the system is working:</p>';
    
    echo '<form method="post" style="margin-bottom: 15px;">';
    wp_nonce_field( 'yatco_test_function', 'yatco_test_function_nonce' );
    submit_button( 'Test Function & API Connection', 'secondary', 'yatco_test_function' );
    echo '</form>';
    
    if ( isset( $_POST['yatco_test_function'] ) && check_admin_referer( 'yatco_test_function', 'yatco_test_function_nonce' ) ) {
        if ( empty( $token ) ) {
            echo '<div class="notice notice-error"><p>Missing token.</p></div>';
        } else {
            if ( function_exists( 'yatco_warm_cache_function' ) ) {
                echo '<div class="notice notice-success"><p>✅ Cache warming function is available.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>❌ Cache warming function is NOT available. Check if yatco-cache.php is loaded.</p></div>';
            }
            
            if ( isset( $wp_filter['yatco_warm_cache_hook'] ) ) {
                echo '<div class="notice notice-success"><p>✅ Hook is registered.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>❌ Hook is NOT registered.</p></div>';
            }
            
            $scheduled = wp_next_scheduled( 'yatco_warm_cache_hook' );
            if ( $scheduled ) {
                echo '<div class="notice notice-info"><p>⚠️ A scheduled event exists for ' . date( 'Y-m-d H:i:s', $scheduled ) . '</p></div>';
            } else {
                echo '<div class="notice notice-info"><p>ℹ️ No scheduled events found.</p></div>';
            }
            
            if ( function_exists( 'yatco_get_active_vessel_ids' ) ) {
                $test_token = $token;
                $test_ids = yatco_get_active_vessel_ids( $test_token, 5 );
                if ( is_wp_error( $test_ids ) ) {
                    echo '<div class="notice notice-error"><p>❌ Error fetching vessel IDs: ' . esc_html( $test_ids->get_error_message() ) . '</p></div>';
                } elseif ( empty( $test_ids ) ) {
                    echo '<div class="notice notice-warning"><p>⚠️ No vessel IDs returned.</p></div>';
                } else {
                    echo '<div class="notice notice-success"><p>✅ Successfully fetched ' . count( $test_ids ) . ' vessel ID(s).</p></div>';
                }
            }
        }
    }
    
    echo '<form method="post" style="margin-bottom: 15px;">';
    wp_nonce_field( 'yatco_test_cron', 'yatco_test_cron_nonce' );
    submit_button( 'Test WP-Cron', 'secondary', 'yatco_test_cron' );
    echo '</form>';
    
    if ( isset( $_POST['yatco_test_cron'] ) && check_admin_referer( 'yatco_test_cron', 'yatco_test_cron_nonce' ) ) {
        $test_key = 'yatco_test_cron_' . time();
        set_transient( $test_key, 'not_run', 60 );
        
        wp_schedule_single_event( time(), 'yatco_test_cron_hook' );
        
        add_action( 'yatco_test_cron_hook', function() use ( $test_key ) {
            set_transient( $test_key, 'ran_successfully', 60 );
        } );
        
        echo '<p>Testing WP-Cron...</p>';
        echo '<div style="background: #fff; border: 1px solid #ddd; padding: 10px; font-family: monospace; font-size: 12px;">';
        
        if ( function_exists( 'spawn_cron' ) ) {
            echo '✅ spawn_cron() Available<br />';
            spawn_cron();
            echo '✅ spawn_cron() called<br />';
        } else {
            echo '❌ spawn_cron() NOT Available<br />';
        }
        
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
            echo '<span style="color: #dc3232; font-weight: bold; font-size: 14px;">❌ FAILED! WP-Cron did not run. The test hook did not execute.</span><br />';
            echo '<p style="margin: 5px 0; color: #666; font-size: 12px;">This means WP-Cron is likely disabled or not working on your server. You should use the "Run Directly" button or "Run Cache Warming Function NOW (Direct)" instead.</p>';
            echo '<p style="margin: 5px 0; color: #666; font-size: 12px;"><strong>Solution:</strong> Set up a real cron job on your server to call <code>wp-cron.php</code> every 5-15 minutes, or use the "Run Directly" button for manual imports.</p>';
        }
        
        echo '</div>';
        
        delete_transient( $test_key );
    }
    
    echo '<h3>Manual Cache Warming (Direct)</h3>';
    echo '<p>If WP-Cron is not working, you can run the cache warming function directly. This will block until complete.</p>';
    
    echo '<form method="post" style="margin-bottom: 15px;">';
    wp_nonce_field( 'yatco_manual_trigger', 'yatco_manual_trigger_nonce' );
    submit_button( 'Run Cache Warming Function NOW (Direct)', 'primary large', 'yatco_manual_trigger', false, array( 'style' => 'font-size: 14px; padding: 8px 16px; height: auto;' ) );
    echo '</form>';
    
    if ( isset( $_POST['yatco_manual_trigger'] ) && check_admin_referer( 'yatco_manual_trigger', 'yatco_manual_trigger_nonce' ) ) {
        if ( empty( $token ) ) {
            echo '<div class="notice notice-error"><p>Missing token.</p></div>';
        } else {
            if ( ! function_exists( 'yatco_warm_cache_function' ) ) {
                require_once YATCO_PLUGIN_DIR . 'includes/yatco-cache.php';
            }
            
            set_transient( 'yatco_cache_warming_status', 'Starting direct cache warm-up...', 600 );
            set_transient( 'yatco_cache_warming_progress', array( 'last_processed' => 0, 'total' => 0, 'timestamp' => time() ), 600 );
            
            echo '<div class="notice notice-info">';
            echo '<p><strong>Starting direct cache warming...</strong></p>';
            echo '<p>This will run synchronously (blocking) and may take several minutes. <strong>Do not close this page.</strong></p>';
            echo '</div>';
            
            ob_flush();
            flush();
            
            $is_running = true;
            
            echo '<form method="post" style="margin: 10px 0;">';
            wp_nonce_field( 'yatco_stop_import', 'yatco_stop_import_nonce' );
            echo '<button type="submit" name="yatco_stop_import" class="button button-secondary" style="background: #dc3232; border-color: #dc3232; color: #fff; font-weight: bold; padding: 8px 16px;">🛑 Stop Import Now</button>';
            echo '</form>';
            
            yatco_warm_cache_function();
            
            $final_status = get_transient( 'yatco_cache_warming_status' );
            $final_progress = get_transient( 'yatco_cache_warming_progress' );
            
            echo '<div class="notice notice-success">';
            echo '<p><strong>Direct cache warming completed!</strong></p>';
            if ( $final_progress !== false && is_array( $final_progress ) ) {
                $processed = isset( $final_progress['processed'] ) ? intval( $final_progress['processed'] ) : 0;
                echo '<p>Total vessels processed: <strong>' . number_format( $processed ) . '</strong></p>';
            }
            echo '</div>';
            
            $is_running = false;
        }
    }
    
    echo '<h3>Real Cron Setup (If WP-Cron Disabled)</h3>';
    echo '<div style="background: #fff; border: 1px solid #ddd; padding: 15px; margin: 10px 0;">';
    echo '<p>If WP-Cron is disabled on your server, you can set up a real cron job to trigger the cache warming automatically.</p>';
    echo '<p><strong>Option 1: Using curl</strong></p>';
    echo '<pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow-x: auto;">*/15 * * * * curl -s ' . esc_url( site_url( 'wp-cron.php?doing_wp_cron' ) ) . ' > /dev/null 2>&1</pre>';
    echo '<p><strong>Option 2: Using wget</strong></p>';
    echo '<pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow-x: auto;">*/15 * * * * wget -q -O - ' . esc_url( site_url( 'wp-cron.php?doing_wp_cron' ) ) . ' > /dev/null 2>&1</pre>';
    echo '<p><strong>Option 3: Using wp-cli (if available)</strong></p>';
    echo '<pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow-x: auto;">*/15 * * * * cd ' . esc_html( ABSPATH ) . ' && wp cron event run --due-now</pre>';
    echo '<p style="font-size: 12px; color: #666; margin-top: 10px;">Note: Replace <code>*/15</code> with your desired frequency (e.g., <code>*/5</code> for every 5 minutes). Add this to your server\'s crontab using <code>crontab -e</code>.</p>';
    echo '</div>';
    
    echo '</div>';
    
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
    
    delete_transient( 'yatco_cache_warming_progress' );
    delete_transient( 'yatco_cache_warming_status' );
    
    set_transient( 'yatco_cache_warming_status', 'Starting cache warm-up...', 600 );
    
    wp_schedule_single_event( time(), 'yatco_warm_cache_hook' );
    spawn_cron();
    
    wp_send_json_success( array( 'message' => 'Cache warming started' ) );
}
add_action( 'wp_ajax_yatco_trigger_cache_warming', 'yatco_ajax_trigger_cache_warming' );

/**
 * AJAX handler to run cache warming directly (synchronous - for testing).
 */
function yatco_ajax_run_cache_warming_direct() {
    check_ajax_referer( 'yatco_run_warming_direct', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        return;
    }
    
    if ( ! function_exists( 'yatco_warm_cache_function' ) ) {
        require_once YATCO_PLUGIN_DIR . 'includes/yatco-cache.php';
    }
    
    set_transient( 'yatco_cache_warming_status', 'Starting direct cache warm-up...', 600 );
    yatco_warm_cache_function();
    
    wp_send_json_success( array( 'message' => 'Direct cache warming completed' ) );
}
add_action( 'wp_ajax_yatco_run_cache_warming_direct', 'yatco_ajax_run_cache_warming_direct' );

/**
 * AJAX handler to get cache warming status and progress.
 */
function yatco_ajax_get_cache_status() {
    check_ajax_referer( 'yatco_get_status', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        return;
    }
    
    $response = array();
    
    $status = get_transient( 'yatco_cache_warming_status' );
    if ( $status !== false ) {
        $response['status'] = $status;
    }
    
    $cache_progress = get_transient( 'yatco_cache_warming_progress' );
    if ( $cache_progress !== false && is_array( $cache_progress ) ) {
        $last_processed = isset( $cache_progress['last_processed'] ) ? intval( $cache_progress['last_processed'] ) : 0;
        $total = isset( $cache_progress['total'] ) ? intval( $cache_progress['total'] ) : 0;
        $start_time = isset( $cache_progress['start_time'] ) ? intval( $cache_progress['start_time'] ) : time();
        $current_time = time();
        $elapsed = $current_time - $start_time;
        
        $response['progress'] = array(
            'last_processed' => $last_processed,
            'total'         => $total,
            'processed'     => $cache_progress['processed'] ?? 0,
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

/**
 * Add meta box to yacht edit screen with Update Vessel button.
 */
function yatco_add_update_vessel_meta_box() {
    add_meta_box(
        'yatco_update_vessel',
        'YATCO Vessel Update',
        'yatco_update_vessel_meta_box_callback',
        'yacht',
        'side',
        'high'
    );
}

/**
 * Meta box callback - displays Update Vessel button.
 */
function yatco_update_vessel_meta_box_callback( $post ) {
    // Check if this is a YATCO vessel
    $vessel_id = get_post_meta( $post->ID, 'yacht_vessel_id', true );
    $mlsid = get_post_meta( $post->ID, 'yacht_mlsid', true );
    
    if ( empty( $vessel_id ) ) {
        echo '<p>This yacht post does not have a YATCO vessel ID. It may not have been imported from YATCO.</p>';
        return;
    }
    
    // Get YATCO listing URL from stored meta, or build it if not stored
    $yatco_listing_url = get_post_meta( $post->ID, 'yacht_yatco_listing_url', true );
    if ( empty( $yatco_listing_url ) ) {
        // Build YATCO listing URL using helper function
        $length = get_post_meta( $post->ID, 'yacht_length_feet', true );
        $builder = get_post_meta( $post->ID, 'yacht_make', true );
        $category = get_post_meta( $post->ID, 'yacht_sub_category', true );
        if ( empty( $category ) ) {
            $category = get_post_meta( $post->ID, 'yacht_category', true );
        }
        if ( empty( $category ) ) {
            $category = get_post_meta( $post->ID, 'yacht_type', true );
        }
        $year = get_post_meta( $post->ID, 'yacht_year', true );
        
        if ( ! function_exists( 'yatco_build_listing_url' ) ) {
            require_once YATCO_PLUGIN_DIR . 'includes/yatco-helpers.php';
        }
        
        $yatco_listing_url = yatco_build_listing_url( $post->ID, $mlsid, $vessel_id, $length, $builder, $category, $year );
        // Save it for future use
        if ( ! empty( $yatco_listing_url ) ) {
            update_post_meta( $post->ID, 'yacht_yatco_listing_url', $yatco_listing_url );
        }
    }
    
    // Display link to original YATCO listing - make it very visible
    echo '<div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 15px; margin-bottom: 20px; border-radius: 4px;">';
    echo '<p style="margin: 0 0 12px 0; font-weight: bold; font-size: 14px; color: #2271b1;">🔗 View Original Listing on YATCO</p>';
    
    // Show IDs for reference
    echo '<div style="margin-bottom: 12px; padding: 8px; background: #fff; border-radius: 3px; font-size: 12px;">';
    if ( ! empty( $mlsid ) ) {
        echo '<strong>MLS ID:</strong> <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;">' . esc_html( $mlsid ) . '</code><br />';
    }
    echo '<strong>Vessel ID:</strong> <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;">' . esc_html( $vessel_id ) . '</code>';
    echo '</div>';
    
    // Large, prominent link button
    echo '<p style="margin: 0;">';
    echo '<a href="' . esc_url( $yatco_listing_url ) . '" target="_blank" rel="noopener noreferrer" class="button button-primary" style="width: 100%; text-align: center; padding: 10px; font-size: 14px; font-weight: bold; text-decoration: none; display: block; box-sizing: border-box;">';
    echo '🌐 Open Original YATCO Listing';
    echo '</a>';
    echo '</p>';
    
    // Also show the URL as a clickable link below
    echo '<p style="margin: 8px 0 0 0; font-size: 11px; color: #666; word-break: break-all;">';
    echo 'Link: <a href="' . esc_url( $yatco_listing_url ) . '" target="_blank" rel="noopener noreferrer" style="color: #2271b1; text-decoration: underline;">' . esc_html( $yatco_listing_url ) . '</a>';
    echo '</p>';
    echo '</div>';
    
    $token = yatco_get_token();
    if ( empty( $token ) ) {
        echo '<p style="color: #d63638;"><strong>Error:</strong> YATCO API token is not configured. Please set it in <a href="' . esc_url( admin_url( 'options-general.php?page=yatco_api' ) ) . '">Settings → YATCO API</a>.</p>';
        return;
    }
    
    // Check if update was just performed
    if ( isset( $_GET['yatco_updated'] ) && $_GET['yatco_updated'] === '1' ) {
        echo '<div class="notice notice-success inline" style="margin: 10px 0;"><p><strong>✓ Vessel updated successfully!</strong></p></div>';
    }
    
    if ( isset( $_GET['yatco_update_error'] ) ) {
        $error_msg = sanitize_text_field( $_GET['yatco_update_error'] );
        echo '<div class="notice notice-error inline" style="margin: 10px 0;"><p><strong>Error:</strong> ' . esc_html( $error_msg ) . '</p></div>';
    }
    
    $last_updated = get_post_meta( $post->ID, 'yacht_last_updated', true );
    if ( $last_updated ) {
        $last_updated_date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_updated );
        echo '<p style="font-size: 12px; color: #666; margin-bottom: 15px;"><strong>Last Updated:</strong> ' . esc_html( $last_updated_date ) . '</p>';
    }
    
    echo '<p>Click the button below to fetch the latest data for this vessel from the YATCO API and update all fields.</p>';
    
    $update_url = wp_nonce_url(
        admin_url( 'admin-post.php?action=yatco_update_vessel&post_id=' . $post->ID ),
        'yatco_update_vessel_' . $post->ID
    );
    
    echo '<p><a href="' . esc_url( $update_url ) . '" class="button button-primary button-large" style="width: 100%; text-align: center;">🔄 Update Vessel from YATCO</a></p>';
    
    echo '<p style="font-size: 11px; color: #666; margin-top: 10px;">This will update all meta fields, images, and taxonomy terms with the latest data from YATCO.</p>';
}

/**
 * Add meta box for editing detailed specifications (Overview).
 */
function yatco_add_detailed_specs_meta_box() {
    add_meta_box(
        'yatco_detailed_specs',
        'Overview / Detailed Specifications',
        'yatco_detailed_specs_meta_box_callback',
        'yacht',
        'normal',
        'high'
    );
}

/**
 * Meta box callback - displays WYSIWYG editor for detailed specifications.
 */
function yatco_detailed_specs_meta_box_callback( $post ) {
    // Add nonce for security
    wp_nonce_field( 'yatco_save_detailed_specs', 'yatco_detailed_specs_nonce' );
    
    // Get current value
    $detailed_specs = get_post_meta( $post->ID, 'yacht_detailed_specifications', true );
    
    // If no content yet, show a helpful message
    if ( empty( $detailed_specs ) ) {
        echo '<p style="padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 15px;"><strong>No overview content yet.</strong> This content will be automatically imported when you click "Update Vessel from YATCO" in the sidebar. You can also manually add content here.</p>';
    }
    
    // Settings for wp_editor
    // The textarea_name must match the field name we check in save function
    $editor_settings = array(
        'textarea_name' => 'yacht_detailed_specifications', // This sets the form field name
        'textarea_rows' => 20,
        'media_buttons' => true,
        'teeny' => false,
        'tinymce' => array(
            'toolbar1' => 'bold,italic,underline,strikethrough,bullist,numlist,blockquote,hr,alignleft,aligncenter,alignright,link,unlink,wp_adv',
            'toolbar2' => 'formatselect,fontselect,fontsizeselect,forecolor,backcolor,pastetext,removeformat,charmap,outdent,indent,undo,redo',
            'toolbar3' => '',
            'toolbar4' => '',
        ),
        'quicktags' => true,
    );
    
    echo '<p style="margin-bottom: 15px; color: #666;">Edit the overview and detailed specifications content. This content appears in the collapsible "View Full Overview" section on the frontend.</p>';
    
    // Output the editor
    // The second parameter is the editor ID (used for DOM element ID)
    // The textarea_name in settings ensures the form field name is correct
    wp_editor( $detailed_specs, 'yacht_detailed_specifications_editor', $editor_settings );
    
    echo '<p style="margin-top: 15px; font-size: 12px; color: #666;"><strong>Note:</strong> This content will be displayed in a toggle section on the frontend. HTML tags are preserved.</p>';
    
    // Show content info
    if ( ! empty( $detailed_specs ) ) {
        echo '<p style="margin-top: 10px; font-size: 11px; color: #999;">Current content: ' . strlen( $detailed_specs ) . ' characters</p>';
    }
}

/**
 * Save detailed specifications when post is saved.
 */
function yatco_save_detailed_specs( $post_id ) {
    // Check if this is an autosave
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    
    // Check if this is a revision
    if ( wp_is_post_revision( $post_id ) ) {
        return;
    }
    
    // Check post type
    if ( get_post_type( $post_id ) !== 'yacht' ) {
        return;
    }
    
    // Check user permissions
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    
    // Verify nonce
    if ( ! isset( $_POST['yatco_detailed_specs_nonce'] ) || ! wp_verify_nonce( $_POST['yatco_detailed_specs_nonce'], 'yatco_save_detailed_specs' ) ) {
        return;
    }
    
    // Save the meta field
    // wp_editor submits content with the textarea_name, which is 'yacht_detailed_specifications'
    if ( isset( $_POST['yacht_detailed_specifications'] ) ) {
        // Sanitize content (allows HTML but removes dangerous tags)
        $detailed_specs = wp_kses_post( $_POST['yacht_detailed_specifications'] );
        update_post_meta( $post_id, 'yacht_detailed_specifications', $detailed_specs );
    } else {
        // If field is empty string, allow it to be cleared (empty content is valid)
        // This handles the case where user clears all content
        if ( isset( $_POST['yacht_detailed_specifications'] ) && $_POST['yacht_detailed_specifications'] === '' ) {
            update_post_meta( $post_id, 'yacht_detailed_specifications', '' );
        }
        // If field is not set at all, don't modify existing value
    }
}

/**
 * Handle Update Vessel request.
 */
function yatco_handle_update_vessel() {
    // Check user permissions
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_die( 'You do not have permission to update vessels.' );
    }
    
    // Get post ID
    $post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;
    
    if ( ! $post_id ) {
        wp_redirect( admin_url( 'edit.php?post_type=yacht&yatco_update_error=' . urlencode( 'Invalid post ID' ) ) );
        exit;
    }
    
    // Verify nonce
    $nonce = isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '';
    if ( ! wp_verify_nonce( $nonce, 'yatco_update_vessel_' . $post_id ) ) {
        wp_redirect( admin_url( 'post.php?post=' . $post_id . '&action=edit&yatco_update_error=' . urlencode( 'Security check failed' ) ) );
        exit;
    }
    
    // Check if post exists and is a yacht
    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'yacht' ) {
        wp_redirect( admin_url( 'edit.php?post_type=yacht&yatco_update_error=' . urlencode( 'Post not found or not a yacht' ) ) );
        exit;
    }
    
    // Get vessel ID
    $vessel_id = get_post_meta( $post_id, 'yacht_vessel_id', true );
    
    if ( empty( $vessel_id ) ) {
        wp_redirect( admin_url( 'post.php?post=' . $post_id . '&action=edit&yatco_update_error=' . urlencode( 'No YATCO vessel ID found for this post' ) ) );
        exit;
    }
    
    // Get API token
    $token = yatco_get_token();
    if ( empty( $token ) ) {
        wp_redirect( admin_url( 'post.php?post=' . $post_id . '&action=edit&yatco_update_error=' . urlencode( 'YATCO API token not configured' ) ) );
        exit;
    }
    
    // Update the vessel
    require_once YATCO_PLUGIN_DIR . 'includes/yatco-helpers.php';
    $result = yatco_import_single_vessel( $token, $vessel_id );
    
    if ( is_wp_error( $result ) ) {
        wp_redirect( admin_url( 'post.php?post=' . $post_id . '&action=edit&yatco_update_error=' . urlencode( $result->get_error_message() ) ) );
        exit;
    }
    
    // Success - redirect back to edit screen with success message
    wp_redirect( admin_url( 'post.php?post=' . $post_id . '&action=edit&yatco_updated=1' ) );
    exit;
}