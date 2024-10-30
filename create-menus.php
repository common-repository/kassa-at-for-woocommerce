<?php
	/**
	 * This File is responsible for doing the admin menu
	 * section for the KASSA.AT-connection.
	 *
	 * @package KASSA.AT For WooCommerce
	 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly!
}

/**
 * Integrating the KASSA.AT menu in the Admin menu pool.
 */
function kaw_create_menu() {
	add_menu_page(
		'KASSA.AT',                                                /* page_title */
		'KASSA.AT',                                                /* menu_title */
		'administrator',                                           /* capability */
		'kaw',                                                     /* menu_slug */
		'kaw_create_kassa_at_options_page',                        /* function */
		plugins_url( 'kassa-at-for-woocommerce/icon.png' )         /* icon_url */
	);

	add_submenu_page(
		'kaw',                                                     /* parent_slug*/
		'KASSA.AT ' . __( 'Options', 'kassa-at-for-woocommerce' ), /* page_title */
		__( 'Options', 'kassa-at-for-woocommerce' ),               /* menu_title */
		'administrator',                                           /* capability */
		'kaw',                                                     /* menu_slug */
		'kaw_create_kassa_at_options_page'                         /* function */
	);

	add_submenu_page(
		'kaw',                                                     /* parent_slug*/
		'KASSA.AT ' . __( 'Logs', 'kassa-at-for-woocommerce' ),    /* page_title */
		__( 'Logs', 'kassa-at-for-woocommerce' ),                  /* menu_title */
		'administrator',                                           /* capability */
		'kaw_logs',                                                /* menu_slug */
		'kaw_get_log_page_content'                                 /* function */
	);
}
add_action( 'admin_menu', 'kaw_create_menu' );

/**
 * Creating the PHP Code for the KASSA.AT admin menu.
 */
function kaw_create_kassa_at_options_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_attr( __( 'You do not have sufficient permissions to access this page.' ) ) );
	}

	kaw_maybe_save_data();
	if ( isset( $_POST['submit_hidden'] ) && 'Z' === sanitize_text_field( wp_unslash( $_POST['submit_hidden'] ) ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) ) ) {
		kaw_synchronize_k_to_w();
	}

	kaw_get_main_page_content();
}

/**
 * Determine if there is anything to save eighter because the user saved a form
 * or connected to a KASSA.AT account. And then save it to the database.
 */
function kaw_maybe_save_data() {
	if ( isset( $_POST['submit_hidden'] ) && 'W' === $_POST['submit_hidden'] && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) ) ) {
		if ( isset( $_POST['kaw-warehouse'] ) ) {
			$opt_val = sanitize_text_field( wp_unslash( $_POST['kaw-warehouse'] ) );

			if ( get_option( 'kaw-warehouse' ) ) {
				$old_warehouse = get_option( 'kaw-warehouse' );
				update_option( 'kaw-warehouse', $opt_val );
			} else {
				$old_warehouse = '';
				add_option( 'kaw-warehouse', $opt_val );
			}
			kaw_log_data(
				'DATA-UPDATE',
				array(
					'message'  => 'Connected warehouse updated',
					'key'      => 'kaw-warehouse',
					'original' => $old_warehouse,
					'updated'  => $opt_val,
					'location' => kaw_get_locationstring(),
				)
			);
		}
	}

	if ( isset( $_GET['subdomain'] ) ) {
		$subdomain_string = sanitize_text_field( wp_unslash( $_GET['subdomain'] ) );

		if ( get_option( 'kaw-subdomain' ) ) {
			$old_subdomain = get_option( 'kaw-subdomain' );
			update_option( 'kaw-subdomain', $subdomain_string );
		} else {
			$old_subdomain = '';
			add_option( 'kaw-subdomain', $subdomain_string );
		}
		kaw_log_data(
			'SYSTEM',
			array(
				'message'  => 'Connection to KASSA.AT established.',
				'location' => kaw_get_locationstring(),
			)
		);
		kaw_log_data(
			'DATA-UPDATE',
			array(
				'message'  => 'Connected subdomain updated',
				'key'      => 'kaw-subdomain',
				'original' => $old_subdomain,
				'updated'  => $subdomain_string,
				'location' => kaw_get_locationstring(),
			)
		);
	}

	if ( isset( $_GET['key'] ) ) {
		$key_string = sanitize_text_field( wp_unslash( $_GET['key'] ) );

		if ( get_option( 'kaw-key' ) ) {
			update_option( 'kaw-key', $key_string );
		} else {
			add_option( 'kaw-key', $key_string );
			if ( get_option( 'kaw-message-active' ) ) {
				update_option( 'kaw-message-active', 2 );
			} else {
				add_option( 'kaw-message-active', 2 );
			}
		}
	}
}

