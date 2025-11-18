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
    echo '<h2>Cache Management</h2>';
    echo '<p>Pre-load all vessels into cache to speed up the shortcode display. This may take several minutes for 7000+ vessels.</p>';
    echo '<form method="post">';
    wp_nonce_field( 'yatco_warm_cache', 'yatco_warm_cache_nonce' );
    submit_button( 'Warm Cache (Pre-load All Vessels)', 'primary', 'yatco_warm_cache' );
    echo '</form>';

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
            
            echo '<div class="notice notice-info"><p><strong>Cache warming started!</strong> This will run in the background and may take several minutes for 7000+ vessels.</p>';
            echo '<p>The system processes vessels in batches of 20 to prevent timeouts. Progress is saved automatically, so if interrupted, it will resume from where it left off.</p>';
            echo '<p><em>Note: If progress doesn\'t appear within 30 seconds, try clicking "Warm Cache" again or check if WP-Cron is enabled on your server.</em></p></div>';
        }
    }
    
    // Handle clear cache action
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
        
        echo '<div class="notice notice-success"><p>Cache cleared successfully!</p></div>';
    }

    // Check if cache warming is in progress
    $cache_status = get_transient( 'yatco_cache_warming_status' );
    $cache_progress = get_transient( 'yatco_cache_warming_progress' );
    $is_warming_scheduled = wp_next_scheduled( 'yatco_warm_cache_hook' );
    
    // Show progress tracker if warming is active, scheduled, or has progress
    if ( $cache_status || $cache_progress || $is_warming_scheduled ) {
        echo '<hr />';
        echo '<h2>Cache Warming Status</h2>';
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
                echo '<p class="yatco-progress-text" style="margin: 0; flex: 1;"><strong>Progress:</strong> Processed <span class="yatco-current-processed">' . number_format( $last_processed ) . '</span> of <span class="yatco-total-vessels">' . number_format( $total ) . '</span> vessels (<span class="yatco-percent">' . $percent . '</span>%). <span class="yatco-cached-count">' . number_format( $cached ) . '</span> vessels cached so far.</p>';
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

