<?php

namespace ClinicSoftware\Lib;

use ClinicSoftware\Api\ClinicSoftwareApi;

use ClinicSoftware\Lib\Entities\Classes\Contacts;
use ClinicSoftware\Lib\Entities\Classes\Coupons;
use ClinicSoftware\Lib\Entities\Classes\Orders;
use ClinicSoftware\Lib\Entities\Classes\Products;
use Exception;
use WP_Error;

if ( session_status() === PHP_SESSION_NONE ) {
	session_start();
}
class ActionManager {

	private $domain   = PLUGIN_SLUG; // clinicsoftware-woocommerce
	private $postData = array();
	private $options  = array();
	public $plugin;


	/**
	 * The single instance of the class.
	 */
	protected static $_instance = null;

	/**
	 * Constructor.
	 */
	protected function __construct() {
		$this->options = get_option( 'main-settings' );
	}

	/**
	 * Main Extension Instance.
	 * Ensures only one instance of the extension is loaded or can be loaded.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {
		// Override this PHP function to prevent unwanted copies of your instance.
		// Implement your own error or use `wc_doing_it_wrong()`
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	public function __wakeup() {
		// Override this PHP function to prevent unwanted copies of your instance.
		// Implement your own error or use `wc_doing_it_wrong()`
	}

	public function getOptions() {
		return $this->options;
	}

	public function getClinicSoftwareApi() {
		$options = $this->options;

		if ( ! ( $options['api_key'] ?? true )
			|| ! ( $options['api_client_secret'] ?? true )
			|| ! ( $options['api_business_alias'] ?? true )
			|| ! ( $options['api_client_url'] ?? true )
		) {
			error_log( "Couldn't find complete Client Software Api Details" );

			throw new Exception( "Couldn't find complete Client Software Api Details" );
		}

		return new ClinicSoftwareApi(
			$this->options['api_key'],
			$this->options['api_client_secret'],
			$this->options['api_business_alias'],
			$this->options['api_client_url']
		);
	}


	public function checkForWooCommerce( $echoNotice = true ) {
		$wc_active = false;
		if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
			$wc_active = true;
		} else {
			// WooCommerce is not active
			if ( $echoNotice ) {
				add_action( 'admin_notices', array( $this, 'cs_woocommerce_warning_notice' ) );
			}
		}
		return $wc_active;
	}

	public function cs_woocommerce_warning_notice() {
		?>
		<div class="notice notice-warning is-dismissible">
			<p><?php esc_html_e( 'Warning: WooCommerce is not active. Please activate WooCommerce to use this feature.', 'textdomain' ); ?>
			</p>
		</div>
		<?php
	}
	public function handleAction() {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['_wpnonce'] ), 'cswoo_nonce' ) ) {
			error_log( 'No nonce set' );
			return;
		} else {
			error_log( 'Nonce: set' );
		}
		$this->postData = $_POST;

		if ( empty( $this->postData['cs_action'] ) ) {
			return;
		}

		if ( ! $this->checkForWooCommerce() ) {
			return;
		}

		if ( ! empty( $this->postData ) ) {

			switch ( $this->postData['cs_action'] ) {
				case 'download_contacts':
					Contacts::initImports();
					break;
				case 'upload_contacts':
					Contacts::initExports();
					break;
				case 'download_products':
					Products::initImports();

					break;
				case 'upload_products':
					Products::initExports();
					break;
				case 'download_orders':
					Orders::initImports();
					break;
				case 'upload_orders':
					Orders::initExports();
					break;
				case 'download_coupons':
					Coupons::initImports();
					break;
				case 'upload_coupons':
					Coupons::initExports();
					break;
				case 'disconnected':
					$this->disconnected();
					break;
				default:
					break;
			}
		}
	}


	public function disconnected() {
		delete_option( 'main-settings' );
	}

	public function run() {
		$_SESSION['offset'] = 0;
		$_SESSION['bool']   = 1;
	}

	public function my_update_notice_contacts() {
		?>
<div class="notice updated">
	<p><?php esc_html_e( 'Succesfully Imported the Costumers !', 'clinicsoftware-woocommerce' ); ?></p>
</div>
		<?php
	}
	public function my_update_notice_export_contacts() {
		?>
<div class="notice updated">
	<p><?php esc_html_e( 'Succesfully Exported the Costumers !', 'clinicsoftware-woocommerce' ); ?></p>
</div>
		<?php
	}
	public function my_update_notice_products() {
		?>
<div class="notice updated">
	<p><?php esc_html_e( 'Succesfully Imported the Products !', 'clinicsoftware-woocommerce' ); ?></p>
</div>
		<?php
	}
	public function my_update_notice_export_products() {
		?>
<div class="notice updated">
	<p><?php esc_html_e( 'Succesfully Exported the Products !', 'clinicsoftware-woocommerce' ); ?></p>
</div>
		<?php
	}
	public function my_update_notice_orders() {
		?>
<div class="notice updated">
	<p><?php esc_html_e( 'Succesfully Imported the Orders !', 'clinicsoftware-woocommerce' ); ?></p>
</div>
		<?php
	}
	public function my_update_notice_export_orders() {
		?>
<div class="notice updated">
	<p><?php esc_html_e( 'Succesfully Exported the Orders !', 'clinicsoftware-woocommerce' ); ?></p>
</div>
		<?php
	}
	public function my_update_notice_coupons() {
		?>
<div class="notice updated">
	<p><?php esc_html_e( 'Succesfully Imported the Coupons !', 'clinicsoftware-woocommerce' ); ?></p>
</div>
		<?php
	}
	public function my_update_notice_export_coupons() {
		?>
<div class="notice updated">
	<p><?php esc_html_e( 'Succesfully Exported the Coupons !', 'clinicsoftware-woocommerce' ); ?></p>
</div>
		<?php
	}

	public function catchData() {
		add_action( 'admin_init', array( $this, 'handleAction' ) );
	}

	/**
	 * Remove before live
	 */

	public function jal_install_data( $data, $status, $name ) {
		global $wpdb;
		$wp_status      = $status;
		$wp_name        = $name;
		$wp_description = $data;
		$user           = wp_get_current_user();
		$array          = json_decode( json_encode( $user ), true );

		$table_name = $wpdb->prefix . 'status';

		$wpdb->insert(
			$table_name,
			array(
				'time'        => current_time( 'mysql' ),
				'name'        => $wp_name,
				'description' => $wp_description,
				'user'        => isset( $array['data']['user_login'] ) ? $array['data']['user_login'] : 'auto-schedule',
				'status'      => $wp_status,
			)
		);
	}
	public function debug( $data, $status, $wp_name ) {
		register_activation_hook( __FILE__, $this->jal_install_data( $data, $status, $wp_name ) );
	}
}
