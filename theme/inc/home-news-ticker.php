<?php
/**
 * Homepage news / event ticker (dynamic).
 *
 * Pulls upcoming events, recently published products, and recent restocks.
 * Free UK shipping over £100 is always included.
 *
 * Customize via filters:
 * - mad_baits_news_ticker_items (final list)
 * - mad_baits_news_ticker_static_items (optional extra lines)
 * - mad_baits_news_ticker_new_product_days (default 21)
 * - mad_baits_news_ticker_restock_days (default 45)
 * - mad_baits_news_ticker_max_events / _new_products / _restocks
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/** @var string Transient cache key. */
const MAD_BAITS_NEWS_TICKER_CACHE_KEY = 'mad_baits_news_ticker_items_v2';

/**
 * Clear ticker transient cache.
 *
 * @return void
 */
function mad_baits_clear_news_ticker_cache() {
	delete_transient(MAD_BAITS_NEWS_TICKER_CACHE_KEY);
}

/**
 * Register stock tracking + cache bust hooks.
 *
 * @return void
 */
function mad_baits_news_ticker_register_hooks() {
	if (! class_exists('WooCommerce')) {
		return;
	}

	add_action('woocommerce_product_set_stock_status', 'mad_baits_ticker_track_restock_status', 10, 3);
	add_action('woocommerce_variation_set_stock_status', 'mad_baits_ticker_track_restock_status', 10, 3);
	add_action('save_post_product', 'mad_baits_clear_news_ticker_cache');
	add_action('save_post_mad_event', 'mad_baits_clear_news_ticker_cache');
}
add_action('init', 'mad_baits_news_ticker_register_hooks');

/**
 * Record restock timestamp when a product returns to stock.
 *
 * @param int         $product_id   Product or variation ID.
 * @param string      $stock_status New status.
 * @param WC_Product  $product      Product object.
 * @return void
 */
function mad_baits_ticker_track_restock_status($product_id, $stock_status, $product) {
	$product_id = absint($product_id);
	if ($product_id < 1) {
		return;
	}

	$previous = (string) get_post_meta($product_id, '_mad_baits_prev_stock_status', true);

	if ('instock' === $stock_status && in_array($previous, array('outofstock', 'onbackorder'), true)) {
		update_post_meta($product_id, '_mad_baits_restocked_at', time());
		mad_baits_clear_news_ticker_cache();
	}

	update_post_meta($product_id, '_mad_baits_prev_stock_status', sanitize_key($stock_status));
}

/**
 * Short uppercase date for ticker copy (e.g. AUGUST 1ST).
 *
 * @param string $date Y-m-d date.
 * @return string
 */
function mad_baits_format_ticker_event_date($date) {
	$date = sanitize_text_field((string) $date);
	if ('' === $date) {
		return '';
	}

	$timestamp = strtotime($date);
	if (! $timestamp) {
		return strtoupper($date);
	}

	return strtoupper(wp_date('F jS', $timestamp));
}

/**
 * Uppercase ticker label with sensible length.
 *
 * @param string $text Raw text.
 * @param int    $max  Max characters.
 * @return string
 */
function mad_baits_ticker_format_label($text, $max = 48) {
	$text = strtoupper(wp_strip_all_tags((string) $text));
	$text = preg_replace('/\s+/', ' ', $text);
	$text = trim((string) $text);

	if ('' === $text) {
		return '';
	}

	if (function_exists('mb_strlen') && function_exists('mb_substr')) {
		if (mb_strlen($text) <= $max) {
			return $text;
		}

		return rtrim(mb_substr($text, 0, max(1, $max - 1))) . '…';
	}

	if (strlen($text) <= $max) {
		return $text;
	}

	return rtrim(substr($text, 0, max(1, $max - 1))) . '…';
}

/**
 * Default ticker links resolved from theme helpers.
 *
 * @return array<string, string>
 */
