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
		// Re-parent the popup to <body>: ancestors with transform/overflow in themes would otherwise clip a fixed-position modal.
		this.$modal.appendTo(document.body);
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
		this.$modal.on('click', '.khabar-close, .khabar-close-done', function () { self.close(); });
		this.$modal.on('click', function (e) { if (e.target === self.$modal[0]) { self.close(); } });
		$(document).on('keydown', function (e) { if (e.key === 'Escape') { self.close(); } });

		this.$form.on('change', '[data-toggle]', function () {
			var $input = self.$form.find('[name="' + $(this).data('toggle') + '"]');
			$input.prop('disabled', !this.checked);
			if (this.checked) { $input.trigger('focus'); }
			self.updateMode();
		});
		this.$form.on('change', 'input[type=checkbox]', function () { self.updateMode(); });
		this.$form.on('change', '.khabar-channels input', function () { self.syncContact(); });
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

	// Show only the contact fields the ticked channels need (SMS/WhatsApp -> mobile, email -> email; name always).
	Widget.prototype.syncContact = function () {
		var $ch = this.$form.find('.khabar-channels input:checked');
		if (!this.$form.find('.khabar-channels').length) { return; }
		var on = {};
		$ch.each(function () { on[this.value] = true; });
		var needsAny = false;
		this.$form.find('.khabar-contact label[data-for]').each(function () {
			var $l = $(this), need = false;
			$.each(String($l.data('for')).split(' '), function (_, c) { if (on[c]) { need = true; } });
			$l.data('need', need);
			needsAny = needsAny || need;
		});
		this.$form.find('.khabar-contact label[data-for]').each(function () {
			var $l = $(this);
			// With only on-site / messenger channels ticked neither contact is needed, so keep both visible but optional.
			var show = $l.data('need') || !needsAny;
			$l.prop('hidden', !show).find('input').prop('disabled', !show);
		});
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
		this.$modal.find('.khabar-variation-label').text(parts.length ? '(' + parts.join('، ') + ')' : '');
	};

	Widget.prototype.open = function (mode) {
		var self = this;
		this.$form.prop('hidden', false)[0].reset();
		// Start clean (browser form-restore / autofill must not leave conditions ticked): only the first condition is on.
		this.$form.find('.khabar-conditions input[type=checkbox]').prop('checked', false);
		this.$form.find('.khabar-conditions input[name=mode][value=all]').prop('checked', true);
		this.$form.find('.khabar-num').val('').prop('disabled', true);
		this.$otp.prop('hidden', true);
		this.$done.prop('hidden', true);
		showMsg(this.$form.find('.khabar-msg'), '');

		// "When back in stock" is meaningless for an item that is already in stock (price alert button).
		var inStockNow = this.variation ? !!this.variation.is_in_stock : (!this.variable && String(this.$root.data('instock')) === '1');
		this.$form.find('[name=in_stock]').closest('.khabar-cond').prop('hidden', mode === 'price' && inStockNow);
		// Default: only the first available condition is ticked.
		this.$form.find('.khabar-cond:not([hidden]) input[type=checkbox]').first().prop('checked', true).trigger('change');

		// Pre-select the attributes currently chosen on the product page (Feature 2).
		var selection = this.$vform.length ? currentSelection(this.$vform) : {};
		this.$form.find('.khabar-attrs select').each(function () {
			var key = $(this).data('attr');
			if (selection[key]) { $(this).val(selection[key]); }
		});
		this.$form.find('[name=variation_id]').val(this.variation ? this.variation.variation_id : 0);
		this.updateLabel();
		this.updateMode();
		this.syncContact();

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
		initConnect(this.$done.find('.khabar-connect'), '');
	};


	/* ---------- Price history chart (single series step line) ---------- */

	var SVGNS = 'http://www.w3.org/2000/svg';

	function svg(tag, attrs, parent) {
		var el = document.createElementNS(SVGNS, tag);
		for (var k in attrs) { if (attrs.hasOwnProperty(k)) { el.setAttribute(k, attrs[k]); } }
		if (parent) { parent.appendChild(el); }
		return el;
	}

	function niceStep(range, count) {
		var raw = range / Math.max(1, count);
		var pow = Math.pow(10, Math.floor(Math.log10(raw || 1)));
		var n = raw / pow;
		return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10) * pow;
	}

	function PriceHistory($root) {
		this.$root = $root;
		this.cfg = $root.data('config');
		this.fmtDate = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { day: 'numeric', month: 'short' });
		this.fmtDateLong = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { day: 'numeric', month: 'long', year: 'numeric' });
		this.fmtNum = new Intl.NumberFormat('fa-IR', { maximumFractionDigits: this.cfg.decimals });
		this.fmtCompact = new Intl.NumberFormat('fa-IR', { notation: 'compact', maximumFractionDigits: 1 });
		this.show(this.cfg['default']);
		var self = this;
		var $vform = $('form.variations_form[data-product_id="' + $root.data('product') + '"]');
		$vform.on('found_variation', function (e, v) { if (self.cfg.series[v.variation_id]) { self.show(v.variation_id); } });
		if (window.ResizeObserver) {
			var last = 0;
			new ResizeObserver(function (entries) {
				var w = Math.round(entries[0].contentRect.width);
				if (Math.abs(w - last) > 4) { last = w; self.draw(); }
			}).observe($root.find('.khabar-ph-plot')[0]);
		}
	}

	PriceHistory.prototype.money = function (v) { return this.fmtNum.format(v) + ' ' + this.cfg.currency; };

	PriceHistory.prototype.show = function (id) {
		var s = this.cfg.series[id];
		if (!s) { return; }
		this.points = s.points;
		this.$root.find('.khabar-ph-variation').text(s.label ? '— ' + s.label : '');
		this.stats();
		this.table();
		this.draw();
	};

	PriceHistory.prototype.stats = function () {
		var p = this.points;
		var prices = p.map(function (x) { return x[1]; });
		var cur = prices[prices.length - 1];
		var min = Math.min.apply(null, prices);
		var max = Math.max.apply(null, prices);
		// Time-weighted average of the step function.
		var sum = 0, span = 0;
		for (var i = 0; i < p.length - 1; i++) { var d = p[i + 1][0] - p[i][0]; sum += p[i][1] * d; span += d; }
		var avg = span ? sum / span : cur;
		this.min = min;
		var tiles = [
			['قیمت فعلی', this.money(cur)],
			['کمترین', this.money(min)],
			['بیشترین', this.money(max)]
		];
		var $stats = this.$root.find('.khabar-ph-stats').empty();
		tiles.forEach(function (t) {
			$stats.append($('<div class="khabar-ph-tile">').append($('<span>').text(t[0])).append($('<strong>').text(t[1])));
		});
		var $ins = this.$root.find('.khabar-ph-insight').removeClass('is-good is-info');
		if (prices.length < 3 && min === max) {
			$ins.prop('hidden', false).addClass('is-info').text('ℹ️ قیمت در این بازه تغییری نداشته است.');
		} else if (cur <= min) {
			$ins.prop('hidden', false).addClass('is-good').text('✅ قیمت فعلی کمترین قیمت ' + this.fmtNum.format(this.cfg.days) + ' روز اخیر است.');
		} else if (avg && cur > avg * 1.02) {
			$ins.prop('hidden', false).addClass('is-info').text('ℹ️ قیمت فعلی ' + this.fmtNum.format(Math.round((cur / avg - 1) * 100)) + '٪ بالاتر از میانگین این بازه است.');
		} else if (avg && cur < avg * 0.98) {
			$ins.prop('hidden', false).addClass('is-good').text('✅ قیمت فعلی ' + this.fmtNum.format(Math.round((1 - cur / avg) * 100)) + '٪ پایین‌تر از میانگین این بازه است.');
		} else {
			$ins.prop('hidden', true);
		}
	};

	PriceHistory.prototype.table = function () {
		var self = this;
		var $tb = this.$root.find('.khabar-ph-table tbody').empty();
		var changes = this.points.filter(function (pt, i, a) { return i === 0 || pt[1] !== a[i - 1][1]; });
		changes.slice().reverse().forEach(function (pt) {
			$tb.append($('<tr>').append($('<td>').text(self.fmtDateLong.format(new Date(pt[0])))).append($('<td>').text(self.money(pt[1]))));
		});
	};

	PriceHistory.prototype.valueAt = function (t) {
		var p = this.points, v = p[0][1];
		for (var i = 0; i < p.length && p[i][0] <= t; i++) { v = p[i][1]; }
		return v;
	};

	PriceHistory.prototype.draw = function () {
		var self = this;
		var $plot = this.$root.find('.khabar-ph-plot');
		var W = Math.max(260, Math.round($plot.width() || 600));
		var H = W < 480 ? 200 : 240;
		var m = { l: 56, r: 16, t: 48, b: 28 }; // Top band holds the hover tooltip.
		var p = this.points;
		var now = Date.now();
		var t0 = Math.min(p[0][0], now - this.cfg.days * 864e5);
		var prices = p.map(function (x) { return x[1]; });
		var lo = Math.min.apply(null, prices), hi = Math.max.apply(null, prices);
		var pad = (hi - lo) * 0.15 || hi * 0.05 || 1;
		var step = niceStep(hi - lo + 2 * pad, 4);
		var y0 = Math.max(0, Math.floor((lo - pad) / step) * step), y1 = Math.ceil((hi + pad) / step) * step;
		var X = function (t) { return m.l + (t - t0) / (now - t0 || 1) * (W - m.l - m.r); };
		var Y = function (v) { return m.t + (1 - (v - y0) / (y1 - y0 || 1)) * (H - m.t - m.b); };

		$plot.empty();
		var root = svg('svg', { viewBox: '0 0 ' + W + ' ' + H, width: '100%', height: H, role: 'img', 'aria-label': 'نمودار تاریخچه قیمت' });

		// Recessive grid + clean y ticks.
		var g = svg('g', { 'class': 'khabar-ph-grid' }, root);
		for (var v = y0; v <= y1 + step / 2; v += step) {
			svg('line', { x1: m.l, x2: W - m.r, y1: Y(v), y2: Y(v) }, g);
			svg('text', { x: m.l - 8, y: Y(v) + 4, 'text-anchor': 'end', 'class': 'khabar-ph-tick' }, g).textContent = this.fmtCompact.format(v);
		}
		// X ticks: ~4 dates.
		for (var i = 0; i <= 3; i++) {
			var tt = t0 + (now - t0) * i / 3;
			svg('text', { x: X(tt), y: H - 8, 'text-anchor': i === 0 ? 'start' : i === 3 ? 'end' : 'middle', 'class': 'khabar-ph-tick' }, g).textContent = this.fmtDate.format(new Date(tt));
		}

		// Step path: the price holds until the next change.
		var d = 'M' + X(t0) + ',' + Y(p[0][1]);
		for (var k = 1; k < p.length; k++) { d += 'H' + X(p[k][0]) + 'V' + Y(p[k][1]); }
		d += 'H' + X(now);
		svg('path', { d: d + 'V' + Y(y0) + 'H' + X(t0) + 'Z', 'class': 'khabar-ph-area' }, root);
		svg('path', { d: d, 'class': 'khabar-ph-line' }, root);

		// Lowest point marker (selective label), only when it isn't the current price.
		var cur = prices[prices.length - 1];
		if (this.min < cur) {
			var idx = prices.indexOf(this.min);
			var tx = X(p[idx][0]);
			svg('circle', { cx: tx, cy: Y(this.min), r: 4, 'class': 'khabar-ph-dot' }, root);
			svg('text', { x: Math.min(Math.max(tx, m.l + 30), W - m.r - 30), y: Y(this.min) + 18, 'text-anchor': 'middle', 'class': 'khabar-ph-label-muted' }, root).textContent = 'کمترین';
		}
		// End dot + current value label.
		svg('circle', { cx: X(now), cy: Y(cur), r: 4, 'class': 'khabar-ph-dot' }, root);
		// Put the label on the side away from the previous step so it never sits on the line.
		var before = prices.length > 1 ? prices[prices.length - 2] : cur;
		for (var b = prices.length - 2; b >= 0 && before === cur; b--) { before = prices[b]; }
		var below = before > cur && Y(cur) + 22 < H - m.b;
		if (W >= 480) { // On narrow screens the "current price" tile already carries this value.
			svg('text', { x: X(now) - 8, y: below ? Y(cur) + 20 : Y(cur) - 10, 'text-anchor': 'end', 'class': 'khabar-ph-label' }, root).textContent = this.money(cur);
		}

		// Crosshair + tooltip (snaps to the hovered day).
		var cross = svg('line', { y1: m.t, y2: H - m.b, 'class': 'khabar-ph-cross', visibility: 'hidden' }, root);
		var hot = svg('circle', { r: 4, 'class': 'khabar-ph-dot', visibility: 'hidden' }, root);
		var hit = svg('rect', { x: m.l, y: 0, width: W - m.l - m.r, height: H, fill: 'transparent', tabindex: 0, 'aria-label': 'برای مشاهده قیمت هر روز، اشاره‌گر را حرکت دهید یا از کلیدهای جهت استفاده کنید' }, root);
		$plot.append(root);
		var $tip = $('<div class="khabar-ph-tip" role="status" hidden>').appendTo($plot);
		var focusT = now;

		function at(t) {
			t = Math.max(t0, Math.min(now, t));
			t = Math.round(t / 864e5) * 864e5;
			t = Math.max(t0, Math.min(now, t));
			focusT = t;
			var val = self.valueAt(t), x = X(t);
			cross.setAttribute('x1', x); cross.setAttribute('x2', x); cross.setAttribute('visibility', 'visible');
			hot.setAttribute('cx', x); hot.setAttribute('cy', Y(val)); hot.setAttribute('visibility', 'visible');
			$tip.empty().append($('<span>').text(self.fmtDateLong.format(new Date(t)))).append($('<strong>').text(self.money(val))).prop('hidden', false);
			var scale = ($plot.width() || W) / W;
			var left = x * scale, tw = $tip.outerWidth();
			$tip.css({ left: Math.max(0, Math.min(left - tw / 2, $plot.width() - tw)) + 'px', top: '0px' });
		}
		function hide() { cross.setAttribute('visibility', 'hidden'); hot.setAttribute('visibility', 'hidden'); $tip.prop('hidden', true); }
		hit.addEventListener('pointermove', function (e) {
			var r = root.getBoundingClientRect();
			var x = (e.clientX - r.left) * W / r.width;
			at(t0 + (x - m.l) / (W - m.l - m.r) * (now - t0));
		});
		hit.addEventListener('pointerleave', hide);
		hit.addEventListener('blur', hide);
		hit.addEventListener('focus', function () { at(focusT); });
		hit.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
				e.preventDefault();
				at(focusT + (e.key === 'ArrowRight' ? 1 : -1) * 864e5);
			}
		});
	};

	/* ---------- Messenger connect (Telegram / Bale) ---------- */

	function initConnect($box, token) {
		var networks = $box.data('networks') || [];
		if (!networks.length) { return; }
		var q = token ? '?token=' + encodeURIComponent(token) : '';
		$box.empty();
		var pending = networks.length;
		networks.forEach(function (net) {
			api('messenger/link' + q, 'POST', { network: net }).then(function (res) {
				var name = D.i18n.networks[net] || net;
				var $a = $('<a class="khabar-connect-btn" target="_blank" rel="noopener">').attr('data-network', net);
				if (res.connected) {
					$a.addClass('is-connected').text(D.i18n.connected.replace('%s', name));
				} else if (res.url) {
					$a.attr('href', res.url).text(D.i18n.connect.replace('%s', name));
				} else { return; }
				$box.append($a);
			}).catch(function () {}).then(function () {
				if (--pending === 0 && $box.children().length) {
					if ($box.find('a:not(.is-connected)').length) { $box.append($('<p class="khabar-connect-hint">').text(D.i18n.connectTip)); }
					$box.prop('hidden', false);
				}
			});
		});
	}

	/* ---------- Customer panel (Flow 4) ---------- */

	function initPanel($panel) {
		var token = $panel.data('token') || '';
		var q = token ? '?token=' + encodeURIComponent(token) : '';
		initConnect($panel.find('.khabar-connect'), token);

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

	// Initialise every Khabar component inside a scope once (also used by the Elementor editor).
	function init($scope) {
		$scope = $scope && $scope.length ? $scope : $(document);
		var once = function (sel, fn) {
			$scope.find(sel).addBack(sel).each(function () {
				var $el = $(this);
				if ($el.data('khabarReady')) { return; }
				$el.data('khabarReady', true);
				fn($el);
			});
		};
		once('.khabar', function ($el) { new Widget($el); });
		once('.khabar-panel', initPanel);
		once('[data-khabar-bell]', initBell);
		once('.khabar-ph', function ($el) { try { new PriceHistory($el); } catch (e) { $el.remove(); } });
	}

	window.Khabar = { init: init };
	$(function () { init($(document)); });
}(jQuery));
