<?php

declare(strict_types=1);

/**
 * Tests automatiques des briques internes (sans navigateur).
 * Lancement : php tests/unit.php
 * Ils utilisent une base temporaire en mémoire et des données fictives.
 */

use Tdb\Auth;
use Tdb\Clock;
use Tdb\Config;
use Tdb\Crypto;
use Tdb\Db;
use Tdb\Modules;
use Tdb\Schema;
use Tdb\Vue;

require dirname(__DIR__) . '/vendor/autoload.php';

$reussis = 0;
$echecs = 0;

function verifier(string $nom, bool $condition): void
{
    global $reussis, $echecs;
    if ($condition) {
        $reussis++;
        echo "  réussi  $nom\n";
    } else {
        $echecs++;
        echo "  ÉCHEC   $nom\n";
    }
}

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)';

$config = new Config([
    'app_url' => 'https://dashboard.exemple.fr/',
    'app_key' => base64_encode(random_bytes(32)),
    'session_days' => 7,
]);
$db = new Db(new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]));

echo "Base de données\n";
verifier('la base vide est en version 0', Schema::version($db) === 0);
Schema::migrer($db);
Schema::migrer($db);
verifier('la création des tables peut être relancée sans erreur', Schema::version($db) === Schema::VERSION);
$db->definirReglage('essai', 'a');
$db->definirReglage('essai', 'b');
verifier('un réglage est remplacé, pas dupliqué', $db->reglage('essai') === 'b'
    && (int) $db->valeur("SELECT COUNT(*) FROM settings WHERE name = 'essai'") === 1);

echo "Configuration\n";
verifier('adresse sans barre finale', $config->origine() === 'https://dashboard.exemple.fr');
verifier('nom de domaine extrait', $config->hote() === 'dashboard.exemple.fr');
verifier('https détecté', $config->https());

echo "Chiffrement\n";
$crypto = new Crypto($config->cle());
$secret = 'mot de passe d’application fictif : abcd-efgh';
$chiffre = $crypto->chiffrer($secret);
verifier('le texte chiffré ne contient pas le texte clair', !str_contains($chiffre, 'fictif'));
verifier('déchiffrement correct', $crypto->dechiffrer($chiffre) === $secret);
verifier('deux chiffrements du même texte sont différents', $crypto->chiffrer($secret) !== $chiffre);
$modifie = substr($chiffre, 0, -2) . (substr($chiffre, -2) === 'AA' ? 'BB' : 'AA');
verifier('une donnée modifiée est rejetée', $crypto->dechiffrer($modifie) === null);
verifier('une autre clé ne déchiffre pas', (new Crypto(random_bytes(32)))->dechiffrer($chiffre) === null);

echo "Sessions (durée de 7 jours)\n";
$depart = strtotime('2026-11-10 08:00:00 UTC');
Clock::fixer($depart);
$auth = new Auth($db, $config);
verifier('personne n’est connecté au départ', !$auth->connecte());
$auth->ouvrirSession();
verifier('connecté après ouverture de session', $auth->connecte());
$jeton = $_COOKIE['__Host-tdb'];
verifier('le jeton n’est pas stocké en clair dans la base',
    (int) $db->valeur('SELECT COUNT(*) FROM sessions WHERE token_hash = ?', [$jeton]) === 0);
verifier('jeton anti-falsification présent', strlen($auth->csrf()) === 64);

Clock::fixer($depart + 6 * 86400 + 23 * 3600);
verifier('toujours connecté après 6 jours et 23 heures', (new Auth($db, $config))->connecte());
Clock::fixer($depart + 7 * 86400 + 60);
verifier('déconnecté après 7 jours', !(new Auth($db, $config))->connecte());

Clock::fixer($depart);
$_COOKIE['__Host-tdb'] = 'jeton-invente';
verifier('un jeton inventé est refusé', !(new Auth($db, $config))->connecte());
$_COOKIE['__Host-tdb'] = $jeton;
$auth = new Auth($db, $config);
$auth->fermerSession();
$_COOKIE['__Host-tdb'] = $jeton;
verifier('après déconnexion, l’ancien jeton ne marche plus', !(new Auth($db, $config))->connecte());
unset($_COOKIE['__Host-tdb']);

echo "Jetons temporaires\n";
$auth = new Auth($db, $config);
$auth->creerTicket('connexion', ['defi' => 'abc'], 300);
verifier('lecture sans consommer', ($auth->ticket('connexion', false)['defi'] ?? '') === 'abc');
verifier('lecture en consommant', ($auth->ticket('connexion', true)['defi'] ?? '') === 'abc');
verifier('usage unique : deuxième lecture refusée', $auth->ticket('connexion', true) === null);
$auth->creerTicket('creation', ['defi' => 'xyz'], 300);
Clock::fixer($depart + 301);
verifier('jeton expiré après 5 minutes', $auth->ticket('creation', false) === null);
Clock::fixer($depart);
$auth->creerTicket('inscription', ['motif' => 'secours'], 900);
verifier('un jeton ne sert que pour son usage prévu', $auth->ticket('connexion', false) === null);

