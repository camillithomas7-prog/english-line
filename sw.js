/* English Line — service worker: app usabile offline, aggiornamenti network-first */
const CACHE = "el-v4-1";
const CORE = ["./", "./index.html", "./icon-192.png", "./icon-512.png", "./manifest.json"];

self.addEventListener("install", e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(CORE)).then(() => self.skipWaiting()));
});
self.addEventListener("activate", e => {
  e.waitUntil(
    caches.keys()
      .then(ks => Promise.all(ks.filter(k => k !== CACHE).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});
self.addEventListener("fetch", e => {
  if (e.request.method !== "GET") return;
  const u = new URL(e.request.url);
  if (u.pathname.endsWith("api.php") || u.pathname.endsWith("install.php")) return; // API sempre in rete
  e.respondWith(
    fetch(e.request)
      .then(r => {
        const cp = r.clone();
        caches.open(CACHE).then(c => c.put(e.request, cp));
        return r;
      })
      .catch(() => caches.match(e.request).then(m => m || caches.match("./index.html")))
  );
});
