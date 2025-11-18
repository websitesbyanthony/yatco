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
register_activation_hook( __FILE__, 'yatco_create_cpt' );

// Register shortcode on init.
add_action( 'init', 'yatco_register_shortcode' );

// Register cache warming hook
add_action( 'yatco_warm_cache_hook', 'yatco_warm_cache_function' );

// Schedule periodic cache refresh if enabled
add_action( 'admin_init', 'yatco_maybe_schedule_cache_refresh' );

/**
 * Admin settings page.
 */
add_action( 'admin_menu', 'yatco_add_admin_menu' );
add_action( 'admin_init', 'yatco_settings_init' );

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
            // Trigger async cache warming
            wp_schedule_single_event( time(), 'yatco_warm_cache_hook' );
            echo '<div class="notice notice-info"><p>Cache warming started in the background. This may take several minutes. The cache will be ready shortly.</p></div>';
        }
    }

    // Check if cache warming is in progress
    $cache_status = get_transient( 'yatco_cache_warming_status' );
    if ( $cache_status ) {
        echo '<div class="notice notice-info"><p>Cache Status: ' . esc_html( $cache_status ) . '</p></div>';
    }

    echo '</div>';
}

/**
 * Connection test helper.
 */
function yatco_test_connection( $token ) {
    $endpoint = 'https://api.yatcoboss.com/api/v1/ForSale/vessel/activevesselmlsid';

    $response = wp_remote_get(
        $endpoint,
        array(
            'headers' => array(
            'Authorization' => 'Basic ' . $token,
            'Accept'        => 'application/json',
            ),
            'timeout' => 20,
        )
    );

    if ( is_wp_error( $response ) ) {
        return '<div class="notice notice-error"><p>Error: ' . esc_html( $response->get_error_message() ) . '</p></div>';
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );

    if ( 200 !== $code ) {
        return '<div class="notice notice-error"><p>Failed: HTTP ' . intval( $code ) . '</p><pre>' . esc_html( substr( $body, 0, 400 ) ) . '</pre></div>';
    }

    $data = json_decode( $body, true );
    if ( ! is_array( $data ) ) {
        return '<div class="notice notice-warning"><p>200 OK but response is not an array. Raw snippet:</p><pre>' . esc_html( substr( $body, 0, 400 ) ) . '</pre></div>';
    }

    $snippet = array_slice( $data, 0, 50 );
    return '<div class="notice notice-success"><p><strong>Success!</strong> 200 OK</p><p>Sample MLSIDs:</p><pre>' . esc_html( print_r( $snippet, true ) ) . '</pre></div>';
}

/**
 * Helper: get Basic token.
 */
function yatco_get_token() {
    $options = get_option( 'yatco_api_settings' );
    return isset( $options['yatco_api_token'] ) ? trim( $options['yatco_api_token'] ) : '';
}

/**
 * Helper: fetch active vessel IDs using activevesselmlsid.
 */
function yatco_get_active_vessel_ids( $token, $max_records = 50 ) {
    $endpoint = 'https://api.yatcoboss.com/api/v1/ForSale/vessel/activevesselmlsid';

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
            return $response;
        }

    if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
        return new WP_Error( 'yatco_http_error', 'HTTP ' . wp_remote_retrieve_response_code( $response ) );
    }

        $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( ! is_array( $data ) ) {
        return new WP_Error( 'yatco_parse_error', 'Could not parse activevesselmlsid response.' );
    }

    $ids = array();
    foreach ( $data as $id ) {
        if ( is_numeric( $id ) ) {
            $ids[] = (int) $id;
        }
    }

    // Only limit if explicitly requested and max_records > 0
    // If max_records is 0, return all IDs
    if ( $max_records > 0 && count( $ids ) > $max_records ) {
        $ids = array_slice( $ids, 0, $max_records );
    }
    // If max_records is 0, return all IDs without limiting

    return $ids;
}

