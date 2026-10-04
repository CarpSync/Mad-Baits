<?php
/**
 * Bait comparison tool.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Bait comparison data (editable).
 *
 * @return array<string, array<string, mixed>>
 */
function mad_baits_get_bait_comparison_data() {
	$boilie_fallback = function_exists('mad_baits_get_boilie_range_url') ? mad_baits_get_boilie_range_url() : home_url('/shop/');

	return array(
		'asbo' => array(
			'label'      => 'ASBO',
			'shop_url'   => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('asbo', $boilie_fallback) : $boilie_fallback,
			'metrics'    => array('High', 'Medium', 'Low', 'High', 'High', 'High', 'Medium', 'High'),
		),
		'nutz-plus' => array(
			'label'      => 'Nutz Plus',
			'shop_url'   => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('nutz-plus', $boilie_fallback) : $boilie_fallback,
			'metrics'    => array('Low', 'High', 'High', 'Low', 'High', 'High', 'High', 'High'),
		),
		'nutz-banana' => array(
			'label'      => 'Nutz Banana',
			'shop_url'   => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('nutz-banana', $boilie_fallback) : $boilie_fallback,
			'metrics'    => array('Low', 'High', 'Medium', 'Medium', 'High', 'Medium', 'High', 'Medium'),
		),
		'pandemic' => array(
			'label'      => 'Pandemic',
			'shop_url'   => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('pandemic', $boilie_fallback) : $boilie_fallback,
			'metrics'    => array('Very High', 'Low', 'Low', 'Very High', 'High', 'Very High', 'Medium', 'Very High'),
		),
		'p-fish' => array(
			'label'      => 'P-Fish',
			'shop_url'   => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('p-fish-2', $boilie_fallback) : $boilie_fallback,
			'metrics'    => array('High', 'Medium', 'Medium', 'Medium', 'High', 'High', 'High', 'High'),
		),
		'wicked-white' => array(
			'label'      => 'Wicked White',
			'shop_url'   => function_exists('mad_baits_get_range_filter_url') ? mad_baits_get_range_filter_url('wicked-white', $boilie_fallback) : $boilie_fallback,
			'metrics'    => array('Medium', 'Low', 'High', 'Low', 'High', 'High', 'Very High', 'High'),
		),
	);
}

/**
 * Render bait comparison shortcode.
 *
 * @return string
 */
function mad_baits_bait_comparison_shortcode() {
	$data = mad_baits_get_bait_comparison_data();
	$metric_labels = array(
		__('Fishmeal profile', 'mad-baits'),
		__('Nut profile', 'mad-baits'),
		__('Milk protein profile', 'mad-baits'),
		__('Spice level', 'mad-baits'),
		__('Instant attraction', 'mad-baits'),
		__('Long-term campaign', 'mad-baits'),
		__('Cold water suitability', 'mad-baits'),
		__('Pressured water suitability', 'mad-baits'),
	);

	ob_start();
	?>
	<section class="mad-bait-comparison" data-bait-comparison>
		<header class="mad-bait-comparison__header">
			<p class="section__kicker"><?php esc_html_e('Bait Comparison', 'mad-baits'); ?></p>
			<h2><?php esc_html_e('Compare up to 3 bait ranges', 'mad-baits'); ?></h2>
			<p><?php esc_html_e('Select ranges to compare confidence metrics before you build your next session order.', 'mad-baits'); ?></p>
		</header>

		<div class="mad-bait-comparison__controls" role="group" aria-label="<?php esc_attr_e('Select bait ranges to compare', 'mad-baits'); ?>">
			<?php
			$index = 0;
			foreach ($data as $slug => $item) :
				$checked = $index < 3;
				$index++;
				?>
				<label class="mad-bait-comparison__toggle">
					<input type="checkbox" data-bait-compare-toggle value="<?php echo esc_attr((string) $slug); ?>" <?php checked($checked); ?> />
					<span><?php echo esc_html((string) $item['label']); ?></span>
				</label>
			<?php endforeach; ?>
		</div>
		<p class="mad-bait-comparison__status" data-bait-compare-status></p>

		<div class="mad-bait-comparison__table-wrap">
			<table class="mad-bait-comparison__table">
				<thead>
					<tr>
						<th><?php esc_html_e('Metric', 'mad-baits'); ?></th>
						<?php foreach ($data as $slug => $item) : ?>
							<th data-bait-column="<?php echo esc_attr((string) $slug); ?>">
								<span><?php echo esc_html((string) $item['label']); ?></span>
								<a class="text-link" href="<?php echo esc_url((string) $item['shop_url']); ?>"><?php esc_html_e('Shop This Range', 'mad-baits'); ?></a>
							</th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($metric_labels as $metric_index => $metric_label) : ?>
						<tr>
							<th scope="row"><?php echo esc_html((string) $metric_label); ?></th>
							<?php foreach ($data as $slug => $item) : ?>
								<td data-bait-column="<?php echo esc_attr((string) $slug); ?>">
									<?php echo esc_html(isset($item['metrics'][ $metric_index ]) ? (string) $item['metrics'][ $metric_index ] : '—'); ?>
								</td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>
	<?php
	return (string) ob_get_clean();
}
add_shortcode('mad_baits_bait_comparison', 'mad_baits_bait_comparison_shortcode');
