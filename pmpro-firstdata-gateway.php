<?php
/**
 * Plugin Name: First Data for Paid Memberships Pro
 * Plugin URI: http://www.authnetsource.com/pmpro?payeezy=hide&pid=df63ff78f8d6a3e8
 * Description: Adds First Data as a payment option to Paid Memberships Pro.
 * Version: 1.0.1
 * Author: Cardpay Solutions, Inc.
 * Author URI: http://www.authnetsource.com/
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: pmpro-firstdata
 * Domain Path: /languages
 *
 * Copyright 2016 Cardpay Solutions, Inc.  (email : sales@cardpaysolutions.com)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 * @author Cardpay Solutions, Inc.
 * @package First Data for Paid Memberships Pro
 * @since 1.0.0
 */

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Main class to set up the First Data gateway
 */
class PMPro_firstdata {

	/**
	 * Constructor
	 */
	public function __construct() {
		define( 'PMPRO_FIRSTDATAGATEWAY_DIR', dirname( __FILE__ ) );
		define( 'PMPRO_FIRSTDATAGATEWAY_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );

		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ), 0 );
	}

	/**
	 * Init function
	 */
	public function init() {
		if ( ! class_exists( 'PMProGateway' ) ) {
			return;
		}
		//load payment gateway class
		require_once( PMPRO_FIRSTDATAGATEWAY_DIR . '/classes/class.pmprogateway_firstdata.php' );
		require_once( PMPRO_FIRSTDATAGATEWAY_DIR . '/classes/class.pmprogateway_firstdata_api.php' );
	}

	/**
	 * Add relevant links to plugins page
	 * @param  array $links
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$plugin_links = array(
				'<a href="' . admin_url( 'admin.php?page=pmpro-paymentsettings' ) . '">' . __( 'Settings', 'pmpro-firstdata' ) . '</a>',
		);
		return array_merge( $plugin_links, $links );
	}
}
new PMPro_firstdata();
