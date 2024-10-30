<?php

namespace ClinicSoftware\Lib\Entities\Classes;

use ClinicSoftware\Lib\ActionManager;
use ClinicSoftware\Lib\Entities\Contracts\ExportableContract;
use ClinicSoftware\Lib\Entities\Contracts\ExportableInBatchesContract;
use ClinicSoftware\Lib\Entities\Traits\HasOptions;
use ClinicSoftware\Lib\Entities\Contracts\ImportableContract;
use ClinicSoftware\Lib\Entities\Contracts\ImportableInBatchesContract;
use ClinicSoftware\Lib\Entities\Contracts\ScheduleableContract;
use ClinicSoftware\Lib\Entities\Traits\CanSchedule;
use Exception as GlobalException;
use WC_Coupon;
use WP_Error;

class Coupons implements ImportableContract, ScheduleableContract, ExportableContract, ExportableInBatchesContract, ImportableInBatchesContract {

	use HasOptions;
	use CanSchedule;

	public const SCHEDULE_ID        = 'cswoo_download_coupons';
	public const EXPORT_SCHEDULE_ID = 'cswoo_export_coupons';
	public const IMPORT_META_KEY    = 'cs_coupon_import_id';
	public const EXPORT_META_KEY    = 'cs_coupon_export_id';
	private $batchSize              = 150;
	private $exportBatchSize        = 50;
	private $options                = array(
		'limit'            => 'cswoo_coupons_import_limit',
		'lastDate'         => 'cswoo_coupons_import_last_date',
		'offset'           => 'cswoo_coupons_import_offset',
		'processing'       => 'cswoo_coupons_import_processing',
		'processId'        => 'cswoo_coupons_import_process_id',
		'finished'         => 'cswoo_coupons_import_finished',
		'duplicates'       => 'cswoo_coupons_import_duplicates',
		'exportCount'      => 'cswoo_coupons_exportable_count',
		'exportLastIndex'  => 'cswoo_coupons_exportable_last_index',
		'exportLastDate'   => 'cswoo_coupons_exportable_last_date',
		'exportProcessing' => 'cswoo_coupons_exportable_processing_export',
	);

	public $recurrence = 'every_2_minutes';
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
	public function getExportMetaKey(): string {
		return self::EXPORT_META_KEY;
	}
	public function getBatchSize(): int {
		return $this->batchSize;
	}
	public function getExportBatchSize(): int {
		return $this->exportBatchSize;
	}

	public static function initImports() {
		return ( new self() )->importInBatches();
	}
	public static function initExports() {
		return ( new self() )->exportInBatches();
	}

	public function importInBatches() {
		update_option( $this->options['duplicates'], null );

		$this->cancelSyncSchedule();
		if ( ! wp_next_scheduled( $this->getScheduleId() ) ) {
			$this->initiateSycnSchedule();
		}

		if ( ! wp_next_scheduled( $this->getScheduleId() ) ) {
			new WP_Error( 'coupons_import_error', 'Error setting imports scheduler for coupons' );
		} else {
			$this->showScheduleMessage();
		}
	}

	public function getCouponsMeta( $coupons ) {
		$duplicates = array();
		foreach ( $coupons as $val ) {
			$i = get_post_meta( $val->ID );

			if ( isset( $i[ $this->getImportMetaKey() ][0] ) ) {
				if ( ! in_array( $i[ $this->getImportMetaKey() ][0], $duplicates ) ) {
					array_push( $duplicates, $i[ $this->getImportMetaKey() ][0] );
				}
			}
		}
		return $duplicates;
	}
	public function handleSchedule() {
		$this->loadEntityOptions();

		$batchSize = $this->getBatchSize();

		error_log( 'coupons imports runner called at - ' . gmdate( 'Y-m-d H:i:s' ) . "| Batch Size: $batchSize" );

		$duplicates = get_option( $this->options['duplicates'], null );
		if ( empty( $duplicates ) && ! is_array( $duplicates ) ) {
			$coupon_posts = get_posts(
				array(
					'posts_per_page' => -1,
					'orderby'        => 'name',
					'order'          => 'asc',
					'post_type'      => 'shop_coupon',
				)
			);

			$duplicates = $this->getCouponsMeta( $coupon_posts );
			update_option( $this->options['duplicates'], $duplicates );
		}

		$params = $this->getCurrentParams( $batchSize );

		$limit    = $params['limit'] ?? $batchSize;
		$offset   = $params['offset'];
		$lastDate = $params['lastDate'];

		$this->import( $limit, $offset, $lastDate, $duplicates );

		error_log( "Query Params - limit: $limit | offset: $offset | lastDate: $lastDate" );

		$this->updateOptions( $batchSize, $offset );
	}

