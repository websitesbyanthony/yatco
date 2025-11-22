<?php
/**
 * API Functions
 * 
 * Handles all YATCO API interactions.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
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

    // Check if JSON decode failed (error occurred)
    $json_error = json_last_error();
    if ( $json_error !== JSON_ERROR_NONE && $data === null ) {
        return new WP_Error( 'yatco_parse_error', 'Could not parse FullSpecsAll JSON: ' . json_last_error_msg() );
    }
    
    // Check if API returned null (valid JSON but no data available for this vessel)
    if ( $data === null || ( is_array( $data ) && empty( $data ) ) ) {
        return new WP_Error( 'yatco_no_data', 'API returned null - this vessel does not have accessible FullSpecsAll data. The vessel may be inactive or restricted.' );
    }

    return $data;
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
        return '<div class="notice notice-error"><p>Unexpected response format.</p></div>';
    }

    $count = count( $data );
    return '<div class="notice notice-success"><p>Connection successful! Found ' . number_format( $count ) . ' active vessel ID(s).</p></div>';
}

