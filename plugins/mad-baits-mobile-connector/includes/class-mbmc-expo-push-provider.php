<?php
/**
 * Expo Push API provider.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_Expo_Push_Provider
 */
class MBMC_Expo_Push_Provider extends MBMC_Push_Provider {

	/**
	 * Expo push send endpoint.
	 */
	const API_URL = 'https://exp.host/--/api/v2/push/send';

	/**
	 * Provider id.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'expo';
	}

	/**
	 * Expo tokens start with ExponentPushToken[...].
	 *
	 * @param string $push_token Raw token.
	 * @return bool
	 */
	public function is_valid_token_format( $push_token ) {
		$push_token = trim( (string) $push_token );

		return '' !== $push_token
			&& 0 === strpos( $push_token, 'ExponentPushToken[' )
			&& ']' === substr( $push_token, -1 );
	}

	/**
	 * Send via Expo Push API.
	 *
	 * @param string               $push_token Expo token.
	 * @param string               $title      Title.
	 * @param string               $message    Body.
	 * @param array<string, mixed> $data       Data payload.
	 * @return array{success:bool,status:string,provider_response:string,error_message:string}
	 */
	public function send( $push_token, $title, $message, array $data = array() ) {
		$push_token = mbmc_sanitize_push_token( $push_token );
		$title      = mbmc_sanitize_short_text( $title, 255 );
		$message    = mbmc_sanitize_long_text( $message, 500 );
		$data       = mbmc_sanitize_push_data( $data );

		if ( ! $this->is_valid_token_format( $push_token ) ) {
			return array(
				'success'           => false,
				'status'            => 'failed',
				'provider_response' => wp_json_encode(
					array(
						'error' => 'invalid_token_format',
					)
				),
				'error_message'     => __( 'Invalid Expo push token format.', 'mad-baits-mobile-connector' ),
			);
		}

		$payload = array(
			'to'    => $push_token,
			'title' => $title,
			'body'  => $message,
			// Expo expects `data` to be a JSON object (record), not an array.
			'data'  => (object) $data,
			'sound' => 'default',
		);

		$response = wp_remote_post(
			self::API_URL,
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
				),
				// Expo v2 push API expects a top-level array of message objects.
				'body'    => wp_json_encode( array( $payload ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success'           => false,
				'status'            => 'failed',
				'provider_response' => wp_json_encode(
					array(
						'error'   => 'network_error',
						'message' => $response->get_error_message(),
					)
				),
				'error_message'     => $response->get_error_message(),
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body_raw    = wp_remote_retrieve_body( $response );
		$body        = json_decode( $body_raw, true );
		$encoded     = is_array( $body ) ? wp_json_encode( $body ) : (string) $body_raw;

		if ( $status_code < 200 || $status_code >= 300 ) {
			return array(
				'success'           => false,
				'status'            => 'failed',
				'provider_response' => $encoded,
				'error_message'     => sprintf(
					/* translators: %d: HTTP status code */
					__( 'Expo API HTTP error %d.', 'mad-baits-mobile-connector' ),
					$status_code
				),
			);
		}

		$ticket_status = '';
		$ticket_id     = '';
		$error_message = '';

		if ( is_array( $body ) && isset( $body['data'] ) ) {
			$ticket = $body['data'];

			if ( is_array( $ticket ) && isset( $ticket[0] ) && is_array( $ticket[0] ) ) {
				$ticket = $ticket[0];
			}

			if ( is_array( $ticket ) ) {
				$ticket_status = isset( $ticket['status'] ) ? (string) $ticket['status'] : '';
				$ticket_id     = isset( $ticket['id'] ) ? (string) $ticket['id'] : '';
				$error_message = isset( $ticket['message'] ) ? (string) $ticket['message'] : '';
			}
		}

		if ( 'error' === $ticket_status ) {
			return array(
				'success'           => false,
				'status'            => 'failed',
				'provider_response' => $encoded,
				'error_message'     => '' !== $error_message
					? $error_message
					: __( 'Expo reported a provider error for this token.', 'mad-baits-mobile-connector' ),
			);
		}

		if ( 'ok' !== $ticket_status && '' === $ticket_id ) {
			return array(
				'success'           => false,
				'status'            => 'failed',
				'provider_response' => $encoded,
				'error_message'     => __( 'Unexpected Expo API response.', 'mad-baits-mobile-connector' ),
			);
		}

		return array(
			'success'           => true,
			'status'            => 'sent',
			'provider_response' => $encoded,
			'error_message'     => '',
			'ticket_id'         => $ticket_id,
		);
	}
}
