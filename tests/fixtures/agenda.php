<?php
/**
 * Faux calendrier iCloud pour les tests (données fictives), dates calculées à partir d'aujourd'hui.
 * Servi par le petit serveur PHP des tests : http://localhost:8766/agenda.php
 * - tests/.tmp/ajout.ics : événement ajouté « depuis l'iPhone » (simulation) ;
 * - tests/.tmp/panne : simule une panne d'iCloud.
 */
$tmp = dirname(__DIR__) . '/.tmp';
if (is_file($tmp . '/panne')) {
    http_response_code(503);
    exit;
}
$paris = new DateTimeZone('Europe/Paris');
$j = static fn (int $decalage, string $heure): string =>
    (new DateTimeImmutable('today', $paris))->modify("+$decalage day")->format('Ymd') . 'T' . $heure;
$d = static fn (int $decalage): string => (new DateTimeImmutable('today', $paris))->modify("+$decalage day")->format('Ymd');

$evenements = [
    ['UID:cours-anglais', "DTSTART;TZID=Europe/Paris:{$j(0, '090000')}", "DTEND;TZID=Europe/Paris:{$j(0, '103000')}", 'SUMMARY:Cours d’anglais (fictif)', 'LOCATION:Salle B12'],
    ['UID:rdv-banque', "DTSTART;TZID=Europe/Paris:{$j(0, '100000')}", "DTEND;TZID=Europe/Paris:{$j(0, '110000')}", 'SUMMARY:Rendez-vous fictif'],
    ['UID:sport', "DTSTART;TZID=Europe/Paris:{$j(0, '180000')}", "DTEND;TZID=Europe/Paris:{$j(0, '190000')}", 'SUMMARY:Sport', 'RRULE:FREQ=DAILY;COUNT=3'],
    ['UID:anniv', "DTSTART;VALUE=DATE:{$d(1)}", "DTEND;VALUE=DATE:{$d(2)}", 'SUMMARY:Anniversaire fictif'],
    ['UID:entretien', "DTSTART;TZID=Europe/Paris:{$j(2, '140000')}", "DTEND;TZID=Europe/Paris:{$j(2, '150000')}", 'SUMMARY:Entretien de stage (fictif)', 'LOCATION:Lyon'],
];
$ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Tests//FR\r\n"
    . "BEGIN:VTIMEZONE\r\nTZID:Europe/Paris\r\nEND:VTIMEZONE\r\n";
foreach ($evenements as $e) {
    $ics .= "BEGIN:VEVENT\r\n" . implode("\r\n", $e) . "\r\nEND:VEVENT\r\n";
}
if (is_file($tmp . '/ajout.ics')) {
    $ics .= str_replace(['{AUJOURDHUI}'], [$d(0)], (string) file_get_contents($tmp . '/ajout.ics'));
}
$ics .= "END:VCALENDAR\r\n";
header('Content-Type: text/calendar; charset=utf-8');
echo $ics;
