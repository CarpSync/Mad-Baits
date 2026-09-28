/**
 * Bundle Product Options admin — add/bulk add, suggestions, product search.
 */
(function ($) {
	'use strict';

	var cfg = typeof mbbbPoolOptionsAdmin !== 'undefined' ? mbbbPoolOptionsAdmin : {};
	var i18n = cfg.i18n || {};
	var types = cfg.types || {};
	var ranges = cfg.ranges || {};
	var bulkIndex = 0;

	function esc(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	function initProductSearch($scope) {
		if (typeof $.fn.selectWoo !== 'function') {
			return;
		}
		($scope || $(document)).find('.mbbb-pool-product-search').each(function () {
			var $el = $(this);
			if ($el.data('select2')) {
				return;
			}
			$el.selectWoo({
				allowClear: true,
				placeholder: $el.data('placeholder') || (i18n.searchProducts || 'Search products…'),
				minimumInputLength: 2,
				escapeMarkup: function (markup) {
					return markup;
				},
				ajax: {
					url: cfg.ajaxUrl,
					dataType: 'json',
					delay: 250,
					data: function (params) {
						return {
							term: params.term,
							action: $el.data('action') || 'woocommerce_json_search_products_and_variations',
							security: cfg.searchNonce || ''
						};
					},
					processResults: function (data) {
						var terms = [];
						if (data) {
							$.each(data, function (id, text) {
								terms.push({ id: id, text: text });
							});
						}
						return { results: terms };
					},
					cache: true
				}
			});
		});
	}

	function openModal($modal) {
		$modal.prop('hidden', false).addClass('is-open');
	}

	function closeModal($modal) {
		$modal.prop('hidden', true).removeClass('is-open');
	}

	function fetchProductSuggest(productId) {
		return $.post(cfg.ajaxUrl, {
			action: 'mbbb_pool_product_suggest',
			nonce: cfg.nonce,
			product_id: productId
		});
	}

	function applySuggestToAddForm(data) {
		var $dup = $('#mbbb-add-duplicate-notice');
		$dup.prop('hidden', true).empty();

		if (data.duplicate) {
			$dup
				.prop('hidden', false)
				.html(
					esc(i18n.alreadyIn || 'Already available in') +
						' <strong>' +
						esc(data.duplicate.type_label) +
						'</strong> ' +
						esc('options.')
				);
		}

		if (data.type && data.type.type) {
			$('#mbbb-add-bundle-type').val(data.type.type);
			var note = (i18n.suggestedType || 'Suggested type') + ': ' + (data.type.label || data.type.type);
			if (data.type.reason) {
				note += ' — ' + data.type.reason;
			}
			$('#mbbb-add-type-suggestion').text(note);
		} else {
			$('#mbbb-add-type-suggestion').text('');
		}

		if (data.range && data.range.slug) {
			if ($('#mbbb-add-range-slug option[value="' + data.range.slug + '"]').length) {
				$('#mbbb-add-range-slug').val(data.range.slug);
			} else {
				$('#mbbb-add-range-slug').val('');
				$('#mbbb-add-range-label').val(data.range.label || data.range.slug);
				$('#mbbb-add-register-range').prop('checked', true);
			}
			var rangeNote = 'Suggested range: ' + (data.range.label || data.range.slug);
			if (data.range.confidence === 'low') {
				rangeNote += ' — please confirm';
			}
			$('#mbbb-add-range-suggestion').text(rangeNote);
		} else {
			$('#mbbb-add-range-suggestion').text('');
		}

		if (data.suggested_label) {
			$('#mbbb-add-customer-label').val(data.suggested_label);
		}
	}

	function typeOptionsHtml(selected) {
		var html = '';
		$.each(types, function (key, meta) {
			html +=
				'<option value="' +
				esc(key) +
				'"' +
				(selected === key ? ' selected' : '') +
				'>' +
				esc(meta.label || key) +
				'</option>';
		});
		return html;
	}

	function rangeOptionsHtml(selected) {
		var html = '<option value="">—</option>';
		$.each(ranges, function (slug, label) {
			html +=
				'<option value="' +
				esc(slug) +
				'"' +
				(selected === slug ? ' selected' : '') +
				'>' +
				esc(label) +
				'</option>';
		});
		return html;
	}

	function addBulkRow(data) {
		var productId = String(data.product_id);
		if ($('#mbbb-bulk-staging-table tbody tr[data-product-id="' + productId + '"]').length) {
			return;
		}

		var idx = bulkIndex++;
		var type = (data.type && data.type.type) || '';
		var rangeSlug = (data.range && data.range.slug) || '';
		var dup = data.duplicate
			? '<div class="mbbb-pool-inline-notice notice notice-warning inline">' +
			  esc(i18n.alreadyIn || 'Already available in') +
			  ' ' +
			  esc(data.duplicate.type_label) +
			  '</div>'
			: '';

		var row =
			'<tr data-product-id="' +
			esc(productId) +
			'">' +
			'<th scope="row" class="check-column">' +
			'<input type="checkbox" name="bulk_rows[' +
			idx +
			'][selected]" value="1" checked' +
			(data.duplicate ? ' disabled' : '') +
			' />' +
			'<input type="hidden" name="bulk_rows[' +
			idx +
			'][product_id]" value="' +
			esc(productId) +
			'" />' +
			'</th>' +
			'<td>' +
			esc(data.display_name || '') +
			dup +
			'</td>' +
			'<td><select name="bulk_rows[' +
			idx +
			'][type]" required>' +
			'<option value="">Choose…</option>' +
			typeOptionsHtml(type) +
			'</select></td>' +
			'<td><input type="text" class="regular-text" name="bulk_rows[' +
			idx +
			'][label]" value="' +
			esc(data.suggested_label || data.display_name || '') +
			'" /></td>' +
			'<td><select name="bulk_rows[' +
			idx +
			'][range_slug]">' +
			rangeOptionsHtml(rangeSlug) +
			'</select>' +
			'<input type="hidden" name="bulk_rows[' +
			idx +
			'][range_label]" value="' +
			esc((data.range && data.range.label) || '') +
			'" />' +
			'</td>' +
			'<td><label><input type="checkbox" name="bulk_rows[' +
			idx +
			'][visible]" value="1" checked /> Visible</label></td>' +
			'<td><button type="button" class="button-link-delete mbbb-bulk-row-remove">Remove</button></td>' +
			'</tr>';

		$('#mbbb-bulk-staging-table tbody').append(row);
	}

	function resetAddForm() {
		$('#mbbb-add-wc-product-id').val('');
		$('#mbbb-add-product-search').val(null).trigger('change');
		$('#mbbb-add-bundle-type').val('');
		$('#mbbb-add-customer-label').val('');
		$('#mbbb-add-range-slug').val('');
		$('#mbbb-add-range-label').val('');
		$('#mbbb-add-register-range').prop('checked', false);
		$('#mbbb-add-type-suggestion, #mbbb-add-range-suggestion').text('');
		$('#mbbb-add-duplicate-notice').prop('hidden', true).empty();
	}

	function prefillAddFromButton($btn) {
		var productId = $btn.data('product-id');
		if (!productId) {
			return;
		}
		openModal($('#mbbb-add-option-modal'));
		resetAddForm();
		$('#mbbb-add-wc-product-id').val(productId);
		var type = $btn.data('type');
		if (type) {
			$('#mbbb-add-bundle-type').val(type);
		}
		var rangeSlug = $btn.data('range-slug');
		if (rangeSlug && $('#mbbb-add-range-slug option[value="' + rangeSlug + '"]').length) {
			$('#mbbb-add-range-slug').val(rangeSlug);
		} else if (rangeSlug) {
			$('#mbbb-add-range-label').val($btn.data('range-label') || rangeSlug);
			$('#mbbb-add-register-range').prop('checked', true);
		}
		$('#mbbb-add-customer-label').val($btn.data('name') || '');
	}

	$(function () {
		if (!$('.mbbb-deals-wrap--options').length) {
			return;
		}

		initProductSearch();

		$('#mbbb-open-add-option').on('click', function () {
			resetAddForm();
			openModal($('#mbbb-add-option-modal'));
		});

		$('#mbbb-open-bulk-add').on('click', function () {
			$('#mbbb-bulk-staging-table tbody').empty();
			bulkIndex = 0;
			$('#mbbb-bulk-product-search').val(null).trigger('change');
			openModal($('#mbbb-bulk-add-modal'));
		});

		$(document).on('click', '[data-mbbb-close-modal], .mbbb-pool-modal__backdrop', function () {
			closeModal($(this).closest('.mbbb-pool-modal'));
		});

		$('#mbbb-add-product-search').on('change', function () {
			var productId = $(this).val();
			$('#mbbb-add-wc-product-id').val(productId || '');
			if (!productId) {
				return;
			}
			fetchProductSuggest(productId).done(function (response) {
				if (response && response.success) {
					applySuggestToAddForm(response.data);
				}
			});
		});

		$('#mbbb-bulk-product-search').on('change', function () {
			var productId = $(this).val();
			if (!productId) {
				return;
			}
			var $select = $(this);
			fetchProductSuggest(productId).done(function (response) {
				if (response && response.success) {
					addBulkRow($.extend({ product_id: productId }, response.data));
				}
				$select.val(null).trigger('change');
			});
		});

		$('#mbbb-bulk-select-all').on('change', function () {
			var checked = $(this).is(':checked');
			$('#mbbb-bulk-staging-table tbody input[type="checkbox"]:not(:disabled)').prop('checked', checked);
		});

		$('#mbbb-bulk-staging-table').on('click', '.mbbb-bulk-row-remove', function () {
			$(this).closest('tr').remove();
		});

		$('#mbbb-bulk-range-slug').on('change', function () {
			var slug = $(this).val();
			if (slug && ranges[slug]) {
				$('#mbbb-bulk-range-label').val(ranges[slug]);
			}
		});

		$(document).on('click', '.mbbb-suggestion-add', function () {
			prefillAddFromButton($(this));
		});

		$('#mbbb-add-pool-option-form').on('submit', function (e) {
			if (!$('#mbbb-add-wc-product-id').val()) {
				e.preventDefault();
				window.alert(i18n.searchProducts || 'Choose a product first.');
				return;
			}
			if (!$('#mbbb-add-bundle-type').val()) {
				e.preventDefault();
				window.alert(i18n.confirmType || 'Please confirm the bundle type.');
			}
		});

		$(document).on('click', '.mbbb-pool-remove-btn', function (e) {
			var usage = parseInt($(this).data('usage'), 10) || 0;
			var formId = $(this).attr('form');
			var $form = formId ? $('#' + formId) : $(this).closest('form');
			var msg = i18n.confirmRemove || 'Remove this option from shared bundle product options?';
			if (usage > 0) {
				msg = i18n.confirmRemoveUsed || 'This option is used by live deals. Remove it anyway?';
				$form.find('.mbbb-confirm-remove-used').val('1');
			}
			if (!window.confirm(msg)) {
				e.preventDefault();
			}
		});
	});
})(jQuery);
