<?php
/**
 * Cache Management
 * 
 * Handles cache warming, refreshing, and scheduling.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Warm cache function - pre-loads all vessels into cache.
 * This runs in the background via WP-Cron or manually.
 * 
 * Update Process:
 * 1. Fetches all active vessel IDs from YATCO API
 * 2. For each vessel ID:
 *    - Calls yatco_import_single_vessel() which:
 *      a. Fetches latest vessel data from API
 *      b. Matches existing CPT post by MLSID or VesselID
 *      c. Updates existing post or creates new one
 *      d. Updates all metadata fields with latest data
 * 3. Processes in batches of 20 to prevent timeouts
 * 4. Saves progress every batch so it can resume if interrupted
 * 5. Calculates daily statistics (added/removed/updated vessels)
 * 
 * Matching Existing Vessels:
 * - Primary: Matches by MLSID (yacht_mlsid meta field)
 * - Fallback: Matches by VesselID (yacht_vessel_id meta field)
 * - This ensures vessels are properly updated even if MLSID changes
 * 
 * Updates are performed every time cache warming runs, ensuring CPT data
 * stays synchronized with YATCO API data.
 */
function yatco_warm_cache_function() {
    // Save initial status immediately
    if ( function_exists( 'set_transient' ) ) {
        set_transient( 'yatco_cache_warming_status', 'Starting cache warm-up...', 600 );
    }
    
    $token = yatco_get_token();
    if ( empty( $token ) ) {
        if ( function_exists( 'set_transient' ) ) {
            set_transient( 'yatco_cache_warming_status', 'Error: API token not configured', 60 );
        }
        return;
    }

    // Use default shortcode attributes
    $atts = array(
        'max'           => '999999',
        'price_min'     => '',
        'price_max'     => '',
        'year_min'      => '',
        'year_max'      => '',
        'loa_min'       => '',
        'loa_max'       => '',
        'columns'       => '3',
        'show_price'    => 'yes',
        'show_year'     => 'yes',
        'show_loa'      => 'yes',
        'cache'         => 'no', // Don't check cache when warming
        'show_filters'  => 'yes',
        'currency'      => 'USD',
        'length_unit'   => 'FT',
    );

    if ( function_exists( 'set_transient' ) ) {
        set_transient( 'yatco_cache_warming_status', 'Fetching vessel IDs...', 600 );
    }
    
    // Increase limits for cache warming
    @ini_set( 'max_execution_time', 0 ); // Unlimited
    @ini_set( 'memory_limit', '512M' ); // Increase memory
    @set_time_limit( 0 ); // Remove time limit
    
    // Fetch all vessel IDs
    $ids = yatco_get_active_vessel_ids( $token, 0 );
    
    if ( is_wp_error( $ids ) ) {
        if ( function_exists( 'set_transient' ) ) {
            set_transient( 'yatco_cache_warming_status', 'Error: ' . $ids->get_error_message(), 60 );
        }
        return;
    }

    $vessel_count = count( $ids );
    if ( function_exists( 'set_transient' ) ) {
        set_transient( 'yatco_cache_warming_status', "Processing {$vessel_count} vessels...", 600 );
    }
    
    // Save initial progress immediately (so we can see it started)
    $cache_key_progress = 'yatco_cache_warming_progress';
    $progress = get_transient( $cache_key_progress );
    $start_from = 0;
    $cached_vessel_ids = array();
    $start_time = time();
    
    if ( $progress !== false && is_array( $progress ) ) {
        $start_from = isset( $progress['last_processed'] ) ? intval( $progress['last_processed'] ) : 0;
        // Support both old format (vessels) and new format (vessel_ids) for backward compatibility
        if ( isset( $progress['vessel_ids'] ) && is_array( $progress['vessel_ids'] ) ) {
            $cached_vessel_ids = $progress['vessel_ids'];
        } elseif ( isset( $progress['vessels'] ) && is_array( $progress['vessels'] ) ) {
            // Extract IDs from old format
            foreach ( $progress['vessels'] as $vessel ) {
                if ( isset( $vessel['id'] ) ) {
                    $cached_vessel_ids[] = intval( $vessel['id'] );
                }
            }
        }
        $start_time = isset( $progress['start_time'] ) ? intval( $progress['start_time'] ) : time();
        $ids = array_slice( $ids, $start_from );
    } else {
        $start_time = time();
        // Save initial progress so we can see it started
        $initial_progress = array(
            'last_processed' => 0,
            'total'         => $vessel_count,
            'processed'     => 0,
            'vessel_ids'    => array(), // Use vessel_ids instead of vessels
            'start_time'    => $start_time,
            'timestamp'     => time(),
        );
        set_transient( $cache_key_progress, $initial_progress, 3600 );
    }

    // Process one vessel at a time with 45-second delay between vessels
    $processed = 0;
    $errors = 0;
    $delay_seconds = 45; // 45 seconds between vessels
    $vessel_ids = $cached_vessel_ids; // Start with previously processed IDs

    foreach ( $ids as $index => $id ) {
        $processed++;
        $actual_index = $start_from + $index;
        
        // Check if import was stopped (stop flag or transient was cleared)
        $stop_flag = get_transient( 'yatco_cache_warming_stop' );
        if ( $stop_flag !== false ) {
            // Stop flag was set, stop processing
            delete_transient( 'yatco_cache_warming_stop' );
            delete_transient( $cache_key_progress );
            if ( function_exists( 'set_transient' ) ) {
                set_transient( 'yatco_cache_warming_status', 'Import stopped by user.', 60 );
            }
            return;
        }
        
        $current_progress = get_transient( $cache_key_progress );
        if ( $current_progress === false && $actual_index > 0 ) {
            // Progress was cleared, stop processing
            if ( function_exists( 'set_transient' ) ) {
                set_transient( 'yatco_cache_warming_status', 'Import stopped (progress cleared).', 60 );
            }
            return;
        }
        
        // Check for timeout - if progress hasn't been updated in 30 minutes, consider it stuck
        if ( $current_progress !== false && is_array( $current_progress ) && isset( $current_progress['timestamp'] ) ) {
            $last_update = intval( $current_progress['timestamp'] );
            if ( ( time() - $last_update > 1800 ) && $actual_index > 0 ) {
                if ( function_exists( 'set_transient' ) ) {
                    set_transient( 'yatco_cache_warming_status', 'Import appears to be stuck (no progress for 30+ minutes). Please clear and restart.', 60 );
                }
                return;
            }
        }
        
        // Reset execution time
        @set_time_limit( 300 ); // 5 minutes per vessel
        
        // Clear memory periodically to prevent buildup
        if ( $processed % 10 == 0 ) {
            if ( function_exists( 'gc_collect_cycles' ) ) {
                gc_collect_cycles();
            }
        }

        try {
            // Import vessel to CPT
            $import_result = yatco_import_single_vessel( $token, $id );
            if ( is_wp_error( $import_result ) ) {
                $errors++;
                
                // Still track the vessel ID for statistics
                $vessel_ids[] = intval( $id );
                
                // Save progress even on error (only store essential data, not full vessel data)
                $progress_data = array(
                    'last_processed' => $actual_index + 1,
                    'total'         => $vessel_count,
                    'processed'     => count( $vessel_ids ),
                    'vessel_ids'    => $vessel_ids, // Only store IDs, not full data
                    'start_time'    => $start_time,
                    'timestamp'     => time(),
                    'errors'        => $errors,
                );
                if ( function_exists( 'set_transient' ) ) {
                    set_transient( $cache_key_progress, $progress_data, 3600 );
                }
                
                // Update status
                $percent = round( ( ( $actual_index + 1 ) / $vessel_count ) * 100, 1 );
                if ( function_exists( 'set_transient' ) ) {
                    set_transient( 'yatco_cache_warming_status', "Error importing vessel {$id}. Continuing... Processed " . ( $actual_index + 1 ) . " of {$vessel_count} ({$percent}%)...", 600 );
                }
                
                // Wait 45 seconds before next vessel (even on error)
                sleep( $delay_seconds );
                continue;
            }
            
            $post_id = $import_result;
            $vessel_ids[] = intval( $id ); // Store ID for statistics
            
            // Save progress after EACH vessel (only essential data, not full vessel data)
            $progress_data = array(
                'last_processed' => $actual_index + 1,
                'total'         => $vessel_count,
                'processed'     => count( $vessel_ids ),
                'vessel_ids'    => $vessel_ids, // Only store IDs, not full data
                'start_time'    => $start_time,
                'timestamp'     => time(),
                'errors'        => $errors,
            );
            if ( function_exists( 'set_transient' ) ) {
                set_transient( $cache_key_progress, $progress_data, 3600 );
            }
            
            // Update status after each vessel
            $percent = round( ( ( $actual_index + 1 ) / $vessel_count ) * 100, 1 );
            if ( function_exists( 'get_the_title' ) ) {
                $vessel_name = get_the_title( $post_id );
                $vessel_name_short = strlen( $vessel_name ) > 40 ? substr( $vessel_name, 0, 40 ) . '...' : $vessel_name;
                $status_msg = "Completed: {$vessel_name_short}. Processed " . ( $actual_index + 1 ) . " of {$vessel_count} ({$percent}%). Waiting 45 seconds before next vessel...";
            } else {
                $status_msg = "Processed " . ( $actual_index + 1 ) . " of {$vessel_count} ({$percent}%). Waiting 45 seconds before next vessel...";
            }
            if ( function_exists( 'set_transient' ) ) {
                set_transient( 'yatco_cache_warming_status', $status_msg, 600 );
            }
            
            // Reset execution time
            @set_time_limit( 300 );
            
            // Wait 45 seconds before processing next vessel (to prevent server overload)
            // Skip delay on last vessel
            if ( $index < count( $ids ) - 1 ) {
                sleep( $delay_seconds );
            }
        } catch ( Exception $e ) {
            // Handle exceptions gracefully
            $errors++;
            $vessel_ids[] = intval( $id );
            
            $progress_data = array(
                'last_processed' => $actual_index + 1,
                'total'         => $vessel_count,
                'processed'     => count( $vessel_ids ),
                'vessel_ids'    => $vessel_ids,
                'start_time'    => $start_time,
                'timestamp'     => time(),
                'errors'        => $errors,
            );
            if ( function_exists( 'set_transient' ) ) {
                set_transient( $cache_key_progress, $progress_data, 3600 );
                set_transient( 'yatco_cache_warming_status', "Exception importing vessel {$id}: " . $e->getMessage() . ". Continuing...", 600 );
            }
            
            if ( $index < count( $ids ) - 1 ) {
                sleep( $delay_seconds );
            }
        }
    }

    // Calculate daily statistics using vessel IDs only
    yatco_calculate_daily_stats_from_cpt( $vessel_ids );
    
    // Clear progress after successful completion
    delete_transient( $cache_key_progress );
    
    $total_processed = count( $vessel_ids );
    $total_cpt_posts = wp_count_posts( 'yacht' );
    $published_count = isset( $total_cpt_posts->publish ) ? intval( $total_cpt_posts->publish ) : 0;
    
    $success_msg = "Vessels imported to CPT successfully! Processed {$total_processed} vessels into {$published_count} yacht posts";
    if ( $errors > 0 ) {
        $success_msg .= " ({$errors} errors)";
    }
    if ( function_exists( 'set_transient' ) ) {
        set_transient( 'yatco_cache_warming_status', $success_msg, 300 );
    }
    
    // Flush rewrite rules to ensure permalinks work correctly
    flush_rewrite_rules( false );
}

