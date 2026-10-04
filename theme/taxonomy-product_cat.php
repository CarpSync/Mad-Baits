<?php
/**
 * Product category archive with premium landing structure.
 *
 * @package MadBaits
 * @see     https://woocommerce.com/document/template-structure/
 * @version 4.7.0
 */

defined('ABSPATH') || exit;

get_header();

do_action('woocommerce_before_main_content');

$shop_url       = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
$bundle_url     = function_exists('mad_baits_get_bundle_deals_url') ? mad_baits_get_bundle_deals_url() : $shop_url;
$boilies_url    = function_exists('mad_baits_get_boilie_range_url')
	? mad_baits_get_boilie_range_url()
	: (function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('boilies', 'boilie', 'boilies-range'), $shop_url) : $shop_url);
$hookbaits_url  = function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('hookbaits', 'hookbait', 'hook-baits'), $shop_url) : $shop_url;
$enable_compact_card_variations = function_exists('mad_baits_enable_compact_card_variations')
	? mad_baits_enable_compact_card_variations()
	: false;
$current_term   = get_queried_object();
$current_slug   = ($current_term instanceof WP_Term) ? sanitize_title((string) $current_term->slug) : '';
$current_name   = ($current_term instanceof WP_Term) ? (string) $current_term->name : __('Category', 'mad-baits');
$category_intros = array(
	'boilies' => __('Premium Mad Baits boilies built for serious anglers, from quick sessions to long campaigns.', 'mad-baits'),
	'hookbaits' => __('Pop ups, wafters and Skinz hookbaits designed to match the hatch or stand out when it matters.', 'mad-baits'),
	'pellets' => __('Feed and floating pellets built to match your baiting approach and keep fish grubbing for longer.', 'mad-baits'),
	'groundbait-bag-mix' => __('Groundbait and bag mixes that bind fast, leak attraction and carry your liquids or crumb perfectly.', 'mad-baits'),
	'liquids' => __('Food dips, oils and liquid additives designed to add attraction, feed signal and confidence to every baiting approach.', 'mad-baits'),
	'sprays' => __('Fast-hit trigger sprays for hookbaits and short-session edges when bites need forcing.', 'mad-baits'),
	'paste' => __('High-attract wrap and mouldable paste options to create instant feed signal around the hookbait.', 'mad-baits'),
	'bundles-deals' => __('Session-ready bait deals built to save time, money and hassle.', 'mad-baits'),
	'tackle' => __('Terminal tackle and essentials selected for reliable rigs, cleaner presentation and fewer weak links.', 'mad-baits'),
	'accessories' => __('On-the-bank essentials and practical extras to keep your sessions efficient and organised.', 'mad-baits'),
	'clothing' => __('Mad Baits clothing built for comfort, durability and long sessions in all conditions.', 'mad-baits'),
	'rock-salt' => __('Rock salt and fishery prep essentials curated for practical use around bait prep and session support.', 'mad-baits'),
);
$name_overrides = array(
	'accesories' => __('Accessories', 'mad-baits'),
	'accessories-2' => __('Accessories', 'mad-baits'),
);
if (isset($name_overrides[ $current_slug ])) {
	$current_name = (string) $name_overrides[ $current_slug ];
}
$current_intro  = isset($category_intros[ $current_slug ]) ? (string) $category_intros[ $current_slug ] : '';
$term_link      = ($current_term instanceof WP_Term) ? get_term_link($current_term) : '';
$range_profile  = function_exists('mad_baits_get_range_brand_profile') ? mad_baits_get_range_brand_profile($current_slug) : array();
$range_hub_slugs = function_exists('mad_baits_get_storefront_range_hub_slugs')
	? mad_baits_get_storefront_range_hub_slugs()
	: array(
		'asbo',
		'nutz-plus',
		'nutz-banana',
		'pandemic',
		'p-fish-2',
		'p-fish',
		'wicked-white',
		'wicked-whites',
		'calamino',
		'compulsive-angler',
		'stp',
	);

$is_range_hub = ! empty($range_profile) && in_array($current_slug, $range_hub_slugs, true);
$range_type      = isset($_GET['range_type']) ? sanitize_key((string) wp_unslash($_GET['range_type'])) : 'all';
$range_type      = '' === $range_type ? 'all' : $range_type;
$range_type_map  = function_exists('mad_baits_get_range_product_type_filters') ? mad_baits_get_range_product_type_filters() : array();
$range_grouped   = array();
$range_products_query = null;

if (! isset($range_type_map[ $range_type ])) {
	$range_type = 'all';
}

if ($is_range_hub && $current_term instanceof WP_Term) {
	if ('all' === $range_type && function_exists('mad_baits_get_range_hub_products_grouped')) {
		$range_grouped = mad_baits_get_range_hub_products_grouped((int) $current_term->term_id);
	} elseif (function_exists('mad_baits_query_range_hub_products')) {
		$range_products_query = mad_baits_query_range_hub_products(
			(int) $current_term->term_id,
			$range_type,
			max(1, (int) get_query_var('paged')),
			24
		);
	}
}

