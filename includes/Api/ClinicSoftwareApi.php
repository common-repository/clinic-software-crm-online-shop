<?php

namespace ClinicSoftware\Api;

use WP_Error;

class ClinicSoftwareApi {

	private $businessAlias = '';
	private $clientKey;
	private $clientSecret;
	private $apiURL = '';
	private $ch;
	private $last_result  = null;
	private $last_status  = null;
	private $last_error   = null;
	private $log_filename = null;
	private $debug        = false;

	public function __construct( $clientKey = '', $clientSecret = '', $businessAlias = '', $apiURL = '' ) {
		$this->clientKey    = $clientKey;
		$this->clientSecret = $clientSecret;
		$this->log_filename = __DIR__ . '/log.txt';
		$this->apiURL       = $this->secureBusinessURL( $apiURL );

		if ( ! empty( $businessAlias ) ) {
			$this->businessAlias = $businessAlias;
		}

		if ( $this->debug ) {
			$this->clearLog();
		}

		// $this->ch = curl_init();
	}

	private function secureBusinessURL( $url ) {
		$url = trim( $url, '\\\\' );
		$url = trim( $url, '//' );
		$url = trim( $url, '/' );
		$url = trim( $url, '\\' );

		$endpoint = 'api_business';
		return "https://$url/$endpoint";
	}

	public function dis() {
		delete_option( 'main-settings' );
		// add_filter('rest_authentication_errors',array($this->__construct, 'wp_snippet_disable_rest_api') );
		// add_filter('rest_authentication_errors',array($this->ch, 'wp_snippet_disable_rest_api') );
		// add_filter('rest_authentication_errors','wp_snippet_disable_rest_api');
	}
	public function wp_snippet_disable_rest_api() {
		return new WP_Error( 'rest_disabled', __( 'The WordPress REST API has been disabled.' ), array( 'status' => rest_authorization_required_code() ) );
	}

	public function __destruct() {
		if ( is_resource( $this->ch ) ) {
			curl_close( $this->ch );
		}
	}

	public function setDebug( $debug ) {
		$this->debug = $debug;

		if ( $this->debug ) {
			$this->clearLog();
		}
	}

	public function setURL( $url ) {
		$this->apiURL = $url;
	}

	public function getLastResult() {
		return $this->last_result;
	}

	public function getLastStatus() {
		return $this->last_status;
	}

	public function getLastError() {
		return $this->last_error;
	}

	public function call( $params = array() ) {
		try {
			return $this->implementedWpCall( $params );
		} catch ( \Throwable $th ) {
			error_log( $th->getMessage() );
			error_log( 'HTTP API Failed' );
		}
	}
	public function implementedWpCall( $params = array() ) {
		$this->last_result = null;
		$this->last_status = null;
		$this->last_error  = null;

		$params['business_client_alias'] = $this->businessAlias;
		$params['api_client_key']        = $this->clientKey;
		$params['api_client_time']       = time();
		$params['api_client_salt']       = uniqid( mt_rand(), true );
		$params['api_client_hash']       = hash( 'sha256', $params['api_client_salt'] . $params['api_client_time'] . $this->clientSecret );

		$start = microtime( true );

		if ( $this->debug ) {
			$this->writeLog( "Call to {$this->apiURL}: " . json_encode( $params ) );
		}

		error_log( "Call to {$this->apiURL}: " /* . json_encode($params) */ );
		$response      = wp_remote_post(
			$this->apiURL,
			array(
				'body' => $params,
			)
		);
		$response_body = wp_remote_retrieve_body( $response );
		$time          = microtime( true ) - $start;

		if ( $this->debug ) {
			$this->writeLog( 'Completed in ' . number_format( $time * 1000, 2 ) . ' ms' );
			$this->writeLog( 'Got response: ' . $response_body );
		}

		if ( is_wp_error( $response ) ) {
			$errorM = 'API call to ' . $this->apiURL . ' failed: ' . $response->get_error_message();
			error_log( $errorM );
			$this->last_error = $errorM;
			return null;
		}

		$result = json_decode( $response_body, true );
		if ( empty( $result ) ) {
			$this->last_error = "Failed decoding JSON response: {$response_body}";
			return null;
		}

		$this->last_result = $result;
		$this->last_status = $result['status'];

		if ( 'error' === $result['status'] ) {
			$this->last_error = "API Error: {$result['message']}";
			return null;
		}
		return empty( $result['data'] ) ? null : $result['data'];
	}

