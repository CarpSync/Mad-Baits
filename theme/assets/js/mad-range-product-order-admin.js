(function ($) {
	'use strict';

	function initRangeProductOrder() {
		var $list = $('#mad-range-product-order-list');
		if (!$list.length) {
			return;
		}

		if (!$.fn.sortable) {
			return;
		}

		if ($list.hasClass('ui-sortable')) {
			$list.sortable('destroy');
		}

		$list.sortable({
			axis: 'y',
			handle: '.mad-range-product-order__handle',
			placeholder: 'mad-range-product-order__placeholder',
			forcePlaceholderSize: true,
			tolerance: 'pointer',
			cursor: 'grabbing',
		});

		$('#mad-range-product-order-save').off('click.madRangeOrder').on('click.madRangeOrder', function () {
			var $btn = $(this);
			var $status = $('#mad-range-product-order-status');
			var config = window.madBaitsRangeProductOrder || {};
			var ids = $list
				.children('.mad-range-product-order__item')
				.map(function () {
					return $(this).data('product-id');
				})
				.get();

			$btn.prop('disabled', true);
			$status.text(config.i18nSaving || 'Saving…');

			$.post(config.ajaxUrl || ajaxurl, {
				action: 'mad_baits_save_range_product_order',
				nonce: $list.data('nonce'),
				range_slug: $list.data('range'),
				product_ids: ids,
			})
				.done(function (response) {
					if (response && response.success) {
						$status.text(
							(response.data && response.data.message) ||
								config.i18nSaved ||
								'Saved.'
						);
					} else {
						$status.text(
							(response && response.data && response.data.message) ||
								config.i18nSaveFailed ||
								'Save failed.'
						);
					}
				})
				.fail(function () {
					$status.text(config.i18nSaveFailedRetry || 'Save failed. Try again.');
				})
				.always(function () {
					$btn.prop('disabled', false);
				});
		});
	}

	$(function () {
		$('#mad-range-product-order-range').on('change', function () {
			$(this).closest('form').trigger('submit');
		});

		initRangeProductOrder();
	});
})(jQuery);
