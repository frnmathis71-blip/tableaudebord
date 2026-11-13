<?php

declare(strict_types=1);

namespace Tdb;

/**
 * Calculs sur l'agenda, en heure de Paris : semaine à venir, conflits et temps libre.
 * Règles décidées avec Mathis (M2) :
 *  - conflit = deux événements à heure fixe qui se chevauchent (journées entières ignorées) ;
 *  - temps libre = plage 8 h – 20 h, tous les jours, moins les événements à heure fixe.
 */
final class Planning
{
    public const HEURE_DEBUT = 8;
    public const HEURE_FIN = 20;

    private static function paris(): \DateTimeZone
    {
        return new \DateTimeZone('Europe/Paris');
    }

    /** Minuit (heure de Paris) du jour contenant $timestamp. */
    public static function debutJour(int $timestamp): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('@' . $timestamp))->setTimezone(self::paris())->setTime(0, 0);
    }

    /** Marque les événements en conflit (clé 'conflit' => true). */
    public static function marquerConflits(array $evenements): array
    {
        $n = count($evenements);
        foreach ($evenements as $i => $e) {
            $evenements[$i]['conflit'] = false;
        }
        for ($i = 0; $i < $n; $i++) {
            $a = $evenements[$i];
            if ($a['journee'] || $a['fin'] <= $a['debut']) {
                continue;
            }
            for ($j = $i + 1; $j < $n; $j++) {
                $b = $evenements[$j];
                if ($b['journee'] || $b['fin'] <= $b['debut']) {
                    continue;
                }
                if ($a['debut'] < $b['fin'] && $b['debut'] < $a['fin']) {
                    $evenements[$i]['conflit'] = true;
                    $evenements[$j]['conflit'] = true;
                }
            }
        }

        return $evenements;
    }

    /** Événements qui touchent le jour donné (minuit de Paris). */
    public static function duJour(array $evenements, \DateTimeImmutable $jour): array
    {
        $debut = $jour->getTimestamp();
        $fin = $jour->modify('+1 day')->getTimestamp();
        $date = $jour->format('Y-m-d');

        return array_values(array_filter($evenements, static function (array $e) use ($debut, $fin, $date): bool {
            if ($e['journee']) {
                return $e['jour_debut'] <= $date && $date < $e['jour_fin'];
            }
            if ($e['fin'] === $e['debut']) {
                return $e['debut'] >= $debut && $e['debut'] < $fin;
            }

            return $e['debut'] < $fin && $e['fin'] > $debut;
        }));
    }

    /**
     * Temps libre (en minutes) du jour, entre 8 h et 20 h.
     * Si $maintenant tombe dans ce jour, seul le temps libre restant est compté.
     */
    public static function tempsLibre(array $evenementsDuJour, \DateTimeImmutable $jour, int $maintenant): int
    {
        $plageDebut = $jour->setTime(self::HEURE_DEBUT, 0)->getTimestamp();
        $plageFin = $jour->setTime(self::HEURE_FIN, 0)->getTimestamp();
        $plageDebut = max($plageDebut, $maintenant);
        if ($plageDebut >= $plageFin) {
            return 0;
        }

        $occupe = [];
        foreach ($evenementsDuJour as $e) {
            if ($e['journee']) {
                continue;
            }
            $d = max($e['debut'], $plageDebut);
            $f = min($e['fin'], $plageFin);
            if ($f > $d) {
                $occupe[] = [$d, $f];
            }
        }
        usort($occupe, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $total = 0;
        $curseur = $plageDebut;
        foreach ($occupe as [$d, $f]) {
            if ($d > $curseur) {
                $total += $d - $curseur;
            }
            $curseur = max($curseur, $f);
        }
        $total += max(0, $plageFin - $curseur);

        return intdiv($total, 60);
    }

    /**
     * Les 7 jours à venir (aujourd'hui compris), avec événements, conflits et temps libre.
     * @return list<array{jour: \DateTimeImmutable, evenements: array, libre: int, conflits: int}>
     */
    public static function semaine(array $evenements, int $maintenant): array
    {
        $evenements = self::marquerConflits($evenements);
        $jour = self::debutJour($maintenant);
        $semaine = [];
        for ($i = 0; $i < 7; $i++) {
            $duJour = self::duJour($evenements, $jour);
            usort($duJour, static fn (array $a, array $b): int =>
                [$b['journee'], $a['debut'], $a['titre']] <=> [$a['journee'], $b['debut'], $b['titre']]);
            $semaine[] = [
                'jour' => $jour,
                'evenements' => $duJour,
                'libre' => self::tempsLibre($duJour, $jour, $maintenant),
                'conflits' => count(array_filter($duJour, static fn (array $e): bool => $e['conflit'])),
            ];
            $jour = $jour->modify('+1 day');
        }

        return $semaine;
    }

    /** Exemple : 270 → « 4 h 30 », 45 → « 45 min ». */
    public static function duree(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' min';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $h . ' h' . ($m > 0 ? ' ' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) : '');
    }

    /** Heure de Paris, exemple : « 08:30 ». */
    public static function heure(int $timestamp): string
    {
        return (new \DateTimeImmutable('@' . $timestamp))->setTimezone(self::paris())->format('H:i');
    }
}
