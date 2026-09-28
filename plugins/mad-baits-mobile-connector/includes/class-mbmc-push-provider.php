<?php
/**
 * Push notification provider contract.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_Push_Provider
 */
abstract class MBMC_Push_Provider {

	/**
	 * Provider identifier stored in logs.
	 *
	 * @return string
	 */
	abstract public function get_id();

	/**
	 * Send a single push notification.
	 *
	 * @param string               $push_token Expo / FCM token.
	 * @param string               $title      Notification title.
	 * @param string               $message    Notification body.
	 * @param array<string, mixed> $data       Optional data payload.
	 * @return array{success:bool,status:string,provider_response:string,error_message:string}
	 */
	abstract public function send( $push_token, $title, $message, array $data = array() );

	/**
	 * Factory for configured provider.
	 *
	 * @param string $provider_id Provider slug.
	 * @return MBMC_Push_Provider|null
	 */
	public static function create( $provider_id ) {
		switch ( sanitize_key( (string) $provider_id ) ) {
			case 'expo':
				return new MBMC_Expo_Push_Provider();
			default:
				return null;
		}
	}

	/**
	 * Whether token looks valid for this provider.
	 *
	 * @param string $push_token Raw token.
	 * @return bool
	 */
	public function is_valid_token_format( $push_token ) {
		unset( $push_token );

		return false;
	}
}
