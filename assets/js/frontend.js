/* global jQuery, KhabarData */
(function ($) {
	'use strict';

	var D = window.KhabarData || {};

	function faToEn(s) {
		return String(s || '').replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); })
			.replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); });
	}

	function api(path, method, data) {
		var headers = { 'Content-Type': 'application/json' };
		if (D.nonce) { headers['X-WP-Nonce'] = D.nonce; }
		// With plain permalinks the REST base already carries "?rest_route=".
		var url = D.rest + (D.rest.indexOf('?') !== -1 ? path.replace('?', '&') : path);
		return fetch(url, {
			method: method || 'GET',
			credentials: 'same-origin',
			headers: headers,
			body: data ? JSON.stringify(data) : undefined
		}).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (json) {
				if (!r.ok) { throw new Error((json && json.message) || D.i18n.error); }
				return json;
			});
		});
	}

	function showMsg($el, text, ok) {
		$el.text(text).prop('hidden', !text).toggleClass('khabar-ok', !!ok).toggleClass('khabar-err', !ok);
	}

	function b64ToUint8(base64) {
		var pad = '='.repeat((4 - base64.length % 4) % 4);
		var raw = atob((base64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
		var arr = new Uint8Array(raw.length);
		for (var i = 0; i < raw.length; i++) { arr[i] = raw.charCodeAt(i); }
		return arr;
	}

	function pushSubscription() {
		if (!D.pushKey || !('serviceWorker' in navigator) || !('PushManager' in window)) {
			return Promise.resolve(null);
		}
		return Notification.requestPermission().then(function (perm) {
			if (perm !== 'granted') { throw new Error(D.i18n.pushDenied); }
			return navigator.serviceWorker.register(D.sw, { scope: '/' });
		}).then(function () {
			return navigator.serviceWorker.ready;
		}).then(function (reg) {
			return reg.pushManager.getSubscription().then(function (existing) {
				return existing || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToUint8(D.pushKey) });
			});
		}).then(function (sub) { return sub ? sub.toJSON() : null; });
	}

	/* ---------- Variation helpers (Feature 3: smart button per variation) ---------- */

	function variationMatches(variation, selection) {
		for (var key in selection) {
			if (!selection.hasOwnProperty(key) || !selection[key]) { continue; }
			var have = variation.attributes[key];
			if (have && have !== selection[key]) { return false; }
		}
		return true;
	}

	function currentSelection($form) {
		var sel = {};
		$form.find('.variations select').each(function () {
			var name = $(this).data('attribute_name') || $(this).attr('name');
			sel[name] = $(this).val() || '';
		});
		return sel;
	}

	function annotateOptions($form) {
		var variations = $form.data('product_variations');
		if (!D.annotate || !variations) { return; }
		var selection = currentSelection($form);
		$form.find('.variations select').each(function () {
			var $select = $(this);
			var name = $select.data('attribute_name') || $select.attr('name');
			$select.find('option').each(function () {
				var $opt = $(this);
				var value = $opt.val();
				if (!value) { return; }
				if ($opt.data('khabarOrig') === undefined) { $opt.data('khabarOrig', $opt.text()); }
				var test = $.extend({}, selection);
				test[name] = value;
				var matches = variations.filter(function (v) { return variationMatches(v, test); });
				var oos = matches.length && matches.every(function (v) { return !v.is_in_stock; });
				$opt.text(oos ? $opt.data('khabarOrig') + ' — ' + D.i18n.outOfStock : $opt.data('khabarOrig'));
			});
		});
	}

	/* ---------- Product widget ---------- */

	function Widget($root) {
		this.$root = $root;
		this.$modal = $root.find('.khabar-modal');
		this.$form = $root.find('.khabar-form');
		this.$otp = $root.find('.khabar-otp');
		this.$done = $root.find('.khabar-done');
		this.variable = $root.data('variable') === 1 || $root.data('variable') === '1';
		this.waiting = $root.data('waiting') || {};
		this.variation = null;
		this.bind();
	}

	Widget.prototype.bind = function () {
		var self = this;
		var $vform = this.variable ? $('form.variations_form[data-product_id="' + this.$root.data('product') + '"]') : $();
		this.$vform = $vform;

		this.$root.on('click', '.khabar-open', function () { self.open($(this).data('mode')); });
		this.$root.on('click', '.khabar-close', function () { self.close(); });
		this.$modal.on('click', function (e) { if (e.target === self.$modal[0]) { self.close(); } });
		$(document).on('keydown', function (e) { if (e.key === 'Escape') { self.close(); } });

		this.$form.on('change', '[data-toggle]', function () {
			var $input = self.$form.find('[name="' + $(this).data('toggle') + '"]');
			$input.prop('disabled', !this.checked);
			if (this.checked) { $input.trigger('focus'); }
			self.updateMode();
		});
		this.$form.on('change', 'input[type=checkbox]', function () { self.updateMode(); });
		this.$form.on('change', '.khabar-attrs select', function () { self.$form.find('[name=variation_id]').val(0); self.updateLabel(); });
		this.$form.on('input', '.khabar-num', function () {
			var digits = faToEn(this.value).replace(/[^\d]/g, '');
			this.value = digits ? Number(digits).toLocaleString('en-US') : '';
		});
		this.$form.on('submit', function (e) { e.preventDefault(); self.submit(); });
		this.$otp.on('submit', function (e) { e.preventDefault(); self.verify(); });

		if ($vform.length) {
			$vform.on('found_variation', function (e, variation) { self.onVariation(variation); });
			$vform.on('reset_data', function () { self.onVariation(null); });
			$vform.on('woocommerce_update_variation_values', function () { annotateOptions($vform); });
			annotateOptions($vform);
		}
	};

	Widget.prototype.onVariation = function (variation) {
		this.variation = variation;
		var $stock = this.$root.find('.khabar-cta-stock');
		var $price = this.$root.find('.khabar-cta-price');
		if (variation) {
			$stock.prop('hidden', !!variation.is_in_stock);
			$price.prop('hidden', !variation.is_in_stock);
			var count = (this.waiting[variation.variation_id] || 0);
			var show = this.$root.data('showCount') && count >= Number(this.$root.data('countMin'));
			this.$root.find('.khabar-waiting').prop('hidden', !show).find('span').text(Number(count).toLocaleString('fa-IR'));
		} else {
			var inStock = String(this.$root.data('instock')) === '1';
			$stock.prop('hidden', inStock);
			$price.prop('hidden', !inStock);
		}
	};

	Widget.prototype.updateMode = function () {
		var n = this.$form.find('.khabar-conditions input[type=checkbox]:checked').length;
		this.$form.find('.khabar-mode').prop('hidden', n < 2);
	};

	Widget.prototype.updateLabel = function () {
		var parts = [];
		this.$form.find('.khabar-attrs select').each(function () {
			if ($(this).val()) { parts.push($(this).closest('label').find('span').text() + ': ' + $(this).find('option:selected').text()); }
		});
		this.$root.find('.khabar-variation-label').text(parts.length ? '(' + parts.join('، ') + ')' : '');
	};

	Widget.prototype.open = function (mode) {
		var self = this;
		this.$form.prop('hidden', false)[0].reset();
		this.$form.find('.khabar-num').prop('disabled', true);
		this.$otp.prop('hidden', true);
		this.$done.prop('hidden', true);
		showMsg(this.$form.find('.khabar-msg'), '');

		if (mode === 'price') {
			this.$form.find('[data-toggle=price_below]').prop('checked', true).trigger('change');
		} else {
			this.$form.find('[name=in_stock]').prop('checked', true);
		}

		// Pre-select the attributes currently chosen on the product page (Feature 2).
		var selection = this.$vform.length ? currentSelection(this.$vform) : {};
		this.$form.find('.khabar-attrs select').each(function () {
			var key = $(this).data('attr');
			if (selection[key]) { $(this).val(selection[key]); }
		});
		this.$form.find('[name=variation_id]').val(this.variation ? this.variation.variation_id : 0);
		this.updateLabel();
		this.updateMode();

		this.$modal.prop('hidden', false);
		$('body').addClass('khabar-lock');
		setTimeout(function () { self.$form.find('input:visible:not([type=checkbox]):not(:disabled)').first().trigger('focus'); }, 50);
	};

	Widget.prototype.close = function () {
		this.$modal.prop('hidden', true);
		$('body').removeClass('khabar-lock');
	};

	Widget.prototype.collect = function () {
		var data = { attributes: {}, channels: [] };
		$.each(this.$form.serializeArray(), function (_, f) {
			var m = f.name.match(/^attributes\[(.+)\]$/);
			if (m) { data.attributes[m[1]] = f.value; } else if (f.name === 'channels[]') { data.channels.push(f.value); } else { data[f.name] = f.value; }
		});
		['price_below', 'price_above', 'min_qty'].forEach(function (k) {
			if (data[k] !== undefined) { data[k] = faToEn(data[k]).replace(/[^\d.]/g, ''); }
		});
		if (data.phone) { data.phone = faToEn(data.phone); }
		return data;
	};

	Widget.prototype.submit = function () {
		var self = this;
		var $msg = this.$form.find('.khabar-msg');
		var $btn = this.$form.find('.khabar-submit');
		var data = this.collect();
		if (!this.$form.find('.khabar-conditions input[type=checkbox]:checked').length) {
			showMsg($msg, D.i18n.chooseOne);
			return;
		}
		$btn.prop('disabled', true).addClass('loading');
		var pushPromise = data.channels.indexOf('push') !== -1 ? pushSubscription().catch(function () { return null; }) : Promise.resolve(null);
		pushPromise.then(function (push) {
			if (push) { data.push = push; }
			return api('subscribe', 'POST', data);
		}).then(function (res) {
			if (res.need_code) {
				self.$form.prop('hidden', true);
				self.$otp.prop('hidden', false).find('[name=contact]').val(res.contact);
				self.$otp.find('.khabar-otp-text').text(res.message);
				self.$otp.find('[name=code]').val('').trigger('focus');
				return;
			}
			self.success(res.message);
		}).catch(function (err) {
			showMsg($msg, err.message);
		}).then(function () {
			$btn.prop('disabled', false).removeClass('loading');
		});
	};

	Widget.prototype.verify = function () {
		var self = this;
		var $msg = this.$otp.find('.khabar-msg');
		api('verify', 'POST', {
			contact: this.$otp.find('[name=contact]').val(),
			code: faToEn(this.$otp.find('[name=code]').val())
		}).then(function (res) { self.success(res.message); })
			.catch(function (err) { showMsg($msg, err.message); });
	};

	Widget.prototype.success = function (message) {
		this.$form.prop('hidden', true);
		this.$otp.prop('hidden', true);
		this.$done.prop('hidden', false).find('.khabar-done-text').text(message);
	};

	/* ---------- Customer panel (Flow 4) ---------- */

	function initPanel($panel) {
		var token = $panel.data('token') || '';
		var q = token ? '?token=' + encodeURIComponent(token) : '';

		$panel.on('click', '.khabar-edit', function () {
			$(this).closest('.khabar-sub').find('.khabar-edit-form').prop('hidden', function (_, v) { return !v; });
		});
		$panel.on('click', '.khabar-delete', function () {
			var $li = $(this).closest('.khabar-sub');
			if (!window.confirm(D.i18n.confirmDel)) { return; }
			api('subscriptions/' + $li.data('id') + q, 'DELETE').then(function () {
				$li.slideUp(200, function () { $li.remove(); });
			}).catch(function (err) { window.alert(err.message); });
		});
		$panel.on('submit', '.khabar-edit-form', function (e) {
			e.preventDefault();
			var $f = $(this);
			var $li = $f.closest('.khabar-sub');
			var data = { channels: [] };
			$.each($f.serializeArray(), function (_, f) {
				if (f.name === 'channels[]') { data.channels.push(f.value); } else { data[f.name] = faToEn(f.value); }
				if (/^(price_below|price_above|min_qty)$/.test(f.name)) { data[f.name] = data[f.name].replace(/[^\d.]/g, ''); }
			});
			if (!$f.find('[name="channels[]"]').length) { delete data.channels; }
			api('subscriptions/' + $li.data('id') + q, 'POST', data).then(function (res) {
				showMsg($f.find('.khabar-msg'), D.i18n.saved, true);
				$li.find('.khabar-sub-cond').text(res.subscription.conditions);
				$li.find('.khabar-badge').text(res.subscription.status_label);
			}).catch(function (err) { showMsg($f.find('.khabar-msg'), err.message); });
		});
	}

	/* ---------- Bell (on-site notifications) ---------- */

	function initBell($bell) {
		var $list = $bell.find('.khabar-bell-list');
		$bell.on('click', '.khabar-bell-btn', function () {
			if (!$list.prop('hidden')) { $list.prop('hidden', true); return; }
			api('notifications').then(function (res) {
				$list.empty();
				if (!res.items.length) { $list.append($('<p>').text(D.i18n.noNotes)); }
				res.items.forEach(function (n) {
					var $a = $('<a class="khabar-bell-item">').attr('href', n.url || '#').toggleClass('khabar-unread', n.is_read === '0' || n.is_read === 0);
					$a.append($('<strong>').text(n.title)).append($('<span>').text(n.message));
					$list.append($a);
				});
				$list.prop('hidden', false);
				if (res.unread) {
					api('notifications/read', 'POST', {}).then(function () { $bell.find('.khabar-bell-count').remove(); });
				}
			});
		});
		$(document).on('click', function (e) { if (!$(e.target).closest($bell).length) { $list.prop('hidden', true); } });
	}

	$(function () {
		$('.khabar').each(function () { new Widget($(this)); });
		$('.khabar-panel').each(function () { initPanel($(this)); });
		$('[data-khabar-bell]').each(function () { initBell($(this)); });
	});
}(jQuery));