/**
 * Calculate daily statistics from CPT vessel IDs (for new CPT-based system).
 */
function yatco_calculate_daily_stats_from_cpt( $current_vessel_ids ) {
    $today = date( 'Y-m-d' );
    $yesterday = date( 'Y-m-d', strtotime( '-1 day' ) );
    
    // Sort vessel IDs for comparison
    $current_ids = array_unique( array_map( 'intval', $current_vessel_ids ) );
    sort( $current_ids );
    
    // Get yesterday's vessel IDs
    $yesterday_stats = get_option( 'yatco_daily_stats_' . $yesterday, array() );
    $yesterday_ids = isset( $yesterday_stats['vessel_ids'] ) && is_array( $yesterday_stats['vessel_ids'] ) ? $yesterday_stats['vessel_ids'] : array();
    
    // Get today's existing stats (if any)
    $today_stats = get_option( 'yatco_daily_stats_' . $today, array() );
    
    // Calculate differences
    $added = array_diff( $current_ids, $yesterday_ids );
    $removed = array_diff( $yesterday_ids, $current_ids );
    
    // Calculate updated (vessels that exist in both)
    $existing = array_intersect( $current_ids, $yesterday_ids );
    
    // Store today's stats
    $stats = array(
        'date'       => $today,
        'total'      => count( $current_ids ),
        'added'      => count( $added ),
        'removed'    => count( $removed ),
        'updated'    => count( $existing ),
        'vessel_ids' => $current_ids,
        'timestamp'  => time(),
    );
    
    // Update existing stats if already exists (for multiple cache refreshes per day)
    if ( ! empty( $today_stats ) && is_array( $today_stats ) ) {
        // Increment added/removed counts
        $prev_added = isset( $today_stats['added'] ) ? intval( $today_stats['added'] ) : 0;
        $prev_removed = isset( $today_stats['removed'] ) ? intval( $today_stats['removed'] ) : 0;
        
        // Only count new additions/removals since last check
        $prev_ids = isset( $today_stats['vessel_ids'] ) && is_array( $today_stats['vessel_ids'] ) ? $today_stats['vessel_ids'] : array();
        $new_added = array_diff( $current_ids, $prev_ids );
        $new_removed = array_diff( $prev_ids, $current_ids );
        
        $stats['added'] = $prev_added + count( $new_added );
        $stats['removed'] = $prev_removed + count( $new_removed );
    }
    
    update_option( 'yatco_daily_stats_' . $today, $stats );
    
    // Clean up old stats (keep last 30 days)
    yatco_cleanup_old_stats();
}

