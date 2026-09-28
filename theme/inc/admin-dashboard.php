<?php
/**
 * Mad Baits branded wp-admin dashboard (replaces default WordPress widgets).
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Register dashboard hooks.
 *
 * @return void
 */
function mad_baits_admin_dashboard_init() {
	add_action('wp_dashboard_setup', 'mad_baits_register_dashboard_widgets', 5);
	add_action('wp_dashboard_setup', 'mad_baits_prune_foreign_dashboard_widgets', 999);
	add_action('wp_dashboard_setup', 'mad_baits_remove_welcome_panel', 1);
	add_filter('admin_body_class', 'mad_baits_admin_dashboard_body_class');
	add_action('admin_enqueue_scripts', 'mad_baits_admin_dashboard_assets');
}
add_action('admin_init', 'mad_baits_admin_dashboard_init');

/**
 * Hide the default "Welcome to WordPress" panel.
 *
 * @return void
 */
function mad_baits_remove_welcome_panel() {
	remove_action('welcome_panel', 'wp_welcome_panel');
}

/**
 * Add body class on the dashboard screen.
 *
 * @param string $classes Space-separated admin body classes.
 * @return string
 */
function mad_baits_admin_dashboard_body_class($classes) {
	$screen = function_exists('get_current_screen') ? get_current_screen() : null;
	if ($screen && 'dashboard' === $screen->id) {
		$classes .= ' mad-baits-dashboard';
	}
	return $classes;
}

/**
 * Enqueue dashboard styles on index.php only.
 *
 * @param string $hook_suffix Current admin page hook.
 * @return void
 */
function mad_baits_admin_dashboard_assets($hook_suffix) {
	if ('index.php' !== $hook_suffix) {
		return;
	}

	$path = get_theme_file_path('assets/css/scoped/admin-dashboard.css');
	if (! file_exists($path)) {
		return;
	}

	wp_enqueue_style(
		'mad-baits-admin-dashboard',
		get_theme_file_uri('assets/css/scoped/admin-dashboard.css'),
		array(),
		(string) filemtime($path)
	);
}

/**
 * Register Mad Baits dashboard widgets.
 *
 * @return void
 */
function mad_baits_register_dashboard_widgets() {
	if (! current_user_can('read')) {
		return;
	}

	wp_add_dashboard_widget(
		'mad_baits_dashboard_welcome',
		__('Mad Baits', 'mad-baits'),
		'mad_baits_render_dashboard_welcome_widget'
	);

	if (class_exists('WooCommerce', false) && current_user_can('manage_woocommerce')) {
		wp_add_dashboard_widget(
			'mad_baits_dashboard_store',
			__('Store snapshot', 'mad-baits'),
			'mad_baits_render_dashboard_store_widget'
		);
	}

	wp_add_dashboard_widget(
		'mad_baits_dashboard_shortcuts',
		__('Quick links', 'mad-baits'),
		'mad_baits_render_dashboard_shortcuts_widget'
	);

	wp_add_dashboard_widget(
		'mad_baits_dashboard_content',
		__('Content & community', 'mad-baits'),
		'mad_baits_render_dashboard_content_widget'
	);
}

/**
 * Remove all dashboard widgets except Mad Baits ones.
 *
 * @return void
 */
function mad_baits_prune_foreign_dashboard_widgets() {
	global $wp_meta_boxes;

	if (empty($wp_meta_boxes['dashboard']) || ! is_array($wp_meta_boxes['dashboard'])) {
		return;
	}

	$keep_prefix = 'mad_baits_dashboard_';

	foreach (array('normal', 'side') as $context) {
		if (empty($wp_meta_boxes['dashboard'][ $context ]) || ! is_array($wp_meta_boxes['dashboard'][ $context ])) {
			continue;
		}

		foreach ($wp_meta_boxes['dashboard'][ $context ] as $priority => $boxes) {
			if (! is_array($boxes)) {
				continue;
			}

			foreach (array_keys($boxes) as $widget_id) {
				if (0 !== strpos((string) $widget_id, $keep_prefix)) {
					remove_meta_box($widget_id, 'dashboard', $context);
				}
			}
		}
	}
}

