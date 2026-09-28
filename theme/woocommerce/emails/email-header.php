<?php
/**
 * Email Header — Mad Baits branded (theme override).
 *
 * Copy derived from WooCommerce 9.6.0; compatibility patched for newer WC.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 10.7.0
 */

if (! defined('ABSPATH')) {
	exit;
}

$mad_logo = function_exists('mad_baits_get_wc_email_brand_logo_url') ? mad_baits_get_wc_email_brand_logo_url() : '';
$wc_img   = get_option('woocommerce_email_header_image');
$email    = $email ?? null;
$store_name = $store_name ?? get_bloginfo('name', 'display');
$header_image_url = apply_filters('woocommerce_email_header_image_url', home_url('/'));

/**
 * WooCommerce email preview (Customizer / settings).
 *
 * @since 9.6.0
 */
if (apply_filters('woocommerce_is_email_preview', false)) {
	$img_transient = get_transient('woocommerce_email_header_image');
	$wc_img        = false !== $img_transient ? $img_transient : $wc_img;
}

$logo_src = '';
if (is_string($wc_img) && '' !== trim($wc_img)) {
	$logo_src = esc_url($wc_img);
} elseif ('' !== $mad_logo) {
	$logo_src = $mad_logo;
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo('charset'); ?>" />
		<meta content="width=device-width, initial-scale=1.0" name="viewport">
		<title><?php echo esc_html($store_name); ?></title>
	</head>
	<body <?php echo is_rtl() ? 'rightmargin' : 'leftmargin'; ?>="0" marginwidth="0" topmargin="0" marginheight="0" offset="0" style="margin:0;padding:0;background-color:#eaeaea;">
		<table width="100%" id="outer_wrapper" cellpadding="0" cellspacing="0" border="0" role="presentation">
			<tr>
				<td><!-- spacer --></td>
				<td width="600" style="max-width:600px;">
					<div id="wrapper" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
						<table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%" role="presentation">
							<tr>
								<td align="center" valign="top">
									<div id="template_header_image">
										<?php if ($logo_src) : ?>
											<table class="mad-email-brand-shell" role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#0a0a0a;">
												<tr>
													<td align="center" style="padding:22px 20px 18px;">
														<?php if ($header_image_url) : ?>
															<a href="<?php echo esc_url($header_image_url); ?>" style="display:inline-block;text-decoration:none;" target="_blank">
																<img src="<?php echo esc_url($logo_src); ?>" alt="<?php echo esc_attr($store_name); ?>" width="220" style="max-width:220px;height:auto;display:block;margin:0 auto;border:0;" />
															</a>
														<?php else : ?>
															<img src="<?php echo esc_url($logo_src); ?>" alt="<?php echo esc_attr($store_name); ?>" width="220" style="max-width:220px;height:auto;display:block;margin:0 auto;border:0;" />
														<?php endif; ?>
													</td>
												</tr>
												<tr>
													<td style="height:4px;line-height:4px;font-size:4px;background-color:#fff202;">&nbsp;</td>
												</tr>
											</table>
										<?php else : ?>
											<table class="mad-email-brand-shell" role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#0a0a0a;">
												<tr>
													<td align="center" style="padding:20px;font-size:20px;font-weight:800;color:#ffffff;font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif;letter-spacing:0.04em;">
														<?php if ($header_image_url) : ?>
															<a href="<?php echo esc_url($header_image_url); ?>" style="color:#ffffff;text-decoration:none;" target="_blank"><?php echo esc_html($store_name); ?></a>
														<?php else : ?>
															<?php echo esc_html($store_name); ?>
														<?php endif; ?>
													</td>
												</tr>
												<tr>
													<td style="height:4px;line-height:4px;font-size:4px;background-color:#fff202;">&nbsp;</td>
												</tr>
											</table>
										<?php endif; ?>
									</div>
									<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_container" role="presentation">
										<tr>
											<td align="center" valign="top">
												<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_header" role="presentation">
													<tr>
														<td id="header_wrapper">
															<h1><?php echo esc_html($email_heading); ?></h1>
														</td>
													</tr>
												</table>
											</td>
										</tr>
										<tr>
											<td align="center" valign="top">
												<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_body" role="presentation">
													<tr>
														<td valign="top" id="body_content">
															<table border="0" cellpadding="20" cellspacing="0" width="100%" role="presentation">
																<tr>
																	<td valign="top">
																		<div id="body_content_inner">
