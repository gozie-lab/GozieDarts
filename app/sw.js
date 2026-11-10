// Előbb a hálózat (mindig a legfrissebb verzió), offline esetén a mentett példány.
const C="darts-v74";
self.addEventListener("install",e=>{e.waitUntil(caches.open(C).then(c=>c.addAll(["./","index.html","manifest.webmanifest","fonts/fonts.css","fonts/inter-400.woff","fonts/inter-500.woff","fonts/inter-600.woff","fonts/inter-700.woff","icon-192.png","icon-512.png"])).then(()=>self.skipWaiting()))});
self.addEventListener("activate",e=>{e.waitUntil(caches.keys().then(k=>Promise.all(k.filter(x=>x!=C).map(x=>caches.delete(x)))).then(()=>self.clients.claim()))});
self.addEventListener("fetch",e=>{
 if(e.request.method!="GET"||new URL(e.request.url).pathname.match(/\.php$/))return;
 e.respondWith(fetch(e.request,{cache:"no-cache"}).then(r=>{if(r&&r.ok){const k=r.clone();caches.open(C).then(c=>c.put(e.request,k))}return r}).catch(()=>caches.open(C).then(c=>c.match(e.request,{ignoreSearch:true}))));
});
