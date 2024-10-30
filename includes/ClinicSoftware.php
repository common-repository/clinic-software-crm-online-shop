<?php

namespace ClinicSoftware;

use ClinicSoftware\Lib\Entities\Classes\Coupons;
use ClinicSoftware\Lib\Entities\Classes\Orders;
use ClinicSoftware\Lib\ActionManager;
use ClinicSoftware\Lib\Entities\Classes\Contacts;
use ClinicSoftware\Lib\Entities\Classes\Products;
use ClinicSoftware\Lib\InterfaceManager;

class ClinicSoftware {

	public $interfaceManager;
	public $actionManager;

	private $entities = array(
		Contacts::class,
		Orders::class,
		Products::class,
		Coupons::class,
	);

	/**
	 * The single instance of the class.
	 */
	protected static $_instance = null;

	/**
	 * Constructor.
	 */
	protected function __construct() {
		// Instantiation logic will go here.
	}

	/**
	 * Main Extension Instance.
	 * Ensures only one instance of the extension is loaded or can be loaded.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {
		// Override this PHP function to prevent unwanted copies of your instance.
		// Implement your own error or use `wc_doing_it_wrong()`
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	public function __wakeup() {
		// Override this PHP function to prevent unwanted copies of your instance.
		// Implement your own error or use `wc_doing_it_wrong()`
	}
	/**
	 * Loading all dependencies
	 *
	 * @return void
	 */
	public function InitializePlugin() {

		$actionManager       = ActionManager::instance();
		$this->actionManager = $actionManager;

		// Load the interface manager
		$this->interfaceManager = new InterfaceManager();

		// initialize the UI
		$this->interfaceManager->initializeUI();

		$this->addCustomSchedules();
		$this->addCronEvents();

		$actionManager->catchData();
	}

	public function addCustomSchedules() {
		add_filter(
			'cron_schedules',
			function ( $schedules ) {
				$schedules['every_5_minutes'] = array(
					'interval' => 300,
					'display'  => esc_html__( 'Every 5 Minutes' ),
				);
				return $schedules;
			}
		);

		add_filter(
			'cron_schedules',
			function ( $schedules ) {
				$schedules['every_2_minutes'] = array(
					'interval' => 120,
					'display'  => esc_html__( 'Every 2 Minutes' ),
				);
				return $schedules;
			}
		);
	}

	public function addCronEvents() {
		add_action( Contacts::SCHEDULE_ID, array( ( new Contacts() ), 'handleSchedule' ) );
		add_action( Contacts::EXPORT_SCHEDULE_ID, array( ( new Contacts() ), 'handleExportSchedule' ) );

		add_action( Products::SCHEDULE_ID, array( ( new Products() ), 'handleSchedule' ) );
		add_action( Products::EXPORT_SCHEDULE_ID, array( ( new Products() ), 'handleExportSchedule' ) );

		add_action( Coupons::SCHEDULE_ID, array( ( new Coupons() ), 'handleSchedule' ) );
		add_action( Coupons::EXPORT_SCHEDULE_ID, array( ( new Coupons() ), 'handleExportSchedule' ) );

		add_action( Orders::SCHEDULE_ID, array( ( new Orders() ), 'handleSchedule' ) );
		add_action( Orders::EXPORT_SCHEDULE_ID, array( ( new Orders() ), 'handleExportSchedule' ) );
	}

	public function clearEntityOptions() {
		foreach ( $this->entities as $entity ) {
			if ( method_exists( $entity, 'downEntityOptions' ) ) {
				$entity::downEntityOptions();
			}
		}
	}
	public function clearScheduleHooks() {
		foreach ( $this->entities as $entity ) {
			if ( method_exists( $entity, 'getScheduleId' ) ) {
				wp_clear_scheduled_hook( $entity::SCHEDULE_ID );
			}
			if ( method_exists( $entity, 'getExportScheduleId' ) ) {
				wp_clear_scheduled_hook( $entity::EXPORT_SCHEDULE_ID );
			}
		}
	}


	public function jal_install() {

		global $wpdb;
		global $jal_db_version;
		$table_name      = $wpdb->prefix . 'status';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
                id INT NOT NULL AUTO_INCREMENT,
                name varchar(500) NOT NULL,
                description varchar(500) NOT NULL,
                status varchar(350) NOT NULL,
                user varchar(350) NOT NULL,
                time DATETIME NOT NULL,
                PRIMARY KEY (id) )$charset_collate;";

		include_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		add_option( 'jal_db_version', $jal_db_version );
	}
	public function jal_install_data() {
		global $wpdb;

		$welcome_name = 'Mr. WordPress';
		$welcome_text = 'Congratulations, you just completed the installation!';

		$table_name = $wpdb->prefix . 'test';

		$wpdb->insert(
			$table_name,
			array(
				'time'        => current_time( 'mysql' ),
				'name'        => $welcome_name,
				'description' => $welcome_text,
			)
		);
	}

	public function my_activation() {
		/*
			$this->InitializePlugin();
		$this->addCustomSchedules();
		$this->addCronEvents(); */
		$this->jal_install();
	}

	public function my_deactivation() {
		try {
			$this->clearScheduleHooks();
		} catch ( \Throwable $th ) {
			throw $th;
		}
	}
	public function my_uninstall() {
		try {
			$this->clearEntityOptions();
			$this->clearScheduleHooks();
		} catch ( \Throwable $th ) {
			throw $th;
		}
	}
}
