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
use WP_Error;
use WC_Order;
use WC_Order_Query;
use WC_Order_Item_Shipping;

class Orders implements ImportableContract, ScheduleableContract, ExportableContract, ExportableInBatchesContract, ImportableInBatchesContract {

	use HasOptions;
	use CanSchedule;

	public const SCHEDULE_ID        = 'cswoo_download_orders';
	public const EXPORT_SCHEDULE_ID = 'cswoo_export_orders';
	public const IMPORT_META_KEY    = 'cs_order_import_id';
	private $batchSize              = 150;
	private $exportBatchSize        = 50;
	private $options                = array(
		'limit'            => 'cswoo_orders_import_limit',
		'lastDate'         => 'cswoo_orders_import_last_date',
		'offset'           => 'cswoo_orders_import_offset',
		'processing'       => 'cswoo_orders_import_processing',
		'processId'        => 'cswoo_orders_import_process_id',
		'finished'         => 'cswoo_orders_import_finished',
		'duplicates'       => 'cswoo_orders_import_duplicates',
		'exportCount'      => 'cswoo_orders_exportable_count',
		'exportLastIndex'  => 'cswoo_orders_exportable_last_index',
		'exportLastDate'   => 'cswoo_orders_exportable_last_date',
		'exportProcessing' => 'cswoo_orders_exportable_processing_export',
	);
	public $recurrence              = 'every_2_minutes';
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
			new WP_Error( 'orders_import_error', 'Error setting imports scheduler for orders' );
		} else {
			$this->showScheduleMessage();
		}
	}

	public function getOrdersMeta( $orders ) {
		$duplicates = array();
		foreach ( $orders as $val ) {
			$i = get_post_meta( $val->get_id() );

			if ( isset( $i['cs_order_id'][0] ) ) {
				if ( ! in_array( $i['cs_order_id'][0], $duplicates ) ) {
					array_push( $duplicates, $i['cs_order_id'][0] );
				}
			} elseif ( isset( $i[ $this->getImportMetaKey() ][0] ) ) {
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

		error_log( 'orders imports runner called at - ' . gmdate( 'Y-m-d H:i:s' ) . "| Batch Size: $batchSize" );

		$duplicates = get_option( $this->options['duplicates'], null );
		if ( empty( $duplicates ) && ! is_array( $duplicates ) ) {
			$query             = new WC_Order_Query(
				array(
					'limit'   => -1,
					'orderby' => 'date',
					'order'   => 'DESC',
				)
			);
			$wooCommerceOrders = $query->get_orders();

			$duplicates = $this->getOrdersMeta( $wooCommerceOrders );
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
		return $this->downloadOrders( $limit, $offset, $lastDate, $duplicate );
	}

	public function downloadOrders( int $limit, int $offset, string $lastDate, array $duplicates ) {
		$ClinicSoftwareApi = $this->getClinicSoftwareApi();

		$orders = $ClinicSoftwareApi->getOrders(
			// $lastDate,
			'',
			$limit,
			$offset
		) ?? array();

		$ordersCount     = count( $orders );
		$duplicatesCount = count( $duplicates );
		error_log( "duplicates count: $duplicatesCount" );
		error_log( "orders count: $ordersCount" );

		if ( $ordersCount < 1 ) {
			$date = gmdate( 'Y-m-d H:i:s' );
			$this->cancelSyncSchedule();
			error_log( "[Finished Sync] | [$date] - No more orders to import" );
			return;
		}

		$products_two = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => -1,
			)
		);

		foreach ( $orders as $o ) {
			$id = get_post_meta( $o['id'] );
			if ( empty( $id[ $this->getImportMetaKey() ][0] ) && ! in_array( $o['id'], $duplicates ) ) {

				$order = wc_create_order();
				foreach ( $o['items'] as $item ) {
					foreach ( $products_two  as $value ) {
						$id_prod = get_post_meta( $value->get_id() );
						if ( $id_prod['cs_id'][0] == $item['product_id'] ) {
							$order->add_product( wc_get_product( $value->get_id() ) );
						}
					}
				}

				$shipping = new WC_Order_Item_Shipping();
				$shipping->set_method_title( $o['shipping_tax_name'] );
				$shipping->set_method_id( $o['shipping_fee_id'] ); // set an existing Shipping method ID
				$shipping->set_total( $o['shipping_price'] ); // optional

				$order->add_item( $shipping );
				// add payment method
				$order->set_payment_method( $o['payment_method'] );

				// order status
				$order->set_status( $o['shipping_status'] );
				$order->calculate_totals();

				try {
					$order->save();
					$order->update_meta_data( 'cs_user_id', $o['client_id'] );
					update_post_meta( $o['id'], $this->getImportMetaKey(), $o['id'] );
					$this->actionManager->debug( 'Order ' . $o['id'] . ' has been created!', 'success', 'Import Orders' );
				} catch ( Exception $exception ) {
					$this->actionManager->debug( $exception->getMessage(), $o['id'], 'error', 'Import Orders' );
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

		error_log( '[Schedule Log] - orders exports runner called at - ' . gmdate( 'Y-m-d H:i:s' ) . "| Batch Size: $batchSize" );

		$count     = get_option( $this->options['exportCount'], 0 );
		$lastIndex = get_option( $this->options['exportLastIndex'], 0 );
		$lastDate  = get_option( $this->options['exportLastDate'], 0 );

		error_log( "[Schedule Log] - Query Params for orders - ojects: $count | index: $lastIndex | lastDate: $lastDate" );
		$this->export( $lastIndex, $batchSize, $lastDate );

		$this->updateExportOptions( $batchSize, $lastIndex );
	}

	public function exportInBatches() {
		if ( ! wp_next_scheduled( $this->getExportScheduleId() ) ) {
			$this->initiateExportSycnSchedule();
		}

		if ( ! wp_next_scheduled( $this->getExportScheduleId() ) ) {
			new WP_Error( 'order_export_error', 'Error setting exports scheduler for orders' );
		} else {
			$this->showExportScheduleMessage();
		}
	}
	public function export( $limit, $offset, $lastDate ) {
		return $this->exportOrders( $limit, $offset, $lastDate );
	}
	private function exportOrders( $limit, $offset, $lastDate ) {
		/*
			$exportableCount = get_option($this->options['exportCount'], 0);
		$lastIndex = get_option($this->options['exportLastIndex'], null);

		if (($exportableCount ?? 0) < ($lastIndex ?? 1)) {
			$date = gmdate('Y-m-d H:i:s');
			$this->cancelExportSyncSchedule();
			error_log("[Finished Sync] | [$date] - No more orders to export");
		} */

		$api = $this->getClinicSoftwareApi();

		$lastDate = get_option( PLUGIN_SLUG . '_last_orders_export_sync' );
		$lastDate = '';
		if ( empty( $lastDate ) ) {
			$lastDate = '';
		}
		if ( '' === $lastDate ) {
			$query = new WC_Order_Query(
				array(
					'limit' => -1,
					'page'  => -1,
				)
			);
		} else {
			$query = new WC_Order_Query(
				array(
					'limit'        => -1,
					'page'         => -1,
					'date_created' => '>' . ( strtotime( $lastDate ) ),
				)
			);
		}

		$orders = $query->get_orders() ?? array();
		$users  = get_users() ?? array();

		$exCount = count( $orders );

		update_option( $this->options['exportCount'], $exCount );

		$exportableCount = get_option( $this->options['exportCount'], 0 );
		$lastIndex       = get_option( $this->options['exportLastIndex'], null );

		error_log( "Exportable - $exCount" );
		if ( ( $exportableCount ?? 0 ) < ( $lastIndex ?? 1 ) ) {
			$date = gmdate( 'Y-m-d H:i:s' );
			$this->cancelExportSyncSchedule();
			error_log( "[Finished Sync] | [$date] - No more orders to export" );
		}

		$array = json_decode( json_encode( $users ), true );

		$startIndex = $offset ?? 0;
		$endIndex   = $offset + $limit;

		$currentChunk = array_slice( $orders, $startIndex, $endIndex );

		$allOrders  = count( $orders );
		$chunkCount = count( $currentChunk );
		error_log( "all: count: $allOrders" );
		error_log( "chunk: count: $chunkCount" );

		foreach ( $currentChunk as $key => $value ) {
			$cs_user_id = 1;
			$a          = new WC_Order( $value->get_id() );
			$id         = get_post_meta( $value->get_id() );

			if ( empty( $id['cs_order_id'][0] ) ) {
				foreach ( $array as $v ) {
					if ( ! isset( $id['_customer_user'] ) ) {
						continue;
					}
					if ( isset( $id['_customer_user'][0] ) && $id['_customer_user'][0] == $v['ID'] ) {
						$id_user = get_user_meta( $v['ID'] );
						foreach ( $id_user['cs_client_id'] as $key => $us ) {
							$cs_user_id = $us;
						}
					}
				}

				if ( 1 === (int) $cs_user_id ) {
					// $this->exportContacts();
					// Contacts::initImports();
					$users = get_users();
					$array = json_decode( json_encode( $users ), true );
					foreach ( $array as $v ) {
						if ( ! isset( $id['_customer_user'] ) ) {
							continue;
						}
						if ( isset( $id['_customer_user'][0] ) && $id['_customer_user'][0] == $v['ID'] ) {
							$id_user = get_user_meta( $v['ID'] );
							foreach ( $id_user['cs_client_id'] as $key => $us ) {
								$cs_user_id = $us;
							}
						}
					}
				}

				$data            = array();
				$data['items'][] = array();
				$items           = array();
				$i               = array();
				$it              = $a->get_items();
				$items_id        = array();
				foreach ( $it as $item ) {
					$items_export = array();
					$t            = $item->get_data();
					$items_id[]   = $item->get_id();
					$products_two = wc_get_products(
						array(
							'status' => 'publish',
							'limit'  => -1,
						)
					);
					foreach ( $products_two as $product ) {
						$id_prod = get_post_meta( $product->get_id() );
						$exist   = '';
						if ( $product->get_id() == $item->get_id() ) {
							$i['product_id'] = $id_prod['cs_id'][0];
							$i['name']       = $item->get_name();
							$i['price']      = null;
							$i['tax_id']     = null;
							$i['tax_value']  = $item->calculate_taxes();
							$i['quantity']   = $item->get_quantity();
							$exist           = $item->get_id();
						}
						if ( ! empty( $exist ) ) {
							$items_export[] = $exist;
						}
					}
					$id_export[]     = array_diff( $items_id, $items_export );
					$data['items'][] = $i;
				}

				if ( isset( $id_export ) ) {
					// $this->exportProducts();
					foreach ( $it as $item ) {
						$t            = $item->get_data();
						$products_two = wc_get_products(
							array(
								'status' => 'publish',
								'limit'  => -1,
							)
						);
						foreach ( $products_two as $product ) {
							$id_prod = get_post_meta( $product->get_id() );
							$exist   = '';
							if ( $product->get_id() == $item->get_id() ) {
								$i['product_id'] = $id_prod['cs_id'][0];
								$i['name']       = $item->get_name();
								$i['price']      = null;
								$i['tax_id']     = null;
								$i['tax_value']  = $item->calculate_taxes();
								$i['quantity']   = $item->get_quantity();
								$exist           = $item->get_id();
							}
						}
						$data['items'][] = $i;
					}
				}

				$data['client_id']       = $cs_user_id;
				$data['shipping_fee_id'] = null;
				$data['amount']          = $a->get_prices_include_tax();
				$data['payment_method']  = $a->get_payment_method_title();
				$data['shipping_price']  = $id['_order_shipping_tax'][0] ?? '';
				$data['notes']           = $a->get_customer_note();
				$data['shipping_status'] = $a->get_status();
				$data['order_status']    = $a->get_status();

				$result = $api->addOrders( $data );
				$status = $api->getLastStatus();
				$error  = $api->getLastError();
				// header('Content-Type: text/plain; charset=UTF-8');
				if ( ! empty( $result ) ) {
					update_post_meta( $value->get_id(), 'cs_order_id', $result['new_order_id'] );
					update_metadata( 'post', $a->get_id(), 'cs_order_id', $result['new_order_id'] );
					update_metadata( 'post', $a->get_id(), 'cs_user_id', $cs_user_id );
					$this->actionManager->debug( 'Order ' . $a->get_id() . ' has saved!', 'success', 'Export Orders' );
				} else {
					$this->actionManager->debug( 'Order ' . $a->get_id() . ' could not be saved!', 'error', 'Export Orders' );
				}
			}
		}
		update_option( PLUGIN_SLUG . '_last_orders_export_sync', gmdate( 'Y-m-d H:i:s' ) );
		add_action( 'admin_notices', array( $this, 'my_update_notice_export_orders' ) );
	}
	private function updateLastImportSyncDate() {
		update_option( PLUGIN_SLUG . '_last_orders_sync', gmdate( 'Y-m-d H:i:s' ) );
	}

	private function triggerAdminNotice() {
		add_action( 'admin_notices', array( $this, 'my_update_notice_orders' ) );
	}
}