/**
 * Delete the connection to KASSA.AT and remove
 * the most important entries from the database.
 */
function kaw_delete_connection() {
	delete_option( 'kaw-key' );
	delete_option( 'kaw-warehouse' );
	delete_option( 'kaw-subdomain' );

	if ( get_option( 'kaw-message-active' ) ) {
		update_option( 'kaw-message-active', 1 );
	} else {
		add_option( 'kaw-message-active', 1 );
	}
	kaw_log_data(
		'SYSTEM',
		array(
			'message'  => 'Connection to KASSA.AT removed.',
			'location' => kaw_get_locationstring(),
		)
	);

	wp_send_json_success();
}
add_action( 'wp_ajax_kaw_delete_connection', 'kaw_delete_connection' );


/**
 * A function to grab the kaw logfile and return its content as json.
 * Meant to be called via ajax.
 */
function kaw_reload_log_file() {
	$logfile     = kaw_check_for_log_file();
	$contentstr  = file_get_contents( $logfile ); // phpcs:ignore
	$contentjson = json_decode( $contentstr );
	wp_send_json_success( $contentjson );
}
add_action( 'wp_ajax_kaw_reload_log_file', 'kaw_reload_log_file' );

/**
 * Creating the HTML code for the admin-menu for KASSA.AT.
 * Including HTML, JS and a little bit of PHP.
 */
