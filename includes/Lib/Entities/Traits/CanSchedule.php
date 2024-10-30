<?php

namespace ClinicSoftware\Lib\Entities\Traits;

trait CanSchedule {

	public function getRecurrenceTimeString() {
		return isset( $this->recurrence ) ? $this->recurrence : 'every_5_minutes';
	}
	public function initiateSycnSchedule() {
		update_option( $this->options['offset'], 0 );
		update_option( $this->options['processing'], true );
		update_option( $this->options['finished'], false );
		wp_schedule_event( time(), $this->getRecurrenceTimeString(), $this->getScheduleId() );
	}
	public function initiateExportSycnSchedule() {
		update_option( $this->options['exportLastIndex'], 0 );
		update_option( $this->options['exportProcessing'], true );
		wp_schedule_event( time(), $this->getRecurrenceTimeString(), $this->getExportScheduleId() );
	}

	public function getCurrentParams( $batchSize ) {
		update_option( $this->options['limit'], $batchSize );

		$limit    = get_option( $this->options['limit'] );
		$offset   = get_option( $this->options['offset'], 0 );
		$lastDate = get_option( $this->options['lastDate'], '' );

		$limit  = empty( $limit ) ? $batchSize : $limit;
		$offset = empty( $offset ) ? ( $limit - $batchSize ) : $offset;

		return array(
			'limit'    => $limit,
			'offset'   => $offset,
			'lastDate' => $lastDate,
		);
	}
	public function cancelSyncSchedule() {
		try {
			update_option( $this->options['processing'], false );
			// error_log("process ended");
			do {
				$timestamp = wp_next_scheduled( $this->getScheduleId() );
				wp_unschedule_event( $timestamp, $this->getScheduleId() );
			} while ( wp_next_scheduled( $this->getScheduleId() ) );
		} catch ( \Throwable $th ) {
			error_log( $th->getMessage() );
		}
		return;
	}
	public function cancelExportSyncSchedule() {
		try {
			update_option( $this->options['exportProcessing'], false );
			// error_log("process ended");
			do {
				$timestamp = wp_next_scheduled( $this->getExportScheduleId() );
				wp_unschedule_event( $timestamp, $this->getExportScheduleId() );
			} while ( wp_next_scheduled( $this->getExportScheduleId() ) );

		} catch ( \Throwable $th ) {
			error_log( $th->getMessage() );
		}
		return;
	}

	public function showScheduleMessage( $message = null ) {
		$path      = explode( '\\', get_class( $this ) );
		$className = array_pop( $path );
		?>
		<div class="notice updated">
			<p><?php esc_html_e( $message ?? "Began processing imports sync for  $className!", 'clinicsoftware-woocommerce' ); ?></p>
		</div>
		<?php
	}
	public function showExportScheduleMessage( $message = null ) {
		$path      = explode( '\\', get_class( $this ) );
		$className = array_pop( $path );
		?>
		<div class="notice updated">
			<p><?php esc_html_e( $message ?? "Began processing exports sync for  $className!", 'clinicsoftware-woocommerce' ); ?></p>
		</div>
		<?php
	}
}