/**
 * Calculate daily statistics by comparing current vessel list with previous day's list.
 */
function yatco_calculate_daily_stats( $current_vessels ) {
    $today = date( 'Y-m-d' );
    $yesterday = date( 'Y-m-d', strtotime( '-1 day' ) );
    
    // Get current vessel IDs
    $current_ids = array();
    foreach ( $current_vessels as $vessel ) {
        if ( ! empty( $vessel['id'] ) ) {
            $current_ids[] = intval( $vessel['id'] );
        }
    }
    $current_ids = array_unique( $current_ids );
    sort( $current_ids );
    
    // Get yesterday's vessel IDs
    $yesterday_stats = get_option( 'yatco_daily_stats_' . $yesterday, array() );
    $yesterday_ids = isset( $yesterday_stats['vessel_ids'] ) && is_array( $yesterday_stats['vessel_ids'] ) ? $yesterday_stats['vessel_ids'] : array();
    
    // Get today's existing stats (if any)
    $today_stats = get_option( 'yatco_daily_stats_' . $today, array() );
    
    // Calculate differences
    $added = array_diff( $current_ids, $yesterday_ids );
    $removed = array_diff( $yesterday_ids, $current_ids );
    
    // Calculate updated (vessels that exist in both but may have changed)
    // For simplicity, we'll count vessels that exist in both as potentially updated
    // In a more sophisticated system, we could compare hash values or timestamps
    $existing = array_intersect( $current_ids, $yesterday_ids );
    
    // Store today's stats
    $stats = array(
        'date'       => $today,
        'total'      => count( $current_ids ),
        'added'      => count( $added ),
        'removed'    => count( $removed ),
        'updated'    => count( $existing ), // Simplified: count existing as potentially updated
        'vessel_ids' => $current_ids,
        'timestamp'  => time(),
    );
    
    // Update existing stats if already exists (for multiple cache refreshes per day)
    if ( ! empty( $today_stats ) && is_array( $today_stats ) ) {
        // Increment added/removed counts
        $prev_added = isset( $today_stats['added'] ) ? intval( $today_stats['added'] ) : 0;
        $prev_removed = isset( $today_stats['removed'] ) ? intval( $today_stats['removed'] ) : 0;
        
        // Only count new additions/removals since last check
        $prev_ids = isset( $today_stats['vessel_ids'] ) && is_array( $today_stats['vessel_ids'] ) ? $today_stats['vessel_ids'] : array();
        $new_added = array_diff( $current_ids, $prev_ids );
        $new_removed = array_diff( $prev_ids, $current_ids );
        
        $stats['added'] = $prev_added + count( $new_added );
        $stats['removed'] = $prev_removed + count( $new_removed );
    }
    
    update_option( 'yatco_daily_stats_' . $today, $stats );
    
    // Clean up old stats (keep last 30 days)
    yatco_cleanup_old_stats();
}