/**
 * Count WooCommerce orders by status.
 *
 * @param string|string[] $status Order status slug(s).
 * @return int
 */
function mad_baits_dashboard_count_orders($status) {
	if (! function_exists('wc_get_orders')) {
		return 0;
	}

	$statuses = is_array($status) ? $status : array($status);
	$statuses = array_map(
		static function ($slug) {
			return 0 === strpos((string) $slug, 'wc-') ? $slug : 'wc-' . $slug;
		},
		$statuses
	);

	$result = wc_get_orders(
		array(
			'status'   => $statuses,
			'limit'    => 1,
			'paginate' => true,
			'return'   => 'ids',
		)
	);

	if (is_object($result) && isset($result->total)) {
		return (int) $result->total;
	}

	return 0;
}

/**
 * Count published posts for a post type.
 *
 * @param string $post_type Post type slug.
 * @return int
 */
function mad_baits_dashboard_count_published($post_type) {
	$counts = wp_count_posts($post_type);
	if (! $counts || ! isset($counts->publish)) {
		return 0;
	}
	return (int) $counts->publish;
}

/**
 * Count pending posts for a post type.
 *
 * @param string $post_type Post type slug.
 * @return int
 */
function mad_baits_dashboard_count_pending($post_type) {
	$counts = wp_count_posts($post_type);
	if (! $counts || ! isset($counts->pending)) {
		return 0;
	}
	return (int) $counts->pending;
}

/**
 * Welcome / overview widget.
 *
 * @return void
 */
function mad_baits_render_dashboard_welcome_widget() {
	$user      = wp_get_current_user();
	$logo_url  = function_exists('mad_baits_get_login_logo_url') ? mad_baits_get_login_logo_url() : '';
	$shop_url  = function_exists('mad_baits_get_shop_url') ? mad_baits_get_shop_url() : home_url('/shop/');
	$site_url  = home_url('/');
	$greeting  = $user instanceof WP_User && $user->exists()
		? sprintf(
			/* translators: %s: display name */
			__('Welcome back, %s', 'mad-baits'),
			$user->display_name
		)
		: __('Welcome back', 'mad-baits');
	?>
	<div class="mb-admin-dash mb-admin-dash--welcome">
		<?php if ($logo_url) : ?>
			<img class="mb-admin-dash__logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php esc_attr_e('Mad Baits', 'mad-baits'); ?>" width="120" height="48" />
		<?php endif; ?>
		<p class="mb-admin-dash__lead"><?php echo esc_html($greeting); ?></p>
		<p class="mb-admin-dash__muted">
			<?php esc_html_e('Your Mad Baits control centre — manage the shop, catalogue, content, and community from here.', 'mad-baits'); ?>
		</p>
		<div class="mb-admin-dash__actions">
			<a class="mb-admin-dash__btn mb-admin-dash__btn--primary" href="<?php echo esc_url($site_url); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e('View site', 'mad-baits'); ?>
			</a>
			<a class="mb-admin-dash__btn" href="<?php echo esc_url($shop_url); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e('Visit shop', 'mad-baits'); ?>
			</a>
		</div>
	</div>
	<?php
}

/**
 * Store snapshot widget (WooCommerce).
 *
 * @return void
 */