	public function add_document( int $client_id, string $base64, string $document_name, string $mime_type, bool $automatically_rename = true ) {

		$params = array(
			'action'               => 'add_document',
			'client_id'            => $client_id,
			'document_name'        => $document_name,
			'document_b64'         => $base64,
			'mime_type'            => $mime_type,
			'automatically_rename' => $automatically_rename ? 1 : 0,
		);

		// Return the results of the call with the provided parameters
		return $this->call( $params );
	}

	/**
	 * Get all client documents
	 * To get the actual document, use the download_document function
	 *
	 * @returns [ "files" => [ "", "" ] ]
	 */
	public function get_documents( int $client_id ) {
		$params = array(
			'action'    => 'get_documents',
			'client_id' => $client_id,
		);

		// Return the results of the call with the provided parameters
		return $this->call( $params );
	}

	/**
	 * Download a client's file data,
	 *
	 * @returns [ "data" => BASE64STRING ]
	 */
	public function download_documents( int $client_id, string $filePath ) {
		$params = array(
			'action'        => 'download_document',
			'client_id'     => $client_id,
			'document_path' => $filePath,
		);

		// Return the results of the call with the provided parameters
		return $this->call( $params );
	}

	/**
	 * Get the relationships of a client
	 *
	 * @param int $client_id The id of the target client.
	 */
	public function getRelationship( int $client_id ) {

		$params = array(
			'action'    => 'get_relationship',
			'client_id' => $client_id,
		);

		// Return the results of the call with the provided parameters
		return $this->call( $params );
	}

	/**
	 * Get a single or multiple services based on their IDs, a last-modified date and/or limit/offset
	 *
	 * @param null | int | array       $id            The id of the service(s) you are looking for
	 * @param string | null | DateTime $last_modified = null A last modified date for filtering out obsolete data
	 * @param int                      $limit         = 10 A limit of objects to return
	 * @param int                      $offset        = 0 An offset for the object array return
	 */
	public function get_services( $id = null, string $last_modified = null, int $limit = 10, int $offset = 0 ) {

		if ( 'string' === gettype( $last_modified ) ) {
			// Parse the last modified to a UNIX timestamp in seconds
			// $last_modified = strtotime($last_modified);

			// Check if the conversion failed
			if ( false === $last_modified ) {
				throw new \Exception( 'Invalid date provided as string' );
				// Correctly format last_modified
				$last_modified = gmdate( 'c', $last_modified );
			}
			$last_modified = gmdate( 'Y-m-d H:i:s', strtotime( $last_modified ) );

		} elseif ( is_a( $last_modified, 'DateTime' ) ) {
			$last_modified = $last_modified->format( 'c' );
		}

		// Check if the id is in array format
		if ( is_array( $id ) ) {
			foreach ( $id as $i ) {
				if ( is_object( $i ) || is_array( $i ) ) {
					throw new \Exception( 'Invalid id provided, please only provide an array of strings or numbers' );
				}
			}
		}

		$params = array(
			'action'        => 'get_services',
			'id'            => $id,
			'last_modified' => $last_modified,
			'limit'         => $limit,
			'offset'        => $offset,
		);
		// Return the results of the call with the provided parameters
		return $this->call( $params );
	}

	public function getClientNofSessionCourses( $client_id, $date_from = null, $date_to = null, $treatment = null ) {
		$params              = array();
		$params['action']    = 'client_get_nof_session_courses';
		$params['client_id'] = $client_id;
		$params['date_from'] = $date_from;
		$params['date_to']   = $date_to;
		$params['treatment'] = $treatment;
		return $this->call( $params );
	}

	public function getClientSessionCoursesPag( $client_id, $date_from = null, $date_to = null, $treatment = null, $offset = 0, $row_count = 0 ) {
		$params              = array();
		$params['action']    = 'client_get_session_courses_pag';
		$params['client_id'] = $client_id;
		$params['date_from'] = $date_from;
		$params['date_to']   = $date_to;
		$params['treatment'] = $treatment;
		$params['offset']    = $offset;
		$params['row_count'] = $row_count;
		return $this->call( $params );
	}