	public function import( $limit, $offset, $lastDate, $duplicate ) {
		return $this->downloadCoupons( $limit, $offset, $lastDate, $duplicate );
	}

	public function downloadCoupons( int $limit, int $offset, string $lastDate, array $duplicates ) {
		$ClinicSoftwareApi = $this->getClinicSoftwareApi();

		$coupons = $ClinicSoftwareApi->getCoupons(
			// $lastDate,
			'',
			$limit,
			$offset
		) ?? array();

		$couponsCount    = count( $coupons );
		$duplicatesCount = count( $duplicates );
		error_log( "duplicates count: $duplicatesCount" );
		error_log( "coupons count: $couponsCount" );

		if ( $couponsCount < 1 ) {
			$date = gmdate( 'Y-m-d H:i:s' );
			$this->cancelSyncSchedule();
			error_log( "[Finished Sync] | [$date] - No more coupons to import" );
			return;
		}

		foreach ( $coupons as  $value ) {
			$coupon = new WC_Coupon();

			$id = get_post_meta( $value['id'] );
			if ( empty( $id[ $this->getImportMetaKey() ][0] ) && ! in_array( $value['id'], $duplicates ) ) {
				$coupon->set_code( $value['code'] );
				$coupon->set_individual_use( $value['is_single_use'] );
				if ( 'percentage' === $value['type'] ) {
					$coupon->set_discount_type( 'percent' );
				} else {
					$coupon->set_discount_type( 'fixed_cart' );
				}

				$coupon->set_date_created( $value['date_added'] );
				$coupon->set_date_expires( $value['available_to'] );

				if ( $value['value'] <= 100.00 ) {
					$coupon->set_amount( $value['value'] );
				} else {
					$this->actionManager->debug( 'Coupon ' . $value['code'] . ' has a discount greater than 100!', 'error', 'Import Coupons' );
				}
				try {
					$coupon->save();
					update_post_meta( $value['id'], $this->getImportMetaKey(), $value['id'] );
					$this->actionManager->debug( 'Coupon ' . $value['code'] . ' has been saved successfully!', 'success', 'Import Coupons' );
				} catch ( GlobalException $exception ) {
					$this->actionManager->debug( 'Coupon ' . $value['code'] . "could not be saved! [Error] - {$exception}", 'error', 'Import Coupons' );
				}
			}
		}

		$this->updateLastImportSyncDate();
		$this->triggerAdminNotice();
	}

	public function handleExportSchedule() {
		$processing = get_option( $this->options['exportProcessing'], false );

		if ( ! $processing ) {
			return;
		}
		$this->loadEntityOptions();

		$batchSize = $this->getExportBatchSize();

		error_log( '[Schedule Log] - coupons exports runner called at - ' . gmdate( 'Y-m-d H:i:s' ) . "| Batch Size: $batchSize" );

		$count     = get_option( $this->options['exportCount'], 0 );
		$lastIndex = get_option( $this->options['exportLastIndex'], 0 );
		$lastDate  = get_option( $this->options['exportLastDate'], 0 );

		error_log( "[Schedule Log] - Query Params for coupons - ojects: $count | index: $lastIndex | lastDate: $lastDate" );
		$this->export( $lastIndex, $batchSize, $lastDate );

		$this->updateExportOptions( $batchSize, $lastIndex );
	}

