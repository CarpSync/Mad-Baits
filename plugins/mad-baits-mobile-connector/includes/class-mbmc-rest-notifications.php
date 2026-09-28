<?php
/**
 * REST API: test push notifications.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_REST_Notifications
 */
class MBMC_REST_Notifications {

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
			'/notification/test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'send_test' ),
				'permission_callback' => 'mbmc_app_token_permission_callback',
				'args'                => $this->get_args(),
			)
		);
	}

	/**
	 * POST /notification/test
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function send_test( WP_REST_Request $request ) {
		$result = MBMC_Notification_Service::send_test_notification(
			array(
				'deviceId'  => $request->get_param( 'deviceId' ),
				'pushToken' => $request->get_param( 'pushToken' ),
				'type'      => $request->get_param( 'type' ),
				'title'     => $request->get_param( 'title' ),
				'message'   => $request->get_param( 'message' ),
				'data'      => $request->get_param( 'data' ),
			)
		);

		$status = 200;

		if ( empty( $result['success'] ) ) {
			$status = 'skipped' === ( $result['status'] ?? '' ) ? 200 : 502;
		}

		$response = rest_ensure_response( $result );
		$response->set_status( $status );

		return $response;
	}

	/**
	 * Endpoint args.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_args() {
		return array(
			'deviceId'  => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_device_id',
			),
			'pushToken' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_push_token',
			),
			'type'      => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_notification_type',
			),
			'title'     => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_short_text',
			),
			'message'   => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'mbmc_sanitize_long_text',
			),
			'data'      => array(
				'required' => false,
				'type'     => 'object',
			),
		);
	}
}
