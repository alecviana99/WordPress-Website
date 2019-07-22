<?php

/**
 * Submenu page
 */
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class DGWT_WCAS_Admin_Menu {

	public function __construct() {

		add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );
	}

	/**
	 * Add meun items
	 */
	public function add_menu() {

		add_menu_page( __( 'Ajax Search for WooCommerce', 'ajax-search-for-woocommerce' ), __( 'Woo Ajax Search', 'ajax-search-for-woocommerce' ), 'manage_options', 'dgwt_wcas_settings', array( $this, 'settings_page' ), DGWT_WCAS_URL . '/assets/img/admin-icon.svg', 56);
	}

	/**
	 * Settings page
	 */
	public function settings_page() {
		DGWT_WCAS_Settings::output();
	}

}

$admin_menu = new DGWT_WCAS_Admin_Menu();

