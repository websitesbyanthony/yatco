<?php
/**
 * API-Only Mode (No Database Storage)
 * 
 * This module provides an optimized API-only approach that:
 * 1. Never saves data to WordPress database (no CPT posts)
 * 2. Never downloads images (uses external URLs)
 * 3. Uses lightweight transients for short-term caching (auto-expire)
 * 4. Fetches only what's needed for display
 * 5. Supports single vessel pages via query parameters
 * 
 * Storage: Only transients (auto-expire, minimal storage)
 * Performance: Caches vessel IDs and individual vessel data temporarily
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get cache duration settings for API-only mode.
 * 
 * @return array Array with 'vessel_ids_duration' and 'vessel_data_duration' in seconds
 */
function yatco_api_only_get_cache_durations() {
    $options = get_option( 'yatco_api_settings', array() );
    
    // Vessel ID list cache duration (default: 6 hours)
    // This detects removed/sold vessels (they disappear from activevesselmlsid endpoint)
    $vessel_ids_duration = isset( $options['yatco_api_only_ids_cache'] ) ? intval( $options['yatco_api_only_ids_cache'] ) : 21600; // 6 hours default
    
    // Individual vessel data cache duration (default: 1 hour)
    // This detects price changes, days on market updates, status changes
    $vessel_data_duration = isset( $options['yatco_api_only_data_cache'] ) ? intval( $options['yatco_api_only_data_cache'] ) : 3600; // 1 hour default
    
    return array(
        'vessel_ids_duration' => $vessel_ids_duration,
        'vessel_data_duration' => $vessel_data_duration,
    );
}

/**
 * Get all active vessel IDs with caching.
 * 
 * CHANGE DETECTION:
 * - Removed/Sold vessels: Detected when they disappear from activevesselmlsid endpoint
 * - New vessels: Detected when they appear in activevesselmlsid endpoint
 * - Cache duration: Configurable (default 6 hours)
 * 
 * How it works:
 * 1. Fetches list of active vessel IDs from YATCO API
 * 2. If a vessel ID is missing from the list, it's been removed/sold
 * 3. If a new vessel ID appears, it's a new listing
 * 4. Caches the ID list to avoid repeated API calls
 * 
 * @param string $token API token
 * @return array|WP_Error Array of vessel IDs
 */
function yatco_api_only_get_vessel_ids( $token ) {
    $cache_key = 'yatco_api_only_vessel_ids';
    $cached_ids = get_transient( $cache_key );
    
    if ( $cached_ids !== false && is_array( $cached_ids ) ) {
        return $cached_ids;
    }
    
    // Fetch all vessel IDs (no limit)
    // This endpoint only returns ACTIVE vessels, so removed/sold vessels won't appear
    $ids = yatco_get_active_vessel_ids( $token, 0 );
    
    if ( is_wp_error( $ids ) ) {
        return $ids;
    }
    
    // Get cache duration from settings
    $durations = yatco_api_only_get_cache_durations();
    $cache_duration = $durations['vessel_ids_duration'];
    
    // Cache the ID list
    set_transient( $cache_key, $ids, $cache_duration );
    
    return $ids;
}

/**
 * Get vessel data from API with short-term caching.
 * 
 * CHANGE DETECTION:
 * - Price changes: Detected when price fields change in API response
 * - Days on market: Updated automatically (increments daily in API)
 * - Status changes: Detected via StatusText field (e.g., "Sold", "Under Contract")
 * - Sold status: Detected when StatusText contains "Sold" or vessel removed from active list
 * - Other updates: All fields refreshed when cache expires
 * 
 * Cache duration: Configurable (default 1 hour)
 * 
 * How it works:
 * 1. Checks cache first (if within cache duration, returns cached data)
 * 2. If cache expired, fetches fresh data from API
 * 3. API always returns latest data (price, days on market, status, etc.)
 * 4. Caches the fresh data for the configured duration
 * 
 * @param string $token API token
 * @param int    $vessel_id Vessel ID
 * @param bool   $force_refresh Force refresh even if cache exists
 * @return array|WP_Error Vessel data array
 */