	public function exportInBatches() {
		if ( ! wp_next_scheduled( $this->getExportScheduleId() ) ) {
			$this->initiateExportSycnSchedule();
		}

		if ( ! wp_next_scheduled( $this->getExportScheduleId() ) ) {
			new WP_Error( 'coupon_export_error', 'Error setting exports scheduler for coupons' );
		} else {
			$this->showExportScheduleMessage();
		}
	}
	public function export( $limit, $offset, $lastDate ) {
		return $this->exportCoupons( $limit, $offset, $lastDate );
	}
	private function exportCoupons( $limit, $offset, $lastDate ) {
		$api = $this->getClinicSoftwareApi();

		$coupon_posts = get_posts(
			array(
				'posts_per_page' => -1,
				'orderby'        => 'name',
				'order'          => 'asc',
				'post_type'      => 'shop_coupon',
			)
		);

		$exCount = count( $coupon_posts );

		update_option( $this->options['exportCount'], $exCount );

		$exportableCount = get_option( $this->options['exportCount'], 0 );
		$lastIndex       = get_option( $this->options['exportLastIndex'], null );

		error_log( "Exportable - $exCount" );
		if ( ( $exportableCount ?? 0 ) < ( $lastIndex ?? 1 ) ) {
			$date = gmdate( 'Y-m-d H:i:s' );
			$this->cancelExportSyncSchedule();
			error_log( "[Finished Sync] | [$date] - No more coupons to export" );
		}

		$startIndex = $offset ?? 0;
		$endIndex   = $offset + $limit;

		$currentChunk = array_slice( $coupon_posts, $startIndex, $endIndex );

		$allCoupons = count( $coupon_posts );
		$chunkCount = count( $currentChunk );
		error_log( "all: count: $allCoupons" );
		error_log( "chunk: count: $chunkCount" );

		foreach ( $currentChunk as $key => $value ) {

			$id = get_post_meta( $value->ID );
			if ( empty( $id[ $this->getExportMetaKey() ][0] ) ) {

				$data         = array();
				$data['code'] = $value->post_title;

				if ( ( 'percent' === $id['discount_type'][0] ) ?? null ) {
					$data['type'] = 'percentage';
				} else {
					$data['type'] = $id['discount_type'][0] ?? '';
				}

				$data['value'] = number_format( $id['coupon_amount'][0] ?? 0, 2 );

				if ( isset( $id['date_expires'][0] ) ) {
					$data['available_to'] = gmdate( 'Y-m-d', $id['date_expires'][0] );
				} else {
					$data['available_to'] = null;
				}

				$data['available_from'] = null;

				if ( 'no' === $id['individual_use'][0] ?? '' ) {
					$data['is_single_use'] = 0;
				} else {
					$data['is_single_use'] = 1;
				}

				$result = $api->addCoupons( $data );
				$status = $api->getLastStatus();

				header( 'Content-Type: text/plain; charset=UTF-8' );
				if ( ! empty( $result ) ) {
					update_post_meta( $value->ID, $this->getExportMetaKey(), $result['new_coupon_id'] );
				} else {
					$this->actionManager->debug( 'Coupon ' . $value->post_title . ' already exists!', 'error', 'Export Coupons' );
				}
			}
		}
		update_option( PLUGIN_SLUG . '_last_coupons_export_sync', gmdate( 'Y-m-d H:i:s' ) );
		add_action( 'admin_notices', array( $this, 'my_update_notice_export_coupons' ) );
	}
	private function updateLastImportSyncDate() {
		update_option( PLUGIN_SLUG . '_last_coupons_import_sync', gmdate( 'Y-m-d H:i:s' ) );
	}

	private function triggerAdminNotice() {
		add_action( 'admin_notices', array( $this, 'my_update_notice_coupons' ) );
	}
}