/**
 * Get daily statistics for display.
 */
function yatco_get_daily_stats( $days = 7 ) {
    $stats = array();
    $today = date( 'Y-m-d' );
    
    for ( $i = 0; $i < $days; $i++ ) {
        $date = date( 'Y-m-d', strtotime( "-{$i} days" ) );
        $day_stats = get_option( 'yatco_daily_stats_' . $date, null );
        
        if ( $day_stats !== null && is_array( $day_stats ) ) {
            $stats[ $date ] = array(
                'total'   => isset( $day_stats['total'] ) ? intval( $day_stats['total'] ) : 0,
                'added'   => isset( $day_stats['added'] ) ? intval( $day_stats['added'] ) : 0,
                'removed' => isset( $day_stats['removed'] ) ? intval( $day_stats['removed'] ) : 0,
                'updated' => isset( $day_stats['updated'] ) ? intval( $day_stats['updated'] ) : 0,
            );
        }
    }
    
    return $stats;
}

/**
 * Clean up old daily statistics (keep last 30 days).
 */
function yatco_cleanup_old_stats() {
    global $wpdb;
    
    // Delete stats older than 30 days
    $cutoff_date = date( 'Y-m-d', strtotime( '-30 days' ) );
    $cutoff_timestamp = strtotime( $cutoff_date );
    
    $stats = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            'yatco_daily_stats_%'
        ),
        ARRAY_A
    );
    
    foreach ( $stats as $stat ) {
        $option_name = $stat['option_name'];
        // Extract date from option name (format: yatco_daily_stats_YYYY-MM-DD)
        if ( preg_match( '/yatco_daily_stats_(\d{4}-\d{2}-\d{2})/', $option_name, $matches ) ) {
            $stat_date = $matches[1];
            $stat_timestamp = strtotime( $stat_date );
            
            if ( $stat_timestamp < $cutoff_timestamp ) {
                delete_option( $option_name );
            }
        }
    }
}

