<?php
/**
 * Helper Functions
 * 
 * Utility functions for data parsing and vessel importing.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Strip inline CSS styles and class attributes from HTML content.
 * Also removes span tags that only contain styling (unwraps their content).
 * 
 * @param string $html HTML content with inline styles and classes
 * @return string Clean HTML without style and class attributes, and spans unwrapped
 */
function yatco_strip_inline_styles_and_classes( $html ) {
    if ( empty( $html ) ) {
        return $html;
    }
    
    // First, unwrap span tags that only have style/class attributes (or no attributes after we strip them)
    // Pattern matches: <span class="..." style="...">content</span> or <span style="...">content</span>
    // This will unwrap spans that are purely for styling
    
    // Remove style and class from spans, then unwrap empty spans (spans with no other attributes)
    // We do this before removing attributes so we can identify spans that are purely for styling
    
    // Step 1: Remove style and class attributes from all tags
    $html = preg_replace( '/\s*style\s*=\s*["\'][^"\']*["\']/i', '', $html );
    $html = preg_replace( '/\s*class\s*=\s*["\'][^"\']*["\']/i', '', $html );
    
    // Step 2: Unwrap span tags that have no attributes (or only empty attributes)
    // Match opening span with optional whitespace, no attributes, then content, then closing span
    $html = preg_replace( '/<span\s*>\s*(.*?)\s*<\/span>/is', '$1', $html );
    
    // Also handle spans that might have empty attributes after stripping
    $html = preg_replace( '/<span\s+>\s*(.*?)\s*<\/span>/is', '$1', $html );
    
    // Clean up any double spaces that might have been created
    $html = preg_replace( '/\s+/', ' ', $html );
    
    // Clean up spaces before closing tags
    $html = preg_replace( '/\s+>/', '>', $html );
    
    // Clean up extra whitespace between tags
    $html = preg_replace( '/>\s+</', '><', $html );
    $html = preg_replace( '/>\s+</', '><', $html ); // Run twice to catch nested cases
    
    // Clean up multiple spaces within text
    $html = preg_replace( '/\s{2,}/', ' ', $html );
    
    return trim( $html );
}

/**
 * Build YATCO listing URL with proper slug format.
 * Format: https://www.yatco.com/yacht/[length]-[builder]-[type]-[year]-[mlsid]/
 * 
 * @param int    $post_id   WordPress post ID
 * @param string $mlsid     MLS ID
 * @param int    $vessel_id Vessel ID (fallback if no MLS ID)
 * @param float  $length    Length in feet
 * @param string $builder   Builder/make name
 * @param string $type      Vessel type/category
 * @param int    $year      Year built
 * @return string YATCO listing URL
 */
function yatco_build_listing_url( $post_id = 0, $mlsid = '', $vessel_id = 0, $length = 0, $builder = '', $type = '', $year = 0 ) {
    // Get MLS ID or use Vessel ID as fallback
    $listing_id = ! empty( $mlsid ) ? $mlsid : $vessel_id;
    
    if ( empty( $listing_id ) ) {
        return '';
    }
    
    // If we have all the data, build the full slug
    if ( ! empty( $length ) && ! empty( $builder ) && ! empty( $type ) && ! empty( $year ) ) {
        // Clean and format each part
        $length_slug = intval( $length ); // Just the number
        $builder_slug = sanitize_title( $builder ); // Convert to slug (lowercase, hyphens)
        $type_slug = sanitize_title( $type ); // Convert to slug
        $year_slug = intval( $year );
        
        // Build the full slug: length-builder-type-year-mlsid
        $slug = $length_slug . '-' . $builder_slug . '-' . $type_slug . '-' . $year_slug . '-' . $listing_id;
    } else {
        // Fallback: just use the MLS ID/Vessel ID
        $slug = $listing_id;
    }
    
    return 'https://www.yatco.com/yacht/' . $slug . '/';
}

/**
 * Helper: parse a brief summary from FullSpecsAll for preview table.
 * Updated to match actual API response structure.
 */
