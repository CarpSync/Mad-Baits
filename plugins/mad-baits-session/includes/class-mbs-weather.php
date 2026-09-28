<?php
/**
 * Weather API integration.
 *
 * @package MadBaitsSession
 */

defined('ABSPATH') || exit;

/**
 * Weather fetcher.
 */
class MBS_Weather {

	/**
	 * @return void
	 */
	public static function init() {
		// REST only.
	}

	/**
	 * Whether live weather API is enabled and has a key.
	 *
	 * @return bool
	 */
	public static function is_api_configured() {
		return 'yes' === MBS_Plugin::get_setting('weather_enabled', 'no')
			&& '' !== (string) MBS_Plugin::get_setting('weather_api_key', '');
	}

	/**
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function fetch_current($lat, $lng) {
		if (! self::is_api_configured()) {
			return new WP_Error('mbs_weather_no_key', __('Live weather is not configured. Add an API key under WooCommerce → Session, or log conditions manually.', 'mad-baits-session'), array('status' => 400));
		}

		$api_key = (string) MBS_Plugin::get_setting('weather_api_key', '');

		$provider = (string) MBS_Plugin::get_setting('weather_provider', 'openweathermap');
		if ('openweathermap' === $provider) {
			return self::fetch_openweathermap($lat, $lng, $api_key);
		}

		return new WP_Error('mbs_weather_provider', __('Unknown weather provider.', 'mad-baits-session'), array('status' => 400));
	}

	/**
	 * @param float  $lat Lat.
	 * @param float  $lng Lng.
	 * @param string $key API key.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function fetch_openweathermap($lat, $lng, $key) {
		$url = add_query_arg(
			array(
				'lat'   => $lat,
				'lon'   => $lng,
				'appid' => $key,
				'units' => 'metric',
			),
			'https://api.openweathermap.org/data/2.5/weather'
		);

		$response = wp_remote_get($url, array('timeout' => 12));
		if (is_wp_error($response)) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$body = json_decode((string) wp_remote_retrieve_body($response), true);
		if (200 !== $code || ! is_array($body)) {
			return new WP_Error('mbs_weather_failed', __('Could not fetch weather.', 'mad-baits-session'), array('status' => 502));
		}

		$wind_deg = isset($body['wind']['deg']) ? (int) $body['wind']['deg'] : 0;
		return array(
			'source'        => 'openweathermap',
			'captured_at'   => current_time('c'),
			'temperature'   => isset($body['main']['temp']) ? (float) $body['main']['temp'] : null,
			'feels_like'    => isset($body['main']['feels_like']) ? (float) $body['main']['feels_like'] : null,
			'pressure'      => isset($body['main']['pressure']) ? (float) $body['main']['pressure'] : null,
			'wind_speed'    => isset($body['wind']['speed']) ? (float) $body['wind']['speed'] : null,
			'wind_direction'=> self::deg_to_compass($wind_deg),
			'wind_deg'      => $wind_deg,
			'rainfall'      => isset($body['rain']['1h']) ? (float) $body['rain']['1h'] : 0,
			'cloud_cover'   => isset($body['clouds']['all']) ? (int) $body['clouds']['all'] : null,
			'description'   => isset($body['weather'][0]['description']) ? sanitize_text_field((string) $body['weather'][0]['description']) : '',
			'sunrise'       => isset($body['sys']['sunrise']) ? (int) $body['sys']['sunrise'] : 0,
			'sunset'        => isset($body['sys']['sunset']) ? (int) $body['sys']['sunset'] : 0,
		);
	}

	/**
	 * @param int $deg Degrees.
	 * @return string
	 */
	private static function deg_to_compass($deg) {
		$dirs = array('N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW');
		return $dirs[ (int) round(( ( (float) $deg % 360 ) / 45 ) ) % 8 ];
	}

	/**
	 * Resolve UK postcode to coordinates via OpenWeather geocoding.
	 *
	 * @param string $postcode Postcode.
	 * @return array{lat: float, lng: float}|WP_Error
	 */
	public static function geocode_postcode($postcode) {
		if (! self::is_api_configured()) {
			return new WP_Error('mbs_weather_no_key', __('Live weather is not configured.', 'mad-baits-session'), array('status' => 400));
		}

		$postcode = trim($postcode);
		if ('' === $postcode) {
			return new WP_Error('mbs_weather_postcode', __('Enter a postcode for weather lookup.', 'mad-baits-session'), array('status' => 400));
		}

		$api_key = (string) MBS_Plugin::get_setting('weather_api_key', '');
		$url     = add_query_arg(
			array(
				'q'     => $postcode . ',GB',
				'limit' => 1,
				'appid' => $api_key,
			),
			'https://api.openweathermap.org/geo/1.0/direct'
		);

		$response = wp_remote_get($url, array('timeout' => 12));
		if (is_wp_error($response)) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$body = json_decode((string) wp_remote_retrieve_body($response), true);
		if (200 !== $code || ! is_array($body) || empty($body[0]['lat'])) {
			return new WP_Error('mbs_weather_geocode', __('Could not find that postcode.', 'mad-baits-session'), array('status' => 404));
		}

		return array(
			'lat' => (float) $body[0]['lat'],
			'lng' => (float) $body[0]['lon'],
		);
	}

	/**
	 * @param array<string, mixed> $data Raw snapshot.
	 * @return array<string, mixed>
	 */
	public static function sanitize_snapshot(array $data) {
		return array(
			'source'          => sanitize_text_field((string) ($data['source'] ?? 'manual')),
			'captured_at'     => sanitize_text_field((string) ($data['captured_at'] ?? current_time('c'))),
			'temperature'     => isset($data['temperature']) && is_numeric($data['temperature']) ? (float) $data['temperature'] : null,
			'feels_like'      => isset($data['feels_like']) && is_numeric($data['feels_like']) ? (float) $data['feels_like'] : null,
			'pressure'        => isset($data['pressure']) && is_numeric($data['pressure']) ? (float) $data['pressure'] : null,
			'wind_speed'      => isset($data['wind_speed']) && is_numeric($data['wind_speed']) ? (float) $data['wind_speed'] : null,
			'wind_direction'  => sanitize_text_field((string) ($data['wind_direction'] ?? '')),
			'rainfall'        => isset($data['rainfall']) && is_numeric($data['rainfall']) ? (float) $data['rainfall'] : null,
			'cloud_cover'     => isset($data['cloud_cover']) && is_numeric($data['cloud_cover']) ? (int) $data['cloud_cover'] : null,
			'description'     => sanitize_text_field((string) ($data['description'] ?? '')),
			'notes'           => sanitize_textarea_field((string) ($data['notes'] ?? '')),
		);
	}
}