function mad_baits_get_news_ticker_urls() {
	$shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
	$shop_url = is_string($shop_url) && '' !== $shop_url ? $shop_url : home_url('/shop/');

	$events_url = function_exists('mad_baits_get_events_url') ? mad_baits_get_events_url() : home_url('/events/');

	return array(
		'shop'   => $shop_url,
		'events' => $events_url,
	);
}

/**
 * Pinned free-shipping ticker line (always shown).
 *
 * @return array{label: string, url: string}
 */
function mad_baits_get_news_ticker_shipping_item() {
	$urls = mad_baits_get_news_ticker_urls();

	return array(
		'label' => __('🚚 FREE UK SHIPPING OVER £100', 'mad-baits'),
		'url'   => $urls['shop'],
	);
}

/**
 * Upcoming event ticker items.
 *
 * @return array<int, array{label: string, url: string}>
 */
function mad_baits_get_news_ticker_event_items() {
	if (! class_exists('Mad_Events_Data')) {
		return array();
	}

	$urls  = mad_baits_get_news_ticker_urls();
	$limit = max(1, min(4, (int) apply_filters('mad_baits_news_ticker_max_events', 2)));
	$events = Mad_Events_Data::get_upcoming_events(
		array(
			'posts_per_page' => $limit,
		)
	);

	$items = array();

	foreach ($events as $event) {
		if (! is_array($event) || empty($event['title'])) {
			continue;
		}

		$event_url = isset($event['permalink']) && is_string($event['permalink']) && '' !== $event['permalink']
			? $event['permalink']
			: $urls['events'];

		$date_label = mad_baits_format_ticker_event_date((string) ($event['start_date'] ?? ''));
		$headline   = '🔥 ' . mad_baits_ticker_format_label((string) $event['title'], 36);
		if ('' !== $date_label) {
			$headline .= ' — ' . $date_label;
		}

		$items[] = array(
			'label' => $headline,
			'url'   => $event_url,
		);

		if (! empty($event['highlights']) && is_array($event['highlights'])) {
			$highlights = array_filter(
				array_map(
					static function ($line) {
						return mad_baits_ticker_format_label((string) $line, 56);
					},
					array_slice($event['highlights'], 0, 3)
				)
			);

			if (! empty($highlights)) {
				$items[] = array(
					'label' => implode(' • ', $highlights),
					'url'   => $event_url,
				);
			}
		}
	}

	return $items;
}

/**
 * Resolve a purchasable product URL + display name for ticker lines.
 *
 * @param int $product_id Product ID.
 * @return array{label: string, url: string}|null
 */
function mad_baits_ticker_product_item_from_id($product_id) {
	$product_id = absint($product_id);
	if ($product_id < 1 || ! function_exists('wc_get_product')) {
		return null;
	}

	$product = wc_get_product($product_id);
	if (! $product || ! $product->is_visible() || 'publish' !== $product->get_status()) {
		return null;
	}

	if ($product->is_type('variation')) {
		$parent_id = $product->get_parent_id();
		if ($parent_id > 0) {
			$parent = wc_get_product($parent_id);
			if ($parent && $parent->is_visible()) {
				$product = $parent;
			}
		}
	}

	if (! $product->is_in_stock() || ! $product->is_purchasable()) {
		return null;
	}

	$url = $product->get_permalink();
	if (! is_string($url) || '' === $url) {
		return null;
	}

	return array(
		'name' => $product->get_name(),
		'url'  => $url,
		'id'   => $product->get_id(),
	);
}

/**
 * Recently published in-stock products.
 *
 * @param array<int, string> $exclude_urls URLs already used.
 * @return array<int, array{label: string, url: string}>
 */
