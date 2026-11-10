/**
 * Tests automatiques dans un vrai navigateur (Chromium, écran de la taille d'un iPhone).
 * Face ID est remplacé par une « clé d'accès virtuelle » fournie par le navigateur de test.
 * Aucune donnée réelle : base de test locale, identifiants fictifs.
 *
 * Lancement : voir tests/lancer.sh
 */
const { chromium, devices } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');

const URL_APPLI = process.env.TDB_URL || 'http://localhost:8765';
const SOCKET = process.env.TDB_DB_SOCKET;
const CAPTURES = process.env.TDB_CAPTURES || path.join(__dirname, '.tmp');
const BASE = { hote: '127.0.0.1:3307', nom: 'tdb_test', utilisateur: 'tdb', motDePasse: 'motdepasse-test' };

let reussis = 0;
const echecs = [];

function verifier(nom, condition) {
    if (condition) {
        reussis++;
        console.log(`  réussi  ${nom}`);
    } else {
        echecs.push(nom);
        console.log(`  ÉCHEC   ${nom}`);
    }
}

function sql(requete) {
    return execFileSync('mariadb', [`--socket=${SOCKET}`, '-uroot', '-N', '-B', BASE.nom, '-e', requete]).toString().trim();
}

async function cleVirtuelle(page) {
    const cdp = await page.context().newCDPSession(page);
    await cdp.send('WebAuthn.enable');
    await cdp.send('WebAuthn.addVirtualAuthenticator', {
        options: {
            protocol: 'ctap2', transport: 'internal', hasResidentKey: true,
            hasUserVerification: true, isUserVerified: true, automaticPresenceSimulation: true,
        },
    });
    return cdp;
}

const chemin = (page) => new URL(page.url()).pathname;