	public function getClientNofMinutesCourses( $client_id, $date_from = null, $date_to = null, $treatment = null ) {
		$params              = array();
		$params['action']    = 'client_get_nof_minutes_courses';
		$params['client_id'] = $client_id;
		$params['date_from'] = $date_from;
		$params['date_to']   = $date_to;
		$params['treatment'] = $treatment;
		return $this->call( $params );
	}

	public function getClientMinutesCoursesPag( $client_id, $date_from = null, $date_to = null, $treatment = null, $offset = 0, $row_count = 0 ) {
		$params              = array();
		$params['action']    = 'client_get_minutes_courses_pag';
		$params['client_id'] = $client_id;
		$params['date_from'] = $date_from;
		$params['date_to']   = $date_to;
		$params['treatment'] = $treatment;
		$params['offset']    = $offset;
		$params['row_count'] = $row_count;
		return $this->call( $params );
	}

	public function getClientConsentFormPDF( $client_consent_form_id ) {
		$params                           = array();
		$params['action']                 = 'get_signed_consent_form_pdf';
		$params['client_consent_form_id'] = $client_consent_form_id;
		return $this->call( $params );
	}

	public function getClientSignedConsentFormsList( $client_id ) {
		$params              = array();
		$params['action']    = 'get_client_signed_consent_forms_list';
		$params['client_id'] = $client_id;
		return $this->call( $params );
	}

	public function getClientNofUnreadMessagesGlobal( $client_id, $last_message_id = 0 ) {
		$params                    = array();
		$params['action']          = 'get_client_nof_unread_messages_global';
		$params['client_id']       = $client_id;
		$params['last_message_id'] = $last_message_id;
		return $this->call( $params );
	}

	public function getClientNofUnreadMessagesBySalon( $client_id, $salon_id, $last_message_id = 0 ) {
		$params                    = array();
		$params['action']          = 'get_client_nof_unread_messages_by_salon';
		$params['client_id']       = $client_id;
		$params['salon_id']        = $salon_id;
		$params['last_message_id'] = $last_message_id;
		return $this->call( $params );
	}

	public function getClientMessagesBySalon( $client_id, $salon_id, $date_start = null, $date_end = null, $last_message_id = 0, $mark_messages_as_read = 0 ) {
		$params                          = array();
		$params['action']                = 'get_client_messages_by_salon';
		$params['salon_id']              = $salon_id;
		$params['client_id']             = $client_id;
		$params['date_start']            = $date_start;
		$params['date_end']              = $date_end;
		$params['last_message_id']       = $last_message_id;
		$params['mark_messages_as_read'] = $mark_messages_as_read;
		return $this->call( $params );
	}

	public function addClientMessage( $client_id, $salon_id, $message ) {
		$params              = array();
		$params['action']    = 'add_client_message';
		$params['salon_id']  = $salon_id;
		$params['client_id'] = $client_id;
		$params['message']   = $message;
		return $this->call( $params );
	}

	public function addStaffMessage( $client_id, $salon_id, $staff_id, $message ) {
		$params              = array();
		$params['action']    = 'add_client_message';
		$params['salon_id']  = $salon_id;
		$params['client_id'] = $client_id;
		$params['staff_id']  = $staff_id;
		$params['message']   = $message;
		return $this->call( $params );
	}

	public function getSalons() {
		$params           = array();
		$params['action'] = 'get_salons';
		return $this->call( $params );
	}

	public function getBarcodeImage( $barcode ) {
		$params            = array();
		$params['action']  = 'get_barcode_image';
		$params['barcode'] = $barcode;
		return $this->call( $params );
	}

	public function getResources() {
		$params           = array();
		$params['action'] = 'get_api_client_resources';
		return $this->call( $params );
	}

	/**
	 * Get shifts from a date to a date, optionally filter by staff.
	 *
	 * @param
	 */
	public function getShifts( \DateTime $dateFrom, \DateTime $dateTo, ?int $staff_id = null ): ?array {
		$params           = array();
		$params['action'] = 'staff_get_shift';

		// Handle invalid Dates
		if ( $dateFrom->getTimestamp() > $dateTo->getTimestamp() ) {
			throw new \Exception( 'The from date can not be greater than the to date' );
		}

		if ( round( ( ( ( $dateTo->getTimestamp() - $dateFrom->getTimestamp() ) / 60 ) / 60 ) / 24, 2 ) > 30 ) {
			throw new \Exception( 'The date range is invalid, please do not use a larger date range than 1 month' );
		}

		$params['staff_id'] = $staff_id;

		$params['date_from'] = $dateFrom->format( 'Y-m-d' );
		$params['date_to']   = $dateTo->format( 'Y-m-d' );

		// Make the call to the API
		return $this->call( $params );
	}

