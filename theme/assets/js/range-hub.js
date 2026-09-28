(function () {
	'use strict';

	var hub = document.querySelector('[data-mad-range-hub]');
	if (!hub) {
		return;
	}

	var grid = hub.querySelector('[data-range-hub-grid]');
	var filtersNav = hub.querySelector('[data-range-hub-filters]');
	var emptyEl = hub.querySelector('[data-range-hub-empty]');

	if (!grid) {
		return;
	}

	var cards = Array.prototype.slice.call(
		grid.querySelectorAll('.mad-product-card[data-product-type], article.product[data-product-type]')
	);
	var allCards = Array.prototype.slice.call(grid.querySelectorAll('.mad-product-card, article.product'));
	var ctaBreak = grid.querySelector('[data-range-cta-break]');
	var activeFilter = 'all';

	function setActiveChip(filterKey) {
		if (!filtersNav) {
			return;
		}

		var chips = filtersNav.querySelectorAll('[data-range-filter]');
		chips.forEach(function (chip) {
			var key = chip.getAttribute('data-range-filter') || '';
			chip.classList.toggle('is-active', key === filterKey);
		});
	}

	function scrollChipIntoView(chip) {
		if (!chip || !chip.parentElement) {
			return;
		}

		try {
			chip.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
		} catch (err) {
			chip.parentElement.scrollLeft = chip.offsetLeft - 24;
		}
	}

	function countVisible() {
		var visible = 0;
		allCards.forEach(function (card) {
			if (!card.classList.contains('is-range-filtered-out')) {
				visible += 1;
			}
		});
		return visible;
	}

	function applyFilter(filterKey) {
		activeFilter = filterKey || 'all';
		hub.classList.toggle('is-filtering', activeFilter !== 'all');

		allCards.forEach(function (card) {
			var type = card.getAttribute('data-product-type') || '';
			var show = activeFilter === 'all' || type === activeFilter;
			card.classList.toggle('is-range-filtered-out', !show);
		});

		if (emptyEl) {
			var hasVisible = countVisible() > 0;
			if (hasVisible) {
				emptyEl.setAttribute('hidden', 'hidden');
			} else {
				emptyEl.removeAttribute('hidden');
			}
		}
	}

	if (filtersNav) {
		filtersNav.addEventListener('click', function (event) {
			var chip = event.target.closest('[data-range-filter]');
			if (!chip || !filtersNav.contains(chip)) {
				return;
			}

			event.preventDefault();
			var filterKey = chip.getAttribute('data-range-filter') || 'all';
			if (filterKey === activeFilter) {
				return;
			}

			setActiveChip(filterKey);
			applyFilter(filterKey);
			scrollChipIntoView(chip);
		});
	}

	applyFilter('all');

	var overview = document.querySelector('[data-range-overview]');
	if (!overview) {
		return;
	}

	var overviewItems = Array.prototype.slice.call(overview.querySelectorAll('[data-range-overview-item]'));
	var overviewTriggers = Array.prototype.slice.call(overview.querySelectorAll('[data-range-overview-trigger]'));

	function setOverviewItemState(item, isOpen) {
		if (!item) {
			return;
		}
		var trigger = item.querySelector('[data-range-overview-trigger]');
		var panel = item.querySelector('[data-range-overview-panel]');
		item.classList.toggle('is-open', isOpen);
		if (trigger) {
			trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
		}
		if (panel) {
			if (isOpen) {
				panel.removeAttribute('hidden');
				panel.style.maxHeight = panel.scrollHeight + 'px';
			} else {
				panel.style.maxHeight = '0px';
				window.setTimeout(function () {
					if (!item.classList.contains('is-open')) {
						panel.setAttribute('hidden', 'hidden');
					}
				}, 220);
			}
		}
	}

	overviewItems.forEach(function (item) {
		setOverviewItemState(item, item.classList.contains('is-open'));
	});

	overviewTriggers.forEach(function (trigger) {
		trigger.addEventListener('click', function () {
			var item = trigger.closest('[data-range-overview-item]');
			if (!item) {
				return;
			}
			var willOpen = !item.classList.contains('is-open');
			setOverviewItemState(item, willOpen);
		});
	});
})();
