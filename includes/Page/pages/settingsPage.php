<div class="wrap">
	<div id="icon-themes" class="icon32"></div>
	<h2><?php echo esc_html_e( ucfirst( $view_data['pageTitle'] ) ); ?></h2>

	<form action="options.php" method="post">
		<?php
			settings_fields( $view_data['pageSlug'] );
		do_settings_sections( $view_data['pageSlug'] . '2' );
		?>

		<input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Save' ); ?>" />
	</form>

</div>
