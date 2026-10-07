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

	function updatePreview() {
		var name = value('mb_bundle[name]') || 'Bundle name';
		var helper = value('mb_bundle[helper_text]') || choiceSentence();
		var badge = value('mb_bundle[badge]');
		var badgeNode = document.getElementById('mb-preview-badge');
		text('mb-preview-name', name);
		text('mb-preview-helper', helper);
		text('mb-preview-qty', choiceSentence());
		text('mb-preview-price', priceSummary());
		text('mb-preview-button', value('mb_bundle[button_text]') || 'Build Your Bundle');
		text('mb-bundle-choice-preview', choiceSentence());
		text('mb-price-summary', priceSummary());
		if (badgeNode) {
			badgeNode.hidden = badge === '';
			badgeNode.textContent = badge;
		}
		var ranges = [];
		form.querySelectorAll('[data-check-list="ranges"] input:checked').forEach(function (input) {
			var label = input.parentElement ? input.parentElement.textContent.trim() : '';
			if (label) {
				ranges.push(label);
			}
		});
		var list = document.getElementById('mb-preview-ranges');
		if (list && ranges.length) {
			list.innerHTML = '';
			ranges.slice(0, 6).forEach(function (label) {
				var item = document.createElement('li');
				item.textContent = label;
				list.appendChild(item);
			});
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

	function selectedLabels(listId) {
		var labels = [];
		form.querySelectorAll('[data-check-list="' + listId + '"] input:checked').forEach(function (input) {
			var label = input.parentElement ? input.parentElement.innerText.trim() : input.value;
			labels.push(label);
		});
		return labels;
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
					node.textContent = live === 1 ? '1 eligible product variation' : live + ' eligible product variations';
				}
				var list = document.getElementById('mb-preview-ranges');
				if (list && response.data.names && response.data.names.length && !selectedLabels('ranges').length) {
					list.innerHTML = '';
					response.data.names.forEach(function (label) {
						var item = document.createElement('li');
						item.textContent = label;
						list.appendChild(item);
					});
				}
			});
		}, 250);
	}

	form.addEventListener('input', function () {
		updatePreview();
		toggleShows();
	});
	form.addEventListener('change', function (event) {
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
			updatePreview();
			refreshCount();
		});
	});

	form.querySelectorAll('[data-filter-list]').forEach(function (input) {
		input.addEventListener('input', function () {
			var listId = input.getAttribute('data-filter-list');
			var query = input.value.toLowerCase();
			form.querySelectorAll('[data-filter-item="' + listId + '"]').forEach(function (item) {
				item.hidden = query !== '' && item.textContent.toLowerCase().indexOf(query) === -1;
			});
		});
	});

	form.querySelectorAll('[data-intent]').forEach(function (button) {
		button.addEventListener('click', function () {
			var intent = document.getElementById('mb-bundle-intent');
			if (intent) {
				intent.value = button.getAttribute('data-intent') || 'save';
			}
		});
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
	updatePreview();
	refreshCount();
}(jQuery));
