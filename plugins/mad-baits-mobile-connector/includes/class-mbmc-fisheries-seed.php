<?php
/**
 * Bulk import approved fisheries from bundled directory JSON.
 *
 * @package MadBaitsMobileConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class MBMC_Fisheries_Seed
 */
class MBMC_Fisheries_Seed {

	const OPTION_IMPORTED_AT    = 'mbmc_fisheries_directory_seeded_at';
	const OPTION_IMPORTED_COUNT = 'mbmc_fisheries_directory_seeded_count';

	/**
	 * Absolute path to bundled seed JSON shipped with the connector plugin.
	 *
	 * @return string
	 */
	public static function seed_file_path() {
		return MBMC_PLUGIN_DIR . 'data/fisheries-directory.seed.json';
	}

	/**
	 * Count rows in the seed file without importing.
	 *
	 * @return int
	 */
	public static function count_seed_records() {
		$rows = self::load_seed_rows();
		return count( $rows );
	}

	/**
	 * Import approved fisheries from seed JSON into wp_madbaits_fisheries.
	 *
	 * @param array<string, bool> $args force, dry_run.
	 * @return array<string, int|string>
	 */
	public static function import_directory( array $args = array() ) {
		$force   = ! empty( $args['force'] );
		$dry_run = ! empty( $args['dry_run'] );

		$rows = self::load_seed_rows();
		if ( empty( $rows ) ) {
			return array(
				'ok'       => false,
				'inserted' => 0,
				'skipped'  => 0,
				'errors'   => 0,
				'message'  => 'Seed file missing or empty.',
			);
		}

		$inserted = 0;
		$skipped  = 0;
		$errors   = 0;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				++$errors;
				continue;
			}

			$mapped = self::map_seed_row( $row );
			if ( null === $mapped ) {
				++$skipped;
				continue;
			}

			$duplicate = MBMC_DB::find_duplicate_fishery(
				$mapped['fishery_name'],
				$mapped['postcode'] ?? '',
				$mapped['latitude'],
				$mapped['longitude']
			);

			if ( $duplicate ) {
				if ( $force && ! $dry_run && empty( $duplicate->approved ) ) {
					MBMC_DB::set_fishery_approval( (int) $duplicate->id, true );
				}
				++$skipped;
				continue;
			}

			if ( $dry_run ) {
				++$inserted;
				continue;
			}

			$result = MBMC_DB::insert_fishery( $mapped );
			if ( false === $result ) {
				++$errors;
				continue;
			}

			++$inserted;
		}

		if ( ! $dry_run && $inserted > 0 ) {
			update_option( self::OPTION_IMPORTED_AT, current_time( 'mysql', true ) );
			update_option(
				self::OPTION_IMPORTED_COUNT,
				(int) get_option( self::OPTION_IMPORTED_COUNT, 0 ) + $inserted
			);
		}

		return array(
			'ok'       => true,
			'inserted' => $inserted,
			'skipped'  => $skipped,
			'errors'   => $errors,
			'total'    => count( $rows ),
			'message'  => sprintf(
				/* translators: 1: inserted count, 2: skipped count, 3: error count */
				__( 'Fisheries import complete. Inserted %1$d, skipped %2$d, errors %3$d.', 'mad-baits-mobile-connector' ),
				$inserted,
				$skipped,
				$errors
			),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function load_seed_rows() {
		$path = self::seed_file_path();
		if ( ! is_readable( $path ) ) {
			return array();
		}

		$raw = file_get_contents( $path );
		if ( false === $raw || '' === trim( $raw ) ) {
			return array();
		}

		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * @param array<string, mixed> $row Seed row.
	 * @return array<string, mixed>|null
	 */
	private static function map_seed_row( array $row ) {
		$name = mbmc_sanitize_short_text( (string) ( $row['venue_name'] ?? '' ), 191 );
		if ( '' === $name ) {
			return null;
		}

		$lower = strtolower( $name );
		$noise = array( 'login', 'register', 'sign in', 'sign up', 'home', 'contact', 'about' );
		if ( in_array( $lower, $noise, true ) ) {
			return null;
		}

		$postcode = mbmc_sanitize_short_text( (string) ( $row['postcode_or_area_code'] ?? '' ), 32 );
		$county   = mbmc_sanitize_short_text(
			(string) ( $row['county_department'] ?? $row['region'] ?? '' ),
			128
		);
		$town     = mbmc_sanitize_short_text( (string) ( $row['town_area'] ?? '' ), 191 );

		$latitude  = self::parse_coordinate( $row['latitude'] ?? null );
		$longitude = self::parse_coordinate( $row['longitude'] ?? null );

		$description_parts = array();
		if ( ! empty( $row['venue_type'] ) ) {
			$description_parts[] = 'Type: ' . mbmc_sanitize_short_text( (string) $row['venue_type'], 191 );
		}
		if ( ! empty( $row['source_url'] ) ) {
			$description_parts[] = 'Source: ' . esc_url_raw( (string) $row['source_url'] );
		}
		if ( ! empty( $row['website_url'] ) ) {
			$description_parts[] = 'Website: ' . esc_url_raw( (string) $row['website_url'] );
		}
		if ( ! empty( $row['notes'] ) ) {
			$description_parts[] = mbmc_sanitize_long_text( (string) $row['notes'] );
		}
		if ( ! empty( $row['location_confidence'] ) ) {
			$description_parts[] = 'Location confidence: ' . mbmc_sanitize_short_text( (string) $row['location_confidence'], 32 );
		}

		return array(
			'fishery_name'    => $name,
			'lake_name'       => $town ?: $name,
			'postcode'        => $postcode ?: null,
			'county'          => $county ?: null,
			'latitude'        => $latitude,
			'longitude'       => $longitude,
			'description'     => ! empty( $description_parts ) ? implode( "\n", $description_parts ) : null,
			'created_by_user' => null,
			'approved'        => true,
		);
	}

	/**
	 * @param mixed $value Raw coordinate.
	 * @return float|null
	 */
	private static function parse_coordinate( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		if ( ! is_numeric( $value ) ) {
			return null;
		}
		$float = (float) $value;
		return is_finite( $float ) ? $float : null;
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * wp mbmc seed-fisheries [--force] [--dry-run]
	 */
	WP_CLI::add_command(
		'mbmc seed-fisheries',
		static function ( $args, $assoc_args ) {
			$result = MBMC_Fisheries_Seed::import_directory(
				array(
					'force'   => ! empty( $assoc_args['force'] ),
					'dry_run' => ! empty( $assoc_args['dry-run'] ),
				)
			);

			if ( empty( $result['ok'] ) ) {
				WP_CLI::error( (string) ( $result['message'] ?? 'Import failed.' ) );
			}

			WP_CLI::success(
				sprintf(
					'Inserted %d, skipped %d, errors %d (seed total %d).',
					(int) $result['inserted'],
					(int) $result['skipped'],
					(int) $result['errors'],
					(int) ( $result['total'] ?? 0 )
				)
			);
		}
	);
}
