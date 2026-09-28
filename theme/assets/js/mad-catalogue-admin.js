(function ($) {
	'use strict';

	function initBulkTagMultiselect() {
		var $select = $('#mad-catalogue-bulk-tag-select');
		if (!$select.length) {
			return;
		}

		if ($.fn.selectWoo) {
			if ($select.hasClass('select2-hidden-accessible')) {
				$select.selectWoo('destroy');
			}
			$select.selectWoo({
				width: '100%',
				placeholder: $select.data('placeholder') || 'Search and select tags…',
				allowClear: true,
				closeOnSelect: false,
			});
		}
	}

	function getSelectedProductIds() {
		return $('.mad-catalogue-row-check:checked')
			.map(function () {
				return parseInt($(this).val(), 10);
			})
			.get()
			.filter(function (id) {
				return id > 0;
			});
	}

	function getSelectedTagIds() {
		var $select = $('#mad-catalogue-bulk-tag-select');
		var val = $select.val();
		if (!val) {
			return [];
		}
		return (Array.isArray(val) ? val : [val])
			.map(function (id) {
				return parseInt(id, 10);
			})
			.filter(function (id) {
				return id > 0;
			});
	}

	function getSelectedTagLabels() {
		var labels = [];
		$('#mad-catalogue-bulk-tag-select option:selected').each(function () {
			var label = $(this).data('label') || $(this).text();
			label = String(label || '').trim();
			if (label) {
				labels.push(label);
			}
		});
		return labels;
	}

	function mergeTagFieldValue(current, additions) {
		var parts = String(current || '')
			.split(',')
			.map(function (part) {
				return part.trim();
			})
			.filter(Boolean);
		var seen = {};
		parts.forEach(function (part) {
			seen[part.toLowerCase()] = part;
		});
		additions.forEach(function (label) {
			var key = label.toLowerCase();
			if (!seen[key]) {
				seen[key] = label;
				parts.push(label);
			}
		});
		return parts.join(', ');
	}

	$(function () {
		initBulkTagMultiselect();

		var $selectAll = $('#mad-catalogue-select-all');
		if ($selectAll.length) {
			$selectAll.on('change', function () {
				var checked = $selectAll.is(':checked');
				$('.mad-catalogue-row-check').prop('checked', checked);
			});
		}

		$(document).on('change', '.mad-catalogue-row-check', function () {
			var $all = $('.mad-catalogue-row-check');
			var $checked = $all.filter(':checked');
			$selectAll.prop('checked', $all.length > 0 && $checked.length === $all.length);
		});

		$('#mad-catalogue-fill-tag-fields').on('click', function () {
			var config = window.madCatalogueAdmin || {};
			var $status = $('#mad-catalogue-bulk-tags-status');
			var productIds = getSelectedProductIds();
			var labels = getSelectedTagLabels();

			if (!productIds.length) {
				$status.text(config.i18nSelectProducts || 'Select at least one product.');
				return;
			}
			if (!labels.length) {
				$status.text(config.i18nSelectTags || 'Select at least one tag.');
				return;
			}

			productIds.forEach(function (productId) {
				var $field = $('input[name="product_override[' + productId + '][tags]"]');
				if (!$field.length) {
					return;
				}
				$field.val(mergeTagFieldValue($field.val(), labels));
			});

			$status.text(
				(config.i18nFilledFields || 'Copied tags into %d product field(s).').replace(
					'%d',
					String(productIds.length)
				)
			);
		});

		$('#mad-catalogue-bulk-assign-tags').on('click', function () {
			var $btn = $(this);
			var $status = $('#mad-catalogue-bulk-tags-status');
			var config = window.madCatalogueAdmin || {};
			var productIds = getSelectedProductIds();
			var tagIds = getSelectedTagIds();

			if (!productIds.length) {
				$status.text(config.i18nSelectProducts || 'Select at least one product.');
				return;
			}
			if (!tagIds.length) {
				$status.text(config.i18nSelectTags || 'Select at least one tag.');
				return;
			}
			if (
				!window.confirm(
					config.i18nConfirmBulkAssign ||
						'Add the selected tags to the checked products?'
				)
			) {
				return;
			}

			$btn.prop('disabled', true);
			$status.text(config.i18nAssigningTags || 'Assigning tags…');

			$.post(config.ajaxUrl || ajaxurl, {
				action: 'mad_baits_catalogue_bulk_assign_tags',
				nonce: $btn.data('nonce'),
				product_ids: productIds,
				tag_ids: tagIds,
			})
				.done(function (response) {
					if (response && response.success) {
						$status.text(
							(response.data && response.data.message) ||
								'Tags assigned.'
						);
					} else {
						$status.text(
							(response && response.data && response.data.message) ||
								'Assign failed.'
						);
					}
				})
				.fail(function () {
					$status.text(config.i18nAssignFailed || 'Assign failed. Try again.');
				})
				.always(function () {
					$btn.prop('disabled', false);
				});
		});
	});
})(jQuery);
