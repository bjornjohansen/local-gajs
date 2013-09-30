<?php
/*
Plugin Name: Local GAjs
Plugin URI: http://wordpress.org/extend/plugins/local-gajs/
Description: Host the ga.js locally for improved load speed. Integrates with Analytics for WordPress by Joost de Valk
Version: 0.0.1
Author: Bjørn Johansen
Author URI: https://bjornjohansen.no
License: GPL2

    Copyright 2013 Bjørn Johansen  (email : post@bjornjohansen.no)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/

new BJ_Local_GAjs;

class BJ_Local_GAjs {

	const GA_URL_ORIGIN = 'http://www.google-analytics.com/ga.js';
	const GA_URL_ORIGIN_SSL = 'https://ssl.google-analytics.com/ga.js';

	public function __construct() {

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
		register_uninstall_hook( __FILE__, array( __CLASS__, 'uninstall' ) );

		add_action( 'cronevent_check_new_gajs', array( $this, 'check_new_gajs' ) );
	}

	public function get_gajs_url() {
		$wp_upload_dir = wp_upload_dir();

		$gajs_file = get_option( 'local-gajs' );

		if ( ! $gajs_file ) {
			if ( is_ssl() ) {
				$gajs_url = self::GA_URL_ORIGIN_SSL;
			} else {
				$gajs_url = self::GA_URL_ORIGIN;
			}
		} else {
			// If you'd worked on *nix ('/'), win ('\') and mac os 9 (':'), you'd understand
			if ( '/' != DIRECTORY_SEPARATOR ) {
				$gajs_file = str_replace( DIRECTORY_SEPARATOR, '/', $gajs_file );
			}
			$gajs_url = $wp_upload_dir['baseurl'] . '/' . $gajs_file;
		}

		return $gajs_url;

	}

	public function get_gajs_path() {

		$gajs_path = false;

		$wp_upload_dir = wp_upload_dir();
		$gajs_file = get_option( 'local-gajs' );

		if ( $gajs_file ) {
			$gajs_path = $wp_upload_dir['basedir'] . '/' . $gajs_file;
		}

		return $gajs_path;

	}

	public function check_new_gajs() {
		$wp_upload_dir = wp_upload_dir();

		$gajs_file = get_option( 'local-gajs' );
		
		$mtime = 0;

		if ( $gajs_file ) {
			$file_path = $wp_upload_dir['basedir'] . DIRECTORY_SEPARATOR . $gajs_file;
			$mtime = filemtime( $file_path );
		}

		$ga_str = $this->_fetch_new_ga( $mtime );
		if ( strlen( $ga_str ) ) {

			if ( $this->_save_new_ga( $ga_str ) ) {

				// Delete old file and make symlink to new one instead
				if ( $file_path && ( $file_path_new = $this->get_gajs_path() ) ) {
					unlink( $file_path );
					symlink( $file_path_new, $file_path );
				}

				$this->_update_yoast_google_analytics();

			}

		}

	}

	protected function _fetch_new_ga( $mtime = 0 ) {

		$args = array(
			'timeout'     => 5,
			'redirection' => 5,
			'httpversion' => '1.1',
			'user-agent'  => 'WordPress/local-gajs; ' . get_bloginfo( 'url' ),
			'blocking'    => true,
			'headers'     => array(
				'host'              => parse_url( self::GA_URL_ORIGIN, PHP_URL_HOST ),
				'If-Modified-Since' => gmdate( 'D, d M Y H:i:s T', $mtime )
			),
			'cookies'     => array(),
			'body'        => null,
			'compress'    => false,
			'decompress'  => true,
			'sslverify'   => true,
			'stream'      => false,
			'filename'    => null
		);

		$response = wp_remote_get( self::GA_URL_ORIGIN, $args );

		if ( is_a( $response, 'WP_Error' ) ) {
			return false;
		}

		if ( '200' != $response['response']['code'] ) {
			return false; // Not necessarily an error. E.g. 304 Not modified is a very welcome response code
		}

		$ga_str = wp_remote_retrieve_body( $response );
		return $ga_str;

	}

	protected function _save_new_ga( & $content ) {

		$wp_upload_dir = wp_upload_dir();
		$ts = time();
		$gajs_file = implode( DIRECTORY_SEPARATOR, array( 'local-gajs', date( 'Y-m', $ts ), date( 'dHi', $ts ) . '-ga.js' ) );
		$path = $wp_upload_dir['basedir'] . DIRECTORY_SEPARATOR . $gajs_file;

		if ( ! is_dir( dirname( $path ) ) ) {
			mkdir( dirname( $path ), 0755, true );
		}

		if ( file_put_contents( $path, $content ) ) {
			update_option( 'local-gajs', $gajs_file );
			$return = true;
		} else {
			$return = false;
		}

		return $return;

	}

	protected function _update_yoast_google_analytics() {

		$Yoast_Google_Analytics = get_option( 'Yoast_Google_Analytics' );

		if ( is_array( $Yoast_Google_Analytics ) ) {
			$Yoast_Google_Analytics['gajslocalhosting'] = true;
			$Yoast_Google_Analytics['gajsurl'] = $this->get_gajs_url();

			update_option( 'Yoast_Google_Analytics', $Yoast_Google_Analytics );
		}

	}

	public static function activate() {
		wp_schedule_event( time(), 'twicedaily', 'cronevent_check_new_gajs' );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( 'cronevent_check_new_gajs' );

		$Yoast_Google_Analytics = get_option( 'Yoast_Google_Analytics' );
		if ( is_array( $Yoast_Google_Analytics ) ) {
			$Yoast_Google_Analytics['gajslocalhosting'] = false;
			$Yoast_Google_Analytics['gajsurl'] = "";

			update_option( 'Yoast_Google_Analytics', $Yoast_Google_Analytics );
		}
	}

	public static function uninstall() {
		$wp_upload_dir = wp_upload_dir();
		self::_delTree( $wp_upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'local-gajs' );
		delete_option( 'local-gajs' );
	}

	protected static function _delTree( $dir ) {
		$dir = str_replace( "\x00", '', (string) $dir ); // null byte protection

		$files = array_diff( scandir( $dir ), array( '.', '..' ) ); 
		foreach ( $files as $file ) {
			$path = $dir . DIRECTORY_SEPARATOR . $file;
			if ( is_dir( $path ) ) {
				self::_delTree( $path );
			} else {
				unlink( $path ); 
			}
		} 
		return rmdir( $dir ); 
	}

}

