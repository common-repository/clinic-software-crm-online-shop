<?php

namespace ClinicSoftware\Lib;

class HelperFunctions {

	public static function cleanPhoneNumber( $phoneNumber ) {
		// Remove non-numeric characters from the phone number
		$cleanedPhoneNumber = preg_replace( '/[^0-9]/', '', $phoneNumber );

		return $cleanedPhoneNumber;
	}

	public static function cleanUsername( $username ) {
		// Remove special characters from the username
		$cleanedUsername = preg_replace( '/[^a-zA-Z0-9]/', '', $username );

		return $cleanedUsername;
	}
}
