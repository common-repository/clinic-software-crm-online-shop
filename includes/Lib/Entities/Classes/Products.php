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
use PHPMailer\PHPMailer\Exception;
use WC_Product;
use WP_Error;

class Products implements ImportableContract, ScheduleableContract, ExportableContract, ExportableInBatchesContract, ImportableInBatchesContract {

	use HasOptions;
	use CanSchedule;

	public const SCHEDULE_ID        = 'cswoo_download_products';
	public const EXPORT_SCHEDULE_ID = 'cswoo_export_products';
	public const IMPORT_META_KEY    = 'cs_product_import_id';
	public const EXPORT_META_KEY    = 'cs_product_export_id';
	private $batchSize              = 150;
	private $exportBatchSize        = 50;
	private $options                = array(
		'limit'            => 'cswoo_products_import_limit',
		'lastDate'         => 'cswoo_products_import_last_date',
		'offset'           => 'cswoo_products_import_offset',
		'processing'       => 'cswoo_products_import_processing',
		'processId'        => 'cswoo_products_import_process_id',
		'finished'         => 'cswoo_products_import_finished',
		'duplicates'       => 'cswoo_products_import_duplicates',
		'exportCount'      => 'cswoo_products_exportable_count',
		'exportLastIndex'  => 'cswoo_products_exportable_last_index',
		'exportLastDate'   => 'cswoo_products_exportable_last_date',
		'exportProcessing' => 'cswoo_products_exportable_processing_export',
	);

