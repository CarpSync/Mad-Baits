<?php
/**
 * Email Footer — Mad Baits branded (theme override).
 *
 * Copy derived from WooCommerce 9.6.0; compatibility patched for newer WC.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates\Emails
 * @version 10.4.0
 */

defined('ABSPATH') || exit;

$email      = $email ?? null;
$details    = function_exists('mad_baits_get_contact_details') ? mad_baits_get_contact_details() : array();
$home       = home_url('/');
$email_addr = isset($details['email']) ? sanitize_email((string) $details['email']) : '';
$phone_disp = isset($details['phone_display']) ? (string) $details['phone_display'] : '';
$phone_tel  = isset($details['phone_tel']) ? preg_replace('/\s+/', '', (string) $details['phone_tel']) : '';
$social     = function_exists('mad_baits_wc_email_social_links') ? mad_baits_wc_email_social_links() : array();

?>
																		</div>
																	</td>
																</tr>
															</table>
														</td>
													</tr>
												</table>
											</td>
										</tr>
									</table>
								</td>
							</tr>
							<tr>
								<td align="center" valign="top">
									<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_footer" role="presentation" style="background-color:#0a0a0a;">
										<tr>
											<td valign="top" style="padding:0;">
												<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
													<tr>
														<td class="mad-email-footer-brand" style="padding:22px 20px 10px;font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif;font-size:13px;line-height:1.55;color:#f0f0f0;text-align:center;">
															<p style="margin:0 0 10px;font-weight:800;letter-spacing:0.08em;text-transform:uppercase;font-size:11px;color:#fff202;">
																<?php esc_html_e('Mad Baits', 'mad-baits'); ?>
															</p>
															<p style="margin:0 0 12px;">
																<a href="<?php echo esc_url($home); ?>" style="color:#fff202;font-weight:700;text-decoration:none;">
																	<?php esc_html_e('Visit our shop', 'mad-baits'); ?>
																</a>
															</p>
															<?php if ($email_addr) : ?>
																<p style="margin:0 0 4px;">
																	<?php esc_html_e('Support', 'mad-baits'); ?>:
																	<a href="mailto:<?php echo esc_attr($email_addr); ?>" style="color:#fff202;font-weight:600;text-decoration:none;">
																		<?php echo esc_html($email_addr); ?>
																	</a>
																</p>
															<?php endif; ?>
															<?php if ($phone_disp && $phone_tel) : ?>
																<p style="margin:0 0 12px;">
																	<a href="<?php echo esc_url('tel:' . $phone_tel); ?>" style="color:#e8e8e8;text-decoration:none;">
																		<?php echo esc_html($phone_disp); ?>
																	</a>
																</p>
															<?php elseif ($phone_disp) : ?>
																<p style="margin:0 0 12px;color:#e8e8e8;"><?php echo esc_html($phone_disp); ?></p>
															<?php endif; ?>
															<?php if (! empty($social)) : ?>
																<p style="margin:0;">
																	<?php
																	$parts = array();
																	foreach ($social as $row) {
																		$parts[] = '<a href="' . esc_url($row['href']) . '" style="color:#fff202;font-weight:600;text-decoration:none;">' . esc_html($row['label']) . '</a>';
																	}
																	echo wp_kses_post(implode(' &nbsp;|&nbsp; ', $parts));
																	?>
																</p>
															<?php endif; ?>
															<p style="margin:14px 0 0;font-size:11px;line-height:1.5;color:#a8a8a8;">
																<?php esc_html_e('Mad Baits Supplies LTD · Company No. 15736640 · EC Animal Feed Hygiene GB702/00634', 'mad-baits'); ?>
															</p>
														</td>
													</tr>
													<tr>
														<td colspan="2" valign="middle" id="credit">
															<?php
															$email_footer_text = get_option('woocommerce_email_footer_text');

															if (apply_filters('woocommerce_is_email_preview', false)) {
																$text_transient    = get_transient('woocommerce_email_footer_text');
																$email_footer_text = false !== $text_transient ? $text_transient : $email_footer_text;
															}

															echo wp_kses_post(
																wpautop(
																	wptexturize(
																		apply_filters('woocommerce_email_footer_text', $email_footer_text, $email)
																	)
																)
															);
															?>
														</td>
													</tr>
												</table>
											</td>
										</tr>
									</table>
								</td>
							</tr>
						</table>
					</div>
				</td>
				<td><!-- spacer --></td>
			</tr>
		</table>
	</body>
</html>
