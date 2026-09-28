/**
 * Mad Baits Bundle Builder — product edit admin UI.
 *
 * Data flow:
 * - slots[] is the in-memory source of truth
 * - Every change syncs to #mbbb_slots_json (posted as mbbb_slots_json)
 * - PHP sanitizes → migrates attribute links → validates → WC product meta save
 *
 * Slot shape (must stay compatible with frontend resolve_slot_options):
 * { label, key, required, min, max, help, source, pool_id, attribute,
 *   category_id, tag_id, product_ids, display, manual_options, allow_same, use_images }
 */
(function ($) {
	'use strict';

	var $list = $('#mbbb-slots-list');
	var $json = $('#mbbb_slots_json');
	if (!$list.length || !$json.length) {
		return;
	}

	var cfg = typeof mbbbAdmin !== 'undefined' ? mbbbAdmin : {};
	var pools = cfg.pools || [];
	var presets = cfg.presets || [];
	var variationAttributes = cfg.variationAttributes || [];
	var slotsFromVariations = cfg.slotsFromVariations || [];
	var i18n = cfg.i18n || {};
	var slots = [];
	var $summaryBody = $('#mbbb-slots-summary-body');
	var $validation = $('#mbbb-admin-validation');
	var $preview = $('#mbbb-customer-preview');
	var $postForm = $('#post');
	var previewTimer = null;
	var previewRequest = null;

	try {
		slots = JSON.parse($json.val() || '[]');
		if (!Array.isArray(slots)) {
			slots = [];
		}
	} catch (e) {
		slots = [];
	}

	function esc(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	function slugify(val) {
		return String(val || '')
			.toLowerCase()
			.replace(/[^a-z0-9]+/g, '-')
			.replace(/^-+|-+$/g, '') || 'slot';
	}

	function normalizeLookup(val) {
		return String(val || '')
			.toLowerCase()
			.replace(/&amp;/g, 'and')
			.replace(/&/g, 'and')
			.replace(/[^a-z0-9]+/g, '');
	}

	function sourceLabel(source) {
		var map = {
			attribute: i18n.sourceAttribute || 'Product attribute',
			global_pool: i18n.sourcePool || 'Global pool',
			manual: i18n.sourceManual || 'Manual options',
			category: i18n.sourceCategory || 'Product category',
			tag: i18n.sourceTag || 'Product tag',
			products: i18n.sourceProducts || 'Manual products'
		};
		return map[source] || source || '—';
	}

	function attributeLabel(key) {
		if (!key) {
			return '—';
		}
		var needle = normalizeLookup(key);
		for (var i = 0; i < variationAttributes.length; i++) {
			var attr = variationAttributes[i];
			if (normalizeLookup(attr.key) === needle || normalizeLookup(attr.label) === needle) {
				return attr.label + ' (' + attr.key + ')';
			}
		}
		return key;
	}

	function findAttributeKey(hint) {
		var needle = normalizeLookup(hint);
		if (!needle) {
			return '';
		}
		var fuzzy = '';
		var fuzzyLen = 0;
		for (var i = 0; i < variationAttributes.length; i++) {
			var attr = variationAttributes[i];
			var candidates = [attr.key, attr.label, String(attr.key || '').replace(/^pa_/, '')];
			for (var c = 0; c < candidates.length; c++) {
				var n = normalizeLookup(candidates[c]);
				if (!n) {
					continue;
				}
				if (n === needle) {
					return attr.key;
				}
				if (n.indexOf(needle) !== -1 || needle.indexOf(n) !== -1) {
					var overlap = Math.min(n.length, needle.length);
					if (overlap > fuzzyLen) {
						fuzzyLen = overlap;
						fuzzy = attr.key;
					}
				}
			}
		}
		return fuzzy;
	}

	function slotHasSource(slot) {
		var source = slot.source || '';
		if (source === 'attribute') {
			return true;
		}
		if (source === 'global_pool') {
			return !!(slot.pool_id && String(slot.pool_id).trim());
		}
		if (source === 'manual') {
			return Array.isArray(slot.manual_options) && slot.manual_options.length > 0;
		}
		if (source === 'category') {
			return parseInt(slot.category_id, 10) > 0;
		}
		if (source === 'tag') {
			return parseInt(slot.tag_id, 10) > 0;
		}
		if (source === 'products') {
			var ids = slot.product_ids;
			if (typeof ids === 'string') {
				return ids.replace(/[^\d,]/g, '').replace(/^,+|,+$/g, '').length > 0;
			}
			return Array.isArray(ids) && ids.filter(Boolean).length > 0;
		}
		return false;
	}

	function manualOptionsToText(options) {
		if (!Array.isArray(options) || !options.length) {
			return '';
		}
		return options.map(function (row) {
			var label = row.label || '';
			var value = row.value || '';
			if (value && slugify(label) !== slugify(value)) {
				return label + '|' + value;
			}
			return label;
		}).join('\n');
	}

	function textToManualOptions(text) {
		var lines = String(text || '').split(/\r?\n/);
		var out = [];
		lines.forEach(function (line) {
			line = $.trim(line);
			if (!line) {
				return;
			}
			var parts = line.split('|');
			var label = $.trim(parts[0] || '');
			if (!label) {
				return;
			}
			var value = $.trim(parts[1] || '') || slugify(label);
			out.push({
				label: label,
				value: value,
				product_id: 0,
				image: '',
				active: true
			});
		});
		return out;
	}

	function productIdsToText(ids) {
		if (typeof ids === 'string') {
			return ids;
		}
		if (!Array.isArray(ids)) {
			return '';
		}
		return ids.filter(Boolean).join(', ');
	}

	function textToProductIds(text) {
		return String(text || '')
			.split(/[\s,]+/)
			.map(function (part) {
				return parseInt(part, 10) || 0;
			})
			.filter(Boolean);
	}

	function defaultSlot() {
		return {
			label: 'New Slot',
			key: 'new-slot',
			required: true,
			min: 1,
			max: 1,
			help: '',
			source: 'attribute',
			pool_id: pools[0] ? pools[0].id : '',
			attribute: '',
			category_id: 0,
			tag_id: 0,
			product_ids: [],
			display: 'cards',
			allow_same: false,
			use_images: false,
			manual_options: []
		};
	}

	function syncJson() {
		// Strip client-only flags before posting to PHP.
		var payload = slots.map(function (slot) {
			var copy = $.extend(true, {}, slot);
			delete copy._keyTouched;
			return copy;
		});
		$json.val(JSON.stringify(payload));
	}

	function slotTitle(slot, index) {
		var label = slot && slot.label ? String(slot.label) : '';
		return (i18n.slotLabel || 'Slot %d').replace('%d', String(index + 1)) + (label ? ': ' + label : '');
	}

	function destroySortable() {
		if ($list.hasClass('ui-sortable')) {
			$list.sortable('destroy');
		}
	}

	function scrollListTo($el) {
		var $wrap = $list.closest('.mbbb-slots-admin__list-wrap');
		if (!$wrap.length || !$el.length) {
			return;
		}
		var top = $el.offset().top - $wrap.offset().top + $wrap.scrollTop() - 12;
		$wrap.animate({ scrollTop: Math.max(0, top) }, 180);
	}

	function attributeOptionsHtml(selected) {
		var html = '<option value="">' + esc(i18n.unlinked || '— Not linked —') + '</option>';
		variationAttributes.forEach(function (attr) {
			var sel = selected && (attr.key === selected || normalizeLookup(attr.key) === normalizeLookup(selected)) ? ' selected' : '';
			html += '<option value="' + esc(attr.key) + '"' + sel + '>' +
				esc(attr.label) + ' (' + esc(attr.key) + ') — ' + esc(String(attr.value_count)) + ' options</option>';
		});
		return html;
	}

	function poolOptionsHtml(selected) {
		return pools.map(function (p) {
			return '<option value="' + esc(p.id) + '"' + (selected === p.id ? ' selected' : '') + '>' + esc(p.name) + '</option>';
		}).join('');
	}

	function renderSummary() {
		if (!$summaryBody.length) {
			return;
		}
		if (!slots.length) {
			$summaryBody.html(
				'<tr class="mbbb-slots-summary__empty"><td colspan="7">' +
				esc(i18n.emptySummary || 'No slots yet.') +
				'</td></tr>'
			);
			return;
		}
		var rows = slots.map(function (slot, index) {
			var linked = slot.attribute || findAttributeKey(slot.label) || findAttributeKey(slot.key) || '';
			return '<tr>' +
				'<td>' + (index + 1) + '</td>' +
				'<td>' + esc(slot.label || '') + '</td>' +
				'<td><code>' + esc(slot.key || '') + '</code></td>' +
				'<td>' + (slot.required ? '✓' : '—') + '</td>' +
				'<td>' + esc(sourceLabel(slot.source)) + '</td>' +
				'<td>' + esc(attributeLabel(linked)) + '</td>' +
				'<td>' + esc(slot.display || 'cards') + '</td>' +
				'</tr>';
		});
		$summaryBody.html(rows.join(''));
	}

	function isBuilderEnabled() {
		return $('#mbbb_enabled').is(':checked');
	}

	function validateSlots() {
		var errors = [];
		var keys = {};

		if (isBuilderEnabled() && !slots.length) {
			errors.push(i18n.enabledNoSlots || 'Bundle Builder is enabled but no slots are configured.');
			return errors;
		}

		slots.forEach(function (slot, index) {
			var label = $.trim(slot.label || '');
			var key = slugify(slot.key || label);
			var n = index + 1;

			if (!label) {
				errors.push((i18n.missingLabel || 'Each slot needs a label.') + ' (#' + n + ')');
				return;
			}

			if (keys[key]) {
				errors.push((i18n.duplicateKey || 'Duplicate slot key: %s').replace('%s', key));
			} else {
				keys[key] = true;
			}

			if (slot.required && !slotHasSource(slot)) {
				errors.push((i18n.requiredNoSource || 'Required slot “%s” needs a source.').replace('%s', label));
			}

			if (slot.attribute) {
				var matched = findAttributeKey(slot.attribute);
				if (variationAttributes.length && !matched) {
					errors.push((i18n.attrNotFound || 'Variation attribute “%s” was not found.').replace('%s', slot.attribute));
				}
			} else if (slot.source === 'attribute' && slot.required && variationAttributes.length) {
				var auto = findAttributeKey(slot.label) || findAttributeKey(slot.key);
				if (!auto) {
					errors.push((i18n.attrMissing || 'Slot “%s” needs a linked variation attribute.').replace('%s', label));
				}
			}
		});

		return errors;
	}

	function renderCustomerPreviewLocal() {
		if (!$preview.length) {
			return;
		}
		if (!slots.length) {
			$preview.html(
				'<p class="mbbb-customer-preview__empty">' +
				esc(i18n.previewEmpty || 'Add slots to preview the customer steps.') +
				'</p>'
			);
			return;
		}

		var html = '<ol class="mbbb-customer-preview__steps">';
		slots.forEach(function (slot, index) {
			var label = $.trim(slot.label || '') || ((i18n.slotLabel || 'Slot %d').replace('%d', String(index + 1)));
			var required = !!slot.required;
			var min = parseInt(slot.min, 10) || 1;
			var max = parseInt(slot.max, 10) || min;
			html +=
				'<li class="mbbb-customer-preview__step' + (required && !slotHasSource(slot) ? ' is-broken' : '') + '">' +
				'<div class="mbbb-customer-preview__step-head">' +
				'<strong>' + esc(String(index + 1) + '. ' + label) + '</strong>' +
				'<span class="mbbb-customer-preview__badge">' +
				esc(required ? (i18n.previewRequired || 'Required') : (i18n.previewOptional || 'Optional')) +
				'</span>' +
				'</div>' +
				'<div class="mbbb-customer-preview__meta">' +
				esc(sourceLabel(slot.source)) +
				' · ' +
				esc((i18n.previewQty || 'Qty %1$d–%2$d').replace('%1$d', String(min)).replace('%2$d', String(Math.max(min, max)))) +
				'</div>' +
				'</li>';
		});
		html += '</ol>';
		$preview.html(html);
	}

	function applyServerPreview(steps) {
		if (!$preview.length || !Array.isArray(steps) || !steps.length) {
			renderCustomerPreviewLocal();
			return;
		}
		var html = '<ol class="mbbb-customer-preview__steps">';
		steps.forEach(function (step) {
			var broken = !!step.is_broken;
			html +=
				'<li class="mbbb-customer-preview__step' + (broken ? ' is-broken' : '') + '">' +
				'<div class="mbbb-customer-preview__step-head">' +
				'<strong>' + esc(String(step.index || '') + '. ' + (step.label || '')) + '</strong>' +
				'<span class="mbbb-customer-preview__badge">' +
				esc(step.required ? (i18n.previewRequired || 'Required') : (i18n.previewOptional || 'Optional')) +
				'</span>' +
				'</div>' +
				'<div class="mbbb-customer-preview__meta">' +
				esc(sourceLabel(step.source)) +
				' · ' +
				esc((i18n.previewQty || 'Qty %1$d–%2$d').replace('%1$d', String(step.min || 1)).replace('%2$d', String(step.max || 1))) +
				' · ' +
				esc((i18n.previewOptions || 'Options: %d').replace('%d', String(step.option_count || 0))) +
				(step.default_label
					? ' · ' + esc((i18n.previewDefault || 'Default: %s').replace('%s', String(step.default_label)))
					: '') +
				(broken ? ' · <em>' + esc(i18n.previewBroken || 'Broken — no options') + '</em>' : '') +
				'</div>' +
				'</li>';
		});
		html += '</ol>';
		$preview.html(html);
	}

	function schedulePreviewRefresh() {
		if (!$preview.length) {
			return;
		}
		renderCustomerPreviewLocal();
		window.clearTimeout(previewTimer);
		previewTimer = window.setTimeout(function () {
			if (!cfg.ajaxUrl || !cfg.previewNonce) {
				return;
			}
			if (previewRequest && previewRequest.abort) {
				previewRequest.abort();
			}
			$preview.attr('data-loading', '1');
			previewRequest = $.post(cfg.ajaxUrl, {
				action: 'mbbb_admin_preview_slots',
				nonce: cfg.previewNonce,
				product_id: cfg.productId || 0,
				slots: JSON.stringify(slots)
			})
				.done(function (response) {
					if (response && response.success && response.data && response.data.steps) {
						applyServerPreview(response.data.steps);
					}
				})
				.always(function () {
					$preview.removeAttr('data-loading');
				});
		}, 350);
	}

	function showValidation(errors) {
		if (!$validation.length) {
			return;
		}
		if (!errors.length) {
			$validation.attr('hidden', true).empty().removeClass('is-visible');
			$list.find('.mbbb-slot-row').removeClass('has-error');
			return;
		}
		var html = '<strong>' + esc(i18n.fixBlocked || 'Fix Bundle Builder errors before saving.') + '</strong><ul>';
		errors.forEach(function (err) {
			html += '<li>' + esc(err) + '</li>';
		});
		html += '</ul>';
		$validation.html(html).removeAttr('hidden').addClass('is-visible');
	}

	function updateSourceVisibility($row, source) {
		$row.find('[data-source-panel]').attr('hidden', true);
		$row.find('[data-source-panel="' + source + '"]').removeAttr('hidden');
	}

	function buildRow(slot, index) {
		var collapsed = index > 1 ? ' is-collapsed' : '';
		var source = slot.source || 'attribute';

		var $row = $(
			'<div class="mbbb-slot-row' + collapsed + '" data-index="' + index + '">' +
			'<div class="mbbb-slot-row__head">' +
			'<button type="button" class="button button-small mbbb-slot-row__toggle" aria-expanded="' + (collapsed ? 'false' : 'true') + '">' +
			(collapsed ? (i18n.expand || 'Show') : (i18n.collapse || 'Hide')) +
			'</button>' +
			'<p class="mbbb-slot-row__title">' + esc(slotTitle(slot, index)) + '</p>' +
			'<div class="mbbb-slot-row__meta">' +
			'<span class="mbbb-slot-pill">' + esc(sourceLabel(source)) + '</span>' +
			(slot.required ? '<span class="mbbb-slot-pill mbbb-slot-pill--req">Required</span>' : '') +
			'</div>' +
			'<div class="mbbb-slot-row__toolbar">' +
			'<span class="mbbb-slot-drag dashicons dashicons-move" title="' + esc(i18n.drag || 'Drag to reorder') + '"></span> ' +
			'<button type="button" class="button button-small mbbb-dup">' + esc(i18n.duplicate || 'Duplicate') + '</button> ' +
			'<button type="button" class="button button-small mbbb-del">' + esc(i18n.remove || 'Delete') + '</button>' +
			'</div>' +
			'</div>' +
			'<div class="mbbb-slot-row__body">' +
			'<div class="mbbb-slot-row__grid">' +
			'<div class="mbbb-slot-row__field"><label>Slot label <span class="mbbb-help" title="' + esc(i18n.helpLabel || '') + '">?</span>' +
			'<input type="text" class="widefat mbbb-field" data-field="label" value="' + esc(slot.label) + '" /></label></div>' +
			'<div class="mbbb-slot-row__field"><label>Slot key <span class="mbbb-help" title="' + esc(i18n.helpKey || '') + '">?</span>' +
			'<input type="text" class="mbbb-field" data-field="key" value="' + esc(slot.key || '') + '" /></label></div>' +
			'<div class="mbbb-slot-row__field mbbb-slot-row__field--check"><label title="' + esc(i18n.helpRequired || '') + '">' +
			'<input type="checkbox" class="mbbb-field" data-field="required" ' + (slot.required ? 'checked' : '') + ' /> Required</label></div>' +
			'<div class="mbbb-slot-row__field"><label>Display style <span class="mbbb-help" title="' + esc(i18n.helpDisplay || '') + '">?</span>' +
			'<select class="mbbb-field" data-field="display">' +
			'<option value="cards"' + (slot.display === 'cards' ? ' selected' : '') + '>Cards</option>' +
			'<option value="pills"' + (slot.display === 'pills' ? ' selected' : '') + '>Pills</option>' +
			'<option value="dropdown"' + (slot.display === 'dropdown' ? ' selected' : '') + '>Dropdown</option>' +
			'</select></label></div>' +
			'</div>' +
			'<div class="mbbb-slot-row__field"><label>Help text <span class="mbbb-help" title="' + esc(i18n.helpHelp || '') + '">?</span>' +
			'<input type="text" class="widefat mbbb-field" data-field="help" value="' + esc(slot.help || '') + '" placeholder="Optional tip for customers" /></label></div>' +
			'<div class="mbbb-slot-row__grid">' +
			'<div class="mbbb-slot-row__field"><label>Source type <span class="mbbb-help" title="' + esc(i18n.helpSource || '') + '">?</span>' +
			'<select class="mbbb-field mbbb-source" data-field="source">' +
			'<option value="attribute"' + (source === 'attribute' ? ' selected' : '') + '>' + esc(i18n.sourceAttribute || 'Product attribute') + '</option>' +
			'<option value="global_pool"' + (source === 'global_pool' ? ' selected' : '') + '>' + esc(i18n.sourcePool || 'Global pool') + '</option>' +
			'<option value="manual"' + (source === 'manual' ? ' selected' : '') + '>' + esc(i18n.sourceManual || 'Manual options') + '</option>' +
			'<option value="category"' + (source === 'category' ? ' selected' : '') + '>' + esc(i18n.sourceCategory || 'Product category') + '</option>' +
			'<option value="tag"' + (source === 'tag' ? ' selected' : '') + '>' + esc(i18n.sourceTag || 'Product tag') + '</option>' +
			'<option value="products"' + (source === 'products' ? ' selected' : '') + '>' + esc(i18n.sourceProducts || 'Manual products') + '</option>' +
			'</select></label></div>' +
			'<div class="mbbb-slot-row__field"><label>Linked variation attribute <span class="mbbb-help" title="' + esc(i18n.helpAttribute || '') + '">?</span>' +
			'<select class="mbbb-field" data-field="attribute">' + attributeOptionsHtml(slot.attribute || '') + '</select></label>' +
			'<p class="description">Maps this slot to a WooCommerce variation for cart matching.</p></div>' +
			'</div>' +
			'<div class="mbbb-slot-row__field" data-source-panel="global_pool" ' + (source === 'global_pool' ? '' : 'hidden') + '>' +
			'<label>Global pool <span class="mbbb-help" title="' + esc(i18n.helpPool || '') + '">?</span>' +
			'<select class="mbbb-field" data-field="pool_id">' + poolOptionsHtml(slot.pool_id || '') + '</select></label></div>' +
			'<div class="mbbb-slot-row__field" data-source-panel="manual" ' + (source === 'manual' ? '' : 'hidden') + '>' +
			'<label>Manual options <span class="mbbb-help" title="' + esc(i18n.helpManual || '') + '">?</span>' +
			'<textarea class="widefat mbbb-field mbbb-manual-options" data-field="manual_options_text" rows="4" placeholder="ASBO 15mm&#10;Banana Pop Ups|banana-pop-ups">' +
			esc(manualOptionsToText(slot.manual_options)) + '</textarea></label></div>' +
			'<div class="mbbb-slot-row__field" data-source-panel="category" ' + (source === 'category' ? '' : 'hidden') + '>' +
			'<label>Category ID <span class="mbbb-help" title="' + esc(i18n.helpCategory || '') + '">?</span>' +
			'<input type="number" min="0" class="mbbb-field" data-field="category_id" value="' + esc(slot.category_id || 0) + '" /></label></div>' +
			'<div class="mbbb-slot-row__field" data-source-panel="tag" ' + (source === 'tag' ? '' : 'hidden') + '>' +
			'<label>Tag ID <span class="mbbb-help" title="' + esc(i18n.helpTag || '') + '">?</span>' +
			'<input type="number" min="0" class="mbbb-field" data-field="tag_id" value="' + esc(slot.tag_id || 0) + '" /></label></div>' +
			'<div class="mbbb-slot-row__field" data-source-panel="products" ' + (source === 'products' ? '' : 'hidden') + '>' +
			'<label>Product IDs <span class="mbbb-help" title="' + esc(i18n.helpProducts || '') + '">?</span>' +
			'<input type="text" class="widefat mbbb-field" data-field="product_ids_text" value="' + esc(productIdsToText(slot.product_ids)) + '" placeholder="123, 456, 789" /></label></div>' +
			'</div>' +
			'</div>'
		);

		return $row;
	}

	function render(options) {
		options = options || {};
		destroySortable();
		$list.empty();
		slots.forEach(function (slot, index) {
			$list.append(buildRow(slot, index));
		});
		$list.sortable({
			handle: '.mbbb-slot-drag',
			placeholder: 'mbbb-slot-row ui-sortable-placeholder',
			forcePlaceholderSize: true,
			tolerance: 'pointer',
			update: function () {
				var ordered = [];
				$list.find('.mbbb-slot-row').each(function () {
					ordered.push(slots[parseInt($(this).data('index'), 10)]);
				});
				slots = ordered;
				syncJson();
				render();
			}
		});
		syncJson();
		renderSummary();
		schedulePreviewRefresh();
		if (!options.skipValidationClear) {
			showValidation([]);
		}
		$('#mbbb-auto-map-attributes').prop('disabled', !variationAttributes.length || !slots.length);
		$('#mbbb-create-from-variations').prop('disabled', !slotsFromVariations.length);
	}

	function enableRecommendedSettings() {
		$('#mbbb_enabled').prop('checked', true);
		$('#mbbb_hide_variations').prop('checked', true);
		$('#mbbb_fixed_price').prop('checked', true);
		$('#mbbb_sticky_atc').prop('checked', true);
		$('#mbbb_show_summary').prop('checked', true);
	}

	function autoMapAttributes() {
		var mapped = 0;
		slots.forEach(function (slot) {
			if (slot.attribute && findAttributeKey(slot.attribute)) {
				return;
			}
			var match = findAttributeKey(slot.attribute) || findAttributeKey(slot.label) || findAttributeKey(slot.key);
			if (match) {
				slot.attribute = match;
				if (!slot.source || slot.source === 'global_pool' || slot.source === 'manual') {
					// Prefer attribute source when we successfully link a variation attr.
					if (!slot.source || slot.source === '') {
						slot.source = 'attribute';
					}
				}
				mapped++;
			}
		});
		render();
		return mapped;
	}

	$list.on('click', '.mbbb-slot-row__toggle', function () {
		var $row = $(this).closest('.mbbb-slot-row');
		var collapsed = $row.toggleClass('is-collapsed').hasClass('is-collapsed');
		$(this).attr('aria-expanded', collapsed ? 'false' : 'true');
		$(this).text(collapsed ? (i18n.expand || 'Show') : (i18n.collapse || 'Hide'));
	});

	$list.on('change input', '.mbbb-field', function () {
		var $row = $(this).closest('.mbbb-slot-row');
		var idx = parseInt($row.data('index'), 10);
		var field = $(this).data('field');
		var $input = $(this);
		var val = $input.is(':checkbox') ? $input.is(':checked') : $input.val();

		if (!slots[idx]) {
			return;
		}

		if (field === 'manual_options_text') {
			slots[idx].manual_options = textToManualOptions(val);
		} else if (field === 'product_ids_text') {
			slots[idx].product_ids = textToProductIds(val);
		} else if (field === 'category_id' || field === 'tag_id') {
			slots[idx][field] = parseInt(val, 10) || 0;
		} else if (field === 'key') {
			slots[idx].key = slugify(val);
			$input.val(slots[idx].key);
		} else {
			slots[idx][field] = val;
		}

		if (field === 'label') {
			if (!slots[idx]._keyTouched) {
				slots[idx].key = slugify(val);
				$row.find('[data-field="key"]').val(slots[idx].key);
			}
			$row.find('.mbbb-slot-row__title').text(slotTitle(slots[idx], idx));
		}

		if (field === 'key') {
			slots[idx]._keyTouched = true;
		}

		if (field === 'source') {
			updateSourceVisibility($row, val);
			$row.find('.mbbb-slot-pill').first().text(sourceLabel(val));
		}

		if (field === 'required') {
			var $reqPill = $row.find('.mbbb-slot-pill--req');
			if (val) {
				if (!$reqPill.length) {
					$row.find('.mbbb-slot-row__meta').append('<span class="mbbb-slot-pill mbbb-slot-pill--req">Required</span>');
				}
			} else {
				$reqPill.remove();
			}
		}

		syncJson();
		renderSummary();
	});

	$list.on('click', '.mbbb-del', function () {
		var idx = parseInt($(this).closest('.mbbb-slot-row').data('index'), 10);
		slots.splice(idx, 1);
		render();
	});

	$list.on('click', '.mbbb-dup', function () {
		var idx = parseInt($(this).closest('.mbbb-slot-row').data('index'), 10);
		var copy = $.extend(true, {}, slots[idx]);
		copy.label += ' (copy)';
		copy.key = slugify((copy.key || 'slot') + '-copy');
		slots.splice(idx + 1, 0, copy);
		render();
		var $newRow = $list.find('.mbbb-slot-row').eq(idx + 1);
		$newRow.removeClass('is-collapsed').find('.mbbb-slot-row__toggle').attr('aria-expanded', 'true').text(i18n.collapse || 'Hide');
		scrollListTo($newRow);
	});

	$('#mbbb-add-slot').on('click', function () {
		slots.push(defaultSlot());
		render();
		var $newRow = $list.find('.mbbb-slot-row').last();
		$newRow.removeClass('is-collapsed').find('.mbbb-slot-row__toggle').attr('aria-expanded', 'true').text(i18n.collapse || 'Hide');
		scrollListTo($newRow);
	});

	$('#mbbb-expand-all-slots').on('click', function () {
		$list.find('.mbbb-slot-row').removeClass('is-collapsed')
			.find('.mbbb-slot-row__toggle').attr('aria-expanded', 'true').text(i18n.collapse || 'Hide');
	});

	$('#mbbb-collapse-all-slots').on('click', function () {
		$list.find('.mbbb-slot-row').addClass('is-collapsed')
			.find('.mbbb-slot-row__toggle').attr('aria-expanded', 'false').text(i18n.expand || 'Show');
	});

	$('#mbbb-create-from-variations').on('click', function () {
		if (!slotsFromVariations.length) {
			window.alert(i18n.noVariations || 'No variation attributes found.');
			return;
		}
		if (slots.length && !window.confirm(i18n.confirmFromVars || 'Replace current slots with variation attributes?')) {
			return;
		}
		slots = JSON.parse(JSON.stringify(slotsFromVariations));
		enableRecommendedSettings();
		$('input[name="mbbb_apply_preset_on_save"]').prop('checked', false);
		render();
		window.alert(i18n.fromVarsDone || 'Slots created from variations. Click Update to save.');
	});

	$('#mbbb-auto-map-attributes').on('click', function () {
		if (!variationAttributes.length) {
			window.alert(i18n.noVariations || 'No variation attributes found.');
			return;
		}
		autoMapAttributes();
		window.alert(i18n.autoMapDone || 'Slots linked where possible. Click Update to save.');
	});

	$('#mbbb-apply-preset-btn').on('click', function () {
		applyPresetFromSelect(true);
	});

	$('#mbbb-quick-enable-preset').on('click', function () {
		var $select = $('#mbbb_apply_preset');
		if (!$select.val()) {
			var detected = $select.find('option[selected]').val();
			if (detected) {
				$select.val(detected);
			}
		}
		if (!$select.val()) {
			window.alert(i18n.selectPreset || 'Please choose a bundle preset first.');
			return;
		}
		applyPresetFromSelect(false);
		enableRecommendedSettings();
		$('input[name="mbbb_apply_preset_on_save"]').prop('checked', true);
		if (variationAttributes.length) {
			autoMapAttributes();
		}
		window.alert(i18n.quickSetupDone || 'Preset applied. Click Update to save.');
	});

	function applyPresetFromSelect(confirmFirst) {
		var id = $('#mbbb_apply_preset').val();
		if (!id) {
			return;
		}
		if (confirmFirst && !window.confirm(i18n.confirmPreset)) {
			return;
		}
		var preset = presets.find(function (p) { return p.id === id; });
		if (preset && preset.slots) {
			slots = JSON.parse(JSON.stringify(preset.slots));
			if (variationAttributes.length) {
				autoMapAttributes();
			} else {
				render();
			}
			scrollListTo($list.find('.mbbb-slot-row').first());
		}
	}

	$('#mbbb_enabled').on('change', function () {
		schedulePreviewRefresh();
	});

	// Block product save when Bundle Builder validation fails.
	$postForm.on('submit', function (e) {
		syncJson();
		var errors = validateSlots();
		showValidation(errors);
		if (!errors.length) {
			return;
		}
		e.preventDefault();
		e.stopPropagation();
		// WooCommerce may set a publishing lock — clear busy state.
		$('#publishing-action .spinner').removeClass('is-active');
		$('#publish, #save-post').removeClass('disabled').prop('disabled', false);
		if (typeof window.wp !== 'undefined' && window.wp.data) {
			try {
				// no-op for classic editor
			} catch (err) { /* ignore */ }
		}
		var $panelTab = $('.mbbb_options a, a[href="#mbbb_product_data"]');
		if ($panelTab.length) {
			$panelTab.trigger('click');
		}
		if ($validation.length && $validation[0].scrollIntoView) {
			$validation[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
		}
		window.alert(i18n.fixBlocked || 'Fix the Bundle Builder errors before saving.');
		return false;
	});

	$('a[href="#mbbb_product_data"]').on('click', function () {
		window.setTimeout(function () {
			scrollListTo($list.find('.mbbb-slot-row').first());
		}, 50);
	});

	// Best-effort: enrich empty attribute links on load for clearer summary.
	if (variationAttributes.length && slots.length) {
		slots.forEach(function (slot) {
			if (!slot.attribute) {
				var match = findAttributeKey(slot.label) || findAttributeKey(slot.key);
				if (match) {
					slot.attribute = match;
				}
			}
		});
	}

	render();
})(jQuery);
