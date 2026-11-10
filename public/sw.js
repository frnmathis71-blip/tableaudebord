/* Service worker : rend l'appli installable et affiche une page claire sans réseau.
   Aucune donnée personnelle n'est gardée en mémoire ici : seulement l'habillage. */
const CACHE = 'tdb-v1';
const FICHIERS = ['/hors-ligne.html', '/assets/icons/icone-192.png'];

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open(CACHE).then((c) => c.addAll(FICHIERS)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys()
            .then((noms) => Promise.all(noms.filter((n) => n !== CACHE).map((n) => caches.delete(n))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (e) => {
    const requete = e.request;
    if (requete.method !== 'GET' || new URL(requete.url).origin !== self.location.origin) return;

    // Pages : toujours le réseau ; sans réseau, la page « hors ligne ».
    if (requete.mode === 'navigate') {
        e.respondWith(fetch(requete).catch(() => caches.match('/hors-ligne.html')));
        return;
    }

    // Styles, scripts, icônes : réponse immédiate depuis la mémoire, mise à jour en arrière-plan.
    if (new URL(requete.url).pathname.startsWith('/assets/')) {
        e.respondWith(
            caches.open(CACHE).then(async (cache) => {
                const enMemoire = await cache.match(requete);
                const reseau = fetch(requete)
                    .then((r) => { if (r.ok) cache.put(requete, r.clone()); return r; })
                    .catch(() => enMemoire);
                return enMemoire || reseau;
            })
        );
    }
});
