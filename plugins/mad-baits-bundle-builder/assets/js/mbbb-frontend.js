(function () {
	'use strict';

	if (window.__mbbbFrontendBooted) {
		return;
	}
	window.__mbbbFrontendBooted = true;

	if (typeof mbbbFrontend === 'undefined') {
		return;
	}

	var cfg = mbbbFrontend;
	var diag = window.__madBaitsDiag || null;
	var iosPwaSafeMode = !!window.__madBaitsIosPwaSafeMode;
	var displayStyle = String(cfg.displayStyle || 'card').replace('compact', 'card');
	// Prefer PHP flag (forces step flow for 4+ slot bundles); fall back to display style.
	var mobileStepFlow = Number(cfg.mobileStepFlow) === 1 || displayStyle === 'step' || (Number(cfg.slotCount || (cfg.slots && cfg.slots.length) || 0) >= 4);
	var showMissingBadges = false;
	var builder = document.getElementById('mbbb-builder');
	if (!builder) {
		return;
	}
	if (diag && typeof diag.log === 'function') {
		diag.log('bundle_builder_mount', {
			path: window.location.pathname,
			displayStyle: displayStyle,
			iosPwaSafeMode: iosPwaSafeMode,
		});
	}

	var form = document.querySelector('form.cart');
	var validationEl = document.getElementById('mbbb-validation');
	var summaryList = document.getElementById('mbbb-summary-list');
	var summaryToggle = document.getElementById('mbbb-summary-toggle');
	var summaryPanel = document.getElementById('mbbb-summary-panel');
	var progressText = document.getElementById('mbbb-progress-text');
	var progressFill = document.getElementById('mbbb-progress-fill');
	var summaryDrawer = document.getElementById('mbbb-summary-drawer');
	var summaryDrawerList = document.getElementById('mbbb-summary-drawer-list');
	var summaryDrawerCta = document.getElementById('mbbb-summary-drawer-cta');
	var summaryDrawerPrice = document.getElementById('mbbb-summary-drawer-price');
	var slotCount = cfg.slotCount || cfg.slots.length;
	var sticky = document.getElementById('mbbb-sticky');
	var stickyBtn = document.getElementById('mbbb-sticky-submit');
	var stickyStatus = document.getElementById('mbbb-sticky-status');
	var stickySummaryBtn = document.getElementById('mbbb-sticky-summary');
	var stickyBtnLabel = stickyBtn ? stickyBtn.querySelector('.mbbb-sticky__btn-label') : null;
	var successRoot = document.getElementById('mbbb-success');
	var successList = document.getElementById('mbbb-success-list');
	var checkoutPanel = document.getElementById('mbbb-checkout-panel');
	var checkoutStatus = document.getElementById('mbbb-checkout-status');
	var summaryRoot = document.getElementById('mbbb-summary');
	var expressSlot = document.getElementById('mbbb-express-slot');
	var expressSection = document.getElementById('mbbb-express-checkout');
	var desktopAtc = form ? form.querySelector('.single_add_to_cart_button') : null;
	var ctaButtons = [];
	var hasPulsedCta = false;
	var wooAtcGuardBound = false;
	var atcReleaseScheduled = false;
	var atcReleaseInProgress = false;
	var atcButtonObserver = null;
	var expressObserver = null;
	var slotSections = Array.from(builder.querySelectorAll('.mbbb-slot'));
	var searchThreshold = cfg.searchThreshold || 12;
	var filterThreshold = cfg.filterThreshold || 8;
	var autoAdvanceEnabled = cfg.autoAdvance !== false;
	if (iosPwaSafeMode) {
		autoAdvanceEnabled = false;
	}
	var autoAdvanceDelayMs = cfg.autoAdvanceDelayMs || 700;
	var manualNextThreshold = cfg.manualNextSlotThreshold || 8;
	var rootShell = document.getElementById('mbbb-shell');

	var choices = {};
	var isAdding = false;
	var lastTap = 0;
	var lastBuilderTap = 0;
	var lastOptionActionKey = '';
	var lastOptionActionAt = 0;
	var selectionLockUntil = 0;
	var slotTransitionUntil = 0;
	var advanceTimer = null;
	var editingSlots = {};
	var activeSlotKey = '';
	var coarsePointer = window.matchMedia('(hover: none) and (pointer: coarse)').matches;

	var FILTER_MATCHERS = {
		asbo: function (label) {
			return /\basbo\b/i.test(label);
		},
		'wicked-whites': function (label) {
			return /wicked[\s\-_]*whites?/i.test(label);
		},
		'p-fish-2': function (label) {
			return /p[\s-]?fish|pfish/i.test(label);
		},
		pandemic: function (label) {
			return /pandemic/i.test(label);
		},
		nutz: function (label) {
			return /(^|[\s:_-])nutz($|[\s:_\-+])|nutz[\s\-_\+]*(plus|banana)|product_(cat|tag):nutz/i.test(label);
		},
		'nutz-plus': function (label) {
			return FILTER_MATCHERS.nutz(label);
		},
		'nutz-banana': function (label) {
			return FILTER_MATCHERS.nutz(label);
		},
		stp: function (label) {
			return /\bstp\b/i.test(label);
		},
		calamino: function (label) {
			return /calamino/i.test(label);
		},
		'compulsive-angler': function (label) {
			return /compulsive(\s+angler)?/i.test(label);
		},
		'pop-ups': function (label) {
			return /pop[\s-]?ups?|popups/i.test(label);
		},
		wafters: function (label) {
			return /wafters?/i.test(label);
		},
		skinz: function (label) {
			return /skinz/i.test(label);
		},
		pellets: function (label) {
			return /pellet/i.test(label);
		},
		liquids: function (label) {
			return /liquid|oil|goo|plume|glug|dip/i.test(label);
		},
		'shelf-life': function (label) {
			return /shelf[\s\-]?life/i.test(label);
		},
		'freezer-bait': function (label) {
			return /freezer/i.test(label);
		},
	};

	var FILTER_CHIP_LABELS = cfg.filterChips || {};
	var RANGE_FILTER_IDS = cfg.rangeFilterIds || [
		'asbo',
		'wicked-whites',
		'p-fish-2',
		'pandemic',
		'nutz',
		'stp',
		'calamino',
		'compulsive-angler',
		'pellets',
		'liquids',
		'pop-ups',
		'wafters',
		'skinz',
		'shelf-life',
		'freezer-bait',
	];

	function normalizeFilterId(filterId) {
		var id = String(filterId || '')
			.toLowerCase()
			.trim()
			.replace(/_/g, '-')
			.replace(/[^a-z0-9\-]/g, '-')
			.replace(/\-+/g, '-')
			.replace(/^\-+|\-+$/g, '');
		if (id === 'wicked-white' || id === 'wickedwhites' || id === 'wickedwhite') {
			return 'wicked-whites';
		}
		if (id === 'nutz-plus' || id === 'nutz-banana' || id === 'nutzplus' || id === 'nutzbanana') {
			return 'nutz';
		}
		return id;
	}

	function humanizeFilterLabel(filterId) {
		var canonical = normalizeFilterId(filterId);
		if (!canonical) {
			return '';
		}
		if (FILTER_CHIP_LABELS[canonical]) {
			return FILTER_CHIP_LABELS[canonical];
		}
		return canonical.replace(/-/g, ' ').replace(/\b\w/g, function (c) {
			return c.toUpperCase();
		});
	}

	function deriveTagsFromLabel(label) {
		var hay = String(label || '').toLowerCase();
		var tags = [];
		Object.keys(FILTER_MATCHERS).forEach(function (id) {
			var matcher = FILTER_MATCHERS[id];
			if (matcher && matcher(hay)) {
				id = normalizeFilterId(id);
				if (tags.indexOf(id) === -1) {
					tags.push(id);
				}
			}
		});
		return tags;
	}

	function deriveRangeTagsFromLabel(label) {
		return deriveTagsFromLabel(label).filter(function (id) {
			return RANGE_FILTER_IDS.indexOf(id) !== -1;
		});
	}

	function getOptionTags(btn) {
		if (!btn) {
			return [];
		}
		var tags = (btn.getAttribute('data-tags') || '')
			.split(',')
			.map(normalizeFilterId)
			.filter(Boolean);
		if (tags.length) {
			return tags;
		}
		var label = btn.getAttribute('data-label') || btn.getAttribute('data-search') || '';
		return deriveTagsFromLabel(label);
	}

	function getSessionRangeTags() {
		var active = {};
		cfg.slots.forEach(function (slot) {
			var choice = choices[slot.key];
			if (!choice) {
				return;
			}
			deriveRangeTagsFromLabel(choice.label).forEach(function (id) {
				active[id] = true;
			});
			deriveRangeTagsFromLabel(choice.value).forEach(function (id) {
				active[id] = true;
			});
		});
		return Object.keys(active);
	}

	function ensureFilterChip(slotKey, filterId) {
		var section = getSlotEl(slotKey);
		filterId = normalizeFilterId(filterId);
		if (!section || !filterId || filterId === 'all') {
			return null;
		}
		var existing = section.querySelector(
			'.mbbb-filter-chip[data-filter="' + filterId + '"][data-slot-filter="' + slotKey + '"]'
		);
		if (existing) {
			return existing;
		}
		var toolbar = section.querySelector('.mbbb-slot__filters');
		if (!toolbar) {
			return null;
		}
		var label = humanizeFilterLabel(filterId);
		var chip = document.createElement('button');
		chip.type = 'button';
		chip.className = 'mbbb-filter-chip';
		chip.setAttribute('data-filter', filterId);
		chip.setAttribute('data-filter-label', label);
		chip.setAttribute('data-slot-filter', slotKey);
		chip.textContent = label;
		toolbar.appendChild(chip);
		return chip;
	}

	function syncDynamicFilterChips(slotKey) {
		var section = getSlotEl(slotKey);
		if (!section) {
			return;
		}
		var optionButtons = getSectionOptions(slotKey);
		if (!optionButtons.length) {
			return;
		}
		var visibleIds = {};
		optionButtons.forEach(function (btn) {
			getOptionTags(btn).forEach(function (id) {
				id = normalizeFilterId(id);
				if (id && id !== 'all') {
					visibleIds[id] = true;
				}
			});
			// Fallback: derive from visible labels when tags are missing or incomplete.
			var label = btn.getAttribute('data-label') || btn.getAttribute('data-search') || '';
			deriveTagsFromLabel(label).forEach(function (id) {
				id = normalizeFilterId(id);
				if (id && id !== 'all') {
					visibleIds[id] = true;
				}
			});
		});
		Object.keys(visibleIds).forEach(function (id) {
			ensureFilterChip(slotKey, id);
		});
	}

	function syncSessionFiltersFromChoices() {
		// Intentionally no-op.
		// Filter chips are section-local and should not be injected globally.
	}

	function applyBuilderStyleClasses() {
		document.body.classList.add('mbbb-builder-style-' + displayStyle);
		if (builder) {
			builder.classList.add('mbbb-style-' + displayStyle);
		}
	}

	function applyExpandedCardLayout() {
		slotSections.forEach(function (section) {
			var key = section.getAttribute('data-slot-key');
			var body = getSlotBody(key);
			var trigger = getSlotTrigger(key);
			section.classList.remove('mbbb-slot--future', 'is-collapsed');
			section.classList.add('is-active');
			if (body) {
				body.removeAttribute('hidden');
			}
			if (trigger) {
				trigger.setAttribute('aria-expanded', 'true');
			}
		});
	}

	if (rootShell) {
		document.body.classList.add('mbbb-product', 'mbbb-product--premium', 'mad-bundle-builder-active');
		applyBuilderStyleClasses();
		if (cfg.slots.length >= 10) {
			document.body.classList.add('mbbb-product--large-bundle');
		}
		if (isMobile()) {
			document.body.classList.add('mbbb-product--mobile-builder', 'mbbb-mobile-app-active');
		}
		if (document.body.classList.contains('is-mobile-app-shell')) {
			document.body.classList.add('mbbb-product--app-shell');
		}
	}

	var MBBB_MOBILE_MAX = 1023;

	function isMobile() {
		var viewportWidth =
			window.visualViewport && window.visualViewport.width
				? window.visualViewport.width
				: window.innerWidth;
		return (
			viewportWidth <= MBBB_MOBILE_MAX ||
			window.matchMedia('(max-width: ' + MBBB_MOBILE_MAX + 'px)').matches
		);
	}

	function getMobileBottomInset() {
		var navH = 86;
		var navVar = parseFloat(
			getComputedStyle(document.documentElement).getPropertyValue('--mad-mobile-nav-height')
		);
		if (!isNaN(navVar) && navVar > 0) {
			navH = navVar;
		}
		var stickyH = 0;
		if (sticky && !sticky.hasAttribute('hidden') && sticky.style.display !== 'none') {
			stickyH = sticky.offsetHeight || 0;
		}
		return navH + stickyH + 20;
	}

	function scrollBundleElementIntoView(el) {
		if (!el || !isMobile()) {
			return;
		}
		var topInset = document.body.classList.contains('mad-app-has-top-back') ? 72 : 16;
		var bottomInset = getMobileBottomInset();
		var rect = el.getBoundingClientRect();
		var viewH = window.innerHeight || document.documentElement.clientHeight;
		var needsScroll = rect.bottom > viewH - bottomInset || rect.top < topInset;
		if (!needsScroll) {
			return;
		}
		var targetY = window.scrollY + rect.top - topInset;
		var maxY = window.scrollY + rect.bottom - (viewH - bottomInset);
		if (maxY < targetY) {
			targetY = maxY;
		}
		window.scrollTo({
			top: Math.max(0, targetY),
			behavior: 'smooth',
		});
	}

	function dedupeSummaryOpeners() {
		var canonical = document.getElementById('mbbb-sticky-summary');
		document
			.querySelectorAll('[data-mbbb-open-summary], .mbbb-summary-drawer-trigger, #mbbb-summary-drawer-open')
			.forEach(function (node) {
				if (node !== canonical) {
					node.remove();
				}
			});
	}

	function resolveMobileChrome() {
		summaryDrawer = document.getElementById('mbbb-summary-drawer') || summaryDrawer;
		summaryDrawerList = document.getElementById('mbbb-summary-drawer-list') || summaryDrawerList;
		summaryDrawerCta = document.getElementById('mbbb-summary-drawer-cta') || summaryDrawerCta;
		summaryDrawerPrice = document.getElementById('mbbb-summary-drawer-price') || summaryDrawerPrice;
		sticky = document.getElementById('mbbb-sticky') || sticky;
		stickyBtn = document.getElementById('mbbb-sticky-submit') || stickyBtn;
		stickyStatus = document.getElementById('mbbb-sticky-status') || stickyStatus;
		stickySummaryBtn = document.getElementById('mbbb-sticky-summary') || stickySummaryBtn;
		stickyBtnLabel = stickyBtn ? stickyBtn.querySelector('.mbbb-sticky__btn-label') : stickyBtnLabel;
		successRoot = document.getElementById('mbbb-success') || successRoot;
		successList = document.getElementById('mbbb-success-list') || successList;
	}

	var mobileChromeDocBound = false;

	function bindMobileChrome() {
		resolveMobileChrome();
		dedupeSummaryOpeners();

		if (stickySummaryBtn && cfg.i18n && cfg.i18n.viewSummary) {
			stickySummaryBtn.textContent = cfg.i18n.viewSummary;
		}

		if (stickyBtn && !stickyBtn.dataset.mbbbBound) {
			stickyBtn.dataset.mbbbBound = '1';
			stickyBtn.addEventListener('click', requestAddToCart);
		}

		if (stickySummaryBtn && !stickySummaryBtn.dataset.mbbbBound) {
			stickySummaryBtn.dataset.mbbbBound = '1';
			stickySummaryBtn.addEventListener('click', handleSummaryDrawerToggle);
		}

		if (summaryDrawer && !summaryDrawer.dataset.mbbbBound) {
			summaryDrawer.dataset.mbbbBound = '1';
			summaryDrawer.querySelectorAll('[data-mbbb-drawer-close]').forEach(function (el) {
				el.addEventListener('click', function (event) {
					event.preventDefault();
					closeSummaryDrawer();
				});
			});
		}

		if (summaryDrawerList && !summaryDrawerList.dataset.mbbbEditDelegated) {
			summaryDrawerList.dataset.mbbbEditDelegated = '1';
			summaryDrawerList.addEventListener('click', function (event) {
				var editBtn = event.target.closest('[data-edit-slot]');
				if (!editBtn) {
					return;
				}
				event.preventDefault();
				closeSummaryDrawer();
				openSlot(editBtn.getAttribute('data-edit-slot'), true);
			});
		}

		if (summaryDrawerCta && !summaryDrawerCta.dataset.mbbbBound) {
			summaryDrawerCta.dataset.mbbbBound = '1';
			summaryDrawerCta.addEventListener('click', requestAddToCart);
		}

		if (!mobileChromeDocBound) {
			mobileChromeDocBound = true;

			document.addEventListener(
				'click',
				function (event) {
					if (!(event.target instanceof Element)) {
						return;
					}
					if (!event.target.closest('#mbbb-sticky-summary')) {
						return;
					}
					resolveMobileChrome();
					if (!summaryDrawer) {
						return;
					}
					handleSummaryDrawerToggle(event);
				},
				true
			);

			document.addEventListener('keydown', function (event) {
				resolveMobileChrome();
				if ('Escape' === event.key && summaryDrawer && summaryDrawer.classList.contains('is-open')) {
					closeSummaryDrawer();
				}
			});

			document.addEventListener('mad:mobile-menu-open', function () {
				resolveMobileChrome();
				if (summaryDrawer && summaryDrawer.classList.contains('is-open')) {
					closeSummaryDrawer();
				}
			});
		}

		registerCtaButtons();
	}

	function unlockVariationCartArea() {
		if (!form) {
			return;
		}
		var wrap = form.querySelector('.woocommerce-variation-add-to-cart');
		if (wrap) {
			wrap.classList.remove('woocommerce-variation-add-to-cart-disabled');
		}
	}

	function refreshDesktopAtc() {
		if (!form) {
			return;
		}
		var btn = form.querySelector('.single_add_to_cart_button');
		if (!(btn instanceof HTMLElement)) {
			return;
		}
		if (btn === desktopAtc) {
			return;
		}
		desktopAtc = btn;
		registerCtaButtons();
		if (wooAtcGuardBound) {
			watchBundleAtcButton(desktopAtc);
		}
		if (!desktopAtc.dataset.mbbbAtcBound) {
			desktopAtc.dataset.mbbbAtcBound = '1';
			if (desktopAtc.getAttribute('type') === 'submit') {
				desktopAtc.setAttribute('type', 'button');
			}
			desktopAtc.addEventListener('click', requestAddToCart);
		}
		if (isMobile()) {
			desktopAtc.classList.add('mbbb-hide-mobile-atc');
		}
	}

	function scheduleReleaseWooCommerceAddButtonLock() {
		if (atcReleaseScheduled) {
			return;
		}
		atcReleaseScheduled = true;
		window.requestAnimationFrame(function () {
			atcReleaseScheduled = false;
			releaseWooCommerceAddButtonLock();
		});
	}

	function bundleAtcNeedsUnlock(btn) {
		if (!(btn instanceof HTMLButtonElement) && !(btn instanceof HTMLInputElement)) {
			return false;
		}
		return (
			btn.disabled ||
			btn.hasAttribute('disabled') ||
			btn.classList.contains('disabled') ||
			btn.classList.contains('wc-variation-selection-needed') ||
			btn.classList.contains('wc-variation-is-unavailable')
		);
	}

	function releaseWooCommerceAddButtonLock() {
		if (atcReleaseInProgress) {
			return;
		}
		refreshDesktopAtc();
		if (!form) {
			return;
		}
		var complete = isComplete();
		if (!complete || isAdding) {
			return;
		}
		var cartWrap = form.querySelector('.woocommerce-variation-add-to-cart');
		var wrapLocked = cartWrap && cartWrap.classList.contains('woocommerce-variation-add-to-cart-disabled');
		var mainBtn = form.querySelector('.single_add_to_cart_button');
		var needsUnlock = wrapLocked || (mainBtn && bundleAtcNeedsUnlock(mainBtn));
		if (!needsUnlock) {
			return;
		}
		atcReleaseInProgress = true;
		try {
			unlockVariationCartArea();
			ctaButtons.forEach(function (btn) {
				if (!bundleAtcNeedsUnlock(btn)) {
					return;
				}
				btn.disabled = false;
				btn.removeAttribute('disabled');
				btn.classList.remove('disabled', 'wc-variation-selection-needed', 'wc-variation-is-unavailable');
			});
			if (mainBtn && typeof window.jQuery !== 'undefined' && bundleAtcNeedsUnlock(mainBtn)) {
				window
					.jQuery(mainBtn)
					.prop('disabled', false)
					.removeClass('disabled wc-variation-selection-needed wc-variation-is-unavailable');
			}
		} finally {
			atcReleaseInProgress = false;
		}
	}

	function watchBundleAtcButton(btn) {
		if (iosPwaSafeMode) {
			return;
		}
		if (!(btn instanceof HTMLElement) || typeof MutationObserver === 'undefined') {
			return;
		}
		if (atcButtonObserver) {
			atcButtonObserver.disconnect();
			atcButtonObserver = null;
		}
		atcButtonObserver = new MutationObserver(function () {
			if (isComplete() && !isAdding) {
				scheduleReleaseWooCommerceAddButtonLock();
			}
		});
		atcButtonObserver.observe(btn, {
			attributes: true,
			attributeFilter: ['disabled', 'class'],
		});
	}

	function bindWooVariationAtcGuard() {
		if (!form || wooAtcGuardBound) {
			return;
		}
		wooAtcGuardBound = true;
		scheduleReleaseWooCommerceAddButtonLock();
		if (typeof window.jQuery !== 'undefined') {
			window.jQuery(form).on(
				'woocommerce_variation_has_changed reset_data found_variation hide_variation show_variation check_variations',
				scheduleReleaseWooCommerceAddButtonLock
			);
		}
		if (desktopAtc) {
			watchBundleAtcButton(desktopAtc);
		}
	}

	function requiredSlots() {
		return cfg.slots.filter(function (s) {
			return s.required;
		});
	}

	function requiredDoneCount() {
		var n = 0;
		requiredSlots().forEach(function (slot) {
			if (choices[slot.key]) {
				n += 1;
			}
		});
		return n;
	}

	function allSlotsDoneCount() {
		var n = 0;
		cfg.slots.forEach(function (slot) {
			if (choices[slot.key]) {
				n += 1;
			}
		});
		return n;
	}

	function isComplete() {
		return requiredDoneCount() >= requiredSlots().length;
	}

	function registerCtaButtons() {
		ctaButtons = [];
		if (stickyBtn) {
			stickyBtn.classList.add('mbbb-cta');
			ctaButtons.push(stickyBtn);
		}
		if (summaryDrawerCta) {
			summaryDrawerCta.classList.add('mbbb-cta');
			ctaButtons.push(summaryDrawerCta);
		}
		if (desktopAtc) {
			desktopAtc.classList.add('mbbb-cta', 'mbbb-checkout-panel__cta');
			ctaButtons.push(desktopAtc);
		}
	}

	function getActiveStepNumber() {
		if (!activeSlotKey) {
			return requiredDoneCount() + 1;
		}
		var slot = cfg.slots.find(function (s) {
			return s.key === activeSlotKey;
		});
		if (slot && slot.step) {
			return slot.step;
		}
		var section = getSlotEl(activeSlotKey);
		if (section) {
			return parseInt(section.getAttribute('data-step') || '1', 10);
		}
		return Math.min(requiredDoneCount() + 1, slotCount);
	}

	function clearWooErrorNotices() {
		document.querySelectorAll('.woocommerce-error, .woocommerce-notices-wrapper .woocommerce-error').forEach(function (node) {
			var li = node.closest('li');
			if (li) {
				li.remove();
			} else {
				node.remove();
			}
		});
		var wrapper = document.querySelector('.woocommerce-notices-wrapper');
		if (wrapper && !wrapper.querySelector('.woocommerce-message, .woocommerce-info')) {
			wrapper.innerHTML = '';
			wrapper.setAttribute('hidden', '');
		}
	}

	function getMissingSlotLabels() {
		var labels = [];
		requiredSlots().forEach(function (slot) {
			if (!choices[slot.key]) {
				labels.push(slot.label);
			}
		});
		return labels;
	}

	function clearValidationMessage() {
		if (!validationEl) {
			return;
		}
		validationEl.hidden = true;
		validationEl.textContent = '';
	}

	function showBundleStatusMessage(message, tone) {
		if (!message) {
			return;
		}
		if (checkoutStatus) {
			checkoutStatus.hidden = false;
			checkoutStatus.textContent = message;
			checkoutStatus.classList.remove('is-complete', 'is-incomplete');
			if (tone) {
				checkoutStatus.classList.add(tone);
			}
		}
		if (validationEl) {
			validationEl.hidden = false;
			validationEl.textContent = message;
		}
	}

	function showValidationMessage() {
		var missing = getMissingSlotLabels();
		if (!missing.length) {
			return;
		}
		showMissingBadges = true;
		updateProgress();
		clearValidationMessage();
		clearWooErrorNotices();
		showBundleStatusMessage(cfg.i18n.needToChoose.replace('%s', missing.join(', ')), 'is-incomplete');
		scrollToFirstMissingSlot();
	}

	function updateSummaryDrawerList() {
		if (!summaryDrawerList) {
			return;
		}
		summaryDrawerList.innerHTML = '';
		cfg.slots.forEach(function (slot) {
			var li = document.createElement('li');
			var val = choices[slot.key];
			li.innerHTML =
				'<strong>' +
				escapeHtml(slot.label) +
				'</strong><span>' +
				escapeHtml(val ? val.label : cfg.i18n.notSelected) +
				'</span><button type="button" class="mbbb-summary-drawer__edit" data-edit-slot="' +
				escapeHtml(slot.key) +
				'">' +
				escapeHtml(cfg.i18n.editChoice) +
				'</button>';
			summaryDrawerList.appendChild(li);
		});
	}

	function openSummaryDrawer() {
		resolveMobileChrome();
		if (!summaryDrawer) {
			return;
		}
		updateSummaryDrawerList();
		summaryDrawer.hidden = false;
		summaryDrawer.removeAttribute('hidden');
		summaryDrawer.setAttribute('aria-hidden', 'false');
		summaryDrawer.classList.add('is-open');
		if (stickySummaryBtn) {
			stickySummaryBtn.setAttribute('aria-expanded', 'true');
		}
		document.body.classList.add('mbbb-drawer-open');
	}

	function closeSummaryDrawer() {
		resolveMobileChrome();
		if (!summaryDrawer) {
			return;
		}
		summaryDrawer.hidden = true;
		summaryDrawer.setAttribute('hidden', '');
		summaryDrawer.setAttribute('aria-hidden', 'true');
		summaryDrawer.classList.remove('is-open');
		if (stickySummaryBtn) {
			stickySummaryBtn.setAttribute('aria-expanded', 'false');
		}
		document.body.classList.remove('mbbb-drawer-open');
	}

	function getAdvanceDelay() {
		var delay = parseInt(String(autoAdvanceDelayMs || 700), 10);
		if (isNaN(delay) || delay < 700) {
			return 700;
		}
		if (delay > 900) {
			return 900;
		}
		return delay;
	}

	function isSelectionLocked() {
		return Date.now() < selectionLockUntil || Date.now() < slotTransitionUntil;
	}

	function lockSelection(ms) {
		selectionLockUntil = Date.now() + ms;
	}

	function lockSlotTransition(ms) {
		slotTransitionUntil = Date.now() + ms;
	}

	function clearAdvanceTimer() {
		if (advanceTimer) {
			window.clearTimeout(advanceTimer);
			advanceTimer = null;
		}
	}

	function getSlotOptionCount(slotKey) {
		var section = getSlotEl(slotKey);
		if (!section) {
			return 0;
		}
		var fromAttr = parseInt(section.getAttribute('data-option-count') || '0', 10);
		if (!isNaN(fromAttr) && fromAttr > 0) {
			return fromAttr;
		}
		return section.querySelectorAll('.mbbb-option[data-slot="' + slotKey + '"]').length;
	}

	function shouldAutoAdvanceSlot(slotKey) {
		if (!autoAdvanceEnabled || !isMobile()) {
			return false;
		}
		return getSlotOptionCount(slotKey) < manualNextThreshold;
	}

	function showSelectionFeedback(slotKey, label) {
		var feedback = builder.querySelector('[data-slot-selection-feedback="' + slotKey + '"]');
		if (!feedback) {
			return;
		}
		feedback.textContent = (cfg.i18n.selectedPrefix || 'Selected:') + ' ' + label;
		feedback.removeAttribute('hidden');
		feedback.classList.add('is-visible');
	}

	function hideSelectionFeedback(slotKey) {
		var feedback = builder.querySelector('[data-slot-selection-feedback="' + slotKey + '"]');
		if (!feedback) {
			return;
		}
		feedback.textContent = '';
		feedback.setAttribute('hidden', '');
		feedback.classList.remove('is-visible');
	}

	function showNextChoiceButton(slotKey) {
		var btn = builder.querySelector('[data-slot-next-choice="' + slotKey + '"]');
		if (!btn) {
			return;
		}
		btn.textContent = cfg.i18n.nextChoice || 'Next Choice';
		btn.removeAttribute('hidden');
		btn.classList.add('is-visible');
	}

	function hideNextChoiceButton(slotKey) {
		var btn = builder.querySelector('[data-slot-next-choice="' + slotKey + '"]');
		if (!btn) {
			return;
		}
		btn.setAttribute('hidden', '');
		btn.classList.remove('is-visible');
	}

	function optionMatchesFilter(btn, filterId, searchText) {
		filterId = normalizeFilterId(filterId);
		var label = btn.getAttribute('data-search') || btn.getAttribute('data-label') || '';
		var hay = String(label).toLowerCase();
		var matchesSearch = !searchText || hay.indexOf(searchText) !== -1;
		if (!matchesSearch) {
			return false;
		}
		if (!filterId || filterId === 'all') {
			return true;
		}
		var tags = (btn.getAttribute('data-tags') || '')
			.split(',')
			.map(normalizeFilterId)
			.filter(Boolean);
		if (tags.indexOf(filterId) !== -1) {
			return true;
		}
		var matcher = FILTER_MATCHERS[filterId];
		return matcher ? matcher(hay) : false;
	}

	function getActiveFilterLabel(slotKey) {
		var section = getSlotEl(slotKey);
		if (!section) {
			return '';
		}
		var activeChip = section.querySelector('.mbbb-filter-chip.is-active[data-slot-filter="' + slotKey + '"]');
		if (!activeChip) {
			return '';
		}
		var filterId = activeChip.getAttribute('data-filter') || 'all';
		if (filterId === 'all') {
			return '';
		}
		return activeChip.getAttribute('data-filter-label') || filterId;
	}

	function updateSlotFilterMeta(slotKey, visible, total) {
		var section = getSlotEl(slotKey);
		if (!section) {
			return;
		}
		var countEl = section.querySelector('[data-slot-filter-count="' + slotKey + '"]');
		var activeEl = section.querySelector('[data-slot-filter-active="' + slotKey + '"]');
		var clearBtn = section.querySelector('[data-slot-filter-clear="' + slotKey + '"]');
		var filterLabel = getActiveFilterLabel(slotKey);
		var filters = getSlotFilters(slotKey);

		if (countEl) {
			if (filterLabel) {
				countEl.textContent = (cfg.i18n.filteredCount || '%1$d %2$s options')
					.replace('%1$d', String(visible))
					.replace('%2$s', filterLabel);
			} else {
				countEl.textContent = (cfg.i18n.optionsCount || '%d options').replace('%d', String(visible));
			}
		}

		if (activeEl) {
			if (filterLabel) {
				activeEl.textContent = (cfg.i18n.showingFilter || 'Showing %s').replace('%s', filterLabel);
				activeEl.removeAttribute('hidden');
			} else {
				activeEl.textContent = '';
				activeEl.setAttribute('hidden', '');
			}
		}

		if (clearBtn) {
			if (filters.filter && filters.filter !== 'all') {
				clearBtn.removeAttribute('hidden');
			} else {
				clearBtn.setAttribute('hidden', '');
			}
		}
	}

	function advanceAfterSelection(slotKey) {
		clearAdvanceTimer();
		hideSelectionFeedback(slotKey);
		hideNextChoiceButton(slotKey);
		lockSlotTransition(700);

		var nextKey = nextIncompleteRequiredSlot(slotKey);
		window.requestAnimationFrame(function () {
			if (isMobile() && mobileStepFlow) {
				if (nextKey) {
					openSlot(nextKey, true);
					return;
				}
				openSlot('', false);
				slotSections.forEach(function (section) {
					section.classList.remove('is-active');
					section.classList.add('is-collapsed');
					var body = getSlotBody(section.getAttribute('data-slot-key'));
					var trigger = getSlotTrigger(section.getAttribute('data-slot-key'));
					if (body) {
						body.setAttribute('hidden', '');
					}
					if (trigger) {
						trigger.setAttribute('aria-expanded', 'false');
					}
				});
				return;
			}

			if (isMobile() && !mobileStepFlow && nextKey) {
				var nextSection = getSlotEl(nextKey);
				if (nextSection) {
					scrollBundleElementIntoView(nextSection);
				}
				return;
			}

			var currentBody = getSlotBody(slotKey);
			var currentTrigger = getSlotTrigger(slotKey);
			var currentSection = getSlotEl(slotKey);
			if (currentSection) {
				currentSection.classList.remove('is-active');
				currentSection.classList.add('is-collapsed');
			}
			if (currentBody) {
				currentBody.setAttribute('hidden', '');
			}
			if (currentTrigger) {
				currentTrigger.setAttribute('aria-expanded', 'false');
			}

			if (nextKey) {
				openSlot(nextKey, true);
			}
		});
	}

	function scheduleAdvanceAfterSelection(slotKey) {
		clearAdvanceTimer();
		if (!isMobile()) {
			hideSelectionFeedback(slotKey);
			hideNextChoiceButton(slotKey);
			advanceAfterSelection(slotKey);
			return;
		}
		var filters = getSlotFilters(slotKey);
		var isFiltering = !!filters.search || (!!filters.filter && filters.filter !== 'all');
		if (isFiltering) {
			showNextChoiceButton(slotKey);
			return;
		}
		if (!shouldAutoAdvanceSlot(slotKey)) {
			showNextChoiceButton(slotKey);
			return;
		}
		hideNextChoiceButton(slotKey);
		var delay = getAdvanceDelay();
		lockSlotTransition(delay + 250);
		advanceTimer = window.setTimeout(function () {
			advanceTimer = null;
			advanceAfterSelection(slotKey);
		}, delay);
	}

	function selectOption(slotKey, value, label) {
		if (isSelectionLocked()) {
			return;
		}

		var actionKey = slotKey + ':' + String(value || '');
		var now = Date.now();
		if (actionKey === lastOptionActionKey && now - lastOptionActionAt < 500) {
			return;
		}
		lastOptionActionKey = actionKey;
		lastOptionActionAt = now;
		lockSelection(700);

		if (choices[slotKey] && choices[slotKey].value === value) {
			editingSlots[slotKey] = false;
			setChoice(slotKey, '', '', false);
			clearAdvanceTimer();
			hideSelectionFeedback(slotKey);
			hideNextChoiceButton(slotKey);
			return;
		}

		setChoice(slotKey, value, label, false);
		if (!value) {
			return;
		}

		if (isMobile()) {
			showSelectionFeedback(slotKey, label);
		}
		editingSlots[slotKey] = false;
		scheduleAdvanceAfterSelection(slotKey);
	}

	function flashSlotComplete(slotKey) {
		var section = getSlotEl(slotKey);
		if (!section) {
			return;
		}
		section.classList.add('is-just-complete');
		window.setTimeout(function () {
			section.classList.remove('is-just-complete');
		}, 500);
	}

	function initSearchClearButtons() {
		builder.querySelectorAll('[data-slot-search-clear]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var key = btn.getAttribute('data-slot-search-clear');
				var input = builder.querySelector('[data-slot-search="' + key + '"]');
				if (input) {
					input.value = '';
					btn.classList.remove('is-visible');
					applySlotFilters(key);
					input.focus();
				}
			});
		});
		builder.querySelectorAll('[data-slot-search]').forEach(function (input) {
			input.addEventListener('input', function () {
				var key = input.getAttribute('data-slot-search');
				var clearBtn = builder.querySelector('[data-slot-search-clear="' + key + '"]');
				if (clearBtn) {
					clearBtn.classList.toggle('is-visible', String(input.value || '').length > 0);
				}
			});
		});
	}

	function choicesLeftCount() {
		return Math.max(0, requiredSlots().length - requiredDoneCount());
	}

	function getCtaLabel(complete, isBusy) {
		if (isBusy) {
			return cfg.i18n.adding;
		}
		if (complete) {
			return cfg.i18n.addBundle;
		}
		return cfg.i18n.completeChoicesShort || cfg.i18n.completeChoices;
	}

	function setCtaState(complete, isBusy) {
		var label = getCtaLabel(complete, isBusy);
		ctaButtons.forEach(function (btn) {
			btn.classList.toggle('mbbb-cta--ready', complete && !isBusy);
			btn.classList.toggle('mbbb-cta--locked', !complete && !isBusy);
			btn.classList.toggle('mbbb-cta--loading', isBusy);
			btn.setAttribute('aria-disabled', isBusy || !complete ? 'true' : 'false');
			if (isBusy) {
				btn.setAttribute('aria-busy', 'true');
				btn.disabled = true;
			} else {
				btn.removeAttribute('aria-busy');
				btn.disabled = false;
				btn.removeAttribute('disabled');
				btn.classList.remove('disabled', 'wc-variation-selection-needed', 'wc-variation-is-unavailable');
			}
		});
		document.querySelectorAll('#mbbb-sticky-submit .mbbb-sticky__btn-label, #mbbb-summary-drawer-cta .mbbb-sticky__btn-label').forEach(function (node) {
			node.textContent = label;
		});
		if (desktopAtc) {
			if ('INPUT' === desktopAtc.tagName) {
				desktopAtc.value = label;
			} else {
				var textNode = desktopAtc.querySelector('.mbbb-cta__text');
				if (textNode) {
					textNode.textContent = label;
				} else {
					desktopAtc.textContent = label;
				}
			}
		}
		scheduleReleaseWooCommerceAddButtonLock();
	}

	function scrollToFirstMissingSlot() {
		var key = nextIncompleteRequiredSlot('');
		if (!key) {
			return;
		}
		if (isMobile()) {
			openSlot(key, true);
		} else {
			openSlot(key, true);
		}
		var section = getSlotEl(key);
		if (section) {
			section.classList.add('mbbb-slot--needs-choice');
			window.setTimeout(function () {
				section.classList.remove('mbbb-slot--needs-choice');
			}, 2200);
		}
	}

	function maybePulseCta(complete) {
		if (!complete || hasPulsedCta) {
			return;
		}
		hasPulsedCta = true;
		ctaButtons.forEach(function (btn) {
			btn.classList.add('mbbb-cta--pulse');
			window.setTimeout(function () {
				btn.classList.remove('mbbb-cta--pulse');
			}, 1400);
		});
	}

	function isExpressCandidate(node) {
		if (!(node instanceof HTMLElement) || node.closest('[data-mbbb-express-slot]')) {
			return false;
		}
		if (node.classList.contains('mbbb-checkout-panel') || node.classList.contains('mbbb-express-checkout') || node.classList.contains('mbbb-trust-strip')) {
			return false;
		}
		var tag = node.tagName;
		if ('SCRIPT' === tag || 'STYLE' === tag) {
			return false;
		}
		var cls = String(node.className || '');
		var id = String(node.id || '');
		var hay = cls + ' ' + id;
		return /paypal|ppcp|ppc-|express|payment-request|stripe|apple-pay|google-pay|wc-braintree|wcppec/i.test(hay);
	}

	function organizeExpressCheckout() {
		if (!expressSlot || !form) {
			return;
		}
		var selectors = [
			'.woocommerce-paypal-payments',
			'#woocommerce-paypal-payments',
			'.ppcp-button-wrapper',
			'.ppc-button-wrapper',
			'.wcppec-checkout-buttons',
			'.wc-block-components-express-payment',
			'#wc-stripe-payment-request-wrapper',
			'.wc-stripe-payment-request-button-separator',
			'.payment_request_button',
		];
		selectors.forEach(function (sel) {
			form.querySelectorAll(sel).forEach(function (node) {
				if (node.parentElement !== expressSlot && !expressSlot.contains(node)) {
					expressSlot.appendChild(node);
				}
			});
		});
		if (desktopAtc) {
			var sibling = desktopAtc.nextElementSibling;
			while (sibling) {
				var next = sibling.nextElementSibling;
				if (sibling.id === 'mbbb-express-checkout' || sibling.id === 'mbbb-trust-strip') {
					break;
				}
				if (isExpressCandidate(sibling)) {
					expressSlot.appendChild(sibling);
				}
				sibling = next;
			}
		}
		if (expressSection) {
			expressSection.classList.toggle('is-empty', expressSlot.childElementCount === 0);
		}
		updateExpressVisibility(isComplete());
	}

	function updateExpressVisibility(complete) {
		if (!expressSection) {
			return;
		}
		var hasButtons = expressSlot && expressSlot.childElementCount > 0;
		expressSection.hidden = !complete || !hasButtons;
		expressSection.classList.toggle('is-visible', complete && hasButtons);
	}

	function syncStickyLayout() {
		resolveMobileChrome();
		if (!sticky) {
			document.body.classList.remove('mbbb-sticky-active');
			return;
		}
		if (document.body.classList.contains('mbbb-success-open')) {
			setStickyChromeVisible(false);
			document.body.classList.remove('mbbb-sticky-active');
			return;
		}
		var showSticky = isMobile();
		if (showSticky) {
			sticky.removeAttribute('hidden');
			sticky.style.display = '';
		} else {
			sticky.setAttribute('hidden', '');
			sticky.style.display = 'none';
		}
		document.body.classList.toggle('mbbb-sticky-active', showSticky);
		document.body.classList.toggle('mad-bundle-builder-active', !!rootShell);
		if (showSticky) {
			window.requestAnimationFrame(resizeSpacer);
		}
	}

	function nextIncompleteRequiredSlot(afterKey) {
		var foundAfter = !afterKey;
		var i;
		for (i = 0; i < cfg.slots.length; i += 1) {
			var slot = cfg.slots[i];
			if (!slot.required) {
				continue;
			}
			if (afterKey && slot.key !== afterKey) {
				if (!foundAfter) {
					continue;
				}
			}
			if (afterKey && slot.key === afterKey) {
				foundAfter = true;
				continue;
			}
			if (!choices[slot.key]) {
				return slot.key;
			}
		}
		for (i = 0; i < cfg.slots.length; i += 1) {
			slot = cfg.slots[i];
			if (slot.required && !choices[slot.key]) {
				return slot.key;
			}
		}
		return '';
	}

	function getSlotEl(key) {
		return document.getElementById('mbbb-slot-' + key);
	}

	function getSlotBody(key) {
		return document.getElementById('mbbb-slot-body-' + key);
	}

	function getSlotTrigger(key) {
		return document.getElementById('mbbb-slot-trigger-' + key);
	}

	function enforceDesktopAccordion(startKey) {
		if (isMobile()) {
			return;
		}
		var targetKey = startKey || nextIncompleteRequiredSlot('');
		if (!targetKey && slotSections.length) {
			targetKey = slotSections[0].getAttribute('data-slot-key') || '';
		}
		if (!targetKey) {
			return;
		}
		slotSections.forEach(function (section) {
			var sectionKey = section.getAttribute('data-slot-key');
			var isTarget = sectionKey === targetKey;
			var body = getSlotBody(sectionKey);
			var trigger = getSlotTrigger(sectionKey);
			section.classList.toggle('is-active', isTarget);
			section.classList.toggle('is-collapsed', !isTarget);
			if (body) {
				if (isTarget) {
					body.removeAttribute('hidden');
				} else {
					body.setAttribute('hidden', '');
				}
			}
			if (trigger) {
				trigger.setAttribute('aria-expanded', isTarget ? 'true' : 'false');
			}
		});
		activeSlotKey = targetKey;
	}

	function openSlot(key, scroll) {
		activeSlotKey = key || '';

		if (key && choices[key] && choices[key].value) {
			editingSlots[key] = true;
			clearAdvanceTimer();
			hideNextChoiceButton(key);
		}

		if (isMobile() && !mobileStepFlow && (displayStyle === 'card' || displayStyle === 'accordion')) {
			applyExpandedCardLayout();
			if (scroll && key) {
				var expandedTarget = getSlotEl(key);
				if (expandedTarget) {
					scrollBundleElementIntoView(expandedTarget);
				}
			}
			return;
		}

		if (!isMobile()) {
			if (!key) {
				return;
			}
			slotSections.forEach(function (section) {
				var sectionKey = section.getAttribute('data-slot-key');
				var isTarget = sectionKey === key;
				var body = getSlotBody(sectionKey);
				var trigger = getSlotTrigger(sectionKey);
				section.classList.toggle('is-active', isTarget);
				section.classList.toggle('is-collapsed', !isTarget);
				if (body) {
					if (isTarget) {
						body.removeAttribute('hidden');
					} else {
						body.setAttribute('hidden', '');
					}
				}
				if (trigger) {
					trigger.setAttribute('aria-expanded', isTarget ? 'true' : 'false');
				}
			});
			if (scroll) {
				var targetSection = getSlotEl(key);
				if (targetSection) {
					scrollBundleElementIntoView(targetSection);
				}
			}
			applySlotFilters(key);
			return;
		}

		if (!key) {
			return;
		}

		slotSections.forEach(function (section) {
			var sectionKey = section.getAttribute('data-slot-key');
			var isTarget = sectionKey === key;
			var body = getSlotBody(sectionKey);
			var trigger = getSlotTrigger(sectionKey);
			section.classList.toggle('is-active', isTarget);
			section.classList.toggle('is-collapsed', !isTarget);
			if (body) {
				if (isTarget) {
					body.removeAttribute('hidden');
				} else {
					body.setAttribute('hidden', '');
				}
			}
			if (trigger) {
				trigger.setAttribute('aria-expanded', isTarget ? 'true' : 'false');
			}
		});
		applyMobileVisibility();
		applySlotFilters(key);
		if (scroll) {
			var el = getSlotEl(key);
			if (el) {
				scrollBundleElementIntoView(el);
			}
		}
	}

	function applyMobileVisibility() {
		if (!mobileStepFlow) {
			slotSections.forEach(function (section) {
				section.classList.remove('mbbb-slot--future');
			});
			if (isMobile()) {
				applyExpandedCardLayout();
			}
			document.dispatchEvent(new CustomEvent('mbbb:layout-refresh'));
			return;
		}
		if (!isMobile()) {
			slotSections.forEach(function (section) {
				section.classList.remove('mbbb-slot--future');
			});
			return;
		}
		var reachedActive = false;
		slotSections.forEach(function (section) {
			var key = section.getAttribute('data-slot-key');
			var isComplete = !!choices[key];
			var isActive = key === activeSlotKey;
			if (isActive) {
				reachedActive = true;
			}
			section.classList.toggle('mbbb-slot--future', !reachedActive && !isComplete && !isActive);
		});
	}

	function updateProgress(skipSyncFromDom) {
		if (!skipSyncFromDom) {
			syncChoicesFromDom();
		}
		var total = cfg.slots.length;
		var doneAll = allSlotsDoneCount();
		var reqTotal = requiredSlots().length;
		var reqDone = requiredDoneCount();
		var left = Math.max(0, reqTotal - reqDone);
		var complete = isComplete();

		if (progressText) {
			if (isMobile() && cfg.i18n.progressRatio) {
				progressText.textContent = cfg.i18n.progressRatio
					.replace('%1$d', String(reqDone))
					.replace('%2$d', String(reqTotal));
			} else if (complete) {
				progressText.textContent = cfg.i18n.ready || cfg.i18n.remainingNone;
			} else {
				progressText.textContent = cfg.i18n.progress.replace('%1$d', String(doneAll)).replace('%2$d', String(total));
			}
		}
		if (progressFill) {
			progressFill.style.width = reqTotal ? Math.round((reqDone / reqTotal) * 100) + '%' : '0%';
		}
		if (stickyStatus) {
			stickyStatus.textContent = cfg.i18n.progressRatio
				? cfg.i18n.progressRatio.replace('%1$d', String(reqDone)).replace('%2$d', String(reqTotal))
				: String(reqDone) + '/' + String(reqTotal) + ' complete';
			stickyStatus.classList.toggle('is-complete', complete);
		}
		if (checkoutStatus) {
			checkoutStatus.hidden = true;
			checkoutStatus.textContent = '';
		}
		if (checkoutPanel) {
			checkoutPanel.classList.toggle('is-bundle-complete', complete);
		}
		if (summaryRoot) {
			summaryRoot.classList.toggle('is-bundle-complete', complete);
			if (complete && summaryToggle && summaryPanel) {
				summaryToggle.setAttribute('aria-expanded', 'true');
				summaryPanel.hidden = false;
			}
		}
		document.body.classList.toggle('mbbb-bundle-complete', complete);
		updateSummaryDrawerList();
		if (summaryDrawerPrice) {
			var stickyPrice = document.querySelector('.mbbb-sticky__price');
			summaryDrawerPrice.innerHTML = stickyPrice ? stickyPrice.innerHTML : '';
		}
		setCtaState(complete, isAdding);
		maybePulseCta(complete);
		updateExpressVisibility(complete);

		cfg.slots.forEach(function (slot) {
			var section = getSlotEl(slot.key);
			var status = document.querySelector('[data-slot-status="' + slot.key + '"]');
			var badge = document.querySelector('[data-slot-required-badge="' + slot.key + '"]');
			if (section) {
				section.classList.toggle('is-complete', !!choices[slot.key]);
			}
			if (status) {
				status.textContent = choices[slot.key] ? choices[slot.key].label : cfg.i18n.notSelected;
			}
			if (badge) {
				badge.classList.remove(
					'mbbb-slot__status-badge--required',
					'mbbb-slot__status-badge--complete',
					'mbbb-slot__status-badge--missing',
					'mbbb-slot__status-badge--optional'
				);
				if (choices[slot.key]) {
					badge.textContent = cfg.i18n.statusComplete || 'Complete';
					badge.classList.add('mbbb-slot__status-badge--complete');
				} else if (slot.required) {
					if (showMissingBadges) {
						badge.textContent = cfg.i18n.statusMissing || 'Missing';
						badge.classList.add('mbbb-slot__status-badge--missing');
					} else {
						badge.textContent = cfg.i18n.statusChoose || cfg.i18n.statusRequired || 'Choose';
						badge.classList.add('mbbb-slot__status-badge--required');
					}
				} else {
					badge.textContent = cfg.i18n.statusOptional || 'Optional';
					badge.classList.add('mbbb-slot__status-badge--optional');
				}
			}
			var changeEl = section ? section.querySelector('[data-slot-change="' + slot.key + '"]') : null;
			if (changeEl) {
				changeEl.hidden = !choices[slot.key];
			}
		});

		unlockVariationCartArea();
		if (complete) {
			clearValidationMessage();
		}
		syncStickyLayout();

		updateSummary();
		applyMobileVisibility();
	}

	function updateSummary() {
		if (!summaryList) {
			return;
		}
		summaryList.innerHTML = '';
		cfg.slots.forEach(function (slot) {
			var li = document.createElement('li');
			var val = choices[slot.key];
			li.innerHTML =
				'<strong>' +
				escapeHtml(slot.label) +
				'</strong><span>' +
				escapeHtml(val ? val.label : cfg.i18n.notSelected) +
				'</span>';
			summaryList.appendChild(li);
		});
	}

	function escapeHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function setChoice(slotKey, value, label, scrollNext) {
		if (value) {
			clearValidationMessage();
			clearWooErrorNotices();
		}
		choices[slotKey] = value ? { value: value, label: label } : null;

		var input = builder.querySelector('.mbbb-choice-input[data-slot="' + slotKey + '"]');
		if (input) {
			input.value = value || '';
		}

		builder.querySelectorAll('.mbbb-option[data-slot="' + slotKey + '"]').forEach(function (btn) {
			var selected = btn.getAttribute('data-value') === value && value !== '';
			btn.classList.toggle('is-selected', selected);
			btn.setAttribute('aria-pressed', selected ? 'true' : 'false');
		});

		var select = builder.querySelector('.mbbb-select[data-slot="' + slotKey + '"]');
		if (select) {
			select.value = value || '';
		}

		updateProgress();
		syncSessionFiltersFromChoices();

		if (value) {
			flashSlotComplete(slotKey);
		}

		if (value && scrollNext) {
			var nextKey = nextIncompleteRequiredSlot(slotKey);
			window.requestAnimationFrame(function () {
				if (isMobile() && mobileStepFlow) {
					if (nextKey) {
						openSlot(nextKey, true);
						return;
					}
					openSlot('', false);
					slotSections.forEach(function (section) {
						section.classList.remove('is-active');
						section.classList.add('is-collapsed');
						var body = getSlotBody(section.getAttribute('data-slot-key'));
						var trigger = getSlotTrigger(section.getAttribute('data-slot-key'));
						if (body) {
							body.setAttribute('hidden', '');
						}
						if (trigger) {
							trigger.setAttribute('aria-expanded', 'false');
						}
					});
					return;
				}
				if (isMobile() && !mobileStepFlow && nextKey) {
					var nextSection = getSlotEl(nextKey);
					if (nextSection) {
						scrollBundleElementIntoView(nextSection);
					}
					return;
				}

				var currentSection = getSlotEl(slotKey);
				var currentBody = getSlotBody(slotKey);
				var currentTrigger = getSlotTrigger(slotKey);
				if (currentSection) {
					currentSection.classList.remove('is-active');
					currentSection.classList.add('is-collapsed');
				}
				if (currentBody) {
					currentBody.setAttribute('hidden', '');
				}
				if (currentTrigger) {
					currentTrigger.setAttribute('aria-expanded', 'false');
				}

				if (nextKey) {
					openSlot(nextKey, true);
				}
			});
		}
	}

	function getChoiceLabelFromDom(slotKey, value) {
		if (!value) {
			return '';
		}
		var label = '';
		builder.querySelectorAll('.mbbb-option[data-slot="' + slotKey + '"]').forEach(function (btn) {
			if (!label && btn.getAttribute('data-value') === value) {
				label = btn.getAttribute('data-label') || '';
			}
		});
		if (!label) {
			var select = builder.querySelector('.mbbb-select[data-slot="' + slotKey + '"]');
			if (select && select.value === value) {
				var opt = select.options[select.selectedIndex];
				label = opt ? String(opt.text || '') : '';
			}
		}
		return label || value;
	}

	function syncChoicesFromDom() {
		cfg.slots.forEach(function (slot) {
			var slotKey = slot.key;
			var value = '';
			var selectedBtn = builder.querySelector(
				'.mbbb-option[data-slot="' +
					slotKey +
					'"][aria-pressed="true"], .mbbb-option[data-slot="' +
					slotKey +
					'"].is-selected'
			);
			if (selectedBtn) {
				value = String(selectedBtn.getAttribute('data-value') || '');
			}
			var input = builder.querySelector('.mbbb-choice-input[data-slot="' + slotKey + '"]');
			if (!value && input) {
				value = String(input.value || '');
			}
			if (!value) {
				var select = builder.querySelector('.mbbb-select[data-slot="' + slotKey + '"]');
				if (select && String(select.value || '')) {
					value = String(select.value || '');
					if (input) {
						input.value = value;
					}
				}
			}
			if (value) {
				choices[slotKey] = { value: value, label: getChoiceLabelFromDom(slotKey, value) };
			} else if (!choices[slotKey] || !choices[slotKey].value) {
				choices[slotKey] = null;
			}
		});
	}

	function getSlotFilters(slotKey) {
		var section = getSlotEl(slotKey);
		if (!section) {
			return { search: '', filter: 'all' };
		}
		var searchInput = section.querySelector('[data-slot-search="' + slotKey + '"]');
		var activeChip = section.querySelector('.mbbb-filter-chip.is-active[data-slot-filter="' + slotKey + '"]');
		return {
			search: searchInput ? String(searchInput.value || '').trim().toLowerCase() : '',
			filter: activeChip ? normalizeFilterId(activeChip.getAttribute('data-filter') || 'all') : 'all',
		};
	}

	function getSectionOptions(slotKey) {
		var section = getSlotEl(slotKey);
		if (!section) {
			return [];
		}
		return Array.from(section.querySelectorAll('.mbbb-option[data-slot="' + slotKey + '"]'));
	}

	function chipHasSlotMatches(slotKey, filterId) {
		return getSectionOptions(slotKey).some(function (btn) {
			return optionMatchesFilter(btn, filterId, '');
		});
	}

	function pruneSlotFilterChips(slotKey) {
		var section = getSlotEl(slotKey);
		if (!section) {
			return;
		}
		var toolbar = section.querySelector('.mbbb-slot__filters');
		if (!toolbar) {
			return;
		}

		var allChip = section.querySelector('.mbbb-filter-chip[data-filter="all"][data-slot-filter="' + slotKey + '"]');
		var chips = Array.from(
			section.querySelectorAll('.mbbb-filter-chip[data-slot-filter="' + slotKey + '"]:not([data-filter="all"])')
		);
		var visibleTypeChips = [];

		chips.forEach(function (chip) {
			var filterId = chip.getAttribute('data-filter') || '';
			var hasMatches = !!filterId && chipHasSlotMatches(slotKey, filterId);
			chip.hidden = !hasMatches;
			chip.setAttribute('aria-hidden', hasMatches ? 'false' : 'true');
			chip.classList.toggle('is-hidden', !hasMatches);
			if (hasMatches) {
				visibleTypeChips.push(chip);
			}
		});

		if (allChip) {
			var showAll = visibleTypeChips.length > 1;
			allChip.hidden = !showAll;
			allChip.setAttribute('aria-hidden', showAll ? 'false' : 'true');
			allChip.classList.toggle('is-hidden', !showAll);
		}

		var activeChip = section.querySelector('.mbbb-filter-chip.is-active[data-slot-filter="' + slotKey + '"]');
		var activeValid = activeChip && !activeChip.hidden;
		if (!activeValid) {
			if (allChip && !allChip.hidden) {
				activateFilterChip(allChip);
			} else if (visibleTypeChips.length) {
				activateFilterChip(visibleTypeChips[0]);
			}
		}

		var shouldHideToolbar = visibleTypeChips.length === 0;
		toolbar.hidden = shouldHideToolbar;
		toolbar.setAttribute('aria-hidden', shouldHideToolbar ? 'true' : 'false');
	}

	function applySlotFilters(slotKey) {
		var section = getSlotEl(slotKey);
		if (!section) {
			return;
		}
		syncDynamicFilterChips(slotKey);
		pruneSlotFilterChips(slotKey);
		var filters = getSlotFilters(slotKey);
		if (diag && typeof diag.isLooping === 'function' && diag.isLooping('bundle_slot_filters_' + slotKey, 35, 2500)) {
			diag.log('bundle_filter_loop_detected', {
				slotKey: slotKey,
				search: filters.search || '',
				filter: filters.filter || 'all',
			});
			return;
		}
		if (diag && typeof diag.bump === 'function') {
			var count = diag.bump('bundle_slot_filters_apply');
			if (count % 20 === 0) {
				diag.log('bundle_filter_apply_progress', { count: count, slotKey: slotKey });
			}
		}
		var options = section.querySelectorAll('.mbbb-option[data-slot="' + slotKey + '"]');
		var visible = 0;
		var total = options.length;
		options.forEach(function (btn) {
			var show = optionMatchesFilter(btn, filters.filter, filters.search);
			btn.classList.toggle('is-hidden', !show);
			btn.hidden = !show;
			btn.setAttribute('aria-hidden', show ? 'false' : 'true');
			if (show) {
				visible += 1;
			}
		});
		var emptyEl = section.querySelector('[data-slot-empty="' + slotKey + '"]');
		if (emptyEl) {
			var hasActiveSearch = !!(filters.search && String(filters.search).trim());
			var hasActiveFilter = !!(filters.filter && filters.filter !== 'all');
			// Only show empty-state copy after a real search/filter — never as a default debug message.
			if (visible > 0 || (!hasActiveSearch && !hasActiveFilter)) {
				emptyEl.hidden = true;
				emptyEl.textContent = '';
			} else {
				emptyEl.hidden = false;
				emptyEl.textContent = hasActiveFilter
					? cfg.i18n.noFilterResults || cfg.i18n.noResults
					: cfg.i18n.noResults;
			}
		}
		updateSlotFilterMeta(slotKey, visible, total);
	}

	function activateFilterChip(chip) {
		if (!chip) {
			return;
		}
		var slotKey = chip.getAttribute('data-slot-filter');
		if (!slotKey) {
			return;
		}
		var section = getSlotEl(slotKey);
		if (!section) {
			return;
		}
		section.querySelectorAll('.mbbb-filter-chip[data-slot-filter="' + slotKey + '"]').forEach(function (c) {
			c.classList.toggle('is-active', c === chip);
		});
		applySlotFilters(slotKey);
	}

	function clearSlotFilter(slotKey) {
		var section = getSlotEl(slotKey);
		if (!section) {
			return;
		}
		var allChip = section.querySelector('.mbbb-filter-chip[data-filter="all"][data-slot-filter="' + slotKey + '"]');
		if (allChip) {
			activateFilterChip(allChip);
		}
	}

	function resetSlotFiltersAndSearch() {
		slotSections.forEach(function (section) {
			var key = section.getAttribute('data-slot-key');
			if (!key) {
				return;
			}
			var searchInput = section.querySelector('[data-slot-search="' + key + '"]');
			if (searchInput) {
				searchInput.value = '';
			}
			var clearBtn = section.querySelector('[data-slot-search-clear="' + key + '"]');
			if (clearBtn) {
				clearBtn.classList.remove('is-visible');
			}
			var allChip = section.querySelector('.mbbb-filter-chip[data-filter="all"][data-slot-filter="' + key + '"]');
			if (allChip) {
				section.querySelectorAll('.mbbb-filter-chip[data-slot-filter="' + key + '"]').forEach(function (chip) {
					chip.classList.toggle('is-active', chip === allChip);
				});
			}
		});
	}

	function resizeSpacer() {
		var spacer = document.getElementById('mbbb-builder-spacer');
		if (!spacer || !sticky || sticky.hasAttribute('hidden') || 'none' === sticky.style.display) {
			return;
		}
		var h = sticky.offsetHeight + 16;
		spacer.style.height = h + 'px';
		document.documentElement.style.setProperty('--mbbb-sticky-h', sticky.offsetHeight / 16 + 'rem');
	}

	function getChoicesPayload() {
		var payload = {};
		Object.keys(choices).forEach(function (key) {
			if (choices[key]) {
				payload[key] = choices[key].value;
			}
		});
		return payload;
	}

	function setStickyChromeVisible(visible) {
		resolveMobileChrome();
		if (sticky) {
			if (visible) {
				sticky.removeAttribute('hidden');
				sticky.style.display = '';
			} else {
				sticky.setAttribute('hidden', '');
				sticky.style.display = 'none';
			}
		}
		document.body.classList.toggle('mbbb-sticky-suppressed', !visible);
	}

	function showSuccess(displayMap) {
		if (!successRoot || !successList) {
			return;
		}
		closeSummaryDrawer();
		setStickyChromeVisible(false);
		successList.innerHTML = '';
		Object.keys(displayMap || {}).forEach(function (label) {
			var li = document.createElement('li');
			li.innerHTML = '<strong>' + escapeHtml(label) + ':</strong> ' + escapeHtml(displayMap[label]);
			successList.appendChild(li);
		});
		successRoot.removeAttribute('hidden');
		successRoot.setAttribute('aria-hidden', 'false');
		document.body.classList.add('mbbb-success-open');
	}

	function hideSuccess() {
		if (!successRoot) {
			return;
		}
		successRoot.setAttribute('hidden', '');
		successRoot.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('mbbb-success-open');
		syncStickyLayout();
	}

	function resetLegacyVariationFields() {
		if (!form) {
			return;
		}
		var variationId = form.querySelector('input[name="variation_id"]');
		if (variationId) {
			variationId.value = '0';
		}
		form.querySelectorAll('[name^="attribute_"]').forEach(function (field) {
			if (field instanceof HTMLSelectElement) {
				field.selectedIndex = 0;
			} else if (field instanceof HTMLInputElement) {
				field.value = '';
			}
		});
	}

	function requestAddToCart(event) {
		if (event) {
			event.preventDefault();
			event.stopPropagation();
			if (typeof event.stopImmediatePropagation === 'function') {
				event.stopImmediatePropagation();
			}
		}
		if (desktopAtc) {
			desktopAtc.disabled = false;
			desktopAtc.removeAttribute('disabled');
			desktopAtc.classList.remove('disabled', 'wc-variation-selection-needed', 'wc-variation-is-unavailable');
		}
		if (isAdding) {
			return;
		}
		if (diag && typeof diag.log === 'function') {
			diag.log('bundle_add_to_cart_request', {
				path: window.location.pathname,
				source: event && event.type ? event.type : 'programmatic',
			});
		}
		syncChoicesFromDom();
		if (!isComplete()) {
			showValidationMessage();
			return;
		}
		var quantityMessage = quantityRuleMessage();
		if (quantityMessage) {
			var validation = document.getElementById('mbbb-validation');
			if (validation) {
				validation.hidden = false;
				validation.textContent = quantityMessage;
			}
			return;
		}
		clearValidationMessage();
		clearWooErrorNotices();
		resetLegacyVariationFields();
		addToCart();
	}

	function addToCart() {
		if (isAdding || !isComplete() || !form) {
			return;
		}

		var now = Date.now();
		if (now - lastTap < 600) {
			return;
		}
		lastTap = now;

		isAdding = true;
		if (stickyBtn) {
			stickyBtn.setAttribute('aria-busy', 'true');
		}
		setCtaState(isComplete(), true);
		updateProgress();

		var body = new FormData();
		body.append('action', 'mbbb_add_to_cart');
		body.append('nonce', cfg.nonce);
		body.append('product_id', String(cfg.productId));
		body.append('quantity', form.querySelector('[name="quantity"]') ? form.querySelector('[name="quantity"]').value : '1');

		var payload = getChoicesPayload();
		Object.keys(payload).forEach(function (key) {
			body.append('mbbb_choices[' + key + ']', payload[key]);
		});

		fetch(cfg.ajaxUrl, {
			method: 'POST',
			body: body,
			credentials: 'same-origin',
		})
			.then(function (res) {
				return res.json();
			})
			.then(function (json) {
				isAdding = false;
				if (stickyBtn) {
					stickyBtn.setAttribute('aria-busy', 'false');
				}
				updateProgress();

				if (!json || !json.success) {
					if (diag && typeof diag.log === 'function') {
						diag.log('bundle_add_to_cart_failed', {
							reason: json && json.data && json.data.message ? json.data.message : 'unknown',
						});
					}
					var msg = json && json.data && json.data.message ? json.data.message : cfg.i18n.errorGeneric;
					clearWooErrorNotices();
					showBundleStatusMessage(msg, 'is-incomplete');
					return;
				}
				clearValidationMessage();
				clearWooErrorNotices();

				var data = json.data || {};
				showSuccess(data.choices || {});

				document.dispatchEvent(new CustomEvent('mbbb:added_to_cart', { detail: data }));
				if (diag && typeof diag.log === 'function') {
					diag.log('bundle_add_to_cart_success', { productId: cfg.productId });
				}

				if (typeof window.jQuery !== 'undefined' && window.jQuery(document.body).trigger) {
					window.jQuery(document.body).trigger('wc_fragment_refresh');
				}
			})
			.catch(function () {
				isAdding = false;
				if (stickyBtn) {
					stickyBtn.setAttribute('aria-busy', 'false');
				}
				updateProgress();
				showBundleStatusMessage(cfg.i18n.errorGeneric, 'is-incomplete');
				if (diag && typeof diag.log === 'function') {
					diag.log('bundle_add_to_cart_error', { message: cfg.i18n.errorGeneric });
				}
			});
	}

	function shouldIgnoreDuplicateTap(event) {
		var now = Date.now();
		if (now - lastBuilderTap < 400) {
			return true;
		}
		lastBuilderTap = now;
		return false;
	}

	function shouldHandleOptionEvent(event) {
		if (coarsePointer) {
			return event.type === 'pointerup';
		}
		return event.type === 'click';
	}

	function handleFilterChipEvent(event) {
		if (!(event.target instanceof Element)) {
			return;
		}
		var chip = event.target.closest('.mbbb-filter-chip');
		if (!chip || !builder.contains(chip)) {
			return;
		}
		event.preventDefault();
		event.stopPropagation();
		activateFilterChip(chip);
	}

	function handleBuilderPointer(event) {
		if (!(event.target instanceof Element)) {
			return;
		}
		if ('pointerup' === event.type && event.pointerType === 'mouse' && 0 !== event.button) {
			return;
		}

		var btn = event.target.closest('.mbbb-option');
		if (btn) {
			if (!shouldHandleOptionEvent(event)) {
				return;
			}
			if (shouldIgnoreDuplicateTap(event) || isSelectionLocked()) {
				return;
			}
			event.preventDefault();
			event.stopPropagation();
			var slot = btn.getAttribute('data-slot');
			var value = btn.getAttribute('data-value');
			var label = btn.getAttribute('data-label');
			selectOption(slot, value, label);
			return;
		}

		var trigger = event.target.closest('.mbbb-slot__trigger');
		if (trigger) {
			var sectionEl = trigger.closest('.mbbb-slot');
			if (sectionEl) {
				var key = sectionEl.getAttribute('data-slot-key');
				var body = getSlotBody(key);
				var isOpen = sectionEl.classList.contains('is-active') && body && !body.hasAttribute('hidden');

				if (!isMobile()) {
					if (isOpen && key !== activeSlotKey) {
						sectionEl.classList.remove('is-active');
						sectionEl.classList.add('is-collapsed');
						body.setAttribute('hidden', '');
						trigger.setAttribute('aria-expanded', 'false');
						return;
					}
					if (isOpen && key === activeSlotKey) {
						return;
					}
					slotSections.forEach(function (section) {
						var sectionKey = section.getAttribute('data-slot-key');
						var isTarget = sectionKey === key;
						var slotBody = getSlotBody(sectionKey);
						var slotTrigger = getSlotTrigger(sectionKey);
						section.classList.toggle('is-active', isTarget);
						section.classList.toggle('is-collapsed', !isTarget);
						if (slotBody) {
							if (isTarget) {
								slotBody.removeAttribute('hidden');
							} else {
								slotBody.setAttribute('hidden', '');
							}
						}
						if (slotTrigger) {
							slotTrigger.setAttribute('aria-expanded', isTarget ? 'true' : 'false');
						}
					});
					activeSlotKey = key;
					return;
				}

				if (key === activeSlotKey) {
					return;
				}
				openSlot(key, true);
			}
		}
	}

	builder.addEventListener('click', handleBuilderPointer);
	builder.addEventListener('pointerup', handleBuilderPointer);
	builder.addEventListener('click', handleFilterChipEvent, true);

	builder.querySelectorAll('[data-slot-filter-clear]').forEach(function (btn) {
		btn.addEventListener('click', function (event) {
			event.preventDefault();
			event.stopPropagation();
			clearSlotFilter(btn.getAttribute('data-slot-filter-clear'));
		});
	});

	builder.querySelectorAll('[data-slot-next-choice]').forEach(function (btn) {
		btn.addEventListener('click', function (event) {
			event.preventDefault();
			event.stopPropagation();
			var slotKey = btn.getAttribute('data-slot-next-choice');
			if (!slotKey || isSelectionLocked()) {
				return;
			}
			advanceAfterSelection(slotKey);
		});
	});

	builder.addEventListener('input', function (event) {
		var search = event.target.closest('[data-slot-search]');
		if (search) {
			applySlotFilters(search.getAttribute('data-slot-search'));
		}
	});

	builder.addEventListener('change', function (event) {
		var sel = event.target.closest('.mbbb-select');
		if (!sel) {
			return;
		}
		var slot = sel.getAttribute('data-slot');
		var opt = sel.options[sel.selectedIndex];
		if (!sel.value) {
			setChoice(slot, '', '', false);
			return;
		}
		selectOption(slot, sel.value, opt ? opt.text : '');
	});

	function handleSummaryDrawerToggle(event) {
		if (event) {
			event.preventDefault();
			event.stopPropagation();
			if (event.target.closest('#mbbb-sticky-submit, #mbbb-summary-drawer-cta, .single_add_to_cart_button')) {
				return;
			}
		}
		if (summaryDrawer && summaryDrawer.classList.contains('is-open')) {
			closeSummaryDrawer();
		} else {
			openSummaryDrawer();
		}
	}

	if (form) {
		form.addEventListener('submit', requestAddToCart, true);
		if (!form.dataset.mbbbCartClickBound) {
			form.dataset.mbbbCartClickBound = '1';
			form.addEventListener(
				'click',
				function (event) {
					if (!(event.target instanceof Element)) {
						return;
					}
					var btn = event.target.closest('.single_add_to_cart_button');
					if (!btn || !builder) {
						return;
					}
					requestAddToCart(event);
				},
				true
			);
		}
	}

	refreshDesktopAtc();
	if (desktopAtc) {
		if (desktopAtc.getAttribute('type') === 'submit') {
			desktopAtc.setAttribute('type', 'button');
		}
		if (!desktopAtc.dataset.mbbbAtcBound) {
			desktopAtc.dataset.mbbbAtcBound = '1';
			desktopAtc.addEventListener('click', requestAddToCart);
		}
	}

	if (desktopAtc && isMobile()) {
		desktopAtc.classList.add('mbbb-hide-mobile-atc');
	}

	bindWooVariationAtcGuard();

	if (summaryToggle && summaryPanel) {
		summaryToggle.addEventListener('click', function () {
			var open = summaryToggle.getAttribute('aria-expanded') === 'true';
			summaryToggle.setAttribute('aria-expanded', open ? 'false' : 'true');
			summaryPanel.hidden = open;
		});
	}

	if (successRoot) {
		hideSuccess();
		successRoot.querySelectorAll('[data-mbbb-success-close]').forEach(function (el) {
			el.addEventListener('click', hideSuccess);
		});
		document.addEventListener('keydown', function (event) {
			if ('Escape' === event.key && !successRoot.hasAttribute('hidden')) {
				hideSuccess();
			}
		});
	}

	window.addEventListener('resize', function () {
		if (isMobile()) {
			document.body.classList.add('mbbb-product--mobile-builder', 'mbbb-mobile-app-active');
			document.body.classList.remove('mbbb-product--desktop');
		} else {
			document.body.classList.remove('mbbb-product--mobile-builder', 'mbbb-mobile-app-active');
			document.body.classList.add('mbbb-product--desktop');
		}
		if (document.body.classList.contains('is-mobile-app-shell')) {
			document.body.classList.add('mbbb-product--app-shell');
		} else {
			document.body.classList.remove('mbbb-product--app-shell');
		}
		applyMobileVisibility();
		if (!mobileStepFlow && isMobile()) {
			applyExpandedCardLayout();
		} else if (!isMobile()) {
			enforceDesktopAccordion(activeSlotKey || nextIncompleteRequiredSlot(''));
		}
		syncStickyLayout();
		organizeExpressCheckout();
	});

	initSearchClearButtons();
	document.querySelectorAll('[data-auto-select="1"]').forEach(function (section) {
		var key = section.getAttribute('data-slot-key');
		var buttons = section.querySelectorAll('.mbbb-option');
		if (!key || buttons.length !== 1 || (choices[key] && choices[key].value)) {
			return;
		}
		setChoice(key, buttons[0].getAttribute('data-value'), buttons[0].getAttribute('data-label'), false);
	});

	function quantityRuleMessage() {
		var rules = cfg.quantityRules;
		if (!rules) {
			return '';
		}
		var count = 0;
		Object.keys(choices).forEach(function (key) {
			if (choices[key] && choices[key].value) {
				count += 1;
			}
		});
		if ((rules.fixed || rules.mode === 'exact') && count !== rules.exact && !rules.fixed) {
			return 'Choose exactly ' + rules.exact + '.';
		}
		if (rules.fixed) {
			return '';
		}
		if (rules.mode === 'minimum' && rules.min && count < rules.min) {
			return 'Choose at least ' + rules.min + '.';
		}
		if (rules.max && count > rules.max && rules.mode === 'minimum') {
			return 'Choose no more than ' + rules.max + '.';
		}
		if (rules.multiple > 1 && count % rules.multiple !== 0) {
			return 'Choose a quantity in multiples of ' + rules.multiple + '.';
		}
		return '';
	}

	bindMobileChrome();
	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', bindMobileChrome);
	}

	var expressOrganizeScheduled = false;
	function scheduleOrganizeExpressCheckout() {
		if (expressOrganizeScheduled) {
			return;
		}
		expressOrganizeScheduled = true;
		window.requestAnimationFrame(function () {
			expressOrganizeScheduled = false;
			organizeExpressCheckout();
		});
	}

	scheduleOrganizeExpressCheckout();
	if (!iosPwaSafeMode && typeof MutationObserver !== 'undefined' && form) {
		expressObserver = new MutationObserver(function () {
			scheduleOrganizeExpressCheckout();
		});
		expressObserver.observe(form, { childList: true, subtree: true });
	}
	window.setTimeout(organizeExpressCheckout, 600);
	window.setTimeout(organizeExpressCheckout, 1800);

	clearWooErrorNotices();
	unlockVariationCartArea();
	syncChoicesFromDom();
	syncStickyLayout();
	bindMobileChrome();
	updateProgress(true);
	window.addEventListener(
		'pagehide',
		function () {
			if (atcButtonObserver) {
				atcButtonObserver.disconnect();
				atcButtonObserver = null;
			}
			if (expressObserver) {
				expressObserver.disconnect();
				expressObserver = null;
			}
		},
		{ once: true }
	);

	var initialActive = builder.querySelector('.mbbb-slot.is-active');
	if (initialActive) {
		activeSlotKey = initialActive.getAttribute('data-slot-key') || '';
	} else {
		activeSlotKey = nextIncompleteRequiredSlot('');
	}
	if (!isMobile()) {
		document.body.classList.add('mbbb-product--desktop');
	}

	if (!mobileStepFlow && isMobile()) {
		var refreshCardLayout = function () {
			applyExpandedCardLayout();
			document.dispatchEvent(new CustomEvent('mbbb:layout-refresh'));
		};
		refreshCardLayout();
		activeSlotKey = '';
		if (
			document.body.classList.contains('is-standalone-app') ||
			document.body.classList.contains('is-mobile-app-shell') ||
			document.documentElement.classList.contains('is-standalone-app')
		) {
			window.setTimeout(refreshCardLayout, 300);
			window.setTimeout(refreshCardLayout, 1200);
		}
	} else if (!isMobile() && activeSlotKey) {
		openSlot(activeSlotKey, false);
	} else if (activeSlotKey) {
		openSlot(activeSlotKey, false);
	}

	if (!isMobile()) {
		enforceDesktopAccordion(activeSlotKey || nextIncompleteRequiredSlot(''));
	}

	applyMobileVisibility();

	resetSlotFiltersAndSearch();

	slotSections.forEach(function (section) {
		var key = section.getAttribute('data-slot-key');
		var count = parseInt(section.getAttribute('data-option-count') || '0', 10);
		if (count >= searchThreshold || count >= filterThreshold || section.classList.contains('mbbb-slot--filterable')) {
			applySlotFilters(key);
		}
	});
	syncSessionFiltersFromChoices();
})();