function kaw_get_main_page_content() {
	$key_isset     = get_option( 'kaw-key' );
	$kaw_warehouse = get_option( 'kaw-warehouse' );
	$state         = kaw_get_envirement_mode();
	if ( $key_isset ) {
		$kaw_warehouses = kaw_call_api( 'GET', '/warehouses', array( 'deleted' => 'false' ) );
		if ( ! $kaw_warehouse && $kaw_warehouses->length > 0 ) {
			add_option( 'kaw-warehouse', $kaw_warehouses->details[0]->id );
		}
	}
	?>
	<script type="text/javascript">
		window.onload = function () {
			<?php if ( isset( $_GET['key'] /* phpcs:ignore*/ ) ) { ?>
				window.location.href = window.location.href.split("&")[0];
			<?php } ?>
			<?php if ( isset( $_GET['delete_kaw'] /* phpcs:ignore */ ) ) { ?>
				window.location.href = window.location.href.split("&")[0];
			<?php } ?>
		}

		function kaw_change_sync_enabeling( checkbox ) {
			checkbox = jQuery( checkbox );

			let field = checkbox.data( 'field' );
			let mode  = checkbox.is( ':checked' );

			jQuery.ajax({
				type: 'POST',
				url: '<?php echo esc_url( get_admin_url() . 'admin-ajax.php' ); ?>',
				data: {
					action: 'kaw_activate_synchro_option',
					field: field,
					mode: mode
				}
			});
		}

		function kaw_delete_connection() {
			jQuery.ajax({
				type: 'POST',
				url: '<?php echo esc_url( get_admin_url() . 'admin-ajax.php' ); ?>',
				data: {
					action: 'kaw_delete_connection'
				},
				success: function () {
					location.reload();
				}
			});
		}
	</script>

	<div style="height: 50px"></div>
	<h1><?php esc_attr_e( 'KASSA.AT connection:', 'kassa-at-for-woocommerce' ); ?></h1>
	<?php if ( $key_isset ) : ?>
		<p>
			<?php esc_attr_e( 'You are already connected to a KASSA.AT account.', 'kassa-at-for-woocommerce' ); ?>
			(<?php echo esc_attr( get_option( 'kaw-subdomain' ) ); ?>)<br />
			<a onclick="kaw_delete_connection()" style="cursor: pointer;" id="delete_kaw_key">
				<?php esc_attr_e( 'Remove connection to KASSA.AT.', 'kassa-at-for-woocommerce' ); ?>
			</a>
		</p>
	<?php else : ?>
		<?php $url = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ) ? 'https://' : 'http://'; ?>
		<?php $url .= ( isset( $_SERVER['HTTP_HOST'] ) && isset( $_SERVER['REQUEST_URI'] ) ) ? esc_attr( $_SERVER['HTTP_HOST'] ) . esc_attr( $_SERVER['REQUEST_URI'] ) : ''; /* phpcs:ignore */ ?>

		<form id="connect-to-kaw-form" action="<?php echo esc_url( preg_replace( '/\/\/[^.]*\./', '//wp.', kaw_get_api_host(), 1 ) ); ?>/api/v1/authentications/authenticate" method="get">
			<input id="hidden-field-return-path" name="return_path" type="hidden" value="<?php echo esc_url( $url ); ?>">
			<input name="description" type="hidden" value="Woocommerce Plugin">
			<button type="submit" name="Submit" class="button-primary">
				<?php esc_attr_e( 'Connect with KASSA.AT account', 'kassa-at-for-woocommerce' ); ?>
			</button>
		</form>
		<br />
		<a id="kaw-create-link" href="<?php echo esc_url( preg_replace( '/\/\/[^.]*\./', '//wp.', kaw_get_api_host(), 1 ) ); ?>/companies/new" target="_blank">
			<?php esc_attr_e( 'Dont have a KASSA.AT account? Create one!', 'kassa-at-for-woocommerce' ); ?>
		</a>
	<?php endif; ?>

	<?php if ( $key_isset ) : ?>
		<div style="height:50px"></div>
		<h1><?php esc_attr_e( 'Synchronize Stocks:', 'kassa-at-for-woocommerce' ); ?></h1>
		<h2><?php esc_attr_e( 'Choose warehouse:', 'kassa-at-for-woocommerce' ); ?></h2>
		<form name="choose-kaw-warehouse-form" method="post" action="">
			<input type="hidden" name="submit_hidden" value="W">
			<?php wp_nonce_field(); ?>
			<select name="kaw-warehouse" id="kaw-warehouse">
			<?php foreach ( $kaw_warehouses->details as &$warehouse ) { ?>
				<option value="<?php echo esc_attr( $warehouse->id ); ?>" <?php echo ( esc_attr( $warehouse->id ) === get_option( 'kaw-warehouse' ) ) ? 'selected' : ''; ?>><?php echo esc_attr( $warehouse->description ); ?></option>
			<?php } ?>
			</select>
			<button type="submit" name="Submit" class="button-primary">
				<?php esc_attr_e( 'Save changes!', 'kassa-at-for-woocommerce' ); ?>
			</button>
		</form>
		<table>
			<tr>
				<td>
					<p>
						<?php esc_attr_e( 'Synchronize stocks with KASSA.AT.', 'kassa-at-for-woocommerce' ); ?>
						(<?php esc_attr_e( 'Use KASSA.AT-data', 'kassa-at-for-woocommerce' ); ?>)
					</p>
				</td>
				<td>
					<form name="synchronize-stocks-ktow-form" method="post" action="">
						<input type="hidden" name="submit_hidden" value="Z">
						<?php wp_nonce_field(); ?>
						<button type="submit" name="Submit" class="button-primary"><?php esc_attr_e( 'Synchronize!', 'kassa-at-for-woocommerce' ); ?></button>
					</form>
				</td>
			</tr>
		</table>
		<table>
			<tr>
				<td>
					<input id="kaw-synchronize-at-singleproduct" type="checkbox" onchange="kaw_change_sync_enabeling( this )" data-field="kaw-synchronize-at-singleproduct" <?php echo ( ! get_option( 'kaw-synchronize-at-singleproduct' ) || get_option( 'kaw-synchronize-at-singleproduct' ) === 'enabled' ) ? 'checked' : ''; ?>>
				</td>
				<td>
					<label for="kaw-synchronize-at-singleproduct"><?php esc_attr_e( 'Synchronize on the Single Product Page!', 'kassa-at-for-woocommerce' ); ?></label>
				</td>
			</tr>

			<tr>
				<td>
					<input id="kaw-synchronize-at-cart" type="checkbox" onchange="kaw_change_sync_enabeling( this )" data-field="kaw-synchronize-at-cart" <?php echo ( ! get_option( 'kaw-synchronize-at-cart' ) || get_option( 'kaw-synchronize-at-cart' ) === 'enabled' ) ? 'checked' : ''; ?>>
				</td>
				<td>
					<label for="kaw-synchronize-at-cart"><?php esc_attr_e( 'Synchronize on the Cart Page!', 'kassa-at-for-woocommerce' ); ?></label>
				</td>
			</tr>

			<tr style="display: none">
				<td>
					<input id="kaw-synchronize-on-order" type="checkbox" onchange="kaw_change_sync_enabeling( this )" data-field="kaw-synchronize-on-order" <?php echo ( ! get_option( 'kaw-synchronize-on-order' ) || get_option( 'kaw-synchronize-on-order' ) === 'enabled' ) ? 'checked' : ''; ?> disabled>
				</td>
				<td>
					<label for="kaw-synchronize-on-order"><?php esc_attr_e( 'Synchronize when the order is received!', 'kassa-at-for-woocommerce' ); ?></label>
				</td>
			</tr>
		</table>
		<br />
	<?php endif; ?>
<?php } ?>