/**
 * Helper: fetch FullSpecsAll for a vessel.
 */
function yatco_fetch_fullspecs( $token, $vessel_id ) {
    $endpoint = 'https://api.yatcoboss.com/api/v1/ForSale/Vessel/' . intval( $vessel_id ) . '/Details/FullSpecsAll';

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
        return $response;
    }

    if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
        return new WP_Error( 'yatco_http_error', 'HTTP ' . wp_remote_retrieve_response_code( $response ) );
    }

    $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

    if ( null === $data ) {
        return new WP_Error( 'yatco_parse_error', 'Could not parse FullSpecsAll JSON.' );
        }

        return $data;
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

    // Vessel name: Check Result.VesselName, then BasicInfo.BoatName.
    if ( ! empty( $result['VesselName'] ) ) {
        $name = $result['VesselName'];
    } elseif ( ! empty( $basic['BoatName'] ) ) {
        $name = $basic['BoatName'];
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
 * Import a single vessel ID into the Yacht CPT.
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

    // Basic fields – updated to match actual API structure.
    $name   = '';
    $price  = '';
    $year   = '';
    $loa    = '';
    $mlsid  = '';
    $make   = '';
    $class  = '';
    $desc   = '';

    // Vessel name: Check Result.VesselName, then BasicInfo.BoatName.
    if ( ! empty( $result['VesselName'] ) ) {
        $name = $result['VesselName'];
    } elseif ( ! empty( $basic['BoatName'] ) ) {
        $name = $basic['BoatName'];
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

    // Vessel class: From BasicInfo.MainCategory, or Result.MainCategoryText.
    if ( ! empty( $basic['MainCategory'] ) ) {
        $class = $basic['MainCategory'];
    } elseif ( ! empty( $result['MainCategoryText'] ) ) {
        $class = $result['MainCategoryText'];
    }

    // Description: From VD or MiscInfo.
    if ( ! empty( $vd['VesselDescriptionShortDescriptionNoStyles'] ) ) {
        $desc = $vd['VesselDescriptionShortDescriptionNoStyles'];
    } elseif ( ! empty( $misc['VesselDescriptionShortDescription'] ) ) {
        $desc = $misc['VesselDescriptionShortDescription'];
    } elseif ( ! empty( $vd['VesselDescriptionShortDescription'] ) ) {
        $desc = $vd['VesselDescriptionShortDescription'];
    }

    // Find existing post by MLSID if available.
    $post_id = 0;
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

    $post_data = array(
        'post_type'   => 'yacht',
        'post_title'  => $name ? $name : 'Yacht ' . $vessel_id,
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

    // Store core meta – these can be mapped to ACF fields.
    update_post_meta( $post_id, 'yacht_mlsid', $mlsid );
    update_post_meta( $post_id, 'yacht_price', $price );
    update_post_meta( $post_id, 'yacht_year', $year );
    update_post_meta( $post_id, 'yacht_length', $loa );
    update_post_meta( $post_id, 'yacht_make', $make );
    update_post_meta( $post_id, 'yacht_class', $class );
    update_post_meta( $post_id, 'yacht_fullspecs_raw', $full );

    if ( ! empty( $desc ) ) {
        wp_update_post(
            array(
                'ID'           => $post_id,
                'post_content' => $desc,
            )
        );
    }

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

    return $post_id;
}

/**
 * Register shortcode for displaying YATCO vessels.
 */
function yatco_register_shortcode() {
    add_shortcode( 'yatco_vessels', 'yatco_vessels_shortcode' );
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

    // max parameter is ignored - we load ALL vessels for filtering
    // This is only used for cache key, not for limiting results
    $max_results = 999999; // Set very high so we process all vessels

    // Get cache key based on attributes.
    $cache_key = 'yatco_vessels_' . md5( serialize( $atts ) );
    
    // Check cache if enabled - first check for pre-warmed vessel data (faster)
    if ( $atts['cache'] === 'yes' ) {
        $options = get_option( 'yatco_api_settings' );
        $cache_duration = isset( $options['yatco_cache_duration'] ) ? intval( $options['yatco_cache_duration'] ) : 30;
        
        // Check for pre-warmed vessel data (much faster than generating from API)
        $cached_vessels = get_transient( 'yatco_vessels_data' );
        $cached_builders = get_transient( 'yatco_vessels_builders' );
        $cached_categories = get_transient( 'yatco_vessels_categories' );
        $cached_types = get_transient( 'yatco_vessels_types' );
        $cached_conditions = get_transient( 'yatco_vessels_conditions' );
        
        // If we have cached vessel data, use it (this is much faster!)
        if ( $cached_vessels !== false && is_array( $cached_vessels ) && ! empty( $cached_vessels ) ) {
            // Filter vessels based on shortcode attributes
            $filtered_vessels = $cached_vessels;
            if ( $atts['price_min'] !== '' || $atts['price_max'] !== '' || $atts['year_min'] !== '' || $atts['year_max'] !== '' || $atts['loa_min'] !== '' || $atts['loa_max'] !== '' ) {
                $filtered_vessels = array();
                foreach ( $cached_vessels as $vessel ) {
                    $price = ! empty( $vessel['price_usd'] ) ? floatval( $vessel['price_usd'] ) : null;
                    $year  = ! empty( $vessel['year'] ) ? intval( $vessel['year'] ) : null;
                    $loa   = ! empty( $vessel['loa_feet'] ) ? floatval( $vessel['loa_feet'] ) : null;
                    
                    $price_min = ! empty( $atts['price_min'] ) && $atts['price_min'] !== '0' ? floatval( $atts['price_min'] ) : '';
                    $price_max = ! empty( $atts['price_max'] ) && $atts['price_max'] !== '0' ? floatval( $atts['price_max'] ) : '';
                    $year_min  = ! empty( $atts['year_min'] ) && $atts['year_min'] !== '0' ? intval( $atts['year_min'] ) : '';
                    $year_max  = ! empty( $atts['year_max'] ) && $atts['year_max'] !== '0' ? intval( $atts['year_max'] ) : '';
                    $loa_min   = ! empty( $atts['loa_min'] ) && $atts['loa_min'] !== '0' ? floatval( $atts['loa_min'] ) : '';
                    $loa_max   = ! empty( $atts['loa_max'] ) && $atts['loa_max'] !== '0' ? floatval( $atts['loa_max'] ) : '';
                    
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
                    $filtered_vessels[] = $vessel;
                }
            }
            
            // Use cached data - generate HTML from cached vessels (fast!)
            $builders = $cached_builders !== false ? $cached_builders : array();
            $categories = $cached_categories !== false ? $cached_categories : array();
            $types = $cached_types !== false ? $cached_types : array();
            $conditions = $cached_conditions !== false ? $cached_conditions : array();
            
            return yatco_generate_vessels_html_from_data( $filtered_vessels, $builders, $categories, $types, $conditions, $atts );
        }
        
        // Fallback to full cached HTML output
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            return $cached;
        }
    }

    // Fetch all active vessel IDs (set to 0 to get all, or use a high limit like 10000)
    // For 7000+ vessels, we need to fetch all IDs
    $ids_to_fetch = 0; // 0 means fetch all
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
    // Note: For large datasets (7000+), this may take time. Consider increasing PHP max_execution_time.
    $vessel_count = count( $ids );
    $processed = 0;
    $error_count = 0;
    
    // Reset execution time limit for large datasets (300 seconds = 5 minutes)
    @set_time_limit( 300 );
    
    foreach ( $ids as $id ) {
        $processed++;
        
        // Reset execution time every 100 vessels to avoid timeout
        if ( $processed % 100 === 0 ) {
            @set_time_limit( 300 ); // Reset execution time
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
        
        $vessel_data = array(
            'id'          => $id,
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
            'link'        => get_post_type_archive_link( 'yacht' ) . '?vessel_id=' . $id,
        );

        $vessels[] = $vessel_data;
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

    if ( empty( $vessels ) ) {
        $output = '<p>No vessels match your criteria.</p>';
            } else {
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
                <?php if ( $vessel_count > 1000 ) : ?>
                    <div class="yatco-loading-note">Loaded <?php echo number_format( count( $vessels ) ); ?> of <?php echo number_format( $vessel_count ); ?> vessels</div>
                <?php endif; ?>
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
            <?php foreach ( $vessels as $vessel ) : ?>
                <div class="yatco-vessel-card" 
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
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <style>
            .yatco-vessels-container {
                margin: 20px 0;
            }
            .yatco-filters {
                background: #f9f9f9;
                padding: 20px;
                border-radius: 8px;
                margin-bottom: 20px;
            }
            .yatco-filters-row {
                display: flex;
                flex-wrap: wrap;
                gap: 15px;
                margin-bottom: 15px;
            }
            .yatco-filters-row-2 {
                margin-bottom: 0;
            }
            .yatco-filter-group {
                flex: 1;
                min-width: 150px;
            }
            .yatco-filter-group label {
                display: block;
                margin-bottom: 5px;
                font-weight: 600;
                font-size: 14px;
                color: #333;
            }
            .yatco-filter-input,
            .yatco-filter-select {
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
            }
            .yatco-input-small {
                width: 80px;
            }
            .yatco-filter-range {
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .yatco-filter-toggle {
                display: flex;
                margin-top: 8px;
                gap: 0;
            }
            .yatco-toggle-btn {
                padding: 6px 16px;
                border: 1px solid #0073aa;
                background: #fff;
                color: #0073aa;
                cursor: pointer;
                font-size: 13px;
                font-weight: 600;
                transition: all 0.2s;
            }
            .yatco-toggle-btn:first-child {
                border-top-left-radius: 4px;
                border-bottom-left-radius: 4px;
            }
            .yatco-toggle-btn:last-child {
                border-top-right-radius: 4px;
                border-bottom-right-radius: 4px;
                border-left: none;
            }
            .yatco-toggle-btn.active {
                background: #0073aa;
                color: #fff;
            }
            .yatco-filter-actions {
                display: flex;
                align-items: flex-end;
                gap: 10px;
            }
            .yatco-search-btn,
            .yatco-reset-btn {
                padding: 10px 24px;
                border: none;
                border-radius: 4px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
            }
            .yatco-search-btn {
                background: #0073aa;
                color: #fff;
            }
            .yatco-search-btn:hover {
                background: #005a87;
            }
            .yatco-reset-btn {
                background: #ddd;
                color: #333;
            }
            .yatco-reset-btn:hover {
                background: #ccc;
            }
            .yatco-results-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                padding: 10px 0;
            }
            .yatco-results-count {
                font-weight: 600;
                color: #333;
            }
            .yatco-sort-view {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .yatco-sort-select {
                padding: 6px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .yatco-pagination {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 15px;
                margin: 30px 0;
                padding: 20px 0;
            }
            .yatco-pagination-btn {
                padding: 10px 20px;
                border: 1px solid #0073aa;
                background: #fff;
                color: #0073aa;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 600;
                transition: all 0.2s;
            }
            .yatco-pagination-btn:hover {
                background: #0073aa;
                color: #fff;
            }
            .yatco-pagination-btn.active {
                background: #0073aa;
                color: #fff;
                font-weight: 700;
            }
            .yatco-page-info {
                font-weight: 600;
                color: #333;
                margin-left: 15px;
            }
            .yatco-page-dots {
                padding: 10px 5px;
                color: #666;
            }
            .yatco-pagination {
                flex-wrap: wrap;
                gap: 5px;
            }
            .yatco-pagination-btn.yatco-page-num {
                min-width: 40px;
            }
            .yatco-loading-note {
                font-size: 12px;
                color: #666;
                font-style: italic;
                margin-top: 5px;
            }
            .yatco-vessels-grid {
                display: grid;
                gap: 20px;
                margin: 20px 0;
            }
            .yatco-vessels-grid.yatco-col-1 { grid-template-columns: 1fr; }
            .yatco-vessels-grid.yatco-col-2 { grid-template-columns: repeat(2, 1fr); }
            .yatco-vessels-grid.yatco-col-3 { grid-template-columns: repeat(3, 1fr); }
            .yatco-vessels-grid.yatco-col-4 { grid-template-columns: repeat(4, 1fr); }
            .yatco-vessel-card {
                border: 1px solid #ddd;
                border-radius: 8px;
                overflow: hidden;
                background: #fff;
                transition: box-shadow 0.3s;
            }
            .yatco-vessel-card:hover {
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            }
            .yatco-vessel-image {
                width: 100%;
                padding-top: 75%;
                position: relative;
                overflow: hidden;
                background: #f5f5f5;
            }
            .yatco-vessel-image img {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            .yatco-vessel-info {
                padding: 15px;
            }
            .yatco-vessel-name {
                margin: 0 0 10px 0;
                font-size: 18px;
                font-weight: 600;
            }
            .yatco-vessel-details {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                font-size: 14px;
                color: #666;
            }
            .yatco-vessel-price {
                font-weight: 600;
                color: #0073aa;
            }
            @media (max-width: 768px) {
                .yatco-vessels-grid.yatco-col-2,
                .yatco-vessels-grid.yatco-col-3,
                .yatco-vessels-grid.yatco-col-4 {
                    grid-template-columns: 1fr;
                }
                .yatco-filters-row {
                    flex-direction: column;
                }
                .yatco-filter-group {
                    min-width: 100%;
                }
                .yatco-results-header {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 15px;
                }
            }
        </style>
        <script>
        (function() {
            const container = document.querySelector('.yatco-vessels-container');
            if (!container) return;
            
            const currency = container.dataset.currency || 'USD';
            const lengthUnit = container.dataset.lengthUnit || 'FT';
            const allVessels = Array.from(document.querySelectorAll('.yatco-vessel-card'));
            const grid = document.getElementById('yatco-vessels-grid');
            const resultsCount = document.querySelector('.yatco-results-count');
            const totalCount = document.getElementById('yatco-total-count');
            
            // Filter elements
            const keywords = document.getElementById('yatco-keywords');
            const builder = document.getElementById('yatco-builder');
            const yearMin = document.getElementById('yatco-year-min');
            const yearMax = document.getElementById('yatco-year-max');
            const loaMin = document.getElementById('yatco-loa-min');
            const loaMax = document.getElementById('yatco-loa-max');
            const priceMin = document.getElementById('yatco-price-min');
            const priceMax = document.getElementById('yatco-price-max');
            const condition = document.getElementById('yatco-condition');
            const type = document.getElementById('yatco-type');
            const category = document.getElementById('yatco-category');
            const cabins = document.getElementById('yatco-cabins');
            const sort = document.getElementById('yatco-sort');
            const searchBtn = document.getElementById('yatco-search-btn');
            const resetBtn = document.getElementById('yatco-reset-btn');
            
            // Toggle buttons
            const lengthBtns = document.querySelectorAll('.yatco-toggle-btn[data-unit]');
            const currencyBtns = document.querySelectorAll('.yatco-toggle-btn[data-currency]');
            
            let currentCurrency = currency;
            let currentLengthUnit = lengthUnit;
            
            function updateToggleButtons() {
                lengthBtns.forEach(btn => {
                    btn.classList.toggle('active', btn.dataset.unit === currentLengthUnit);
                });
                currencyBtns.forEach(btn => {
                    btn.classList.toggle('active', btn.dataset.currency === currentCurrency);
                });
            }
            
            lengthBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    currentLengthUnit = this.dataset.unit;
                    updateToggleButtons();
                    filterAndDisplay();
                });
            });
            
            currencyBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    currentCurrency = this.dataset.currency;
                    updateToggleButtons();
                    filterAndDisplay();
                });
            });
            
            function filterVessels() {
                const keywordVal = keywords ? keywords.value.toLowerCase() : '';
                const builderVal = builder ? builder.value : '';
                const yearMinVal = yearMin ? parseInt(yearMin.value) : null;
                const yearMaxVal = yearMax ? parseInt(yearMax.value) : null;
                const loaMinVal = loaMin ? parseFloat(loaMin.value) : null;
                const loaMaxVal = loaMax ? parseFloat(loaMax.value) : null;
                const priceMinVal = priceMin ? parseFloat(priceMin.value) : null;
                const priceMaxVal = priceMax ? parseFloat(priceMax.value) : null;
                const conditionVal = condition ? condition.value : '';
                const typeVal = type ? type.value : '';
                const categoryVal = category ? category.value : '';
                const cabinsVal = cabins ? parseInt(cabins.value) : null;
                
                return allVessels.filter(vessel => {
                    const name = vessel.dataset.name || '';
                    const location = vessel.dataset.location || '';
                    const vesselBuilder = vessel.dataset.builder || '';
                    const vesselCategory = vessel.dataset.category || '';
                    const vesselType = vessel.dataset.type || '';
                    const vesselCondition = vessel.dataset.condition || '';
                    const year = parseInt(vessel.dataset.year) || 0;
                    const loaFeet = parseFloat(vessel.dataset.loaFeet) || 0;
                    const loaMeters = parseFloat(vessel.dataset.loaMeters) || 0;
                    const priceUsd = parseFloat(vessel.dataset.priceUsd) || 0;
                    const priceEur = parseFloat(vessel.dataset.priceEur) || 0;
                    const stateRooms = parseInt(vessel.dataset.stateRooms) || 0;
                    
                    // Keywords
                    if (keywordVal && !name.includes(keywordVal) && !location.includes(keywordVal)) {
            return false;
        }
                    
                    // Builder
                    if (builderVal && vesselBuilder !== builderVal) {
            return false;
        }
                    
                    // Year
                    if (yearMinVal && (year === 0 || year < yearMinVal)) {
            return false;
        }
                    if (yearMaxVal && (year === 0 || year > yearMaxVal)) {
            return false;
        }
                    
                    // Length
                    const loa = currentLengthUnit === 'M' ? loaMeters : loaFeet;
                    if (loaMinVal && (loa === 0 || loa < loaMinVal)) {
            return false;
        }
                    if (loaMaxVal && (loa === 0 || loa > loaMaxVal)) {
            return false;
        }

                    // Price
                    const price = currentCurrency === 'EUR' ? priceEur : priceUsd;
                    if (priceMinVal && (price === 0 || price < priceMinVal)) {
                        return false;
                    }
                    if (priceMaxVal && (price === 0 || price > priceMaxVal)) {
                        return false;
                    }
                    
                    // Condition
                    if (conditionVal && vesselCondition !== conditionVal) {
                        return false;
                    }
                    
                    // Type
                    if (typeVal && vesselType !== typeVal) {
                        return false;
                    }
                    
                    // Category
                    if (categoryVal && vesselCategory !== categoryVal) {
                        return false;
                    }
                    
                    // Cabins
                    if (cabinsVal && stateRooms < cabinsVal) {
                        return false;
                    }
                    
                    return true;
                });
            }
            
            function sortVessels(vessels) {
                const sortVal = sort ? sort.value : '';
                if (!sortVal) return vessels;
                
                return [...vessels].sort((a, b) => {
                    switch(sortVal) {
                        case 'price_asc':
                            const priceA = currentCurrency === 'EUR' ? parseFloat(a.dataset.priceEur || 0) : parseFloat(a.dataset.priceUsd || 0);
                            const priceB = currentCurrency === 'EUR' ? parseFloat(b.dataset.priceEur || 0) : parseFloat(b.dataset.priceUsd || 0);
                            return priceA - priceB;
                        case 'price_desc':
                            const priceA2 = currentCurrency === 'EUR' ? parseFloat(a.dataset.priceEur || 0) : parseFloat(a.dataset.priceUsd || 0);
                            const priceB2 = currentCurrency === 'EUR' ? parseFloat(b.dataset.priceEur || 0) : parseFloat(b.dataset.priceUsd || 0);
                            return priceB2 - priceA2;
                        case 'year_desc':
                            return (parseInt(b.dataset.year) || 0) - (parseInt(a.dataset.year) || 0);
                        case 'year_asc':
                            return (parseInt(a.dataset.year) || 0) - (parseInt(b.dataset.year) || 0);
                        case 'length_desc':
                            const loaA = currentLengthUnit === 'M' ? parseFloat(a.dataset.loaMeters || 0) : parseFloat(a.dataset.loaFeet || 0);
                            const loaB = currentLengthUnit === 'M' ? parseFloat(b.dataset.loaMeters || 0) : parseFloat(b.dataset.loaFeet || 0);
                            return loaB - loaA;
                        case 'length_asc':
                            const loaA2 = currentLengthUnit === 'M' ? parseFloat(a.dataset.loaMeters || 0) : parseFloat(a.dataset.loaFeet || 0);
                            const loaB2 = currentLengthUnit === 'M' ? parseFloat(b.dataset.loaMeters || 0) : parseFloat(b.dataset.loaFeet || 0);
                            return loaA2 - loaB2;
                        case 'name_asc':
                            return (a.dataset.name || '').localeCompare(b.dataset.name || '');
                        default:
                            return 0;
                    }
                });
            }
            
            // Pagination - shows 24 vessels per page
            let currentPage = 1;
            const vesselsPerPage = 24;
            
            // Get pagination range with ellipsis for large page counts (e.g., 1 ... 5 6 7 ... 140)
            function getPaginationRange(current, total) {
                const delta = 2;
                const range = [];
                const rangeWithDots = [];
                
                if (total <= 7) {
                    // Show all pages if 7 or fewer
                    for (let i = 1; i <= total; i++) {
                        rangeWithDots.push(i);
                    }
                    return rangeWithDots;
                }
                
                for (let i = Math.max(2, current - delta); i <= Math.min(total - 1, current + delta); i++) {
                    range.push(i);
                }
                
                if (current - delta > 2) {
                    rangeWithDots.push(1, '...');
                } else {
                    rangeWithDots.push(1);
                }
                
                rangeWithDots.push(...range);
                
                if (current + delta < total - 1) {
                    rangeWithDots.push('...', total);
                } else {
                    if (total > 1) {
                        rangeWithDots.push(total);
                    }
                }
                
                return rangeWithDots;
            }
            
            function paginateVessels(vessels) {
                const start = (currentPage - 1) * vesselsPerPage;
                const end = start + vesselsPerPage;
                return vessels.slice(start, end);
            }
            
            function updatePaginationControls(totalVessels) {
                const totalPages = Math.ceil(totalVessels / vesselsPerPage);
                if (totalPages <= 1) {
                    // Hide pagination if only one page
                    const paginationContainer = document.querySelector('.yatco-pagination');
                    if (paginationContainer) {
                        paginationContainer.style.display = 'none';
                    }
                    return;
                }
                
                const pageRange = getPaginationRange(currentPage, totalPages);
                let paginationHtml = '<div class="yatco-pagination">';
                
                // Previous button
                if (currentPage > 1) {
                    paginationHtml += `<button class="yatco-pagination-btn yatco-prev-btn" onclick="window.yatcoGoToPage(${currentPage - 1})">‹ Previous</button>`;
                }
                
                // Page numbers
                pageRange.forEach((page, index) => {
                    if (page === '...') {
                        paginationHtml += `<span class="yatco-page-dots">...</span>`;
                    } else {
                        const isActive = page === currentPage;
                        paginationHtml += `<button class="yatco-pagination-btn yatco-page-num ${isActive ? 'active' : ''}" onclick="window.yatcoGoToPage(${page})">${page}</button>`;
                    }
                });
                
                // Next button
                if (currentPage < totalPages) {
                    paginationHtml += `<button class="yatco-pagination-btn yatco-next-btn" onclick="window.yatcoGoToPage(${currentPage + 1})">Next ›</button>`;
                }
                
                paginationHtml += `<span class="yatco-page-info">Page ${currentPage} of ${totalPages}</span>`;
                paginationHtml += '</div>';
                
                let paginationContainer = document.querySelector('.yatco-pagination');
                if (!paginationContainer) {
                    paginationContainer = document.createElement('div');
                    paginationContainer.className = 'yatco-pagination';
                    if (grid && grid.parentNode) {
                        grid.parentNode.insertBefore(paginationContainer, grid.nextSibling);
                    }
                }
                paginationContainer.innerHTML = paginationHtml;
                paginationContainer.style.display = 'flex';
            }
            
            window.yatcoGoToPage = function(page) {
                currentPage = page;
                filterAndDisplay();
            };
            
            function filterAndDisplay() {
                const filtered = filterVessels();
                const sorted = sortVessels(filtered);
                const paginated = paginateVessels(sorted);
                
                // Hide all vessels
                allVessels.forEach(v => v.style.display = 'none');
                
                // Show paginated vessels
                paginated.forEach(v => {
                    v.style.display = '';
                    grid.appendChild(v);
                });
                
                // Update count
                if (resultsCount) {
                    const totalFiltered = sorted.length;
                    const shownStart = totalFiltered > 0 ? (currentPage - 1) * vesselsPerPage + 1 : 0;
                    const shownEnd = Math.min(currentPage * vesselsPerPage, totalFiltered);
                    const total = totalCount ? totalCount.textContent : allVessels.length;
                    resultsCount.innerHTML = `${shownStart} - ${shownEnd} of <span id="yatco-total-count">${totalFiltered}</span> YACHTS FOUND`;
                }
                
                // Update pagination
                updatePaginationControls(sorted.length);
            }
            
            if (resetBtn) {
                resetBtn.addEventListener('click', function() {
                    if (keywords) keywords.value = '';
                    if (builder) builder.value = '';
                    if (yearMin) yearMin.value = '';
                    if (yearMax) yearMax.value = '';
                    if (loaMin) loaMin.value = '';
                    if (loaMax) loaMax.value = '';
                    if (priceMin) priceMin.value = '';
                    if (priceMax) priceMax.value = '';
                    if (condition) condition.value = '';
                    if (type) type.value = '';
                    if (category) category.value = '';
                    if (cabins) cabins.value = '';
                    if (sort) sort.value = '';
                    currentCurrency = currency;
                    currentLengthUnit = lengthUnit;
                    currentPage = 1;
                    updateToggleButtons();
                    filterAndDisplay();
                });
            }
            
            if (searchBtn) {
                searchBtn.addEventListener('click', function() {
                    currentPage = 1;
                    filterAndDisplay();
                });
            }
            
            if (sort) {
                sort.addEventListener('change', function() {
                    currentPage = 1;
                    filterAndDisplay();
                });
            }
            
            // Initialize
            updateToggleButtons();
            filterAndDisplay();
        })();
        </script>
        <?php
        $output = ob_get_clean();
    }

    // Cache the output if enabled.
    if ( $atts['cache'] === 'yes' && isset( $cache_duration ) ) {
        set_transient( $cache_key, $output, $cache_duration * MINUTE_IN_SECONDS );
    }

    return $output;
}
