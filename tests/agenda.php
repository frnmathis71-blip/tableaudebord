<?php

declare(strict_types=1);

/**
 * Tests automatiques du module Agenda (M2), avec des calendriers fictifs.
 * Lancement : php tests/agenda.php
 */

use Tdb\Agenda;
use Tdb\Clock;
use Tdb\Config;
use Tdb\Db;
use Tdb\Ics;
use Tdb\Planning;
use Tdb\Schema;

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

function ics(string ...$evenements): string
{
    $corps = '';
    foreach ($evenements as $e) {
        $corps .= "BEGIN:VEVENT\r\n" . str_replace("\n", "\r\n", trim($e)) . "\r\nEND:VEVENT\r\n";
    }

    return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Tests//FR\r\n$corps" . "END:VCALENDAR\r\n";
}

/** Horodatage d'une heure de Paris, exemple : t('2026-11-13 09:00'). */
function t(string $heureParis): int
{
    return (new DateTimeImmutable($heureParis, new DateTimeZone('Europe/Paris')))->getTimestamp();
}

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$debut = t('2026-11-01 00:00');
$fin = t('2026-12-01 00:00');

echo "Lecture des fichiers d'agenda\n";
$ev = Ics::evenements(ics("
UID:a
DTSTART;TZID=Europe/Paris:20261113T090000
DTEND;TZID=Europe/Paris:20261113T100000
SUMMARY:Cours
LOCATION:Salle 3"), $debut, $fin);
verifier('un événement simple est lu', count($ev) === 1 && $ev[0]['titre'] === 'Cours' && $ev[0]['lieu'] === 'Salle 3');
verifier('heure de Paris respectée (9 h à Paris = 8 h UTC en hiver)', $ev[0]['debut'] === strtotime('2026-11-13 08:00:00 UTC'));

$ev = Ics::evenements(ics("
UID:ete
DTSTART;TZID=Europe/Paris:20260715T090000
DTEND;TZID=Europe/Paris:20260715T100000
SUMMARY:Été"), t('2026-07-01'), t('2026-08-01'));
verifier('heure d’été (9 h à Paris = 7 h UTC)', $ev[0]['debut'] === strtotime('2026-07-15 07:00:00 UTC'));

$ev = Ics::evenements(ics("
UID:utc
DTSTART:20261113T170000Z
DTEND:20261113T180000Z
SUMMARY:En UTC"), $debut, $fin);
verifier('événement noté en UTC converti en heure de Paris (18:00)', Planning::heure($ev[0]['debut']) === '18:00');

$ev = Ics::evenements(ics("
UID:ny
DTSTART;TZID=America/New_York:20261113T090000
DTEND;TZID=America/New_York:20261113T100000
SUMMARY:Visio New York"), $debut, $fin);
verifier('événement créé dans un autre fuseau (9 h à New York = 15 h à Paris)', Planning::heure($ev[0]['debut']) === '15:00');

$ev = Ics::evenements(ics("
UID:rep
DTSTART;TZID=Europe/Paris:20261109T080000
DTEND;TZID=Europe/Paris:20261109T090000
RRULE:FREQ=WEEKLY;BYDAY=MO,WE;COUNT=6
EXDATE;TZID=Europe/Paris:20261111T080000
SUMMARY:Répété"), $debut, $fin);
verifier('répétition « lundi et mercredi » avec une date supprimée : 5 occurrences', count($ev) === 5);
verifier('les occurrences gardent 8 h à Paris', array_unique(array_map(static fn ($e) => Planning::heure($e['debut']), $ev)) === ['08:00']);

$ev = Ics::evenements(ics("
UID:dst
DTSTART;TZID=Europe/Paris:20261023T090000
DTEND;TZID=Europe/Paris:20261023T100000
RRULE:FREQ=DAILY;COUNT=4
SUMMARY:Passage à l’heure d’hiver"), t('2026-10-20'), t('2026-10-30'));
verifier('répétition à cheval sur le changement d’heure : toujours 9 h à Paris',
    array_unique(array_map(static fn ($e) => Planning::heure($e['debut']), $ev)) === ['09:00']);

$ev = Ics::evenements(ics("
UID:modif
DTSTART;TZID=Europe/Paris:20261116T100000
DTEND;TZID=Europe/Paris:20261116T110000
RRULE:FREQ=DAILY;COUNT=2
SUMMARY:Réunion", "
UID:modif
RECURRENCE-ID;TZID=Europe/Paris:20261117T100000
DTSTART;TZID=Europe/Paris:20261117T140000
DTEND;TZID=Europe/Paris:20261117T150000
SUMMARY:Réunion déplacée"), $debut, $fin);
verifier('occurrence déplacée prise en compte', count($ev) === 2 && $ev[1]['titre'] === 'Réunion déplacée' && Planning::heure($ev[1]['debut']) === '14:00');

$ev = Ics::evenements(ics("
UID:jour
DTSTART;VALUE=DATE:20261113
DTEND;VALUE=DATE:20261115
SUMMARY:Week-end"), $debut, $fin);
verifier('événement « toute la journée » sur 2 jours', $ev[0]['journee'] && $ev[0]['jour_debut'] === '2026-11-13' && $ev[0]['jour_fin'] === '2026-11-15');

$ev = Ics::evenements(ics("
UID:annule
DTSTART;TZID=Europe/Paris:20261113T090000
DTEND;TZID=Europe/Paris:20261113T100000
STATUS:CANCELLED
SUMMARY:Annulé", "
UID:duree
DTSTART;TZID=Europe/Paris:20261113T120000
DURATION:PT1H30M
SUMMARY:Avec durée"), $debut, $fin);
verifier('événement annulé ignoré', count($ev) === 1);
verifier('fin calculée à partir de la durée (12:00 → 13:30)', Planning::heure($ev[0]['fin']) === '13:30');

$ev = Ics::evenements(ics("
UID:hors
DTSTART;TZID=Europe/Paris:20270301T090000
DTEND;TZID=Europe/Paris:20270301T100000
SUMMARY:Lointain"), $debut, $fin);
verifier('événement hors période ignoré', $ev === []);

$malveillant = Ics::evenements(ics("
UID:x
DTSTART;TZID=Europe/Paris:20261113T090000
DTEND;TZID=Europe/Paris:20261113T100000
SUMMARY:<script>alert(1)</script>"), $debut, $fin);
verifier('un titre contenant du code est gardé tel quel (neutralisé à l’affichage)', $malveillant[0]['titre'] === '<script>alert(1)</script>');

echo "Conflits\n";
$e = static fn (string $d, string $f, string $titre = 'x', bool $journee = false): array => [
    'titre' => $titre, 'lieu' => '', 'journee' => $journee, 'debut' => t($d), 'fin' => t($f),
    'jour_debut' => $journee ? substr($d, 0, 10) : null, 'jour_fin' => $journee ? substr($f, 0, 10) : null,
];
$c = Planning::marquerConflits([
    $e('2026-11-13 09:00', '2026-11-13 10:30', 'A'),
    $e('2026-11-13 10:00', '2026-11-13 11:00', 'B'),
    $e('2026-11-13 11:00', '2026-11-13 12:00', 'C'),
    $e('2026-11-13', '2026-11-14', 'Journée', true),
]);
verifier('deux événements qui se chevauchent sont en conflit', $c[0]['conflit'] && $c[1]['conflit']);
verifier('un événement qui commence pile à la fin du précédent n’est pas en conflit', !$c[2]['conflit']);
verifier('les événements « toute la journée » sont ignorés', !$c[3]['conflit']);

echo "Temps libre (8 h – 20 h)\n";
$jour = Planning::debutJour(t('2026-11-13 12:00'));
verifier('journée vide : 12 h de libre', Planning::tempsLibre([], $jour, t('2026-11-13 00:00')) === 720);
verifier('événements qui se chevauchent comptés une seule fois (9 h–11 h occupé → 10 h libre)',
    Planning::tempsLibre(array_slice($c, 0, 2), $jour, t('2026-11-13 00:00')) === 600);
verifier('événement avant 8 h et après 20 h sans effet',
    Planning::tempsLibre([$e('2026-11-13 06:00', '2026-11-13 08:00'), $e('2026-11-13 20:00', '2026-11-13 23:00')], $jour, t('2026-11-13 00:00')) === 720);
verifier('événement « toute la journée » ne réduit pas le temps libre',
    Planning::tempsLibre([$e('2026-11-13', '2026-11-14', 'J', true)], $jour, t('2026-11-13 00:00')) === 720);
verifier('aujourd’hui à 15 h : seul le temps restant compte (5 h)', Planning::tempsLibre([], $jour, t('2026-11-13 15:00')) === 300);
verifier('après 20 h : 0 min', Planning::tempsLibre([], $jour, t('2026-11-13 21:00')) === 0);
verifier('affichage des durées', Planning::duree(270) === '4 h 30' && Planning::duree(45) === '45 min' && Planning::duree(120) === '2 h' && Planning::duree(65) === '1 h 05');

echo "Semaine à venir\n";
$semaine = Planning::semaine([
    $e('2026-11-13 09:00', '2026-11-13 10:30', 'A'),
    $e('2026-11-13 10:00', '2026-11-13 11:00', 'B'),
    $e('2026-11-14', '2026-11-16', 'Week-end', true),
    $e('2026-11-13 23:30', '2026-11-14 00:30', 'Soirée'),
    $e('2026-11-20 09:00', '2026-11-20 10:00', 'Trop loin'),
], t('2026-11-13 07:00'));
verifier('7 jours, à partir d’aujourd’hui', count($semaine) === 7 && $semaine[0]['jour']->format('Y-m-d') === '2026-11-13' && $semaine[6]['jour']->format('Y-m-d') === '2026-11-19');
verifier('2 conflits aujourd’hui', $semaine[0]['conflits'] === 2);
verifier('événement « toute la journée » sur 2 jours affiché les deux jours', count($semaine[1]['evenements']) === 2 && count($semaine[2]['evenements']) === 1);
verifier('événement à cheval sur minuit affiché les deux jours', in_array('Soirée', array_column($semaine[1]['evenements'], 'titre'), true));
verifier('« toute la journée » en tête de liste', $semaine[1]['evenements'][0]['titre'] === 'Week-end');
verifier('événement du 8e jour exclu', !in_array('Trop loin', array_merge(...array_map(static fn ($j) => array_column($j['evenements'], 'titre'), $semaine)), true));
$changement = Planning::semaine([], t('2026-10-24 12:00'));
verifier('le jour du changement d’heure (25 octobre) est bien un seul jour', $changement[1]['jour']->format('Y-m-d H:i') === '2026-10-25 00:00' && $changement[2]['jour']->format('Y-m-d H:i') === '2026-10-26 00:00');

echo "Calendriers et mises à jour\n";
$config = new Config(['app_url' => 'https://dashboard.exemple.fr', 'app_key' => base64_encode(random_bytes(32))]);
$db = new Db(new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]));
Schema::migrer($db);
$agenda = new Agenda($db, $config);

$lienFictif = 'webcal://p42-caldav.icloud.com/published/2/LIENFICTIF123';
verifier('lien webcal iCloud accepté et converti en https', $agenda->lienValide($lienFictif) === 'https://p42-caldav.icloud.com/published/2/LIENFICTIF123');
verifier('lien vers un autre site refusé', $agenda->lienValide('https://exemple.fr/agenda.ics') === null);
verifier('faux domaine imitant iCloud refusé', $agenda->lienValide('https://icloud.com.pirate.fr/x.ics') === null);
verifier('lien non chiffré (http) refusé', $agenda->lienValide('http://p42-caldav.icloud.com/x') === null);
verifier('lien avec identifiants refusé', $agenda->lienValide('https://moi:secret@p42-caldav.icloud.com/x') === null);
verifier('texte quelconque refusé', $agenda->lienValide('bonjour') === null);

Clock::fixer(t('2026-11-13 07:00'));
$contenu = ics("
UID:a
DTSTART;TZID=Europe/Paris:20261113T090000
DTEND;TZID=Europe/Paris:20261113T100000
SUMMARY:Cours fictif");
$telechargements = 0;
$agenda->remplacerTelechargement(static function (string $lien) use (&$contenu, &$telechargements): string {
    $telechargements++;
    if ($contenu === 'PANNE') {
        throw new RuntimeException('iCloud ne répond pas pour le moment.');
    }

    return $contenu;
});
$id = $agenda->ajouter('Perso', (string) $agenda->lienValide($lienFictif));
verifier('le lien est stocké chiffré', !str_contains((string) $db->valeur('SELECT url_enc FROM calendars'), 'LIENFICTIF'));

$r = $agenda->synchroniser();
verifier('première lecture : 1 événement enregistré', $r['mis_a_jour'] === 1 && $r['change'] && count($agenda->evenements()) === 1);
verifier('la lecture est inscrite au journal des accès', (int) $db->valeur("SELECT COUNT(*) FROM access_log WHERE event = 'source_lue'") === 1);

Clock::fixer(t('2026-11-13 07:01'));
$r = $agenda->synchroniser(Agenda::DELAI_OUVERTURE);
verifier('ouverture rapprochée (1 min) : pas de nouveau téléchargement', $telechargements === 1 && $r['mis_a_jour'] === 0);

Clock::fixer(t('2026-11-13 07:05'));
$r = $agenda->synchroniser(Agenda::DELAI_OUVERTURE);
verifier('rien n’a changé : la page n’a pas besoin d’être rechargée', $r['mis_a_jour'] === 1 && !$r['change']);

$contenu = ics("
UID:a
DTSTART;TZID=Europe/Paris:20261113T090000
DTEND;TZID=Europe/Paris:20261113T100000
SUMMARY:Cours fictif", "
UID:b
DTSTART;TZID=Europe/Paris:20261113T150000
DTEND;TZID=Europe/Paris:20261113T160000
SUMMARY:Ajouté sur l’iPhone");
Clock::fixer(t('2026-11-13 07:09'));
$r = $agenda->synchroniser(Agenda::DELAI_OUVERTURE);
verifier('événement ajouté sur l’iPhone : détecté à l’ouverture suivante', $r['change'] && count($agenda->evenements()) === 2);

$contenu = 'PANNE';
Clock::fixer(t('2026-11-13 08:00'));
$r = $agenda->synchroniser();
$etat = $agenda->etat();
verifier('iCloud en panne : erreur signalée', $r['erreurs'] === 1 && $etat['erreurs'] !== []);
verifier('mode dégradé : les derniers événements restent affichés', count($agenda->evenements()) === 2);
verifier('mode dégradé : date de dernière mise à jour conservée', $etat['derniere_maj'] === Clock::sql(t('2026-11-13 07:09')));

$contenu = '<html>Page introuvable</html>';
Clock::fixer(t('2026-11-13 09:00'));
$agenda->synchroniser();
verifier('réponse qui n’est pas un agenda : refusée sans effacer les événements',
    count($agenda->evenements()) === 2 && str_contains((string) $agenda->etat()['erreurs'][0]['last_error'], 'pas un agenda'));

$contenu = ics("
UID:a
DTSTART;TZID=Europe/Paris:20261113T090000
DTEND;TZID=Europe/Paris:20261113T100000
SUMMARY:Cours fictif");
Clock::fixer(t('2026-11-13 10:00'));
$agenda->synchroniser();
verifier('retour d’iCloud : erreur effacée et événement supprimé sur l’iPhone retiré',
    $agenda->etat()['erreurs'] === [] && count($agenda->evenements()) === 1);

$agenda->supprimer($id);
verifier('retirer un calendrier efface ses événements', $agenda->calendriers() === [] && $agenda->evenements() === []);

Clock::fixer(null);
echo "\nRésultat : $reussis réussis, $echecs échoués\n";
exit($echecs === 0 ? 0 : 1);