function yatco_api_only_get_vessel_data( $token, $vessel_id, $force_refresh = false ) {
    $cache_key = 'yatco_api_only_vessel_' . $vessel_id;
    
    // Check cache unless forcing refresh
    if ( ! $force_refresh ) {
        $cached_data = get_transient( $cache_key );
        
        if ( $cached_data !== false && is_array( $cached_data ) ) {
            return $cached_data;
        }
    }
    
    // Fetch full specs from API (always gets latest data)
    $full = yatco_fetch_fullspecs( $token, $vessel_id );
    
    if ( is_wp_error( $full ) ) {
        return $full;
    }
    
    // Build vessel data array (same structure as CPT version)
    $result = isset( $full['Result'] ) ? $full['Result'] : array();
    $basic  = isset( $full['BasicInfo'] ) ? $full['BasicInfo'] : array();
    $dims   = isset( $full['Dimensions'] ) ? $full['Dimensions'] : array();
    $sections = isset( $full['Sections'] ) && is_array( $full['Sections'] ) ? $full['Sections'] : array();
    $engines = isset( $full['Engines'] ) && is_array( $full['Engines'] ) ? $full['Engines'] : array();
    $accommodations = isset( $full['Accommodations'] ) ? $full['Accommodations'] : array();
    $speed_weight = isset( $full['SpeedWeight'] ) ? $full['SpeedWeight'] : array();
    $hull_deck = isset( $full['HullDeck'] ) ? $full['HullDeck'] : array();
    
    // Build vessel data (same as yatco_import_single_vessel but without saving)
    $vessel_data = yatco_api_only_build_vessel_data( $vessel_id, $full, $result, $basic, $dims, $sections, $engines, $accommodations, $speed_weight, $hull_deck );
    
    // Get cache duration from settings
    $durations = yatco_api_only_get_cache_durations();
    $cache_duration = $durations['vessel_data_duration'];
    
    // Cache the fresh data
    set_transient( $cache_key, $vessel_data, $cache_duration );
    
    return $vessel_data;
}

/**
 * Build vessel data array from API response.
 * This is similar to yatco_import_single_vessel but doesn't save to database.
 * 
 * @param int   $vessel_id Vessel ID
 * @param array $full Full API response
 * @param array $result Result section
 * @param array $basic BasicInfo section
 * @param array $dims Dimensions section
 * @param array $sections Sections array
 * @param array $engines Engines array
 * @param array $accommodations Accommodations array
 * @param array $speed_weight SpeedWeight array
 * @param array $hull_deck HullDeck array
 * @return array Vessel data array
 */
