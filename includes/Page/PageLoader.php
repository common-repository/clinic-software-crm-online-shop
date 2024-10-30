<?php

namespace ClinicSoftware\Page;

use Exception;

class PageLoader {
	public static function loadPage( $pagePath, $view_data = array() ) {

		if ( empty( $pagePath ) ) {
			throw new Exception( 'Invalid page Page' );
		}

		$filePath = PLUGIN_PATH . 'includes/Page/pages/' . $pagePath;

		// Find the page
		if ( ! file_exists( $filePath ) ) {
			throw new Exception( esc_html( "Could not find file $filePath" ) );
		}

		try {
			// Load and return the view!
			if ( file_exists( $filePath ) ) {
				switch ( $pagePath ) {
					case 'error-messages\woo-commerce-not-found-notice.php':
						include esc_html( PLUGIN_PATH . 'includes/Page/pages/error-messages\woo-commerce-not-found-notice.php' );
						break;
					case 'dashboard.php':
						include esc_html( PLUGIN_PATH . 'includes/Page/pages/dashboard.php' );
						break;
					case 'settingsPage.php':
						include esc_html( PLUGIN_PATH . 'includes/Page/pages/settingsPage.php' );
						break;

					default:
						// code...
						break;
				}
				// include esc_html( PLUGIN_PATH . 'includes/Page/pages/' . $pagePath );
			} else {
				// Handle the case where the file doesn't exist!
				throw new Exception( esc_html( "File not found: $filePath" ) );
			}
		} catch ( Exception $e ) {
			echo esc_html( 'Error: ' . $e->getMessage() );
		}
	}
}
