/* سرویس‌ورکر: نمایش پوش نوتیفیکیشن */
self.addEventListener('push', function (e) {
  let data = { title: 'پلنر روزانه', body: 'اعلان جدید دارید.' };
  try { if (e.data) data = Object.assign(data, e.data.json()); } catch (err) {}
  e.waitUntil(self.registration.showNotification(data.title, {
    body: data.body, dir: 'rtl', lang: 'fa', badge: undefined,
    data: { url: data.url || '/' }
  }));
});

self.addEventListener('notificationclick', function (e) {
  e.notification.close();
  const url = (e.notification.data && e.notification.data.url) || '/';
  e.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
    for (const c of list) {
      if ('focus' in c) { c.focus(); return; }
    }
    return clients.openWindow(url);
  }));
});