function yatco_api_only_build_vessel_data( $vessel_id, $full, $result, $basic, $dims, $sections, $engines, $accommodations, $speed_weight, $hull_deck ) {
    // Get name
    $name = '';
    if ( ! empty( $basic['BoatName'] ) ) {
        $name = $basic['BoatName'];
    } elseif ( ! empty( $result['VesselName'] ) ) {
        $name = $result['VesselName'];
    }
    
    // Get prices
    $price_usd = isset( $basic['AskingPriceUSD'] ) && $basic['AskingPriceUSD'] > 0 ? floatval( $basic['AskingPriceUSD'] ) : null;
    if ( ! $price_usd && isset( $result['AskingPriceCompare'] ) && $result['AskingPriceCompare'] > 0 ) {
        $price_usd = floatval( $result['AskingPriceCompare'] );
    }
    $price_eur = isset( $basic['AskingPrice'] ) && $basic['AskingPrice'] > 0 && isset( $basic['Currency'] ) && $basic['Currency'] === 'EUR' ? floatval( $basic['AskingPrice'] ) : null;
    
    // Get year
    $year = '';
    if ( ! empty( $basic['YearBuilt'] ) ) {
        $year = $basic['YearBuilt'];
    } elseif ( ! empty( $basic['ModelYear'] ) ) {
        $year = $basic['ModelYear'];
    } elseif ( ! empty( $result['YearBuilt'] ) ) {
        $year = $result['YearBuilt'];
    } elseif ( ! empty( $result['Year'] ) ) {
        $year = $result['Year'];
    }
    
    // Get LOA
    $loa_feet = isset( $result['LOAFeet'] ) && $result['LOAFeet'] > 0 ? floatval( $result['LOAFeet'] ) : null;
    $loa_meters = isset( $result['LOAMeters'] ) && $result['LOAMeters'] > 0 ? floatval( $result['LOAMeters'] ) : null;
    if ( ! $loa_meters && $loa_feet ) {
        $loa_meters = $loa_feet * 0.3048;
    }
    
    // Get MLSID
    $mlsid = '';
    if ( ! empty( $result['MLSID'] ) ) {
        $mlsid = $result['MLSID'];
    } elseif ( ! empty( $result['VesselID'] ) ) {
        $mlsid = $result['VesselID'];
    }
    
    // Get builder, category, type
    $builder = '';
    if ( ! empty( $basic['Builder'] ) ) {
        $builder = $basic['Builder'];
    } elseif ( ! empty( $result['BuilderName'] ) ) {
        $builder = $result['BuilderName'];
    }
    
    $category = '';
    if ( ! empty( $basic['MainCategory'] ) ) {
        $category = $basic['MainCategory'];
    } elseif ( ! empty( $result['MainCategoryText'] ) ) {
        $category = $result['MainCategoryText'];
    }
    
    $sub_category = '';
    if ( ! empty( $basic['SubCategory'] ) ) {
        $sub_category = $basic['SubCategory'];
    } elseif ( ! empty( $result['SubCategoryText'] ) ) {
        $sub_category = $result['SubCategoryText'];
    }
    
    $type = '';
    if ( ! empty( $basic['VesselTypeText'] ) ) {
        $type = $basic['VesselTypeText'];
    } elseif ( ! empty( $result['VesselTypeText'] ) ) {
        $type = $result['VesselTypeText'];
    }
    
    $condition = isset( $result['VesselCondition'] ) ? $result['VesselCondition'] : '';
    $location = isset( $basic['LocationCustom'] ) ? $basic['LocationCustom'] : '';
    $location_city = isset( $basic['LocationCity'] ) ? $basic['LocationCity'] : ( isset( $result['LocationCity'] ) ? $result['LocationCity'] : '' );
    $location_state = isset( $basic['LocationState'] ) ? $basic['LocationState'] : ( isset( $result['LocationState'] ) ? $result['LocationState'] : '' );
    $location_country = isset( $basic['LocationCountry'] ) ? $basic['LocationCountry'] : ( isset( $result['LocationCountry'] ) ? $result['LocationCountry'] : '' );
    $state_rooms = isset( $basic['StateRooms'] ) ? intval( $basic['StateRooms'] ) : ( isset( $result['StateRooms'] ) ? intval( $result['StateRooms'] ) : 0 );
    
    // Get model
    $model = '';
    if ( ! empty( $basic['Model'] ) ) {
        $model = $basic['Model'];
    } elseif ( ! empty( $result['Model'] ) ) {
        $model = $result['Model'];
    } elseif ( ! empty( $basic['ModelName'] ) ) {
        $model = $basic['ModelName'];
    } elseif ( ! empty( $result['ModelName'] ) ) {
        $model = $result['ModelName'];
    }
    
    // Build description from sections (same logic as import function)
    $desc = '';
    $detailed_specs = '';
    if ( ! empty( $sections ) ) {
        $desc_parts = array();
        $specs_parts = array();
        
        usort( $sections, function( $a, $b ) {
            $order_a = isset( $a['SortOrder'] ) ? intval( $a['SortOrder'] ) : 999;
            $order_b = isset( $b['SortOrder'] ) ? intval( $b['SortOrder'] ) : 999;
            return $order_a - $order_b;
        } );
        
        $description_sections = array( 'Description' );
        $excluded_sections = array( 'Overview', 'Specifications', 'Equipment', 'Features' );
        
        foreach ( $sections as $section ) {
            if ( empty( $section['SectionText'] ) ) {
                continue;
            }
            
            $section_name = isset( $section['SectionName'] ) ? trim( $section['SectionName'] ) : '';
            $section_text = trim( $section['SectionText'] );
            $section_text = yatco_strip_inline_styles_and_classes( $section_text );
            
            $is_excluded = false;
            if ( ! empty( $section_name ) ) {
                $section_name_lower = strtolower( $section_name );
                foreach ( $excluded_sections as $excluded ) {
                    if ( $section_name_lower === strtolower( $excluded ) ) {
                        $is_excluded = true;
                        break;
                    }
                }
            }
            
            if ( $is_excluded ) {
                if ( ! empty( $section_name ) && stripos( $section_text, '<h2' ) === false && stripos( $section_text, '<h3' ) === false ) {
                    $specs_parts[] = '<h2>' . esc_html( $section_name ) . '</h2>';
                }
                $specs_parts[] = $section_text;
            } else {
                $is_description_section = false;
                if ( empty( $section_name ) ) {
                    $is_description_section = true;
                } else {
                    $section_name_lower = strtolower( $section_name );
                    foreach ( $description_sections as $desc_section ) {
                        if ( $section_name_lower === strtolower( $desc_section ) ) {
                            $is_description_section = true;
                            break;
                        }
                    }
                }
                
                if ( $is_description_section ) {
                    if ( ! empty( $section_name ) && stripos( $section_text, '<h2' ) === false && stripos( $section_text, '<h3' ) === false ) {
                        $desc_parts[] = '<h2>' . esc_html( $section_name ) . '</h2>';
                    }
                    $desc_parts[] = $section_text;
                } else {
                    if ( ! empty( $section_name ) && stripos( $section_text, '<h2' ) === false && stripos( $section_text, '<h3' ) === false ) {
                        $specs_parts[] = '<h2>' . esc_html( $section_name ) . '</h2>';
                    }
                    $specs_parts[] = $section_text;
                }
            }
        }
        
        if ( ! empty( $desc_parts ) ) {
            $desc = implode( "\n\n", $desc_parts );
        }
        
        if ( ! empty( $specs_parts ) ) {
            $detailed_specs = implode( "\n\n", $specs_parts );
        }
    }
    
    // Get additional fields
    $beam = '';
    if ( ! empty( $dims['Beam'] ) ) {
        $beam = $dims['Beam'];
    } elseif ( ! empty( $dims['BeamFeet'] ) ) {
        $beam = $dims['BeamFeet'];
    }
    
    $gross_tonnage = '';
    if ( isset( $result['GrossTonnage'] ) && $result['GrossTonnage'] !== 0 ) {
        $gross_tonnage = $result['GrossTonnage'];
    } elseif ( isset( $dims['GrossTonnage'] ) && $dims['GrossTonnage'] !== 0 ) {
        $gross_tonnage = $dims['GrossTonnage'];
    }
    
    $hull_material = isset( $result['HullMaterial'] ) ? $result['HullMaterial'] : ( isset( $dims['HullMaterial'] ) ? $dims['HullMaterial'] : '' );
    
    // Engine info
    $engine_count = count( $engines );
    $engine_manufacturer = '';
    $engine_model = '';
    $engine_type = '';
    $engine_fuel_type = '';
    $engine_horsepower = '';
    
    if ( ! empty( $engines ) && isset( $engines[0] ) ) {
        $first_engine = $engines[0];
        $engine_manufacturer = isset( $first_engine['Manufacturer'] ) ? $first_engine['Manufacturer'] : '';
        $engine_model = isset( $first_engine['Model'] ) ? trim( $first_engine['Model'] ) : '';
        $engine_type = isset( $first_engine['EngineType'] ) ? $first_engine['EngineType'] : '';
        $engine_fuel_type = isset( $first_engine['FuelType'] ) ? $first_engine['FuelType'] : '';
        
        if ( isset( $first_engine['Horsepower'] ) && $first_engine['Horsepower'] > 0 ) {
            $hp = intval( $first_engine['Horsepower'] );
            if ( $engine_count > 1 ) {
                $total_hp = $hp * $engine_count;
                $engine_horsepower = $total_hp . ' HP';
            } else {
                $engine_horsepower = $hp . ' HP';
            }
        }
    }
    
    // Accommodations
    $heads = isset( $accommodations['HeadsValue'] ) && $accommodations['HeadsValue'] > 0 ? intval( $accommodations['HeadsValue'] ) : 0;
    $sleeps = isset( $accommodations['SleepsValue'] ) && $accommodations['SleepsValue'] > 0 ? intval( $accommodations['SleepsValue'] ) : 0;
    $berths = isset( $accommodations['BerthsValue'] ) && $accommodations['BerthsValue'] > 0 ? intval( $accommodations['BerthsValue'] ) : 0;
    
    // Speed and weight
    $cruise_speed = isset( $speed_weight['CruiseSpeed'] ) ? $speed_weight['CruiseSpeed'] : '';
    $max_speed = isset( $speed_weight['MaxSpeed'] ) ? $speed_weight['MaxSpeed'] : '';
    $fuel_capacity = isset( $speed_weight['FuelCapacity'] ) ? $speed_weight['FuelCapacity'] : '';
    $water_capacity = isset( $speed_weight['WaterCapacity'] ) ? $speed_weight['WaterCapacity'] : '';
    $holding_tank = isset( $speed_weight['HoldingTank'] ) ? $speed_weight['HoldingTank'] : '';
    
    // Images - store URLs only (never download)
    $image_url = isset( $result['MainPhotoUrl'] ) ? $result['MainPhotoUrl'] : ( isset( $basic['MainPhotoURL'] ) ? $basic['MainPhotoURL'] : '' );
    $gallery_images = array();
    if ( isset( $full['PhotoGallery'] ) && is_array( $full['PhotoGallery'] ) && ! empty( $full['PhotoGallery'] ) ) {
        foreach ( $full['PhotoGallery'] as $photo ) {
            $url = '';
            if ( ! empty( $photo['largeImageURL'] ) ) {
                $url = $photo['largeImageURL'];
            } elseif ( ! empty( $photo['mediumImageURL'] ) ) {
                $url = $photo['mediumImageURL'];
            } elseif ( ! empty( $photo['smallImageURL'] ) ) {
                $url = $photo['smallImageURL'];
            }
            
            if ( ! empty( $url ) ) {
                $gallery_images[] = array(
                    'url'     => $url,
                    'caption' => isset( $photo['Caption'] ) ? $photo['Caption'] : '',
                );
            }
        }
    }
    
    // Videos
    $videos = array();
    if ( isset( $result['Videos'] ) && is_array( $result['Videos'] ) ) {
        $videos = $result['Videos'];
    } elseif ( isset( $basic['Videos'] ) && is_array( $basic['Videos'] ) ) {
        $videos = $basic['Videos'];
    }
    
    // Broker info
    $broker_first_name = isset( $result['BrokerFirstName'] ) ? $result['BrokerFirstName'] : ( isset( $basic['BrokerFirstName'] ) ? $basic['BrokerFirstName'] : '' );
    $broker_last_name = isset( $result['BrokerLastName'] ) ? $result['BrokerLastName'] : ( isset( $basic['BrokerLastName'] ) ? $basic['BrokerLastName'] : '' );
    $broker_phone = isset( $result['BrokerPhone'] ) ? $result['BrokerPhone'] : ( isset( $basic['BrokerPhone'] ) ? $basic['BrokerPhone'] : '' );
    $broker_email = isset( $result['BrokerEmail'] ) ? $result['BrokerEmail'] : ( isset( $basic['BrokerEmail'] ) ? $basic['BrokerEmail'] : '' );
    $broker_photo_url = isset( $result['BrokerPhotoUrl'] ) ? $result['BrokerPhotoUrl'] : ( isset( $basic['BrokerPhotoUrl'] ) ? $basic['BrokerPhotoUrl'] : '' );
    
    // Company info
    $company_name = isset( $result['CompanyName'] ) ? $result['CompanyName'] : ( isset( $basic['CompanyName'] ) ? $basic['CompanyName'] : '' );
    $company_logo_url = isset( $result['CompanyLogoUrl'] ) ? $result['CompanyLogoUrl'] : ( isset( $basic['CompanyLogoUrl'] ) ? $basic['CompanyLogoUrl'] : '' );
    $company_address = isset( $result['CompanyAddress'] ) ? $result['CompanyAddress'] : ( isset( $basic['CompanyAddress'] ) ? $basic['CompanyAddress'] : '' );
    $company_website = isset( $result['CompanyWebsite'] ) ? $result['CompanyWebsite'] : ( isset( $basic['CompanyWebsite'] ) ? $basic['CompanyWebsite'] : '' );
    $company_phone = isset( $result['CompanyPhone'] ) ? $result['CompanyPhone'] : ( isset( $basic['CompanyPhone'] ) ? $basic['CompanyPhone'] : '' );
    $company_email = isset( $result['CompanyEmail'] ) ? $result['CompanyEmail'] : ( isset( $basic['CompanyEmail'] ) ? $basic['CompanyEmail'] : '' );
    
    // Builder description
    $builder_description = isset( $result['BuilderDescription'] ) ? $result['BuilderDescription'] : '';
    
    // Status info
    $status_text = isset( $result['StatusText'] ) ? $result['StatusText'] : ( isset( $basic['StatusText'] ) ? $basic['StatusText'] : '' );
    $agreement_type = isset( $result['AgreementType'] ) ? $result['AgreementType'] : ( isset( $basic['AgreementType'] ) ? $basic['AgreementType'] : '' );
    $days_on_market = isset( $result['DaysOnMarket'] ) ? intval( $result['DaysOnMarket'] ) : ( isset( $basic['DaysOnMarket'] ) ? intval( $basic['DaysOnMarket'] ) : 0 );
    
    // Virtual tour
    $virtual_tour_url = isset( $result['VirtualTourUrl'] ) ? $result['VirtualTourUrl'] : ( isset( $basic['VirtualTourUrl'] ) ? $basic['VirtualTourUrl'] : '' );
    
    // Price formatting
    $currency = isset( $basic['Currency'] ) ? $basic['Currency'] : ( isset( $result['Currency'] ) ? $result['Currency'] : 'USD' );
    $price_formatted = isset( $result['AskingPriceFormatted'] ) ? $result['AskingPriceFormatted'] : '';
    $price_on_application = isset( $result['PriceOnApplication'] ) ? (bool) $result['PriceOnApplication'] : false;
    
    // Build YATCO listing URL
    $yatco_listing_url = yatco_build_listing_url( 0, $mlsid, $vessel_id, $loa_feet, $builder, $category, $year );
    
    // Return complete vessel data array (same structure as CPT meta fields)
    return array(
        'vessel_id' => $vessel_id,
        'mlsid' => $mlsid,
        'name' => $name,
        'year' => $year,
        'builder' => $builder,
        'model' => $model,
        'category' => $category,
        'sub_category' => $sub_category,
        'type' => $type,
        'condition' => $condition,
        'location' => $location,
        'location_city' => $location_city,
        'location_state' => $location_state,
        'location_country' => $location_country,
        'price_usd' => $price_usd,
        'price_eur' => $price_eur,
        'price_formatted' => $price_formatted,
        'currency' => $currency,
        'price_on_application' => $price_on_application,
        'loa_feet' => $loa_feet,
        'loa_meters' => $loa_meters,
        'beam' => $beam,
        'gross_tonnage' => $gross_tonnage,
        'hull_material' => $hull_material,
        'state_rooms' => $state_rooms,
        'heads' => $heads,
        'sleeps' => $sleeps,
        'berths' => $berths,
        'engine_count' => $engine_count,
        'engine_manufacturer' => $engine_manufacturer,
        'engine_model' => $engine_model,
        'engine_type' => $engine_type,
        'engine_fuel_type' => $engine_fuel_type,
        'engine_horsepower' => $engine_horsepower,
        'cruise_speed' => $cruise_speed,
        'max_speed' => $max_speed,
        'fuel_capacity' => $fuel_capacity,
        'water_capacity' => $water_capacity,
        'holding_tank' => $holding_tank,
        'image_url' => $image_url,
        'gallery_images' => $gallery_images,
        'videos' => $videos,
        'description' => $desc,
        'detailed_specifications' => $detailed_specs,
        'broker_first_name' => $broker_first_name,
        'broker_last_name' => $broker_last_name,
        'broker_phone' => $broker_phone,
        'broker_email' => $broker_email,
        'broker_photo_url' => $broker_photo_url,
        'company_name' => $company_name,
        'company_logo_url' => $company_logo_url,
        'company_address' => $company_address,
        'company_website' => $company_website,
        'company_phone' => $company_phone,
        'company_email' => $company_email,
        'builder_description' => $builder_description,
        'status_text' => $status_text,
        'agreement_type' => $agreement_type,
        'days_on_market' => $days_on_market,
        'virtual_tour_url' => $virtual_tour_url,
        'yatco_listing_url' => $yatco_listing_url,
        'fullspecs_raw' => $full, // Store full response for reference
    );
}

