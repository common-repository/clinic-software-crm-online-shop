<?php
wp_enqueue_style( 'cswoo_bootstrap_css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.2.0-beta1/dist/css/bootstrap-grid.min.css', array(), '1.0.0', false );
wp_enqueue_style( 'cswoo_main_css', plugins_url( '/css/cs_woo_main.css', __FILE__ ), array(), '1.0.0', false );
$settings = get_option( 'main-settings' );
$nonce    = wp_create_nonce( 'cswoo_nonce' );
function verifyNonceIsset() {
	return isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( $_POST['_wpnonce'] ), 'cswoo_nonce' );
}
if ( ! empty( $_POST ) && ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['_wpnonce'] ), 'cswoo_nonce' ) ) ) {
	error_log( 'No nonce set' );
	return;
} else {
	error_log( 'Nonce: set' );
}
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

		<div class="col-12 cs-box cs-nav">
			<a class="cs-nav-item tablinks active" href="#" onclick="openMeniu(event, 'Dashboard')">Dashboard</a>
			<a class="cs-nav-item tablinks" href="#" onclick="openMeniu(event, 'Feeds')">Feeds</a>
			<a class="cs-nav-item tablinks" href="#" onclick="openMeniu(event, 'Logs')">Logs</a>
			<a class="cs-nav-item tablinks" href="#" onclick="openMeniu(event, 'Settings')">Settings</a>
		</div>
		<div class="col-12 cs-box">
			<div class=" tabcontentt dashboard active" id="Dashboard">
				<!-- <div id="Dashboard" class="col-12 tabcontentt active"> -->
				<div class="col-12_ cs-box cs-nav" style="width:inherit">
					<div style="display: flex; flex-flow: row;">

						<?php

						use ClinicSoftware\Api\ClinicSoftwareApi;
						use ClinicSoftware\Lib\Entities\Classes\Contacts;
						use ClinicSoftware\Lib\Entities\Classes\Orders;
						use ClinicSoftware\Lib\Entities\Classes\Coupons;
						use ClinicSoftware\Lib\Entities\Classes\Products;
						use ClinicSoftware\Page\PageLoader;

						$settingsObj     = (object) $settings;
						$settingsAreOkay = isset( $settingsObj->api_key ) &&
							isset( $settingsObj->api_client_secret ) &&
							isset( $settingsObj->api_business_alias ) &&
							isset( $settingsObj->api_client_url );
						/* echo $settingsAreOkay ? 'okay' : 'not okay'; */
						if ( ! empty( $settings ) ) {
							$ClinicSoftwareApi = new ClinicSoftwareApi( $settings['api_key'], $settings['api_client_secret'], $settings['api_business_alias'], $settings['api_client_url'] );
							$lastDate          = '';
							$limit             = 1000;
							$offset            = 0;

							$connection_status = $ClinicSoftwareApi->checkStatus(
								$lastDate,
								$limit,
								$offset
							);
						}

						$canSyncOrders = false;

						$all_users     = get_users();
						$regular_users = array_filter(
							$all_users,
							function ( $user ) {

								return empty( $user->user_url );
							}
						);

						$regular_users_count = count( $regular_users );

						$products       = get_posts(
							array(
								'posts_per_page' => -1,
								'orderby'        => 'name',
								'order'          => 'asc',
								'post_type'      => 'product',
							)
						);
						$products_count = count( $products );

						$canSyncOrders = ( $regular_users_count > 0 ) && ( $products_count > 0 );

						$contactsClass = new Contacts();
						$productsClass = new Products();

						$processingOrdersOrContacts = ( get_option( $contactsClass->getOptions()['processing'], false ) ) || ( get_option( $productsClass->getOptions()['processing'], false ) );

						$canSyncOrders = $canSyncOrders && ! $processingOrdersOrContacts;
						?>
						<span class="conn-status-indicator">
							<?php if ( isset( $connection_status['status'] ) && ! empty( 'connected' === $connection_status['status'] ) ) { ?>
								<span class="conn-status">Connection Status:&nbsp;</span>
								<span class="text-success">
									Connected
								</span>
								<br><br>
								<button class="btn btn-primary" id="myBtn">Disconnect</button>
								<div style="display: flex; flex-flow: row; align-items: center; align-items: center;">
									<!-- <button class="btn btn-light"  type="submit" name="dis">Disconnected</button> -->


									<!-- The Modal -->
									<div id="myModal" class="modal">

										<!-- Modal content -->
										<div class="modal-content">
											<span class="close">&times;</span>
											<h4>Are you sure you want to disconnect the API?</h4>
											<div style=" text-align: center;display: flex; flex-flow: row; align-items: center;">
												<form method="post" style="margin-right: 1rem; align-items: center; ">
													<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
													<input type="hidden" name="cs_action" value="disconnected">
													<input type="hidden" name="example_value" value="123">
													<div class="btn btn-primary" onclick="submitMyForm(this);">
														<span>
														</span>
														<span>
															Yes
														</span>
													</div>
												</form>
												<form method="post">
													<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
													<input type="hidden" name="cs_action" value="cencel">
													<input type="hidden" name="example_value" value="123">
													<div class="btn btn-primary" onclick="submitMyForm(this);">
														<span>
														</span>
														<span>
															No
														</span>
													</div>
												</form>
											</div>
										</div>
									</div>

								</div>


							<?php } else { ?>
								<span class="conn-status">Connection Status:&nbsp;</span>
								<span class="text-warning">
									Disconnected
								</span>
								<br><br>
								<button class="btn btn-primary" id="myBtn">Connect</button>

								<!-- <button class="btn btn-light"  type="submit" name="dis">Disconnected</button> -->


								<!-- The Modal -->
								<div id="myModal" class="modal">

									<!-- Modal content -->
									<div class="modal-content">
										<span class="close">&times;</span>
										<?php
										PageLoader::loadPage(
											'settingsPage.php',
											array(
												'pageTitle' => 'ClinicSoftware',
												'pageSlug' => 'main-settings',
											)
										);
										?>
										<br> <br>
										<form method="post">
											<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
											<input type="hidden" name="cs_action" value="cencel">
											<input type="hidden" name="example_value" value="123">
											<div class="btn btn-primary" onclick="submitMyForm(this);">
												<span>
												</span>

												<span>
													Cancel
												</span>
											</div>
										</form>
									</div>
								</div>

							<?php } ?>
						</span>

					</div>
				</div>
				<br>
				<div class="col-12" style="transform: scaleX(1.03); margin-left: .5vw">
					<div class="col-12 row">

						<div class="col-6 col-md-3">
							<div class="cs-card-point">
								<div class="card-title-point">
									Contacts Synced
								</div>
								<div class="card-amount-point">
									<?php echo esc_html( $regular_users_count ); ?>
								</div>
							</div>
						</div>

						<div class="col-6 col-md-3">
							<div class="cs-card-point">
								<div class="card-title-point">
									Products Synced
								</div>
								<!--class="card-amount-point"-->
								<div class="card-amount-point">
									<?php
									// echo "<pre>" .print_r($products,true)."</pre>";
									echo esc_html( $products_count );
									?>
								</div>
							</div>
						</div>

						<div class="col-6 col-md-3">
							<div class="cs-card-point">
								<div class="card-title-point">
									Coupons Synced
								</div>
								<div class="card-amount-point">
									<?php
									$coupon_posts = get_posts(
										array(
											'posts_per_page' => -1,
											'orderby'   => 'name',
											'order'     => 'asc',
											'post_type' => 'shop_coupon',
										)
									);
									echo esc_html( count( $coupon_posts ) );
									?>
								</div>
							</div>
						</div>

						<div class="col-6 col-md-3">
							<div class="cs-card-point">
								<div class="card-title-point">
									Orders Synced
								</div>
								<div class="card-amount-point">
									<?php

									$query  = new WC_Order_Query(
										array(
											'limit' => -1,
											'page'  => -1,
										)
									);
									$orders = $query->get_orders();
									$count  = 0;
									foreach ( $orders as $q ) {
										++$count;
									}
									echo esc_html( $count );
									?>
								</div>
							</div>
						</div>

					</div>
				</div>

				<br>
				<div class="col-12_ cs-box_ " style="width:full">

					<div class="col-12 row">
						<h4>Available Actions</h4>
						<br>
						<div class="col-12 col-sm-6">
							<div class="available-option">
								<h4 style="color: var(--c-hl);">Contacts</h4>
								<p>
									Syncronize your <code>Contacts</code>, this will instantly talk to
									<code>ClinicSoftware.com</code> and share the data between the two systems.
								</p>
								<div style="display: flex; flex-flow: row; align-items: center;">
									<form method="post" style="margin-right: 1rem;">
										<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
										<input type="hidden" name="cs_action" value="download_contacts">
										<input type="hidden" name="example_value" value="123">

										<?php
										if ( ! get_option( $contactsClass->getOptions()['processing'] ) ) {
											?>
											<div class="btn btn-primary" onclick="submitMyForm(this);">
												<span>
													<!-- ICON GOES HERE -->
												</span>
												<span>
													Import Contacts
												</span>
											</div>
											<?php
										} else {
											?>
											<div style="opacity: 0.5;" class="btn btn-primary">
												<span>
													<!-- ICON GOES HERE -->
												</span>
												<span>
													Processing Imports...
												</span>
											</div>
											<?php
										}
										?>
									</form>
									<form method="post">
										<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
										<input type="hidden" name="cs_action" value="upload_contacts">
										<input type="hidden" name="example_value" value="123">
										<?php
										if ( ! get_option( $contactsClass->getOptions()['exportProcessing'] ) ) {
											?>
											<div class="btn btn-primary" onclick="submitMyForm(this);">
												<span>
													<!-- ICON GOES HERE -->
												</span>
												<span>
													Export Contacts
												</span>
											</div>
											<?php
										} else {
											?>
											<div style="opacity: 0.5;" class="btn btn-primary">
												<span>
													<!-- ICON GOES HERE -->
												</span>
												<span>
													Processing Exports...
												</span>
											</div>
											<?php
										}
										?>
									</form>
								</div>
							</div>
						</div>

						<div class="col-12 col-sm-6">
							<div class="available-option">
								<h4 style="color: var(--c-hl);">Products</h4>
								<p>
									Syncronize your <code>Products</code>, this will instantly talk to
									<code>ClinicSoftware.com</code> and share the data between the two systems.
								</p>
								<div style="display: flex; flex-flow: row; align-items: center;">
									<form method="post" style="margin-right: 1rem;">
										<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
										<input type="hidden" name="cs_action" value="download_products">
										<input type="hidden" name="example_value" value="123">
										<?php
										if ( ! get_option( $productsClass->getOptions()['processing'] ) ) {
											?>
											<div class="btn btn-primary" onclick="submitMyForm(this);">
												<span>
													<!-- ICON GOES HERE -->
												</span>
												<span>
													Import Products
												</span>
											</div>
											<?php
										} else {
											?>
											<div style="opacity: 0.5;" class="btn btn-primary">
												<span>
													<!-- ICON GOES HERE -->
												</span>
												<span>
													Processing Imports...
												</span>
											</div>
											<?php
										}
										?>
									</form>
									<form method="post">
										<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
										<input type="hidden" name="cs_action" value="upload_products">
										<input type="hidden" name="example_value" value="123">
										<?php
										if ( ! get_option( $productsClass->getOptions()['exportProcessing'] ) ) {
											?>
											<div class="btn btn-primary" onclick="submitMyForm(this);">
												<span>
													<!-- ICON GOES HERE -->
												</span>
												<span>
													Export Products
												</span>
											</div>
											<?php
										} else {
											?>
											<div style="opacity: 0.5;" class="btn btn-primary">
												<span>
													<!-- ICON GOES HERE -->
												</span>
												<span>
													Processing Exports...
												</span>
											</div>
											<?php
										}
										?>
									</form>
								</div>
							</div>
						</div>

					</div>
					<br>
					<div class="col-12 row ">
						<div class="col-12 col-sm-6">
							<div class="available-option">
								<h4 style="color: var(--c-hl);">Coupons</h4>
								<p>
									Syncronize your <code>Coupons</code>, this will instantly talk to
									<code>ClinicSoftware.com</code> and share the data between the two systems.
								</p>
								<div style="display: flex; flex-flow: row; align-items: center;">
									<form method="post" style="margin-right: 1rem;">
										<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
										<input type="hidden" name="cs_action" value="download_coupons">
										<input type="hidden" name="example_value" value="123">
										<?php
										if ( ! get_option( ( new Coupons() )->getOptions()['processing'] ) ) {
											?>
											<div class="btn btn-primary" onclick="submitMyForm(this);">
												<span>
													<!-- ICON GOES HERE -->
												</span>
												<span>
													Import Coupons
												</span>
											</div>
											<?php
										} else {
											?>
											<div style="opacity: 0.5;" class="btn btn-primary">
												<span>
													<!-- ICON GOES HERE -->
												</span>
												<span>
													Processing Imports...
												</span>
											</div>
											<?php
										}
										?>
									</form>
									<form method="post">
										<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
										<input type="hidden" name="cs_action" value="upload_coupons">
										<input type="hidden" name="example_value" value="123">
										<?php
										if ( ! get_option( ( new Coupons() )->getOptions()['exportProcessing'] ) ) {
											?>
											<div class="btn btn-primary" onclick="submitMyForm(this);">
												<span>
													<!-- ICON GOES HERE -->
												</span>
												<span>
													Export Coupons
												</span>
											</div>
											<?php
										} else {
											?>
											<div style="opacity: 0.5;" class="btn btn-primary">
												<span>
													<!-- ICON GOES HERE -->
												</span>
												<span>
													Processing Exports...
												</span>
											</div>
											<?php
										}
										?>
									</form>
								</div>
							</div>
						</div>

						<script>
							function submitMyForm(self) {
								console.log(self.parentElement.submit());
							}
						</script>

						<div class="col-12 col-sm-6">
							<div class="available-option">
								<h4 style="color: var(--c-hl);">Orders</h4>
								<p>
									Syncronize your <code>Orders</code>, this will instantly talk to
									<code>ClinicSoftware.com</code> and share the data between the two systems.
								</p>
								<div style="display: flex; flex-flow: row; align-items: center;">
									<form method="post" style="margin-right: 1rem;">
										<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
										<input type="hidden" name="cs_action" value="download_orders">
										<input type="hidden" name="example_value" value="123">
										<?php
										if ( ! $canSyncOrders ) {
											?>
											<div style="opacity: 0.5;" class="btn btn-primary">
												<span>
													<!-- ICON GOES HERE -->
												</span>
												<span>
													Waiting for contacts and products to be added
												</span>
											</div>
											<?php
										} elseif ( ! get_option( ( new Orders() )->getOptions()['processing'] ) ) {
											?>
											<div class="btn btn-primary" onclick="submitMyForm(this);">
												<span>
													<!-- ICON GOES HERE -->
												</span>
												<span>
													Import Orders
												</span>
											</div>
											<?php
										} else {
											?>
											<div style="opacity: 0.5;" class="btn btn-primary">
												<span>
													<!-- ICON GOES HERE -->
												</span>
												<span>
													Processing Imports...
												</span>
											</div>
											<?php
										}
										?>
									</form>
									<form method="post">
										<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
										<input type="hidden" name="cs_action" value="upload_orders">
										<input type="hidden" name="example_value" value="123">
										<?php
										if ( ! $canSyncOrders ) {
											?>
											<div style="opacity: 0.5;" class="btn btn-primary">
												<span>
													<!-- ICON GOES HERE -->
												</span>
												<span>
													Waiting for contacts and products to be added
												</span>
											</div>
											<?php
										} elseif ( ! get_option( ( new Orders() )->getOptions()['exportProcessing'] ) ) {
											?>
											<div class="btn btn-primary" onclick="submitMyForm(this);">
												<span>
													<!-- ICON GOES HERE -->
												</span>
												<span>
													Export Orders
												</span>
											</div>
											<?php
										} else {
											?>
											<div style="opacity: 0.5;" class="btn btn-primary">
												<span>
													<!-- ICON GOES HERE -->
												</span>
												<span>
													Processing Exports...
												</span>
											</div>
											<?php
										}
										?>
									</form>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="col-12 cs-box_ tabcontentt logs active" style="width:inherit; display: none;" id="Logs">
				<?php
				global $wpdb;

				$results = array();
				if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( $_POST['_wpnonce'] ), 'cswoo_nonce' ) ) {
					$logs_keyword = sanitize_text_field( isset( $_POST['logs_keyword'] ) ? $_POST['logs_keyword'] : '' );
					$results      = array();

					$con       = sanitize_text_field( isset( $_POST['con'] ) ? $_POST['con'] : 15 );
					$log_month = sanitize_text_field( isset( $_POST['m'] ) ? $_POST['m'] : 0 );
				} else {
					$logs_keyword = '';
					$con          = 15;
					$log_month    = 0;
				}

				$logs_filter_keyword = "%$logs_keyword%";

				$date_f   = gmdate( 'Y-m-d H:i:s', strtotime( $log_month . ' month' ) );
				$date_fii = gmdate( 'Y-m-d H:i:s', strtotime( '- 1 month', strtotime( $date_f ) ) );

				$selectMonth = gmdate( 'M Y', strtotime( $date_f ) );

				try {

					$results = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT * FROM wp_status WHERE `time` <= %s AND `time` > %s AND `status`='error' AND (`name` LIKE %s OR `status` LIKE %s OR `description` LIKE %s) ORDER BY `id` DESC LIMIT %d",
							array(
								$date_f,
								$date_fii,
								$logs_filter_keyword,
								$logs_filter_keyword,
								$logs_filter_keyword,
								$con,
							)
						)
					) ?? array();
				} catch ( \Throwable $th ) {
					// Log or handle the exception
					error_log( 'Error in SQL query: ' . $th->getMessage() );
				}

				$con = $con + 15;
				?>


				<div class="container">
					<div class="cs-box" style="">
						<form method="POST">
							<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
							<input type="hidden" name="con" value="<?php echo esc_attr( $con ); ?>">
							<input type="hidden" name="thisMonth" value="<?php echo esc_attr( $selectMonth ); ?>">
							<input type="hidden" name="m" value="<?php echo esc_attr( $log_month ); ?>">
							<input type="hidden" name="logs" value="Logs">
							<input type="text" name="logs_keyword" placeholder="Enter keyword" value="<?php echo esc_attr( $logs_keyword ); ?>">
							<button class="btn" style="" type="submit" name="submit">Search Logs</button>
						</form>
					</div>
					<div class="timeline">
						<div class="timeline-month">
							<?php echo esc_html( $selectMonth ); ?>
							<span>3 Entries</span>
						</div>
						<div class="timeline-section">
							<!-- <div class="timeline-date">
								21, Tuesday
							</div> -->
							<?php
							$count_logs = count( $results );
							?>
							<table class="table" style="width: 100%; overflow: auto; text-align:left">
								<thead>
									<th></th>
									<th colspan="2">Action</th>
									<th>Status</th>
									<th>Message</th>
									<th>Date</th>
								</thead>
								<tbody>

									<?php
									foreach ( $results as $result ) {
										?>
										<tr>
											<td class="box-">
												<i class="fa fa-asterisk text-success" aria-hidden="true"></i>
												<?php
												// ucfirst($result->user);
												?>
											</td>
											<td colspan="2">
												<?php
												if ( 'Import Contacts' === $result->name ) {
													$user_action = ucfirst( $result->user ) . ' has Imported Contacts!';
												} elseif ( 'Export Contacts' === $result->name ) {
													$user_action = ucfirst( $result->user ) . ' has Exported Contacts!';
												} elseif ( 'Import Products' === $result->name ) {
													$user_action = ucfirst( $result->user ) . ' has Imported Products!';
												} elseif ( 'Export Products' === $result->name ) {
													$user_action = ucfirst( $result->user ) . ' has Exported Products!';
												} elseif ( 'Import Coupons' === $result->name ) {
													$user_action = ucfirst( $result->user ) . ' has Imported Coupons!';
												} elseif ( 'Export Coupons' === $result->name ) {
													$user_action = ucfirst( $result->user ) . ' has Exported Coupons!';
												} elseif ( 'Import Orders' === $result->name ) {
													$user_action = ucfirst( $result->user ) . ' has Imported Orders!';
												} elseif ( 'Export Orders' === $result->name ) {
													$user_action = ucfirst( $result->user ) . ' has Exported Orders!';
												} else {
													$user_action = $result->name;
												}
												?>
												<h4 style="color: var(--c-hl);"><?php echo esc_html( $user_action ); ?></h4>
											</td>
											<td class="box-content_">
												<p>
													<?php if ( 'error' === $result->status ) { ?>
														<span style="color: red;"> <?php echo 'Status: Error'; ?></span>
													<?php } ?>
												</p>
											</td>
											<td>
												<?php echo esc_html( $result->description ); ?>
											</td>
											<td class=""><?php echo esc_html( gmdate( 'd-m-Y H:i:s', strtotime( $result->time ) ) ); ?></td>
										</tr>
										<?php
									}
									?>
								</tbody>
							</table>
							<?php if ( 0 !== $count_logs ) { ?>
								<div class="timeline-date" style="margin-top: 20px;">
									<form method="POST">
										<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
										<input type="hidden" name="con" value="<?php echo esc_attr( $con ); ?>">
										<input type="hidden" name="thisMonth" value="<?php echo esc_attr( $selectMonth ); ?>">
										<input type="hidden" name="m" value="<?php echo esc_attr( $log_month ); ?>">
										<input type="hidden" name="logs" value="Logs">
										<button class="btn" style="background-color:#85c5f9; border:#85c5f9; color:#fff;" type="submit" name="submit">View More</button>
									</form>
								</div>
							<?php } ?>
						</div>
					</div>
					<div class="timeline-month">
						<form method="POST">
							<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
							<input type="hidden" name="m" value="<?php echo esc_attr( $log_month - 1 ); ?>">
							<input type="hidden" name="logs" value="Logs">
							<button class="btn btn-light" style="background-color:#2295f5; border:#2295f5; color:#fff;" type="submit" name="submitMonth">Last Month</button>

						</form>
					</div>
					<?php if ( 0 !== (int) $log_month ) { ?>
						<div class="timeline-month">
							<form method="POST">
								<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
								<input type="hidden" name="m" value="<?php echo esc_attr( $log_month + 1 ); ?>">
								<input type="hidden" name="logs" value="Logs">
								<button class="btn btn-light" style="background-color:#2295f5; border:#2295f5; color:#fff;" type="submit" name="nextMonth">Next Month</button>

							</form>
						</div>
					<?php } ?>
				</div>
				<!-- <h4>Available Actions</h4> -->
			</div>
			<div id="Feeds" class="tabcontentt col-12 cs-box_ feeds" style="width:inherit; display: none;">
				<?php
				global $wpdb;

				if ( ! verifyNonceIsset() ) {
					$feeds_keyword = '';

					$conf        = 15;
					$feeds_month = 0;
				} else {
					$feeds_keyword = sanitize_text_field( isset( $_POST['feeds_keyword'] ) ? $_POST['feeds_keyword'] : '' );

					$results = array();

					$conf        = sanitize_text_field( isset( $_POST['conf'] ) ? $_POST['conf'] : 15 );
					$feeds_month = sanitize_text_field( isset( $_POST['f'] ) ? $_POST['f'] : 0 );
				}
				$feeds_filter_keyword = "%$feeds_keyword%";

				$date_f   = gmdate( 'Y-m-d H:i:s', strtotime( $feeds_month . ' month' ) );
				$date_fii = gmdate( 'Y-m-d H:i:s', strtotime( '- 1 month', strtotime( $date_f ) ) );

				$selectMonth = gmdate( 'M Y', strtotime( '+' . $feeds_month . ' month' ) );

				try {
					$results = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT * FROM wp_status WHERE `time` <= %s AND `time` > %s AND `status` != 'error' AND (`name` LIKE %s OR `status` LIKE %s OR `description` LIKE %s) ORDER BY `id` DESC LIMIT %d",
							array(
								$date_f,
								$date_fii,
								$feeds_filter_keyword,
								$feeds_filter_keyword,
								$feeds_filter_keyword,
								$conf,
							)
						)
					) ?? array();
				} catch ( \Throwable $th ) {
					// Log or handle the exception
					error_log( 'Error in SQL query: ' . $th->getMessage() );
				}

				$selectMonth = gmdate( 'M Y', strtotime( $date_f ) );

				$conf = $conf + 15;

				?>


				<div class="container">

					<div class="cs-box" style="">
						<form method="POST">
							<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
							<input type="hidden" name="conf" value="<?php echo esc_attr( $conf ); ?>">
							<input type="hidden" name="thisMonth" value="<?php echo esc_attr( $selectMonth ); ?>">
							<input type="hidden" name="f" value="<?php echo esc_attr( $feeds_month ); ?>">
							<input type="hidden" name="feeds" value="Feeds">
							<input type="text" name="feeds_keyword" placeholder="Enter keyword" value="<?php echo esc_attr( $feeds_keyword ); ?>">
							<button class="btn" style="" type="submit" name="submitf">Search Feed</button>
						</form>
					</div>
					<div class="timeline">
						<div class="timeline-month">
							<?php echo esc_html( $selectMonth ); ?>
							<span>3 Entries</span>
						</div>
						<div class="timeline-section">
							<table class="table" style="width: 100%; text-align:left">
								<thead>
									<th></th>
									<th colspan="2">Action</th>
									<th>Status</th>
									<th>Message</th>
									<th>Date</th>
								</thead>
								<tbody>

									<?php
									foreach ( $results as $result ) {
										?>
										<tr>
											<td class="box-">
												<i class="fa fa-asterisk text-success" aria-hidden="true"></i>
												<?php
												// ucfirst($result->user);
												?>
											</td>
											<td colspan="2">
												<?php
												if ( 'Import Contacts' === $result->name ) {
													$user_action = ucfirst( $result->user ) . ' has Imported Contacts!';
												} elseif ( 'Export Contacts' === $result->name ) {
													$user_action = ucfirst( $result->user ) . ' has Exported Contacts!';
												} elseif ( 'Import Products' === $result->name ) {
													$user_action = ucfirst( $result->user ) . ' has Imported Products!';
												} elseif ( 'Export Products' === $result->name ) {
													$user_action = ucfirst( $result->user ) . ' has Exported Products!';
												} elseif ( 'Import Coupons' === $result->name ) {
													$user_action = ucfirst( $result->user ) . ' has Imported Coupons!';
												} elseif ( 'Export Coupons' === $result->name ) {
													$user_action = ucfirst( $result->user ) . ' has Exported Coupons!';
												} elseif ( 'Import Orders' === $result->name ) {
													$user_action = ucfirst( $result->user ) . ' has Imported Orders!';
												} elseif ( 'Export Orders' === $result->name ) {
													$user_action = ucfirst( $result->user ) . ' has Exported Orders!';
												}

												?>
												<h4 style="color: var(--c-hl);"><?php echo esc_html( $user_action ); ?></h4>
											</td>
											<td class="box-content_">
												<p>
													<?php if ( 'warning' === $result->status ) { ?>
														<span style="color: red;"> <?php echo 'Status: Warning'; ?></span>
													<?php } ?>
													<?php if ( 'success' === $result->status ) { ?>
														<span style="color: green;"> <?php echo 'Status: Success'; ?></span>
													<?php } ?>
												</p>
											</td>
											<td>
												<?php echo esc_html( $result->description ); ?>
											</td>
											<td class=""><?php echo esc_html( gmdate( 'd-m-Y H:i:s', strtotime( $result->time ) ) ); ?>
											</td>
										</tr>
										<?php
									}
									?>
								</tbody>
							</table>
							<?php if ( 0 !== $count ) { ?>
								<div class="timeline-date" style="margin-top: 20px;">
									<form method="POST">
										<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
										<input type="hidden" name="conf" value="<?php echo esc_attr( $conf ); ?>">
										<input type="hidden" name="thisMonth" value="<?php echo esc_attr( $selectMonth ); ?>">
										<input type="hidden" name="f" value="<?php echo esc_attr( $feeds_month ); ?>">
										<input type="hidden" name="feeds" value="Feeds">
										<button class="btn" style="background-color:#85c5f9; border:#85c5f9; color:#fff;" type="submit" name="submitf">View More</button>
									</form>
								</div>
							<?php } ?>
						</div>
					</div>
					<div class="timeline-month">
						<form method="POST">
							<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
							<input type="hidden" name="f" value="<?php echo esc_attr( $feeds_month - 1 ); ?>">
							<input type="hidden" name="feeds" value="Feeds">
							<button class="btn btn-light" style="background-color:#2295f5; border:#2295f5; color:#fff;" type="submit" name="submitMonthf">Last Month</button>
						</form>
					</div>
					<?php if ( 0 !== (int) $feeds_month ) { ?>
						<div class="timeline-month">
							<form method="POST">
								<input type="hidden" id="_wpnonce" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
								<input type="hidden" name="f" value="<?php echo esc_attr( $feeds_month + 1 ); ?>">
								<input type="hidden" name="feeds" value="Feeds">
								<button class="btn btn-light" style="background-color:#2295f5; border:#2295f5; color:#fff;" type="submit" name="nextMonthf">Next Month</button>

							</form>
						</div>
					<?php } ?>
				</div>
			</div>
			<div id="Settings" class=" col-12 cs-box_ tabcontentt elementcontent settings" style="width:inherit; display: none;">
				<div class="cs-box container ">
					<div class="col-12 row">
						<?php

						use ClinicSoftware\Lib\OptionsPage;

						PageLoader::loadPage(
							'settingsPage.php',
							array(
								'pageTitle' => 'ClinicSoftware',
								'pageSlug'  => 'main-settings',
							)
						);

						?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	function openMeniu(evt, name) {

		var i, tabcontent, tablinks;

		tabcontent = document.getElementsByClassName("tabcontentt");
		for (i = 0; i < tabcontent.length; i++) {
			tabcontent[i].style.display = "none";
		}
		tablinks = document.getElementsByClassName("tablinks");
		for (i = 0; i < tablinks.length; i++) {
			tablinks[i].className = tablinks[i].className.replace(" active", "");
		}
		document.getElementById(name).style.display = "block";
		evt.currentTarget.className += " active";
	}

	function Button(name) {

		var i, tabcontent, tablinks, myclass;
		var myclass = document.getElementById(name);

		tabcontent = document.getElementsByClassName("tabcontentt");

		for (i = 0; i < tabcontent.length; i++) {
			tabcontent[i].style.display = "none";
		}

		tablinks = document.getElementsByClassName("tablinks");

		for (i = 0; i < tablinks.length; i++) {
			tablinks[i].className = tablinks[i].className.replace(" active", "");
		}

		document.getElementById(name).style.display = "block";
		myclass.classList.add("active");
	}

	jQuery(document).ready(function($) {
		console.log("5");
		<?php
		if ( verifyNonceIsset() ) {
			if ( isset( $_POST['logs'] ) && 'Logs' === sanitize_text_field( $_POST['logs'] ) ) {
				?>
				console.log("7");
				Button("Logs");

			<?php } ?>
			<?php if ( isset( $_POST['feeds'] ) && 'Feeds' === sanitize_text_field( $_POST['feeds'] ) ) { ?>
				console.log("7");
				Button("Feeds");
				console.log("8");

				<?php
			}
		}
		?>
	});

	// Get the modal
	var modal = document.getElementById("myModal");

	// Get the button that opens the modal
	var btn = document.getElementById("myBtn");

	// Get the <span> element that closes the modal
	var span = document.getElementsByClassName("close")[0];

	// When the user clicks the button, open the modal 
	btn.onclick = function() {
		modal.style.display = "block";
	}

	// When the user clicks on <span> (x), close the modal
	span.onclick = function() {
		modal.style.display = "none";
	}

	// When the user clicks anywhere outside of the modal, close it
	window.onclick = function(event) {
		if (event.target == modal) {
			modal.style.display = "none";
		}
	}
</script>