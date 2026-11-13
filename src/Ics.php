<?php

declare(strict_types=1);

namespace Tdb;

use Sabre\VObject\DateTimeParser;
use Sabre\VObject\Reader;

/**
 * Lecture d'un fichier d'agenda au format iCalendar (.ics), celui des liens de partage iCloud.
 * Les répétitions (« tous les lundis »), exceptions et fuseaux horaires sont gérés par la
 * bibliothèque sabre/vobject. Le résultat est une liste d'événements « à plat ».
 */
final class Ics
{
    /**
     * @return list<array{uid: string, titre: string, lieu: string, journee: bool,
     *     debut: int, fin: int, jour_debut: ?string, jour_fin: ?string}>
     *  debut/fin : horodatages (UTC) ; pour une journée entière, jour_debut/jour_fin (AAAA-MM-JJ, fin exclue).
     */
    public static function evenements(string $contenu, int $depuis, int $jusqua): array
    {
        $paris = new \DateTimeZone('Europe/Paris');
        $calendrier = Reader::read($contenu, Reader::OPTION_FORGIVING);
        $etendu = $calendrier->expand(
            (new \DateTimeImmutable('@' . $depuis)),
            (new \DateTimeImmutable('@' . $jusqua)),
            $paris
        );

        $resultat = [];
        foreach ($etendu->select('VEVENT') as $ev) {
            if (!isset($ev->DTSTART) || strtoupper((string) ($ev->STATUS ?? '')) === 'CANCELLED') {
                continue;
            }
            $journee = !$ev->DTSTART->hasTime();
            $debut = \DateTimeImmutable::createFromInterface($ev->DTSTART->getDateTime($paris));
            if (isset($ev->DTEND)) {
                $fin = \DateTimeImmutable::createFromInterface($ev->DTEND->getDateTime($paris));
            } elseif (isset($ev->DURATION)) {
                $fin = $debut->add(DateTimeParser::parseDuration((string) $ev->DURATION));
            } else {
                $fin = $journee ? $debut->modify('+1 day') : $debut;
            }
            if ($fin < $debut) {
                $fin = $debut;
            }
            if ($journee && $fin == $debut) {
                $fin = $debut->modify('+1 day');
            }

            $resultat[] = [
                'uid' => mb_substr((string) ($ev->UID ?? ''), 0, 250),
                'titre' => mb_substr(trim((string) ($ev->SUMMARY ?? '')), 0, 250) ?: 'Sans titre',
                'lieu' => mb_substr(trim((string) ($ev->LOCATION ?? '')), 0, 250),
                'journee' => $journee,
                'debut' => $debut->getTimestamp(),
                'fin' => $fin->getTimestamp(),
                'jour_debut' => $journee ? $debut->format('Y-m-d') : null,
                'jour_fin' => $journee ? $fin->format('Y-m-d') : null,
            ];
        }
        usort($resultat, static fn (array $a, array $b): int => [$a['debut'], $a['titre']] <=> [$b['debut'], $b['titre']]);

        return $resultat;
    }
}