	/**
	 * Get all of the available appointment statuses
	 *
	 * @return Array<string>
	 */
	public function appointment_get_statuses(): array {
		$params = array(
			'action' => 'appointment_get_statuses',
		);

		// Make the call to the API
		return $this->call( $params );
	}

	public function getClients( string $last_modified, int $limit = 10, int $offset = 0, ?array $whitelist = null ) {

		$params           = array();
		$params['action'] = 'get_clients';
		$params['limit']  = $limit;
		$params['offset'] = $offset;

		if ( ! empty( $last_modified ) ) {
			$params['last_modified'] = gmdate( 'c', strtotime( $last_modified ) );
		}

		if ( ! empty( $whitelist ) ) {
			$params['whitelist'] = implode( ',', $whitelist );
		}

		return $this->call( $params );
	}

	public function getOrders( string $last_modified, int $limit = 10, int $offset = 0 ) {

		$params           = array();
		$params['action'] = 'get_shop_orders';
		$params['limit']  = $limit;
		$params['offset'] = $offset;

		if ( ! empty( $last_modified ) ) {
			$params['last_modified'] = gmdate( 'c', strtotime( $last_modified ) );
		}

		return $this->call( $params );
	}
	public function getCoupons( string $last_modified, int $limit = 10, int $offset = 0 ) {

		$params           = array();
		$params['action'] = 'get_coupons';
		$params['limit']  = $limit;
		$params['offset'] = $offset;

		if ( ! empty( $last_modified ) ) {
			$params['last_modified'] = gmdate( 'c', strtotime( $last_modified ) );
		}

		return $this->call( $params );
	}
	public function checkStatus( string $last_modified, int $limit = 10, int $offset = 0, ?array $whitelist = null ) {

		$params           = array();
		$params['action'] = 'check_status';
		$params['limit']  = $limit;
		$params['offset'] = $offset;

		if ( ! empty( $last_modified ) ) {
			$params['last_modified'] = gmdate( 'c', strtotime( $last_modified ) );
		}

		if ( ! empty( $whitelist ) ) {
			$params['whitelist'] = implode( ',', $whitelist );
		}

		return $this->call( $params );
	}

	public function getClientByID( $client_id, $is_online_account = 1 ) {
		$params                      = array();
		$params['action']            = 'get_client_by_id';
		$params['client_id']         = $client_id;
		$params['is_online_account'] = $is_online_account;
		return $this->call( $params );
	}

	public function getClientByEmail( $client_email, $is_online_account = 1 ) {
		$params                      = array();
		$params['action']            = 'get_client_by_email';
		$params['client_email']      = $client_email;
		$params['is_online_account'] = $is_online_account;
		return $this->call( $params );
	}

	public function getClientByEmailAndPassword( $client_email, $client_password, $is_online_account = 1 ) {
		$params                      = array();
		$params['action']            = 'get_client_by_email_password';
		$params['client_email']      = $client_email;
		$params['client_password']   = $client_password;
		$params['is_online_account'] = $is_online_account;
		return $this->call( $params );
	}

	public function getClientByPhone( $client_phone, $is_online_account = 1 ) {
		$params                      = array();
		$params['action']            = 'get_client_by_phone';
		$params['client_phone']      = $client_phone;
		$params['is_online_account'] = $is_online_account;
		return $this->call( $params );
	}

	public function getLeadByPhone( $client_phone ) {
		$params               = array();
		$params['action']     = 'get_lead_by_phone';
		$params['lead_phone'] = $client_phone;
		return $this->call( $params );
	}

	public function getClientByName( $client_name, $is_online_account = 1 ) {
		$params                      = array();
		$params['action']            = 'get_client_by_name';
		$params['client_name']       = $client_name;
		$params['is_online_account'] = $is_online_account;
		return $this->call( $params );
	}