<?php
/**
 * Creating the HTML code for the log-page in the admin-menu for KASSA.AT.
 * Including HTML, JS, css and PHP.
 */
function kaw_get_log_page_content() {
	?>
	<style media="screen">
		div.kawlog-lineno {
			text-align: center;
			background-color: rgba(0,0,0,0.1);
			color: #888;
		}

		div.kawlog-row {
			min-height: 25px;
		}

		div.kawlog-row.kawlog-row-primary {
			border-top: rgba(0,0,0,0.1) 1px solid;
		}

		div#kawlog-container {
			display: flex;
			flex-direction: column;
			height: 400px;
			overflow: auto;
			width: 99%;
			white-space: nowrap;
			border: 1px #bbb solid;
			padding: 5px;
			border-radius: 3px;
			max-width: 1280px;
			background-color: #fff;
		}

		table.kaw-option-table thead {
			font-weight: bold;
		}

		table.kaw-option-table td {
			padding: 8px 16px;
			border: rgba(0,0,0,0.1) 1px solid;
		}
	</style>

	<script type="text/javascript">
		function kawlog_reload() {
			jQuery.ajax({
				type: 'GET',
				url: '<?php echo esc_url( get_admin_url() . 'admin-ajax.php' ); ?>',
				data: {
					action: 'kaw_reload_log_file'
				},
				success: function( data ) {
					let currentDate = new Date();
					let refreshDateTime = ( ( currentDate.getDate() < 10 ) ? '0' : '' ) + currentDate.getDate() + '.' + ( ( currentDate.getMonth() < 10 ) ? '0' : '' ) + ( currentDate.getMonth() + 1 ) + '.' + currentDate.getFullYear() + ' ' + ( ( currentDate.getHours() < 10 ) ? '0' : '' ) + currentDate.getHours() + ':' + ( ( currentDate.getMinutes() < 10 ) ? '0' : '' ) + currentDate.getMinutes() + ':' + ( ( currentDate.getSeconds() < 10 ) ? '0' : '' ) + currentDate.getSeconds();
					jQuery( 'span#kawlog-reload-time' ).attr( 'title', refreshDateTime );
					jQuery( 'span#kawlog-reload-time' ).text( refreshDateTime.split( ' ' )[1] );
					document.getElementById( 'kawlog-container' ).innerHTML = '';

					var kawloghtml = '';
					data.data.forEach((log, line) => {
						line = parseInt( line ) + 1;
						kawloghtml += "<div class='kawlog-row kawlog-row-primary' style='display: flex; flex-wrap: nowrap; cursor: pointer;' onclick='jQuery( \"div#kaw_logs_extra_info_" + line + "\" ).slideToggle()'>";
						kawloghtml += "<div class='kawlog-lineno' style='width: 50px;'>" + line + "</div>";
						kawloghtml +=	"<div style='width: 150px'>" + log.datetime + "</div>";
						kawloghtml += "<div style='width: 100px'>" + log.type + "</div>";
						if ( log.type === 'API-CALL' ) {
							kawloghtml += "<div style='width: calc(100% - 300px)'>[" + log.data.httpMethod + "] " + log.data.httpUrl + "</div>";
						} else if ( log.type === 'API-ERROR' ) {
							kawloghtml += "<div style='width: calc(100% - 300px)'>" + log.data.message + "</div>";
						} else if ( log.type === 'DATA-UPDATE' ) {
							kawloghtml += "<div style='width: calc(100% - 300px)'>[" + log.data.message + "] " + log.data.key + "</div>";
						} else if ( log.type === 'SYSTEM' ) {
							kawloghtml += "<div style='width: calc(100% - 300px)'>" + log.data.message + "</div>";
						}
						kawloghtml += "</div>";

						kawloghtml += "<div id='kaw_logs_extra_info_" + line + "' style='display: none;'>";
						kawloghtml += "<div style='display: flex; flex-direction: column;'>";
						let linestart = "<div class='kawlog-row' style='display: flex; flex-direction: auto;'><div class='kawlog-lineno' style='width: 50px;'></div><div style='width: 150px'></div>";
						if ( log.type === 'API-CALL' ) {
							kawloghtml += linestart + "<div style='width: 100px'><?php esc_attr_e( 'Parameters', 'kassa-at-for-woocommerce' ); ?>: </div><div style='width: calc(100% - 300px)'>" + JSON.stringify( log.data.params ) + "</div></div>";
							kawloghtml += linestart + "<div style='width: 100px'><?php esc_attr_e( 'API-Key', 'kassa-at-for-woocommerce' ); ?>: </div><div style='width: calc(100% - 300px)'>" + log.data.kawKey + "</div></div>";
							kawloghtml += linestart + "<div style='width: 100px'><?php esc_attr_e( 'Result', 'kassa-at-for-woocommerce' ); ?>: </div><div style='width: calc(100% - 300px)'>" + JSON.stringify( log.data.result ) + "</div></div>";
							kawloghtml += linestart + "<div style='width: 100px'><?php esc_attr_e( 'Location', 'kassa-at-for-woocommerce' ); ?>: </div><div style='width: calc(100% - 300px)'>" + log.data.location + "</div></div>";
						} else if ( log.type === 'API-ERROR' ) {
							kawloghtml += linestart + "<div style='width: 100px'><?php esc_attr_e( 'Location', 'kassa-at-for-woocommerce' ); ?>: </div><div style='width: calc(100% - 300px)'>" + log.data.location + "</div></div>";
						} else if ( log.type === 'DATA-UPDATE' ) {
							kawloghtml += linestart + "<div style='width: 100px'><?php esc_attr_e( 'Changes', 'kassa-at-for-woocommerce' ); ?>: </div><div style='width: calc(100% - 300px)'>" + log.data.original + " => " + log.data.updated + " (" + log.data.key + ")</div></div>";
							kawloghtml += linestart + "<div style='width: 100px'><?php esc_attr_e( 'Location', 'kassa-at-for-woocommerce' ); ?>: </div><div style='width: calc(100% - 300px)'>" + log.data.location + "</div></div>";
						} else if ( log.type === 'SYSTEM' ) {
							kawloghtml += linestart + "<div style='width: 100px'><?php esc_attr_e( 'Location', 'kassa-at-for-woocommerce' ); ?>: </div><div style='width: calc(100% - 300px)'>" + log.data.location + "</div></div>";
						}

						kawloghtml += "</div>";
						kawloghtml += "</div>";
					});
					document.getElementById( 'kawlog-container' ).innerHTML = kawloghtml;
				}
			});
		}

		function kawlog_enable( btn ) {
			btn = jQuery( btn );
			var mode = '';
			if ( btn.data( 'currentstate' ) === 'enabled' ) {
				// disable logs
				mode = 'disable';
			} else if ( btn.data( 'currentstate' ) === 'disabled' ) {
				// enable logs
				mode = 'enable';
			}

			jQuery.ajax({
				type: 'POST',
				url: '<?php echo esc_url( get_admin_url() . 'admin-ajax.php' ); ?>',
				data: {
					action: 'kaw_activate_logging',
					mode: mode
				},
				success: function( data ) {
					if ( data.data === false ) {
						btn.html( btn.data( 'enabletext' ) );
						btn.data( 'currentstate', 'disabled' );
					} else if ( data.data === true ) {
						btn.html( btn.data( 'disabletext' ) );
						btn.data( 'currentstate', 'enabled' );
					}

					kawlog_reload();
				}
			});
		}

		function kawlog_delete() {
			jQuery.ajax({
				type: 'POST',
				url: '<?php echo esc_url( get_admin_url() . 'admin-ajax.php' ); ?>',
				data: {
					action: 'kaw_delete_logfile'
				},
				success: function( data ) {
					if ( data.success === true ) {
						kawlog_reload();
					}
				}
			});
		}

		function kawlog_download() {
			jQuery.ajax({
				type: 'GET',
				url: '<?php echo esc_url( get_admin_url() . 'admin-ajax.php' ); ?>',
				data: {
					action: 'kaw_reload_log_file'
				},
				success: function( data ) {
					var dataStr = 'data:text/json;charset=utf-8,' + encodeURIComponent( JSON.stringify( data.data, null, '\t' ) );
					var dlAnchorElem = document.createElement( 'a' );
					dlAnchorElem.setAttribute( 'href', dataStr );
					dlAnchorElem.setAttribute( 'download', 'KAW-Logfile.json' );
					document.body.appendChild( dlAnchorElem );
					dlAnchorElem.click();
					dlAnchorElem.remove();
				}
			});
		}

		function kawlog_size_save() {
			let select = jQuery( 'select#kawlog-size-select' )
			jQuery.ajax({
				type: 'POST',
				url: '<?php echo esc_url( get_admin_url() . 'admin-ajax.php' ); ?>',
				data: {
					action: 'kaw_logfile_change_size',
					size: select.val()
				},
				success: function( data ) {
					if ( data.success === true ) {
						kawlog_reload();
					}
				}
			});
		}
	</script>

	<h1><?php esc_attr_e( 'Plugin Logs:', 'kassa-at-for-woocommerce' ); ?></h1>
	<div style="display: flex; justify-content: space-between; width: 99%; max-width: 1280px;">
		<div style="width: calc((100% / 3) - 50px); min-width:230px; text-align: left;">
			<button class="button-secondary" type="button" onclick="kawlog_reload()"><?php esc_attr_e( 'Reload logfile', 'kassa-at-for-woocommerce' ); ?></button>
			<span id="kawlog-reload-title"><?php esc_attr_e( 'Refreshed', 'kassa-at-for-woocommerce' ); ?>: </span>
			<span id="kawlog-reload-time">-</span>
		</div>

		<div style="width: calc((100% / 3) + 100px); min-width:350px; text-align: center;">
			<button id="kawlog_enable_btn" class="button-secondary" onclick="kawlog_enable( this )" data-currentstate="<?php echo kaw_enable_log() ? 'enabled' : 'disabled'; ?>" data-enabletext="<?php esc_attr_e( 'Enable Logging', 'kassa-at-for-woocommerce' ); ?>" data-disabletext="<?php esc_attr_e( 'Disable Logging', 'kassa-at-for-woocommerce' ); ?>">
				<?php kaw_enable_log() ? esc_attr_e( 'Disable Logging', 'kassa-at-for-woocommerce' ) : esc_attr_e( 'Enable Logging', 'kassa-at-for-woocommerce' ); ?>
			</button>
			<button id="kawlog_delete_btn" class="button-secondary" type="button" onclick="kawlog_delete()"><?php esc_attr_e( 'Delete logfile', 'kassa-at-for-woocommerce' ); ?></button>
			<button id="kawlog_download_btn" class="button-secondary" type="button" onclick="kawlog_download()"><?php esc_attr_e( 'Download logfile', 'kassa-at-for-woocommerce' ); ?></button>
		</div>

		<div style="width: calc((100% / 3) - 50px); min-width:175px; text-align: right;">
			<select id="kawlog-size-select" name="kawlog-size" title="<?php esc_html_e( 'Limit kaw-log length', 'kassa-at-for-woocommerce' ); ?>">
				<option value="500" <?php echo ( intval( get_option( 'kaw-logging-size' ) ) === 500 ) ? 'selected' : ''; ?>>500 <?php esc_attr_e( 'lines', 'kassa-at-for-woocommerce' ); ?></option>
				<option value="1000" <?php echo ( ! get_option( 'kaw-logging-size' ) || intval( get_option( 'kaw-logging-size' ) ) === 1000 ) ? 'selected' : ''; ?>>1000 <?php esc_attr_e( 'lines', 'kassa-at-for-woocommerce' ); ?></option>
				<option value="2000" <?php echo ( intval( get_option( 'kaw-logging-size' ) ) === 2000 ) ? 'selected' : ''; ?>>2000 <?php esc_attr_e( 'lines', 'kassa-at-for-woocommerce' ); ?></option>
				<option value="5000" <?php echo ( intval( get_option( 'kaw-logging-size' ) ) === 5000 ) ? 'selected' : ''; ?>>5000 <?php esc_attr_e( 'lines', 'kassa-at-for-woocommerce' ); ?></option>
				<?php if ( get_option( 'kaw-logging-size' ) && ( ! in_array( strval( get_option( 'kaw-logging-size' ) ), array( '500', '1000', '2000', '5000' ), true ) ) ) : ?>
					<option value="<?php echo esc_html( get_option( 'kaw-logging-size' ) ); ?>" selected><?php echo esc_html( get_option( 'kaw-logging-size' ) ); ?> <?php esc_attr_e( 'lines', 'kassa-at-for-woocommerce' ); ?></option>
				<?php endif; ?>
			</select>
			<button id="kawlog_size_save_btn" class="button-secondary" onclick="kawlog_size_save()" title="<?php esc_attr_e( 'Limit kaw-log length', 'kassa-at-for-woocommerce' ); ?>"><?php esc_attr_e( 'Save', 'kassa-at-for-woocommerce' ); ?></button>
		</div>
	</div>

	<?php $logfile = kaw_check_for_log_file(); ?>
	<?php $json_logdata = json_decode( file_get_contents( $logfile ) ); /* phpcs:ignore */ ?>
	<div id="kawlog-container" style="margin: 10px 0px 50px 0px;">
		<?php foreach ( $json_logdata as $line => $log ) { ?>
			<div class="kawlog-row kawlog-row-primary" style="display: flex; flex-wrap: nowrap; cursor: pointer;" onclick="jQuery( 'div#kaw_logs_extra_info_<?php echo esc_html( $line + 1 ); ?>' ).slideToggle()">
				<div class="kawlog-lineno" style="width: 50px;" ><?php echo esc_attr( $line + 1 ); ?></div>
				<div style="width: 150px"><?php echo esc_attr( $log->datetime ); ?></div>
				<div style="width: 100px"><?php echo esc_attr( $log->type ); ?></div>
				<?php if ( 'API-CALL' === $log->type ) : ?>
					<div style="width: calc(100% - 300px)">[<?php echo esc_attr( $log->data->httpMethod ); ?>] <?php echo esc_attr( $log->data->httpUrl ); ?></div>
				<?php elseif ( 'API-ERROR' === $log->type ) : ?>
					<div style="width: calc(100% - 300px)"><?php echo esc_attr( $log->data->message ); ?></div>
				<?php elseif ( 'DATA-UPDATE' === $log->type ) : ?>
					<div style="width: calc(100% - 300px)">[<?php echo esc_attr( $log->data->message ); ?>] <?php echo esc_attr( $log->data->key ); ?></div>
				<?php elseif ( 'SYSTEM' === $log->type ) : ?>
					<div style="width: calc(100% - 300px)"><?php echo esc_attr( $log->data->message ); ?></div>
				<?php endif; ?>
			</div>
			<div id="kaw_logs_extra_info_<?php echo esc_html( $line + 1 ); ?>" style="display: none;">
				<div style="display: flex; flex-direction: column;">
					<?php if ( 'API-CALL' === $log->type ) : ?>
						<div class="kawlog-row" style="display: flex; flex-direction: auto;"><div class="kawlog-lineno" style="width: 50px;"></div><div style="width: 150px"></div><div style="width: 100px"><?php esc_attr_e( 'Parameters', 'kassa-at-for-woocommerce' ); ?>: </div><div style="width: calc(100% - 300px)"><?php echo esc_html( wp_json_encode( (array) $log->data->params ) ); ?></div></div>
						<div class="kawlog-row" style="display: flex; flex-direction: auto;"><div class="kawlog-lineno" style="width: 50px;"></div><div style="width: 150px"></div><div style="width: 100px"><?php esc_attr_e( 'API-Key', 'kassa-at-for-woocommerce' ); ?>: </div><div style="width: calc(100% - 300px)"><?php echo esc_attr( $log->data->kawKey ); ?></div></div>
						<div class="kawlog-row" style="display: flex; flex-direction: auto;"><div class="kawlog-lineno" style="width: 50px;"></div><div style="width: 150px"></div><div style="width: 100px"><?php esc_attr_e( 'Result', 'kassa-at-for-woocommerce' ); ?>: </div><div style="width: calc(100% - 300px)"><?php echo esc_html( wp_json_encode( (array) $log->data->result ) ); ?></div></div>
						<div class="kawlog-row" style="display: flex; flex-direction: auto;"><div class="kawlog-lineno" style="width: 50px;"></div><div style="width: 150px"></div><div style="width: 100px"><?php esc_attr_e( 'Location', 'kassa-at-for-woocommerce' ); ?>: </div><div style="width: calc(100% - 300px)"><?php echo esc_attr( $log->data->location ); ?></div></div>
					<?php elseif ( 'API-ERROR' === $log->type ) : ?>
						<div class="kawlog-row" style="display: flex; flex-direction: auto;"><div class="kawlog-lineno" style="width: 50px;"></div><div style="width: 150px"></div><div style="width: 100px"><?php esc_attr_e( 'Location', 'kassa-at-for-woocommerce' ); ?>: </div><div style="width: calc(100% - 300px)"><?php echo esc_attr( $log->data->location ); ?></div></div>
					<?php elseif ( 'DATA-UPDATE' === $log->type ) : ?>
						<div class="kawlog-row" style="display: flex; flex-direction: auto;"><div class="kawlog-lineno" style="width: 50px;"></div><div style="width: 150px"></div><div style="width: 100px"><?php esc_attr_e( 'Changes', 'kassa-at-for-woocommerce' ); ?>: </div><div style="width: calc(100% - 300px)"><?php echo esc_attr( $log->data->original ); ?> => <?php echo esc_attr( $log->data->updated ); ?> (<?php echo esc_attr( $log->data->key ); ?>)</div></div>
						<div class="kawlog-row" style="display: flex; flex-direction: auto;"><div class="kawlog-lineno" style="width: 50px;"></div><div style="width: 150px"></div><div style="width: 100px"><?php esc_attr_e( 'Location', 'kassa-at-for-woocommerce' ); ?>: </div><div style="width: calc(100% - 300px)"><?php echo esc_attr( $log->data->location ); ?></div></div>
					<?php elseif ( 'SYSTEM' === $log->type ) : ?>
						<div class="kawlog-row" style="display: flex; flex-direction: auto;"><div class="kawlog-lineno" style="width: 50px;"></div><div style="width: 150px"></div><div style="width: 100px"><?php esc_attr_e( 'Location', 'kassa-at-for-woocommerce' ); ?>: </div><div style="width: calc(100% - 300px)"><?php echo esc_attr( $log->data->location ); ?></div></div>
					<?php endif; ?>
				</div>
			</div>
		<?php } ?>
	</div>

	<h1><?php esc_attr_e( 'Database options', 'kassa-at-for-woocommerce' ); ?></h1>
	<table class="kaw-option-table">
		<thead>
			<tr>
				<td><?php esc_attr_e( 'Creator', 'kassa-at-for-woocommerce' ); ?></td>
				<td><?php esc_attr_e( 'Option-name', 'kassa-at-for-woocommerce' ); ?></td>
				<td><?php esc_attr_e( 'Option-value', 'kassa-at-for-woocommerce' ); ?></td>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><?php esc_attr_e( 'KASSA.AT For WooCommerce', 'kassa-at-for-woocommerce' ); ?></td>
				<td>kaw-subdomain</td>
				<td><?php echo get_option( 'kaw-subdomain' ) ? esc_attr( get_option( 'kaw-subdomain' ) ) : 'not set'; ?></td>
			</tr>
			<tr>
				<td><?php esc_attr_e( 'KASSA.AT For WooCommerce', 'kassa-at-for-woocommerce' ); ?></td>
				<td>kaw-key</td>
				<td><?php echo get_option( 'kaw-key' ) ? esc_attr( get_option( 'kaw-key' ) ) : 'not set'; ?></td>
			</tr>
			<tr>
				<td><?php esc_attr_e( 'KASSA.AT For WooCommerce', 'kassa-at-for-woocommerce' ); ?></td>
				<td>kaw-warehouse</td>
				<td><?php echo get_option( 'kaw-warehouse' ) ? esc_attr( get_option( 'kaw-warehouse' ) ) : 'not set'; ?></td>
			</tr>
			<tr>
				<td><?php esc_attr_e( 'KASSA.AT For WooCommerce', 'kassa-at-for-woocommerce' ); ?></td>
				<td>kaw-message-active</td>
				<td><?php echo get_option( 'kaw-message-active' ) ? esc_attr( get_option( 'kaw-message-active' ) ) : 'not set'; ?></td>
			</tr>
			<tr>
				<td><?php esc_attr_e( 'KASSA.AT For WooCommerce', 'kassa-at-for-woocommerce' ); ?></td>
				<td>kaw-logging</td>
				<td><?php echo get_option( 'kaw-logging' ) ? esc_attr( get_option( 'kaw-logging' ) ) : 'not set'; ?></td>
			</tr>
			<tr>
				<td><?php esc_attr_e( 'KASSA.AT For WooCommerce', 'kassa-at-for-woocommerce' ); ?></td>
				<td>kaw-logging-size</td>
				<td><?php echo get_option( 'kaw-logging-size' ) ? esc_attr( get_option( 'kaw-logging-size' ) ) : 'not set'; ?></td>
			</tr>
			<tr>
				<td><?php esc_attr_e( 'KASSA.AT For WooCommerce', 'kassa-at-for-woocommerce' ); ?></td>
				<td>kaw-synchronize-at-singleproduct</td>
				<td><?php echo get_option( 'kaw-synchronize-at-singleproduct' ) ? esc_attr( get_option( 'kaw-synchronize-at-singleproduct' ) ) : 'not set'; ?></td>
			</tr>
			<tr>
				<td><?php esc_attr_e( 'KASSA.AT For WooCommerce', 'kassa-at-for-woocommerce' ); ?></td>
				<td>kaw-synchronize-at-cart</td>
				<td><?php echo get_option( 'kaw-synchronize-at-cart' ) ? esc_attr( get_option( 'kaw-synchronize-at-cart' ) ) : 'not set'; ?></td>
			</tr>
			<tr>
				<td><?php esc_attr_e( 'KASSA.AT For WooCommerce', 'kassa-at-for-woocommerce' ); ?></td>
				<td>kaw-synchronize-on-order</td>
				<td><?php echo get_option( 'kaw-synchronize-on-order' ) ? esc_attr( get_option( 'kaw-synchronize-on-order' ) ) : 'not set'; ?></td>
			</tr>
			<tr>
				<td>WooCommerce</td>
				<td>woocommerce_manage_stock</td>
				<td><?php echo get_option( 'woocommerce_manage_stock' ) ? esc_attr( get_option( 'woocommerce_manage_stock' ) ) : 'not set'; ?></td>
			</tr>
		</tbody>
	</table>
<?php } ?>
