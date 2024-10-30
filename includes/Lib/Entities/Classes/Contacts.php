<?php

namespace ClinicSoftware\Lib\Entities\Classes;

use ClinicSoftware\Lib\ActionManager;
use ClinicSoftware\Lib\Entities\Contracts\ExportableContract;
use ClinicSoftware\Lib\Entities\Contracts\ExportableInBatchesContract;
use ClinicSoftware\Lib\Entities\Contracts\ImportableContract;
use ClinicSoftware\Lib\Entities\Contracts\ImportableInBatchesContract;
use ClinicSoftware\Lib\Entities\Contracts\ScheduleableContract;
use ClinicSoftware\Lib\Entities\Traits\CanSchedule;
use ClinicSoftware\Lib\Entities\Traits\HasOptions;
use ClinicSoftware\Lib\HelperFunctions;
use Exception as GlobalException;
use WC_Customer;
use WC_Logger;
use WP_Error;

class Contacts implements ImportableContract, ScheduleableContract, ExportableContract, ExportableInBatchesContract, ImportableInBatchesContract {

	use HasOptions;
	use CanSchedule;

	public const SCHEDULE_ID        = 'cswoo_download_contacts';
	public const EXPORT_SCHEDULE_ID = 'cswoo_export_contacts';
	public const IMPORT_META_KEY    = 'cs_import_client_id';
	protected $batchSize            = 50;
	private $options                = array(
		'limit'            => 'cswoo_contacts_import_limit',
		'lastDate'         => 'cswoo_contacts_import_last_date',
		'offset'           => 'cswoo_contacts_import_offset',
		'processing'       => 'cswoo_contacts_import_processing',
		'processId'        => 'cswoo_contacts_import_process_id',
		'finished'         => 'cswoo_contacts_import_finished',
		'duplicates'       => 'cswoo_contacts_importable_duplicates',
		'exportCount'      => 'cswoo_contacts_exportable_count',
		'exportLastIndex'  => 'cswoo_contacts_exportable_last_index',
		'exportLastDate'   => 'cswoo_contacts_exportable_last_date',
		'exportProcessing' => 'cswoo_contacts_exportable_processing_export',
	);

	private $actionManager;
	public function __construct() {
		$this->actionManager = ActionManager::instance();
	}
	protected function getClinicSoftwareApi() {
		return $this->actionManager->getClinicSoftwareApi();
	}
	public function getScheduleId(): string {
		return self::SCHEDULE_ID;
	}
	public function getExportScheduleId(): string {
		return self::EXPORT_SCHEDULE_ID;
	}
	public function getImportMetaKey(): string {
		return self::IMPORT_META_KEY;
	}
	public function getBatchSize(): int {
		return $this->batchSize;
	}
	public static function initExports() {
		return ( new self() )->exportInBatches();
	}

	public static function initImports() {
		return ( new self() )->importInBatches();
	}

	public function importInBatches() {
		update_option( $this->options['duplicates'], null );

		if ( ! wp_next_scheduled( $this->getScheduleId() ) ) {
			$this->initiateSycnSchedule();
		}

		if ( ! wp_next_scheduled( $this->getScheduleId() ) ) {
			new WP_Error( 'contact_import_error', 'Error setting imports scheduler for contacts' );
		} else {
			$this->showScheduleMessage();
		}
	}

	private function extractIdsFromMeta( $objectsArray, $meta_key ) {
		$meta_values_array = array();
		foreach ( $objectsArray as $obj ) {
			$meta_value = get_user_meta( $obj->ID, $meta_key, true );
			if ( isset( $meta_value ) ) {
				$meta_values_array[] = $meta_value;
			}
		}
		return $meta_values_array;
	}

	public function handleSchedule() {
		$processing = get_option( $this->options['processing'], false );
		if ( ! $processing ) {
			return;
		}
		$this->loadEntityOptions();

		$batchSize = $this->getBatchSize();

		error_log( '[Schedule Log] - contacts imports runner called at - ' . gmdate( 'Y-m-d H:i:s' ) . "| Batch Size: $batchSize" );

		$duplicates = get_option( $this->options['duplicates'], null );
		if ( empty( $duplicates ) && ! is_array( $duplicates ) ) {
			$users      = get_users();
			$duplicates = $this->extractIdsFromMeta( $users, $this->getImportMetaKey() );
			update_option( $this->options['duplicates'], $duplicates );
		}

		$params = $this->getCurrentParams( $batchSize );

		$limit    = $params['limit'];
		$offset   = $params['offset'];
		$lastDate = $params['lastDate'];

		error_log( "[Schedule Log] - Query Params for contacts - limit: $limit | offset: $offset | lastDate: $lastDate" );
		$this->import( $limit, $offset, $lastDate, $duplicates );

		$this->updateOptions( $batchSize, $offset );
	}

	public function import( $limit, $offset, $lastDate, $duplicates ) {
		return $this->downloadContacts( $limit, $offset, $lastDate, $duplicates );
	}