	public function addClient( $data ) {
		$params           = array();
		$params['action'] = 'add_client';
		$params['data']   = json_encode( $data );
		return $this->call( $params );
	}
	public function addServices( $data ) {
		$params           = array();
		$params['action'] = 'add_service';
		$params['data']   = json_encode( $data );
		return $this->call( $params );
	}
	public function addCoupons( $data ) {
		$params           = array();
		$params['action'] = 'add_coupon';
		$params['data']   = json_encode( $data );
		return $this->call( $params );
	}
	public function addOrders( $data ) {
		$params           = array();
		$params['action'] = 'add_shop_order';
		$params['data']   = json_encode( $data );
		return $this->call( $params );
	}
	public function updateClient( $client_id, $data ) {
		$params              = array();
		$params['action']    = 'update_client';
		$params['client_id'] = $client_id;
		$params['data']      = json_encode( $data );
		return $this->call( $params );
	}

	public function deleteClient( $client_id ) {
		$params              = array();
		$params['action']    = 'delete_client';
		$params['client_id'] = $client_id;
		return $this->call( $params );
	}

	public function subscribeClientToNewsletter( $client_id ) {
		$params              = array();
		$params['action']    = 'client_subscribe_newsletter';
		$params['client_id'] = $client_id;
		return $this->call( $params );
	}

	public function unsubscribeClientFromNewsletter( $client_id ) {
		$params              = array();
		$params['action']    = 'client_unsubscribe_newsletter';
		$params['client_id'] = $client_id;
		return $this->call( $params );
	}

	public function getClientReceipts( $client_id ) {
		$params              = array();
		$params['action']    = 'get_client_receipts';
		$params['client_id'] = $client_id;
		return $this->call( $params );
	}

	public function emailReceiptToClient( $bill_id ) {
		$params            = array();
		$params['action']  = 'client_email_receipt';
		$params['bill_id'] = $bill_id;
		return $this->call( $params );
	}

	public function getClientAppointments( $client_id ) {
		$params              = array();
		$params['action']    = 'client_get_appointments';
		$params['client_id'] = $client_id;
		return $this->call( $params );
	}

	public function getAppointments( string $from = '2021-11-01', string $to = '2021-12-20', ?string $last_modified = null ) {
		// Set the parameters for the call
		$params = array(
			'action'        => 'get_appointments',
			// From date Y-m-d
			'from'          => $from,
			// To date Y-m-d
			'to'            => $to,
			// Last modified Y-m-d
			'last_modified' => $last_modified,
		);

		return $this->call( $params );
	}

	/**
	 * Add an appointment to the system
	 */
	public function addAppointment( AppointmentObject $appointment ) {
		// Init the params for the call
		$params = array( 'action' => 'appointment_add' );

		// Parse the provided appointment
		$parsedAppointment = $appointment->toApiJSON();

		// Merge into params
		$params = array_merge( $params, $parsedAppointment );

		return $this->call( $params );
	}

	public function appointment_availability( \DateTime $date, int $duration, array $items ) {
		// Error Checking
		// Make sure the duration is at least 5 minutes
		$params = array();
		if ( $duration < 5 ) {
			throw new \Exception( 'The duration must be greater than or equal to 5 minutes' );
		} elseif ( $duration > 1440 ) {
			throw new \Exception( 'The duration of an appointment can not be greater than 24h/1440min' );
		}

			// Check if there are any items in the array
		if ( empty( $items ) || count( $items ) < 1 ) {
			throw new \Exception( 'You must provide at least 1 service ID' );
		}

			// Check if the provided services are integers
		foreach ( $items as $i ) {
			if ( ! is_int( $i ) ) {
				throw new \Exception( 'The service ID must be of type Integer' );
			}
		}

		// Set the parameters for the call
		$params = array(
			'action' => 'appointment_availability',
			// Add the starting Date and Time
			'date'   => $date->format( 'Y-m-d' ),
			// Provide the items
			'items'  => implode( ',', $items ),
		);

		return $this->call( $params );
	}

	/**
	 * Cancel an appointment
	 *
	 * @param int $appointmentID The id of the appointment to cancel.
	 * @param int $staffID       The id of the staff that has performed this action, by default it will be 0 which means "client"
	 */
	public function cancelAppointment( int $appointmentID, int $staffID = 0 ) {
		// Set the parameters for the call
		$params = array(
			'action'         => 'appointment_cancel',
			// The ID of the appointment to cancel
			'appointment_id' => $appointmentID,
			// The ID of the appointment to cancel
			'staff_id'       => $staffID,
		);

		return $this->call( $params );
	}

	public function getClientBalance( $client_id ) {
		$params              = array();
		$params['action']    = 'client_get_balance';
		$params['client_id'] = $client_id;
		return $this->call( $params );
	}

