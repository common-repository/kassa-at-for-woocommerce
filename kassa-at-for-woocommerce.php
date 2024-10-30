<?php
	/**
	 * Plugin Name: KASSA.AT For WooCommerce
	 * Text Domain: kassa-at-for-woocommerce
	 * Domain Path: /languages
	 * Description: An API Plugin which corresponds with a KASSA.AT account to synchronize online and offline stock-changes.
	 * Version:     1.1.1
	 * Author:      Faxonline GmbH
	 * Author URI:  https://www.kassa.at
	 *
	 * @package     KASSA.AT For WooCommerce
	 *
	 * WC requires at least: 5.4.0
	 * WC tested up to: 6.1.1
	 */

// NOTE: The term "kaw" is for "kassa at woocommerce" and will be a prefix for all functions created by this plugin!

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly!
}

/**
 * Maybe create the custom logging folder and file.
 * Returning the log_file path.
 *
 * @return string
 */
function kaw_check_for_log_file() {

	$uploads  = wp_upload_dir( null, false );
	$logs_dir = $uploads['basedir'] . '/kaw-logs';

	if ( ! is_dir( $logs_dir ) ) {
		mkdir( $logs_dir, 0777, true );
	}

	if ( ! file_exists( $logs_dir . '/log.json' ) ) {
		$file = fopen( $logs_dir . '/log.json', 'a+' ); /* phpcs:ignore */
		fwrite( $file, wp_json_encode( array() ) ); /* phpcs:ignore */
		fclose( $file ); /* phpcs:ignore */
	}

	$filename = $logs_dir . '/log.json';
	return $filename;
}

/**
 * Check if variable is null or empty
 */
function IsNullOrEmptyString($var){
	return ($var === null || trim($var) === '');
}


/**
 * Asks for the log file and writes whatever is in $data to it.
 *
 * @param string  $type The type of log-entry. Can be 'API-CALL', 'API-ERROR', 'DATA-UPDATE' or 'SYSTEM'.
 * @param array   $data The things that should be written in the log_file.
 * @param boolean $force Forces logging to be written to logfile even if logging is disabled.
 */
function kaw_log_data( $type, $data, $force = false ) {
	if ( kaw_enable_log() || $force ) {
		$logfile    = kaw_check_for_log_file();
		$filestring = file_get_contents( $logfile ); /* phpcs:ignore */
		$filejson   = json_decode( $filestring );

		if( !empty($data) && !empty($filestring) && !empty($logfile) && !empty($filejson) ) {
			array_unshift(
				$filejson,
				array(
					'datetime' => gmdate( 'd-m-Y H:i:s' ),
					'type'     => $type,
					'data'     => kaw_log_data_formating( $type, $data ),
				)
			);
			$filejson   = kaw_cut_log( $filejson );
			$fileresult = wp_json_encode( $filejson );
			file_put_contents( $logfile, $fileresult ); /* phpcs:ignore */
		}
	}
}

/**
 * Brings the data in the correct Format for writing it into the logfile.
 *
 * @param  string $type The type of log-entry. Can be 'API-CALL', 'API-ERROR', 'DATA-UPDATE' or 'SYSTEM'.
 * @param  array  $data The values that should be written in the log_file.
 * @return array
 */
function kaw_log_data_formating( $type, $data ) {
	switch ( $type ) {
		case 'API-CALL':
			return array(
				'httpMethod' => $data['httpMethod'],
				'httpUrl'    => $data['httpUrl'],
				'params'     => $data['paramSting'],
				'kawKey'     => $data['kawKey'],
				'result'     => $data['result'],
				'location'   => $data['location'],
			);

		case 'API-ERROR':
			return array(
				'message'  => $data['message'],
				'location' => $data['location'],
			);

		case 'DATA-UPDATE':
			return array(
				'message'  => $data['message'],
				'key'      => $data['key'],
				'original' => $data['original'],
				'updated'  => $data['updated'],
				'location' => $data['location'],
			);

		case 'SYSTEM':
			return array(
				'message'  => $data['message'],
				'location' => $data['location'],
			);
	}
}

/**
 * Performing the act of trimming the logfile to the correct length before
 * saving its content back to the file. The default length is 1000 entries
 * but it can be a different length whitch would be
 * stored in the wp_option 'kaw-logging-size'!
 *
 * @param  array $json The content of the logfile that should be trimmed.
 * @return array
 */
