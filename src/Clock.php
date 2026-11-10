<?php

declare(strict_types=1);

namespace Tdb;

/**
 * Horloge de l'application. Les tests peuvent fixer l'heure (simulation de dates).
 * La base de données stocke toujours les dates en temps universel (UTC) ;
 * l'affichage se fait en heure de Paris.
 */
final class Clock
{
    private static ?int $fixe = null;

    public static function now(): int
    {
        return self::$fixe ?? time();
    }

    public static function fixer(?int $timestamp): void
    {
        self::$fixe = $timestamp;
    }

    /** Date et heure au format de la base (UTC). */
    public static function sql(?int $timestamp = null): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp ?? self::now());
    }

    public static function depuisSql(string $date): int
    {
        return (int) strtotime($date . ' UTC');
    }
}