	public function getClientBalanceHistory( $client_id ) {
		$params              = array();
		$params['action']    = 'client_get_balance_history';
		$params['client_id'] = $client_id;
		return $this->call( $params );
	}

	public function getVoucherByBarcode( $voucher_barcode ) {
		$params                    = array();
		$params['action']          = 'get_voucher';
		$params['voucher_barcode'] = $voucher_barcode;
		return $this->call( $params );
	}

	public function addVoucher( $data ) {
		$params           = array();
		$params['action'] = 'add_voucher';
		$params['data']   = json_encode( $data );
		return $this->call( $params );
	}

	public function updateVoucherByBarcode( $voucher_barcode, $data ) {
		$params                    = array();
		$params['action']          = 'update_voucher';
		$params['voucher_barcode'] = $voucher_barcode;
		$params['data']            = json_encode( $data );
		return $this->call( $params );
	}

	public function deleteVoucherByBarcode( $voucher_barcode ) {
		$params                    = array();
		$params['action']          = 'delete_voucher';
		$params['voucher_barcode'] = $voucher_barcode;
		return $this->call( $params );
	}

	public function assignVoucherBarcodeToClient( $voucher_barcode, $client_id ) {
		$params                    = array();
		$params['action']          = 'client_assign_voucher';
		$params['voucher_barcode'] = $voucher_barcode;
		$params['client_id']       = $client_id;
		return $this->call( $params );
	}

	public function getClientVouchers( $client_id ) {
		$params              = array();
		$params['action']    = 'client_get_vouchers';
		$params['client_id'] = $client_id;
		return $this->call( $params );
	}

	public function getClientTrackSessionsHistory( $client_id ) {
		$params              = array();
		$params['action']    = 'client_get_track_sessions_history';
		$params['client_id'] = $client_id;
		return $this->call( $params );
	}

	public function getClientTrackMinutesHistory( $client_id ) {
		$params              = array();
		$params['action']    = 'client_get_track_minutes_history';
		$params['client_id'] = $client_id;
		return $this->call( $params );
	}

	public function getClientCoursesInstallmentsHistory( $client_id ) {
		$params              = array();
		$params['action']    = 'client_get_courses_installments_history';
		$params['client_id'] = $client_id;
		return $this->call( $params );
	}

	public function getClientPowerPlatesHistory( $client_id ) {
		$params              = array();
		$params['action']    = 'client_get_power_plates_history';
		$params['client_id'] = $client_id;
		return $this->call( $params );
	}

	public function getClientTanningHistory( $client_id ) {
		$params              = array();
		$params['action']    = 'client_get_tanning_history';
		$params['client_id'] = $client_id;
		return $this->call( $params );
	}

	public function getClientTreatmentRecords( $client_id ) {
		$params              = array();
		$params['action']    = 'client_get_treatment_records';
		$params['client_id'] = $client_id;
		return $this->call( $params );
	}

	public function reqOnlineBookingAuthToken( $client_id, $expires = 120 ) {
		$params              = array();
		$params['action']    = 'client_req_online_booking_auth';
		$params['client_id'] = $client_id;
		$params['expires']   = $expires;
		return $this->call( $params );
	}

	public function addLead( $data ) {
		$params           = array();
		$params['action'] = 'add_lead';
		$params['data']   = json_encode( $data );
		return $this->call( $params );
	}

	public function readLog() {
		if ( ! file_exists( $this->log_filename ) ) {
			return '';
		}

		$fh = fopen( $this->log_filename, 'r' );
		if ( false === $fh ) {
			return '';
		}

		$contents = fread( $fh, filesize( $this->log_filename ) );
		fclose( $fh );

		return $contents;
	}

	private function writeLog( $message ) {
		// error_log("Api Curl Log: $message"); // for debugging: daniel

		if ( ! file_exists( $this->log_filename ) ) {
			return;
		}

		$fh = fopen( $this->log_filename, 'a' );
		if ( false === $fh ) {
			return;
		}

		fwrite( $fh, "{$message}\n\n" );
		fclose( $fh );
	}

	private function clearLog() {
		if ( ! file_exists( $this->log_filename ) ) {
			return;
		}

		$fh = fopen( $this->log_filename, 'w' );
		if ( false === $fh ) {
			return;
		}

		fclose( $fh );
	}
}