if (! empty($range_profile) && function_exists('mad_baits_render_range_brand_section')) {
	mad_baits_render_range_brand_section(
		$range_profile,
		array(
			'term'          => $current_term,
			'shop_url'      => $shop_url,
			'term_link'     => (! is_wp_error($term_link) && is_string($term_link) && '' !== $term_link) ? $term_link : $shop_url,
			'boilie_range_url' => $boilies_url,
			'hookbaits_url' => $hookbaits_url,
			'ai_url'        => function_exists('mad_baits_get_ai_bait_finder_url') ? mad_baits_get_ai_bait_finder_url() : home_url('/ai-bait-finder/'),
			'build_url'     => function_exists('mad_baits_get_page_url') ? mad_baits_get_page_url('build-my-session', 'build-my-session') : home_url('/build-my-session/'),
		)
	);
}

$category_hero_slugs = array('hookbaits', 'hookbait', 'hook-baits');
$show_category_hero  = in_array($current_slug, $category_hero_slugs, true) && ! $is_range_hub;
$category_hero_image = '';
$category_hero_style = '';

if ($show_category_hero && function_exists('mad_baits_resolve_context_hero_image')) {
	$category_hero_image = mad_baits_resolve_context_hero_image(
		array(
			'term_id'                 => ($current_term instanceof WP_Term) ? (int) $current_term->term_id : 0,
			'term_taxonomy'           => 'product_cat',
			'candidate_filenames'     => array('hookbaits-hero.png', 'compulsive-triple-hookbaits.png', 'compulsive-pink-popups-hand.png', 'compulsive-yellow-popups-bank.png', 'compulsive-orange-popups-night.png', 'bait-spikez-product.webp', 'CUP-LOGOv2-1.svg'),
			'product_cat_slugs'       => array('hookbaits', 'hookbait', 'hook-baits', 'pop-ups', 'wafters'),
			'semantic_terms'          => array('hookbait', 'pop-up', 'wafter'),
			'prefer_theme_candidates' => true,
			'allow_latest_product'    => false,
		)
	);

	if ('' !== $category_hero_image) {
		$category_hero_style = ' style="' . esc_attr(
			mad_baits_build_css_var_string(
				array(
					'--shop-hero-position' => '52% 38%',
					'--shop-hero-accent'   => '18% 14%',
					'--shop-hero-image'    => "url('" . esc_url_raw($category_hero_image) . "')",
				)
			)
		) . '"';
	}
}
?>

<?php if ($show_category_hero) : ?>
	<section class="shop-hero shop-hero--catalogue shop-hero--hookbaits"<?php echo $category_hero_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<div class="container">
			<?php if (function_exists('mad_baits_render_woo_breadcrumbs')) : ?>
				<?php mad_baits_render_woo_breadcrumbs('mb-hero-breadcrumbs'); ?>
			<?php endif; ?>
			<p class="shop-hero__eyebrow"><?php esc_html_e('Presentation Edge', 'mad-baits'); ?></p>
			<h1 class="shop-hero__title"><?php echo esc_html($current_name); ?></h1>
			<?php if ('' !== $current_intro) : ?>
				<p class="shop-hero__copy"><?php echo esc_html($current_intro); ?></p>
			<?php endif; ?>
			<div class="shop-hero__actions">
				<a class="mad-button" href="#mad-shop-grid"><?php esc_html_e('Shop Hookbaits', 'mad-baits'); ?></a>
				<a class="mad-button mad-button--ghost" href="<?php echo esc_url($boilies_url); ?>"><?php esc_html_e('Browse Boilies', 'mad-baits'); ?></a>
			</div>
		</div>
	</section>
<?php endif; ?>

