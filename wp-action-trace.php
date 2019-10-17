<?php
/*
Plugin Name: Wp-action-trace
Version: 1.0
Description: A simple plugin to show you exactly what actions are being called when you run WordPress.
Author: Cal Evans
Author URI: http://blog.calevans.com
Plugin URI: https://www.getpantheon.com/blog/tracing-wordpress-actions
*/

function calevans_action_trace() {
	/*
	 * Even though this plugin should never EVER be used in production, this is 
	 * a safety net. You have to actually set the showDebugTrace=1 flag in the query 
	 * string for it to operate. If you don't it will still slow down your 
	 * site, but it won't do anything.
	 */
	if ( ! isset( $_GET['showDebugTrace'] ) || true !== (bool) $_GET['showDebugTrace'] ) {
		return;
	}

	/*
	 * There are 3 other flags you can set to control what is output and how.
	 */
	$show_args   = ( isset( $_GET['showDebugArgs'] ) ? (bool) $_GET['showDebugArgs'] : false );
	$show_time   = ( isset( $_GET['showDebugTime'] ) ? (bool) $_GET['showDebugTime'] : false );
	$log_to_file = ( isset( $_GET['logToFile'] ) ? (bool) $_GET['logToFile'] : false );

	/*
	 * This is the main array we are using to hold the list of actions
	 */
	static $actions = [];

	/*
	 * Some actions are not going to be of interest to you. Add them into this 
	 * array to exclude them. Remove the two default if you want to see them.
	 */
	$excludeActions = ['gettext', 'gettext_with_context', 'set_url_scheme','sanitize_key','pre_option_siteurl','option_siteurl','clean_url','attribute_escape','alloptions','admin_url','wp_parse_str'];
	$thisAction     = current_filter();
	$thisArguments  = func_get_args();

	if ( !in_array( $thisAction, $excludeActions ) ) {
		$actions[] = ['action'    => $thisAction,
					  'time'      => microtime( true ),
					  'arguments' => print_r( $thisArguments, true )];
	}

	/*
	 * Shutdown is the last action, process the list.
	 */ 
	if ( 'shutdown' === $thisAction ) {
		calevans_format_debug_output( $actions, $show_args, $show_time, $log_to_file );
	}

	return;
}


function calevans_format_debug_output( $actions = [], $show_args = false, $show_time = false, $log_to_file ) {
	/*
	 * Let's do a little formatting here.
	 * The class "debug" is so you can control the look and feel
	 */
	$output = '';

	foreach ( $actions as $thisAction ) {
		$output  .= "Action Name: ";

		/*
		 * if you want the timings, let's make sure everything is padded out properly.
		 */
		if ( $show_time ) {
			$timeParts = explode('.', $thisAction['time'] );
			$output  .= '(' . $timeParts[0] . '.' .  str_pad( $timeParts[1], 4, '0' ) . ') ';
		}


		$output  .= $thisAction['action'] . PHP_EOL;

		/*
		 * If you've requested the arguments, let's display them.
		 */
		if ( $show_args && count( $thisAction['arguments'] ) > 0 ) {
			$output  .= "Args:" . PHP_EOL . print_r( $thisAction['arguments'], true );
			$output  .= PHP_EOL;
		}
	}

	if ( $log_to_file ) {
		global $post;
		$upload = wp_upload_dir();
		$upload_dir = $upload['basedir'];
		$upload_dir = $upload_dir . '/wp-action-trace';
		if ( ! is_dir( $upload_dir ) ) {
			mkdir( $upload_dir, 0700 );
		}

		$filename = '';
		if ( ! empty( $post->post_name) ) {
			$filename .= $post->post_name . '_';
		}
		$filename .= date( 'Y-m-d-h-i-s' ) . '.log';
		$filepath = trailingslashit( $upload_dir ) . $filename;

		if ( !$fp = @fopen( $filepath, "wb" ) ) {
			error_log( "[WP-action-trace] Cannot open file ($filepath)" );
			exit;
		}
		if ( false === fwrite( $fp, $output ) ) {
			error_log( "[WP-action-trace] Cannot write to file ($filepath)" );
			exit;
		}
		if ( ! fclose( $fp ) ) {
			error_log( "[WP-action-trace] Cannot close file ($filepath)" );
			exit;
		}
	} else {
		echo '<pre class="debug">' . $output . '</pre>';
	}
	
	return;
}


/*
 * Hook it into WordPress.
 * all = add this to every action. 
 * calevans_action_trace = the name of the function above to call
 * 99999 = the priority. This is the lowest priority action in the list.
 * 99 = the number of parameters that this method can accept. Honestly, if you have a action approaching this many parameter, you really are doing sometheing wrong. 
 * 
 */
if (
	isset( $_GET['showDebugTrace'] ) &&
	true === (bool) $_GET['showDebugTrace']
) {
	add_action( 'all', 'calevans_action_trace', 99999, 99 );
}