	public function downloadContacts( $limit, $offset, $lastDate, $duplicates ) {
		$ClinicSoftwareApi = $this->getClinicSoftwareApi();
		$l                 = new WC_Logger();

		$customersStats = array(
			'created' => 0,
			'updated' => 0,
		);

		// $clients = $ClinicSoftwareApi->getClients($lastDate, $limit, $offset) ?? [];
		$clients = $ClinicSoftwareApi->getClients( '', $limit, $offset ) ?? array(); // using $lastDate parameter limits users query by returning only users modified after the set date

		$clientsCount    = count( $clients );
		$duplicatesCount = count( $duplicates );
		error_log( "duplicates count: $duplicatesCount" );
		error_log( "clients count: $clientsCount" );
		if ( $clientsCount < 1 ) {
			$date = gmdate( 'Y-m-d H:i:s' );
			$this->cancelSyncSchedule();
			error_log( "[Finished Sync] | [$date] - No more clients to import" );
		}

		foreach ( $clients as $c ) {
			if ( ! $this->userIsValid( $c ) ) {
				error_log( 'Tried adding Invalid user' );
				continue;
			}

			$identifierValue = $this->generateUserEmail( $c );

			if ( empty( $identifierValue ) ) {
				$this->actionManager->debug( 'Customer ' . $c['name'] . ' has no valid email or phone!', 'error', 'Import Clients' );
				continue;
			}

			if ( ! $this->validateUserEmail( $identifierValue ) ) {
				$l->error( 'Invalid email address for imported ClinicSoftware Customer', $c );
				continue;
			}

			$existingUser = get_user_by( 'email', $identifierValue );

			if ( is_object( $existingUser ) && isset( $existingUser->ID ) ) {
				$customerInstance = new WC_Customer( $existingUser->ID, false );
				++$customersStats['updated'];
			} elseif ( ! $this->userAlreadyExists( $duplicates, $c ) ) {
					$customerInstance = new WC_Customer( 0, false );
					++$customersStats['created'];
			} else {
				$user_query       = get_users(
					array(
						'meta_query' => array(
							array(
								'key'     => $this->getImportMetaKey(),
								'value'   => $c['id'],
								'compare' => '=',
							),
						),
					)
				);
				$customerInstance = new WC_Customer( isset( $user_query[0] ) ? $user_query[0]->ID : null, false );
				++$customersStats['updated'];
			}

			$this->updateCustomerInformation( $customerInstance, $c, $identifierValue );
			$this->saveCustomerInstance( $customerInstance, $c, $customersStats );
		}

		$this->updateLastImportSyncDate();
		$this->triggerAdminNotice();
	}

	public function userIsValid( $user = array() ) {
		if ( ! $user || empty( $user ) ) {
			return false;
		}
		$user = (object) $user;
		if ( ! isset( $user->name ) || ! isset( $user->id ) ) {
			return false;
		}
		return true;
	}
	public function userAlreadyExists( $alreadyImportedUsers, $user ) {
		return in_array( $user['id'], $alreadyImportedUsers );
	}

	private function generateUserEmail( $c ) {
		if ( empty( $c['email'] ) ) {
			if ( ! empty( $c['phone'] ) ) {
				$cleanedPhoneNumber = HelperFunctions::cleanPhoneNumber( $c['phone'] );
				return $cleanedPhoneNumber . '@no-email.com';
			}
			return '';
		} else {
			return $c['email'];
		}
	}
	public function handleExportSchedule() {
		$processing = get_option( $this->options['exportProcessing'], false );

		if ( ! $processing ) {
			return;
		}
		$this->loadEntityOptions();

		$batchSize = $this->getBatchSize();

		error_log( '[Schedule Log] - contacts exports runner called at - ' . gmdate( 'Y-m-d H:i:s' ) . "| Batch Size: $batchSize" );

		$count     = get_option( $this->options['exportCount'], 0 );
		$lastIndex = get_option( $this->options['exportLastIndex'], 0 );
		$lastDate  = get_option( $this->options['exportLastDate'], 0 );

		error_log( "[Schedule Log] - Query Params for contacts - ojects: $count | index: $lastIndex | lastDate: $lastDate" );
		$this->export( $count, $lastIndex, $batchSize, $lastDate );

		$this->updateExportOptions( $batchSize, $lastIndex );
	}