function kaw_cut_log( $json ) {
	$requested_size = get_option( 'kaw-logging-size' );
	if ( ! $requested_size ) {
		$requested_size = 1000;
	}

	update_option( 'logging-info', count( $json ) );

	if ( count( $json ) > $requested_size ) {
		$json = array_slice( $json, 0, $requested_size );
	}

	return $json;
}

/**
 * Checking if the kaw logging is enabled or disabled. Default is enabled,
 * but the user can change that with value stored in the wp_option 'kaw-logging'!
 *
 * This function is overruled if the function
 * kaw_log_data (which calls this function) got $force as true!
 *
 * @return boolean
 */
function kaw_enable_log() {
	if ( get_option( 'kaw-logging' ) ) {

		if ( get_option( 'kaw-logging' ) === 'enabled' ) {
			return true;
		} elseif ( get_option( 'kaw-logging' ) === 'disabled' ) {
			return false;
		}
	}

	return true;
}

/**
 * Enabling or disabling the logging of kaw logs.
 * Called via ajax this function updates (or creates) the wp_option 'kaw-logging'
 * to stop or start again logging all non-forced loggings.
 * In the end it forces a log-entry to keep track of activating and deactivating the logging.
 */
function kaw_activate_logging() {
	$mode = ( isset( $_POST['mode'] ) ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'disable'; /* phpcs:ignore */

	if ( 'enable' === $mode ) {
		$val = 'enabled';
	} else {
		$val = 'disabled';
	}

	if ( get_option( 'kaw-logging' ) ) {
		update_option( 'kaw-logging', $val );
	} else {
		add_option( 'kaw-logging', $val );
	}
	kaw_log_data(
		'SYSTEM',
		array(
			'message'  => 'Logging ' . $val . '.',
			'location' => kaw_get_locationstring(),
		),
		true
	);

	wp_send_json_success( kaw_enable_log() );
}
add_action( 'wp_ajax_kaw_activate_logging', 'kaw_activate_logging' );

/**
 * Called via ajax this function gets a number and stores that number in the
 * wp_option 'kaw-logging-size'. This value is used to
 * trim the logfile in the process of writing to it.
 * In the end it forces a log-entry to save the updating of the logging-size.
 */
function kaw_logfile_change_size() {
	$val = ( isset( $_POST['size'] ) ) ? sanitize_text_field( wp_unslash( $_POST['size'] ) ) : '1000'; /* phpcs:ignore */

	if ( get_option( 'kaw-logging-size' ) ) {
		update_option( 'kaw-logging-size', $val );
	} else {
		add_option( 'kaw-logging-size', $val );
	}

	kaw_log_data(
		'SYSTEM',
		array(
			'message'  => 'Logging length changed to: ' . $val,
			'location' => kaw_get_locationstring(),
		),
		true
	);

	wp_send_json_success();
}
add_action( 'wp_ajax_kaw_logfile_change_size', 'kaw_logfile_change_size' );

/**
 * Deletes the kaw-logfile, deletes the kaw-log folder,
 * then creates both of them again and
 * forces a log-entry, that says that the logfile was cleared.
 */
function kaw_delete_logfile() {
	$uploads  = wp_upload_dir( null, false );
	$logs_dir = $uploads['basedir'] . '/kaw-logs';

	if ( file_exists( $logs_dir . '/log.json' ) ) {
		unlink( $logs_dir . '/log.json' );
	}

	if ( is_dir( $logs_dir ) ) {
		rmdir( $logs_dir );
	}
	kaw_log_data(
		'SYSTEM',
		array(
			'message'  => 'Logfile cleared.',
			'location' => kaw_get_locationstring(),
		),
		true
	);

	wp_send_json_success();
}
add_action( 'wp_ajax_kaw_delete_logfile', 'kaw_delete_logfile' );

/**
 * Load the translation file!
 */
function kaw_load_plugin_textdomain() {
	load_plugin_textdomain( 'kassa-at-for-woocommerce', false, basename( dirname( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'kaw_load_plugin_textdomain' );

/**
 * Log when plugin gets activated!
 * Activate the activation message!
 */
function kaw_plugin_activate() {
	kaw_log_data(
		'SYSTEM',
		array(
			'message'  => 'Plugin KASSA.AT For WooCommerce activated.',
			'location' => kaw_get_locationstring(),
		),
		true
	);
	add_option( 'kaw-message-active', 1 );
}
register_activation_hook( __FILE__, 'kaw_plugin_activate' );

/**
 * Log when plugin gets deactivated!
 */
function kaw_plugin_deactivate() {
	kaw_log_data(
		'SYSTEM',
		array(
			'message'  => 'Plugin KASSA.AT For WooCommerce deactivated.',
			'location' => kaw_get_locationstring(),
		),
		true
	);
	delete_option( 'kaw-message-active' );
}
register_deactivation_hook( __FILE__, 'kaw_plugin_deactivate' );

/**
 * Display the activation message!
 */
function kaw_plugin_notice() {
	if ( get_option( 'kaw-message-active' ) ) {
		?>
		<script type="text/javascript">
			jQuery( document ).ready( function() {
				jQuery( document ).on( 'click', '.kaw-first-setup-message .notice-dismiss', function() {
					var data = { action: 'my_dismiss_notice' };
					jQuery.post( "<?php echo esc_url( get_admin_url() . 'admin-ajax.php' ); ?>", data);
				})
			});
		</script>
		<div class="updated notice kaw-first-setup-message is-dismissible">
			<p><?php esc_attr_e( 'Please follow these instructions to use the plugin:', 'kassa-at-for-woocommerce' ); ?></p>
			<?php if ( '1' === get_option( 'kaw-message-active' ) ) : ?>
				<ol>
					<?php /* translators: %s is for the url to the KASSA.AT/create_account page */ ?>
					<li><?php printf( __( 'If you don\'t have a KASSA.AT account please create one <a href="%s">here</a>.', 'kassa-at-for-woocommerce' ), esc_url( 'dev' === kaw_get_envirement_mode() ? 'http://www.kassa.lvh.me:3000/companies/new' : 'https://www.kassa.at/companies/new' ) ); ?></li>
					<li><?php esc_attr_e( 'Use the Button "Connect with KASSA.AT account" to connect to the register-service.', 'kassa-at-for-woocommerce' ); ?></li>
				</ol>
			<?php elseif ( '2' === get_option( 'kaw-message-active' ) ) : ?>
				<ol>
					<li><?php esc_attr_e( 'Login the KASSA.AT site and create your articles. Note that in order to have connect the WP-site and the KASSA.AT-account, you need to fill in the "Artikelnummer"-field with your article numbers.', 'kassa-at-for-woocommerce' ); ?></li>
					<li><?php esc_attr_e( 'In your KASSA.AT account, create a warehouse and assign the articles to it.', 'kassa-at-for-woocommerce' ); ?></li>
					<li><?php esc_attr_e( 'Go back to your WordPress page, choose your register-warehouse from your KASSA.AT\'s warehouses.', 'kassa-at-for-woocommerce' ); ?></li>
					<li><?php esc_attr_e( 'Go to your woocommerce-article section and create or edit your articles, use the article-number from your KASSA.AT-articles in the "SKU"-field and activate stock-management.', 'kassa-at-for-woocommerce' ); ?></li>
					<li><?php esc_attr_e( 'Go to the KASSA.AT-menu in your wordpress-site and press the "Synchronize!" Button.', 'kassa-at-for-woocommerce' ); ?></li>
				</ol>
				<p><?php esc_attr_e( 'And here we go. Whenever a customer buys anything in your local store or a customer orders something in your online-store, your KASSA.AT-service will have trace of that and will always check, that the onlineshop displays the correct amount of items in stock.', 'kassa-at-for-woocommerce' ); ?></p>
			<?php endif; ?>
			<p>
				<?php
					printf(
						/* translators: %s is for the linked part of the sentance */
						esc_html__( 'If you have any questions with the setting up, feel free to %s.', 'kassa-at-for-woocommerce' ),
						sprintf(
							'<a href="%s">%s</a>',
							esc_url( 'https://kassa.at/kontakt/' ),
							esc_html__( 'contact us', 'kassa-at-for-woocommerce' )
						)
					);
				?>
			</p>
		</div>
		<?php
	}
}
add_action( 'admin_notices', 'kaw_plugin_notice' );

/**
 * Change to part two of the activation-message!
 */
function kaw_update_kaw_message_active() {
	update_option( 'kaw-message-active', 0 );
}
add_action( 'wp_ajax_my_dismiss_notice', 'kaw_update_kaw_message_active' );

/**
 * Get the current Filename and Linenumber!
 */
function kaw_get_locationstring() {
	$bt     = debug_backtrace(); /* phpcs:ignore */
	$caller = array_shift( $bt );

	if ( count( explode( 'wp-content', $caller['file'] ) ) === 2 ) {
		$path = '/wp-content' . explode( 'wp-content', $caller['file'] )[1];
	} else {
		$path = $caller['file'];
	}
	return $path . ':' . $caller['line'];
}

require 'api-connection.php';
require 'create-menus.php';
require 'stock-syncro.php';
