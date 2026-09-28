<?php
/**
 * REST API: app feedback submissions.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_REST_Feedback
 */
class MBMC_REST_Feedback {

	/**
	 * REST namespace.
	 */
	const REST_NAMESPACE = 'madbaits/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/feedback',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'submit_feedback' ),
				'permission_callback' => 'mbmc_app_token_permission_callback',
				'args'                => $this->get_args(),
			)
		);
	}

	/**
	 * POST /feedback
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submit_feedback( WP_REST_Request $request ) {
		$message = mbmc_sanitize_long_text( $request->get_param( 'message' ) );

		if ( '' === $message ) {
			return new WP_Error(
				'mbmc_invalid_message',
				__( 'message is required.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		$feedback_type = mbmc_sanitize_feedback_type(
			(string) ( $request->get_param( 'feedbackType' ) ?: $request->get_param( 'category' ) )
		);

		$user_id = mbmc_get_user_id_from_jwt( $request );

		$result = MBMC_DB::insert_feedback(
			array(
				'user_id'       => $user_id,
				'device_id'     => mbmc_sanitize_device_id( (string) $request->get_param( 'deviceId' ) ) ?: null,
				'feedback_type' => $feedback_type,
				'title'         => mbmc_sanitize_short_text( (string) $request->get_param( 'title' ), 255 ) ?: null,
				'message'       => $message,
				'priority'      => mbmc_sanitize_feedback_priority( (string) $request->get_param( 'priority' ) ),
				'app_version'   => mbmc_sanitize_app_version( (string) $request->get_param( 'appVersion' ) ),
				'platform'      => mbmc_sanitize_platform( (string) $request->get_param( 'platform' ) ) ?: null,
				'device_info'   => mbmc_encode_device_info( $request->get_param( 'deviceInfo' ) ),
			)
		);

		if ( false === $result ) {
			return new WP_Error(
				'mbmc_feedback_failed',
				__( 'Could not save feedback.', 'mad-baits-mobile-connector' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'ok'           => true,
				'id'           => (string) $result,
				'feedbackType' => $feedback_type,
				'status'       => 'new',
				'source'       => 'wordpress',
				'generatedAt'    => wp_date( 'c' ),
			)
		);
	}

	/**
	 * Endpoint args.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_args() {
		return array(
			'message'      => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_long_text',
			),
			'feedbackType' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_feedback_type',
			),
			'category'     => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_feedback_type',
			),
			'title'        => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_short_text',
			),
			'priority'     => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_feedback_priority',
			),
			'appVersion'   => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_app_version',
			),
			'platform'     => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_platform',
			),
			'deviceId'     => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_device_id',
			),
			'deviceInfo'   => array(
				'required' => false,
				'type'     => 'object',
			),
		);
	}
}
