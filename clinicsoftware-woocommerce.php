<?php

/**
 * Plugin Name: Clinic Software CRM Online Shop
 * Plugin URI: http://clinicsoftware.com/
 * Description: Connect your WooCommerce data to your ClinicSoftware Instance.
 * Version: 1.0.0
 * Author: Clinic Software
 * Author URI: https://profiles.wordpress.org/clinicsoftware/
 * Developer: Bianca & Daniel Nwaeke @ClinicSoftware
 * Developer URI: http://clinicsoftware.com/
 * Text Domain: my-extension
 * Domain Path: /languages
 *
 * Woo: 12345:342928dfsfhsf8429842374wdf4234sfd
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gl-3.0.html
 */

namespace ClinicSoftware;

require __DIR__ . '/vendor/autoload.php';

define( 'PLUGIN_NAME', 'ClinicSoftware' );
define( 'PLUGIN_SLUG', 'clinic-software-crm-online-shop' );
define( 'PLUGIN_PATH', WP_PLUGIN_DIR . '/' . PLUGIN_SLUG . '/' );


if ( ! defined( 'ABSPATH' ) ) {
	return;
}


global $jal_db_version;
$jal_db_version = '1.0';
global $bool;
$bool = true;

if ( session_status() === PHP_SESSION_NONE ) {
	session_start();
}
// session_start();

/**
 * Undocumented function
 * uncomment and register this callback in register_uninstall_hook if you will remove the uninstall.php file
 * @return void
 */

/* function clinicsoftware_uninstall() {
	ClinicSoftware::instance()->my_uninstall();
} */

function cswoo_init() {
	$cs = ClinicSoftware::instance();
	$cs->InitializePlugin();

	register_activation_hook( __FILE__, array( ClinicSoftware::instance(), 'my_activation' ) );

	register_deactivation_hook( __FILE__, array( ClinicSoftware::instance(), 'my_deactivation' ) );

	// register_uninstall_hook( __FILE__, 'clinicsoftware_uninstall' );
}

cswoo_init();
