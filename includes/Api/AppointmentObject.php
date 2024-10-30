<?php
namespace ClinicSoftware\Api;

class AppointmentObject {

	public function __construct( int $salon_id ) {
		$this->salon_id = $salon_id;
	}

	public function toApiJSON(): array {
		$parsed = array(); { // Error checking
		if ( empty( $this->salon_id ) || $this->salon_id < 1 ) {
			throw new \Exception( 'Invalid Salon ID' );
		}

		if ( empty( $this->staffID ) || $this->staffID < 1 ) {
			throw new \Exception( 'Invalid staffID' );
		}

		if ( empty( $this->clientID ) || $this->clientID < 1 ) {
			throw new \Exception( 'Invalid clientID' );
		}

		if ( empty( $this->datetime ) || $this->datetime->getTimestamp() < time() ) {
			throw new \Exception( "Invalid DateTime: {$this->datetime->getTimestamp()} < " . time() );
		}

		if ( empty( $this->duration ) || $this->duration < 5 ) {
			throw new \Exception( 'Invalid Duration' );
		}

		if ( empty( $this->status ) ) {
			throw new \Exception( 'Invalid Status' );
		}

		if ( empty( $this->items ) ) {
			throw new \Exception( 'Please provide a valid list of items to add to the booking ( Services )' );
		}
		}

		// Build the object
		{
		$parsed['salon_id'] = $this->salon_id;
		$parsed['date']     = $this->datetime->format( 'Y-m-d' );
		$parsed['time']     = $this->datetime->format( 'H:i:s' );
		$parsed['duration'] = $this->duration;
		$parsed['staff']    = $this->staffID;
		$parsed['client']   = $this->clientID;
		$parsed['status']   = $this->status;
		$parsed['items']    = json_encode( $this->items );

		$parsed['title']               = $this->title;
		$parsed['notes']               = $this->notes;
		$parsed['booking_type_id']     = $this->booking_type_id;
		$parsed['booking_requested']   = $this->booking_requested;
		$parsed['marketing_source_id'] = $this->marketing_source_id;
		}

		return $parsed;
	}

	public $salon_id            = null;
	public $title               = null;
	public $notes               = null;
	public $datetime            = null;
	public $duration            = null;
	public $staffID             = null;
	public $clientID            = null;
	public $status              = 'booked';
	public $booking_type_id     = null;
	public $booking_requested   = null;
	public $items               = array();
	public $marketing_source_id = null;
}