	private $actionManager;
	public $recurrence = 'every_2_minutes';
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
			new WP_Error( 'products_import_error', 'Error setting imports scheduler for products' );
		} else {
			$this->showScheduleMessage();
		}
	}

	public function getProductsMeta( $products ) {
		$duplicates = array();
		foreach ( $products as $val ) {
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

		error_log( 'import products runner called at - ' . gmdate( 'Y-m-d H:i:s' ) . "| Batch Size: $batchSize" );

		$duplicates = get_option( $this->options['duplicates'], null );
		if ( empty( $duplicates ) && ! is_array( $duplicates ) ) {
			$existingProducts = wc_get_products(
				array(
					'status' => 'publish',
					'limit'  => -1,
				)
			) ?? array();

			$duplicates = $this->getProductsMeta( $existingProducts );
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

	public function import( $limit, $offset, $lastDate, $duplicates ) {
		return $this->downloadProducts( $limit, $offset, $lastDate, $duplicates );
	}

	public function downloadProducts( int $limit, int $offset, string $lastDate, array $duplicates ) {
		$ClinicSoftwareApi = $this->getClinicSoftwareApi();

		$services = $ClinicSoftwareApi->get_services(
			// $lastDate,
			null,
			'',
			$limit,
			$offset
		) ?? array();

		$products = array();
		foreach ( $services as $key => $value ) {
			if ( 'prod' === ( $value['section_type'] ?? null ) ) {
				array_push( $products, $value );
			}
		}
		$servicesCount   = count( $services );
		$productsCount   = count( $products );
		$duplicatesCount = count( $duplicates );
		error_log( "duplicates count: $duplicatesCount" );
		error_log( "services count: $servicesCount" );
		error_log( "products count: $productsCount" );

		if ( $servicesCount < 1 ) {
			$date = gmdate( 'Y-m-d H:i:s' );
			$this->cancelSyncSchedule();
			error_log( "[Finished Sync] | [$date] - No more products to import" );
			return;
		}
		foreach ( $products as $prod ) {

			if ( ! isset( $prod['id'] ) ) {
				return;
			}

			if ( ( ! in_array( $prod['id'], $duplicates ) ) && empty( $id['cs_import_id'][0] ?? '' ) ) {
				$category_ids = array();
				$term         = term_exists( $prod['product_category'], 'product_cat', null );

				if ( 0 !== $term && null !== $term ) {
					if ( ! empty( $prod['product_category_id'] ) ) {
						$category_ids[] = $prod['product_category_id'];
					}
				} else {
					$id = wp_insert_term(
						$prod['product_category'],
						'product_cat',
						array(
							'slug' => $prod['product_category'],
						)
					);
					if ( ! is_wp_error( $id ) ) {
						$category_ids[] = $id['term_id'];
					}
					if ( empty( $prod['product_category'] ) ) {
						$this->actionManager->debug( 'Category ' . $prod['product_category'] . ' has been created!', 'success', 'Import Products' );
					}
				}

				$product = new WC_Product();

				// somehow short_description != null
				if ( empty( $prod['online_description'] ) ) {
					$prod['online_description'] = '-';
				}
				// tax_value is percent like vat
				$price = $prod['price'] + ( $prod['tax_value'] / 100 * $prod['price'] );

				if ( empty( $prod['price'] ) ) {
					$this->actionManager->debug( 'Product ' . $prod['title'] . ' has no price!', 'warning', 'Import Products' );
				}
				if ( empty( $prod['description'] ) ) {
					$this->actionManager->debug( 'Product ' . $prod['title'] . ' has no description!', 'warning', 'Import Products' );
				}
				if ( empty( $prod['stock'] ) ) {
					$this->actionManager->debug( 'Product ' . $prod['title'] . ' has no stock!', 'warning', 'Import Products' );
				}
				// here set all the remaining fields
				// no meta update for cs_id !!!
				$product->set_name( $prod['title'] );
				$product->set_price( $price );
				// $product->set_stock($prod['stock']); // deprecated !!! please make sure when you use this methods that are not deprecated.
				wc_update_product_stock( $product->get_id(), $prod['stock'] );
				$product->set_description( $prod['description'] );
				$product->set_category_ids( $category_ids );
				$product->set_short_description( $prod['online_description'] );
				unset( $category_ids );
				$price = 0;
				try {
					update_post_meta( $prod['id'], 'cs_import_id', $prod['id'] );
					$product->save();
					$this->actionManager->debug( 'Product ' . $prod['title'] . ' has been saved successfully!', 'success', 'Import Products' );
				} catch ( GlobalException $exception ) {
					$this->actionManager->debug( 'Product ' . $prod['title'] . "could not be saved! [Error] - {$exception}", 'error', 'Import Products' );
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

		error_log( '[Schedule Log] - products exports runner called at - ' . gmdate( 'Y-m-d H:i:s' ) . "| Batch Size: $batchSize" );

		$count     = get_option( $this->options['exportCount'], 0 );
		$lastIndex = get_option( $this->options['exportLastIndex'], 0 );
		$lastDate  = get_option( $this->options['exportLastDate'], 0 );

		error_log( "[Schedule Log] - Query Params for products - ojects: $count | index: $lastIndex | lastDate: $lastDate" );
		$this->export( $lastIndex, $batchSize, $lastDate );

		$this->updateExportOptions( $batchSize, $lastIndex );
	}

	public function exportInBatches() {
		if ( ! wp_next_scheduled( $this->getExportScheduleId() ) ) {
			$this->initiateExportSycnSchedule();
		}

		if ( ! wp_next_scheduled( $this->getExportScheduleId() ) ) {
			new WP_Error( 'product_export_error', 'Error setting exports scheduler for products' );
		} else {
			$this->showExportScheduleMessage();
		}
	}
	public function export( $limit, $offset, $lastDate ) {
		return $this->exportProducts( $limit, $offset, $lastDate );
	}
	private function exportProducts( $limit, $offset, $lastDate ) {
		$api = $this->getClinicSoftwareApi();

		$wcProducts = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => -1,
			)
		);

		$exCount = count( $wcProducts );

		update_option( $this->options['exportCount'], $exCount );

		$exportableCount = get_option( $this->options['exportCount'], 0 );
		$lastIndex       = get_option( $this->options['exportLastIndex'], null );

		error_log( "Exportable - $exCount" );
		if ( ( $exportableCount ?? 0 ) < ( $lastIndex ?? 1 ) ) {
			$date = gmdate( 'Y-m-d H:i:s' );
			$this->cancelExportSyncSchedule();
			error_log( "[Finished Sync] | [$date] - No more products to export" );
		}

		$startIndex = $offset ?? 0;
		$endIndex   = $offset + $limit;

		$currentChunk = array_slice( $wcProducts, $startIndex, $endIndex );

		$allProducts = count( $wcProducts );
		$chunkCount  = count( $currentChunk );
		error_log( "all: count: $allProducts" );
		error_log( "chunk: count: $chunkCount" );

		foreach ( $currentChunk as $product ) {
			$id = get_post_meta( $product->get_id() );
			if ( empty( $id[ $this->getImportMetaKey() ] ) ) {
				$data          = array();
				$data['title'] = $product->get_title();
				$data['price'] = $product->get_price();
				$data['desc']  = $product->get_description();

				$getCategories = $product->get_category_ids();
				if ( ! empty( $getCategories ) ) {
					$data['category_id'] = $getCategories[0];
				} else {
					$data['category_id'] = 1;
				}

				$data['barcode']   = $product->get_sku();
				$data['unit_size'] = $product->get_stock_quantity();
				$data['sku']       = $product->get_sku();
				$data['max_stock'] = $product->get_stock_quantity();

				$result = $api->addServices( $data );
				$status = $api->getLastStatus();

				// header('Content-Type: text/plain; charset=UTF-8');
				if ( ! empty( $result ) ) {
					update_post_meta( $product->get_id(), $this->getImportMetaKey(), $result['new_product_id'] );
					$this->actionManager->debug( 'Product ' . $product->get_title() . ' has been exported!', 'success', 'Export Products' );
				} else {
					$this->actionManager->debug( 'Product ' . $product->get_title() . ' has no saved!', 'error', 'Export Products' );
				}
			}
		}
		update_option( PLUGIN_SLUG . '_last_services_export_sync', gmdate( 'Y-m-d H:i:s' ) );
		add_action( 'admin_notices', array( $this, 'my_update_notice_export_products' ) );
	}
	private function updateLastImportSyncDate() {
		update_option( PLUGIN_SLUG . '_last_services_import_sync', gmdate( 'Y-m-d H:i:s' ) );
	}

	private function triggerAdminNotice() {
		add_action( 'admin_notices', array( $this, 'my_update_notice_products' ) );
	}
}
