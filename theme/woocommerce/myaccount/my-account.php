<?php
/**
 * My Account page layout (Mad Baits).
 *
 * Single navigation + main content column. Required Woo hooks preserved.
 *
 * @package MadBaits
 * @see     https://woocommerce.com/document/template-structure/
 * @version 3.5.0
 */

defined('ABSPATH') || exit;

wc_print_notices();
?>
<div class="woocommerce mad-myaccount-woocommerce">
	<div class="mad-myaccount-layout">
		<?php do_action('woocommerce_account_navigation'); ?>
		<div class="mad-myaccount-layout__main">
			<?php do_action('woocommerce_account_content'); ?>
		</div>
	</div>
</div>
