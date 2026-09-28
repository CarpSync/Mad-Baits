<?php
/**
 * Login / Register form (Mad Baits) — premium two-column layout when registration enabled.
 *
 * @package MadBaits
 * @see     https://woocommerce.com/document/template-structure/
 * @version 9.9.0
 */

defined('ABSPATH') || exit;

$logo_url = get_template_directory_uri() . '/assets/img/CUP-LOGOv2-1.svg';

do_action('woocommerce_before_customer_login_form');

$allow_registration = 'yes' === get_option('woocommerce_enable_myaccount_registration');
?>

<section class="mad-account-auth-layout" aria-label="<?php esc_attr_e('Create account or sign in', 'mad-baits'); ?>">
	<aside class="mad-account-auth-layout__brand">
		<p class="mad-account-auth-layout__eyebrow"><?php esc_html_e('Mad Baits', 'mad-baits'); ?></p>
		<h2><?php esc_html_e('Own your edge on every session.', 'mad-baits'); ?></h2>
		<p><?php esc_html_e('Premium account access built for anglers who want speed, control, and first look offers.', 'mad-baits'); ?></p>
		<div class="mad-account-auth-layout__logo-wrap">
			<img src="<?php echo esc_url($logo_url); ?>" alt="<?php esc_attr_e('Mad Baits', 'mad-baits'); ?>" width="120" height="120" loading="lazy" decoding="async">
		</div>
		<ul class="mad-account-auth-layout__benefits">
			<li><?php esc_html_e('Faster checkout', 'mad-baits'); ?></li>
			<li><?php esc_html_e('Track orders', 'mad-baits'); ?></li>
			<li><?php esc_html_e('Save addresses', 'mad-baits'); ?></li>
			<li><?php esc_html_e('Access drops & bundle offers', 'mad-baits'); ?></li>
		</ul>
	</aside>
	<div class="mad-account-auth-layout__forms">
		<?php if ($allow_registration) : ?>
			<div class="u-columns col2-set" id="customer_login">
				<div class="u-column1 col-1">
		<?php endif; ?>

					<h2><?php esc_html_e('Login', 'woocommerce'); ?></h2>

					<form class="woocommerce-form woocommerce-form-login login" method="post" novalidate>

						<?php do_action('woocommerce_login_form_start'); ?>

						<?php
						woocommerce_form_field(
							'username',
							array(
								'type'        => 'text',
								'class'       => array('woocommerce-form-row', 'woocommerce-form-row--wide', 'form-row', 'form-row-wide'),
								'label'       => __('Username or email address', 'woocommerce'),
								'required'    => true,
								'autocomplete' => 'username',
							)
						);
						?>

						<?php
						woocommerce_form_field(
							'password',
							array(
								'type'        => 'password',
								'class'       => array('woocommerce-form-row', 'woocommerce-form-row--wide', 'form-row', 'form-row-wide'),
								'label'       => __('Password', 'woocommerce'),
								'required'    => true,
								'autocomplete' => 'current-password',
							)
						);
						?>

						<?php do_action('woocommerce_login_form'); ?>

						<p class="woocommerce-form-row form-row">
							<label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme">
								<input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" />
								<span><?php esc_html_e('Remember me', 'woocommerce'); ?></span>
							</label>
							<?php wp_nonce_field('woocommerce-login', 'woocommerce-login-nonce'); ?>
							<?php
							$wc_btn_class = function_exists('wc_wp_theme_get_element_class_name') ? wc_wp_theme_get_element_class_name('button') : '';
							$wc_btn_class = is_string($wc_btn_class) ? trim($wc_btn_class) : '';
							?>
							<button type="submit" class="woocommerce-button button woocommerce-form-login__submit<?php echo $wc_btn_class ? ' ' . esc_attr($wc_btn_class) : ''; ?>" name="login" value="<?php esc_attr_e('Log in', 'woocommerce'); ?>"><?php esc_html_e('Log in', 'woocommerce'); ?></button>
						</p>
						<p class="woocommerce-LostPassword lost_password">
							<a href="<?php echo esc_url(wp_lostpassword_url()); ?>"><?php esc_html_e('Lost your password?', 'woocommerce'); ?></a>
						</p>

						<?php do_action('woocommerce_login_form_end'); ?>

					</form>

		<?php if ($allow_registration) : ?>
				</div>
				<div class="u-column2 col-2">
					<h2><?php esc_html_e('Register', 'woocommerce'); ?></h2>
					<form method="post" class="woocommerce-form woocommerce-form-register register" <?php do_action('woocommerce_register_form_tag'); ?>>

						<?php do_action('woocommerce_register_form_start'); ?>

						<?php if ('no' === get_option('woocommerce_registration_generate_username')) : ?>
							<?php
							woocommerce_form_field(
								'username',
								array(
									'type'         => 'text',
									'class'        => array('woocommerce-form-row', 'woocommerce-form-row--wide', 'form-row', 'form-row-wide'),
									'label'        => __('Username', 'woocommerce'),
									'required'     => true,
									'autocomplete' => 'username',
								)
							);
							?>
						<?php endif; ?>

						<?php
						woocommerce_form_field(
							'email',
							array(
								'type'         => 'email',
								'class'        => array('woocommerce-form-row', 'woocommerce-form-row--wide', 'form-row', 'form-row-wide'),
								'label'        => __('Email address', 'woocommerce'),
								'required'     => true,
								'autocomplete' => 'email',
							)
						);
						?>

						<?php if ('no' === get_option('woocommerce_registration_generate_password')) : ?>
							<?php
							woocommerce_form_field(
								'password',
								array(
									'type'         => 'password',
									'class'        => array('woocommerce-form-row', 'woocommerce-form-row--wide', 'form-row', 'form-row-wide'),
									'label'        => __('Password', 'woocommerce'),
									'required'     => true,
									'autocomplete' => 'new-password',
								)
							);
							?>
						<?php else : ?>
							<p><?php esc_html_e('A link to set a new password will be sent to your email address.', 'woocommerce'); ?></p>
						<?php endif; ?>

						<?php do_action('woocommerce_register_form'); ?>

						<p class="woocommerce-form-row form-row">
							<?php wp_nonce_field('woocommerce-register', 'woocommerce-register-nonce'); ?>
							<?php
							$wc_reg_btn = function_exists('wc_wp_theme_get_element_class_name') ? wc_wp_theme_get_element_class_name('button') : '';
							$wc_reg_btn = is_string($wc_reg_btn) ? trim($wc_reg_btn) : '';
							?>
							<button type="submit" class="woocommerce-Button woocommerce-button button<?php echo $wc_reg_btn ? ' ' . esc_attr($wc_reg_btn) : ''; ?> woocommerce-form-register__submit" name="register" value="<?php esc_attr_e('Register', 'woocommerce'); ?>"><?php esc_html_e('Register', 'woocommerce'); ?></button>
						</p>

						<?php do_action('woocommerce_register_form_end'); ?>

					</form>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php do_action('woocommerce_after_customer_login_form'); ?>
