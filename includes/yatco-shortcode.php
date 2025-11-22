<?php
/**
 * Shortcode Functions
 * 
 * Handles shortcode registration and vessel display functionality.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register shortcode for displaying YATCO vessels.
 */
function yatco_register_shortcode() {
    add_shortcode( 'yatco_vessels', 'yatco_vessels_shortcode' );
}

/**
 * Generate HTML from vessel data (helper function for cached data).
 */
function yatco_generate_vessels_html_from_data( $vessels, $builders, $categories, $types, $conditions, $atts ) {
    if ( empty( $vessels ) ) {
        return '<p>No vessels match your criteria.</p>';
    }

    $columns = intval( $atts['columns'] );
    if ( $columns < 1 || $columns > 4 ) {
        $columns = 3;
    }

    $column_class = 'yatco-col-' . $columns;
    $show_price = $atts['show_price'] === 'yes';
    $show_year  = $atts['show_year'] === 'yes';
    $show_loa   = $atts['show_loa'] === 'yes';
    $show_filters = $atts['show_filters'] === 'yes';
    $currency = strtoupper( $atts['currency'] ) === 'EUR' ? 'EUR' : 'USD';
    $length_unit = strtoupper( $atts['length_unit'] ) === 'M' ? 'M' : 'FT';

    ob_start();
    ?>
    <div class="yatco-vessels-container" data-currency="<?php echo esc_attr( $currency ); ?>" data-length-unit="<?php echo esc_attr( $length_unit ); ?>">
        <?php if ( $show_filters ) : ?>
        <div class="yatco-filters">
            <div class="yatco-filters-row yatco-filters-row-1">
                <div class="yatco-filter-group">
                    <label for="yatco-keywords">Keywords</label>
                    <input type="text" id="yatco-keywords" class="yatco-filter-input" placeholder="Boat Name, Location, Features" />
                </div>
                <div class="yatco-filter-group">
                    <label for="yatco-builder">Builder</label>
                    <select id="yatco-builder" class="yatco-filter-select">
                        <option value="">Any</option>
                        <?php foreach ( $builders as $builder ) : ?>
                            <option value="<?php echo esc_attr( $builder ); ?>"><?php echo esc_html( $builder ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="yatco-filter-group">
                    <label>Year</label>
                    <div class="yatco-filter-range">
                        <input type="number" id="yatco-year-min" class="yatco-filter-input yatco-input-small" placeholder="Min" />
                        <span>-</span>
                        <input type="number" id="yatco-year-max" class="yatco-filter-input yatco-input-small" placeholder="Max" />
                    </div>
                </div>
                <div class="yatco-filter-group">
                    <label>Length</label>
                    <div class="yatco-filter-range">
                        <input type="number" id="yatco-loa-min" class="yatco-filter-input yatco-input-small" placeholder="Min" step="0.1" />
                        <span>-</span>
                        <input type="number" id="yatco-loa-max" class="yatco-filter-input yatco-input-small" placeholder="Max" step="0.1" />
                    </div>
                    <div class="yatco-filter-toggle">
                        <button type="button" class="yatco-toggle-btn yatco-ft active" data-unit="FT">FT</button>
                        <button type="button" class="yatco-toggle-btn yatco-m" data-unit="M">M</button>
                    </div>
                </div>
                <div class="yatco-filter-group">
                    <label>Price</label>
                    <div class="yatco-filter-range">
                        <input type="number" id="yatco-price-min" class="yatco-filter-input yatco-input-small" placeholder="Min" step="1" />
                        <span>-</span>
                        <input type="number" id="yatco-price-max" class="yatco-filter-input yatco-input-small" placeholder="Max" step="1" />
                    </div>
                    <div class="yatco-filter-toggle">
                        <button type="button" class="yatco-toggle-btn yatco-usd active" data-currency="USD">USD</button>
                        <button type="button" class="yatco-toggle-btn yatco-eur" data-currency="EUR">EUR</button>
                    </div>
                </div>
            </div>
            <div class="yatco-filters-row yatco-filters-row-2">
                <div class="yatco-filter-group">
                    <label for="yatco-condition">Condition</label>
                    <select id="yatco-condition" class="yatco-filter-select">
                        <option value="">Any</option>
                        <?php foreach ( $conditions as $condition ) : ?>
                            <option value="<?php echo esc_attr( $condition ); ?>"><?php echo esc_html( $condition ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="yatco-filter-group">
                    <label for="yatco-type">Type</label>
                    <select id="yatco-type" class="yatco-filter-select">
                        <option value="">Any</option>
                        <?php foreach ( $types as $type ) : ?>
                            <option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $type ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="yatco-filter-group">
                    <label for="yatco-category">Category</label>
                    <select id="yatco-category" class="yatco-filter-select">
                        <option value="">Any</option>
                        <?php foreach ( $categories as $category ) : ?>
                            <option value="<?php echo esc_attr( $category ); ?>"><?php echo esc_html( $category ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="yatco-filter-group">
                    <label for="yatco-cabins">Cabins</label>
                    <select id="yatco-cabins" class="yatco-filter-select">
                        <option value="">Any</option>
                        <option value="1">1+</option>
                        <option value="2">2+</option>
                        <option value="3">3+</option>
                        <option value="4">4+</option>
                        <option value="5">5+</option>
                        <option value="6">6+</option>
                    </select>
                </div>
                <div class="yatco-filter-group yatco-filter-actions">
                    <button type="button" id="yatco-search-btn" class="yatco-search-btn">Search</button>
                    <button type="button" id="yatco-reset-btn" class="yatco-reset-btn">Reset</button>
                </div>
            </div>
        </div>
        <div class="yatco-results-header">
            <span class="yatco-results-count">0 - 0 of <span id="yatco-total-count"><?php echo count( $vessels ); ?></span> YACHTS FOUND</span>
            <div class="yatco-sort-view">
                <label for="yatco-sort">Sort by:</label>
                <select id="yatco-sort" class="yatco-sort-select">
                    <option value="">Pick a sort</option>
                    <option value="price_asc">Price: Low to High</option>
                    <option value="price_desc">Price: High to Low</option>
                    <option value="year_desc">Year: Newest First</option>
                    <option value="year_asc">Year: Oldest First</option>
                    <option value="length_desc">Length: Largest First</option>
                    <option value="length_asc">Length: Smallest First</option>
                    <option value="name_asc">Name: A to Z</option>
                </select>
            </div>
        </div>
        <?php endif; ?>
        <div class="yatco-vessels-grid <?php echo esc_attr( $column_class ); ?>" id="yatco-vessels-grid">
        <?php foreach ( $vessels as $vessel ) : 
            // Get the link - prefer CPT permalink if post_id exists, otherwise use the link from vessel data
            $vessel_link = '';
            if ( ! empty( $vessel['post_id'] ) ) {
                $vessel_link = get_permalink( $vessel['post_id'] );
            } elseif ( ! empty( $vessel['link'] ) ) {
                $vessel_link = $vessel['link'];
            }
        ?>
            <a href="<?php echo esc_url( $vessel_link ); ?>" class="yatco-vessel-card" 
                 data-name="<?php echo esc_attr( strtolower( $vessel['name'] ) ); ?>"
                 data-location="<?php echo esc_attr( strtolower( $vessel['location'] ) ); ?>"
                 data-builder="<?php echo esc_attr( $vessel['builder'] ); ?>"
                 data-category="<?php echo esc_attr( $vessel['category'] ); ?>"
                 data-type="<?php echo esc_attr( $vessel['type'] ); ?>"
                 data-condition="<?php echo esc_attr( $vessel['condition'] ); ?>"
                 data-year="<?php echo esc_attr( $vessel['year'] ); ?>"
                 data-loa-feet="<?php echo esc_attr( $vessel['loa_feet'] ); ?>"
                 data-loa-meters="<?php echo esc_attr( $vessel['loa_meters'] ); ?>"
                 data-price-usd="<?php echo esc_attr( $vessel['price_usd'] ); ?>"
                 data-price-eur="<?php echo esc_attr( $vessel['price_eur'] ); ?>"
                 data-state-rooms="<?php echo esc_attr( $vessel['state_rooms'] ); ?>">
                <?php if ( ! empty( $vessel['image'] ) ) : ?>
                    <div class="yatco-vessel-image">
                        <img src="<?php echo esc_url( $vessel['image'] ); ?>" alt="<?php echo esc_attr( $vessel['name'] ); ?>" />
                    </div>
                <?php endif; ?>
                <div class="yatco-vessel-info">
                    <h3 class="yatco-vessel-name"><?php echo esc_html( $vessel['name'] ); ?></h3>
                    <?php if ( ! empty( $vessel['location'] ) ) : ?>
                        <div class="yatco-vessel-location"><?php echo esc_html( $vessel['location'] ); ?></div>
                    <?php endif; ?>
                    <div class="yatco-vessel-details">
                        <?php 
                        $display_price = null;
                        $currency_symbol = '$';
                        if ( $currency === 'EUR' && ! empty( $vessel['price_eur'] ) ) {
                            $display_price = $vessel['price_eur'];
                            $currency_symbol = '€';
                        } elseif ( ! empty( $vessel['price_usd'] ) ) {
                            $display_price = $vessel['price_usd'];
                        }
                        ?>
                        <?php if ( $show_price && $display_price ) : ?>
                            <span class="yatco-vessel-price"><?php echo esc_html( $currency_symbol . number_format( floatval( $display_price ) ) ); ?></span>
                        <?php endif; ?>
                        <?php if ( $show_year && ! empty( $vessel['year'] ) ) : ?>
                            <span class="yatco-vessel-year"><?php echo esc_html( $vessel['year'] ); ?></span>
                        <?php endif; ?>
                        <?php 
                        $display_loa = null;
                        $loa_unit_text = ' ft';
                        if ( $length_unit === 'M' && ! empty( $vessel['loa_meters'] ) ) {
                            $display_loa = $vessel['loa_meters'];
                            $loa_unit_text = ' m';
                        } elseif ( ! empty( $vessel['loa_feet'] ) ) {
                            $display_loa = $vessel['loa_feet'];
                        }
                        ?>
                        <?php if ( $show_loa && $display_loa ) : ?>
                            <span class="yatco-vessel-loa"><?php echo esc_html( number_format( $display_loa, 1 ) . $loa_unit_text ); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
        </div>
    </div>
    <?php
    // Include CSS and JavaScript
    if ( file_exists( YATCO_PLUGIN_DIR . 'includes/yatco-shortcode-assets.php' ) ) {
        include YATCO_PLUGIN_DIR . 'includes/yatco-shortcode-assets.php';
    }
    return ob_get_clean();
}

/**
 * Shortcode to display YATCO vessels in real-time.
 * 
 * Usage: [yatco_vessels max="20" price_min="25000" price_max="500000" year_min="" year_max="" loa_min="" loa_max="" columns="3" show_price="yes" show_year="yes" show_loa="yes" show_filters="yes"]
 */
function yatco_vessels_shortcode( $atts ) {
    $atts = shortcode_atts(
        array(
            'max'           => '50',
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
            'cache'         => 'yes',
            'show_filters'  => 'yes',
            'currency'      => 'USD',
            'length_unit'   => 'FT',
        ),
        $atts,
        'yatco_vessels'
    );

    $token = yatco_get_token();
    if ( empty( $token ) ) {
        return '<p>YATCO API token is not configured.</p>';
    }

    // NEW CPT-BASED APPROACH: Query vessels from Custom Post Type
    // Check if cache is enabled (CPT-based, not transients)
    if ( $atts['cache'] === 'yes' ) {
        // Build WP_Query args for yacht CPT
        $query_args = array(
            'post_type'      => 'yacht',
            'post_status'    => 'publish',
            'posts_per_page' => -1, // Get all vessels for filtering
            'meta_query'     => array(),
        );
        
        // Parse filter criteria
        $price_min = ! empty( $atts['price_min'] ) && $atts['price_min'] !== '0' ? floatval( $atts['price_min'] ) : '';
        $price_max = ! empty( $atts['price_max'] ) && $atts['price_max'] !== '0' ? floatval( $atts['price_max'] ) : '';
        $year_min  = ! empty( $atts['year_min'] ) && $atts['year_min'] !== '0' ? intval( $atts['year_min'] ) : '';
        $year_max  = ! empty( $atts['year_max'] ) && $atts['year_max'] !== '0' ? intval( $atts['year_max'] ) : '';
        $loa_min   = ! empty( $atts['loa_min'] ) && $atts['loa_min'] !== '0' ? floatval( $atts['loa_min'] ) : '';
        $loa_max   = ! empty( $atts['loa_max'] ) && $atts['loa_max'] !== '0' ? floatval( $atts['loa_max'] ) : '';
        
        // Add meta queries for filtering
        if ( $price_min !== '' ) {
            $query_args['meta_query'][] = array(
                'key'     => 'yacht_price_usd',
                'value'   => $price_min,
                'compare' => '>=',
                'type'    => 'NUMERIC',
            );
        }
        if ( $price_max !== '' ) {
            $query_args['meta_query'][] = array(
                'key'     => 'yacht_price_usd',
                'value'   => $price_max,
                'compare' => '<=',
                'type'    => 'NUMERIC',
            );
        }
        if ( $year_min !== '' ) {
            $query_args['meta_query'][] = array(
                'key'     => 'yacht_year',
                'value'   => $year_min,
                'compare' => '>=',
                'type'    => 'NUMERIC',
            );
        }
        if ( $year_max !== '' ) {
            $query_args['meta_query'][] = array(
                'key'     => 'yacht_year',
                'value'   => $year_max,
                'compare' => '<=',
                'type'    => 'NUMERIC',
            );
        }
        if ( $loa_min !== '' ) {
            $query_args['meta_query'][] = array(
                'key'     => 'yacht_length_feet',
                'value'   => $loa_min,
                'compare' => '>=',
                'type'    => 'DECIMAL',
            );
        }
        if ( $loa_max !== '' ) {
            $query_args['meta_query'][] = array(
                'key'     => 'yacht_length_feet',
                'value'   => $loa_max,
                'compare' => '<=',
                'type'    => 'DECIMAL',
            );
        }
        
        // Check if cache is currently warming
        $cache_status = get_transient( 'yatco_cache_warming_status' );
        $cache_progress = get_transient( 'yatco_cache_warming_progress' );
        $is_warming = ( $cache_status !== false ) || ( $cache_progress !== false );
        
        // Query CPT posts
        $vessel_query = new WP_Query( $query_args );
        
        if ( $vessel_query->have_posts() ) {
            // Convert CPT posts to vessel data array
            $vessels = array();
            $builders = array();
            $categories = array();
            $types = array();
            $conditions = array();
            
            while ( $vessel_query->have_posts() ) {
                $vessel_query->the_post();
                $post_id = get_the_ID();
                
                // Get vessel data from post meta
                $vessel_id = get_post_meta( $post_id, 'yacht_vessel_id', true );
                $price_usd = get_post_meta( $post_id, 'yacht_price_usd', true );
                $price_eur = get_post_meta( $post_id, 'yacht_price_eur', true );
                $price = get_post_meta( $post_id, 'yacht_price', true );
                $year = get_post_meta( $post_id, 'yacht_year', true );
                $loa = get_post_meta( $post_id, 'yacht_length', true );
                $loa_feet = get_post_meta( $post_id, 'yacht_length_feet', true );
                $loa_meters = get_post_meta( $post_id, 'yacht_length_meters', true );
                $builder = get_post_meta( $post_id, 'yacht_make', true );
                $category = get_post_meta( $post_id, 'yacht_category', true );
                $type = get_post_meta( $post_id, 'yacht_type', true );
                $condition = get_post_meta( $post_id, 'yacht_condition', true );
                $location = get_post_meta( $post_id, 'yacht_location', true );
                $state_rooms = get_post_meta( $post_id, 'yacht_state_rooms', true );
                $image_url = get_post_meta( $post_id, 'yacht_image_url', true );
                
                // Get featured image if available
                if ( empty( $image_url ) && has_post_thumbnail( $post_id ) ) {
                    $image_url = get_the_post_thumbnail_url( $post_id, 'full' );
                }
                
                $vessel_data = array(
                    'id'          => $vessel_id ? $vessel_id : $post_id,
                    'post_id'     => $post_id,
                    'name'        => get_the_title(),
                    'price'       => $price,
                    'price_usd'   => $price_usd,
                    'price_eur'   => $price_eur,
                    'year'        => $year,
                    'loa'         => $loa,
                    'loa_feet'    => $loa_feet,
                    'loa_meters'  => $loa_meters,
                    'builder'     => $builder,
                    'category'    => $category,
                    'type'        => $type,
                    'condition'   => $condition,
                    'state_rooms' => $state_rooms,
                    'location'    => $location,
                    'image'       => $image_url,
                    'link'        => get_permalink( $post_id ),
                );
                
                $vessels[] = $vessel_data;
                
                // Collect unique values for filters
                if ( ! empty( $builder ) && ! in_array( $builder, $builders ) ) {
                    $builders[] = $builder;
                }
                if ( ! empty( $category ) && ! in_array( $category, $categories ) ) {
                    $categories[] = $category;
                }
                if ( ! empty( $type ) && ! in_array( $type, $types ) ) {
                    $types[] = $type;
                }
                if ( ! empty( $condition ) && ! in_array( $condition, $conditions ) ) {
                    $conditions[] = $condition;
                }
            }
            wp_reset_postdata();
            
            sort( $builders );
            sort( $categories );
            sort( $types );
            sort( $conditions );
            
            // If warming, show message with partial results
            if ( $is_warming && ! empty( $vessels ) ) {
                $status_msg = esc_html( $cache_status ? $cache_status : 'Vessels are being imported to CPT...' );
                $vessels_output = yatco_generate_vessels_html_from_data( $vessels, $builders, $categories, $types, $conditions, $atts );
                return '<div class="yatco-cache-warming-notice" style="padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 20px;"><strong>Note:</strong> ' . $status_msg . ' Showing ' . count( $vessels ) . ' vessels from CPT. More will appear as import completes.</div>' . $vessels_output;
            }
            
            // Return results from CPT
            return yatco_generate_vessels_html_from_data( $vessels, $builders, $categories, $types, $conditions, $atts );
        } else {
            // No CPT posts yet - check if warming or show fallback message
            if ( $is_warming ) {
                $status_msg = esc_html( $cache_status ? $cache_status : 'Vessels are being imported to CPT in the background' );
                return '<div class="yatco-cache-warming-notice" style="padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; margin: 20px 0;"><p><strong>CPT Import in Progress</strong></p><p>' . $status_msg . '</p><p>Vessels will appear once the import is complete. This may take several minutes for 7000+ vessels.</p><p><em>Tip: You can temporarily use <code>cache="no"</code> in the shortcode to load vessels directly from the API while import is running.</em></p></div>';
            }
            
            // No CPT posts and not warming - fall through to API fallback
        }
    }
    
    // FALLBACK: If cache is disabled or no CPT posts exist, fetch from API

    // Respect the max parameter - limit how many vessel IDs to fetch
    $max_vessels = ! empty( $atts['max'] ) && $atts['max'] !== '0' ? intval( $atts['max'] ) : 50;
    // Fetch more IDs than needed to account for filtering (3x the desired results, but cap at reasonable limit)
    $ids_to_fetch = min( $max_vessels * 3, 500 ); // Cap at 500 to prevent timeouts
    
    $ids = yatco_get_active_vessel_ids( $token, $ids_to_fetch );

    if ( is_wp_error( $ids ) ) {
        return '<p>Error loading vessels: ' . esc_html( $ids->get_error_message() ) . '</p>';
    }

    if ( empty( $ids ) ) {
        return '<p>No vessels available.</p>';
    }

    $vessels = array();
    
    // Parse filter criteria.
    $price_min = ! empty( $atts['price_min'] ) && $atts['price_min'] !== '0' ? floatval( $atts['price_min'] ) : '';
    $price_max = ! empty( $atts['price_max'] ) && $atts['price_max'] !== '0' ? floatval( $atts['price_max'] ) : '';
    $year_min  = ! empty( $atts['year_min'] ) && $atts['year_min'] !== '0' ? intval( $atts['year_min'] ) : '';
    $year_max  = ! empty( $atts['year_max'] ) && $atts['year_max'] !== '0' ? intval( $atts['year_max'] ) : '';
    $loa_min   = ! empty( $atts['loa_min'] ) && $atts['loa_min'] !== '0' ? floatval( $atts['loa_min'] ) : '';
    $loa_max   = ! empty( $atts['loa_max'] ) && $atts['loa_max'] !== '0' ? floatval( $atts['loa_max'] ) : '';

    // Process ALL vessel IDs to make all 7000+ vessels searchable/filterable
    // Note: For large datasets (7000+), this may take time. We use batch processing to prevent timeouts.
    $vessel_count = count( $ids );
    $processed = 0;
    $error_count = 0;
    
    // Increase limits to handle processing
    @ini_set( 'max_execution_time', 300 ); // 5 minutes max
    @ini_set( 'memory_limit', '256M' ); // Reasonable memory limit
    @set_time_limit( 300 ); // 5 minutes
    
    // Start fresh - no progress caching when cache="no"
    $vessels = array();
    $processed = 0;
    $error_count = 0;
    
    // Process vessels up to max limit
    foreach ( $ids as $index => $id ) {
        // Stop if we've reached the max limit
        if ( count( $vessels ) >= $max_vessels ) {
            break;
        }
        
        $processed++;
        
        // Reset execution time periodically
        if ( $processed % 10 === 0 ) {
            @set_time_limit( 300 );
        }

        $full = yatco_fetch_fullspecs( $token, $id );
        if ( is_wp_error( $full ) ) {
            continue;
        }

        $brief = yatco_build_brief_from_fullspecs( $id, $full );

        // Apply filtering.
        $price = ! empty( $brief['Price'] ) ? floatval( $brief['Price'] ) : null;
        $year  = ! empty( $brief['Year'] ) ? intval( $brief['Year'] ) : null;
        $loa_raw = $brief['LOA'];
        if ( is_string( $loa_raw ) && preg_match( '/([0-9.]+)/', $loa_raw, $matches ) ) {
            $loa = floatval( $matches[1] );
        } elseif ( ! empty( $loa_raw ) && is_numeric( $loa_raw ) ) {
            $loa = floatval( $loa_raw );
        } else {
            $loa = null;
        }

        // Apply filters.
        if ( $price_min !== '' && ( is_null( $price ) || $price <= 0 || $price < $price_min ) ) {
            continue;
        }
        if ( $price_max !== '' && ( is_null( $price ) || $price <= 0 || $price > $price_max ) ) {
            continue;
        }
        if ( $year_min !== '' && ( is_null( $year ) || $year <= 0 || $year < $year_min ) ) {
            continue;
        }
        if ( $year_max !== '' && ( is_null( $year ) || $year <= 0 || $year > $year_max ) ) {
            continue;
        }
        if ( $loa_min !== '' && ( is_null( $loa ) || $loa <= 0 || $loa < $loa_min ) ) {
            continue;
        }
        if ( $loa_max !== '' && ( is_null( $loa ) || $loa <= 0 || $loa > $loa_max ) ) {
            continue;
        }

        // Get full specs for display.
        $result = isset( $full['Result'] ) ? $full['Result'] : array();
        $basic  = isset( $full['BasicInfo'] ) ? $full['BasicInfo'] : array();
        
        // Get builder, category, type, condition
        $builder = isset( $basic['Builder'] ) ? $basic['Builder'] : ( isset( $result['BuilderName'] ) ? $result['BuilderName'] : '' );
        $category = isset( $basic['MainCategory'] ) ? $basic['MainCategory'] : ( isset( $result['MainCategoryText'] ) ? $result['MainCategoryText'] : '' );
        $type = isset( $basic['VesselTypeText'] ) ? $basic['VesselTypeText'] : ( isset( $result['VesselTypeText'] ) ? $result['VesselTypeText'] : '' );
        $condition = isset( $result['VesselCondition'] ) ? $result['VesselCondition'] : '';
        $state_rooms = isset( $basic['StateRooms'] ) ? intval( $basic['StateRooms'] ) : ( isset( $result['StateRooms'] ) ? intval( $result['StateRooms'] ) : 0 );
        $location = isset( $basic['LocationCustom'] ) ? $basic['LocationCustom'] : '';
        
        // Get LOA in feet and meters
        $loa_feet = isset( $result['LOAFeet'] ) && $result['LOAFeet'] > 0 ? floatval( $result['LOAFeet'] ) : null;
        $loa_meters = isset( $result['LOAMeters'] ) && $result['LOAMeters'] > 0 ? floatval( $result['LOAMeters'] ) : null;
        if ( ! $loa_meters && $loa_feet ) {
            $loa_meters = $loa_feet * 0.3048;
        }
        
        // Get price in USD and EUR
        $price_usd = isset( $basic['AskingPriceUSD'] ) && $basic['AskingPriceUSD'] > 0 ? floatval( $basic['AskingPriceUSD'] ) : null;
        if ( ! $price_usd && isset( $result['AskingPriceCompare'] ) && $result['AskingPriceCompare'] > 0 ) {
            $price_usd = floatval( $result['AskingPriceCompare'] );
        }
        
        $price_eur = isset( $basic['AskingPrice'] ) && $basic['AskingPrice'] > 0 && isset( $basic['Currency'] ) && $basic['Currency'] === 'EUR' ? floatval( $basic['AskingPrice'] ) : null;
        
        // Try to find existing CPT post for this vessel
        $post_id = 0;
        $mlsid = isset( $result['MLSID'] ) ? $result['MLSID'] : '';
        
        // Try matching by MLSID first
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
        
        // Fallback: Try matching by VesselID
        if ( ! $post_id ) {
            $existing = get_posts(
                array(
                    'post_type'   => 'yacht',
                    'meta_key'    => 'yacht_vessel_id',
                    'meta_value'  => $id,
                    'numberposts' => 1,
                    'fields'      => 'ids',
                )
            );
            if ( ! empty( $existing ) ) {
                $post_id = (int) $existing[0];
            }
        }
        
        // Use CPT permalink if post exists, otherwise fallback to archive link
        $vessel_link = '';
        if ( $post_id ) {
            $vessel_link = get_permalink( $post_id );
        } else {
            $vessel_link = get_post_type_archive_link( 'yacht' ) . '?vessel_id=' . $id;
        }
        
        $vessel_data = array(
            'id'          => $id,
            'post_id'     => $post_id,
            'name'        => $brief['Name'],
            'price'       => $brief['Price'],
            'price_usd'   => $price_usd,
            'price_eur'   => $price_eur,
            'year'        => $brief['Year'],
            'loa'         => $brief['LOA'],
            'loa_feet'    => $loa_feet,
            'loa_meters'  => $loa_meters,
            'builder'     => $builder,
            'category'    => $category,
            'type'        => $type,
            'condition'   => $condition,
            'state_rooms' => $state_rooms,
            'location'    => $location,
            'image'       => isset( $result['MainPhotoUrl'] ) ? $result['MainPhotoUrl'] : ( isset( $basic['MainPhotoURL'] ) ? $basic['MainPhotoURL'] : '' ),
            'link'        => $vessel_link,
        );

        $vessels[] = $vessel_data;
        
        // Stop if we've reached the max limit
        if ( count( $vessels ) >= $max_vessels ) {
            break;
        }
    }

    // Collect unique values for filter dropdowns
    $builders = array();
    $categories = array();
    $types = array();
    $conditions = array();
    
    foreach ( $vessels as $vessel ) {
        if ( ! empty( $vessel['builder'] ) && ! in_array( $vessel['builder'], $builders ) ) {
            $builders[] = $vessel['builder'];
        }
        if ( ! empty( $vessel['category'] ) && ! in_array( $vessel['category'], $categories ) ) {
            $categories[] = $vessel['category'];
        }
        if ( ! empty( $vessel['type'] ) && ! in_array( $vessel['type'], $types ) ) {
            $types[] = $vessel['type'];
        }
        if ( ! empty( $vessel['condition'] ) && ! in_array( $vessel['condition'], $conditions ) ) {
            $conditions[] = $vessel['condition'];
        }
    }
    sort( $builders );
    sort( $categories );
    sort( $types );
    sort( $conditions );

    // Generate HTML output
    $output = yatco_generate_vessels_html_from_data( $vessels, $builders, $categories, $types, $conditions, $atts );

    // Cache the output if enabled.
    if ( $atts['cache'] === 'yes' ) {
        $options = get_option( 'yatco_api_settings' );
        $cache_duration = isset( $options['yatco_cache_duration'] ) ? intval( $options['yatco_cache_duration'] ) : 30;
        
        set_transient( $cache_key, $output, $cache_duration * MINUTE_IN_SECONDS );
        
        // Also cache vessel data separately for faster future loads
        set_transient( 'yatco_vessels_data', $vessels, $cache_duration * MINUTE_IN_SECONDS );
        set_transient( 'yatco_vessels_builders', $builders, $cache_duration * MINUTE_IN_SECONDS );
        set_transient( 'yatco_vessels_categories', $categories, $cache_duration * MINUTE_IN_SECONDS );
        set_transient( 'yatco_vessels_types', $types, $cache_duration * MINUTE_IN_SECONDS );
        set_transient( 'yatco_vessels_conditions', $conditions, $cache_duration * MINUTE_IN_SECONDS );
    }

    return $output;
}
