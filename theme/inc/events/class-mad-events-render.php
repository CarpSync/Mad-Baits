<?php
/**
 * Event frontend rendering.
 *
 * @package MadBaits
 */

defined('ABSPATH') || exit;

/**
 * Render events UI components.
 */
class Mad_Events_Render {
	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action('mad_baits_render_event_teaser', array(__CLASS__, 'render_teaser'));
	}

	/**
	 * Compact teaser for app home / discover.
	 *
	 * @param array<string, mixed> $args Args.
	 * @return void
	 */
	public static function render_teaser($args = array()) {
		$event = Mad_Events_Data::get_featured_event();
		if (! $event) {
			return;
		}

		$args = wp_parse_args(
			$args,
			array(
				'context' => 'app',
			)
		);
		?>
		<section class="mad-event-teaser mad-event-teaser--<?php echo esc_attr((string) $args['context']); ?>" data-event-teaser>
			<div class="mad-event-teaser__glow" aria-hidden="true"></div>
			<div class="mad-event-teaser__inner">
				<p class="mad-event-teaser__kicker"><?php esc_html_e('Upcoming Event', 'mad-baits'); ?></p>
				<h3 class="mad-event-teaser__title"><?php echo esc_html((string) $event['title']); ?></h3>
				<?php if (! empty($event['date_label'])) : ?>
					<p class="mad-event-teaser__date"><?php echo esc_html((string) $event['date_label']); ?></p>
				<?php endif; ?>
				<?php if (! empty($event['highlights'])) : ?>
					<p class="mad-event-teaser__tags"><?php echo esc_html(implode(' • ', array_slice($event['highlights'], 0, 4))); ?></p>
				<?php elseif (! empty($event['short_description'])) : ?>
					<p class="mad-event-teaser__tags"><?php echo esc_html(wp_strip_all_tags((string) $event['short_description'])); ?></p>
				<?php endif; ?>
				<a class="mad-button mad-button--small" href="<?php echo esc_url((string) $event['permalink']); ?>">
					<?php esc_html_e('View Event', 'mad-baits'); ?>
				</a>
			</div>
		</section>
		<?php
	}

	/**
	 * Events hub page content.
	 *
	 * @return void
	 */
	public static function render_hub() {
		$featured  = Mad_Events_Data::get_featured_event();
		$upcoming  = Mad_Events_Data::get_upcoming_events();
		$remaining = $featured ? array_values(array_filter($upcoming, static function ($event) use ($featured) {
			return (int) $event['id'] !== (int) $featured['id'];
		})) : $upcoming;
		?>
		<section class="mad-events-hub">
			<header class="mad-events-hub__hero">
				<div class="container mad-events-hub__hero-inner">
					<p class="mad-events-hub__kicker"><?php esc_html_e('Mad Baits Events', 'mad-baits'); ?></p>
					<h1 class="mad-events-hub__title"><?php esc_html_e('Upcoming Events', 'mad-baits'); ?></h1>
					<p class="mad-events-hub__intro"><?php esc_html_e('Open days, bait drops, socials and Mad Baits community events.', 'mad-baits'); ?></p>
				</div>
			</header>

			<div class="container mad-events-hub__body">
				<?php if ($featured) : ?>
					<?php self::render_featured_card($featured); ?>
				<?php endif; ?>

				<?php if (! empty($remaining)) : ?>
					<div class="mad-events-hub__grid">
						<?php foreach ($remaining as $event) : ?>
							<?php self::render_event_card($event); ?>
						<?php endforeach; ?>
					</div>
				<?php elseif (! $featured) : ?>
					<?php self::render_empty_state(); ?>
				<?php endif; ?>

				<nav class="mad-events-hub__discover-links" aria-label="<?php esc_attr_e('Discover more', 'mad-baits'); ?>">
					<a class="mad-button mad-button--ghost" href="<?php echo esc_url(function_exists('mad_baits_get_catch_reports_url') ? mad_baits_get_catch_reports_url() : home_url('/catch-reports/')); ?>">
						<?php esc_html_e('Catch Reports', 'mad-baits'); ?>
					</a>
				</nav>
			</div>
		</section>
		<?php
	}

	/**
	 * @param array<string, mixed> $event Event.
	 * @return void
	 */
	public static function render_featured_card($event) {
		?>
		<article class="mad-event-featured">
			<?php if (! empty($event['hero_image'])) : ?>
				<div class="mad-event-featured__media">
					<img src="<?php echo esc_url((string) $event['hero_image']); ?>" alt="" loading="lazy" decoding="async" />
				</div>
			<?php endif; ?>
			<div class="mad-event-featured__content">
				<div class="mad-event-featured__top">
					<?php if (! empty($event['date_badge'])) : ?>
						<div class="mad-event-card__date-badge" aria-hidden="true"><?php echo $event['date_badge']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<?php endif; ?>
					<div>
						<p class="mad-event-featured__label"><?php esc_html_e('Featured Event', 'mad-baits'); ?></p>
						<h2 class="mad-event-featured__title"><?php echo esc_html((string) $event['title']); ?></h2>
						<?php if (! empty($event['countdown_label'])) : ?>
							<p class="mad-event-featured__countdown"><?php echo esc_html((string) $event['countdown_label']); ?></p>
						<?php elseif (! empty($event['date_label'])) : ?>
							<p class="mad-event-featured__countdown"><?php echo esc_html((string) $event['date_label']); ?></p>
						<?php endif; ?>
					</div>
				</div>
				<?php if (! empty($event['short_description'])) : ?>
					<p class="mad-event-featured__desc"><?php echo esc_html(wp_strip_all_tags((string) $event['short_description'])); ?></p>
				<?php endif; ?>
				<?php self::render_highlights($event); ?>
				<?php self::render_action_buttons($event, true); ?>
			</div>
		</article>
		<?php
	}

	/**
	 * @param array<string, mixed> $event Event.
	 * @return void
	 */
	public static function render_event_card($event) {
		?>
		<article class="mad-event-card">
			<?php if (! empty($event['hero_image'])) : ?>
				<a class="mad-event-card__image" href="<?php echo esc_url((string) $event['permalink']); ?>">
					<img src="<?php echo esc_url((string) $event['hero_image']); ?>" alt="" loading="lazy" decoding="async" />
				</a>
			<?php endif; ?>
			<div class="mad-event-card__body">
				<div class="mad-event-card__head">
					<?php if (! empty($event['date_badge'])) : ?>
						<div class="mad-event-card__date-badge" aria-hidden="true"><?php echo $event['date_badge']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<?php endif; ?>
					<div>
						<?php if (! empty($event['date_label'])) : ?>
							<p class="mad-event-card__date"><?php echo esc_html((string) $event['date_label']); ?></p>
						<?php endif; ?>
						<h3 class="mad-event-card__title"><a href="<?php echo esc_url((string) $event['permalink']); ?>"><?php echo esc_html((string) $event['title']); ?></a></h3>
					</div>
				</div>
				<?php if (! empty($event['location_name'])) : ?>
					<p class="mad-event-card__location"><?php echo esc_html((string) $event['location_name']); ?></p>
				<?php endif; ?>
				<?php if (! empty($event['short_description'])) : ?>
					<p class="mad-event-card__desc"><?php echo esc_html(wp_strip_all_tags((string) $event['short_description'])); ?></p>
				<?php endif; ?>
				<?php self::render_highlights($event); ?>
				<a class="mad-button mad-button--small mad-button--ghost" href="<?php echo esc_url((string) $event['permalink']); ?>"><?php esc_html_e('View Event', 'mad-baits'); ?></a>
			</div>
		</article>
		<?php
	}

	/**
	 * Single event page.
	 *
	 * @param array<string, mixed> $event Event.
	 * @return void
	 */
	public static function render_single($event) {
		?>
		<article class="mad-event-single">
			<header class="mad-event-single__hero" <?php echo ! empty($event['hero_image']) ? 'style="--mad-event-hero:url(\'' . esc_url((string) $event['hero_image']) . '\')"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<div class="container mad-event-single__hero-inner">
					<p class="mad-event-single__kicker"><?php esc_html_e('Mad Baits Event', 'mad-baits'); ?></p>
					<h1 class="mad-event-single__title"><?php echo esc_html((string) $event['title']); ?></h1>
					<ul class="mad-event-single__meta">
						<?php if (! empty($event['date_label'])) : ?><li><?php echo esc_html((string) $event['date_label']); ?></li><?php endif; ?>
						<?php if (! empty($event['time_label'])) : ?><li><?php echo esc_html((string) $event['time_label']); ?></li><?php endif; ?>
						<?php if (! empty($event['location_name'])) : ?><li><?php echo esc_html((string) $event['location_name']); ?></li><?php endif; ?>
					</ul>
					<?php if (! empty($event['countdown_label'])) : ?>
						<p class="mad-event-single__countdown"><?php echo esc_html((string) $event['countdown_label']); ?></p>
					<?php endif; ?>
					<?php self::render_action_buttons($event, true); ?>
				</div>
			</header>

			<div class="container mad-event-single__body">
				<?php if (! empty($event['short_description'])) : ?>
					<p class="mad-event-single__intro"><?php echo esc_html(wp_strip_all_tags((string) $event['short_description'])); ?></p>
				<?php endif; ?>

				<section class="mad-event-single__section">
					<h2><?php esc_html_e('What\'s Happening', 'mad-baits'); ?></h2>
					<?php self::render_highlights($event, 'mad-event-highlights--large'); ?>
					<div class="mad-event-single__content entry-content">
						<?php
						if (! empty($event['full_description'])) {
							echo wp_kses_post((string) $event['full_description']);
						}
						echo (string) $event['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
					</div>
				</section>

				<?php if (! empty($event['gallery_ids'])) : ?>
					<section class="mad-event-single__section mad-event-gallery">
						<h2><?php esc_html_e('Gallery', 'mad-baits'); ?></h2>
						<div class="mad-event-gallery__grid">
							<?php foreach ($event['gallery_ids'] as $attachment_id) : ?>
								<figure><?php echo wp_get_attachment_image((int) $attachment_id, 'medium_large'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></figure>
							<?php endforeach; ?>
						</div>
					</section>
				<?php endif; ?>

				<?php if ($event['rsvp_enabled']) : ?>
					<section class="mad-event-single__section mad-event-rsvp" id="mad-event-rsvp">
						<h2><?php esc_html_e('Register Interest', 'mad-baits'); ?></h2>
						<?php self::render_rsvp_form($event); ?>
					</section>
				<?php endif; ?>

				<?php self::render_featured_products($event); ?>

				<section class="mad-event-single__section mad-event-share">
					<h2><?php esc_html_e('Share', 'mad-baits'); ?></h2>
					<div class="mad-event-share__actions">
						<button type="button" class="mad-button mad-button--ghost mad-button--small" data-event-share><?php esc_html_e('Share Event', 'mad-baits'); ?></button>
						<?php if (Mad_Events_Push::is_available()) : ?>
							<p class="mad-event-share__hint"><?php esc_html_e('Enable Mad Baits alerts in the app to get event reminders on your phone.', 'mad-baits'); ?></p>
						<?php endif; ?>
					</div>
				</section>
			</div>
		</article>
		<?php
	}

	/**
	 * @return void
	 */
	public static function render_empty_state() {
		?>
		<div class="mad-events-empty">
			<p class="mad-events-empty__title"><?php esc_html_e('No upcoming events yet', 'mad-baits'); ?></p>
			<p><?php esc_html_e('Check back soon for open days, bait drops and Mad Baits community events.', 'mad-baits'); ?></p>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed> $event Event.
	 * @param string               $class Extra class.
	 * @return void
	 */
	public static function render_highlights($event, $class = '') {
		if (empty($event['highlights'])) {
			return;
		}
		?>
		<ul class="mad-event-highlights <?php echo esc_attr($class); ?>">
			<?php foreach ($event['highlights'] as $tag) : ?>
				<li><?php echo esc_html((string) $tag); ?></li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * @param array<string, mixed> $event Event.
	 * @param bool                 $featured Featured layout.
	 * @return void
	 */
	public static function render_action_buttons($event, $featured = false) {
		$google = Mad_Events_Calendar::get_google_url($event);
		$ics    = Mad_Events_Calendar::get_ics_url($event);
		$dir    = (string) $event['directions_url'];
		$class  = $featured ? 'mad-event-actions mad-event-actions--featured' : 'mad-event-actions';
		?>
		<div class="<?php echo esc_attr($class); ?>">
			<a class="mad-button mad-button--small" href="<?php echo esc_url((string) $event['permalink']); ?>"><?php esc_html_e('View Event', 'mad-baits'); ?></a>
			<?php if ($event['rsvp_enabled']) : ?>
				<a class="mad-button mad-button--small mad-button--ghost" href="<?php echo esc_url((string) $event['permalink'] . '#mad-event-rsvp'); ?>"><?php esc_html_e('RSVP', 'mad-baits'); ?></a>
			<?php endif; ?>
			<?php if ('' !== $google) : ?>
				<a class="mad-button mad-button--small mad-button--ghost" href="<?php echo esc_url($google); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Add To Calendar', 'mad-baits'); ?></a>
			<?php endif; ?>
			<?php if ('' !== $ics) : ?>
				<a class="mad-button mad-button--small mad-button--ghost" href="<?php echo esc_url($ics); ?>"><?php esc_html_e('Download .ics', 'mad-baits'); ?></a>
			<?php endif; ?>
			<?php if ('' !== $dir) : ?>
				<a class="mad-button mad-button--small mad-button--ghost" href="<?php echo esc_url($dir); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Get Directions', 'mad-baits'); ?></a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed> $event Event.
	 * @return void
	 */
	public static function render_rsvp_form($event) {
		?>
		<form class="mad-event-rsvp__form" data-event-rsvp-form data-event-id="<?php echo esc_attr((string) $event['id']); ?>">
			<input type="text" name="mad_event_hp" value="" tabindex="-1" autocomplete="off" class="mad-event-rsvp__hp" aria-hidden="true" />
			<div class="mad-event-rsvp__grid">
				<label><span><?php esc_html_e('Name', 'mad-baits'); ?></span><input type="text" name="name" required /></label>
				<label><span><?php esc_html_e('Email', 'mad-baits'); ?></span><input type="email" name="email" required /></label>
				<label><span><?php esc_html_e('Phone', 'mad-baits'); ?> <em><?php esc_html_e('(optional)', 'mad-baits'); ?></em></span><input type="tel" name="phone" /></label>
				<label><span><?php esc_html_e('Number attending', 'mad-baits'); ?></span><input type="number" name="attendees" min="1" max="20" value="1" required /></label>
				<label class="mad-event-rsvp__notes"><span><?php esc_html_e('Notes', 'mad-baits'); ?> <em><?php esc_html_e('(optional)', 'mad-baits'); ?></em></span><textarea name="notes" rows="3"></textarea></label>
			</div>
			<button type="submit" class="mad-button"><?php esc_html_e('Register Interest', 'mad-baits'); ?></button>
			<p class="mad-event-rsvp__status" data-event-rsvp-status aria-live="polite"></p>
		</form>
		<?php
	}

	/**
	 * @param array<string, mixed> $event Event.
	 * @return void
	 */
	public static function render_featured_products($event) {
		if (empty($event['featured_products']) || ! class_exists('WooCommerce')) {
			return;
		}
		?>
		<section class="mad-event-single__section mad-event-products">
			<h2><?php esc_html_e('Featured Products For This Event', 'mad-baits'); ?></h2>
			<div class="mad-event-products__grid mad-product-grid">
				<?php
				foreach ($event['featured_products'] as $product_id) {
					if (function_exists('mad_baits_render_product_card')) {
						mad_baits_render_product_card((int) $product_id, true);
					}
				}
				?>
			</div>
		</section>
		<?php
	}
}
