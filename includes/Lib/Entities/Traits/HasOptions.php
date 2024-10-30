<?php

namespace ClinicSoftware\Lib\Entities\Traits;

trait HasOptions {

	public static function drop() {
		$self = ( new self() );
		$self->dropEntityOptions();
	}

	public function getOptions(): array {
		return $this->options;
	}
	public function updateOptions( $batchSize, $offset ) {
		update_option( $this->options['offset'], $offset += $batchSize );
		if ( ! ( get_option( $this->options['processing'], true ) ?? true ) ) {
			update_option( $this->options['lastDate'], gmdate( 'Y-m-d H:i:s' ) );
		}
	}
	public function updateExportOptions( $batchSize, $lastIndex ) {
		update_option( $this->options['exportLastIndex'], $lastIndex += $batchSize );
		if ( ! ( get_option( $this->options['exportProcessing'], true ) ?? true ) ) {
			update_option( $this->options['exportLastDate'], gmdate( 'Y-m-d H:i:s' ) );
		}
	}
	public function dropEntityOptions() {
		foreach ( $this->options as $key => $value ) {
			delete_option( $value );
		}
	}
	public static function downEntityOptions() {
		return ( new self() )->dropEntityOptions();
	}
	public function loadEntityOptions() {
		foreach ( $this->options as $key => $value ) {
			add_option( $value );
		}
	}
}