<section class="section mad-shop-catalogue-section" id="mad-shop-grid">
	<div class="container mad-shop-catalogue">
		<header class="mad-shop-catalogue__header">
			<?php if (function_exists('mad_baits_render_woo_breadcrumbs')) : ?>
				<?php mad_baits_render_woo_breadcrumbs('mb-hero-breadcrumbs'); ?>
			<?php endif; ?>
			<?php $catalogue_heading = $show_category_hero ? 'h2' : 'h1'; ?>
			<<?php echo tag_escape($catalogue_heading); ?> class="mad-shop-catalogue__title"><?php echo esc_html($current_name); ?></<?php echo tag_escape($catalogue_heading); ?>>
			<?php if ('' !== $current_intro) : ?>
				<p class="mad-shop-catalogue__intro"><?php echo esc_html($current_intro); ?></p>
			<?php endif; ?>
		</header>
		<?php if ($is_range_hub) : ?>
			<?php
			$base_term_url = (! is_wp_error($term_link) && is_string($term_link) && '' !== $term_link) ? $term_link : $shop_url;
			?>
			<nav class="mad-shop-quick-filters" aria-label="<?php esc_attr_e('Range type filters', 'mad-baits'); ?>">
				<?php foreach ($range_type_map as $type_key => $type_conf) : ?>
					<?php
					$type_url = 'all' === $type_key
						? remove_query_arg('range_type', $base_term_url)
						: add_query_arg('range_type', $type_key, $base_term_url);
					$is_active = $range_type === $type_key;
					$link_class = $is_active ? 'mad-shop-quick-filters__link mad-shop-quick-filters__link--accent' : 'mad-shop-quick-filters__link';
					?>
					<a class="<?php echo esc_attr($link_class); ?>" href="<?php echo esc_url($type_url); ?>">
						<?php echo esc_html(isset($type_conf['label']) ? (string) $type_conf['label'] : ucfirst((string) $type_key)); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php if ('all' === $range_type && ! empty($range_grouped)) : ?>
				<?php
				$has_grouped_products = false;
				foreach ($range_grouped as $group_product_ids) {
					if (! empty($group_product_ids)) {
						$has_grouped_products = true;
						break;
					}
				}
				?>
				<?php if ($has_grouped_products) : ?>
					<div class="mad-range-product-groups">
						<?php foreach ($range_grouped as $group_key => $group_product_ids) : ?>
							<?php
							if (empty($group_product_ids) || ! isset($range_type_map[ $group_key ]['label'])) {
								continue;
							}
							?>
							<section class="mad-range-product-group" id="range-type-<?php echo esc_attr((string) $group_key); ?>">
								<header class="mad-range-product-group__header">
									<h2 class="mad-range-product-group__title"><?php echo esc_html((string) $range_type_map[ $group_key ]['label']); ?></h2>
								</header>
								<div class="product-grid">
									<?php foreach ($group_product_ids as $group_product_id) : ?>
										<?php mad_baits_render_product_card((int) $group_product_id, $enable_compact_card_variations); ?>
									<?php endforeach; ?>
								</div>
							</section>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<p class="section__empty-state"><?php esc_html_e('No products found for this range yet. Assign products to this range and product type to populate the hub.', 'mad-baits'); ?></p>
				<?php endif; ?>
			<?php elseif ($range_products_query instanceof WP_Query && $range_products_query->have_posts()) : ?>
				<div class="product-grid">
					<?php while ($range_products_query->have_posts()) : ?>
						<?php $range_products_query->the_post(); ?>
						<?php mad_baits_render_product_card(get_the_ID(), $enable_compact_card_variations); ?>
					<?php endwhile; ?>
				</div>
				<?php wp_reset_postdata(); ?>

				<?php
				$pagination_links = paginate_links(
					array(
						'base'      => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
						'format'    => '?paged=%#%',
						'current'   => max(1, (int) get_query_var('paged')),
						'total'     => max(1, (int) $range_products_query->max_num_pages),
						'prev_text' => __('Previous', 'mad-baits'),
						'next_text' => __('Next', 'mad-baits'),
						'type'      => 'list',
					)
				);
				?>
				<?php if (is_string($pagination_links) && '' !== $pagination_links) : ?>
					<div class="mad-shop-pagination">
						<?php echo wp_kses_post($pagination_links); ?>
					</div>
				<?php endif; ?>
			<?php else : ?>
				<p class="section__empty-state"><?php esc_html_e('No products found for this range filter yet. Assign products to this range and type to populate the hub.', 'mad-baits'); ?></p>
			<?php endif; ?>
		<?php elseif (woocommerce_product_loop()) : ?>
			<div class="mad-shop-toolbar" aria-label="<?php esc_attr_e('Shop sorting and results', 'mad-baits'); ?>">
				<?php do_action('woocommerce_before_shop_loop'); ?>
			</div>

			<nav class="mad-shop-quick-filters" aria-label="<?php esc_attr_e('Quick category filters', 'mad-baits'); ?>">
				<a class="mad-shop-quick-filters__link" href="<?php echo esc_url($boilies_url); ?>"><?php esc_html_e('Boilies', 'mad-baits'); ?></a>
				<a class="mad-shop-quick-filters__link" href="<?php echo esc_url($hookbaits_url); ?>"><?php esc_html_e('Hookbaits', 'mad-baits'); ?></a>
				<a class="mad-shop-quick-filters__link" href="<?php echo esc_url(function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('liquids', 'liquid', 'liquid-foods'), $shop_url) : $shop_url); ?>"><?php esc_html_e('Liquids', 'mad-baits'); ?></a>
				<a class="mad-shop-quick-filters__link" href="<?php echo esc_url(function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('pellets', 'pellet'), $shop_url) : $shop_url); ?>"><?php esc_html_e('Pellets', 'mad-baits'); ?></a>
				<a class="mad-shop-quick-filters__link" href="<?php echo esc_url(function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('groundbait-bag-mix', 'groundbait', 'bag-mix'), $shop_url) : $shop_url); ?>"><?php esc_html_e('Groundbait & Bag Mix', 'mad-baits'); ?></a>
				<a class="mad-shop-quick-filters__link" href="<?php echo esc_url(function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('sprays', 'spray'), $shop_url) : $shop_url); ?>"><?php esc_html_e('Sprays', 'mad-baits'); ?></a>
				<a class="mad-shop-quick-filters__link" href="<?php echo esc_url(function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('paste'), $shop_url) : $shop_url); ?>"><?php esc_html_e('Paste', 'mad-baits'); ?></a>
				<a class="mad-shop-quick-filters__link" href="<?php echo esc_url(function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('accessories', 'accessories-2', 'accesories'), $shop_url) : $shop_url); ?>"><?php esc_html_e('Accessories', 'mad-baits'); ?></a>
				<a class="mad-shop-quick-filters__link" href="<?php echo esc_url(function_exists('mad_baits_get_product_cat_link') ? mad_baits_get_product_cat_link(array('clothing', 'merchandise', 'merch'), $shop_url) : $shop_url); ?>"><?php esc_html_e('Clothing', 'mad-baits'); ?></a>
				<a class="mad-shop-quick-filters__link" href="<?php echo esc_url(function_exists('mad_baits_get_page_url') ? mad_baits_get_page_url('rock-salt', 'rock-salt') : home_url('/rock-salt/')); ?>"><?php esc_html_e('Rock Salt', 'mad-baits'); ?></a>
				<a class="mad-shop-quick-filters__link" href="<?php echo esc_url($bundle_url); ?>"><?php esc_html_e('Bundles & Deals', 'mad-baits'); ?></a>
				<a class="mad-shop-quick-filters__link mad-shop-quick-filters__link--accent" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('All products', 'mad-baits'); ?></a>
			</nav>

			<div class="product-grid">
				<?php while (have_posts()) : the_post(); ?>
					<?php mad_baits_render_product_card(get_the_ID(), $enable_compact_card_variations); ?>
				<?php endwhile; ?>
			</div>

			<div class="mad-shop-pagination">
				<?php do_action('woocommerce_after_shop_loop'); ?>
			</div>
		<?php else : ?>
			<div class="mb-premium-empty-state">
				<h2><?php esc_html_e('Products are landing here soon', 'mad-baits'); ?></h2>
				<p><?php esc_html_e('This section is being refreshed. Check back shortly or browse the full shop and bundles in the meantime.', 'mad-baits'); ?></p>
				<div class="mb-premium-hero__actions">
					<a class="mad-button" href="<?php echo esc_url($shop_url); ?>"><?php esc_html_e('Back to Shop', 'mad-baits'); ?></a>
					<a class="mad-button mad-button--ghost" href="<?php echo esc_url($bundle_url); ?>"><?php esc_html_e('View Bundles & Deals', 'mad-baits'); ?></a>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php if ($is_range_hub) : ?>
	<section class="section section--cta">
		<div class="container">
			<div class="footer-cta mb-cta-panel">
				<div>
					<p class="section__kicker"><?php esc_html_e('Build Your Session', 'mad-baits'); ?></p>
					<h2><?php esc_html_e('Need A Complete Match-Up For This Range?', 'mad-baits'); ?></h2>
				</div>
				<div class="mb-premium-hero__actions">
					<a class="mad-button" href="<?php echo esc_url(function_exists('mad_baits_get_page_url') ? mad_baits_get_page_url('build-my-session', 'build-my-session') : home_url('/build-my-session/')); ?>">
						<?php esc_html_e('Build My Session', 'mad-baits'); ?>
					</a>
					<a class="mad-button mad-button--ghost" href="<?php echo esc_url($bundle_url); ?>">
						<?php esc_html_e('Shop Bundles & Deals', 'mad-baits'); ?>
					</a>
				</div>
			</div>
		</div>
	</section>
<?php endif; ?>

<section class="section section--cta">
	<div class="container">
		<div class="footer-cta mb-cta-panel">
			<div>
				<p class="section__kicker"><?php esc_html_e('Need Help Choosing?', 'mad-baits'); ?></p>
				<h2><?php esc_html_e('Speak To The Mad Baits Team', 'mad-baits'); ?></h2>
			</div>
			<a class="mad-button" href="<?php echo esc_url(home_url('/contact/')); ?>">
				<?php esc_html_e('Contact Support', 'mad-baits'); ?>
			</a>
		</div>
	</div>
</section>

<?php
do_action('woocommerce_after_main_content');

get_footer();