function mad_baits_render_dashboard_store_widget() {
	$processing = mad_baits_dashboard_count_orders('processing');
	$on_hold    = mad_baits_dashboard_count_orders('on-hold');
	$completed  = mad_baits_dashboard_count_orders('completed');
	$products   = mad_baits_dashboard_count_published('product');

	$low_stock = 0;
	if (function_exists('wc_get_products')) {
		$low_stock = count(
			wc_get_products(
				array(
					'status'    => 'publish',
					'limit'     => 100,
					'low_stock' => true,
					'return'    => 'ids',
				)
			)
		);
	}

	$orders_url   = admin_url('edit.php?post_type=shop_order');
	$products_url = admin_url('edit.php?post_type=product');
	$reports_url  = admin_url('admin.php?page=wc-admin&path=/analytics/overview');
	?>
	<div class="mb-admin-dash mb-admin-dash--store">
		<ul class="mb-admin-dash__stats">
			<li>
				<span class="mb-admin-dash__stat-value"><?php echo esc_html((string) $processing); ?></span>
				<span class="mb-admin-dash__stat-label"><?php esc_html_e('Processing', 'mad-baits'); ?></span>
			</li>
			<li>
				<span class="mb-admin-dash__stat-value"><?php echo esc_html((string) $on_hold); ?></span>
				<span class="mb-admin-dash__stat-label"><?php esc_html_e('On hold', 'mad-baits'); ?></span>
			</li>
			<li>
				<span class="mb-admin-dash__stat-value"><?php echo esc_html((string) $completed); ?></span>
				<span class="mb-admin-dash__stat-label"><?php esc_html_e('Completed (all time)', 'mad-baits'); ?></span>
			</li>
			<li>
				<span class="mb-admin-dash__stat-value"><?php echo esc_html((string) $products); ?></span>
				<span class="mb-admin-dash__stat-label"><?php esc_html_e('Products live', 'mad-baits'); ?></span>
			</li>
		</ul>
		<?php if ($low_stock > 0) : ?>
			<p class="mb-admin-dash__notice">
				<?php
				printf(
					/* translators: %d: product count */
					esc_html(_n('%d product needs stock attention.', '%d products need stock attention.', $low_stock, 'mad-baits')),
					(int) $low_stock
				);
				?>
			</p>
		<?php endif; ?>
		<p class="mb-admin-dash__links">
			<a href="<?php echo esc_url($orders_url); ?>"><?php esc_html_e('Orders', 'mad-baits'); ?></a>
			<span aria-hidden="true">·</span>
			<a href="<?php echo esc_url($products_url); ?>"><?php esc_html_e('Products', 'mad-baits'); ?></a>
			<?php if (current_user_can('view_woocommerce_reports')) : ?>
				<span aria-hidden="true">·</span>
				<a href="<?php echo esc_url($reports_url); ?>"><?php esc_html_e('Analytics', 'mad-baits'); ?></a>
			<?php endif; ?>
		</p>
	</div>
	<?php
}

/**
 * Quick links widget.
 *
 * @return void
 */
function mad_baits_render_dashboard_shortcuts_widget() {
	$links = mad_baits_get_dashboard_shortcut_links();
	?>
	<div class="mb-admin-dash mb-admin-dash--shortcuts">
		<ul class="mb-admin-dash__shortcut-grid">
			<?php foreach ($links as $link) : ?>
				<?php if (empty($link['cap']) || current_user_can($link['cap'])) : ?>
					<li>
						<a class="mb-admin-dash__shortcut" href="<?php echo esc_url($link['url']); ?>">
							<?php if (! empty($link['icon'])) : ?>
								<span class="dashicons <?php echo esc_attr($link['icon']); ?>" aria-hidden="true"></span>
							<?php endif; ?>
							<span class="mb-admin-dash__shortcut-label"><?php echo esc_html($link['label']); ?></span>
						</a>
					</li>
				<?php endif; ?>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
}

/**
 * Build shortcut links for the dashboard.
 *
 * @return array<int, array{label: string, url: string, icon?: string, cap?: string}>
 */