function yatco_build_brief_from_fullspecs( $vessel_id, $full ) {
    $name   = '';
    $price  = '';
    $year   = '';
    $loa    = '';
    $mlsid  = '';

    // Get Result and BasicInfo for easier access.
    $result = isset( $full['Result'] ) ? $full['Result'] : array();
    $basic  = isset( $full['BasicInfo'] ) ? $full['BasicInfo'] : array();
    $dims   = isset( $full['Dimensions'] ) ? $full['Dimensions'] : array();

    // Vessel name: Prefer BasicInfo.BoatName (better case formatting), then Result.VesselName.
    // BasicInfo.BoatName usually has proper case like "25' Scarab 255 Open ID"
    // Result.VesselName is often all caps like "25' SCARAB 255 OPEN ID"
    if ( ! empty( $basic['BoatName'] ) ) {
        $name = $basic['BoatName'];
    } elseif ( ! empty( $result['VesselName'] ) ) {
        $name = $result['VesselName'];
    }

    // Price: Prefer USD price from BasicInfo, fallback to Result.AskingPriceCompare.
    if ( isset( $basic['AskingPriceUSD'] ) && $basic['AskingPriceUSD'] > 0 ) {
        $price = $basic['AskingPriceUSD'];
    } elseif ( isset( $result['AskingPriceCompare'] ) && $result['AskingPriceCompare'] > 0 ) {
        $price = $result['AskingPriceCompare'];
    } elseif ( isset( $basic['AskingPrice'] ) && $basic['AskingPrice'] > 0 ) {
        $price = $basic['AskingPrice'];
    }

    // Year: Check BasicInfo first, then Result.
    if ( ! empty( $basic['YearBuilt'] ) ) {
        $year = $basic['YearBuilt'];
    } elseif ( ! empty( $basic['ModelYear'] ) ) {
        $year = $basic['ModelYear'];
    } elseif ( ! empty( $result['YearBuilt'] ) ) {
        $year = $result['YearBuilt'];
    } elseif ( ! empty( $result['Year'] ) ) {
        $year = $result['Year'];
    }

    // LOA: Use LOAFeet if available, otherwise formatted LOA string.
    if ( isset( $result['LOAFeet'] ) && $result['LOAFeet'] > 0 ) {
        $loa = $result['LOAFeet'];
    } elseif ( ! empty( $dims['LOAFeet'] ) ) {
        $loa = $dims['LOAFeet'];
    } elseif ( ! empty( $dims['LOA'] ) ) {
        $loa = $dims['LOA'];
    } elseif ( ! empty( $result['LOAFeet'] ) ) {
        $loa = $result['LOAFeet'];
    }

    // MLSID: From Result.
    if ( ! empty( $result['MLSID'] ) ) {
        $mlsid = $result['MLSID'];
    } elseif ( ! empty( $result['VesselID'] ) ) {
        $mlsid = $result['VesselID'];
    }

    return array(
        'VesselID' => $vessel_id,
        'Name'     => $name,
        'Price'    => $price,
        'Year'     => $year,
        'LOA'      => $loa,
        'MLSId'    => $mlsid,
    );
}

/**
 * Import a single vessel ID into the Yacht CPT.
 * 
 * This function:
 * 1. Fetches full vessel specifications from YATCO API
 * 2. Matches existing CPT post by MLSID (primary) or VesselID (fallback)
 * 3. Creates new post if not found, or updates existing post if found
 * 4. Stores all vessel metadata in post meta fields
 * 5. Stores image URLs in meta (images are NOT downloaded to save storage)
 * 
 * Matching Logic:
 * - First attempts to match by MLSID (yacht_mlsid meta field)
 * - Falls back to matching by VesselID (yacht_vessel_id meta field) if MLSID match fails
 * - This ensures vessels are properly updated even if MLSID changes or is missing
 * 
 * Update Behavior:
 * - Updates all post meta fields with latest API data
 * - Updates post title if vessel name changed
 * - Maintains post ID and permalink for existing vessels
 * - Updates yacht_last_updated timestamp on every import
 * 
 * @param string $token     YATCO API token
 * @param int    $vessel_id YATCO Vessel ID
 * @return int|WP_Error     Post ID on success, WP_Error on failure
 */
