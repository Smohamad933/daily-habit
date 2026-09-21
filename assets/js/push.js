/* ثبت سرویس‌ورکر و اشتراک پوش نوتیفیکیشن */
(function () {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

  function urlBase64ToUint8Array(b64) {
    const pad = '='.repeat((4 - b64.length % 4) % 4);
    const s = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(Array.from(s).map(c => c.charCodeAt(0)));
  }

  window.showBrowserNotif = function (title, body) {
    if (Notification.permission === 'granted' && document.hidden) {
      try { navigator.serviceWorker.getRegistration().then(reg => {
        if (reg) reg.showNotification(title, { body, icon: undefined, dir: 'rtl', lang: 'fa' });
      }); } catch (e) {}
    }
  };

  navigator.serviceWorker.register(window.APP.sw).then(reg => {
    return reg.pushManager.getSubscription().then(sub => {
      if (sub) return sendSub(sub);
      if (!window.APP.vapid) return;
      if (Notification.permission === 'denied') return;
      const ask = Notification.permission === 'default' ? Notification.requestPermission() : Promise.resolve(Notification.permission);
      return ask.then(p => {
        if (p !== 'granted') return;
        return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(window.APP.vapid) })
          .then(sendSub).catch(() => {});
      });
    });
  }).catch(() => {});

  function sendSub(sub) {
    const j = sub.toJSON();
    return fetch(window.APP.api, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ action: 'push_sub', endpoint: j.endpoint, p256dh: j.keys.p256dh, auth: j.keys.auth, csrf: window.APP.csrf || '' }).toString()
    }).catch(() => {});
  }
})();
