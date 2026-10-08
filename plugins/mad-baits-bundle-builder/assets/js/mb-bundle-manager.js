(function ($) {
	'use strict';

	var cfg = window.mbBundleManager || {};
	var form = document.getElementById('mb-bundle-editor');
	if (!form) {
		return;
	}

	function value(name) {
		var field = form.querySelector('[name="' + name + '"]');
		return field ? field.value : '';
	}

	function checkedValue(name) {
		var field = form.querySelector('[name="' + name + '"]:checked');
		return field ? field.value : '';
	}

	function unitWord(count) {
		var unit = value('mb_bundle[unit]');
		var custom = value('mb_bundle[unit_custom]');
		var map = {
			bags: ['bag', 'bags'],
			tubs: ['tub', 'tubs'],
			bottles: ['bottle', 'bottles'],
			items: ['item', 'items']
		};
		if (unit === 'custom' && custom) {
			return custom;
		}
		var pair = map[unit] || map.items;
		return count === 1 ? pair[0] : pair[1];
	}

	function choiceSentence() {
		if (checkedValue('mb_bundle[bundle_type]') === 'fixed') {
			return 'Fixed set of products';
		}
		var mode = checkedValue('mb_bundle[quantity_mode]') || 'exact';
		if (mode === 'minimum') {
			var min = parseInt(value('mb_bundle[min_quantity]'), 10) || 0;
			var max = parseInt(value('mb_bundle[max_quantity]'), 10) || 0;
			if (max > 0) {
				return 'Choose ' + min + ' to ' + max + ' ' + unitWord(Math.max(max, 2));
			}
			return 'Choose at least ' + min + ' ' + unitWord(Math.max(min, 2));
		}
		var qty = parseInt(value('mb_bundle[quantity]'), 10) || 0;
		return 'Choose any ' + qty + ' ' + unitWord(qty);
	}

	function money(amount) {
		var number = parseFloat(amount);
		if (isNaN(number)) {
			number = 0;
		}
		return (cfg.currency || '£') + number.toFixed(2);
	}

	function priceSummary() {
		var mode = checkedValue('mb_bundle[pricing_mode]') || 'fixed';
		if (mode === 'percent') {
			return 'Customer receives: ' + (parseInt(value('mb_bundle[percent]'), 10) || 0) + '% discount';
		}
		if (mode === 'amount') {
			return 'Customer receives: ' + money(value('mb_bundle[amount]')) + ' off';
		}
		return 'Customer pays: ' + money(value('mb_bundle[fixed_price]'));
	}

	function text(id, content) {
		var node = document.getElementById(id);
		if (node) {
			node.textContent = content;
		}
	}

	function selectedLabels(listId) {
		var labels = [];
		form.querySelectorAll('[data-check-list="' + listId + '"] input:checked').forEach(function (input) {
			var span = input.parentElement ? input.parentElement.querySelector('span') : null;
			var label = span ? span.textContent.trim() : input.value;
			if (label) {
				labels.push(label);
			}
		});
		return labels;
	}

	function joined(listId, empty) {
		var labels = selectedLabels(listId);
		return labels.length ? labels.join(', ') : empty;
	}

	function prefixed(listId, label, empty) {
		var labels = selectedLabels(listId);
		return labels.length ? label + ': ' + labels.join(', ') : empty;
	}

	function formatPreview() {
		var current = checkedValue('mb_bundle[bait_format]');
		if (current === 'both') {
			return 'Format: Shelf Life + Freezer';
		}
		if (current === 'shelf_life') {
			return 'Format: Shelf Life';
		}
		if (current === 'freezer') {
			return 'Format: Freezer';
		}
		return '';
	}

	function statusLabel() {
		var field = form.querySelector('[name="mb_bundle[status]"]:checked');
		if (!field || !field.parentElement) {
			return '';
		}
		var span = field.parentElement.querySelector('span');
		return span ? span.textContent.trim() : field.value;
	}

	function updatePreview() {
		var name = value('mb_bundle[name]') || 'Bundle name';
		var helper = value('mb_bundle[helper_text]') || choiceSentence();
		var badge = value('mb_bundle[badge]');
		var badgeNode = document.getElementById('mb-preview-badge');
		text('mb-preview-name', name);
		text('mb-preview-status', statusLabel());
		text('mb-preview-helper', helper);
		text('mb-preview-qty', choiceSentence());
		text('mb-preview-ranges', prefixed('ranges', 'Ranges', 'No ranges selected'));
		text('mb-preview-sizes', prefixed('sizes', 'Sizes', 'No sizes selected'));
		var formatNode = document.getElementById('mb-preview-format');
		var formatText = formatPreview();
		if (formatNode) {
			formatNode.hidden = formatText === '';
			formatNode.textContent = formatText;
		}
		text('mb-preview-price', priceSummary());
		text('mb-preview-button', value('mb_bundle[button_text]') || 'Build Your Bundle');
		text('mb-bundle-choice-preview', choiceSentence());
		text('mb-price-summary', priceSummary());
		if (badgeNode) {
			badgeNode.hidden = badge === '';
			badgeNode.textContent = badge;
		}
	}

	function toggleShows() {
		var type = checkedValue('mb_bundle[bundle_type]') || 'mix_and_match';
		var mode = checkedValue('mb_bundle[quantity_mode]') || 'exact';
		var unit = value('mb_bundle[unit]');
		var price = checkedValue('mb_bundle[pricing_mode]') || 'fixed';
		form.querySelectorAll('[data-show-for]').forEach(function (node) {
			node.hidden = node.getAttribute('data-show-for') !== type;
		});
		form.querySelectorAll('[data-show-for-mode]').forEach(function (node) {
			node.hidden = node.getAttribute('data-show-for-mode') !== mode;
		});
		form.querySelectorAll('[data-show-for-unit]').forEach(function (node) {
			node.hidden = node.getAttribute('data-show-for-unit') !== unit;
		});
		form.querySelectorAll('[data-price-for]').forEach(function (node) {
			node.hidden = node.getAttribute('data-price-for') !== price;
		});
	}

	function sizeMatchesRanges(item, selected) {
		if (!selected.length) {
			return false;
		}
		var raw = item.getAttribute('data-size-ranges') || '';
		if (!raw) {
			return false;
		}
		var known = raw.split(',');
		return selected.some(function (slug) {
			return known.indexOf(slug) !== -1;
		});
	}

	function selectedRangeValues() {
		var selected = [];
		form.querySelectorAll('[data-check-list="ranges"] input:checked').forEach(function (input) {
			selected.push(input.value);
		});
		return selected;
	}

	function applyListFilter(listId, uncheckHidden) {
		var search = form.querySelector('[data-filter-list="' + listId + '"]');
		var query = search ? search.value.toLowerCase() : '';
		var ranges = selectedRangeValues();
		form.querySelectorAll('[data-filter-item="' + listId + '"]').forEach(function (item) {
			var textHide = query !== '' && item.textContent.toLowerCase().indexOf(query) === -1;
			var rangeHide = listId === 'sizes' && !sizeMatchesRanges(item, ranges);
			var input = item.querySelector('input[type="checkbox"]');
			if (input && input.checked && listId === 'sizes' && rangeHide && !uncheckHidden) {
				rangeHide = false;
			}
			item.hidden = textHide || rangeHide;
			if (input && item.hidden && uncheckHidden && listId === 'sizes') {
				input.checked = false;
			}
		});
		if (listId === 'sizes') {
			updateSizeHint();
		}
	}

	var formatTouched = form.getAttribute('data-new') !== '1' && !!form.querySelector('[name="mb_bundle[bait_format]"]:checked');
	var allowFormatDefault = form.getAttribute('data-new') === '1';

	function formatInput(format) {
		return form.querySelector('[name="mb_bundle[bait_format]"][value="' + format + '"]');
	}

	function formatsForRanges(ranges) {
		var found = { shelf_life: false, freezer: false };
		['shelf_life', 'freezer'].forEach(function (format) {
			var input = formatInput(format);
			if (!input) {
				return;
			}
			var known = (input.getAttribute('data-format-ranges') || '').split(',');
			found[format] = ranges.some(function (slug) {
				return slug !== '' && known.indexOf(slug) !== -1;
			});
		});
		return found;
	}

	function defaultFormat(found) {
		if (found.shelf_life && found.freezer) {
			return 'both';
		}
		if (found.shelf_life) {
			return 'shelf_life';
		}
		if (found.freezer) {
			return 'freezer';
		}
		return '';
	}

	function syncFormats() {
		var ranges = selectedRangeValues();
		var found = formatsForRanges(ranges);
		var bothAvailable = found.shelf_life && found.freezer;
		['shelf_life', 'freezer', 'both'].forEach(function (format) {
			var input = formatInput(format);
			if (!input) {
				return;
			}
			var available = format === 'both' ? bothAvailable : found[format];
			var label = input.closest('label');
			input.disabled = !available;
			if (label) {
				label.hidden = !available;
			}
			if (!available) {
				input.checked = false;
			}
		});

		var current = form.querySelector('[name="mb_bundle[bait_format]"]:checked');
		var currentValid = current && !current.disabled;
		var pick = '';
		if (formatTouched && currentValid) {
			pick = current.value;
		} else if (allowFormatDefault) {
			pick = defaultFormat(found);
		} else if (currentValid) {
			pick = current.value;
		}
		['shelf_life', 'freezer', 'both'].forEach(function (format) {
			var input = formatInput(format);
			if (input) {
				input.checked = pick !== '' && format === pick && !input.disabled;
			}
		});

		var hint = document.getElementById('mb-format-hint');
		if (hint) {
			if (!ranges.length) {
				hint.hidden = false;
				hint.textContent = 'Choose bait ranges to see freezer and shelf life options.';
			} else if (!found.shelf_life && !found.freezer) {
				hint.hidden = false;
				hint.textContent = 'No shelf life or freezer products were found for the selected ranges.';
			} else {
				hint.hidden = true;
			}
		}
	}

	function updateSizeHint() {
		var hint = document.getElementById('mb-size-hint');
		if (!hint) {
			return;
		}
		var visible = 0;
		form.querySelectorAll('[data-check-list="sizes"] [data-filter-item]').forEach(function (item) {
			if (!item.hidden) {
				visible += 1;
			}
		});
		hint.hidden = visible > 0;
		hint.textContent = selectedRangeValues().length
			? 'No boilie sizes were found for the selected ranges.'
			: 'Choose bait ranges to see the sizes customers can pick.';
	}

	function closeAdvanced() {
		var advanced = document.getElementById('mb-advanced');
		if (advanced && form.getAttribute('data-advanced') !== '1') {
			advanced.open = false;
		}
	}

	function syncFixedQty(input) {
		var label = input.closest ? input.closest('label') : null;
		var qty = label ? label.querySelector('.mb-manager__qty') : null;
		if (qty) {
			qty.disabled = !input.checked;
		}
	}

	function markLegacyChoices(event) {
		if (form.getAttribute('data-legacy') !== '1' || !event.target || !event.target.closest) {
			return;
		}
		if (!event.target.closest('[data-structure]')) {
			return;
		}
		var box = form.querySelector('[name="update_choices"]');
		if (box) {
			box.checked = true;
		}
	}

	function setRadio(name, radioValue) {
		var field = form.querySelector('[name="' + name + '"][value="' + radioValue + '"]');
		if (field) {
			field.checked = true;
		}
	}

	function applyPreset(key) {
		if (form.getAttribute('data-new') !== '1') {
			return;
		}
		var presets = {
			'10kg': { quantity: '10', sizes: ['15mm', '18mm'] },
			'20kg': { quantity: '20', sizes: ['15mm', '18mm'] },
			'5kg': { quantity: '5', sizes: ['15mm', '18mm'] },
			blank: { quantity: '10', sizes: [], clear: true }
		};
		var preset = presets[key];
		if (!preset) {
			return;
		}
		setRadio('mb_bundle[bundle_type]', 'mix_and_match');
		setRadio('mb_bundle[pricing_mode]', 'fixed');
		setRadio('mb_bundle[status]', 'draft');
		var qty = form.querySelector('[name="mb_bundle[quantity]"]');
		if (qty) {
			qty.value = preset.quantity;
		}
		var unit = form.querySelector('[name="mb_bundle[unit]"]');
		if (unit) {
			unit.value = 'bags';
		}
		if (preset.clear) {
			['mb_bundle[name]', 'mb_bundle[fixed_price]', 'mb_bundle[helper_text]', 'mb_bundle[badge]'].forEach(function (fieldName) {
				var field = form.querySelector('[name="' + fieldName + '"]');
				if (field) {
					field.value = '';
				}
			});
			form.querySelectorAll('[data-check-list="ranges"] input').forEach(function (input) {
				input.checked = false;
			});
		}
		form.querySelectorAll('[data-check-list="sizes"] input').forEach(function (input) {
			var span = input.parentElement ? input.parentElement.querySelector('span') : null;
			var blob = ((input.value || '') + ' ' + (span ? span.textContent : '')).toLowerCase().replace(/\s+/g, '');
			input.checked = preset.sizes.some(function (size) {
				return blob.indexOf(size.replace(/\s+/g, '')) !== -1;
			});
		});
		formatTouched = false;
		toggleShows();
		applyListFilter('sizes', false);
		syncFormats();
		closeAdvanced();
		updatePreview();
		refreshCount();
	}

	var countTimer = null;
	var countRequest = 0;
	function refreshCount() {
		window.clearTimeout(countTimer);
		countTimer = window.setTimeout(function () {
			var requestId = ++countRequest;
			var data = $(form).serialize() + '&action=mb_bundle_eligibility&nonce=' + encodeURIComponent(cfg.nonce || '');
			$.post(cfg.ajaxUrl, data).done(function (response) {
				if (requestId !== countRequest || !response || !response.success) {
					return;
				}
				var live = parseInt(response.data.live, 10) || 0;
				var node = document.getElementById('mb-eligible-count');
				if (node) {
					node.textContent = live === 1 ? '1 eligible product' : live + ' eligible products';
				}
			});
		}, 250);
	}

	form.addEventListener('input', function (event) {
		markLegacyChoices(event);
		updatePreview();
		toggleShows();
	});
	form.addEventListener('change', function (event) {
		markLegacyChoices(event);
		if (event.target && event.target.closest && event.target.type === 'checkbox' && event.target.closest('[data-check-list="fixed"]')) {
			syncFixedQty(event.target);
		}
		if (event.target && event.target.name === 'mb_bundle[bait_format]') {
			formatTouched = true;
		}
		if (event.target && event.target.closest('[data-check-list="ranges"]')) {
			applyListFilter('sizes', true);
			syncFormats();
		}
		updatePreview();
		toggleShows();
		if (event.target && event.target.matches('input[type="checkbox"], input[type="radio"], select')) {
			refreshCount();
		}
	});

	form.querySelectorAll('[data-select-all], [data-clear]').forEach(function (button) {
		button.addEventListener('click', function () {
			var listId = button.getAttribute('data-select-all') || button.getAttribute('data-clear');
			var check = !!button.getAttribute('data-select-all');
			form.querySelectorAll('[data-check-list="' + listId + '"] input[type="checkbox"]').forEach(function (input) {
				var item = input.closest('[data-filter-item]');
				if (item && item.hidden) {
					return;
				}
				input.checked = check;
			});
			markLegacyChoices({ target: button });
			if (listId === 'ranges') {
				applyListFilter('sizes', true);
				syncFormats();
			}
			updatePreview();
			refreshCount();
		});
	});

	form.querySelectorAll('[data-filter-list]').forEach(function (input) {
		input.addEventListener('input', function () {
			applyListFilter(input.getAttribute('data-filter-list'), false);
		});
	});

	form.querySelectorAll('[data-preset]').forEach(function (button) {
		button.addEventListener('click', function () {
			form.querySelectorAll('[data-preset]').forEach(function (other) {
				other.classList.toggle('is-active', other === button);
			});
			applyPreset(button.getAttribute('data-preset'));
		});
	});

	var editChoices = document.getElementById('mb-edit-choices');
	if (editChoices) {
		editChoices.addEventListener('click', function () {
			var panel = document.getElementById('mb-bundle-choices');
			if (panel) {
				panel.hidden = false;
			}
			editChoices.hidden = true;
			toggleShows();
			applyListFilter('sizes', false);
			syncFormats();
		});
	}

	form.querySelectorAll('[data-intent]').forEach(function (button) {
		button.addEventListener('click', function () {
			var intent = document.getElementById('mb-bundle-intent');
			if (intent) {
				intent.value = button.getAttribute('data-intent') || 'save';
			}
		});
	});

	form.addEventListener('submit', function () {
		form.querySelectorAll('[data-check-list="fixed"] input[type="checkbox"]').forEach(syncFixedQty);
	});

	$('#mb-bundle-image-pick').on('click', function (event) {
		event.preventDefault();
		if (!window.wp || !wp.media) {
			return;
		}
		var frame = wp.media({
			title: 'Bundle image',
			button: { text: 'Use this image' },
			multiple: false
		});
		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			var idField = document.getElementById('mb-bundle-image-id');
			if (idField) {
				idField.value = attachment.id ? String(attachment.id) : '';
			}
			var url = (attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url) || attachment.url || '';
			if (!/^https?:\/\//i.test(url)) {
				return;
			}
			['mb-bundle-image-preview', 'mb-preview-image'].forEach(function (id) {
				var node = document.getElementById(id);
				var img;
				if (!node) {
					return;
				}
				node.textContent = '';
				img = document.createElement('img');
				img.alt = '';
				img.src = url;
				node.appendChild(img);
			});
		});
		frame.open();
	});

	toggleShows();
	applyListFilter('sizes', false);
	syncFormats();
	closeAdvanced();
	window.addEventListener('pageshow', closeAdvanced);
	updatePreview();
	refreshCount();
}(jQuery));
