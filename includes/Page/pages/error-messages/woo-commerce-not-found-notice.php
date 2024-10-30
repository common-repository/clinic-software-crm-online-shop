<?php

	wp_enqueue_style( 'cswoo_bootstrap_css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.2.0-beta1/dist/css/bootstrap-grid.min.css', array(), '1.0.0', false );
	wp_enqueue_style( 'cswoo_main_css', plugins_url( '../css/cs_woo_main.css', __FILE__ ), array(), '1.0.0', false );
	$settings = get_option( 'main-settings' );

?>
<div class="wrap" style="font-size: 1rem;">
	<div class="cscontainer" style="margin: 0 auto;">
		<br>
		<h1 style="text-align: start; width: 100%; color: #777; font-size: 2rem; margin-left: -1.5rem;" class="p-0">
			<!-- ClinicSoftware Dashboard -->
			<img src="https://clinicsoftware.com/static/assets/img/tim-logo.png" alt="" width="256">
		</h1>
		<br>

		<div class="col-12 cs-box">
			<h4 style="color: var(--c-hl); margin: 0; padding: 0; font-size: 1.5rem;">
				<?php
				if ( isset( $settings['api_business_alias'] ) ) {
					echo 'CRM Integration for ClinicSoftware.com ' . esc_html( $settings['api_business_alias'] ?? '' );

				} else {
					echo 'CRM Integration for ClinicSoftware.com ';

				}
				?>
			</h4>
		</div>
		<div class="col-12 cs-box">
			An Error occurred whilst initializing the plugin. Could not find WooCommerce. Please install this plugin
			<p>
				<a href="<?php echo esc_url( $view_data['link'] ?? '#' ); ?>" target=\"_blank\" rel="noopener noreferrer"><?php echo esc_html( $view_data['linkText'] ?? 'Error' ); ?></a>
			</p>
		</div>
		<div class="notice notice-warning is-dismissible">
			<p><?php esc_html_e( 'Warning: ' . $view_data['warningMessage'] ?? '', 'textdomain' ); ?>
			</p>
		</div>
	</div>
</div>
