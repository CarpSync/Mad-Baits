/**
 * Bundle Deals product meta box — variable product variation controls.
 */
(function ($) {
	'use strict';

	function syncVariationRowState($checkbox) {
		var $row = $checkbox.closest('.mbbb-variation-bundle-option');
		$row.toggleClass('is-enabled', $checkbox.is(':checked'));
	}

	$(function () {
		var $wrap = $('.mbbb-variation-bundle-options');
		if (!$wrap.length) {
			return;
		}

		$wrap.on('change', '.mbbb-variation-bundle-enable', function () {
			syncVariationRowState($(this));
		});

		$('.mbbb-variation-select-all').on('click', function (e) {
			e.preventDefault();
			$wrap.find('.mbbb-variation-bundle-enable').prop('checked', true).each(function () {
				syncVariationRowState($(this));
			});
		});

		$('.mbbb-variation-deselect-all').on('click', function (e) {
			e.preventDefault();
			$wrap.find('.mbbb-variation-bundle-enable').prop('checked', false).each(function () {
				syncVariationRowState($(this));
			});
		});
	});
}(jQuery));
