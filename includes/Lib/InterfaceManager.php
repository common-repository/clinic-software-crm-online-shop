<?php

namespace ClinicSoftware\Lib;

use ClinicSoftware\Page\PageLoader;
use ClinicSoftware\Lib\ActionManager;

class InterfaceManager {

	public function initializeUI() {
		add_action( 'admin_menu', array( $this, 'loadInterface' ), 9 );
	}

	public function loadInterface() {
		// add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position );
		add_menu_page( PLUGIN_SLUG, PLUGIN_NAME, 'manage_options', PLUGIN_SLUG, array( $this, 'displayPluginAdminDashboard' ), plugin_dir_url( __FILE__ ) . 'images/logo-60.png', 26 );

		$this->initializeSettingsPage();
	}

	public function initializeSettingsPage() {
		$myPage = new OptionsPage( 'ClinicSoftware', 'main-settings' );
		$myPage->registerPage();
	}

	public function displayPluginAdminDashboard() {
		$actionManager = ActionManager::instance();

		if ( ! $actionManager->checkForWooCommerce( false ) ) {
			return PageLoader::loadPage( 'error-messages\woo-commerce-not-found-notice.php', $this->errorData()['woo_commerce_not_found'] );
		}
		return PageLoader::loadPage( 'dashboard.php' );
	}

	public function errorData() {
		return array(
			'woo_commerce_not_found' => array(
				'warningMessage' => 'WooCommerce is not active. Please activate WooCommerce to use this plugin. ',
				'link'           => 'https://wordpress.org/plugins/woocommerce',
				'linkText'       => 'WooCommerce plugin',
			),
		);
	}
}