function mad_baits_get_news_ticker_new_product_items(array $exclude_urls = array()) {
	if (! function_exists('wc_get_products')) {
		return array();
	}

	$days   = max(1, (int) apply_filters('mad_baits_news_ticker_new_product_days', 21));
	$limit  = max(1, min(6, (int) apply_filters('mad_baits_news_ticker_max_new_products', 3)));
	$cutoff = time() - ($days * DAY_IN_SECONDS);

	$product_ids = wc_get_products(
		array(
			'status'       => 'publish',
			'stock_status' => 'instock',
			'limit'        => $limit * 4,
			'orderby'      => 'date',
			'order'        => 'DESC',
			'return'       => 'ids',
		)
	);

	$items = array();

	foreach ((array) $product_ids as $product_id) {
		if (count($items) >= $limit) {
			break;
		}

		$product = wc_get_product((int) $product_id);
		if (! $product) {
			continue;
		}

		$created = $product->get_date_created();
		if (! $created || $created->getTimestamp() < $cutoff) {
			continue;
		}

		$row = mad_baits_ticker_product_item_from_id((int) $product_id);
		if (! $row || isset($exclude_urls[ $row['url'] ])) {
			continue;
		}

		$label = mad_baits_ticker_format_label(
			sprintf(
				/* translators: %s: product name */
				__('NEW IN — %s', 'mad-baits'),
				$row['name']
			),
			52
		);

		if ('' === $label) {
			continue;
		}

		$items[] = array(
			'label' => '🆕 ' . $label,
			'url'   => $row['url'],
		);
		$exclude_urls[ $row['url'] ] = true;
	}

	return $items;
}

/**
 * Recently restocked in-stock products.
 *
 * @param array<int, string> $exclude_urls URLs already used.
 * @return array<int, array{label: string, url: string}>
 */
function mad_baits_get_news_ticker_restock_items(array $exclude_urls = array()) {
	if (! function_exists('wc_get_products')) {
		return array();
	}

	$days   = max(1, (int) apply_filters('mad_baits_news_ticker_restock_days', 45));
	$limit  = max(1, min(6, (int) apply_filters('mad_baits_news_ticker_max_restocks', 3)));
	$cutoff = time() - ($days * DAY_IN_SECONDS);

	$product_ids = wc_get_products(
		array(
			'status'       => 'publish',
			'stock_status' => 'instock',
			'limit'        => $limit * 2,
			'orderby'      => 'meta_value_num',
			'order'        => 'DESC',
			'meta_key'     => '_mad_baits_restocked_at',
			'return'       => 'ids',
			'meta_query'   => array(
				array(
					'key'     => '_mad_baits_restocked_at',
					'value'   => $cutoff,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				),
			),
		)
	);

	$items = array();

	foreach ((array) $product_ids as $product_id) {
		if (count($items) >= $limit) {
			break;
		}

		$row = mad_baits_ticker_product_item_from_id((int) $product_id);
		if (! $row || isset($exclude_urls[ $row['url'] ])) {
			continue;
		}

		$label = mad_baits_ticker_format_label(
			sprintf(
				/* translators: %s: product name */
				__('BACK IN STOCK — %s', 'mad-baits'),
				$row['name']
			),
			52
		);

		if ('' === $label) {
			continue;
		}

		$items[] = array(
			'label' => '🟡 ' . $label,
			'url'   => $row['url'],
		);
		$exclude_urls[ $row['url'] ] = true;
	}

	return $items;
}

/**
 * Merge ticker groups, de-dupe by URL, always append shipping.
 *
 * @param array<int, array{label: string, url: string}> ...$groups Item groups.
 * @return array<int, array{label: string, url: string}>
 */
function mad_baits_merge_news_ticker_items(array ...$groups) {
	$merged    = array();
	$seen_urls = array();
	$shipping  = mad_baits_get_news_ticker_shipping_item();

	foreach ($groups as $group) {
		foreach ($group as $item) {
			$label = isset($item['label']) ? trim((string) $item['label']) : '';
			$url   = isset($item['url']) ? (string) $item['url'] : '';

			if ('' === $label) {
				continue;
			}

			if ($label === $shipping['label']) {
				continue;
			}

			if ('' !== $url) {
				if (isset($seen_urls[ $url ])) {
					continue;
				}
				$seen_urls[ $url ] = true;
			}

			$merged[] = array(
				'label' => $label,
				'url'   => $url,
			);
		}
	}

	$merged[] = $shipping;

	return $merged;
}

