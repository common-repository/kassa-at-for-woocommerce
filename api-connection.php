<?php
	/**
	 * This File manages calls to the KASSA.AT API.
	 * Just call kaw_call_api($method, $action, $params) and these functions will do the trick.
	 *
	 * @package KASSA.AT For WooCommerce
	 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly!
}

/* Include class WP_HTTP from /wp-includes/http.php */
if ( ! class_exists( 'WP_Http' ) ) {
	include_once ABSPATH . WPINC . '/class-http.php';
}

/**
 * Return string 'dev' || 'pro' to indicate current state.
 * ('dev' is testing, 'pro' is live envirement)
 *
 * @return string
 */
function kaw_get_envirement_mode() {
	return 'pro';
}

/**
 * Return the KASSA.AT Host Domain.
 *
 * @return string
 */
function kaw_get_api_host() {
	if ( kaw_get_envirement_mode() === 'pro' ) {
		$host = 'https://' . kaw_get_subdomain() . '.kassa.at';
	} elseif ( kaw_get_envirement_mode() === 'dev' ) {
		$host = 'http://' . kaw_get_subdomain() . '.kassa.at.192.168.165.45.nip.io:3000';
	}
	return $host;
}

/**
 * Return the subdomain, stored in the database.
 *
 * @return string
 */
function kaw_get_subdomain() {
	$subdomain = get_option( 'kaw-subdomain' );
	return $subdomain;
}

/**
 * Return the KASSA.AT API version.
 *
 * @param string $suggestion The version, that should be used or empty for version 1.
 * @return string
 */
function kaw_get_api_version( $suggestion = 'v1' ) {
	$version = $suggestion;
	return $version;
}

/**
 * Calls the KASSA.AT API and returns the DATA as an object.
 *
 * @param string $method The HTTP-Methode that should be used for the API-call.
 * @param string $action The path you want to call through the API.
 * @param array  $params The parameters you want to send with the API.
 * @return object
 */
function kaw_call_api( $method, $action, $params ) {
	/**
	 * We won't be using cURL here because it can or can not be installed on the server
	 * and we dont want to print something like: 'ERROR: Please install cURL on your hosting server!'
	 */

	$site = kaw_get_api_host() . '/api/' . kaw_get_api_version( 'v1' ) . $action;

	$args['method']                   = $method;
	$args['headers']['Authorization'] = get_option( 'kaw-key' );
	if ( 'GET' === $method ) {
		$args['headers']['Content-Type'] = 'application/json';

	}
	$args['body'] = $params;
	$request      = new WP_Http();
	$result       = $request->request( $site, $args );

	if ( is_wp_error( $result ) ) {
		$result->length = '0';
		$json           = $result;
	} else {
		$json = json_decode( $result['body'] );
	}

	kaw_log_data(
		'API-CALL',
		array(
			'httpMethod' => $method,
			'httpUrl'    => $site,
			'paramSting' => $params,
			'kawKey'     => get_option( 'kaw-key' ),
			'result'     => $json,
			'location'   => kaw_get_locationstring(),
		)
	);

	return $json;
}
