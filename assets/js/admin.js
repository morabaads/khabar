/* global jQuery */
/**
 * Khabar admin: single-page navigation. Every link / form inside #khabar-app is fetched with a
 * custom header; the server answers with just the section markup (no WordPress chrome) and the
 * body is swapped in place. Anything unexpected falls back to a normal page load.
 */
jQuery(function ($) {
	var $app = $('#khabar-app');
	var $body = $('#khabar-body');
	var ctl = null;

	$(document).on('click', '.khabar-reset', function () {
		$('#' + $(this).data('target')).val($(this).data('default'));
	});

	/* ---- SMS pattern repeater ---- */
	function driverOf($root) {
		var gw = $('#khabar-sms_gateway').val();
		var d = ($root.data('drivers') || {})[gw] || gw;
		return d === 'compat' ? ($('#khabar-sms_platform').val() || 'payamak_panel') : d;
	}
	var starters = {
		ghasedak: [['name', '{product_name}'], ['price', '{price}'], ['link', '{link}']],
		iranpayamak: [['product', '{product_name}'], ['price', '{price}'], ['link', '{link}']],
		payamak_panel: [['p1', '{product_name}'], ['p2', '{price}'], ['p3', '{link}']],
		kavenegar: [['token', '{product_name}'], ['token2', '{price}'], ['token3', '{link}']],
		melipayamak: [['p1', '{product_name}'], ['p2', '{price}'], ['p3', '{link}']],
		ippanel: [['product', '{product_name}'], ['price', '{price}'], ['link', '{link}']],
		smsir: [['Product', '{product_name}'], ['Price', '{price}'], ['Link', '{link}']]
	};
	var otpStarter = { kavenegar: [['token', '{code}']], payamak_panel: [['p1', '{code}']], ghasedak: [['code', '{code}']], iranpayamak: [['code', '{code}']], ippanel: [['code', '{code}']], smsir: [['Code', '{code}']] };
	var uid = Date.now();

	function varRow(base, name, value) {
		var k = ++uid;
		var $r = $('<div class="khabar-pat-var">');
		$('<input type="text" dir="ltr" class="khabar-pat-vname" placeholder="token">').attr('name', base + '[vars][' + k + '][name]').val(name || '').appendTo($r);
		$('<span class="khabar-pat-arrow">\u2190</span>').appendTo($r);
		$('<input type="text" dir="auto" class="khabar-pat-vval" placeholder="{product_name}">').attr('name', base + '[vars][' + k + '][value]').val(value || '').appendTo($r);
		$('<button type="button" class="khabar-pat-vdel" aria-label="\u062d\u0630\u0641">\u00d7</button>').appendTo($r);
		return $r;
	}

	function syncPatterns($root) {
		var gw = driverOf($root);
		var hints = $root.data('hints') || {};
		$root.find('.khabar-pat-gw').text(hints[gw] || '').prop('hidden', !hints[gw]);
		var used = {};
		$root.find('.khabar-pat-event').each(function () { used[this.value] = (used[this.value] || 0) + 1; });
		$root.find('.khabar-pat-event').each(function () {
			var self = this;
			$(this).find('option').each(function () {
				$(this).prop('disabled', this.value !== self.value && !!used[this.value]);
			});
		});
		$root.find('.khabar-pat-empty').prop('hidden', !!$root.find('.khabar-pat-row').length);
		// Pattern UI only matters in pattern mode.
		var pattern = $('#khabar-sms_mode').val() === 'pattern' && ($root.data('patternDrivers') || []).indexOf(gw) !== -1 && $('#khabar-sms_mode').closest('tr').css('display') !== 'none';
		$root.closest('tr').toggle(pattern);
	}

	function initPatterns() {
		$body.find('.khabar-pat').each(function () { syncPatterns($(this)); });
	}
	$(document).on('change', '#khabar-sms_gateway, #khabar-sms_mode, .khabar-pat-event', function () {
		$('.khabar-pat').each(function () { syncPatterns($(this)); });
	});
	$(document).on('click', '.khabar-pat-add', function () {
		var $root = $(this).closest('.khabar-pat');
		var n = ++uid;
		var html = $root.find('.khabar-pat-tpl').html().replace(/__i__/g, n);
		var $row = $($.parseHTML(html.trim())).filter('.khabar-pat-row');
		// First free event.
		var used = {};
		$root.find('.khabar-pat-event').each(function () { used[this.value] = 1; });
		var $sel = $row.find('.khabar-pat-event');
		var free = $sel.find('option').filter(function () { return !used[this.value]; }).first().val();
		if (free) { $sel.val(free); }
		var gw = driverOf($root);
		var list = (free === 'otp' ? otpStarter[gw] : starters[gw]) || [];
		var base = $row.find('.khabar-pat-vars').data('base');
		$.each(list, function (_, v) { $row.find('.khabar-pat-vlist').append(varRow(base, v[0], v[1])); });
		$root.find('.khabar-pat-list').append($row);
		syncPatterns($root);
		$row.find('.khabar-pat-code input').trigger('focus');
	});
	$(document).on('click', '.khabar-pat-del', function () {
		var $root = $(this).closest('.khabar-pat');
		$(this).closest('.khabar-pat-row').remove();
		syncPatterns($root);
	});
	$(document).on('click', '.khabar-pat-addvar', function () {
		var $vars = $(this).closest('.khabar-pat-vars');
		var $r = varRow($vars.data('base'), '', '');
		$vars.find('.khabar-pat-vlist').append($r);
		$r.find('.khabar-pat-vname').trigger('focus');
	});
	$(document).on('click', '.khabar-pat-vdel', function () { $(this).closest('.khabar-pat-var').remove(); });
	$(document).on('focusin', '.khabar-pat-vval', function () { $(this).closest('.khabar-pat-vars').data('last', this); });
	$(document).on('click', '.khabar-chip', function () {
		var $vars = $(this).closest('.khabar-pat-vars');
		var el = $vars.data('last');
		if (!el || !document.body.contains(el)) { el = $vars.find('.khabar-pat-vval').filter(function () { return !this.value; }).first()[0] || $vars.find('.khabar-pat-vval').last()[0]; }
		if (!el) { return; }
		var t = $(this).data('token');
		var s = el.selectionStart == null ? el.value.length : el.selectionStart;
		var e = el.selectionEnd == null ? s : el.selectionEnd;
		el.value = el.value.slice(0, s) + t + el.value.slice(e);
		el.focus();
		el.selectionStart = el.selectionEnd = s + t.length;
	});

	// Rows that only apply to one gateway / provider (data-show-if='{"sms_gateway":["kavenegar"]}').
	function rulesOk(el) {
		// A rule set is {field: [values]} (all must match); a list of rule sets matches if any does.
		var rules = $(el).data('showIf') || {};
		var sets = $.isArray(rules) ? rules : [rules];
		return sets.some(function (set) {
			var all = true;
			$.each(set, function (key, values) {
				var $f = $('#khabar-' + key);
				if ($f.length && (values.indexOf($f.val()) === -1 || $f.closest('tr').css('display') === 'none')) { all = false; }
			});
			return all;
		});
	}
	function syncConditional() {
		$body.find('tr[data-show-if]').each(function () { $(this).toggle(rulesOk(this)); });
		// A settings group is shown when its own rule passes and at least one of its rows is.
		$body.find('details.khabar-acc').each(function () {
			var rows = $(this).find('tr').filter(function () { return this.style.display !== 'none'; }).length;
			$(this).toggle(rows > 0 && (!this.hasAttribute('data-show-if') || rulesOk(this)));
			accBadge($(this));
		});
		$('.khabar-pat').each(function () { syncPatterns($(this)); });
	}
	$(document).on('change', '#khabar-body select', syncConditional);

	// Settings groups (accordion): remember which panels are open and keep the status badge live.
	var ACC_KEY = 'khabarAccOpen';
	function accOpen() {
		try { return JSON.parse(sessionStorage.getItem(ACC_KEY) || '[]'); } catch (e) { return []; }
	}
	function accBadge($d) {
		var $badge = $d.find('> summary .khabar-acc-badge');
		var status = $d.data('status');
		if (status) {
			$badge.attr('class', 'khabar-acc-badge is-info').text($('#khabar-' + status + ' option:selected').text());
			return;
		}
		var labels = $d.data('labels') || {};
		// Only fields shown for the current gateway / provider count.
		var keys = ($d.data('ready') || []).filter(function (k) {
			var $f = $('#khabar-' + k);
			return !$f.length || $f.closest('tr').css('display') !== 'none';
		});
		var filled = keys.filter(function (k) {
			var $f = $('#khabar-' + k);
			if ($f.length) { return $.trim($f.val() || '') !== ''; }
			return $d.find('input[name="khabar[' + k + '][]"]:checked').length > 0;
		}).length;
		// No required field applies to the chosen gateway/provider: show no badge rather than a wrong one.
		var state = !keys.length ? 'none' : (!filled ? 'off' : (filled === keys.length ? 'on' : 'partial'));
		$badge.attr('class', 'khabar-acc-badge is-' + state).text(labels[state] || '');
	}
	function initAcc() {
		var open = accOpen();
		$body.find('details.khabar-acc').each(function () {
			if (open.indexOf(this.id) !== -1) { this.open = true; }
		});
	}
	// "toggle" does not bubble: listen in the capture phase.
	document.addEventListener('toggle', function (e) {
		var d = e.target;
		if (!d.matches || !d.matches('details.khabar-acc') || !d.id) { return; }
		var open = accOpen().filter(function (id) { return id !== d.id; });
		if (d.open) { open.push(d.id); }
		try { sessionStorage.setItem(ACC_KEY, JSON.stringify(open)); } catch (err) {}
	}, true);
	$(document).on('input change', 'details.khabar-acc :input', function () { accBadge($(this).closest('details.khabar-acc')); });
	// Option cards that show another setting's value (e.g. the channel id) follow it live.
	$(document).on('input', '#khabar-body :input[id^="khabar-"]', function () {
		var key = this.id.replace(/^khabar-/, ''), v = $.trim(this.value || '');
		$body.find('[data-hint-from="' + key + '"]').each(function () {
			$(this).text(v || $(this).data('empty')).toggleClass('is-missing', !v);
		});
	});

	function enhance() {
		syncConditional();
		initPatterns();
		initAcc();
		if ($.fn.wpColorPicker) { $body.find('.khabar-color').wpColorPicker(); }
		// Flash notices become a floating toast so saving never moves the page.
		var $n = $body.find('.khabar-notice');
		if ($n.length) {
			var $wrap = $('#khabar-toasts');
			if (!$wrap.length) { $wrap = $('<div id="khabar-toasts" class="khabar-admin" aria-live="polite">').appendTo(document.body); }
			$n.each(function () {
				var $t = $(this).detach().addClass('khabar-toast').appendTo($wrap);
				$('<button type="button" class="khabar-toast-x" aria-label="بستن">×</button>').appendTo($t).on('click', function () { $t.remove(); });
				setTimeout(function () { $t.addClass('is-out'); setTimeout(function () { $t.remove(); }, 350); }, 4500);
			});
		}
	}
	enhance();
	if (!$app.length || !window.fetch || !window.URL) { return; }

	var NATIVE = /export|csv/;

	function isAppUrl(href) {
		var u;
		try { u = new URL(href, location.href); } catch (e) { return false; }
		if (u.origin !== location.origin) { return false; }
		if (/\/admin\.php$/.test(u.pathname)) { return u.searchParams.get('page') === 'khabar'; }
		if (/\/admin-post\.php$/.test(u.pathname)) {
			var a = u.searchParams.get('action') || '';
			return /^khabar_/.test(a) && !NATIVE.test(a);
		}
		return false;
	}

	function setActive(url) {
		var view = 'dashboard';
		try { view = new URL(url, location.href).searchParams.get('view') || 'dashboard'; } catch (e) {}
		// Keep the sidebar shortcut in sync.
		$('#toplevel_page_khabar .wp-submenu li').each(function () {
			var $a = $(this).children('a');
			if (!$a.length) { return; }
			var v = 'dashboard';
			try { v = new URL($a.attr('href'), location.href).searchParams.get('view') || 'dashboard'; } catch (e) {}
			$(this).toggleClass('current', v === view);
			$a.toggleClass('current', v === view).attr('aria-current', v === view ? 'page' : null);
		});
		var label = '';
		$app.find('.khabar-nav .nav-tab').each(function () {
			var on = $(this).data('view') === view;
			$(this).toggleClass('nav-tab-active', on);
			if (on) { label = $(this).text(); }
		});
		if (label) { document.title = label + ' ‹ ' + document.title.split('‹').pop().trim(); }
	}

	function go(url, init, push, scroll) {
		if (ctl) { ctl.abort(); }
		ctl = new AbortController();
		init = init || {};
		init.headers = { 'X-Khabar-Ajax': '1' };
		init.credentials = 'same-origin';
		init.signal = ctl.signal;
		$app.addClass('is-loading');
		return fetch(url, init).then(function (r) {
			if (r.headers.get('X-Khabar-Fragment') !== '1') { location.href = r.url || url; return null; }
			return r.text().then(function (html) { return { html: html, url: r.url || url }; });
		}).then(function (res) {
			if (!res) { return; }
			$body.html(res.html);
			if (push !== false) { history.pushState({ khabar: 1 }, '', res.url); }
			setActive(res.url);
			enhance();
			if (scroll) {
				var top = $app.offset().top - ($('#wpadminbar').outerHeight() || 0) - 8;
				if (window.pageYOffset > top) { window.scrollTo({ top: top, behavior: 'smooth' }); }
			}
		}).catch(function (e) {
			if (e && e.name === 'AbortError') { return; }
			location.href = url;
		}).then(function () { $app.removeClass('is-loading'); });
	}

	// Sidebar shortcuts also switch sections in place while the app is open.
	$('#toplevel_page_khabar .wp-submenu').on('click', 'a[href]', function (e) {
		if (e.button || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || !isAppUrl(this.href)) { return; }
		e.preventDefault();
		go(this.href, {}, true, true);
	});

	$app.on('click', 'a[href]', function (e) {
		if (e.defaultPrevented || e.button || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
		var a = this;
		if (a.target === '_blank' || a.hasAttribute('download') || a.hasAttribute('data-native') || !isAppUrl(a.href)) { return; }
		e.preventDefault();
		go(a.href, {}, true, true);
	});

	$app.on('submit', 'form', function (e) {
		var form = this;
		if (form.hasAttribute('data-native')) { return; }
		var action = form.getAttribute('action') || location.href;
		var fd;
		try { fd = new FormData(form, e.originalEvent && e.originalEvent.submitter); } catch (err) { fd = new FormData(form); }
		var act = fd.get('action');
		if (/\/admin-post\.php/.test(action) && act && NATIVE.test(String(act))) { return; }
		if (!isAppUrl(/\/admin-post\.php/.test(action) && act ? action + '?action=' + encodeURIComponent(act) : action)) { return; }
		e.preventDefault();
		var method = (form.method || 'get').toLowerCase();
		if (method === 'post') {
			go(action, { method: 'POST', body: fd }, true, false);
		} else {
			var u = new URL(action, location.href);
			u.search = new URLSearchParams(fd).toString();
			go(u.toString(), {}, true, false);
		}
	});

	// WordPress binds "select all" once on load, so repeat it for swapped-in tables.
	$app.on('click', '.check-column input[type=checkbox][id^="cb-select-all"]', function () {
		var on = this.checked;
		$(this).closest('table').find('tbody .check-column input[type=checkbox]').prop('checked', on);
		$app.find('.check-column input[id^="cb-select-all"]').prop('checked', on);
	});

	window.addEventListener('popstate', function () {
		if (isAppUrl(location.href)) { go(location.href, {}, false, false); }
	});
});