/**
 * Build fresh ticker items (uncached).
 *
 * @return array<int, array{label: string, url: string}>
 */
function mad_baits_build_news_ticker_items() {
	$event_items = mad_baits_get_news_ticker_event_items();
	$seen_urls   = array();

	foreach ($event_items as $item) {
		if (! empty($item['url'])) {
			$seen_urls[ (string) $item['url'] ] = true;
		}
	}

	$new_items     = mad_baits_get_news_ticker_new_product_items($seen_urls);
	$restock_items = mad_baits_get_news_ticker_restock_items($seen_urls);

	$static_items = apply_filters(
		'mad_baits_news_ticker_static_items',
		array()
	);

	$items = mad_baits_merge_news_ticker_items(
		$event_items,
		$new_items,
		$restock_items,
		is_array($static_items) ? $static_items : array()
	);

	/**
	 * Filter homepage ticker items.
	 *
	 * @param array<int, array{label: string, url: string}> $items Ticker rows.
	 */
	return apply_filters('mad_baits_news_ticker_items', $items);
}

/**
 * Build ticker items (cached).
 *
 * @return array<int, array{label: string, url: string}>
 */
function mad_baits_get_news_ticker_items() {
	$cached = get_transient(MAD_BAITS_NEWS_TICKER_CACHE_KEY);
	if (is_array($cached) && ! empty($cached)) {
		return $cached;
	}

	$items = mad_baits_build_news_ticker_items();

	if (! empty($items)) {
		$ttl = max(60, (int) apply_filters('mad_baits_news_ticker_cache_ttl', 10 * MINUTE_IN_SECONDS));
		set_transient(MAD_BAITS_NEWS_TICKER_CACHE_KEY, $items, $ttl);
	}

	return $items;
}

/**
 * Render one ticker item row group (used twice for seamless loop).
 *
 * @param array<int, array{label: string, url: string}> $items         Items.
 * @param bool                                          $aria_hidden Duplicate strip flag.
 * @return void
 */
function mad_baits_render_news_ticker_item_group(array $items, $aria_hidden = false) {
	?>
	<div class="mad-news-ticker__items"<?php echo $aria_hidden ? ' aria-hidden="true"' : ''; ?>>
		<?php
		$rendered = 0;
		foreach ($items as $item) {
			$label = isset($item['label']) ? (string) $item['label'] : '';
			$url   = isset($item['url']) ? (string) $item['url'] : '';

			if ('' === trim($label)) {
				continue;
			}

			if ($rendered > 0) {
				echo '<span class="mad-news-ticker__separator" aria-hidden="true">•</span>';
			}

			if ('' !== $url) {
				printf(
					'<a class="mad-news-ticker__item" href="%1$s">%2$s</a>',
					esc_url($url),
					esc_html($label)
				);
			} else {
				printf(
					'<span class="mad-news-ticker__item mad-news-ticker__item--text">%s</span>',
					esc_html($label)
				);
			}

			++$rendered;
		}
		?>
	</div>
	<?php
}

/**
 * Output homepage news ticker markup.
 *
 * @return void
 */
function mad_baits_render_news_ticker() {
	$items = mad_baits_get_news_ticker_items();
	if (empty($items)) {
		return;
	}

	?>
	<section class="mad-news-ticker" aria-label="<?php esc_attr_e('Mad Baits news and updates', 'mad-baits'); ?>">
		<div class="mad-news-ticker__viewport">
			<div class="mad-news-ticker__track">
				<?php mad_baits_render_news_ticker_item_group($items, false); ?>
				<?php mad_baits_render_news_ticker_item_group($items, true); ?>
			</div>
		</div>
	</section>
	<?php
}
