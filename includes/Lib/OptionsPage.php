<?php

namespace ClinicSoftware\Lib;

use ClinicSoftware\Page\PageLoader;

class OptionsPage {

	public $pageTitle = '';
	public $pageSlug  = '';

	public function __construct( $pageTitle, $pageSlug ) {

		$this->pageTitle = $pageTitle;
		$this->pageSlug  = $pageSlug;
	}

	public function registerSettings() {

		add_options_page( $this->pageTitle, $this->pageTitle, 'manage_options', $this->pageSlug, array( $this, 'renderSettingsPage' ) );

		$section_slug       = 'test_section_1';
		$field_group_slug   = $this->pageSlug;
		$field_group_slug_2 = $this->pageSlug . '2';

		register_setting( $field_group_slug, $field_group_slug, array( $this, 'validate_function' ) );

		add_settings_section( $section_slug, 'API Settings', array( $this, 'dbi_plugin_section_text' ), $field_group_slug_2 );

		add_settings_field( $this->pageSlug . '_api_key', 'API Key', array( $this, 'dbi_plugin_setting_api_key' ), $field_group_slug_2, $section_slug );
		add_settings_field( $this->pageSlug . '_api_client_secret', 'API Client Secret', array( $this, 'dbi_plugin_setting_api_client_secret' ), $field_group_slug_2, $section_slug );
		add_settings_field( $this->pageSlug . '_api_client_salt', 'API Client Salt', array( $this, 'dbi_plugin_setting_api_client_salt' ), $field_group_slug_2, $section_slug );
		add_settings_field( $this->pageSlug . '_api_client_url', 'API Client Url', array( $this, 'dbi_plugin_setting_api_client_url' ), $field_group_slug_2, $section_slug );
		add_settings_field( $this->pageSlug . '_api_client_business_alias', 'API Client Business Alias', array( $this, 'dbi_plugin_setting_api_business_alias' ), $field_group_slug_2, $section_slug );
		add_settings_field( $this->pageSlug . '_sync_contacts', 'Contact Sync', array( $this, 'dbi_plugin_setting_sync_contacts' ), $field_group_slug_2, $section_slug );
		add_settings_field( $this->pageSlug . '_sync_orders', 'Order Sync', array( $this, 'dbi_plugin_setting_sync_orders' ), $field_group_slug_2, $section_slug );
		add_settings_field( $this->pageSlug . '_sync_products', 'Product Sync', array( $this, 'dbi_plugin_setting_sync_products' ), $field_group_slug_2, $section_slug );
	}

	/*
	public function validate_function( $input ) {
		// $newinput['api_key'] = trim( $input['api_key'] );
		// if ( ! preg_match( '/^[a-z0-9]{32}$/i', $newinput['api_key'] ) ) {
		//     $newinput['api_key'] = '';
		// }

		return $input;
	} */

	public function dbi_plugin_setting_api_key() {
		$options = get_option( $this->pageSlug );

		echo "<input id='" . esc_attr( $this->pageSlug . '_api_key' ) . "' name=\"" . esc_attr( $this->pageSlug . '[api_key]' ) . "\" type='text' value='" . esc_attr( $options['api_key'] ?? '' ) . "' />";
	}

	public function dbi_plugin_setting_api_client_secret() {
		$options = get_option( $this->pageSlug );

		echo "<input id='", esc_attr( $this->pageSlug . '_api_client_secret' ), "' name=\"" . esc_attr( $this->pageSlug . '[api_client_secret]' ) . "\" type='text' value='" . esc_attr( $options['api_client_secret'] ?? '' ) . "' />";
	}

	public function dbi_plugin_setting_api_client_salt() {
		$options = get_option( $this->pageSlug );

		echo "<input id='", esc_attr( $this->pageSlug . '_api_client_salt' ), "' name=\"" . esc_attr( $this->pageSlug . '[api_client_salt]' ) . "\" type='text' value='" . esc_attr( $options['api_client_salt'] ?? '' ) . "' />";
	}

	public function dbi_plugin_setting_api_client_url() {
		$options = get_option( $this->pageSlug );

		echo "<input id='" . esc_attr( $this->pageSlug . '_api_client_url' ) . "' name=\"" . esc_attr( $this->pageSlug . '[api_client_url]' ) . "\" type='text' pattern=\"^server\d+\.clinicsoftware\.com$\" title=\"Your URL should match 'server[Digit].clinicsoftware.com'. For example, server1.clinicsoftware.com\" value='" . esc_attr( $options['api_client_url'] ?? '' ) . "' />";
	}


	public function dbi_plugin_setting_api_business_alias() {
		$options = get_option( $this->pageSlug );

		echo "<input id='" . esc_attr( $this->pageSlug . '_api_business_alias' ) . "' name=\"" . esc_attr( $this->pageSlug . '[api_business_alias]' ) . "\" type='text' value='" . esc_attr( $options['api_business_alias'] ?? '' ) . "' />";
	}

	public function dbi_plugin_setting_sync_contacts() {
		$options = get_option( $this->pageSlug );

		$isChecked = '';
		if ( ! empty( $options['sync_contacts'] ) ) {
			$isChecked = 'checked';
		}

		echo '
            <input 
                type="checkbox" 
                id="' . esc_attr( $this->pageSlug . '_sync_contacts' ) . '" 
                name="' . esc_attr( $this->pageSlug . '[sync_contacts]' ) . '" ' .
				esc_attr( $isChecked ) . '
                value="check"
            />
        ';
	}

	public function dbi_plugin_setting_sync_orders() {
		$options = get_option( $this->pageSlug );

		$isChecked = '';
		if ( ! empty( $options['sync_orders'] ) ) {
			$isChecked = 'checked';
		}

		echo '
            <input 
                type="checkbox" 
                id="' . esc_attr( $this->pageSlug . '_sync_orders' ) . '" 
                name="' . esc_attr( $this->pageSlug . '[sync_orders]' ) . '" ' .
				esc_attr( $isChecked ) . '
                value="check"
            />
        ';
	}

	public function dbi_plugin_setting_sync_products() {
		$options = get_option( $this->pageSlug );

		$isChecked = '';
		if ( ! empty( $options['sync_contacts'] ) ) {
			$isChecked = 'checked';
		}

		echo '
            <input 
                type="checkbox" 
                id="' . esc_attr( $this->pageSlug . '_sync_products' ) . '" 
                name="' . esc_attr( $this->pageSlug . '[sync_products]' ) . '" ' .
				esc_attr( $isChecked ) . '
                value="check"
            />
        ';
	}

	public function dbi_plugin_section_text() {
		echo 'EXAMPLE';
	}


	/**
	 * Register and build the different settings options
	 *
	 * @return void
	 */
	public function registerPage() {
		$this->registerSettings();
	}

	public function renderSettingsPage() {

		PageLoader::loadPage(
			'settingsPage.php',
			array(
				'pageTitle' => $this->pageTitle,
				'pageSlug'  => $this->pageSlug,
			)
		);
	}
}
