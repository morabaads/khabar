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

	function enhance() {
		if ($.fn.wpColorPicker) { $body.find('.khabar-color').wpColorPicker(); }
		$body.find('.khabar-notice').delay(6000).fadeOut(400);
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
			go(action, { method: 'POST', body: fd }, true, true);
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