function mad_baits_get_dashboard_shortcut_links() {
	$links = array(
		array(
			'label' => __('All products', 'mad-baits'),
			'url'   => admin_url('edit.php?post_type=product'),
			'icon'  => 'dashicons-cart',
			'cap'   => 'edit_products',
		),
		array(
			'label' => __('Orders', 'mad-baits'),
			'url'   => admin_url('edit.php?post_type=shop_order'),
			'icon'  => 'dashicons-list-view',
			'cap'   => 'edit_shop_orders',
		),
		array(
			'label' => __('Mad Baits Catalogue', 'mad-baits'),
			'url'   => admin_url('admin.php?page=mad-baits-catalogue-setup'),
			'icon'  => 'dashicons-category',
			'cap'   => 'manage_woocommerce',
		),
		array(
			'label' => __('Range product order', 'mad-baits'),
			'url'   => admin_url('admin.php?page=mad-baits-range-product-order'),
			'icon'  => 'dashicons-sort',
			'cap'   => 'manage_woocommerce',
		),
		array(
			'label' => __('Catch reports', 'mad-baits'),
			'url'   => admin_url('edit.php?post_type=catch_report'),
			'icon'  => 'dashicons-format-gallery',
			'cap'   => 'edit_posts',
		),
		array(
			'label' => __('Events', 'mad-baits'),
			'url'   => admin_url('edit.php?post_type=mad_event'),
			'icon'  => 'dashicons-calendar-alt',
			'cap'   => 'edit_posts',
		),
		array(
			'label' => __('Mad Baits TV', 'mad-baits'),
			'url'   => admin_url('edit.php?post_type=mad_baits_video'),
			'icon'  => 'dashicons-video-alt3',
			'cap'   => 'edit_posts',
		),
		array(
			'label' => __('Menus', 'mad-baits'),
			'url'   => admin_url('nav-menus.php'),
			'icon'  => 'dashicons-menu',
			'cap'   => 'edit_theme_options',
		),
	);

	$bundle_url = function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : '';
	if ($bundle_url) {
		$links[] = array(
			'label' => __('Bundles & deals (front)', 'mad-baits'),
			'url'   => $bundle_url,
			'icon'  => 'dashicons-tag',
			'cap'   => 'read',
		);
	}

	return apply_filters('mad_baits_dashboard_shortcut_links', $links);
}

/**
 * Content & community widget.
 *
 * @return void
 */
function mad_baits_render_dashboard_content_widget() {
	$catch_pending = mad_baits_dashboard_count_pending('catch_report');
	$catch_live    = mad_baits_dashboard_count_published('catch_report');
	$videos        = mad_baits_dashboard_count_published('mad_baits_video');
	$events        = class_exists('Mad_Events_Data', false)
		? Mad_Events_Data::get_upcoming_events(array('posts_per_page' => 5))
		: array();
	?>
	<div class="mb-admin-dash mb-admin-dash--content">
		<ul class="mb-admin-dash__content-stats">
			<li>
				<strong><?php echo esc_html((string) $catch_live); ?></strong>
				<?php esc_html_e('Catch reports published', 'mad-baits'); ?>
				<?php if ($catch_pending > 0) : ?>
					<a class="mb-admin-dash__badge" href="<?php echo esc_url(admin_url('edit.php?post_status=pending&post_type=catch_report')); ?>">
						<?php
						printf(
							/* translators: %d: pending count */
							esc_html(_n('%d pending review', '%d pending review', $catch_pending, 'mad-baits')),
							(int) $catch_pending
						);
						?>
					</a>
				<?php endif; ?>
			</li>
			<li>
				<strong><?php echo esc_html((string) $videos); ?></strong>
				<?php esc_html_e('Mad Baits TV videos', 'mad-baits'); ?>
			</li>
		</ul>

		<?php if (! empty($events)) : ?>
			<h4 class="mb-admin-dash__subhead"><?php esc_html_e('Upcoming events', 'mad-baits'); ?></h4>
			<ul class="mb-admin-dash__event-list">
				<?php foreach ($events as $event) : ?>
					<?php
					$post_id = isset($event['id']) ? (int) $event['id'] : 0;
					if ($post_id <= 0) {
						continue;
					}
					$edit_url = get_edit_post_link($post_id, 'raw');
					$title    = isset($event['title']) ? (string) $event['title'] : get_the_title($post_id);
					$date     = isset($event['date_label']) ? (string) $event['date_label'] : '';
					?>
					<li>
						<?php if ($edit_url) : ?>
							<a href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html($title); ?></a>
						<?php else : ?>
							<?php echo esc_html($title); ?>
						<?php endif; ?>
						<?php if ($date) : ?>
							<span class="mb-admin-dash__event-date"><?php echo esc_html($date); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p class="mb-admin-dash__muted">
				<?php esc_html_e('No upcoming events scheduled.', 'mad-baits'); ?>
				<a href="<?php echo esc_url(admin_url('post-new.php?post_type=mad_event')); ?>"><?php esc_html_e('Add one', 'mad-baits'); ?></a>
			</p>
		<?php endif; ?>
	</div>
	<?php
}