function yatco_import_single_vessel( $token, $vessel_id ) {
    $full = yatco_fetch_fullspecs( $token, $vessel_id );
    if ( is_wp_error( $full ) ) {
        return $full;
    }

    // Get Result and BasicInfo for easier access.
    $result = isset( $full['Result'] ) ? $full['Result'] : array();
    $basic  = isset( $full['BasicInfo'] ) ? $full['BasicInfo'] : array();
    $dims   = isset( $full['Dimensions'] ) ? $full['Dimensions'] : array();
    $vd     = isset( $full['VD'] ) ? $full['VD'] : array();
    $misc   = isset( $full['MiscInfo'] ) ? $full['MiscInfo'] : array();
    $sections = isset( $full['Sections'] ) && is_array( $full['Sections'] ) ? $full['Sections'] : array();

    // Basic fields – updated to match actual API structure.
    $name   = '';
    $price  = '';
    $year   = '';
    $loa    = '';
    $mlsid  = '';
    $make   = '';
    $class  = '';
    $desc   = '';

    // Vessel name: Prefer BasicInfo.BoatName (better case formatting), then Result.VesselName.
    // BasicInfo.BoatName usually has proper case like "25' Scarab 255 Open ID"
    // Result.VesselName is often all caps like "25' SCARAB 255 OPEN ID"
    if ( ! empty( $basic['BoatName'] ) ) {
        $name = $basic['BoatName'];
    } elseif ( ! empty( $result['VesselName'] ) ) {
        $name = $result['VesselName'];
    }

    // Price: Prefer USD price from BasicInfo, fallback to Result.AskingPriceCompare.
    if ( isset( $basic['AskingPriceUSD'] ) && $basic['AskingPriceUSD'] > 0 ) {
        $price = $basic['AskingPriceUSD'];
    } elseif ( isset( $result['AskingPriceCompare'] ) && $result['AskingPriceCompare'] > 0 ) {
        $price = $result['AskingPriceCompare'];
    } elseif ( isset( $basic['AskingPrice'] ) && $basic['AskingPrice'] > 0 ) {
        $price = $basic['AskingPrice'];
    }

    // Year: Check BasicInfo first, then Result.
    if ( ! empty( $basic['YearBuilt'] ) ) {
        $year = $basic['YearBuilt'];
    } elseif ( ! empty( $basic['ModelYear'] ) ) {
        $year = $basic['ModelYear'];
    } elseif ( ! empty( $result['YearBuilt'] ) ) {
        $year = $result['YearBuilt'];
    } elseif ( ! empty( $result['Year'] ) ) {
        $year = $result['Year'];
    }

    // LOA: Use LOAFeet if available, otherwise formatted LOA string.
    if ( isset( $result['LOAFeet'] ) && $result['LOAFeet'] > 0 ) {
        $loa = $result['LOAFeet'];
    } elseif ( ! empty( $dims['LOAFeet'] ) ) {
        $loa = $dims['LOAFeet'];
    } elseif ( ! empty( $dims['LOA'] ) ) {
        $loa = $dims['LOA'];
    }

    // MLSID: From Result.
    if ( ! empty( $result['MLSID'] ) ) {
        $mlsid = $result['MLSID'];
    } elseif ( ! empty( $result['VesselID'] ) ) {
        $mlsid = $result['VesselID'];
    }

    // Builder: From BasicInfo.
    if ( ! empty( $basic['Builder'] ) ) {
        $make = $basic['Builder'];
    } elseif ( ! empty( $result['BuilderName'] ) ) {
        $make = $result['BuilderName'];
    }

    // Model: From BasicInfo or Result.
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

    // Vessel class: From BasicInfo.MainCategory, or Result.MainCategoryText.
    if ( ! empty( $basic['MainCategory'] ) ) {
        $class = $basic['MainCategory'];
    } elseif ( ! empty( $result['MainCategoryText'] ) ) {
        $class = $result['MainCategoryText'];
    }
    
    // Sub Category: From BasicInfo or Result.
    $sub_category = '';
    if ( ! empty( $basic['SubCategory'] ) ) {
        $sub_category = $basic['SubCategory'];
    } elseif ( ! empty( $result['SubCategoryText'] ) ) {
        $sub_category = $result['SubCategoryText'];
    } elseif ( ! empty( $result['SubCategory'] ) ) {
        $sub_category = $result['SubCategory'];
    }

    // Description: Build from Sections array (preserves h2/h3 headings), or fallback to short description.
    // Sections contain structured content with headings that matches what's displayed on YATCO website.
    $desc = '';
    
    if ( ! empty( $sections ) ) {
        // Build description from Sections array - this preserves h2/h3 tags and structure
        $desc_parts = array();
        
        // Sort sections by SortOrder if available
        usort( $sections, function( $a, $b ) {
            $order_a = isset( $a['SortOrder'] ) ? intval( $a['SortOrder'] ) : 999;
            $order_b = isset( $b['SortOrder'] ) ? intval( $b['SortOrder'] ) : 999;
            return $order_a - $order_b;
        } );
        
        foreach ( $sections as $section ) {
            if ( empty( $section['SectionText'] ) ) {
                continue;
            }
            
            $section_name = isset( $section['SectionName'] ) ? trim( $section['SectionName'] ) : '';
            $section_text = trim( $section['SectionText'] );
            
            // Strip inline CSS styles and class attributes from section text
            $section_text = yatco_strip_inline_styles_and_classes( $section_text );
            
            // If SectionText already contains h2/h3 tags, use it as-is
            // Otherwise, wrap SectionName as h2 if it exists
            if ( ! empty( $section_name ) && stripos( $section_text, '<h2' ) === false && stripos( $section_text, '<h3' ) === false ) {
                // Add section name as h2 heading if the text doesn't already have headings
                $desc_parts[] = '<h2>' . esc_html( $section_name ) . '</h2>';
            }
            
            $desc_parts[] = $section_text;
        }
        
        if ( ! empty( $desc_parts ) ) {
            $desc = implode( "\n\n", $desc_parts );
        }
    }
    
    // Fallback to short description if Sections are empty or don't have content
    if ( empty( $desc ) ) {
        if ( ! empty( $vd['VesselDescriptionShortDescriptionNoStyles'] ) ) {
            $desc = $vd['VesselDescriptionShortDescriptionNoStyles'];
        } elseif ( ! empty( $misc['VesselDescriptionShortDescription'] ) ) {
            $desc = $misc['VesselDescriptionShortDescription'];
        } elseif ( ! empty( $vd['VesselDescriptionShortDescription'] ) ) {
            $desc = $vd['VesselDescriptionShortDescription'];
        }
    }
    
    // Strip inline CSS styles and class attributes from the description
    if ( ! empty( $desc ) ) {
        $desc = yatco_strip_inline_styles_and_classes( $desc );
    }

    // Find existing post by MLSID (primary) or VesselID (fallback).
    // This ensures we can match and update existing vessels even if MLSID changes or is missing.
    $post_id = 0;
    
    // Try matching by MLSID first (most reliable identifier).
    if ( ! empty( $mlsid ) ) {
        $existing = get_posts(
            array(
                'post_type'   => 'yacht',
                'meta_key'    => 'yacht_mlsid',
                'meta_value'  => $mlsid,
                'numberposts' => 1,
                'fields'      => 'ids',
            )
        );
        if ( ! empty( $existing ) ) {
            $post_id = (int) $existing[0];
        }
    }
    
    // Fallback: If MLSID matching failed, try matching by VesselID.
    // This handles cases where MLSID might be missing or changed.
    if ( ! $post_id && ! empty( $vessel_id ) ) {
        $existing = get_posts(
            array(
                'post_type'   => 'yacht',
                'meta_key'    => 'yacht_vessel_id',
                'meta_value'  => $vessel_id,
                'numberposts' => 1,
                'fields'      => 'ids',
            )
        );
        if ( ! empty( $existing ) ) {
            $post_id = (int) $existing[0];
        }
    }

    // Build full title: "BoatName Year Length' BUILDER Category"
    // Example: "Fontana 2012 92' SANLORENZO YACHTS Motor Yacht"
    $title_parts = array();
    
    // Boat name (trimmed)
    if ( ! empty( $name ) ) {
        $title_parts[] = trim( $name );
    }
    
    // Year
    if ( ! empty( $year ) ) {
        $title_parts[] = $year;
    }
    
    // Length in feet (e.g., "92'")
    if ( ! empty( $loa ) && is_numeric( $loa ) ) {
        $title_parts[] = intval( $loa ) . "'";
    }
    
    // Builder name (uppercase)
    if ( ! empty( $make ) ) {
        $title_parts[] = strtoupper( trim( $make ) );
    }
    
    // Category (e.g., "Motor Yacht")
    if ( ! empty( $class ) ) {
        $title_parts[] = trim( $class );
    }
    
    // Build the title, or fallback to boat name, or vessel ID
    if ( ! empty( $title_parts ) ) {
        $full_title = implode( ' ', $title_parts );
    } elseif ( ! empty( $name ) ) {
        $full_title = trim( $name );
    } else {
        $full_title = 'Yacht ' . $vessel_id;
    }
    
    $post_data = array(
        'post_type'   => 'yacht',
        'post_title'  => $full_title,
        'post_status' => 'publish',
    );

    if ( $post_id ) {
        $post_data['ID'] = $post_id;
        $post_id         = wp_update_post( $post_data );
        } else {
        $post_id = wp_insert_post( $post_data );
    }

    if ( is_wp_error( $post_id ) || ! $post_id ) {
        return new WP_Error( 'yatco_post_error', 'Failed to create or update yacht post.' );
    }

    // Get additional fields for filtering and display
    $loa_feet = isset( $result['LOAFeet'] ) && $result['LOAFeet'] > 0 ? floatval( $result['LOAFeet'] ) : ( isset( $dims['LOAFeet'] ) && $dims['LOAFeet'] > 0 ? floatval( $dims['LOAFeet'] ) : null );
    $loa_meters = isset( $result['LOAMeters'] ) && $result['LOAMeters'] > 0 ? floatval( $result['LOAMeters'] ) : null;
    if ( ! $loa_meters && $loa_feet ) {
        $loa_meters = $loa_feet * 0.3048;
    }
    
    // Get price in USD and EUR separately
    $price_usd = isset( $basic['AskingPriceUSD'] ) && $basic['AskingPriceUSD'] > 0 ? floatval( $basic['AskingPriceUSD'] ) : null;
    if ( ! $price_usd && isset( $result['AskingPriceCompare'] ) && $result['AskingPriceCompare'] > 0 ) {
        $price_usd = floatval( $result['AskingPriceCompare'] );
    }
    
    $price_eur = isset( $basic['AskingPrice'] ) && $basic['AskingPrice'] > 0 && isset( $basic['Currency'] ) && $basic['Currency'] === 'EUR' ? floatval( $basic['AskingPrice'] ) : null;
    
    // Get additional metadata
    // Category: use $class if already set, otherwise get from BasicInfo/Result
    if ( empty( $class ) ) {
        $category = isset( $basic['MainCategory'] ) ? $basic['MainCategory'] : ( isset( $result['MainCategoryText'] ) ? $result['MainCategoryText'] : '' );
    } else {
        $category = $class; // Use $class which was set earlier from MainCategory
    }
    $type = isset( $basic['VesselTypeText'] ) ? $basic['VesselTypeText'] : ( isset( $result['VesselTypeText'] ) ? $result['VesselTypeText'] : '' );
    $condition = isset( $result['VesselCondition'] ) ? $result['VesselCondition'] : '';
    $location = isset( $basic['LocationCustom'] ) ? $basic['LocationCustom'] : '';
    $location_city = isset( $basic['LocationCity'] ) ? $basic['LocationCity'] : ( isset( $result['LocationCity'] ) ? $result['LocationCity'] : '' );
    $location_state = isset( $basic['LocationState'] ) ? $basic['LocationState'] : ( isset( $result['LocationState'] ) ? $result['LocationState'] : '' );
    $location_country = isset( $basic['LocationCountry'] ) ? $basic['LocationCountry'] : ( isset( $result['LocationCountry'] ) ? $result['LocationCountry'] : '' );
    $state_rooms = isset( $basic['StateRooms'] ) ? intval( $basic['StateRooms'] ) : ( isset( $result['StateRooms'] ) ? intval( $result['StateRooms'] ) : 0 );
    $image_url = isset( $result['MainPhotoUrl'] ) ? $result['MainPhotoUrl'] : ( isset( $basic['MainPhotoURL'] ) ? $basic['MainPhotoURL'] : '' );
    
    // Get price formatting and currency
    $currency = isset( $basic['Currency'] ) ? $basic['Currency'] : ( isset( $result['Currency'] ) ? $result['Currency'] : 'USD' );
    $price_formatted = isset( $result['AskingPriceFormatted'] ) ? $result['AskingPriceFormatted'] : '';
    
    // If no formatted price from API, create one from the price value
    if ( empty( $price_formatted ) && ! empty( $price ) ) {
        $price_formatted = $currency . ' ' . number_format( floatval( $price ), 0 );
    }
    
    $price_on_application = isset( $result['PriceOnApplication'] ) ? (bool) $result['PriceOnApplication'] : false;
    if ( ! $price_on_application && isset( $basic['PriceOnApplication'] ) ) {
        $price_on_application = (bool) $basic['PriceOnApplication'];
    }
    
    // Format the main price field as a formatted string for display
    $price_formatted_display = $price_formatted;
    if ( $price_on_application ) {
        $price_formatted_display = 'Price on Application';
    } elseif ( empty( $price_formatted_display ) && ! empty( $price ) ) {
        $price_formatted_display = $currency . ' ' . number_format( floatval( $price ), 0 );
    }
    
    // Get status information
    $status_text = isset( $result['StatusText'] ) ? $result['StatusText'] : ( isset( $basic['StatusText'] ) ? $basic['StatusText'] : '' );
    $agreement_type = isset( $result['AgreementType'] ) ? $result['AgreementType'] : ( isset( $basic['AgreementType'] ) ? $basic['AgreementType'] : '' );
    $days_on_market = isset( $result['DaysOnMarket'] ) ? intval( $result['DaysOnMarket'] ) : ( isset( $basic['DaysOnMarket'] ) ? intval( $basic['DaysOnMarket'] ) : 0 );
    
    // Get hull material
    $hull_material = isset( $result['HullMaterial'] ) ? $result['HullMaterial'] : ( isset( $dims['HullMaterial'] ) ? $dims['HullMaterial'] : '' );
    
    // Get virtual tour URL
    $virtual_tour_url = isset( $result['VirtualTourUrl'] ) ? $result['VirtualTourUrl'] : ( isset( $basic['VirtualTourUrl'] ) ? $basic['VirtualTourUrl'] : '' );
    
    // Get videos
    $videos = array();
    if ( isset( $result['Videos'] ) && is_array( $result['Videos'] ) ) {
        $videos = $result['Videos'];
    } elseif ( isset( $basic['Videos'] ) && is_array( $basic['Videos'] ) ) {
        $videos = $basic['Videos'];
    }
    
    // Get broker information
    $broker_first_name = isset( $result['BrokerFirstName'] ) ? $result['BrokerFirstName'] : ( isset( $basic['BrokerFirstName'] ) ? $basic['BrokerFirstName'] : '' );
    $broker_last_name = isset( $result['BrokerLastName'] ) ? $result['BrokerLastName'] : ( isset( $basic['BrokerLastName'] ) ? $basic['BrokerLastName'] : '' );
    $broker_phone = isset( $result['BrokerPhone'] ) ? $result['BrokerPhone'] : ( isset( $basic['BrokerPhone'] ) ? $basic['BrokerPhone'] : '' );
    $broker_email = isset( $result['BrokerEmail'] ) ? $result['BrokerEmail'] : ( isset( $basic['BrokerEmail'] ) ? $basic['BrokerEmail'] : '' );
    $broker_photo_url = isset( $result['BrokerPhotoUrl'] ) ? $result['BrokerPhotoUrl'] : ( isset( $basic['BrokerPhotoUrl'] ) ? $basic['BrokerPhotoUrl'] : '' );
    
    // Get company information
    $company_name = isset( $result['CompanyName'] ) ? $result['CompanyName'] : ( isset( $basic['CompanyName'] ) ? $basic['CompanyName'] : '' );
    $company_logo_url = isset( $result['CompanyLogoUrl'] ) ? $result['CompanyLogoUrl'] : ( isset( $basic['CompanyLogoUrl'] ) ? $basic['CompanyLogoUrl'] : '' );
    $company_address = isset( $result['CompanyAddress'] ) ? $result['CompanyAddress'] : ( isset( $basic['CompanyAddress'] ) ? $basic['CompanyAddress'] : '' );
    $company_website = isset( $result['CompanyWebsite'] ) ? $result['CompanyWebsite'] : ( isset( $basic['CompanyWebsite'] ) ? $basic['CompanyWebsite'] : '' );
    $company_phone = isset( $result['CompanyPhone'] ) ? $result['CompanyPhone'] : ( isset( $basic['CompanyPhone'] ) ? $basic['CompanyPhone'] : '' );
    $company_email = isset( $result['CompanyEmail'] ) ? $result['CompanyEmail'] : ( isset( $basic['CompanyEmail'] ) ? $basic['CompanyEmail'] : '' );
    
    // Get builder description
    $builder_description = isset( $result['BuilderDescription'] ) ? $result['BuilderDescription'] : ( isset( $misc['BuilderDescription'] ) ? $misc['BuilderDescription'] : '' );
    
    // Store core meta – these can be mapped to ACF fields.
    update_post_meta( $post_id, 'yacht_mlsid', $mlsid );
    update_post_meta( $post_id, 'yacht_vessel_id', $vessel_id ); // Store vessel ID for reference
    
    // Store YATCO listing URL for easy access in admin
    // Build proper YATCO URL with slug format: length-builder-category-year-mlsid
    // Use category or sub_category for the type part (e.g., "motor-yacht")
    $category_for_url = ! empty( $sub_category ) ? $sub_category : ( ! empty( $category ) ? $category : $type );
    $yatco_listing_url = yatco_build_listing_url( $post_id, $mlsid, $vessel_id, $loa_feet, $make, $category_for_url, $year );
    update_post_meta( $post_id, 'yacht_yatco_listing_url', $yatco_listing_url );
    update_post_meta( $post_id, 'yacht_price', $price_formatted_display ); // Save formatted price string
    update_post_meta( $post_id, 'yacht_price_usd', $price_usd );
    update_post_meta( $post_id, 'yacht_price_eur', $price_eur );
    update_post_meta( $post_id, 'yacht_price_formatted', $price_formatted );
    update_post_meta( $post_id, 'yacht_currency', $currency );
    update_post_meta( $post_id, 'yacht_price_on_application', $price_on_application );
    update_post_meta( $post_id, 'yacht_year', $year );
    update_post_meta( $post_id, 'yacht_length', $loa );
    update_post_meta( $post_id, 'yacht_length_feet', $loa_feet );
    update_post_meta( $post_id, 'yacht_length_meters', $loa_meters );
    update_post_meta( $post_id, 'yacht_make', $make );
    update_post_meta( $post_id, 'yacht_model', $model );
    update_post_meta( $post_id, 'yacht_class', $class );
    update_post_meta( $post_id, 'yacht_category', $category );
    update_post_meta( $post_id, 'yacht_sub_category', $sub_category );
    update_post_meta( $post_id, 'yacht_type', $type );
    update_post_meta( $post_id, 'yacht_condition', $condition );
    update_post_meta( $post_id, 'yacht_location', $location );
    update_post_meta( $post_id, 'yacht_location_custom_rjc', $location );
    update_post_meta( $post_id, 'yacht_location_city', $location_city );
    update_post_meta( $post_id, 'yacht_location_state', $location_state );
    update_post_meta( $post_id, 'yacht_location_country', $location_country );
    update_post_meta( $post_id, 'yacht_state_rooms', $state_rooms );
    update_post_meta( $post_id, 'yacht_image_url', $image_url );
    update_post_meta( $post_id, 'yacht_hull_material', $hull_material );
    update_post_meta( $post_id, 'yacht_status_text', $status_text );
    update_post_meta( $post_id, 'yacht_agreement_type', $agreement_type );
    update_post_meta( $post_id, 'yacht_days_on_market', $days_on_market );
    update_post_meta( $post_id, 'yacht_virtual_tour_url', $virtual_tour_url );
    update_post_meta( $post_id, 'yacht_videos', $videos );
    update_post_meta( $post_id, 'yacht_broker_first_name', $broker_first_name );
    update_post_meta( $post_id, 'yacht_broker_last_name', $broker_last_name );
    update_post_meta( $post_id, 'yacht_broker_phone', $broker_phone );
    update_post_meta( $post_id, 'yacht_broker_email', $broker_email );
    update_post_meta( $post_id, 'yacht_broker_photo_url', $broker_photo_url );
    update_post_meta( $post_id, 'yacht_company_name', $company_name );
    update_post_meta( $post_id, 'yacht_company_logo_url', $company_logo_url );
    update_post_meta( $post_id, 'yacht_company_address', $company_address );
    update_post_meta( $post_id, 'yacht_company_website', $company_website );
    update_post_meta( $post_id, 'yacht_company_phone', $company_phone );
    update_post_meta( $post_id, 'yacht_company_email', $company_email );
    update_post_meta( $post_id, 'yacht_builder_description', $builder_description );
    update_post_meta( $post_id, 'yacht_last_updated', time() );
    update_post_meta( $post_id, 'yacht_fullspecs_raw', $full );

    // Assign taxonomy terms for archives
    // Builder
    if ( ! empty( $make ) ) {
        wp_set_object_terms( $post_id, $make, 'yacht_builder', false );
    }
    
    // Vessel Type
    if ( ! empty( $type ) ) {
        wp_set_object_terms( $post_id, $type, 'yacht_vessel_type', false );
    }
    
    // Category - use $class if $category is empty (they should be the same, but $class is set earlier)
    $category_for_taxonomy = ! empty( $category ) ? trim( $category ) : ( ! empty( $class ) ? trim( $class ) : '' );
    if ( ! empty( $category_for_taxonomy ) ) {
        // Use append=false to replace existing terms (ensures clean assignment)
        // Pass as array to ensure proper handling - wp_set_object_terms will create term if it doesn't exist
        $result = wp_set_object_terms( $post_id, array( $category_for_taxonomy ), 'yacht_category', false );
        // Note: wp_set_object_terms returns term IDs on success, WP_Error on failure, or empty array if no terms
    }
    
    // Sub Category (if hierarchical categories are used)
    if ( ! empty( $sub_category ) && ! empty( $category_for_taxonomy ) ) {
        // Try to create as child term if parent exists
        $parent_term = get_term_by( 'name', $category_for_taxonomy, 'yacht_category' );
        if ( $parent_term ) {
            $sub_category_trimmed = trim( $sub_category );
            $sub_term = wp_insert_term( $sub_category_trimmed, 'yacht_category', array( 'parent' => $parent_term->term_id ) );
            if ( ! is_wp_error( $sub_term ) && isset( $sub_term['term_id'] ) ) {
                wp_set_object_terms( $post_id, array( (int) $sub_term['term_id'] ), 'yacht_category', true );
            } elseif ( ! is_wp_error( $sub_term ) ) {
                // Term might already exist, try to get it and assign
                $existing_sub_term = get_term_by( 'name', $sub_category_trimmed, 'yacht_category' );
                if ( $existing_sub_term ) {
                    wp_set_object_terms( $post_id, array( (int) $existing_sub_term->term_id ), 'yacht_category', true );
                }
            }
        } elseif ( ! empty( $sub_category ) ) {
            // If parent category doesn't exist, still assign sub-category as top-level term
            wp_set_object_terms( $post_id, array( trim( $sub_category ) ), 'yacht_category', true );
        }
    } elseif ( ! empty( $sub_category ) ) {
        // If no main category, assign sub-category as top-level term
        wp_set_object_terms( $post_id, array( trim( $sub_category ) ), 'yacht_category', true );
    }

    if ( ! empty( $desc ) ) {
        wp_update_post(
            array(
                'ID'           => $post_id,
                'post_content' => $desc,
            )
        );
    }

    // IMAGE IMPORT DISABLED - Images are not downloaded to avoid storage issues
    // Image URLs are stored in yacht_image_url meta field for reference
    // If you want to enable image importing, uncomment the code below
    
    /*
    // Fetch gallery photos from PhotoGallery array in FullSpecsAll response.
    if ( isset( $full['PhotoGallery'] ) && is_array( $full['PhotoGallery'] ) && ! empty( $full['PhotoGallery'] ) ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attach_ids = array();

        foreach ( $full['PhotoGallery'] as $photo ) {
            // Use largeImageURL if available, fallback to medium or small.
            $url = '';
            if ( ! empty( $photo['largeImageURL'] ) ) {
                $url = $photo['largeImageURL'];
            } elseif ( ! empty( $photo['mediumImageURL'] ) ) {
                $url = $photo['mediumImageURL'];
            } elseif ( ! empty( $photo['smallImageURL'] ) ) {
                $url = $photo['smallImageURL'];
            }

            if ( empty( $url ) ) {
                continue;
            }

            $caption   = isset( $photo['Caption'] ) ? $photo['Caption'] : '';
            $attach_id = media_sideload_image( $url, $post_id, $caption, 'id' );
            if ( ! is_wp_error( $attach_id ) ) {
                $attach_ids[] = $attach_id;
            }
        }

        if ( ! empty( $attach_ids ) ) {
            set_post_thumbnail( $post_id, $attach_ids[0] );
            update_post_meta( $post_id, 'yacht_images', $attach_ids );
        }
    }
    */
    
    // Store image gallery URLs in meta for reference (without downloading)
    if ( isset( $full['PhotoGallery'] ) && is_array( $full['PhotoGallery'] ) && ! empty( $full['PhotoGallery'] ) ) {
        $image_urls = array();
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
                $image_urls[] = array(
                    'url'     => $url,
                    'caption' => isset( $photo['Caption'] ) ? $photo['Caption'] : '',
                );
            }
        }
        
        if ( ! empty( $image_urls ) ) {
            update_post_meta( $post_id, 'yacht_image_gallery_urls', $image_urls );
        }
    }

    return $post_id;
}
