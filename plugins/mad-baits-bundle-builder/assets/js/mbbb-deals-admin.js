/**
 * Bundle Deals admin — template/custom build modes, preview, media picker.
 */
(function ($) {
	'use strict';

	var cfg = typeof mbbbDealsAdmin !== 'undefined' ? mbbbDealsAdmin : {};
	var i18n = cfg.i18n || {};
	var previewTimer = null;
	var includedIndex = 0;

	function esc(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	function isCustomMode() {
		return $('#mbbb-build-mode').val() === 'custom';
	}

	function syncBuildModeUi() {
		var custom = $('input[name="mbbb_build_mode_ui"]:checked').val() === 'custom';
		$('#mbbb-build-mode').val(custom ? 'custom' : 'template');
		$('.mbbb-deal-build-mode__option').toggleClass('is-selected', false);
		$('input[name="mbbb_build_mode_ui"]:checked').closest('.mbbb-deal-build-mode__option').addClass('is-selected');

		$('#mbbb-build-template').prop('hidden', custom);
		$('#mbbb-deal-template-choices').prop('hidden', custom);
		$('#mbbb-build-custom').prop('hidden', !custom);
		$('#mbbb-deal-custom-options').prop('hidden', !custom);

		$('#mbbb-build-template, #mbbb-deal-template-choices, #mbbb-build-custom, #mbbb-deal-custom-options').each(function () {
			var disabled = !!this.hidden;
			$(this).find(':input').prop('disabled', disabled);
		});

		syncPoolGroupVisibility();
	}

	function syncPoolGroupVisibility() {
		if (!isCustomMode()) {
			return;
		}
		var map = {
			hookbait: parseInt($('[name="custom_hookbait_choices"]').val(), 10) || 0,
			liquid: parseInt($('[name="custom_liquid_choices"]').val(), 10) || 0,
			dip: parseInt($('[name="custom_dip_choices"]').val(), 10) || 0,
			pellet: parseInt($('[name="custom_pellet_choices"]').val(), 10) || 0
		};
		Object.keys(map).forEach(function (key) {
			$('[data-mbbb-pool-group="' + key + '"]').prop('hidden', map[key] < 1);
		});
	}

	function getFormData() {
		var $form = $('#mbbb-deal-form');
		if (!$form.length) {
			return {};
		}
		syncBuildModeUi();
		var data = {};
		$.each($form.serializeArray(), function (_, field) {
			if (field.name.slice(-2) === '[]') {
				var key = field.name.slice(0, -2);
				if (!data[key]) {
					data[key] = [];
				}
				data[key].push(field.value);
			} else {
				data[field.name] = field.value;
			}
		});

		['include_hookbaits', 'include_liquids', 'include_dips', 'include_pellets', 'deal_active', 'free_shipping'].forEach(function (name) {
			data[name] = $form.find('[name="' + name + '"]:enabled').is(':checked') ? '1' : '';
		});

		if (!data.boilie_ranges) {
			data.boilie_ranges = [];
		}
		['hookbait_options', 'liquid_options', 'dip_options', 'pellet_options'].forEach(function (name) {
			if (!data[name]) {
				data[name] = [];
			}
		});

		return data;
	}

	function syncTemplateMeta() {
		var $selected = $('input[name="template_id"]:checked:enabled');
		if (!$selected.length) {
			return;
		}
		$('#mbbb-deal-type').val($selected.data('deal-type') || '');
		$('#mbbb-bundle-size').val($selected.data('bundle-size') || '');
		$('.mbbb-deal-template-card').removeClass('is-selected');
		$selected.closest('.mbbb-deal-template-card').addClass('is-selected');
	}

	function renderPreviewSteps(steps, errors, summary) {
		var $panel = $('#mbbb-deal-preview-steps');
		var $summaryTemplate = $('#mbbb-deal-customer-summary p');
		var $summaryCustom = $('#mbbb-deal-custom-summary p');

		if (summary) {
			if (isCustomMode()) {
				$summaryCustom.text(summary);
			} else {
				$summaryTemplate.text(summary);
			}
		}

		if (!$panel.length) {
			return;
		}

		if (errors && errors.length) {
			$panel.html('<div class="mbbb-deal-preview-error"><ul><li>' + errors.map(esc).join('</li><li>') + '</li></ul></div>');
			return;
		}

		if (!steps || !steps.length) {
			$panel.html('<p class="description mbbb-deal-preview-steps__empty">' + esc(i18n.selectTemplate || 'Choose a deal type to preview what the customer will select.') + '</p>');
			return;
		}

		var html = '<p><strong>' + esc(i18n.customerWillChoose || 'Customer will choose:') + '</strong></p>';
		html += '<ol class="mbbb-deal-preview-list">';
		steps.forEach(function (step, index) {
			var broken = step.is_broken ? ' is-broken' : '';
			var included = step.is_included ? ' is-included' : '';
			var label = step.label || ('Choice ' + (index + 1));
			html += '<li class="mbbb-deal-preview-step' + broken + included + '">' + esc(label);
			if (step.is_broken) {
				html += ' <span class="mbbb-deal-preview-step__warn">(no products available)</span>';
			}
			html += '</li>';
		});
		html += '</ol>';
		$panel.html(html);
	}

	function canPreview() {
		if (isCustomMode()) {
			return true;
		}
		return !!$('input[name="template_id"]:checked:enabled').val();
	}

	function refreshPreview() {
		if (!canPreview()) {
			renderPreviewSteps([], [], '');
			return;
		}

		$('#mbbb-deal-preview-steps').html('<p class="description">' + esc(i18n.previewLoading || 'Loading…') + '</p>');

		$.post(cfg.ajaxUrl, {
			action: 'mbbb_deal_preview',
			nonce: cfg.nonce,
			form: getFormData()
		})
			.done(function (response) {
				if (!response || !response.success) {
					renderPreviewSteps([], [response && response.data && response.data.message ? response.data.message : 'Preview failed.'], '');
					return;
				}
				renderPreviewSteps(response.data.steps, response.data.errors, response.data.summary);
			})
			.fail(function () {
				renderPreviewSteps([], ['Could not load preview.'], '');
			});
	}

	function debouncedPreview() {
		clearTimeout(previewTimer);
		previewTimer = setTimeout(refreshPreview, 350);
	}

	function initMediaPicker() {
		var frame;
		$('#mbbb-deal-image-select').on('click', function (e) {
			e.preventDefault();
			if (frame) {
				frame.open();
				return;
			}
			frame = wp.media({
				title: i18n.chooseImage || 'Choose deal image',
				button: { text: 'Use this image' },
				multiple: false
			});
			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				$('#mbbb-deal-image-id').val(attachment.id);
				var url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
				$('.mbbb-deal-image__preview').removeAttr('hidden').find('img').attr('src', url);
				$('#mbbb-deal-image-remove').removeAttr('hidden');
			});
			frame.open();
		});

		$('#mbbb-deal-image-remove').on('click', function (e) {
			e.preventDefault();
			$('#mbbb-deal-image-id').val('');
			$('.mbbb-deal-image__preview').attr('hidden', true).find('img').attr('src', '');
			$(this).attr('hidden', true);
		});
	}

	function initOptionsSearch() {
		var $search = $('#mbbb-deal-options-search');
		if (!$search.length) {
			return;
		}

		$search.on('input', function () {
			var query = String($search.val() || '').trim().toLowerCase();
			$('.mbbb-deal-pools-table tbody tr').each(function () {
				var blob = String($(this).attr('data-mbbb-option-search') || '').toLowerCase();
				var match = !query || blob.indexOf(query) !== -1;
				$(this).toggleClass('is-filter-hidden', !match);
			});

			if (query) {
				$('.mbbb-deal-pool-section').prop('open', true);
			}
		});
	}

	function initCountControls() {
		$(document).on('click', '.mbbb-deal-count-btn', function (e) {
			e.preventDefault();
			var $input = $(this).closest('.mbbb-deal-choice-count__controls').find('.mbbb-deal-count-input');
			var value = parseInt($input.val(), 10) || 0;
			var min = parseInt($input.attr('min'), 10) || 0;
			var max = parseInt($input.attr('max'), 10) || 20;
			if ($(this).data('action') === 'plus') {
				value = Math.min(max, value + 1);
			} else {
				value = Math.max(min, value - 1);
			}
			$input.val(value).trigger('change');
		});
	}

	function initIncludedProducts() {
		var $container = $('#mbbb-included-products');
		if (!$container.length) {
			return;
		}

		includedIndex = $container.find('.mbbb-included-product-row').length;

		$('#mbbb-included-product-add').on('click', function (e) {
			e.preventDefault();
			var index = includedIndex++;
			var row = '<div class="mbbb-included-product-row">' +
				'<label class="screen-reader-text">Included product</label>' +
				'<select class="wc-product-search mbbb-included-product-search" name="included_products[' + index + '][product_id]" data-placeholder="' + esc(i18n.searchProducts || 'Search WooCommerce products…') + '" data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true"></select>' +
				'<label><span>Quantity</span><input type="number" class="small-text" name="included_products[' + index + '][quantity]" min="1" step="1" value="1" /></label>' +
				'<button type="button" class="button button-link-delete mbbb-included-product-remove">Remove</button>' +
				'</div>';
			$container.append(row);
			initProductSearch($container.find('.mbbb-included-product-row').last());
		});

		$container.on('click', '.mbbb-included-product-remove', function (e) {
			e.preventDefault();
			var $rows = $container.find('.mbbb-included-product-row');
			if ($rows.length <= 1) {
				$(this).closest('.mbbb-included-product-row').find('select').val(null).trigger('change');
				$(this).closest('.mbbb-included-product-row').find('input[type="number"]').val(1);
				return;
			}
			$(this).closest('.mbbb-included-product-row').remove();
			debouncedPreview();
		});
	}

	function initProductSearch($scope) {
		if (typeof $.fn.selectWoo !== 'function') {
			return;
		}
		($scope || $(document)).find('.wc-product-search').each(function () {
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

	function initOptionGroupToggles() {
		$(document).on('click', '.mbbb-deal-select-all', function (e) {
			e.preventDefault();
			var $fieldset = $(this).closest('fieldset');
			$fieldset.find('input[type="checkbox"]').prop('checked', true);
			debouncedPreview();
		});

		$(document).on('click', '.mbbb-deal-deselect-all', function (e) {
			e.preventDefault();
			var $fieldset = $(this).closest('fieldset');
			$fieldset.find('input[type="checkbox"]').prop('checked', false);
			debouncedPreview();
		});
	}

	$(function () {
		syncBuildModeUi();
		syncTemplateMeta();
		debouncedPreview();
		initMediaPicker();
		initOptionsSearch();
		initCountControls();
		initIncludedProducts();
		initProductSearch();
		initOptionGroupToggles();

		$(document).on('change', 'input[name="mbbb_build_mode_ui"]', function () {
			syncBuildModeUi();
			debouncedPreview();
		});

		$(document).on('change', 'input[name="template_id"]', function () {
			syncTemplateMeta();
			debouncedPreview();
		});

		$('#mbbb-deal-form').on('change input', 'input, textarea, select', function () {
			if ($(this).attr('name') === 'template_id' || $(this).attr('name') === 'mbbb_build_mode_ui') {
				return;
			}
			if ($(this).hasClass('mbbb-deal-count-input')) {
				syncPoolGroupVisibility();
			}
			debouncedPreview();
		});

		$('[data-mbbb-confirm-trash="1"]').on('click', function (e) {
			if (!window.confirm(i18n.confirmTrash || 'Move this deal to trash?')) {
				e.preventDefault();
			}
		});
	});
}(jQuery));