/**
 * Clear all API-only caches.
 * Useful for forcing fresh data immediately.
 */
function yatco_api_only_clear_cache() {
    global $wpdb;
    
    // Delete all transients starting with yatco_api_only_
    $wpdb->query( 
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_yatco_api_only_%' 
         OR option_name LIKE '_transient_timeout_yatco_api_only_%'"
    );
}

/**
 * Detect changes in vessel list (new/removed vessels).
 * Compares current vessel IDs with cached list.
 * 
 * @param string $token API token
 * @return array Array with 'added', 'removed', 'total' keys
 */
function yatco_api_only_detect_vessel_changes( $token ) {
    // Get current vessel IDs (may fetch from API if cache expired)
    $current_ids = yatco_api_only_get_vessel_ids( $token );
    
    if ( is_wp_error( $current_ids ) ) {
        return $current_ids;
    }
    
    // Get last known vessel IDs from a separate cache
    $last_ids_key = 'yatco_api_only_last_vessel_ids';
    $last_ids = get_transient( $last_ids_key );
    
    if ( $last_ids === false || ! is_array( $last_ids ) ) {
        // First run - save current IDs as baseline
        set_transient( $last_ids_key, $current_ids, 86400 ); // Keep for 24 hours
        return array(
            'added' => count( $current_ids ),
            'removed' => 0,
            'total' => count( $current_ids ),
            'message' => 'First check - all vessels marked as new',
        );
    }
    
    // Compare lists
    $current_ids_sorted = array_unique( array_map( 'intval', $current_ids ) );
    $last_ids_sorted = array_unique( array_map( 'intval', $last_ids ) );
    sort( $current_ids_sorted );
    sort( $last_ids_sorted );
    
    $added = array_diff( $current_ids_sorted, $last_ids_sorted );
    $removed = array_diff( $last_ids_sorted, $current_ids_sorted );
    
    // Update last known IDs
    set_transient( $last_ids_key, $current_ids, 86400 );
    
    return array(
        'added' => count( $added ),
        'removed' => count( $removed ),
        'total' => count( $current_ids_sorted ),
        'added_ids' => array_values( $added ),
        'removed_ids' => array_values( $removed ),
    );
}