echo "Code de secours\n";
$code = $auth->nouveauCodeSecours();
verifier('format XXXX-XXXX-XXXX-XXXX-XXXX', preg_match('/^([A-Z2-9]{4}-){4}[A-Z2-9]{4}$/', $code) === 1);
verifier('le code n’est pas stocké en clair', !str_contains((string) $db->reglage('recovery_hash'), str_replace('-', '', $code)));
verifier('bon code accepté', $auth->codeSecoursValide($code));
verifier('accepté aussi en minuscules et sans tirets', $auth->codeSecoursValide(strtolower(str_replace('-', ' ', $code))));
verifier('mauvais code refusé', !$auth->codeSecoursValide('AAAA-AAAA-AAAA-AAAA-AAAA'));
verifier('code vide refusé', !$auth->codeSecoursValide(''));
$nouveau = $auth->nouveauCodeSecours();
verifier('un nouveau code annule l’ancien', !$auth->codeSecoursValide($code) && $auth->codeSecoursValide($nouveau));

echo "Limitation des tentatives\n";
for ($i = 0; $i < 4; $i++) {
    $auth->noterEchec('secours');
}
verifier('4 échecs : encore autorisé', !$auth->tropDeTentatives('secours', 5));
$auth->noterEchec('secours');
verifier('5 échecs : bloqué', $auth->tropDeTentatives('secours', 5));
Clock::fixer($depart + 16 * 60);
verifier('débloqué après 15 minutes', !$auth->tropDeTentatives('secours', 5));
Clock::fixer($depart);

echo "Modules déplaçables\n";
$modules = new Modules($db);
$ids = static fn (array $l): array => array_column($l, 'id');
verifier('ordre par défaut', $ids($modules->disposition()) === ['agenda', 'ecole', 'pro', 'stage']);
verifier('nouvel ordre accepté', $modules->enregistrer([
    ['id' => 'stage', 'visible' => true], ['id' => 'agenda', 'visible' => false],
    ['id' => 'pro', 'visible' => true], ['id' => 'ecole', 'visible' => true],
]));
$disposition = $modules->disposition();
verifier('ordre conservé', $ids($disposition) === ['stage', 'agenda', 'pro', 'ecole']);
verifier('module masqué conservé', $disposition[1]['visible'] === false && $disposition[0]['visible'] === true);
verifier('module inconnu refusé', !$modules->enregistrer([['id' => 'pirate'], ['id' => 'agenda'], ['id' => 'pro'], ['id' => 'ecole']]));
verifier('doublon refusé', !$modules->enregistrer([['id' => 'agenda'], ['id' => 'agenda'], ['id' => 'pro'], ['id' => 'ecole']]));
verifier('liste incomplète refusée', !$modules->enregistrer([['id' => 'agenda']]));
verifier('donnée invalide refusée', !$modules->enregistrer('nimporte quoi'));
verifier('un refus ne change rien', $ids($modules->disposition()) === ['stage', 'agenda', 'pro', 'ecole']);

echo "Dates en heure de Paris\n";
verifier('date longue', Vue::dateLongue(strtotime('2026-11-10 08:00:00 UTC')) === 'mardi 10 novembre');
verifier('23h30 UTC est déjà le lendemain à Paris', Vue::dateLongue(strtotime('2026-11-10 23:30:00 UTC')) === 'mercredi 11 novembre');
verifier('premier du mois', Vue::dateLongue(strtotime('2027-01-01 12:00:00 UTC')) === 'vendredi 1er janvier');
verifier('heure d’hiver (UTC+1)', Vue::dateCourte('2026-11-10 07:05:00') === '10 nov. 2026 à 08:05');
verifier('heure d’été (UTC+2)', Vue::dateCourte('2026-07-31 07:05:00') === '31 juil. 2026 à 09:05');
verifier('date absente', Vue::dateCourte(null) === 'jamais');

echo "Affichage\n";
verifier('les caractères dangereux sont neutralisés', Vue::e('<script>"x"</script>') === '&lt;script&gt;&quot;x&quot;&lt;/script&gt;');
verifier('appareil reconnu', Vue::appareil($_SERVER['HTTP_USER_AGENT']) === 'iPhone');

Clock::fixer(null);
echo "\nRésultat : $reussis réussis, $echecs échoués\n";
exit($echecs === 0 ? 0 : 1);
