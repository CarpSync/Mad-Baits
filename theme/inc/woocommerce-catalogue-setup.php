<?php
/**
 * Mad Baits WooCommerce catalogue setup — bootstrap.
 *
 * Admin: WooCommerce → Mad Baits Catalogue
 * Requires manage_woocommerce. Idempotent — safe on live production.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

$catalogue_dir = get_theme_file_path('/inc/catalogue/');

require_once $catalogue_dir . 'definitions.php';
require_once $catalogue_dir . 'structure.php';
require_once $catalogue_dir . 'suggestions.php';
require_once $catalogue_dir . 'apply.php';
require_once $catalogue_dir . 'admin.php';
require_once $catalogue_dir . 'admin-product-filters.php';

/**
 * Register admin menus (WooCommerce submenu + Tools fallback).
 *
 * @return void
 */
function mad_baits_catalogue_setup_register_menu() {
	add_submenu_page(
		'woocommerce',
		__('Mad Baits Catalogue', 'mad-baits'),
		__('Mad Baits Catalogue', 'mad-baits'),
		'manage_woocommerce',
		'mad-baits-catalogue-setup',
		'mad_baits_catalogue_setup_render_admin_page'
	);

	add_management_page(
		__('Mad Baits Catalogue', 'mad-baits'),
		__('Mad Baits Catalogue', 'mad-baits'),
		'manage_woocommerce',
		'mad-baits-catalogue-setup',
		'mad_baits_catalogue_setup_render_admin_page'
	);
}
add_action('admin_menu', 'mad_baits_catalogue_setup_register_menu', 99);

if (defined('WP_CLI') && WP_CLI) {
	/**
	 * WP-CLI: wp mad-baits catalogue-setup
	 */
	class Mad_Baits_Catalogue_Setup_CLI_Command {
		/**
		 * Create Mad Baits catalogue structure (idempotent).
		 *
		 * ## EXAMPLES
		 *
		 *     wp mad-baits catalogue-setup
		 *
		 * @when after_wp_load
		 */
		public function catalogue_setup() {
			if (! class_exists('WooCommerce', false)) {
				WP_CLI::error('WooCommerce is not active.');
			}

			$log = mad_baits_catalogue_run_structure_setup();
			WP_CLI::success('Catalogue setup finished.');

			foreach ($log as $section => $lines) {
				if (empty($lines) || 'errors' === $section) {
					continue;
				}
				WP_CLI::log(strtoupper(str_replace('_', ' ', $section)) . ': ' . count($lines));
				foreach ($lines as $line) {
					WP_CLI::log('  - ' . $line);
				}
			}

			if (! empty($log['errors'])) {
				foreach ($log['errors'] as $err) {
					WP_CLI::warning($err);
				}
			}
		}

		/**
		 * Scan products and output CSV-style summary (dry run).
		 *
		 * ## OPTIONS
		 *
		 * [--limit=<number>]
		 * : Max products (0 = all).
		 *
		 * @param array $args       Positional args.
		 * @param array $assoc_args Assoc args.
		 */
		public function catalogue_audit($args, $assoc_args) {
			unset($args);
			$limit = isset($assoc_args['limit']) ? absint($assoc_args['limit']) : 0;
			$rows  = mad_baits_catalogue_scan_product_suggestions($limit);
			WP_CLI::log('ID,Title,Score,Suggested Category,Warnings');
			foreach ($rows as $row) {
				WP_CLI::log(
					sprintf(
						'%d,"%s",%d,"%s","%s"',
						(int) $row['id'],
						str_replace('"', '""', (string) $row['title']),
						(int) ($row['confidence_score'] ?? 0),
						str_replace('"', '""', (string) ($row['suggested_category'] ?? '')),
						str_replace('"', '""', implode('; ', (array) ($row['warnings'] ?? array())))
					)
				);
			}
			WP_CLI::success(count($rows) . ' products scanned (no changes made).');
		}
	}

	WP_CLI::add_command('mad-baits catalogue-setup', array('Mad_Baits_Catalogue_Setup_CLI_Command', 'catalogue_setup'));
	WP_CLI::add_command('mad-baits catalogue-audit', array('Mad_Baits_Catalogue_Setup_CLI_Command', 'catalogue_audit'));
}
