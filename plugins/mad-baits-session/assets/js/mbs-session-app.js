/**
 * Mad Baits Session — mobile SPA logbook
 * Vanilla JS, hash routing, offline queue.
 */
(function () {
	'use strict';

	var cfg = window.mbsSessionConfig || {};
	var root = document.querySelector('[data-mbs-app]');
	if (!root) {
		return;
	}

	var OFFLINE_KEY = 'mbs_offline_queue';
	var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/* ── State ─────────────────────────────────────────────── */

	var state = {
		access: cfg.access || 'guest',
		canUse: !!cfg.canUse,
		dashboard: null,
		sessions: [],
		venues: [],
		products: [],
		activeSession: null,
		currentSession: null,
		timerInterval: null,
		wizard: { step: 0, data: {} },
		drawer: null,
		online: navigator.onLine,
		syncPending: false,
		historyStack: [],
		skipHash: false,
		reportSubmitting: false
	};

	/* ── Utilities ─────────────────────────────────────────── */

	function esc(str) {
		if (str == null) {
			return '';
		}
		var d = document.createElement('div');
		d.textContent = String(str);
		return d.innerHTML;
	}

	function $(sel, ctx) {
		return (ctx || document).querySelector(sel);
	}

	function uuid() {
		return 'q_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
	}

	function haptic(ms) {
		if (!cfg.settings || !cfg.settings.haptics) {
			return;
		}
		if (navigator.vibrate) {
			navigator.vibrate(ms || 12);
		}
	}

	function toast(msg) {
		var el = $('.mbs-toast');
		if (!el) {
			el = document.createElement('div');
			el.className = 'mbs-toast';
			el.setAttribute('role', 'status');
			document.body.appendChild(el);
		}
		el.textContent = msg;
		el.classList.add('is-visible');
		clearTimeout(toast._t);
		toast._t = setTimeout(function () {
			el.classList.remove('is-visible');
		}, 2800);
	}

	function formatDuration(ms) {
		var s = Math.max(0, Math.floor(ms / 1000));
		var h = Math.floor(s / 3600);
		var m = Math.floor((s % 3600) / 60);
		var sec = s % 60;
		return [h, m, sec].map(function (n) {
			return n < 10 ? '0' + n : String(n);
		}).join(':');
	}

	function formatWeight(lb, oz) {
		lb = parseFloat(lb) || 0;
		oz = parseFloat(oz) || 0;
		if (oz > 0) {
			return lb + 'lb ' + oz + 'oz';
		}
		return lb + 'lb';
	}

	function formatDate(iso) {
		if (!iso) {
			return '—';
		}
		try {
			return new Date(iso).toLocaleDateString(undefined, {
				day: 'numeric',
				month: 'short',
				year: 'numeric'
			});
		} catch (e) {
			return iso;
		}
	}

	function formatTime(iso) {
		if (!iso) {
			return '';
		}
		try {
			return new Date(iso).toLocaleTimeString(undefined, {
				hour: '2-digit',
				minute: '2-digit'
			});
		} catch (e) {
			return iso;
		}
	}

	function weatherApiReady() {
		return cfg.settings && (cfg.settings.weatherApiReady || cfg.settings.weatherEnabled);
	}

	function latestWeatherSnapshot(data) {
		if (!data || !data.weather_snapshots || !data.weather_snapshots.length) {
			return null;
		}
		return data.weather_snapshots[data.weather_snapshots.length - 1];
	}

	function formatWeatherSnapshot(snap) {
		if (!snap) {
			return '';
		}
		var parts = [];
		if (snap.temperature != null && snap.temperature !== '') {
			parts.push(Math.round(snap.temperature) + '°C');
		}
		if (snap.feels_like != null && snap.feels_like !== '' && snap.feels_like !== snap.temperature) {
			parts.push('feels ' + Math.round(snap.feels_like) + '°C');
		}
		if (snap.wind_speed != null && snap.wind_speed !== '') {
			parts.push('wind ' + snap.wind_speed + ' m/s ' + (snap.wind_direction || ''));
		}
		if (snap.pressure != null && snap.pressure !== '') {
			parts.push(snap.pressure + ' hPa');
		}
		if (snap.cloud_cover != null && snap.cloud_cover !== '') {
			parts.push(snap.cloud_cover + '% cloud');
		}
		if (snap.rainfall != null && snap.rainfall > 0) {
			parts.push('rain ' + snap.rainfall + 'mm');
		}
		if (snap.description) {
			parts.push(snap.description);
		}
		if (snap.sunrise) {
			parts.push('↑ ' + formatSunTime(snap.sunrise));
		}
		if (snap.sunset) {
			parts.push('↓ ' + formatSunTime(snap.sunset));
		}
		return parts.join(' · ');
	}

	function formatSunTime(unix) {
		var d = new Date(parseInt(unix, 10) * 1000);
		if (isNaN(d.getTime())) {
			return '';
		}
		return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
	}

	function sessionWeatherDashboardHtml(snap) {
		if (!snap) {
			return weatherCardHtml(null, 'Refresh live weather or log conditions manually.');
		}
		var rows = '';
		if (snap.temperature != null) {
			rows += '<div class="mbs-dash-weather__stat"><span>Temp</span><strong>' + esc(Math.round(snap.temperature) + '°C') + '</strong></div>';
		}
		if (snap.wind_speed != null) {
			rows += '<div class="mbs-dash-weather__stat"><span>Wind</span><strong>' + esc((snap.wind_direction || '') + ' ' + snap.wind_speed + ' m/s') + '</strong></div>';
		}
		if (snap.pressure != null) {
			rows += '<div class="mbs-dash-weather__stat"><span>Pressure</span><strong>' + esc(snap.pressure + ' hPa') + '</strong></div>';
		}
		if (snap.sunrise) {
			rows += '<div class="mbs-dash-weather__stat"><span>Sunrise</span><strong>' + esc(formatSunTime(snap.sunrise)) + '</strong></div>';
		}
		if (snap.sunset) {
			rows += '<div class="mbs-dash-weather__stat"><span>Sunset</span><strong>' + esc(formatSunTime(snap.sunset)) + '</strong></div>';
		}
		return (
			'<div class="mbs-weather-card mbs-weather-card--dashboard">' +
			'<p class="mbs-weather-card__title">🌤 Live session weather</p>' +
			'<p class="mbs-weather-card__summary">' + esc(snap.description || formatWeatherSnapshot(snap)) + '</p>' +
			(rows ? '<div class="mbs-dash-weather__grid">' + rows + '</div>' : '') +
			'<button type="button" class="mbs-btn mbs-btn--small mbs-btn--ghost" data-action="quick-weather">Update weather</button>' +
			'</div>'
		);
	}

	function companionTipsHtml(companion) {
		if (!companion || !companion.weather_tips || !companion.weather_tips.length) {
			return '';
		}
		var items = companion.weather_tips.map(function (t) {
			return '<li>' + esc(t) + '</li>';
		}).join('');
		return (
			'<div class="mbs-card mbs-card--tips">' +
			'<p class="mbs-card__label">💡 Session tips</p>' +
			'<ul class="mbs-tips-list">' + items + '</ul>' +
			(companion.disclaimer ? '<p class="mbs-card__hint">' + esc(companion.disclaimer) + '</p>' : '') +
			'</div>'
		);
	}

	function biteWindowsHtml(companion) {
		if (!companion || !companion.bite_windows || !companion.bite_windows.length) {
			return '';
		}
		var cards = companion.bite_windows.map(function (w) {
			return (
				'<div class="mbs-bite-window">' +
				'<p class="mbs-bite-window__label">' + esc(w.label) + '</p>' +
				'<p class="mbs-bite-window__time">' + esc(w.time) + '</p>' +
				'<p class="mbs-card__hint">' + esc(w.hint) + '</p></div>'
			);
		}).join('');
		return '<div class="mbs-card"><p class="mbs-card__label">Suggested bite windows</p><div class="mbs-bite-windows">' + cards + '</div></div>';
	}

	function baitSummaryHtml(companion) {
		if (!companion || !companion.bait_totals) {
			return '<p class="mbs-card__hint">No bait logged yet</p>';
		}
		var bt = companion.bait_totals;
		if (!bt.lines || !bt.lines.length) {
			return '<p class="mbs-card__hint">No bait logged yet</p>';
		}
		var lines = bt.lines.slice(0, 4).map(function (row) {
			return '<li>' + esc(row.name) + (row.amount ? ' · ' + esc(row.amount) : '') + '</li>';
		}).join('');
		var total = bt.estimated_label ? '<p class="mbs-card__hint">Est. total: ' + esc(bt.estimated_label) + '</p>' : '';
		return '<ul class="mbs-timeline mbs-timeline--compact">' + lines + '</ul>' + total;
	}

	function sessionTimelineHtml(companion, limit) {
		limit = limit || 12;
		if (!companion || !companion.timeline || !companion.timeline.length) {
			return '<p class="mbs-card__hint">Session events will appear here</p>';
		}
		return '<ul class="mbs-timeline mbs-timeline--feed">' + companion.timeline.slice(0, limit).map(function (ev) {
			return (
				'<li class="mbs-timeline-feed__item mbs-timeline-feed__item--' + esc(ev.type) + '">' +
				'<time>' + esc(formatTime(ev.time)) + '</time>' +
				'<span class="mbs-timeline-feed__title">' + esc(ev.title) + '</span>' +
				(ev.detail ? '<span class="mbs-timeline-feed__detail">' + esc(ev.detail) + '</span>' : '') +
				'</li>'
			);
		}).join('') + '</ul>';
	}

	function quickLogBarHtml() {
		var acts = [
			{ type: 'liner', label: 'Liner' },
			{ type: 'lost_fish', label: 'Lost fish' },
			{ type: 'fish_showing', label: 'Showing' },
			{ type: 'recast', label: 'Recast' },
			{ type: 'baited_spot', label: 'Baited' },
			{ type: 'rig_changed', label: 'Rig' },
			{ type: 'moved_swim', label: 'Moved' },
			{ type: 'note', label: 'Note' }
		];
		return (
			'<div class="mbs-quick-log">' +
			'<p class="mbs-card__label">Quick log</p>' +
			'<div class="mbs-quick-log__grid">' +
			acts.map(function (a) {
				return '<button type="button" class="mbs-quick-log__btn" data-action="quick-activity" data-activity-type="' + esc(a.type) + '">' + esc(a.label) + '</button>';
			}).join('') +
			'</div></div>'
		);
	}

	function sessionDashActionsHtml() {
		return (
			'<div class="mbs-dash-actions">' +
			'<button type="button" class="mbs-dash-actions__btn mbs-dash-actions__btn--primary" data-action="log-catch"><span>🎣</span>Log Catch</button>' +
			'<button type="button" class="mbs-dash-actions__btn" data-action="add-photo"><span>📷</span>Photo</button>' +
			'<button type="button" class="mbs-dash-actions__btn" data-action="quick-bait"><span>🧪</span>Bait</button>' +
			'<button type="button" class="mbs-dash-actions__btn" data-action="add-note"><span>📝</span>Note</button>' +
			'<button type="button" class="mbs-dash-actions__btn" data-action="submit-catch-report"><span>📤</span>Report</button>' +
			'<button type="button" class="mbs-dash-actions__btn mbs-dash-actions__btn--danger" data-action="end-session"><span>⏹</span>End</button>' +
			'</div>'
		);
	}

	function endSummaryScreenHtml(session) {
		var sum = session.end_summary || {};
		var companion = session.companion || {};
		return (
			'<div class="mbs-card mbs-card--hero mbs-summary-card">' +
			'<p class="mbs-card__label">Session complete</p>' +
			'<p class="mbs-card__hint">' + esc(sum.venue || session.data.venue_name || '') +
			(sum.swim ? ' · ' + esc(sum.swim) : '') + '</p>' +
			'<div class="mbs-grid mbs-grid--2" style="margin-top:0.75rem">' +
			statCell(sum.duration || '—', 'Duration') +
			statCell(sum.catch_count != null ? sum.catch_count : '—', 'Catches') +
			statCell(sum.best_fish_lb ? sum.best_fish_lb + 'lb' : '—', 'Best fish') +
			statCell(sum.photos_count != null ? sum.photos_count : '—', 'Photos') +
			'</div>' +
			(sum.bait_total ? '<p class="mbs-card__hint">Bait used: ' + esc(sum.bait_total) + '</p>' : '') +
			(sum.best_bait ? '<p class="mbs-card__hint">Best bait: ' + esc(sum.best_bait) + '</p>' : '') +
			(sum.weather ? '<p class="mbs-card__hint">Weather: ' + esc(sum.weather) + '</p>' : '') +
			'</div>' +
			biteWindowsHtml(companion) +
			'<div class="mbs-card"><p class="mbs-card__label">Timeline</p>' + sessionTimelineHtml(companion, 10) + '</div>' +
			'<div style="display:flex;flex-direction:column;gap:0.5rem">' +
			'<button type="button" class="mbs-btn mbs-btn--primary mbs-btn--block" data-action="submit-catch-report">Submit Catch Report</button>' +
			'<button type="button" class="mbs-btn mbs-btn--ghost mbs-btn--block" data-action="share-summary">Share summary</button>' +
			'<button type="button" class="mbs-btn mbs-btn--ghost mbs-btn--block" data-action="repeat-session">Repeat Session</button>' +
			'<button type="button" class="mbs-btn mbs-btn--ghost mbs-btn--block" data-action="reorder-session">Shop / Reorder bait</button>' +
			'<a class="mbs-btn mbs-btn--ghost mbs-btn--block" href="' + esc(cfg.bundlesUrl || cfg.shopUrl || '#') + '">Build next session pack</a>' +
			'</div>'
		);
	}

	function weatherCardHtml(snap, emptyLabel) {
		var summary = formatWeatherSnapshot(snap);
		if (!summary) {
			return (
				'<div class="mbs-weather-card mbs-weather-card--empty">' +
				'<p class="mbs-weather-card__title">🌤 Bank weather</p>' +
				'<p class="mbs-card__hint">' + esc(emptyLabel || 'Log air conditions or fetch live weather.') + '</p>' +
				'</div>'
			);
		}
		return (
			'<div class="mbs-weather-card">' +
			'<p class="mbs-weather-card__title">🌤 Bank weather</p>' +
			'<p class="mbs-weather-card__summary">' + esc(summary) + '</p>' +
			(snap.captured_at ? '<p class="mbs-card__hint">Updated ' + esc(formatTime(snap.captured_at)) + '</p>' : '') +
			'</div>'
		);
	}

	function weatherManualFields(snap, prefix) {
		prefix = prefix || '';
		snap = snap || {};
		return (
			'<p class="mbs-weather-card__title" style="margin-top:0.75rem">Air conditions</p>' +
			'<label>Air temp (°C)</label><input type="number" step="0.1" name="' + prefix + 'temperature" value="' + esc(snap.temperature != null ? snap.temperature : '') + '">' +
			'<label>Feels like (°C)</label><input type="number" step="0.1" name="' + prefix + 'feels_like" value="' + esc(snap.feels_like != null ? snap.feels_like : '') + '">' +
			'<label>Barometric pressure (hPa)</label><input type="number" step="1" name="' + prefix + 'pressure" value="' + esc(snap.pressure != null ? snap.pressure : '') + '">' +
			'<label>Wind speed (m/s)</label><input type="number" step="0.1" name="' + prefix + 'wind_speed" value="' + esc(snap.wind_speed != null ? snap.wind_speed : '') + '">' +
			'<label>Wind direction</label>' + selectField(prefix + 'wind_direction', '', snap.wind_direction, ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW']) +
			'<label>Rainfall (mm)</label><input type="number" step="0.1" name="' + prefix + 'rainfall" value="' + esc(snap.rainfall != null ? snap.rainfall : '') + '">' +
			'<label>Cloud cover (%)</label><input type="number" min="0" max="100" name="' + prefix + 'cloud_cover" value="' + esc(snap.cloud_cover != null ? snap.cloud_cover : '') + '">' +
			'<label>Sky / notes</label><input type="text" name="' + prefix + 'description" value="' + esc(snap.description || '') + '" placeholder="Overcast, clearing…">'
		);
	}

	function collectManualWeather(form, prefix) {
		prefix = prefix || '';
		if (!form) {
			return null;
		}
		var snap = {
			source: 'manual',
			temperature: val(form, prefix + 'temperature'),
			feels_like: val(form, prefix + 'feels_like'),
			pressure: val(form, prefix + 'pressure'),
			wind_speed: val(form, prefix + 'wind_speed'),
			wind_direction: val(form, prefix + 'wind_direction'),
			rainfall: val(form, prefix + 'rainfall'),
			cloud_cover: val(form, prefix + 'cloud_cover'),
			description: val(form, prefix + 'description')
		};
		var has = snap.temperature || snap.pressure || snap.wind_speed || snap.description || snap.wind_direction;
		if (!has) {
			return null;
		}
		if (snap.temperature) {
			snap.temperature = parseFloat(snap.temperature);
		}
		if (snap.feels_like) {
			snap.feels_like = parseFloat(snap.feels_like);
		}
		if (snap.pressure) {
			snap.pressure = parseFloat(snap.pressure);
		}
		if (snap.wind_speed) {
			snap.wind_speed = parseFloat(snap.wind_speed);
		}
		if (snap.rainfall) {
			snap.rainfall = parseFloat(snap.rainfall);
		}
		if (snap.cloud_cover) {
			snap.cloud_cover = parseInt(snap.cloud_cover, 10);
		}
		return snap;
	}

	function fetchLiveWeather(opts) {
		opts = opts || {};
		var q = '';
		if (opts.lat != null && opts.lng != null) {
			q = 'weather?lat=' + encodeURIComponent(opts.lat) + '&lng=' + encodeURIComponent(opts.lng);
		} else if (opts.postcode) {
			q = 'weather?postcode=' + encodeURIComponent(opts.postcode);
		} else {
			return Promise.reject(new Error('Location required for live weather'));
		}
		if (!weatherApiReady()) {
			return Promise.reject(new Error('Live weather not configured — add API key in WooCommerce → Session'));
		}
		return apiGet(q);
	}

	function bitePredictorTeaserHtml(insight) {
		if (!insight || !insight.ready) {
			return (
				'<a class="mbs-card mbs-card--action mbs-bite-predictor" href="#insights/conditions" data-nav="insights/conditions">' +
				'<p class="mbs-card__label">🎯 Bite predictor</p>' +
				'<p class="mbs-card__hint">' + esc((insight && insight.message) || 'Log more sessions to unlock bite trends from your logs.') + '</p>' +
				'</a>'
			);
		}
		return (
			'<a class="mbs-card mbs-card--action mbs-bite-predictor mbs-bite-predictor--ready" href="#insights/conditions" data-nav="insights/conditions">' +
			'<p class="mbs-card__label">🎯 Bite predictor</p>' +
			'<p class="mbs-weather-card__summary">' +
			(insight.pressure_avg != null ? esc(String(insight.pressure_avg) + ' hPa avg · ') : '') +
			esc(insight.top_wind || '') + (insight.top_wind && insight.top_clarity ? ' · ' : '') +
			esc(insight.top_clarity || '') +
			'</p>' +
			'<p class="mbs-card__hint">Based on your logs — tap for full breakdown</p>' +
			'</a>'
		);
	}

	function cloneTemplate(id) {
		var tpl = document.getElementById(id);
		if (!tpl || !tpl.content) {
			return null;
		}
		return tpl.content.cloneNode(true);
	}

	function hideLoader() {
		var loader = document.querySelector('[data-mbs-loader]');
		if (loader) {
			loader.hidden = true;
			loader.style.display = 'none';
		}
		var privacy = document.querySelector('[data-mbs-privacy]');
		if (privacy) {
			privacy.hidden = false;
		}
	}

	function syncIndicator() {
		if (!state.canUse) {
			return '';
		}
		var cls = 'mbs-sync';
		var label = 'Synced';
		if (!state.online) {
			cls += ' mbs-sync--offline';
			label = 'Offline';
		} else if (state.syncPending || getOfflineQueue().length) {
			cls += ' mbs-sync--pending';
			label = 'Syncing…';
		}
		return '<div class="' + cls + '" data-mbs-sync aria-live="polite">' + esc(label) + '</div>';
	}

	/* ── Offline queue ─────────────────────────────────────── */

	function getOfflineQueue() {
		if (!cfg.settings || !cfg.settings.offlineDraft) {
			return [];
		}
		try {
			var raw = localStorage.getItem(OFFLINE_KEY);
			return raw ? JSON.parse(raw) : [];
		} catch (e) {
			return [];
		}
	}

	function saveOfflineQueue(queue) {
		try {
			localStorage.setItem(OFFLINE_KEY, JSON.stringify(queue));
		} catch (e) { /* quota */ }
		updateSyncUI();
	}

	function enqueueOffline(item) {
		var queue = getOfflineQueue();
		queue.push(item);
		saveOfflineQueue(queue);
		state.syncPending = true;
	}

	function updateSyncUI() {
		var el = document.querySelector('[data-mbs-sync]');
		if (!el) {
			return;
		}
		var pending = getOfflineQueue().length;
		el.className = 'mbs-sync';
		if (!state.online) {
			el.className += ' mbs-sync--offline';
			el.textContent = 'Offline';
		} else if (pending || state.syncPending) {
			el.className += ' mbs-sync--pending';
			el.textContent = pending ? 'Pending (' + pending + ')' : 'Syncing…';
		} else {
			el.textContent = 'Synced';
		}
	}

	function flushOfflineQueue() {
		if (!state.online || !cfg.settings || !cfg.settings.offlineDraft) {
			return Promise.resolve();
		}
		var queue = getOfflineQueue();
		if (!queue.length) {
			state.syncPending = false;
			updateSyncUI();
			return Promise.resolve();
		}
		state.syncPending = true;
		updateSyncUI();
		var remaining = [];
		var chain = Promise.resolve();
		queue.forEach(function (item) {
			chain = chain.then(function () {
				return apiRequest(item.method, item.path, item.body, true).catch(function () {
					remaining.push(item);
				});
			});
		});
		return chain.then(function () {
			saveOfflineQueue(remaining);
			state.syncPending = false;
			updateSyncUI();
			if (!remaining.length) {
				toast('Offline changes synced');
			}
		});
	}

	/* ── REST API ──────────────────────────────────────────── */

	function apiUrl(path) {
		var base = (cfg.restUrl || '').replace(/\/$/, '');
		return base + '/' + String(path).replace(/^\//, '');
	}

	function apiRequest(method, path, body, skipQueue) {
		var url = apiUrl(path);
		var opts = {
			method: method,
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': cfg.nonce || ''
			}
		};

		if (body && !(body instanceof FormData)) {
			opts.headers['Content-Type'] = 'application/json';
			opts.body = JSON.stringify(body);
		} else if (body instanceof FormData) {
			opts.body = body;
		}

		if (!state.online && !skipQueue && method !== 'GET' && cfg.settings && cfg.settings.offlineDraft) {
			enqueueOffline({ id: uuid(), method: method, path: path, body: body, ts: Date.now() });
			return Promise.resolve({ offline: true, queued: true });
		}

		return fetch(url, opts).then(function (res) {
			if (!res.ok) {
				return res.json().catch(function () {
					return { message: res.statusText };
				}).then(function (err) {
					var error = new Error(err.message || 'Request failed');
					if (err && err.code) {
						error.code = err.code;
					}
					throw error;
				});
			}
			if (res.status === 204) {
				return {};
			}
			return res.json();
		});
	}

	function apiGet(path) {
		return apiRequest('GET', path);
	}

	function apiPost(path, body) {
		return apiRequest('POST', path, body);
	}

	function apiPatch(path, body) {
		return apiRequest('PATCH', path, body);
	}

	function apiDelete(path) {
		return apiRequest('DELETE', path);
	}

	function uploadPhoto(file) {
		var fd = new FormData();
		fd.append('photo', file);
		return apiRequest('POST', 'upload/catch-photo', fd);
	}

	/* ── Router ────────────────────────────────────────────── */

	function parseRoute() {
		var hash = (location.hash || '#dashboard').replace(/^#/, '');
		var parts = hash.split('/').filter(Boolean);
		var route = parts[0] || 'dashboard';
		return { route: route, id: parts[1] ? parseInt(parts[1], 10) : null, sub: parts[1] || null };
	}

	function navigate(hash, replace) {
		state.skipHash = true;
		if (replace) {
			location.replace('#' + hash.replace(/^#/, ''));
		} else {
			location.hash = hash.replace(/^#/, '');
		}
		renderRoute();
	}

	function goBack() {
		if (state.drawer) {
			closeDrawer();
			return;
		}
		if (state.historyStack.length > 1) {
			state.historyStack.pop();
			var prev = state.historyStack[state.historyStack.length - 1] || 'dashboard';
			navigate(prev, true);
		} else {
			navigate('dashboard', true);
		}
	}

	function topbar(title, showBack) {
		showBack = showBack !== false;
		return (
			'<div class="mbs-topbar">' +
			(showBack ? '<button type="button" class="mbs-topbar__back" data-action="back" aria-label="Back">←</button>' : '') +
			'<h2 class="mbs-topbar__title">' + esc(title) + '</h2>' +
			'</div>'
		);
	}

	/* ── Drawer ────────────────────────────────────────────── */

	function openDrawer(title, html, onSubmit, opts) {
		opts = opts || {};
		closeDrawer();
		var drawer = document.createElement('div');
		var btnLabel = opts.submitLabel || (cfg.i18n && cfg.i18n.save ? cfg.i18n.save : 'Save');
		drawer.className = 'mbs-drawer' + (reducedMotion ? '' : '');
		drawer.innerHTML =
			'<div class="mbs-drawer__backdrop" data-action="close-drawer"></div>' +
			'<div class="mbs-drawer__panel" role="dialog" aria-label="' + esc(title) + '">' +
			'<div class="mbs-topbar"><h2 class="mbs-topbar__title">' + esc(title) + '</h2></div>' +
			html +
			(onSubmit ? '<button type="button" class="mbs-btn mbs-btn--primary mbs-btn--block" data-action="drawer-submit">' + esc(btnLabel) + '</button>' : '') +
			'</div>';
		document.body.appendChild(drawer);
		state.drawer = { el: drawer, onSubmit: onSubmit, formType: opts.formType || '' };
		requestAnimationFrame(function () {
			drawer.classList.add('is-open');
			var photoField = drawer.querySelector('[data-catch-photo-field]');
			if (photoField) {
				bindCatchReportPhotoInput(photoField);
			}
		});
		haptic(8);
	}

	function setDrawerSubmitState(isSubmitting) {
		if (!state.drawer || !state.drawer.el) {
			return;
		}
		var btn = state.drawer.el.querySelector('[data-action="drawer-submit"]');
		if (!btn) {
			return;
		}
		if (!btn.dataset.defaultLabel) {
			btn.dataset.defaultLabel = btn.textContent || (cfg.i18n && cfg.i18n.save ? cfg.i18n.save : 'Save');
		}
		btn.disabled = !!isSubmitting;
		btn.textContent = isSubmitting
			? (cfg.i18n && cfg.i18n.saving ? cfg.i18n.saving : 'Saving…')
			: btn.dataset.defaultLabel;
	}

	function closeDrawer() {
		if (!state.drawer) {
			return;
		}
		var el = state.drawer.el;
		el.classList.remove('is-open');
		setTimeout(function () {
			if (el.parentNode) {
				el.parentNode.removeChild(el);
			}
		}, reducedMotion ? 0 : 280);
		state.drawer = null;
	}

	/* ── Locked screens ────────────────────────────────────── */

	function renderLocked(tplId) {
		root.innerHTML = '';
		root.classList.remove('mbs-session-app--full');
		var frag = cloneTemplate(tplId);
		if (frag) {
			root.appendChild(frag);
			if (tplId === 'mbs-tpl-guest-locked') {
				var loginHref = cfg.loginUrl || cfg.sessionUrl || '/session/';
				var registerHref = cfg.registerUrl || loginHref;
				var primary = root.querySelector('.mbs-btn--primary');
				var ghost = root.querySelector('.mbs-btn--ghost');
				if (primary) {
					primary.setAttribute('href', loginHref);
				}
				if (ghost) {
					ghost.setAttribute('href', registerHref);
				}
			}
		}
		hideLoader();
	}

	/* ── Dashboard ─────────────────────────────────────────── */

	function renderDashboard() {
		var stats = (state.dashboard && state.dashboard.stats) || {};
		var reminders = (state.dashboard && state.dashboard.reminders) || [];
		var active = stats.active_session;

		var reminderHtml = reminders.slice(0, 3).map(function (r) {
			return '<div class="mbs-reminder">' + esc(r.message || r.title || r) + '</div>';
		}).join('');

		var cards = [
			{ hash: 'start', icon: '🎣', label: 'Start Session', hint: 'New log on the bank' },
			active ? { hash: 'active/' + active.id, icon: '⏱', label: 'Active Session', hint: active.venue_name || 'In progress' } : null,
			{ hash: 'logbook', icon: '📖', label: 'Logbook', hint: (stats.total_sessions || 0) + ' sessions' },
			{ hash: 'insights/conditions', icon: '🎯', label: 'Bite Predictor', hint: 'Conditions from your logs' },
			{ hash: 'insights/bait', icon: '📊', label: 'Bait Performance', hint: stats.most_used_bait || 'Your trends' },
			{ hash: 'venues', icon: '📍', label: 'Venues', hint: stats.favourite_venue || 'Saved spots' },
			{ hash: 'gallery', icon: '📷', label: 'Catch Gallery', hint: (stats.pb_count || 0) + ' PBs' }
		].filter(Boolean);

		root.innerHTML =
			'<div class="mbs-screen mbs-session-app--full">' +
			syncIndicator() +
			'<header class="mbs-header">' +
			'<h1 class="mbs-header__title">Session</h1>' +
			'<p class="mbs-header__sub">Your private fishing logbook</p>' +
			'</header>' +
			reminderHtml +
			'<div class="mbs-grid mbs-grid--2" style="margin-bottom:1rem">' +
			statCell(stats.total_sessions, 'Sessions') +
			statCell(stats.total_catches, 'Catches') +
			statCell(stats.best_fish_lb ? stats.best_fish_lb + 'lb' : '—', 'Best Fish') +
			statCell(stats.pb_count, 'PBs') +
			'</div>' +
			(active ? (
				'<a class="mbs-card mbs-card--hero mbs-card--action" href="#active/' + active.id + '" data-nav="active/' + active.id + '">' +
				'<p class="mbs-card__label">Active now</p>' +
				'<p class="mbs-card__hint">' + esc(active.venue_name || active.title) + '</p>' +
				'</a>'
			) : '') +
			'<div class="mbs-grid mbs-grid--2">' +
			cards.map(cardLink).join('') +
			'</div>' +
			'<div data-bite-predictor-slot></div>' +
			(cfg.teamReports ? '<a class="mbs-card mbs-card--action" href="#team" data-nav="team" style="margin-top:0.65rem;display:block"><p class="mbs-card__label">Team Reports</p><p class="mbs-card__hint">Submit field reports</p></a>' : '') +
			'</div>';

		root.classList.add('mbs-session-app--full');

		apiGet('insights/conditions').then(function (insight) {
			var slot = root.querySelector('[data-bite-predictor-slot]');
			if (slot) {
				slot.outerHTML = bitePredictorTeaserHtml(insight);
			}
		}).catch(function () {});
	}

	function statCell(val, label) {
		return '<div class="mbs-card mbs-stat"><span class="mbs-stat__value">' + esc(val != null ? val : '—') + '</span><span class="mbs-stat__label">' + esc(label) + '</span></div>';
	}

	function cardLink(c) {
		return (
			'<a class="mbs-card mbs-card--action" href="#' + c.hash + '" data-nav="' + c.hash + '">' +
			'<div class="mbs-card__icon" aria-hidden="true">' + c.icon + '</div>' +
			'<p class="mbs-card__label">' + esc(c.label) + '</p>' +
			'<p class="mbs-card__hint">' + esc(c.hint) + '</p>' +
			'</a>'
		);
	}

	/* ── Start wizard ──────────────────────────────────────── */

	var WIZARD_STEPS = ['Details', 'Location', 'Conditions', 'Bait', 'Tactics', 'Confirm'];

	function renderStartWizard() {
		var step = state.wizard.step;
		var d = state.wizard.data;

		var dots = WIZARD_STEPS.map(function (_, i) {
			var cls = 'mbs-steps__dot';
			if (i < step) {
				cls += ' is-done';
			}
			if (i === step) {
				cls += ' is-active';
			}
			return '<div class="' + cls + '"></div>';
		}).join('');

		var body = '';
		if (step === 0) {
			body =
				'<div class="mbs-form">' +
				'<label>Session title</label><input type="text" name="title" value="' + esc(d.title || 'Fishing Session') + '" placeholder="Fishing Session">' +
				'<label>Planned end (optional)</label><input type="datetime-local" name="planned_end_at" value="' + esc(d.planned_end_at || '') + '">' +
				'</div>';
		} else if (step === 1) {
			var venueOpts = state.venues.map(function (v) {
				return '<option value="' + v.id + '"' + (d.venue_id === v.id ? ' selected' : '') + '>' + esc(v.title) + '</option>';
			}).join('');
			body =
				'<div class="mbs-form">' +
				'<label>Saved venue</label><select name="venue_id"><option value="">— New venue —</option>' + venueOpts + '</select>' +
				'<label>Venue name</label><input type="text" name="venue_name" value="' + esc(d.venue_name || '') + '">' +
				'<label>Lake / swim</label><input type="text" name="lake_swim" value="' + esc(d.lake_swim || '') + '">' +
				'<label>Location</label><input type="text" name="venue_location" value="' + esc(d.venue_location || '') + '">' +
				'<label>Postcode</label><input type="text" name="postcode" value="' + esc(d.postcode || '') + '">' +
				'<button type="button" class="mbs-btn mbs-btn--ghost mbs-btn--block" data-action="geo">Use my location</button>' +
				'</div>';
		} else if (step === 2) {
			var wSnap = d.weather || (d.weather_snapshots && d.weather_snapshots[0]) || null;
			var liveBtn = weatherApiReady()
				? '<button type="button" class="mbs-btn mbs-btn--ghost mbs-btn--block" data-action="fetch-weather-wizard">Get live weather</button>'
				: '<p class="mbs-card__hint">Live weather: enable API under WooCommerce → Session (manual fields below always work).</p>';
			body =
				weatherCardHtml(wSnap, 'Set air conditions for this session.') +
				'<div class="mbs-form" data-wizard-conditions>' +
				'<p class="mbs-weather-card__title">Water & lake</p>' +
				selectField('water_clarity', 'Water clarity', d.conditions && d.conditions.water_clarity, ['Clear', 'Slightly coloured', 'Coloured', 'Murky']) +
				selectField('water_temp', 'Water temp', d.conditions && d.conditions.water_temp, ['Cold', 'Cool', 'Warm', 'Hot']) +
				selectField('weed_level', 'Weed', d.conditions && d.conditions.weed_level, ['None', 'Light', 'Moderate', 'Heavy']) +
				selectField('fishing_pressure', 'Angling pressure', d.conditions && d.conditions.fishing_pressure, ['Low', 'Medium', 'High']) +
				selectField('baiting_level', 'Baiting level', d.conditions && d.conditions.baiting_level, ['Low', 'Medium', 'Heavy']) +
				selectField('lake_activity', 'Lake activity', d.conditions && d.conditions.lake_activity, ['Quiet', 'Moderate', 'Busy']) +
				weatherManualFields(wSnap) +
				liveBtn +
				'<label>Water notes</label><textarea name="cond_notes">' + esc((d.conditions && d.conditions.notes) || '') + '</textarea>' +
				'</div>';
		} else if (step === 3) {
			var prodList = state.products.length
				? state.products.map(function (p) {
					var checked = (d.bait_used || []).some(function (b) {
						return b.product_id === p.product_id;
					}) ? ' checked' : '';
					return (
						'<label style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;text-transform:none;letter-spacing:0">' +
						'<input type="checkbox" name="product" value="' + p.product_id + '" data-name="' + esc(p.name) + '" data-range="' + esc(p.range || '') + '"' + checked + '>' +
						esc(p.name) + '</label>'
					);
				}).join('')
				: '<p class="mbs-empty">No order products found — add bait manually on the bank.</p>';
			body = '<div class="mbs-form">' + prodList + '</div>';
		} else if (step === 4) {
			body =
				'<div class="mbs-form">' +
				'<label>Rig plan</label><textarea name="rig_plan">' + esc(d.rig_plan || '') + '</textarea>' +
				'<label>Baiting tactics</label><textarea name="baiting_plan">' + esc(d.baiting_plan || '') + '</textarea>' +
				'<label>Session notes</label><textarea name="notes">' + esc(d.notes || '') + '</textarea>' +
				'</div>';
		} else {
			body =
				'<div class="mbs-card">' +
				'<p><strong>' + esc(d.title || 'Fishing Session') + '</strong></p>' +
				'<p class="mbs-card__hint">' + esc(d.venue_name || 'Venue TBC') + (d.lake_swim ? ' · ' + esc(d.lake_swim) : '') + '</p>' +
				'<p class="mbs-card__hint">Baits: ' + esc((d.bait_used || []).map(function (b) { return b.name; }).join(', ') || 'None selected') + '</p>' +
				'</div>';
		}

		root.innerHTML =
			'<div class="mbs-screen">' +
			topbar('Start Session') +
			'<div class="mbs-steps">' + dots + '</div>' +
			'<p class="mbs-header__sub" style="margin-bottom:1rem">' + esc(WIZARD_STEPS[step]) + '</p>' +
			body +
			'<div style="display:flex;gap:0.5rem;margin-top:1rem">' +
			(step > 0 ? '<button type="button" class="mbs-btn mbs-btn--ghost" data-action="wizard-prev">Back</button>' : '') +
			'<button type="button" class="mbs-btn mbs-btn--primary" style="flex:1" data-action="wizard-next">' +
			(step < WIZARD_STEPS.length - 1 ? 'Continue' : 'Start Session') +
			'</button></div></div>';
	}

	function selectField(name, label, val, options) {
		var opts = options.map(function (o) {
			return '<option value="' + esc(o) + '"' + (val === o ? ' selected' : '') + '>' + esc(o) + '</option>';
		}).join('');
		return '<label>' + esc(label) + '</label><select name="' + name + '"><option value="">—</option>' + opts + '</select>';
	}

	function collectWizardStep() {
		var d = state.wizard.data;
		var step = state.wizard.step;
		var form = root.querySelector('.mbs-form');
		if (!form) {
			return;
		}
		if (step === 0) {
			d.title = val(form, 'title') || 'Fishing Session';
			d.planned_end_at = val(form, 'planned_end_at');
		} else if (step === 1) {
			var vid = val(form, 'venue_id');
			d.venue_id = vid ? parseInt(vid, 10) : 0;
			d.venue_name = val(form, 'venue_name');
			d.lake_swim = val(form, 'lake_swim');
			d.venue_location = val(form, 'venue_location');
			d.postcode = val(form, 'postcode');
		} else if (step === 2) {
			d.conditions = {
				water_clarity: val(form, 'water_clarity'),
				water_temp: val(form, 'water_temp'),
				weed_level: val(form, 'weed_level'),
				fishing_pressure: val(form, 'fishing_pressure'),
				baiting_level: val(form, 'baiting_level'),
				lake_activity: val(form, 'lake_activity'),
				notes: val(form, 'cond_notes')
			};
			var manualW = collectManualWeather(form, '');
			if (manualW) {
				d.weather = manualW;
			}
		} else if (step === 3) {
			d.bait_used = [];
			form.querySelectorAll('input[name="product"]:checked').forEach(function (cb) {
				d.bait_used.push({
					product_id: parseInt(cb.value, 10),
					name: cb.getAttribute('data-name') || '',
					range: cb.getAttribute('data-range') || ''
				});
			});
		} else if (step === 4) {
			d.rig_plan = val(form, 'rig_plan');
			d.baiting_plan = val(form, 'baiting_plan');
			d.notes = val(form, 'notes');
			d.tactics = [{ notes: d.rig_plan, baiting: d.baiting_plan }];
		}
	}

	function val(form, name) {
		var el = form.querySelector('[name="' + name + '"]');
		return el ? el.value.trim() : '';
	}

	function submitWizard() {
		var d = state.wizard.data;
		var payload = {
			title: d.title || 'Fishing Session',
			data: {
				status: 'active',
				start_at: new Date().toISOString(),
				venue_id: d.venue_id || 0,
				venue_name: d.venue_name || '',
				lake_swim: d.lake_swim || '',
				venue_location: d.venue_location || '',
				postcode: d.postcode || '',
				planned_end_at: d.planned_end_at || '',
				conditions: d.conditions || {},
				bait_used: d.bait_used || [],
				tactics: d.tactics || [],
				notes: d.notes || '',
				location_lat: d.location_lat || null,
				location_lng: d.location_lng || null,
				weather_snapshots: d.weather ? [d.weather] : []
			}
		};

		toast(cfg.i18n && cfg.i18n.saving ? cfg.i18n.saving : 'Saving…');
		apiPost('sessions', payload).then(function (res) {
			if (res.offline) {
				toast('Saved offline — will sync when online');
				navigate('dashboard');
				return;
			}
			haptic(20);
			toast(cfg.i18n && cfg.i18n.sessionSaved ? cfg.i18n.sessionSaved : 'Session started');
			state.wizard = { step: 0, data: {} };
			if (res.id) {
				navigate('active/' + res.id);
			} else {
				loadDashboard().then(renderDashboard);
			}
		}).catch(function (err) {
			toast(err.message || 'Could not start session');
		});
	}

	/* ── Active session ────────────────────────────────────── */

	function stopTimer() {
		if (state.timerInterval) {
			clearInterval(state.timerInterval);
			state.timerInterval = null;
		}
	}

	function startTimer(startAt, el) {
		stopTimer();
		if (!startAt || !el) {
			return;
		}
		function tick() {
			var ms = Date.now() - new Date(startAt).getTime();
			el.textContent = formatDuration(ms);
		}
		tick();
		state.timerInterval = setInterval(tick, 1000);
	}

	function renderActiveSession(id) {
		loadSession(id).then(function (session) {
			if (!session || !session.data) {
				root.innerHTML = '<div class="mbs-empty"><p>Session not found</p><button class="mbs-btn mbs-btn--primary" data-action="back">Back</button></div>';
				return;
			}
			state.currentSession = session;
			var data = session.data;
			var companion = session.companion || {};
			var counts = companion.session_counts || {};
			var catches = data.catches || [];
			var snap = latestWeatherSnapshot(data);

			var catchRows = catches.slice().reverse().slice(0, 6).map(function (c) {
				return catchTimelineItemHtml(c, id);
			}).join('');

			root.innerHTML =
				'<div class="mbs-screen mbs-screen--dashboard" data-session-id="' + id + '">' +
				topbar(data.venue_name || session.title || 'Live Session') +
				syncIndicator() +
				'<div class="mbs-card mbs-card--hero mbs-dash-hero">' +
				'<p class="mbs-card__label">' + esc(data.venue_name || session.title) + '</p>' +
				'<div class="mbs-timer" data-mbs-timer>00:00:00</div>' +
				'<p class="mbs-card__hint">' + esc(data.lake_swim || data.venue_location || '') + '</p>' +
				'<div class="mbs-grid mbs-grid--2 mbs-dash-stats">' +
				statCell(counts.catches != null ? counts.catches : catches.length, 'Catches') +
				statCell(counts.photos || 0, 'Photos') +
				statCell(counts.bait_entries || 0, 'Bait logs') +
				statCell((session.stats && session.stats.best_fish_lb) ? session.stats.best_fish_lb + 'lb' : '—', 'Best') +
				'</div></div>' +
				sessionDashActionsHtml() +
				sessionWeatherDashboardHtml(snap) +
				companionTipsHtml(companion) +
				biteWindowsHtml(companion) +
				'<div class="mbs-card"><p class="mbs-card__label">Bait used this session</p>' + baitSummaryHtml(companion) +
				'<button type="button" class="mbs-btn mbs-btn--small mbs-btn--ghost" data-action="quick-bait">Add bait</button></div>' +
				quickLogBarHtml() +
				'<div class="mbs-segment">' +
				quickBtn('baiting', 'Baiting') +
				quickBtn('rig', 'Rig') +
				quickBtn('conditions', 'Conditions') +
				quickBtn('weather', 'Weather') +
				'</div>' +
				'<div class="mbs-card"><p class="mbs-card__label">Recent catches</p>' +
				(catchRows ? '<ul class="mbs-timeline">' + catchRows + '</ul>' : '<p class="mbs-card__hint">No catches yet — log your first fish.</p>') +
				'</div>' +
				'<div class="mbs-card"><p class="mbs-card__label">Session timeline</p>' +
				sessionTimelineHtml(companion, 10) +
				'</div></div>';

			startTimer(data.start_at, $('[data-mbs-timer]', root));
		});
	}

	function quickBtn(action, label) {
		return '<button type="button" data-action="quick-' + action + '">' + esc(label) + '</button>';
	}

	/* ── Session report ───────────────────────────────────── */

	function renderSessionReport(id) {
		loadSession(id).then(function (session) {
			if (!session) {
				root.innerHTML = '<div class="mbs-empty"><p>Session not found</p></div>';
				return;
			}
			var data = session.data || {};
			var stats = session.stats || {};
			var catches = data.catches || [];

			var catchList = catches.map(function (c, i) {
				return (
					'<li class="mbs-timeline__item">' +
					'<time>' + esc(formatTime(c.catch_time)) + '</time> ' +
					esc(formatWeight(c.weight_lb, c.weight_oz)) + ' ' + esc(c.species || '') +
					(c.is_pb ? ' <strong>PB</strong>' : '') +
					catchReportStatusHtml(c) +
					(!catchAlreadyReported(c) ? ' <button type="button" class="mbs-btn mbs-btn--small mbs-btn--ghost" data-action="submit-catch-report" data-catch-id="' + esc(c.id || '') + '">Report</button>' : '') +
					(c.photo_id ? ' <button type="button" class="mbs-btn mbs-btn--small mbs-btn--ghost" data-action="share-catch" data-index="' + i + '">Share</button>' : '') +
					'</li>'
				);
			}).join('');

			var summaryBlock = (data.status === 'ended' || data.end_at) ? endSummaryScreenHtml(session) : '';

			root.innerHTML =
				'<div class="mbs-screen" data-session-id="' + id + '">' +
				topbar(session.title || 'Session Report') +
				summaryBlock +
				weatherCardHtml(latestWeatherSnapshot(data)) +
				'<div class="mbs-card">' +
				'<p class="mbs-card__label">' + esc(data.venue_name || '') + '</p>' +
				'<p class="mbs-card__hint">' + esc(formatDate(data.start_at)) +
				(data.end_at ? ' → ' + esc(formatDate(data.end_at)) : '') + '</p>' +
				'<div class="mbs-grid mbs-grid--2" style="margin-top:0.75rem">' +
				statCell(stats.catch_count, 'Catches') +
				statCell(stats.best_fish_lb ? stats.best_fish_lb + 'lb' : '—', 'Best') +
				'</div></div>' +
				(data.lessons ? '<div class="mbs-card"><p class="mbs-card__label">Lessons</p><p>' + esc(data.lessons) + '</p></div>' : '') +
				'<div class="mbs-card"><p class="mbs-card__label">Session timeline</p>' + sessionTimelineHtml(session.companion, 20) + '</div>' +
				'<div class="mbs-card"><p class="mbs-card__label">Catches</p>' +
				(catchList ? '<ul class="mbs-timeline">' + catchList + '</ul>' : '<p class="mbs-card__hint">None logged</p>') +
				'</div>' +
				(!summaryBlock ? '<div style="display:flex;flex-direction:column;gap:0.5rem;margin-top:0.5rem">' +
				'<button type="button" class="mbs-btn mbs-btn--primary mbs-btn--block" data-action="submit-catch-report">Submit Catch Report</button>' +
				'<button type="button" class="mbs-btn mbs-btn--ghost mbs-btn--block" data-action="repeat-session">Repeat setup</button>' +
				'<button type="button" class="mbs-btn mbs-btn--ghost mbs-btn--block" data-action="reorder-session">Shop / Reorder bait</button>' +
				(cfg.settings && cfg.settings.pdfExport ? '<button type="button" class="mbs-btn mbs-btn--ghost mbs-btn--block" data-action="print-report">Export / Print</button>' : '') +
				'<button type="button" class="mbs-btn mbs-btn--ghost mbs-btn--block" data-action="delete-session" style="color:#f87171">Delete session</button>' +
				'</div>' : '') +
				'</div>' +
				printReportHtml(session);
		});
	}

	function printReportHtml(session) {
		var data = session.data || {};
		var catches = data.catches || [];
		var rows = catches.map(function (c) {
			return '<tr><td>' + esc(formatTime(c.catch_time)) + '</td><td>' + esc(c.species) + '</td><td>' + esc(formatWeight(c.weight_lb, c.weight_oz)) + '</td><td>' + esc(c.bait_used) + '</td></tr>';
		}).join('');
		return (
			'<div class="mbs-print-report" id="mbs-print-report">' +
			'<h1>' + esc(session.title) + '</h1>' +
			'<p>' + esc(data.venue_name) + ' · ' + esc(formatDate(data.start_at)) + '</p>' +
			'<table border="1" cellpadding="6" style="width:100%;border-collapse:collapse"><thead><tr><th>Time</th><th>Species</th><th>Weight</th><th>Bait</th></tr></thead><tbody>' +
			rows + '</tbody></table>' +
			(data.lessons ? '<p><strong>Lessons:</strong> ' + esc(data.lessons) + '</p>' : '') +
			'<p style="margin-top:2rem;font-size:0.85rem">Mad Baits Session — madbaits.co.uk</p></div>'
		);
	}

	/* ── End session flow ──────────────────────────────────── */

	function renderEndSession(id) {
		loadSession(id).then(function (session) {
			if (!session) {
				return;
			}
			state.currentSession = session;
			root.innerHTML =
				'<div class="mbs-screen" data-session-id="' + id + '">' +
				topbar('End Session') +
				'<div class="mbs-form">' +
				'<label>How did it go?</label>' +
				selectField('result', 'Result', '', ['Blank', 'Slow', 'Steady', 'Good', 'Epic']) +
				'<label>What worked?</label><textarea name="what_worked"></textarea>' +
				'<label>What would you change?</label><textarea name="what_change"></textarea>' +
				'<label>Same bait again?</label><select name="same_bait_again"><option value="">—</option><option value="yes">Yes</option><option value="no">No</option><option value="maybe">Maybe</option></select>' +
				'<label>Bait confidence (1–5)</label><input type="number" name="bait_confidence" min="1" max="5" value="3">' +
				'<label>Lessons learned</label><textarea name="lessons"></textarea>' +
				'</div>' +
				'<button type="button" class="mbs-btn mbs-btn--primary mbs-btn--block" data-action="submit-end">Finish Session</button>' +
				'</div>';
		});
	}

	/* ── Logbook ───────────────────────────────────────────── */

	function renderLogbook() {
		loadSessions().then(function () {
			var list = state.sessions.length
				? state.sessions.map(function (s) {
					return (
						'<a class="mbs-card mbs-card--action" href="#session/' + s.id + '" data-nav="session/' + s.id + '">' +
						'<p class="mbs-card__label">' + esc(s.venue_name || s.title) + '</p>' +
						'<p class="mbs-card__hint">' + esc(formatDate(s.start_at)) + ' · ' + s.catch_count + ' catches' +
						(s.status === 'active' ? ' · Active' : '') + '</p></a>'
					);
				}).join('')
				: '<div class="mbs-empty"><p>No sessions yet</p><button class="mbs-btn mbs-btn--primary mbs-empty__cta" data-nav="start">Start your first session</button></div>';

			root.innerHTML = '<div class="mbs-screen">' + topbar('Logbook') + list + '</div>';
		});
	}

	/* ── Insights ──────────────────────────────────────────── */

	function renderInsightsBait() {
		apiGet('insights/bait').then(function (res) {
			var rows = Object.keys(res.by_range || {}).map(function (key) {
				var row = res.by_range[key];
				return '<div class="mbs-card"><p class="mbs-card__label">' + esc(key) + '</p><p class="mbs-card__hint">' + row.sessions + ' sessions · ' + row.catches + ' with catches</p></div>';
			}).join('') || '<div class="mbs-empty"><p>No bait data yet</p></div>';

			root.innerHTML =
				'<div class="mbs-screen">' + topbar('Bait Performance') +
				(res.disclaimer ? '<p class="mbs-header__sub">' + esc(res.disclaimer) + '</p>' : '') +
				rows + '</div>';
		});
	}

	function renderInsightsConditions() {
		apiGet('insights/conditions').then(function (res) {
			var body;
			if (!res.ready) {
				body = '<div class="mbs-empty"><p>' + esc(res.message || 'Not enough data') + '</p></div>';
			} else {
				body =
					'<p class="mbs-header__sub">Conditions that produced bites in your logbook.</p>' +
					'<div class="mbs-card"><p class="mbs-card__label">Avg pressure</p><p class="mbs-stat__value">' + esc(res.pressure_avg != null ? res.pressure_avg : '—') + '</p></div>' +
					'<div class="mbs-card"><p class="mbs-card__label">Top wind</p><p>' + esc(res.top_wind || '—') + '</p></div>' +
					'<div class="mbs-card"><p class="mbs-card__label">Top clarity</p><p>' + esc(res.top_clarity || '—') + '</p></div>' +
					(res.disclaimer ? '<p class="mbs-header__sub">' + esc(res.disclaimer) + '</p>' : '');
			}
			root.innerHTML = '<div class="mbs-screen">' + topbar('Bite Predictor') + body + '</div>';
		});
	}

	function renderGallery() {
		apiGet('insights/gallery').then(function (res) {
			var items = res.items || [];
			var grid = items.length
				? '<div class="mbs-gallery">' + items.map(function (item) {
					return '<img src="' + esc(item.photo_url) + '" alt="' + esc(formatWeight(item.weight_lb, item.weight_oz)) + '" loading="lazy" data-gallery-item="' + esc(JSON.stringify({ url: item.photo_url, weight: formatWeight(item.weight_lb, item.weight_oz), venue: item.venue_name })) + '">';
				}).join('') + '</div>'
				: '<div class="mbs-empty"><p>No catch photos yet</p></div>';

			root.innerHTML = '<div class="mbs-screen">' + topbar('Catch Gallery') + grid + '</div>';
		});
	}

	/* ── Venues ────────────────────────────────────────────── */

	function renderVenues() {
		loadVenues().then(function () {
			var list = state.venues.length
				? state.venues.map(function (v) {
					var d = v.data || {};
					return (
						'<div class="mbs-card" data-venue-id="' + v.id + '">' +
						'<p class="mbs-card__label">' + esc(v.title) + '</p>' +
						'<p class="mbs-card__hint">' + esc(d.location || '') + '</p>' +
						'<button type="button" class="mbs-btn mbs-btn--small mbs-btn--ghost" data-action="delete-venue" data-id="' + v.id + '">Delete</button></div>'
					);
				}).join('')
				: '<div class="mbs-empty"><p>No saved venues</p></div>';

			root.innerHTML =
				'<div class="mbs-screen">' + topbar('Venues') + list +
				'<button type="button" class="mbs-btn mbs-btn--primary mbs-btn--block" style="margin-top:1rem" data-action="add-venue">Add venue</button></div>';
		});
	}

	/* ── Team reports ──────────────────────────────────────── */

	function renderTeam() {
		if (!cfg.teamReports) {
			navigate('dashboard', true);
			return;
		}
		apiGet('team-reports').then(function (res) {
			var reports = res.reports || [];
			var list = reports.map(function (r) {
				return '<div class="mbs-card"><p class="mbs-card__label">' + esc(r.title) + '</p></div>';
			}).join('') || '<p class="mbs-card__hint">No reports submitted yet</p>';

			root.innerHTML =
				'<div class="mbs-screen">' + topbar('Team Reports') + list +
				'<div class="mbs-form" style="margin-top:1rem">' +
				'<label>Report title</label><input type="text" name="report_title" placeholder="Venue session report">' +
				'<label>Notes</label><textarea name="report_body"></textarea>' +
				'<button type="button" class="mbs-btn mbs-btn--primary mbs-btn--block" data-action="submit-report">Submit report</button></div></div>';
		});
	}

	/* ── Drawer forms ──────────────────────────────────────── */

	function catchAlreadyReported(catchEntry) {
		return !!(catchEntry && (catchEntry.media_report_submitted || catchEntry.catch_report_id));
	}

	function catchReportStatusHtml(catchEntry) {
		if (catchAlreadyReported(catchEntry)) {
			return ' <span class="mbs-badge mbs-badge--ok">Report submitted</span>';
		}
		return '';
	}

	function catchTimelineItemHtml(c, sessionId) {
		var reported = catchReportStatusHtml(c);
		var reportBtn = '';
		if (!reported && c && c.id) {
			reportBtn = ' <button type="button" class="mbs-btn mbs-btn--small mbs-btn--ghost" data-action="submit-catch-report" data-catch-id="' + esc(c.id) + '">Report</button>';
		}
		return '<li class="mbs-timeline__item"><time>' + esc(formatTime(c.catch_time)) + '</time> ' +
			esc(formatWeight(c.weight_lb, c.weight_oz)) + ' ' + esc(c.species || '') + reported + reportBtn + '</li>';
	}

	function bindCatchReportPhotoInput(wrap) {
		var input = wrap.querySelector('input[type="file"][name="photo"]');
		var preview = wrap.querySelector('[data-catch-photo-preview]');
		var empty = wrap.querySelector('[data-catch-photo-empty]');
		if (!input || !preview) {
			return;
		}
		function renderPreview() {
			preview.innerHTML = '';
			var files = input.files;
			if (!files || !files.length) {
				preview.hidden = true;
				if (empty) {
					empty.hidden = false;
				}
				return;
			}
			preview.hidden = false;
			if (empty) {
				empty.hidden = true;
			}
			Array.prototype.forEach.call(files, function (file, idx) {
				if (!file.type || file.type.indexOf('image/') !== 0) {
					return;
				}
				var url = URL.createObjectURL(file);
				var item = document.createElement('div');
				item.className = 'mbs-photo-preview__item';
				item.innerHTML =
					'<img src="' + url + '" alt="">' +
					'<p class="mbs-photo-preview__name">' + esc(file.name) + '</p>' +
					'<button type="button" class="mbs-btn mbs-btn--small mbs-btn--ghost" data-remove-photo="' + idx + '">Remove</button>';
				preview.appendChild(item);
			});
			preview.querySelectorAll('[data-remove-photo]').forEach(function (btn) {
				btn.addEventListener('click', function () {
					input.value = '';
					renderPreview();
				});
			});
		}
		input.addEventListener('change', renderPreview);
	}

	function drawerCatchForm() {
		var photos = cfg.settings && cfg.settings.catchPhotos;
		return (
			'<div class="mbs-form" data-drawer-form="catch">' +
			'<label>Species</label><input type="text" name="species" value="Carp">' +
			'<label>Weight (lb)</label><input type="number" name="weight_lb" step="0.1" min="0">' +
			'<label>Weight (oz)</label><input type="number" name="weight_oz" step="0.1" min="0" max="15">' +
			'<label>Bait used</label><input type="text" name="bait_used">' +
			'<label>Hookbait</label><input type="text" name="hookbait">' +
			'<label>Rig</label><input type="text" name="rig">' +
			'<label>Swim</label><input type="text" name="swim">' +
			(photos
				? '<div class="mbs-photo-field" data-catch-photo-field>' +
					'<label>Catch photo</label>' +
					'<p class="mbs-card__hint mbs-photo-field__empty" data-catch-photo-empty>No photo selected</p>' +
					'<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" capture="environment">' +
					'<div class="mbs-photo-preview" data-catch-photo-preview hidden></div></div>'
				: '') +
			'<label><input type="checkbox" name="is_pb"> Personal best</label>' +
			'<label class="mbs-consent"><input type="checkbox" name="use_in_catch_report" value="1" checked> Use in catch report when submitted</label>' +
			'<label>Private note</label><textarea name="private_note"></textarea></div>'
		);
	}

	function drawerCatchReportForm(opts) {
		opts = opts || {};
		var session = opts.session;
		var data = session && session.data ? session.data : {};
		var c = opts.catch || {};
		var user = cfg.user || {};
		var photos = cfg.settings && cfg.settings.catchPhotos;
		var weather = latestWeatherSnapshot(data);
		var weatherHint = weather.summary || (weather.temp_c != null ? weather.temp_c + '°C' : '');
		var fishWeight = (c.weight_lb || c.weight_oz) ? formatWeight(c.weight_lb, c.weight_oz) : '';

		return (
			'<div class="mbs-form" data-drawer-form="catch_report">' +
			'<input type="hidden" name="session_catch_id" value="' + esc(c.id || '') + '">' +
			(c.photo_id ? '<input type="hidden" name="existing_photo_id" value="' + esc(String(c.photo_id)) + '">' : '') +
			'<label>Angler name</label><input type="text" name="angler_name" required value="' + esc(user.displayName || '') + '" autocomplete="name" autocapitalize="words">' +
			'<label>Email</label><input type="email" name="angler_email" value="' + esc(user.email || '') + '" autocomplete="email">' +
			'<label>Phone</label><input type="tel" name="angler_phone" autocomplete="tel">' +
			'<label>Fish weight</label><input type="text" name="fish_weight" required value="' + esc(fishWeight) + '" placeholder="e.g. 24lb 8oz" autocomplete="off" autocapitalize="off" spellcheck="false">' +
			'<label>Venue</label><input type="text" name="venue" value="' + esc(data.venue_name || '') + '" autocomplete="off" autocapitalize="words">' +
			'<label>Swim / peg</label><input type="text" name="swim" value="' + esc(c.swim || data.lake_swim || '') + '" autocomplete="off" autocapitalize="words">' +
			'<label>Bait used</label><input type="text" name="bait_used" value="' + esc(c.bait_used || '') + '" autocomplete="off" autocapitalize="words">' +
			'<label>Hookbait</label><input type="text" name="hookbait" value="' + esc(c.hookbait || '') + '" autocomplete="off" autocapitalize="words">' +
			'<label>Rig / approach</label><input type="text" name="rig" value="' + esc(c.rig || '') + '" autocomplete="off" autocapitalize="words">' +
			'<label>Story / session notes</label><textarea name="story" rows="4">' + esc(c.private_note || '') + '</textarea>' +
			(weatherHint ? '<p class="mbs-card__hint">Weather on session: ' + esc(weatherHint) + '</p>' : '') +
			(photos
				? '<div class="mbs-photo-field" data-catch-photo-field>' +
					'<label>Catch photo</label>' +
					(c.photo_id ? '<p class="mbs-card__hint">Session photo will be included unless you replace it below.</p>' : '<p class="mbs-card__hint mbs-photo-field__empty" data-catch-photo-empty>No photo selected</p>') +
					'<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" capture="environment">' +
					'<div class="mbs-photo-preview" data-catch-photo-preview hidden></div></div>'
				: '<p class="mbs-card__hint">No photo attached — you can still submit your report.</p>') +
			'<label class="mbs-consent"><input type="checkbox" name="marketing_consent" value="1"> I give Mad Baits permission to use this catch report and photo on social media, the website and marketing.</label>' +
			'</div>'
		);
	}

	function openCatchReportDrawer(sessionId, catchEntry) {
		if (catchEntry && (catchEntry.media_report_submitted || catchEntry.catch_report_id)) {
			toast('This catch has already been reported');
			return;
		}
		var session = state.currentSession;
		openDrawer(
			'Submit Catch Report',
			drawerCatchReportForm({ session: session, catch: catchEntry || {} }),
			true,
			{
				submitLabel: 'Submit to media team',
				formType: 'catch_report'
			}
		);
	}

	function showCatchReportSuccess(sessionId, baitUsed) {
		closeDrawer();
		var shopUrl = cfg.shopUrl || '/shop/';
		if (baitUsed) {
			shopUrl = shopUrl + (shopUrl.indexOf('?') > -1 ? '&' : '?') + 's=' + encodeURIComponent(baitUsed);
		}
		var sessionUrl = (cfg.sessionUrl || '') + '#session/' + sessionId;
		root.innerHTML =
			'<div class="mbs-screen mbs-success-screen">' +
			topbar('Catch report submitted') +
			'<div class="mbs-card mbs-card--hero">' +
			'<p class="mbs-card__label">' + esc(cfg.i18n && cfg.i18n.reportSubmitted ? cfg.i18n.reportSubmitted : 'Catch report submitted') + '</p>' +
			'<p class="mbs-card__hint">' + esc(cfg.i18n && cfg.i18n.reportThanks ? cfg.i18n.reportThanks : 'Thanks — the Mad Baits media team has received your report.') + '</p>' +
			'</div>' +
			'<button type="button" class="mbs-btn mbs-btn--primary mbs-btn--block" data-action="view-session-after-report" data-session-id="' + esc(String(sessionId)) + '">View My Session</button>' +
			'<button type="button" class="mbs-btn mbs-btn--ghost mbs-btn--block" data-action="another-catch-report" data-session-id="' + esc(String(sessionId)) + '">Submit Another Catch</button>' +
			'<a class="mbs-btn mbs-btn--ghost mbs-btn--block" href="' + esc(shopUrl) + '">Shop Bait Used</a>' +
			'</div>';
		haptic(30);
	}

	function submitCatchReportFromDrawer(sid, data) {
		if (state.reportSubmitting) {
			return Promise.resolve();
		}
		state.reportSubmitting = true;
		var form = state.drawer && state.drawer.el.querySelector('[data-drawer-form="catch_report"]');
		var fileInput = form && form.querySelector('input[type="file"][name="photo"]');
		var file = fileInput && fileInput.files && fileInput.files[0];
		var existingPhoto = parseInt(data.existing_photo_id, 10) || 0;

		function postReport(photoId) {
			var payload = {
				session_catch_id: data.session_catch_id || '',
				angler_name: data.angler_name || '',
				angler_email: data.angler_email || '',
				angler_phone: data.angler_phone || '',
				fish_weight: data.fish_weight || '',
				weight_lb: parseFloat(data.weight_lb) || 0,
				weight_oz: parseFloat(data.weight_oz) || 0,
				venue: data.venue || '',
				swim: data.swim || '',
				bait_used: data.bait_used || '',
				hookbait: data.hookbait || '',
				rig: data.rig || '',
				story: data.story || '',
				marketing_consent: !!data.marketing_consent,
				catch_time: data.catch_time || new Date().toISOString()
			};
			if (photoId) {
				payload.photo_id = photoId;
			} else if (existingPhoto) {
				payload.photo_id = existingPhoto;
			}
			return apiPost('sessions/' + sid + '/catch_report', payload);
		}

		var chain = file
			? uploadPhoto(file).then(function (up) {
				return postReport(up.attachment_id);
			})
			: postReport(0);

		return chain.then(function (res) {
			state.reportSubmitting = false;
			if (res.session) {
				state.currentSession = res.session;
			}
			showCatchReportSuccess(sid, data.bait_used || '');
			return res;
		}).catch(function (err) {
			state.reportSubmitting = false;
			if (err && err.code === 'mbs_report_exists') {
				toast('This catch has already been reported');
			} else {
				toast(err.message || 'Could not submit catch report');
			}
			throw err;
		});
	}

	function drawerBaitingForm() {
		return (
			'<div class="mbs-form" data-drawer-form="baiting">' +
			'<label>Bait</label><input type="text" name="bait">' +
			'<label>Amount</label><input type="text" name="amount">' +
			'<label>Method</label><input type="text" name="method" placeholder="Spomb, catapult…">' +
			'<label>Notes</label><textarea name="notes"></textarea></div>'
		);
	}

	function drawerRigForm() {
		return (
			'<div class="mbs-form" data-drawer-form="rig">' +
			'<label>Rig name</label><input type="text" name="name">' +
			'<label>Hook size</label><input type="text" name="hook_size">' +
			'<label>Hooklink</label><input type="text" name="hooklink">' +
			'<label>Lead</label><input type="text" name="lead">' +
			'<label>Hookbait</label><input type="text" name="hookbait">' +
			'<label>Notes</label><textarea name="notes"></textarea></div>'
		);
	}

	function drawerConditionsForm(session) {
		var c = (session && session.data && session.data.conditions) || {};
		return (
			'<div class="mbs-form" data-drawer-form="conditions">' +
			selectField('water_clarity', 'Water clarity', c.water_clarity, ['Clear', 'Slightly coloured', 'Coloured', 'Murky']) +
			selectField('water_temp', 'Water temp', c.water_temp, ['Cold', 'Cool', 'Warm', 'Hot']) +
			selectField('weed_level', 'Weed', c.weed_level, ['None', 'Light', 'Moderate', 'Heavy']) +
			selectField('fishing_pressure', 'Angling pressure', c.fishing_pressure, ['Low', 'Medium', 'High']) +
			selectField('lake_activity', 'Lake activity', c.lake_activity, ['Quiet', 'Moderate', 'Busy']) +
			'<label>Notes</label><textarea name="notes">' + esc(c.notes || '') + '</textarea></div>'
		);
	}

	function drawerWeatherForm(session) {
		var snap = latestWeatherSnapshot(session && session.data) || {};
		var liveBtn = weatherApiReady()
			? '<button type="button" class="mbs-btn mbs-btn--primary mbs-btn--block" data-action="fetch-weather-drawer">Refresh live weather</button>'
			: '<p class="mbs-card__hint">Add OpenWeather API key in WooCommerce → Session for live data.</p>';
		return (
			'<div class="mbs-form" data-drawer-form="weather">' +
			weatherCardHtml(snap, 'Log or refresh bank weather.') +
			weatherManualFields(snap) +
			liveBtn +
			'</div>'
		);
	}

	function drawerBaitForm() {
		var amounts = cfg.quickAmounts || ['250g', '500g', '1kg', 'handful', 'spod mix', 'solid bag'];
		var chips = amounts.map(function (amt) {
			return '<button type="button" class="mbs-amount-chip" data-action="pick-amount" data-amount="' + esc(amt) + '">' + esc(amt) + '</button>';
		}).join('');
		return (
			'<div class="mbs-form" data-drawer-form="bait">' +
			'<label>Bait / product</label><input type="text" name="name" placeholder="Range or product name">' +
			'<label>Hookbait</label><input type="text" name="hookbait">' +
			'<label>Type</label><input type="text" name="type" placeholder="Pellet, boilie, particle, liquid…">' +
			'<label>Amount used</label><input type="text" name="amount_used" data-amount-field>' +
			'<div class="mbs-amount-chips">' + chips + '</div>' +
			'<label>Time added</label><input type="time" name="time_added">' +
			'<label>Notes</label><textarea name="notes"></textarea></div>'
		);
	}

	function drawerPhotoForm(session) {
		var data = session && session.data ? session.data : {};
		return (
			'<div class="mbs-form" data-drawer-form="photo">' +
			'<div class="mbs-photo-field" data-catch-photo-field>' +
			'<label>Catch photo</label>' +
			'<p class="mbs-card__hint mbs-photo-field__empty" data-catch-photo-empty>Add one or more photos (first = main unless you change it).</p>' +
			'<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" capture="environment" multiple>' +
			'<div class="mbs-photo-preview" data-catch-photo-preview hidden></div></div>' +
			'<label>Fish weight</label><input type="text" name="weight" placeholder="e.g. 22lb">' +
			'<label>Bait used</label><input type="text" name="bait_used">' +
			'<label>Swim / peg</label><input type="text" name="swim" value="' + esc(data.lake_swim || '') + '">' +
			'<label>Venue</label><input type="text" name="venue" value="' + esc(data.venue_name || '') + '">' +
			'<label class="mbs-consent"><input type="checkbox" name="use_in_catch_report" value="1" checked> Use this photo in catch report</label>' +
			'</div>'
		);
	}

	function drawerNoteForm() {
		return '<div class="mbs-form" data-drawer-form="note"><label>Note</label><textarea name="note" rows="4"></textarea></div>';
	}

	function collectFormData(form) {
		var data = {};
		if (!form) {
			return data;
		}
		form.querySelectorAll('input, select, textarea').forEach(function (el) {
			if (!el.name || el.type === 'file') {
				return;
			}
			if (el.type === 'checkbox') {
				data[el.name] = el.checked;
			} else {
				data[el.name] = el.value;
			}
		});
		return data;
	}

	function sessionIdFromRoot() {
		var el = root.querySelector('[data-session-id]');
		if (el) {
			return parseInt(el.getAttribute('data-session-id'), 10);
		}
		if (state.drawer && state.drawer.el) {
			el = state.drawer.el.closest('[data-session-id]') || root.querySelector('[data-session-id]');
		}
		return el ? parseInt(el.getAttribute('data-session-id'), 10) : null;
	}

	function submitDrawerAction(action) {
		var sid = sessionIdFromRoot();
		var formSelector = action ? '[data-drawer-form="' + action + '"]' : '[data-drawer-form]';
		var form = state.drawer && state.drawer.el.querySelector(formSelector);
		var data = collectFormData(form);

		if (action === 'venue') {
			return apiPost('venues', {
				title: data.title || 'Saved Venue',
				data: { location: data.location || '', notes: data.notes || '' }
			}).then(function () {
				haptic(12);
				toast('Venue saved');
				closeDrawer();
				renderVenues();
			}).catch(function (err) {
				toast(err.message || 'Save failed');
			});
		}

		if (!sid) {
			return Promise.resolve();
		}

		if (action === 'catch_report') {
			if (!data.fish_weight && !(parseFloat(data.weight_lb) || parseFloat(data.weight_oz))) {
				toast('Enter fish weight');
				return Promise.reject(new Error('missing weight'));
			}
			toast(cfg.i18n && cfg.i18n.submittingReport ? cfg.i18n.submittingReport : 'Submitting catch report…');
			return submitCatchReportFromDrawer(sid, data);
		}

		if (action === 'activity') {
			var actType = data.type || 'note';
			return apiPost('sessions/' + sid + '/activity', {
				type: actType,
				notes: data.notes || '',
				time: new Date().toISOString()
			}).then(function () {
				haptic(12);
				toast('Logged');
				closeDrawer();
				renderActiveSession(sid);
			}).catch(function (err) {
				toast(err.message || 'Could not log');
			});
		}

		if (action === 'photo') {
			toast('Saving photo…');
			var photoInput = form && form.querySelector('input[name="photo"]');
			var files = photoInput && photoInput.files ? Array.prototype.slice.call(photoInput.files) : [];
			function uploadAll() {
				if (!files.length) {
					return Promise.resolve([]);
				}
				return files.reduce(function (chain, file) {
					return chain.then(function (ids) {
						return uploadPhoto(file).then(function (up) {
							ids.push(up.attachment_id);
							return ids;
						});
					});
				}, Promise.resolve([]));
			}
			return uploadAll().then(function (ids) {
				var mainId = ids[0] || 0;
				return apiPost('sessions/' + sid + '/photo', {
					attachment_id: mainId,
					photo_ids: ids,
					main_photo_id: mainId,
					weight: data.weight || '',
					bait_used: data.bait_used || '',
					swim: data.swim || '',
					venue: data.venue || '',
					use_in_catch_report: !!data.use_in_catch_report,
					captured_at: new Date().toISOString()
				});
			}).then(function () {
				haptic(20);
				toast('Photo saved');
				closeDrawer();
				renderActiveSession(sid);
			}).catch(function (err) {
				toast(err.message || 'Photo failed');
			});
		}

		if (action === 'bait') {
			return apiPost('sessions/' + sid + '/bait', {
				name: data.name || '',
				range: data.range || data.name || '',
				hookbait: data.hookbait || '',
				bait_type: data.type || '',
				amount_used: data.amount_used || '',
				time_added: data.time_added || '',
				notes: data.notes || ''
			}).then(function () {
				haptic(12);
				toast('Bait logged');
				closeDrawer();
				renderActiveSession(sid);
			}).catch(function (err) {
				toast(err.message || 'Save failed');
			});
		}

		if (action === 'catch') {
			toast(cfg.i18n && cfg.i18n.savingCatch ? cfg.i18n.savingCatch : 'Saving catch…');
			var fileInput = form && form.querySelector('input[name="photo"]');
			var files = fileInput && fileInput.files ? Array.prototype.slice.call(fileInput.files) : [];

			function postCatch(photoIds, mainId) {
				var payload = {
					species: data.species || 'Carp',
					weight_lb: parseFloat(data.weight_lb) || 0,
					weight_oz: parseFloat(data.weight_oz) || 0,
					bait_used: data.bait_used || '',
					hookbait: data.hookbait || '',
					rig: data.rig || '',
					swim: data.swim || '',
					is_pb: !!data.is_pb,
					public_consent: !!data.public_consent,
					use_in_catch_report: !!data.use_in_catch_report,
					private_note: data.private_note || '',
					catch_time: new Date().toISOString()
				};
				if (mainId) {
					payload.photo_id = mainId;
					payload.main_photo_id = mainId;
				}
				if (photoIds && photoIds.length) {
					payload.photo_ids = photoIds;
				}
				return apiPost('sessions/' + sid + '/catch', payload);
			}

			function uploadAll() {
				if (!files.length) {
					return Promise.resolve({ ids: [], main: 0 });
				}
				return files.reduce(function (chain, file) {
					return chain.then(function (acc) {
						return uploadPhoto(file).then(function (up) {
							acc.ids.push(up.attachment_id);
							return acc;
						});
					});
				}, Promise.resolve({ ids: [] })).then(function (acc) {
					acc.main = acc.ids[0] || 0;
					return acc;
				});
			}

			return uploadAll().then(function (res) {
				return postCatch(res.ids, res.main);
			}).then(function () {
				haptic(25);
				toast(cfg.i18n && cfg.i18n.catchLogged ? cfg.i18n.catchLogged : 'Catch logged');
				closeDrawer();
				renderActiveSession(sid);
			}).catch(function (err) {
				toast(err.message || 'Failed to log catch');
			});
		}

		if (action === 'note') {
			return apiPost('sessions/' + sid + '/note', { note: data.note || '' }).then(function () {
				haptic(12);
				toast('Note added');
				closeDrawer();
				renderActiveSession(sid);
			}).catch(function (err) {
				toast(err.message || 'Save failed');
			});
		}

		if (action === 'conditions') {
			return apiPost('sessions/' + sid + '/conditions', data).then(function () {
				haptic(12);
				toast('Conditions updated');
				closeDrawer();
				renderActiveSession(sid);
			}).catch(function (err) {
				toast(err.message || 'Save failed');
			});
		}

		if (action === 'weather') {
			var manual = collectManualWeather(form, '');
			var snapPromise = manual
				? Promise.resolve(manual)
				: (weatherApiReady() && navigator.geolocation
					? new Promise(function (resolve, reject) {
						navigator.geolocation.getCurrentPosition(function (pos) {
							fetchLiveWeather({ lat: pos.coords.latitude, lng: pos.coords.longitude }).then(resolve).catch(reject);
						}, reject);
					})
					: Promise.resolve(null));

			return snapPromise.then(function (snap) {
				if (!snap) {
					toast('Enter air conditions above, or enable live weather in WooCommerce → Session');
					return null;
				}
				return apiPost('sessions/' + sid + '/weather', snap);
			}).then(function (res) {
				if (!res) {
					return;
				}
				haptic(12);
				toast('Weather saved');
				closeDrawer();
				renderActiveSession(sid);
			}).catch(function (err) {
				toast(err.message || 'Save failed');
			});
		}

		var path = 'sessions/' + sid + '/' + action;
		return apiPost(path, data).then(function () {
			haptic(12);
			toast('Saved');
			closeDrawer();
			renderActiveSession(sid);
		}).catch(function (err) {
			toast(err.message || 'Save failed');
		});
	}

	/* ── Share card (canvas) ───────────────────────────────── */

	function buildShareCard(opts, callback) {
		var canvas = document.createElement('canvas');
		canvas.width = 1080;
		canvas.height = 1350;
		var ctx = canvas.getContext('2d');
		ctx.fillStyle = '#0a0a0b';
		ctx.fillRect(0, 0, canvas.width, canvas.height);

		function drawText() {
			ctx.fillStyle = '#fff202';
			ctx.font = 'bold 48px system-ui, sans-serif';
			ctx.fillText('Mad Baits', 60, 120);
			ctx.fillStyle = '#f5f5f5';
			ctx.font = 'bold 72px system-ui, sans-serif';
			ctx.fillText(opts.weight || '', 60, 1050);
			ctx.font = '36px system-ui, sans-serif';
			ctx.fillStyle = '#9a9a9e';
			ctx.fillText(opts.venue || '', 60, 1120);
			ctx.fillStyle = '#fff202';
			ctx.font = '28px system-ui, sans-serif';
			ctx.fillText('madbaits.co.uk', 60, 1280);
			if (callback) {
				callback(canvas);
			}
		}

		if (opts.photoUrl) {
			var img = new Image();
			img.crossOrigin = 'anonymous';
			img.onload = function () {
				ctx.drawImage(img, 60, 160, 960, 800);
				drawText();
			};
			img.onerror = drawText;
			img.src = opts.photoUrl;
		} else {
			drawText();
		}
		return canvas;
	}

	function shareCatch(index) {
		var session = state.currentSession;
		if (!session || !session.data) {
			return;
		}
		var c = (session.data.catches || [])[index];
		if (!c) {
			return;
		}
		var photoUrl = c.photo_id ? null : null;
		if (c.photo_id && session.data.photos) {
			/* photo url resolved server-side in gallery */
		}
		apiGet('insights/gallery').then(function (res) {
			var match = (res.items || []).find(function (item) {
				return item.catch_id === c.id;
			});
			var url = match ? match.photo_url : '';
			var canvas = buildShareCard({
				weight: formatWeight(c.weight_lb, c.weight_oz),
				venue: session.data.venue_name || '',
				photoUrl: url
			}, function (cv) {
				cv.toBlob(function (blob) {
					if (!blob) {
						return;
					}
					if (navigator.share && navigator.canShare) {
						var file = new File([blob], 'mad-baits-catch.png', { type: 'image/png' });
						if (navigator.canShare({ files: [file] })) {
							navigator.share({ files: [file], title: 'My Mad Baits catch' });
							return;
						}
					}
					var a = document.createElement('a');
					a.href = URL.createObjectURL(blob);
					a.download = 'mad-baits-catch.png';
					a.click();
				}, 'image/png');
			});
		});
	}

	/* ── Data loaders ──────────────────────────────────────── */

	function loadAccess() {
		return apiGet('session/access').then(function (res) {
			state.access = res.state || cfg.access;
			state.canUse = !!res.can_use;
			cfg.mobileOnly = res.mobile_only != null ? res.mobile_only : cfg.mobileOnly;
			cfg.isMobile = res.is_mobile != null ? res.is_mobile : cfg.isMobile;
		}).catch(function () {
			state.access = cfg.access;
			state.canUse = cfg.canUse;
		});
	}

	function loadDashboard() {
		return apiGet('session/dashboard').then(function (res) {
			state.dashboard = res;
		});
	}

	function loadSessions() {
		return apiGet('sessions').then(function (res) {
			state.sessions = res.sessions || [];
		});
	}

	function loadSession(id) {
		return apiGet('sessions/' + id).then(function (res) {
			return res;
		});
	}

	function loadVenues() {
		return apiGet('venues').then(function (res) {
			state.venues = res.venues || [];
		});
	}

	function loadProducts() {
		return apiGet('order-products').then(function (res) {
			state.products = res.products || [];
		});
	}

	/* ── Route render ──────────────────────────────────────── */

	function renderRoute() {
		stopTimer();
		closeDrawer();

		if (!state.canUse) {
			if (cfg.mobileOnly && !cfg.isMobile) {
				renderLocked('mbs-tpl-desktop-locked');
				return;
			}
			if (state.access === 'guest') {
				renderLocked('mbs-tpl-guest-locked');
				return;
			}
			if (state.access === 'logged_no_order') {
				renderLocked('mbs-tpl-customer-locked');
				return;
			}
			renderLocked('mbs-tpl-customer-locked');
			return;
		}

		var r = parseRoute();
		var hash = location.hash.replace(/^#/, '') || 'dashboard';

		if (!state.skipHash) {
			if (state.historyStack[state.historyStack.length - 1] !== hash) {
				state.historyStack.push(hash);
			}
		}
		state.skipHash = false;

		document.body.classList.add('mad-session-route');

		switch (r.route) {
			case 'dashboard':
				loadDashboard().then(renderDashboard);
				break;
			case 'start':
				Promise.all([loadVenues(), loadProducts()]).then(function () {
					if (!state.wizard.data || !Object.keys(state.wizard.data).length) {
						state.wizard = { step: 0, data: {} };
					}
					renderStartWizard();
				});
				break;
			case 'active':
				if (r.id) {
					renderActiveSession(r.id);
				} else {
					navigate('dashboard', true);
				}
				break;
			case 'session':
				if (r.id) {
					stopTimer();
					renderSessionReport(r.id);
				} else {
					navigate('logbook', true);
				}
				break;
			case 'logbook':
				renderLogbook();
				break;
			case 'insights':
				if (r.sub === 'conditions') {
					renderInsightsConditions();
				} else {
					renderInsightsBait();
				}
				break;
			case 'gallery':
				renderGallery();
				break;
			case 'venues':
				renderVenues();
				break;
			case 'team':
				renderTeam();
				break;
			case 'end':
				if (r.id) {
					renderEndSession(r.id);
				} else {
					navigate('dashboard', true);
				}
				break;
			default:
				navigate('dashboard', true);
		}

		hideLoader();
		updateSyncUI();
	}

	/* ── Event delegation ──────────────────────────────────── */

	function bindEvents() {
		root.addEventListener('click', function (e) {
			var t = e.target.closest('[data-nav]');
			if (t) {
				e.preventDefault();
				haptic(6);
				navigate(t.getAttribute('data-nav'));
				return;
			}

			var actionEl = e.target.closest('[data-action]');
			if (!actionEl) {
				return;
			}
			var action = actionEl.getAttribute('data-action');

			if (action === 'back') {
				e.preventDefault();
				goBack();
				return;
			}

			if (action === 'close-drawer') {
				closeDrawer();
				return;
			}

			if (action === 'drawer-submit' && state.drawer) {
				var formType = state.drawer.el.querySelector('[data-drawer-form]');
				var type = formType ? formType.getAttribute('data-drawer-form') : '';
				setDrawerSubmitState(true);
				Promise.resolve(submitDrawerAction(type)).finally(function () {
					setDrawerSubmitState(false);
				});
				return;
			}

			if (action === 'wizard-prev') {
				collectWizardStep();
				state.wizard.step = Math.max(0, state.wizard.step - 1);
				renderStartWizard();
				return;
			}

			if (action === 'wizard-next') {
				collectWizardStep();
				if (state.wizard.step < WIZARD_STEPS.length - 1) {
					state.wizard.step++;
					renderStartWizard();
				} else {
					submitWizard();
				}
				return;
			}

			if (action === 'geo') {
				if (!navigator.geolocation) {
					toast('Geolocation not supported');
					return;
				}
				navigator.geolocation.getCurrentPosition(function (pos) {
					state.wizard.data.location_lat = pos.coords.latitude;
					state.wizard.data.location_lng = pos.coords.longitude;
					toast('Location captured');
					if (weatherApiReady()) {
						fetchLiveWeather({ lat: pos.coords.latitude, lng: pos.coords.longitude }).then(function (w) {
							state.wizard.data.weather = w;
							toast('Live weather loaded');
							if (state.wizard.step === 2) {
								renderStartWizard();
							}
						}).catch(function (err) {
							toast(err.message || 'Weather fetch failed');
						});
					}
				}, function () {
					toast('Could not get location');
				});
				return;
			}

			if (action === 'fetch-weather-wizard') {
				collectWizardStep();
				var d = state.wizard.data;
				var done = function (w) {
					state.wizard.data.weather = w;
					toast('Live weather loaded');
					renderStartWizard();
				};
				var fail = function (err) { toast(err.message || 'Weather failed'); };
				if (d.location_lat != null && d.location_lng != null) {
					fetchLiveWeather({ lat: d.location_lat, lng: d.location_lng }).then(done).catch(fail);
				} else if (d.postcode) {
					fetchLiveWeather({ postcode: d.postcode }).then(done).catch(fail);
				} else if (navigator.geolocation) {
					navigator.geolocation.getCurrentPosition(function (pos) {
						fetchLiveWeather({ lat: pos.coords.latitude, lng: pos.coords.longitude }).then(done).catch(fail);
					}, function () { toast('Allow location or enter a postcode'); });
				} else {
					toast('Set location on step 2 first');
				}
				return;
			}

			if (action === 'fetch-weather-drawer') {
				var sidW = sessionIdFromRoot();
				var failD = function (err) { toast(err.message || 'Weather failed'); };
				var saveW = function (w) {
					apiPost('sessions/' + sidW + '/weather', w).then(function () {
						toast('Weather updated');
						closeDrawer();
						renderActiveSession(sidW);
					}).catch(failD);
				};
				if (navigator.geolocation) {
					navigator.geolocation.getCurrentPosition(function (pos) {
						fetchLiveWeather({ lat: pos.coords.latitude, lng: pos.coords.longitude }).then(saveW).catch(failD);
					}, failD);
				} else {
					toast('Location required for live weather');
				}
				return;
			}

			if (action === 'log-catch') {
				openDrawer('Log Catch', drawerCatchForm(), true, { formType: 'catch', submitLabel: 'Log catch' });
				return;
			}

			if (action === 'add-photo') {
				openDrawer('Add Catch Photo', drawerPhotoForm(state.currentSession), true, { formType: 'photo', submitLabel: 'Save photo' });
				return;
			}

			if (action === 'quick-activity') {
				var sidAct = sessionIdFromRoot();
				var actType = actionEl.getAttribute('data-activity-type');
				if (!sidAct || !actType) {
					return;
				}
				apiPost('sessions/' + sidAct + '/activity', { type: actType, time: new Date().toISOString() }).then(function () {
					haptic(10);
					toast('Logged');
					renderActiveSession(sidAct);
				}).catch(function (err) {
					toast(err.message || 'Could not log');
				});
				return;
			}

			if (action === 'pick-amount') {
				var amt = actionEl.getAttribute('data-amount');
				var field = state.drawer && state.drawer.el.querySelector('[data-amount-field]');
				if (field && amt) {
					field.value = amt;
				}
				return;
			}

			if (action === 'quick-weather') {
				var sidW2 = sessionIdFromRoot();
				openDrawer('Bank weather', drawerWeatherForm(state.currentSession), true, { formType: 'weather' });
				return;
			}

			if (action === 'share-summary') {
				var sess = state.currentSession;
				if (!sess) {
					return;
				}
				var sum = sess.end_summary || {};
				var text = 'Mad Baits session at ' + (sum.venue || '') + '\n' +
					'Catches: ' + (sum.catch_count || 0) + '\n' +
					'Best: ' + (sum.best_fish_lb ? sum.best_fish_lb + 'lb' : '—') + '\n' +
					(sum.weather ? 'Weather: ' + sum.weather : '');
				if (navigator.share) {
					navigator.share({ title: 'My Mad Baits session', text: text }).catch(function () {});
				} else {
					toast('Summary ready — copy from session report');
				}
				return;
			}

			if (action === 'submit-catch-report') {
				var sidReport = sessionIdFromRoot();
				var catchId = actionEl.getAttribute('data-catch-id');
				var catchEntry = null;
				if (catchId && state.currentSession && state.currentSession.data) {
					(state.currentSession.data.catches || []).forEach(function (c) {
						if (c.id === catchId) {
							catchEntry = c;
						}
					});
				}
				if (sidReport) {
					openCatchReportDrawer(sidReport, catchEntry);
				}
				return;
			}

			if (action === 'view-session-after-report') {
				var vsid = parseInt(actionEl.getAttribute('data-session-id'), 10);
				if (vsid) {
					navigate('active/' + vsid);
				}
				return;
			}

			if (action === 'another-catch-report') {
				var asid = parseInt(actionEl.getAttribute('data-session-id'), 10);
				if (asid) {
					loadSession(asid).then(function (session) {
						state.currentSession = session;
						openCatchReportDrawer(asid, null);
					});
				}
				return;
			}

			if (action === 'add-note') {
				openDrawer('Add Note', drawerNoteForm(), true);
				return;
			}

			if (action === 'end-session') {
				var sid = sessionIdFromRoot();
				if (sid) {
					navigate('end/' + sid);
				}
				return;
			}

			if (action === 'quick-baiting') {
				openDrawer('Log Baiting', drawerBaitingForm(), true);
				return;
			}
			if (action === 'quick-rig') {
				openDrawer('Log Rig', drawerRigForm(), true);
				return;
			}
			if (action === 'quick-conditions') {
				openDrawer('Update Conditions', drawerConditionsForm(state.currentSession), true);
				return;
			}
			if (action === 'quick-bait') {
				openDrawer('Bait Used', drawerBaitForm(), true);
				return;
			}
			if (action === 'quick-weather') {
				openDrawer('Bank weather', drawerWeatherForm(state.currentSession), true);
				return;
			}

			if (action === 'submit-end') {
				var endId = sessionIdFromRoot();
				var endForm = root.querySelector('.mbs-form');
				var outcome = collectFormData(endForm);
				apiPost('sessions/' + endId + '/end', {
					outcome: {
						result: outcome.result,
						what_worked: outcome.what_worked,
						what_change: outcome.what_change,
						same_bait_again: outcome.same_bait_again,
						bait_confidence: parseInt(outcome.bait_confidence, 10) || 3,
						notes: outcome.notes
					},
					lessons: outcome.lessons || ''
				}).then(function () {
					haptic(30);
					toast('Session ended — here\'s your summary');
					state.skipHash = true;
					navigate('session/' + endId, true);
					renderSessionReport(endId);
				}).catch(function (err) {
					toast(err.message || 'Could not end session');
				});
				return;
			}

			if (action === 'repeat-session') {
				var repId = sessionIdFromRoot();
				apiPost('sessions/' + repId + '/repeat', {}).then(function (res) {
					toast('Session duplicated');
					if (res.id) {
						navigate('start');
						state.wizard = { step: 1, data: {
							title: res.title,
							venue_name: res.data && res.data.venue_name,
							lake_swim: res.data && res.data.lake_swim,
							venue_id: res.data && res.data.venue_id
						}};
					}
				});
				return;
			}

			if (action === 'reorder-session') {
				var roId = sessionIdFromRoot();
				apiPost('reorder', { session_id: roId }).then(function (res) {
					if (res.redirect_url || res.cart_url) {
						window.location.href = res.redirect_url || res.cart_url || cfg.cartUrl;
					} else {
						toast('Added to cart');
						if (cfg.cartUrl) {
							setTimeout(function () { window.location.href = cfg.cartUrl; }, 800);
						}
					}
				}).catch(function () {
					toast('Reorder failed');
				});
				return;
			}

			if (action === 'print-report') {
				window.print();
				return;
			}

			if (action === 'delete-session') {
				if (!window.confirm('Delete this session?')) {
					return;
				}
				var delId = sessionIdFromRoot();
				apiDelete('sessions/' + delId).then(function () {
					toast('Session deleted');
					navigate('logbook');
				});
				return;
			}

			if (action === 'share-catch') {
				shareCatch(parseInt(actionEl.getAttribute('data-index'), 10));
				return;
			}

			if (action === 'add-venue') {
				openDrawer('New Venue', '<div class="mbs-form" data-drawer-form="venue"><label>Name</label><input type="text" name="title"><label>Location</label><input type="text" name="location"><label>Notes</label><textarea name="notes"></textarea></div>', true);
				return;
			}

			if (action === 'delete-venue') {
				var vid = parseInt(actionEl.getAttribute('data-id'), 10);
				if (window.confirm('Delete venue?')) {
					apiDelete('venues/' + vid).then(function () {
						renderVenues();
					});
				}
				return;
			}

			if (action === 'submit-report') {
				var rf = root.querySelector('.mbs-form');
				var title = val(rf, 'report_title') || 'Team Report';
				var body = val(rf, 'report_body');
				apiPost('team-reports', { title: title, notes: body }).then(function () {
					toast('Report submitted');
					renderTeam();
				});
				return;
			}
		});

		window.addEventListener('hashchange', function () {
			state.skipHash = false;
			renderRoute();
		});

		window.addEventListener('online', function () {
			state.online = true;
			updateSyncUI();
			flushOfflineQueue().then(function () {
				var r = parseRoute();
				if (r.route === 'dashboard') {
					loadDashboard().then(renderDashboard);
				}
			});
		});

		window.addEventListener('offline', function () {
			state.online = false;
			updateSyncUI();
		});
	}

	/* ── Boot ──────────────────────────────────────────────── */

	function boot() {
		if (reducedMotion) {
			document.documentElement.classList.add('mbs-reduced-motion');
		}

		bindEvents();

		loadAccess().then(function () {
			if (cfg.mobileOnly && !cfg.isMobile) {
				renderLocked('mbs-tpl-desktop-locked');
				return;
			}
			if (!state.canUse) {
				if (state.access === 'guest') {
					renderLocked('mbs-tpl-guest-locked');
				} else {
					renderLocked('mbs-tpl-customer-locked');
				}
				return;
			}

			root.classList.add('mbs-session-app--full');
			if (!location.hash || location.hash === '#') {
				location.hash = 'dashboard';
			}
			flushOfflineQueue().then(renderRoute);
		}).catch(function () {
			hideLoader();
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
