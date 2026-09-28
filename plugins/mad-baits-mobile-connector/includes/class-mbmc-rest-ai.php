<?php
/**
 * REST API: Bait Buddy / Ask Mark server-side assistant responses.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_REST_AI
 */
class MBMC_REST_AI {

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
			'/ai/bait-buddy',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'send_bait_buddy' ),
				'permission_callback' => 'mbmc_app_token_permission_callback',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/ai/ask-mark',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'send_ask_mark' ),
				'permission_callback' => 'mbmc_app_token_permission_callback',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/ai/speech-to-text',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'speech_to_text' ),
				'permission_callback' => 'mbmc_app_token_permission_callback',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/ai/text-to-speech',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'text_to_speech' ),
				'permission_callback' => 'mbmc_app_token_permission_callback',
			)
		);
	}

	/**
	 * POST /ai/bait-buddy
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function send_bait_buddy( WP_REST_Request $request ) {
		$user_message = $this->extract_user_message( $request );
		if ( '' === $user_message ) {
			return new WP_Error(
				'mbmc_ai_invalid_message',
				__( 'userMessage is required.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		$response_message = $this->build_bait_buddy_message( $user_message );

		return rest_ensure_response(
			array(
				'ok'               => true,
				'mode'             => 'bait_buddy_ai',
				'provider'         => 'backend',
				'message'          => $response_message,
				'suggestedFollowUps' => array(
					'What would you change for low pressure?',
					'Give me a 24-hour session plan.',
					'What bait approach fits this weather?',
				),
				'source'           => 'wordpress',
				'generatedAt'      => wp_date( 'c' ),
			)
		);
	}

	/**
	 * POST /ai/ask-mark
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function send_ask_mark( WP_REST_Request $request ) {
		$user_message = $this->extract_user_message( $request );
		if ( '' === $user_message ) {
			return new WP_Error(
				'mbmc_ai_invalid_message',
				__( 'userMessage is required.', 'mad-baits-mobile-connector' ),
				array( 'status' => 400 )
			);
		}

		$product_recommendation = $this->find_product_recommendation( $user_message );
		$response_message       = $this->build_ask_mark_message( $user_message, $product_recommendation );

		return rest_ensure_response(
			array(
				'ok'                    => true,
				'mode'                  => 'ask_mark',
				'provider'              => 'backend',
				'message'               => $response_message,
				'productRecommendations' => $product_recommendation ? array( $product_recommendation ) : array(),
				'suggestedFollowUps'    => array(
					'Which hookbait would you pair with this?',
					'Build me a margin and long-rod split.',
					'What should I change for an overnight trip?',
				),
				'source'                => 'wordpress',
				'generatedAt'           => wp_date( 'c' ),
			)
		);
	}

	/**
	 * Extract user message from request payload.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string
	 */
	private function extract_user_message( WP_REST_Request $request ) {
		$message = $request->get_param( 'userMessage' );
		if ( ! is_string( $message ) || '' === trim( $message ) ) {
			$message = $request->get_param( 'message' );
		}

		return mbmc_sanitize_long_text( (string) $message, 2000 );
	}

	/**
	 * Build Bait Buddy response text.
	 *
	 * @param string $user_message User message.
	 * @return string
	 */
	private function build_bait_buddy_message( $user_message ) {
		$lower = strtolower( $user_message );

		if ( false !== strpos( $lower, 'pressure' ) ) {
			return 'For pressure-led sessions, fish location first and bait volume second. On falling pressure, begin with a tidy trap and scale up only after signs of fish movement.';
		}

		if ( false !== strpos( $lower, 'session' ) || false !== strpos( $lower, 'overnight' ) ) {
			return 'For an overnight plan: start with two confidence spots, keep one rod mobile, and review activity every 90 minutes rather than recasting too aggressively.';
		}

		if ( false !== strpos( $lower, 'weather' ) || false !== strpos( $lower, 'wind' ) ) {
			return 'Use weather as a guide, not a guarantee. Prioritize windward features, maintain accurate presentation, and adjust feed rate to fish activity rather than forecast alone.';
		}

		return 'Focus on three levers in order: fish location, presentation quality, then bait quantity. Keep decisions simple and adapt based on real signs from the water.';
	}

	/**
	 * Build Ask Mark response text.
	 *
	 * @param string                     $user_message User message.
	 * @param array<string, string>|null $recommendation Product recommendation.
	 * @return string
	 */
	private function build_ask_mark_message( $user_message, $recommendation ) {
		$intro = 'Based on Mark\'s Mad Baits approach: keep confidence high, fish accurately, and avoid over-complicating your baiting pattern.';
		$lower = strtolower( (string) $user_message );

		if ( false !== strpos( $lower, 'fish meal' ) || false !== strpos( $lower, 'fishmeal' ) ) {
			$topic = 'Fish meal is a protein-rich base bait ingredient. It works well as part of a food-bait programme when carp are actively feeding — match your hookbait to the free offerings and build volume gradually.';
			if ( $recommendation ) {
				return $topic . ' From the current Mad Baits range, ' . $recommendation['productName'] . ' is a strong option to consider.';
			}
			return $topic;
		}

		if ( $recommendation ) {
			return $intro . ' For your question, a strong fit from current store products is ' . $recommendation['productName'] . '. Start with a controlled amount and top up only after clear signs of fish in the area.';
		}

		return $intro . ' Pair your hookbait and free offerings sensibly, and scale quantity to pressure and session length.';
	}

	/**
	 * Try to recommend one live WooCommerce product.
	 *
	 * @param string $query User query.
	 * @return array<string, string>|null
	 */
	private function find_product_recommendation( $query ) {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return null;
		}

		$search_terms = $this->extract_product_search_terms( (string) $query );
		$search       = mbmc_sanitize_short_text( $search_terms, 120 );
		$products     = array();

		if ( '' !== $search ) {
			$products = wc_get_products(
				array(
					'status' => 'publish',
					'limit'  => 1,
					'search' => $search,
				)
			);
		}

		if ( empty( $products ) && false !== stripos( (string) $query, 'fish meal' ) ) {
			$products = wc_get_products(
				array(
					'status' => 'publish',
					'limit'  => 1,
					'search' => 'fish meal',
				)
			);
		}

		if ( empty( $products ) ) {
			$products = wc_get_products(
				array(
					'status'   => 'publish',
					'featured' => true,
					'limit'    => 1,
				)
			);
		}

		if ( empty( $products ) || ! is_object( $products[0] ) ) {
			return null;
		}

		/** @var WC_Product $product */
		$product    = $products[0];
		$product_id = (int) $product->get_id();
		$image_id   = (int) $product->get_image_id();
		$image_url  = $image_id > 0 ? wp_get_attachment_image_url( $image_id, 'large' ) : '';
		$price_html = '';
		if ( function_exists( 'wc_price' ) ) {
			$price_html = html_entity_decode( wp_strip_all_tags( wc_price( (float) $product->get_price() ) ), ENT_QUOTES, 'UTF-8' );
		}

		return array(
			'productId'   => 'woo-' . $product_id,
			'productSlug' => (string) $product->get_slug(),
			'productName' => (string) $product->get_name(),
			'reason'      => 'Recommended from current live store products based on your query.',
			'priceLabel'  => $price_html,
			'imageUrl'    => (string) $image_url,
		);
	}

	/**
	 * Extract meaningful product search terms from a natural-language question.
	 *
	 * @param string $query User query.
	 * @return string
	 */
	private function extract_product_search_terms( $query ) {
		$lower = strtolower( (string) $query );
		$lower = str_replace( array( 'fish meal', 'fishmeal' ), 'fish meal', $lower );
		$stop  = array(
			'which',
			'what',
			'the',
			'for',
			'with',
			'about',
			'should',
			'would',
			'recommend',
			'mad',
			'baits',
			'is',
			'are',
			'a',
			'an',
			'me',
			'my',
			'i',
			'use',
			'using',
			'bait',
			'mark',
			'ask',
		);

		$words = preg_split( '/\s+/', preg_replace( '/[^a-z0-9\s]/', ' ', $lower ) );
		$terms = array();
		foreach ( $words as $word ) {
			if ( strlen( $word ) < 3 || in_array( $word, $stop, true ) ) {
				continue;
			}
			$terms[] = $word;
		}

		return trim( implode( ' ', $terms ) );
	}

	/**
	 * POST /ai/speech-to-text
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function speech_to_text( WP_REST_Request $request ) {
		if ( ! mbmc_rate_limit_check( 'mbmc_ai_stt', 30, HOUR_IN_SECONDS ) ) {
			return new WP_Error( 'mbmc_rate_limited', __( 'Too many voice requests. Please try again later.', 'mad-baits-mobile-connector' ), array( 'status' => 429 ) );
		}

		$user_id = mbmc_get_user_id_from_jwt( $request );
		$audio   = $request->get_param( 'audioBase64' );
		if ( ! is_string( $audio ) || '' === trim( $audio ) ) {
			$files = $request->get_file_params();
			if ( ! empty( $files['audio']['tmp_name'] ) ) {
				$audio = base64_encode( (string) file_get_contents( $files['audio']['tmp_name'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		}

		if ( ! is_string( $audio ) || '' === trim( $audio ) ) {
			return new WP_Error( 'mbmc_ai_invalid_audio', __( 'audioBase64 or audio file is required.', 'mad-baits-mobile-connector' ), array( 'status' => 400 ) );
		}

		$api_key = trim( (string) get_option( 'mbmc_openai_api_key', '' ) );
		if ( '' === $api_key ) {
			return new WP_Error( 'mbmc_ai_unconfigured', __( 'Voice transcription is not configured on the server.', 'mad-baits-mobile-connector' ), array( 'status' => 503 ) );
		}

		$decoded = base64_decode( preg_replace( '/^data:audio\/[^;]+;base64,/', '', $audio ), true );
		if ( false === $decoded || strlen( $decoded ) < 100 ) {
			return new WP_Error( 'mbmc_ai_invalid_audio', __( 'Invalid audio payload.', 'mad-baits-mobile-connector' ), array( 'status' => 400 ) );
		}

		$tmp = wp_tempnam( 'mbmc-stt' );
		if ( ! $tmp ) {
			return new WP_Error( 'mbmc_ai_stt_failed', __( 'Could not process audio.', 'mad-baits-mobile-connector' ), array( 'status' => 500 ) );
		}

		file_put_contents( $tmp, $decoded ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$boundary = wp_generate_password( 24, false );
		$body     = "--{$boundary}\r\nContent-Disposition: form-data; name=\"model\"\r\n\r\nwhisper-1\r\n";
		$body    .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"audio.m4a\"\r\nContent-Type: audio/m4a\r\n\r\n";
		$body    .= $decoded . "\r\n--{$boundary}--\r\n";

		$response = wp_remote_post(
			'https://api.openai.com/v1/audio/transcriptions',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
				),
				'body'    => $body,
			)
		);

		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( is_wp_error( $response ) ) {
			MBMC_DB::insert_ai_log( 'speech-to-text', $user_id, 'stt_request', 'error' );
			return new WP_Error( 'mbmc_ai_stt_failed', __( 'Transcription service unavailable.', 'mad-baits-mobile-connector' ), array( 'status' => 502 ) );
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$text = is_array( $data ) && isset( $data['text'] ) ? trim( (string) $data['text'] ) : '';
		$text = $this->apply_safety_filter( $text );

		MBMC_DB::insert_ai_log( 'speech-to-text', $user_id, mbmc_sanitize_short_text( $text, 120 ), $text ? 'ok' : 'empty' );

		return rest_ensure_response(
			array(
				'ok'       => true,
				'text'     => $text,
				'provider' => 'openai_whisper',
			)
		);
	}

	/**
	 * POST /ai/text-to-speech
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function text_to_speech( WP_REST_Request $request ) {
		if ( ! mbmc_rate_limit_check( 'mbmc_ai_tts', 30, HOUR_IN_SECONDS ) ) {
			return new WP_Error( 'mbmc_rate_limited', __( 'Too many voice requests. Please try again later.', 'mad-baits-mobile-connector' ), array( 'status' => 429 ) );
		}

		$user_id = mbmc_get_user_id_from_jwt( $request );
		$text    = $this->apply_safety_filter( mbmc_sanitize_long_text( (string) $request->get_param( 'text' ), 1500 ) );
		if ( '' === $text ) {
			return new WP_Error( 'mbmc_ai_invalid_text', __( 'text is required.', 'mad-baits-mobile-connector' ), array( 'status' => 400 ) );
		}

		$api_key = trim( (string) get_option( 'mbmc_openai_api_key', '' ) );
		if ( '' === $api_key ) {
			return new WP_Error( 'mbmc_ai_unconfigured', __( 'Text-to-speech is not configured on the server.', 'mad-baits-mobile-connector' ), array( 'status' => 503 ) );
		}

		$response = wp_remote_post(
			'https://api.openai.com/v1/audio/speech',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model' => 'tts-1',
						'input' => $text,
						'voice' => 'onyx',
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			MBMC_DB::insert_ai_log( 'text-to-speech', $user_id, mbmc_sanitize_short_text( $text, 120 ), 'error' );
			return new WP_Error( 'mbmc_ai_tts_failed', __( 'Speech service unavailable.', 'mad-baits-mobile-connector' ), array( 'status' => 502 ) );
		}

		$audio = wp_remote_retrieve_body( $response );
		if ( '' === $audio ) {
			return new WP_Error( 'mbmc_ai_tts_failed', __( 'Empty speech response.', 'mad-baits-mobile-connector' ), array( 'status' => 502 ) );
		}

		MBMC_DB::insert_ai_log( 'text-to-speech', $user_id, mbmc_sanitize_short_text( $text, 120 ), 'ok' );

		return rest_ensure_response(
			array(
				'ok'          => true,
				'audioBase64' => base64_encode( $audio ),
				'mimeType'    => 'audio/mpeg',
				'provider'    => 'openai_tts',
			)
		);
	}

	/**
	 * Basic safety guardrails for AI/voice text.
	 *
	 * @param string $text Input text.
	 * @return string
	 */
	private function apply_safety_filter( $text ) {
		$text = trim( (string) $text );
		if ( strlen( $text ) > 2000 ) {
			$text = substr( $text, 0, 2000 );
		}
		$blocked = array( 'kill yourself', 'self-harm', 'bomb', 'terrorist' );
		foreach ( $blocked as $phrase ) {
			if ( false !== stripos( $text, $phrase ) ) {
				return __( 'Sorry, I cannot help with that request.', 'mad-baits-mobile-connector' );
			}
		}
		return $text;
	}
}