	public function exportInBatches() {
		if ( ! wp_next_scheduled( $this->getExportScheduleId() ) ) {
			$this->initiateExportSycnSchedule();
		}

		if ( ! wp_next_scheduled( $this->getExportScheduleId() ) ) {
			new WP_Error( 'contact_import_error', 'Error setting exports scheduler for contacts' );
		} else {
			$this->showExportScheduleMessage();
		}
	}
	public function export( $offset, $limit, $lastDate ) {
		return $this->exportContacts( $limit, $offset, $lastDate );
	}
	private function exportContacts( $limit, $offset, $lastDate ) {
		$exportableCount = get_option( $this->options['exportCount'], 0 );
		$lastIndex       = get_option( $this->options['exportLastIndex'], null );

		if ( ( $exportableCount ?? 0 ) < ( $lastIndex ?? 1 ) ) {
			$date = gmdate( 'Y-m-d H:i:s' );
			$this->cancelExportSyncSchedule();
			error_log( "[Finished Sync] | [$date] - No more clients to export" );
		}

		$api = $this->getClinicSoftwareApi();
		$api->setURL( $this->actionManager->getOptions()['api_client_url'] );
		$api->setDebug( true );

		$users = get_users();
		$array = json_decode( json_encode( $users ), true );

		update_option( $this->options['exportCount'], count( $array ) );

		$startIndex = $offset ?? 0;
		$endIndex   = $offset + $limit;

		$currentChunk = array_slice( $array, $startIndex, $endIndex );

		$allUsers   = count( $array );
		$chunkCount = count( $currentChunk );
		error_log( "all: count: $allUsers" );
		error_log( "chunk: count: $chunkCount" );

		foreach ( $currentChunk as $key => $value ) {

			$ar = get_post_meta( $value['data']['ID'] );
			if ( empty( $arr['cs_client_id'][0] ) ) {

				$data                    = array();
				$data['name']            = $value['data']['display_name']; // mandatory
				$data['surname']         = $value['data']['display_name'];
				$data['postcode']        = '';
				$data['address']         = '';
				$data['phone']           = '';
				$data['phone_work']      = '';
				$data['email']           = $value['data']['user_email']; // mandatory, must be valid and unique
				$data['password']        = $value['data']['user_pass']; // mandatory, plain password containing at least 4 characters (not counting spaces), hashed on server side
				$data['sex']             = 'not_set'; // gender, possible values: 'm', 'f', 'not_set', defaults to 'not_set'
				$data['dob']             = $value['data']['user_registered']; // YYYY-MM-DD format
				$data['discount_value']  = ''; // float, global client discount
				$data['notes']           = 'test notes';
				$data['salon_id']        = 0;  // defaults to first salon found if not specified or 0
				$data['courses_barcode'] = ''; // valid and unique EAN8/EAN13 barcode, generated automatically if not specified

				$result = $api->addClient( $data );
				$status = $api->getLastStatus();
				// header('Content-Type: text/plain; charset=UTF-8');

				if ( ! empty( $result ) ) {
					$error = $api->getLastError();
					add_post_meta( $value['data']['ID'], 'cs_client_id', $result['client_id'] );
					$this->debug( 'The ' . $value['data']['display_name'] . ' contact was exported!', 'success', 'Export Contacts' );
				}

				if ( ! empty( $error ) ) {
					$this->debug( 'The ' . $value['data']['display_name'] . ' contact was not exported!', 'error', 'Export Contacts' );
				}
				// echo "\n\n" . $api->readLog();
			}
		}
		update_option( PLUGIN_SLUG . '_last_contacts_export_sync', gmdate( 'Y-m-d H:i:s' ) );

		add_action( 'admin_notices', array( $this, 'my_update_notice_export_contacts' ) );
	}
	public function validUsername( $value ) {
		return HelperFunctions::cleanUsername( $value );
	}
	private function validateUserEmail( $value ) {
		return filter_var( $value, FILTER_VALIDATE_EMAIL );
	}
	private function updateCustomerInformation( $customerInstance, $c, $value ) {
		$customerInstance->set_email( $value );
		$customerInstance->set_display_name( $c['name'] . ' ' . $c['surname'] );
		$customerInstance->set_username( $this->validUsername( $value ) );
		$customerInstance->set_password( md5( uniqid() ) );
		$customerInstance->set_first_name( $c['name'] ?? '' );
		$customerInstance->set_last_name( $c['surname'] ?? '' );
		$customerInstance->set_date_created( $c['create_date'] ?? null );
		$customerInstance->set_is_paying_customer( 1 );
		$customerInstance->update_meta_data( $this->getImportMetaKey(), $c['id'] );
		// $customerInstance->set_meta_data([$this->getImportMetaKey() => $c['id']]);
	}

	private function saveCustomerInstance( $customerInstance, $c, &$customersStats ) {
		$full_name = ( $c['name'] ?? '' ) . ' ' . ( $c['surname'] ?? '' );
		try {
			$customerInstance->save();
			$this->actionManager->debug( 'Contact ' . $full_name . ' has been successfully saved', 'success', 'Import Contacts' );
		} catch ( GlobalException $exception ) {
			$this->actionManager->debug( 'Contact ' . $full_name . "could not be saved! [Error] - {$exception}", 'error', 'Import Contacts' );
		}
	}

	private function updateLastImportSyncDate() {
		update_option( PLUGIN_SLUG . '_last_contacts_import_sync', gmdate( 'Y-m-d H:i:s' ) );
	}

	private function triggerAdminNotice() {
		add_action( 'admin_notices', array( $this->actionManager, 'my_update_notice_contacts' ) );
	}
}
