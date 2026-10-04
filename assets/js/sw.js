self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });

self.addEventListener('push', function (event) {
	event.waitUntil(
		self.registration.pushManager.getSubscription().then(function (sub) {
			if (!sub) { return null; }
			var sep = KHABAR_LATEST.indexOf('?') === -1 ? '?' : '&';
			return fetch(KHABAR_LATEST + sep + 'endpoint=' + encodeURIComponent(sub.endpoint), { credentials: 'omit' })
				.then(function (r) { return r.json(); });
		}).then(function (data) {
			data = data || {};
			return self.registration.showNotification(data.title || 'خبرم کن', {
				body: data.body || '',
				icon: data.icon || undefined,
				dir: 'rtl',
				lang: 'fa',
				tag: 'khabar',
				renotify: true,
				data: { url: data.url || '/' }
			});
		})
	);
});

self.addEventListener('notificationclick', function (event) {
	event.notification.close();
	var url = (event.notification.data && event.notification.data.url) || '/';
	event.waitUntil(self.clients.openWindow(url));
});