/**
 * Schedule periodic cache refresh if enabled.
 */
function yatco_maybe_schedule_cache_refresh() {
    $options = get_option( 'yatco_api_settings' );
    $auto_refresh = isset( $options['yatco_auto_refresh_cache'] ) && $options['yatco_auto_refresh_cache'] === 'yes';
    
    if ( $auto_refresh ) {
        if ( ! wp_next_scheduled( 'yatco_auto_refresh_cache_hook' ) ) {
            wp_schedule_event( time(), 'yatco_six_hours', 'yatco_auto_refresh_cache_hook' );
        }
    } else {
        $timestamp = wp_next_scheduled( 'yatco_auto_refresh_cache_hook' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'yatco_auto_refresh_cache_hook' );
        }
    }
}

/**
 * Register custom cron schedule for 6 hours.
 */
function yatco_add_six_hour_schedule( $schedules ) {
    $schedules['yatco_six_hours'] = array(
        'interval' => 21600, // 6 hours in seconds
        'display'  => 'Every 6 Hours',
    );
    return $schedules;
}

// Register custom cron schedule for 6 hours
add_filter( 'cron_schedules', 'yatco_add_six_hour_schedule' );

// Hook for auto-refresh
add_action( 'yatco_auto_refresh_cache_hook', 'yatco_warm_cache_function' );