/**
 * Check if a vessel is sold or removed.
 * 
 * @param string $token API token
 * @param int    $vessel_id Vessel ID to check
 * @return array|WP_Error Array with 'is_active', 'is_sold', 'status' keys
 */
function yatco_api_only_check_vessel_status( $token, $vessel_id ) {
    // Get current active vessel IDs
    $active_ids = yatco_api_only_get_vessel_ids( $token );
    
    if ( is_wp_error( $active_ids ) ) {
        return $active_ids;
    }
    
    $is_active = in_array( intval( $vessel_id ), array_map( 'intval', $active_ids ) );
    
    if ( ! $is_active ) {
        // Vessel not in active list - could be sold or removed
        return array(
            'is_active' => false,
            'is_sold' => true, // Assume sold if not in active list
            'status' => 'removed_or_sold',
            'message' => 'Vessel is not in active listings (may be sold or removed)',
        );
    }
    
    // Vessel is active - check status from API
    $vessel_data = yatco_api_only_get_vessel_data( $token, $vessel_id );
    
    if ( is_wp_error( $vessel_data ) ) {
        return $vessel_data;
    }
    
    $status_text = isset( $vessel_data['status_text'] ) ? strtolower( $vessel_data['status_text'] ) : '';
    $is_sold = ( strpos( $status_text, 'sold' ) !== false ) || ( strpos( $status_text, 'under contract' ) !== false );
    
    return array(
        'is_active' => true,
        'is_sold' => $is_sold,
        'status' => $vessel_data['status_text'] ? $vessel_data['status_text'] : 'active',
        'status_text' => $vessel_data['status_text'],
        'days_on_market' => isset( $vessel_data['days_on_market'] ) ? $vessel_data['days_on_market'] : 0,
        'price_usd' => isset( $vessel_data['price_usd'] ) ? $vessel_data['price_usd'] : null,
    );
}

