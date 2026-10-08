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

	function readJson(id) {
		var node = document.getElementById(id);
		if (!node) {
			return null;
		}
		try {
			return JSON.parse(node.textContent);
		} catch (error) {
			return null;
		}
	}

	var contentModel = readJson('mb-content-model') || { types: {}, products: [] };
	var contentPresets = readJson('mb-content-presets') || {};
	var typeUnits = {
		boilies: 'bags',
		popups: 'tubs',
		wafters: 'tubs',
		hookbaits: 'tubs',
		pellets: 'bags',
		liquids: 'bottles',
		other: 'items'
	};
	var typeWords = {
		boilies: ['Boilie', 'Boilies'],
		popups: ['Pop-up', 'Pop-ups'],
		wafters: ['Wafter', 'Wafters'],
		hookbaits: ['Hookbait', 'Hookbaits'],
		pellets: ['Pellet', 'Pellets'],
		liquids: ['Liquid', 'Liquids'],
		other: ['Item', 'Items']
	};

	function contentCards() {
		return Array.prototype.slice.call(form.querySelectorAll('[data-group]'));
	}

	function cardField(card, field) {
		return card.querySelector('[data-field="' + field + '"]');
	}

	function cardType(card) {
		var input = card.querySelector('[data-field="type"]:checked');
		return input ? input.value : 'boilies';
	}

	function checkedChoices(card, kind) {
		var values = [];
		card.querySelectorAll('[data-choice="' + kind + '"]:checked').forEach(function (input) {
			values.push(input.value);
		});
		return values;
	}

	function choiceLabels(card, kind) {
		var labels = [];
		card.querySelectorAll('[data-choice="' + kind + '"]:checked').forEach(function (input) {
			var span = input.parentElement ? input.parentElement.querySelector('span') : null;
			labels.push(span ? span.textContent.trim() : input.value);
		});
		return labels;
	}

	function unitPair(unit) {
		var map = {
			bags: ['bag', 'bags'],
			tubs: ['tub', 'tubs'],
			bottles: ['bottle', 'bottles'],
			items: ['item', 'items']
		};
		return map[unit] || map.items;
	}

	function makeChoice(value, label, checked, kind) {
		var wrap = document.createElement('label');
		wrap.className = kind === 'products' ? 'mb-content-card__product' : 'mb-manager__pill';
		wrap.setAttribute('data-filter-item', kind);
		var input = document.createElement('input');
		input.type = 'checkbox';
		input.value = value;
		input.checked = !!checked;
		input.setAttribute('data-choice', kind);
		var span = document.createElement('span');
		span.textContent = label;
		wrap.appendChild(input);
		wrap.appendChild(document.createTextNode(' '));
		wrap.appendChild(span);
		return wrap;
	}

	function fillCardChoices(card, group) {
		var type = cardType(card);
		var info = (contentModel.types && contentModel.types[type]) || { ranges: {}, sizes: {}, formats: {} };
		var rangeList = card.querySelector('[data-range-list]');
		var sizeList = card.querySelector('[data-size-list]');
		var selectedRanges = group.ranges || [];
		var selectedSizes = group.sizes || [];
		var selectedProducts = (group.product_ids || []).map(String);
		if (rangeList) {
			rangeList.textContent = '';
			Object.keys(info.ranges || {}).forEach(function (slug) {
				rangeList.appendChild(makeChoice(slug, info.ranges[slug], selectedRanges.indexOf(slug) !== -1, 'ranges'));
			});
		}
		if (sizeList) {
			sizeList.textContent = '';
			Object.keys(info.sizes || {}).forEach(function (slug) {
				var size = info.sizes[slug] || {};
				var item = makeChoice(slug, size.label || slug, selectedSizes.indexOf(slug) !== -1, 'sizes');
				item.setAttribute('data-size-ranges', (size.ranges || []).join(','));
				sizeList.appendChild(item);
			});
		}
		var formats = info.formats || {};
		card.querySelectorAll('[data-field="bait_format"]').forEach(function (input) {
			if (input.value === 'both') {
				return;
			}
			input.setAttribute('data-format-ranges', (formats[input.value] || []).join(','));
		});
		var productList = card.querySelector('[data-product-list]');
		if (productList && type === 'other') {
			productList.textContent = '';
			(contentModel.products || []).forEach(function (product) {
				productList.appendChild(makeChoice(String(product.id), product.name, selectedProducts.indexOf(String(product.id)) !== -1, 'products'));
			});
		}
	}

	function filterCardSizes(card) {
		var ranges = checkedChoices(card, 'ranges');
		var hasRangeChoices = !!card.querySelector('[data-range-list] input');
		var visible = 0;
		card.querySelectorAll('[data-size-list] [data-filter-item]').forEach(function (item) {
			var input = item.querySelector('input');
			var known = (item.getAttribute('data-size-ranges') || '').split(',').filter(Boolean);
			var hide = false;
			if (hasRangeChoices && !ranges.length) {
				hide = !(input && input.checked);
			} else if (ranges.length && known.length && !ranges.some(function (slug) {
				return known.indexOf(slug) !== -1;
			})) {
				hide = true;
				if (input) {
					input.checked = false;
				}
			}
			item.hidden = hide;
			if (!hide) {
				visible += 1;
			}
		});
		var hint = card.querySelector('[data-size-hint]');
		var type = cardType(card);
		if (hint) {
			hint.hidden = visible > 0 || !card.querySelector('[data-size-list] [data-filter-item]');
			hint.textContent = ranges.length
				? (type === 'boilies' ? 'No boilie sizes were found for the selected ranges.' : 'No sizes were found for the selected ranges.')
				: 'Choose bait ranges to see the sizes customers can pick.';
		}
		var rangeHint = card.querySelector('[data-range-hint]');
		if (rangeHint) {
			var empty = !card.querySelector('[data-range-list] input');
			rangeHint.hidden = !empty;
			rangeHint.textContent = empty ? 'Customers can choose any product of this type.' : '';
		}
	}

	function formatPreview(current) {
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

	function cardFormatValue(card) {
		var input = card.querySelector('[data-field="bait_format"]:checked');
		return input ? input.value : '';
	}

	function refreshCard(card) {
		var type = cardType(card);
		var qty = parseInt((cardField(card, 'quantity') || {}).value, 10) || 0;
		var unit = (cardField(card, 'unit') || {}).value || 'items';
		var pair = unitPair(unit);
		var words = typeWords[type] || typeWords.other;
		var open = card.getAttribute('data-open') === '1';
		var editor = card.querySelector('[data-group-editor]');
		if (editor) {
			editor.hidden = !open;
		}
		var edit = card.querySelector('[data-group-edit]');
		if (edit) {
			edit.textContent = open ? 'Done' : 'Edit';
		}
		card.querySelectorAll('[data-panel]').forEach(function (panel) {
			var name = panel.getAttribute('data-panel');
			var show = false;
			if (name === 'products') {
				show = type === 'other';
			} else if (name === 'format') {
				show = type === 'boilies';
			} else {
				show = type !== 'other';
			}
			panel.hidden = !show;
		});
		filterCardSizes(card);
		textNode(card, '[data-summary-title]', words[1]);
		textNode(card, '[data-summary-qty]', qty + ' ' + (qty === 1 ? pair[0] : pair[1]));
		var detail = type === 'other' ? choiceLabels(card, 'products') : choiceLabels(card, 'ranges');
		textNode(card, '[data-summary-detail]', detail.length ? detail.join(', ') : (type === 'other' ? 'Choose products' : 'Any range'));
		var sizes = choiceLabels(card, 'sizes');
		textNode(card, '[data-summary-sizes]', sizes.join(' / '));
		var formatNode = card.querySelector('[data-summary-format]');
		var formatText = type === 'boilies' ? formatPreview(cardFormatValue(card)).replace('Format: ', '') : '';
		if (formatNode) {
			formatNode.hidden = formatText === '';
			formatNode.textContent = formatText;
		}
	}

	function textNode(card, selector, value) {
		var node = card.querySelector(selector);
		if (node) {
			node.textContent = value;
		}
	}

	function reindexCards() {
		contentCards().forEach(function (card, index) {
			var prefix = 'mb_bundle[groups][' + index + ']';
			card.querySelectorAll('[data-field="type"]').forEach(function (input) {
				input.name = prefix + '[type]';
			});
			var quantity = cardField(card, 'quantity');
			if (quantity) {
				quantity.name = prefix + '[quantity]';
			}
			var unit = cardField(card, 'unit');
			if (unit) {
				unit.name = prefix + '[unit]';
			}
			card.querySelectorAll('[data-field="bait_format"]').forEach(function (input) {
				input.name = prefix + '[bait_format]';
			});
			card.querySelectorAll('[data-choice="ranges"]').forEach(function (input) {
				input.name = prefix + '[ranges][]';
			});
			card.querySelectorAll('[data-choice="sizes"]').forEach(function (input) {
				input.name = prefix + '[sizes][]';
			});
			card.querySelectorAll('[data-choice="products"]').forEach(function (input) {
				input.name = prefix + '[product_ids][]';
			});
		});
		var sum = 0;
		contentCards().forEach(function (card) {
			sum += parseInt((cardField(card, 'quantity') || {}).value, 10) || 0;
		});
		var qty = document.getElementById('mb-bundle-quantity');
		var unitField = document.getElementById('mb-bundle-unit');
		if (qty) {
			qty.value = String(sum);
		}
		var first = contentCards()[0];
		if (unitField && first && cardField(first, 'unit')) {
			unitField.value = cardField(first, 'unit').value;
		}
	}

	function addContentCard(group, open) {
		var template = document.getElementById('mb-content-template');
		var host = document.getElementById('mb-content-cards');
		if (!template || !host || !template.content.firstElementChild) {
			return null;
		}
		var card = template.content.firstElementChild.cloneNode(true);
		host.appendChild(card);
		group = group || {};
		var type = group.type || 'boilies';
		card.querySelectorAll('[data-field="type"]').forEach(function (input) {
			input.checked = input.value === type;
		});
		var quantity = cardField(card, 'quantity');
		if (quantity) {
			quantity.value = String(group.quantity || 1);
		}
		var unit = cardField(card, 'unit');
		if (unit) {
			unit.value = group.unit || typeUnits[type] || 'items';
		}
		fillCardChoices(card, group);
		if (group.bait_format) {
			var format = card.querySelector('[data-field="bait_format"][value="' + group.bait_format + '"]');
			if (format) {
				format.checked = true;
			}
			if (form.getAttribute('data-new') !== '1') {
				card.setAttribute('data-format-touched', '1');
			}
		}
		card.setAttribute('data-open', open ? '1' : '0');
		refreshCard(card);
		reindexCards();
		return card;
	}

	function buildContentCards() {
		var host = document.getElementById('mb-content-cards');
		if (!host || host.childElementCount) {
			return;
		}
		var initial = readJson('mb-content-initial') || [];
		var openFirst = form.getAttribute('data-new') === '1';
		initial.forEach(function (group, index) {
			addContentCard(group, openFirst && index === 0);
		});
	}

	function contentsSentence() {
		var parts = [];
		contentCards().forEach(function (card) {
			var qty = parseInt((cardField(card, 'quantity') || {}).value, 10) || 0;
			if (qty < 1) {
				return;
			}
			var unit = (cardField(card, 'unit') || {}).value || 'items';
			var pair = unitPair(unit);
			var type = cardType(card);
			var plural = {
				boilies: 'boilies',
				popups: 'pop-ups',
				wafters: 'wafters',
				hookbaits: 'hookbaits',
				pellets: 'pellets',
				liquids: 'liquids',
				other: 'products'
			}[type] || 'products';
			parts.push(qty + ' ' + (qty === 1 ? pair[0] : pair[1]) + ' of ' + plural);
		});
		return parts.join(', ');
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
		var sentence = contentsSentence() || choiceSentence();
		var helper = value('mb_bundle[helper_text]') || sentence;
		var badge = value('mb_bundle[badge]');
		var badgeNode = document.getElementById('mb-preview-badge');
		var boilieCard = contentCards().filter(function (card) {
			return cardType(card) === 'boilies';
		})[0];
		text('mb-preview-name', name);
		text('mb-preview-status', statusLabel());
		text('mb-preview-helper', helper);
		text('mb-preview-qty', sentence);
		text('mb-preview-ranges', boilieCard && choiceLabels(boilieCard, 'ranges').length ? 'Ranges: ' + choiceLabels(boilieCard, 'ranges').join(', ') : 'No ranges selected');
		text('mb-preview-sizes', boilieCard && choiceLabels(boilieCard, 'sizes').length ? 'Sizes: ' + choiceLabels(boilieCard, 'sizes').join(', ') : 'No sizes selected');
		var formatNode = document.getElementById('mb-preview-format');
		var formatText = boilieCard ? formatPreview(cardFormatValue(boilieCard)) : '';
		if (formatNode) {
			formatNode.hidden = formatText === '';
			formatNode.textContent = formatText;
		}
		text('mb-preview-price', priceSummary());
		text('mb-preview-button', value('mb_bundle[button_text]') || 'Build Your Bundle');
		text('mb-bundle-choice-preview', sentence);
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

	var formatTouched = form.getAttribute('data-new') !== '1' && !!form.querySelector('[data-field="bait_format"]:checked');
	var allowFormatDefault = form.getAttribute('data-new') === '1';

	function formatsForRanges(card, ranges) {
		var found = { shelf_life: false, freezer: false };
		['shelf_life', 'freezer'].forEach(function (format) {
			var input = card.querySelector('[data-field="bait_format"][value="' + format + '"]');
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

	function syncCardFormats(card) {
		if (cardType(card) !== 'boilies') {
			return;
		}
		var ranges = checkedChoices(card, 'ranges');
		var found = formatsForRanges(card, ranges);
		var bothAvailable = found.shelf_life && found.freezer;
		['shelf_life', 'freezer', 'both'].forEach(function (format) {
			var input = card.querySelector('[data-field="bait_format"][value="' + format + '"]');
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

		var current = card.querySelector('[data-field="bait_format"]:checked');
		var currentValid = current && !current.disabled;
		var touched = card.getAttribute('data-format-touched') === '1';
		var pick = '';
		if ((touched || formatTouched) && currentValid) {
			pick = current.value;
		} else if (allowFormatDefault) {
			pick = defaultFormat(found);
		} else if (currentValid) {
			pick = current.value;
		}
		['shelf_life', 'freezer', 'both'].forEach(function (format) {
			var input = card.querySelector('[data-field="bait_format"][value="' + format + '"]');
			if (input) {
				input.checked = pick !== '' && format === pick && !input.disabled;
			}
		});

		var hint = card.querySelector('[data-format-hint]');
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

	function syncFormats() {
		contentCards().forEach(function (card) {
			syncCardFormats(card);
			refreshCard(card);
		});
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
		var preset = contentPresets[key];
		if (!preset) {
			return;
		}
		setRadio('mb_bundle[bundle_type]', 'mix_and_match');
		setRadio('mb_bundle[pricing_mode]', 'fixed');
		setRadio('mb_bundle[status]', 'draft');
		if (preset.clear) {
			['mb_bundle[name]', 'mb_bundle[fixed_price]', 'mb_bundle[helper_text]', 'mb_bundle[badge]'].forEach(function (fieldName) {
				var field = form.querySelector('[name="' + fieldName + '"]');
				if (field) {
					field.value = '';
				}
			});
		}
		var host = document.getElementById('mb-content-cards');
		if (host) {
			host.textContent = '';
		}
		(preset.groups || []).forEach(function (group, index) {
			addContentCard(group, key === 'blank' && index === 0);
		});
		formatTouched = false;
		contentCards().forEach(function (card) {
			card.removeAttribute('data-format-touched');
		});
		toggleShows();
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
		if (event.target && event.target.hasAttribute('data-product-search')) {
			var query = event.target.value.toLowerCase();
			var panel = event.target.closest('[data-panel]');
			if (panel) {
				panel.querySelectorAll('[data-filter-item]').forEach(function (item) {
					item.hidden = query !== '' && item.textContent.toLowerCase().indexOf(query) === -1;
				});
			}
		}
		var contentCard = event.target && event.target.closest ? event.target.closest('[data-group]') : null;
		if (contentCard) {
			refreshCard(contentCard);
			reindexCards();
		}
		updatePreview();
		toggleShows();
	});
	form.addEventListener('change', function (event) {
		markLegacyChoices(event);
		if (event.target && event.target.closest && event.target.type === 'checkbox' && event.target.closest('[data-check-list="fixed"]')) {
			syncFixedQty(event.target);
		}
		var contentCard = event.target && event.target.closest ? event.target.closest('[data-group]') : null;
		if (contentCard && event.target.getAttribute('data-field') === 'type') {
			var nextType = event.target.value;
			var unitInput = cardField(contentCard, 'unit');
			if (unitInput) {
				unitInput.value = typeUnits[nextType] || 'items';
			}
			contentCard.removeAttribute('data-format-touched');
			fillCardChoices(contentCard, {
				ranges: [],
				sizes: [],
				product_ids: checkedChoices(contentCard, 'products')
			});
		}
		if (contentCard && event.target.getAttribute('data-field') === 'bait_format') {
			formatTouched = true;
			contentCard.setAttribute('data-format-touched', '1');
		}
		if (event.target && event.target.closest('[data-check-list="ranges"]')) {
			applyListFilter('sizes', true);
			syncFormats();
		}
		if (contentCard && event.target.getAttribute('data-choice') === 'ranges') {
			syncFormats();
		}
		if (contentCard) {
			refreshCard(contentCard);
			reindexCards();
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

	var addGroup = document.getElementById('mb-add-group');
	if (addGroup) {
		addGroup.addEventListener('click', function () {
			markLegacyChoices({ target: addGroup });
			addContentCard({ type: 'boilies', quantity: 1, unit: 'bags' }, true);
			syncFormats();
			updatePreview();
			refreshCount();
		});
	}

	form.addEventListener('click', function (event) {
		var edit = event.target && event.target.closest ? event.target.closest('[data-group-edit]') : null;
		var remove = event.target && event.target.closest ? event.target.closest('[data-group-remove]') : null;
		if (!edit && !remove) {
			return;
		}
		var card = (edit || remove).closest('[data-group]');
		if (!card) {
			return;
		}
		markLegacyChoices({ target: edit || remove });
		if (edit) {
			card.setAttribute('data-open', card.getAttribute('data-open') === '1' ? '0' : '1');
			refreshCard(card);
			return;
		}
		card.remove();
		reindexCards();
		updatePreview();
		refreshCount();
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

	buildContentCards();
	toggleShows();
	applyListFilter('sizes', false);
	syncFormats();
	closeAdvanced();
	window.addEventListener('pageshow', closeAdvanced);
	updatePreview();
	refreshCount();
}(jQuery));