(async () => {
    const navigateur = await chromium.launch();
    const iphone = { ...devices['iPhone 14'], defaultBrowserType: undefined, deviceScaleFactor: 2 };
    const nouveauContexte = () => navigateur.newContext({ ...iphone, locale: 'fr-FR', timezoneId: 'Europe/Paris' });

    // ------------------------------------------------------------ Installation
    console.log('Installation');
    const contexte = await nouveauContexte();
    const page = await contexte.newPage();
    const erreursConsole = [];
    page.on('console', (m) => { if (m.type() === 'error') erreursConsole.push(m.text()); });
    page.on('pageerror', (e) => erreursConsole.push(String(e)));

    await page.goto(URL_APPLI + '/');
    verifier('sans configuration, on arrive sur la page d’installation', chemin(page) === '/installation');

    await page.fill('#db_host', BASE.hote);
    await page.fill('#db_name', BASE.nom);
    await page.fill('#db_user', BASE.utilisateur);
    await page.fill('#db_pass', 'mauvais-mot-de-passe');
    await page.click('button[type=submit]');
    verifier('mauvais mot de passe de base : message clair, rien n’est installé',
        (await page.locator('.alerte').innerText()).includes('Connexion à la base impossible'));

    await page.fill('#db_pass', BASE.motDePasse);
    await page.click('button[type=submit]');
    const codeSecours = (await page.locator('#code-secours').innerText()).trim();
    verifier('un code de secours est affiché', /^([A-Z2-9]{4}-){4}[A-Z2-9]{4}$/.test(codeSecours));
    verifier('le bouton « Créer ma clé » est bloqué tant que le code n’est pas noté',
        await page.locator('[data-action="creer-cle"]').isDisabled());
    await page.screenshot({ path: path.join(CAPTURES, '1-installation.png') });

    await cleVirtuelle(page);
    await page.check('#code-note');
    await page.click('[data-action="creer-cle"]');
    await page.waitForURL(URL_APPLI + '/');
    verifier('après création de la clé, on arrive sur « Aujourd’hui »',
        (await page.locator('h1').innerText()) === 'Aujourd’hui');

    // --------------------------------------------------------- Page Aujourd'hui
    console.log('Page « Aujourd’hui »');
    verifier('4 modules affichés', (await page.locator('.module').count()) === 4);
    verifier('la date du jour est affichée en français',
        /^(lundi|mardi|mercredi|jeudi|vendredi|samedi|dimanche) \d/i.test(await page.locator('.surtitre').innerText()));
    const debordement = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    verifier('pas de défilement horizontal sur un écran d’iPhone', !debordement);
    const fond = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
    verifier('mode sombre', fond === 'rgb(14, 17, 22)');
    const petites = await page.evaluate(() => [...document.querySelectorAll('.onglet, .bouton, #organiser')]
        .filter((e) => e.offsetParent !== null && e.getBoundingClientRect().height < 40).length);
    verifier('boutons assez grands pour le pouce', petites === 0);
    await page.screenshot({ path: path.join(CAPTURES, '2-aujourdhui.png') });

    // Vitesse d'affichage
    const debut = Date.now();
    await page.goto(URL_APPLI + '/', { waitUntil: 'load' });
    const duree = Date.now() - debut;
    verifier(`page affichée en moins de 2 secondes (mesuré ici : ${duree} ms, en local)`, duree < 2000);
    const poids = await page.evaluate(() => performance.getEntriesByType('resource')
        .reduce((t, r) => t + (r.transferSize || 0), performance.getEntriesByType('navigation')[0].transferSize || 0));
    verifier(`page légère (${Math.round(poids / 1024)} Ko transférés)`, poids < 150 * 1024);

    // ------------------------------------------------------- Modules déplaçables
    console.log('Modules déplaçables');
    await page.click('#organiser');
    await page.screenshot({ path: path.join(CAPTURES, '3-organiser.png') });
    const attente = () => page.waitForResponse((r) => r.url().endsWith('/api/modules') && r.status() === 200);
    let enregistre = attente();
    await page.click('.module[data-id="agenda"] [data-geste="bas"]');
    await enregistre;
    enregistre = attente();
    await page.click('.module[data-id="stage"] [data-geste="voir"]');
    await enregistre;
    await page.click('#organiser');
    await page.reload();
    const ordre = await page.locator('.module').evaluateAll((l) => l.map((e) => e.dataset.id));
    verifier('le nouvel ordre est conservé après rechargement', ordre.join(',') === 'ecole,agenda,pro,stage');
    verifier('le module masqué reste masqué', !(await page.locator('.module[data-id="stage"]').isVisible()));
    await page.click('#organiser');
    verifier('en mode « Organiser », le module masqué réapparaît pour pouvoir le réafficher',
        await page.locator('.module[data-id="stage"]').isVisible());
    await page.click('#organiser');

    // ------------------------------------------------ Accès refusé sans connexion
    console.log('Accès refusé sans authentification');
    const inconnu = await nouveauContexte();
    const pageInconnu = await inconnu.newPage();
    for (const adresse of ['/', '/reglages', '/cle']) {
        await pageInconnu.goto(URL_APPLI + adresse);
        verifier(`${adresse} renvoie vers la connexion`, chemin(pageInconnu) === '/connexion');
    }
    await pageInconnu.goto(URL_APPLI + '/installation');
    verifier('la page d’installation n’est plus accessible', chemin(pageInconnu) === '/connexion');
    const html = await pageInconnu.content();
    verifier('la page de connexion ne révèle aucune donnée', !html.includes('module') && !html.includes('URSSAF'));

    const appel = (ctx, adresse, entetes, corps = {}) => ctx.request.post(URL_APPLI + adresse, {
        headers: { 'Content-Type': 'application/json', ...entetes }, data: corps,
    });
    const memeSite = { Origin: URL_APPLI };
    for (const adresse of ['/api/modules', '/api/deconnexion', '/api/sessions/revoquer', '/api/cle/supprimer', '/api/secours/nouveau']) {
        const r = await appel(inconnu, adresse, memeSite);
        verifier(`${adresse} refusé sans connexion (code ${r.status()})`, r.status() === 401);
    }
    verifier('création de clé refusée sans autorisation',
        (await appel(inconnu, '/api/cle/options', memeSite)).status() === 403);
    verifier('requête venant d’un autre site refusée',
        (await appel(inconnu, '/api/connexion/options', { Origin: 'https://site-pirate.example' })).status() === 403);
    const cookieInvente = await nouveauContexte();
    await cookieInvente.addCookies([{ name: 'tdb', value: 'jeton-invente', url: URL_APPLI }]);
    const pageInvente = await cookieInvente.newPage();
    await pageInvente.goto(URL_APPLI + '/');
    verifier('un jeton de session inventé est refusé', chemin(pageInvente) === '/connexion');

    // Connecté, mais sans le jeton anti-falsification
    const sansCsrf = await appel(contexte, '/api/modules', memeSite, { modules: [] });
    verifier('modification refusée sans jeton anti-falsification', sansCsrf.status() === 403);
    const autreSite = await appel(contexte, '/api/deconnexion', { Origin: 'https://site-pirate.example' });
    verifier('un autre site ne peut pas me déconnecter', autreSite.status() === 403);

    // En-têtes de sécurité
    const entetes = (await page.goto(URL_APPLI + '/')).headers();
    verifier('en-têtes de sécurité présents', (entetes['content-security-policy'] || '').includes("default-src 'self'")
        && entetes['x-frame-options'] === 'DENY' && entetes['cache-control'] === 'no-store');
    const cookies = await contexte.cookies();
    const session = cookies.find((c) => c.name === 'tdb');
    verifier('cookie de session illisible par les scripts (HttpOnly)', session && session.httpOnly && session.sameSite === 'Lax');
    const jours = (session.expires - Date.now() / 1000) / 86400;
    verifier(`session de 7 jours (mesuré : ${jours.toFixed(2)} jours)`, jours > 6.9 && jours <= 7);

    // ---------------------------------------------------- Déconnexion / connexion
    console.log('Déconnexion et connexion');
    await page.goto(URL_APPLI + '/reglages');
    await page.screenshot({ path: path.join(CAPTURES, '4-reglages.png'), fullPage: true });
    await page.click('[data-action="deconnexion"]');
    await page.waitForURL(URL_APPLI + '/connexion');
    verifier('déconnexion : retour à la page de connexion', true);
    await page.goto(URL_APPLI + '/');
    verifier('après déconnexion, « Aujourd’hui » n’est plus accessible', chemin(page) === '/connexion');
    verifier('la session est supprimée du serveur', sql('SELECT COUNT(*) FROM sessions') === '0');
    await page.screenshot({ path: path.join(CAPTURES, '5-connexion.png') });

    await page.click('[data-action="connexion"]');
    await page.waitForURL(URL_APPLI + '/');
    verifier('connexion avec la clé d’accès réussie', (await page.locator('h1').innerText()) === 'Aujourd’hui');
    verifier('la disposition des modules est retrouvée après reconnexion',
        (await page.locator('.module').evaluateAll((l) => l.map((e) => e.dataset.id))).join(',') === 'ecole,agenda,pro,stage');

    // Une clé inconnue (autre appareil, jamais enregistré) est refusée
    const intrus = await nouveauContexte();
    const pageIntrus = await intrus.newPage();
    await pageIntrus.goto(URL_APPLI + '/connexion');
    const cdpIntrus = await cleVirtuelle(pageIntrus);
    const { authenticatorId } = await cdpIntrus.send('WebAuthn.addVirtualAuthenticator', {
        options: { protocol: 'ctap2', transport: 'usb', hasResidentKey: true, hasUserVerification: true, isUserVerified: true, automaticPresenceSimulation: true },
    });
    // Clé fabriquée de toutes pièces pour le même site
    const { privateKey } = await pageIntrus.evaluate(async () => {
        const paire = await crypto.subtle.generateKey({ name: 'ECDSA', namedCurve: 'P-256' }, true, ['sign']);
        const pkcs8 = await crypto.subtle.exportKey('pkcs8', paire.privateKey);
        return { privateKey: btoa(String.fromCharCode(...new Uint8Array(pkcs8))) };
    });
    await cdpIntrus.send('WebAuthn.addCredential', {
        authenticatorId,
        credential: { credentialId: Buffer.from('cle-pirate-0001').toString('base64'), isResidentCredential: true, rpId: 'localhost', privateKey, userHandle: Buffer.from('intrus').toString('base64'), signCount: 0 },
    });
    await pageIntrus.click('[data-action="connexion"]');
    await pageIntrus.locator('#message.erreur').waitFor();
    verifier('une clé d’accès non enregistrée est refusée',
        (await pageIntrus.locator('#message').innerText()).includes('Connexion refusée'));
    await pageIntrus.goto(URL_APPLI + '/');
    verifier('l’intrus reste dehors', chemin(pageIntrus) === '/connexion');

    // ------------------------------------------------------ Expiration de session
    console.log('Expiration et révocation');
    sql("UPDATE sessions SET expires_at = '2020-01-01 00:00:00'");
    await page.goto(URL_APPLI + '/');
    verifier('une session expirée renvoie vers la connexion', chemin(page) === '/connexion');
    await page.click('[data-action="connexion"]');
    await page.waitForURL(URL_APPLI + '/');

    // ---------------------------------------------- Code de secours (iPhone perdu)
    console.log('Code de secours');
    const secours = await nouveauContexte();
    const pageSecours = await secours.newPage();
    await pageSecours.goto(URL_APPLI + '/connexion');
    await pageSecours.click('.secours summary');
    await pageSecours.fill('#code', 'AAAA-AAAA-AAAA-AAAA-AAAA');
    await pageSecours.click('#form-secours button');
    await pageSecours.locator('#message.erreur').waitFor();
    verifier('mauvais code de secours refusé', (await pageSecours.locator('#message').innerText()).includes('incorrect'));

    await pageSecours.fill('#code', codeSecours.toLowerCase());
    await pageSecours.click('#form-secours button');
    await pageSecours.waitForURL(URL_APPLI + '/cle');
    await cleVirtuelle(pageSecours);
    await pageSecours.click('[data-action="creer-cle"]');
    await pageSecours.locator('#bloc-code:not([hidden])').waitFor();
    const nouveauCode = (await pageSecours.locator('#code-secours').innerText()).trim();
    verifier('bon code accepté : nouvelle clé créée et nouveau code de secours donné',
        /^([A-Z2-9]{4}-){4}[A-Z2-9]{4}$/.test(nouveauCode) && nouveauCode !== codeSecours);
    await pageSecours.check('#code-note');
    await pageSecours.click('#continuer');
    await pageSecours.waitForURL(URL_APPLI + '/');
    verifier('connecté sur le nouvel appareil', (await pageSecours.locator('h1').innerText()) === 'Aujourd’hui');

    const ancien = await appel(inconnu, '/api/secours', memeSite, { code: codeSecours });
    verifier('l’ancien code de secours ne marche plus (usage unique)', ancien.status() === 401);

    // Révocation : le nouvel appareil déconnecte l'ancien
    await pageSecours.goto(URL_APPLI + '/reglages');
    verifier('les deux appareils et les deux clés sont listés',
        (await pageSecours.locator('[data-action="revoquer"]').count()) === 1
        && (await pageSecours.locator('[data-action="supprimer-cle"]').count()) === 2);
    await Promise.all([pageSecours.waitForEvent('load'), pageSecours.click('[data-action="revoquer"]')]);
    await page.goto(URL_APPLI + '/');
    verifier('un appareil déconnecté à distance perd l’accès immédiatement', chemin(page) === '/connexion');

    // Suppression de l'ancienne clé (iPhone perdu)
    pageSecours.on('dialog', (d) => d.accept());
    await pageSecours.goto(URL_APPLI + '/reglages');
    await Promise.all([pageSecours.waitForEvent('load'), pageSecours.locator('[data-action="supprimer-cle"]').first().click()]);
    await page.goto(URL_APPLI + '/connexion');
    await page.click('[data-action="connexion"]');
    await page.locator('#message.erreur').waitFor();
    verifier('une clé d’accès supprimée ne permet plus de se connecter',
        (await page.locator('#message').innerText()).includes('Connexion refusée'));
    await pageSecours.goto(URL_APPLI + '/reglages');
    verifier('la dernière clé ne peut pas être supprimée (pas de bouton)',
        (await pageSecours.locator('[data-action="supprimer-cle"]').count()) === 0);

    // Blocage après 5 mauvais codes
    let dernier;
    for (let i = 0; i < 5; i++) {
        dernier = await appel(inconnu, '/api/secours', memeSite, { code: 'ZZZZ-ZZZZ-ZZZZ-ZZZZ-ZZZZ' });
    }
    verifier('blocage après 5 mauvais codes de secours', dernier.status() === 429);
    const bloque = await appel(inconnu, '/api/secours', memeSite, { code: nouveauCode });
    verifier('pendant le blocage, même le bon code est refusé', bloque.status() === 429);

    // -------------------------------------------- Installation sur l'écran d'accueil
    console.log('Application installable');
    const manifeste = await (await secours.request.get(URL_APPLI + '/manifest.webmanifest')).json();
    verifier('fiche d’installation : nom « Tableau de bord », plein écran',
        manifeste.short_name === 'Tableau de bord' && manifeste.display === 'standalone' && manifeste.icons.length >= 2);
    for (const icone of ['/assets/icons/icone-180.png', '/assets/icons/icone-192.png', '/assets/icons/icone-512.png']) {
        verifier(`icône ${icone} disponible`, (await secours.request.get(URL_APPLI + icone)).status() === 200);
    }
    await pageSecours.goto(URL_APPLI + '/');
    verifier('balises iPhone présentes (icône, plein écran)',
        (await pageSecours.locator('link[rel="apple-touch-icon"]').count()) === 1
        && (await pageSecours.locator('meta[name="apple-mobile-web-app-capable"]').count()) === 1);
    await pageSecours.evaluate(() => navigator.serviceWorker.ready);
    await pageSecours.reload();
    await secours.setOffline(true);
    await pageSecours.goto(URL_APPLI + '/').catch(() => {});
    verifier('sans réseau : page « Pas de connexion » au lieu d’une erreur',
        (await pageSecours.locator('h1').innerText().catch(() => '')) === 'Pas de connexion');
    await pageSecours.screenshot({ path: path.join(CAPTURES, '6-hors-ligne.png') });
    await secours.setOffline(false);

    // ------------------------------------------------- Fichiers internes protégés
    console.log('Fichiers internes inaccessibles depuis Internet');
    for (const fichier of ['/JOURNAL.md', '/src/App.php', '/config/config.php', '/vendor/autoload.php', '/composer.json',
        '/.git/config', '/.gitignore', '/tests/unit.php', '/public/index.php', '/../tableaudebord-config.php']) {
        const r = await inconnu.request.get(URL_APPLI + fichier, { maxRedirects: 0 });
        const texte = await r.text();
        verifier(`${fichier} n’est pas lisible (code ${r.status()})`,
            r.status() !== 200 && !texte.includes('app_key') && !texte.includes('namespace Tdb') && !texte.includes('Journal du projet'));
    }

    // ------------------------------------------------------------ Journal et secrets
    console.log('Journal des accès et stockage');
    const evenements = sql('SELECT GROUP_CONCAT(DISTINCT event) FROM access_log');
    verifier('le journal des accès enregistre connexions, refus et déconnexions',
        ['installation', 'connexion', 'connexion_refusee', 'deconnexion', 'code_secours_refuse', 'cle_creee']
            .every((e) => evenements.split(',').includes(e)));
    verifier('aucun code de secours en clair dans la base',
        sql(`SELECT COUNT(*) FROM settings WHERE value LIKE '%${nouveauCode.replace(/-/g, '')}%'`) === '0');
    verifier('aucune erreur JavaScript pendant le parcours principal',
        erreursConsole.filter((e) => !e.includes('401') && !e.includes('status of 4')).length === 0);
    if (erreursConsole.length) console.log('    (console : ' + erreursConsole.join(' | ') + ')');

    await navigateur.close();
    console.log(`\nRésultat : ${reussis} réussis, ${echecs.length} échoués`);
    echecs.forEach((e) => console.log('  - ' + e));
    process.exit(echecs.length === 0 ? 0 : 1);
})().catch((e) => {
    console.error('Test interrompu :', e);
    process.exit(2);
});
