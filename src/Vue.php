<?php

declare(strict_types=1);

namespace Tdb;

/** Affichage des pages et petits outils de mise en forme. */
final class Vue
{
    private const JOURS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    private const MOIS = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août',
        'septembre', 'octobre', 'novembre', 'décembre'];
    private const MOIS_COURTS = [1 => 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août',
        'sept.', 'oct.', 'nov.', 'déc.'];

    /** Protège un texte avant de l'afficher dans la page. */
    public static function e(mixed $texte): string
    {
        return htmlspecialchars((string) $texte, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function paris(int $timestamp): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('@' . $timestamp))->setTimezone(new \DateTimeZone('Europe/Paris'));
    }

    /** Exemple : « mardi 10 novembre ». */
    public static function dateLongue(int $timestamp): string
    {
        $d = self::paris($timestamp);

        return self::JOURS[(int) $d->format('w')] . ' ' . ($d->format('j') === '1' ? '1er' : $d->format('j'))
            . ' ' . self::MOIS[(int) $d->format('n')];
    }

    /** Exemple : « 10 nov. 2026 à 08:05 » (heure de Paris), à partir d'une date de la base. */
    public static function dateCourte(?string $sql): string
    {
        if ($sql === null || $sql === '') {
            return 'jamais';
        }
        $d = self::paris(Clock::depuisSql($sql));

        return $d->format('j') . ' ' . self::MOIS_COURTS[(int) $d->format('n')] . ' '
            . $d->format('Y') . ' à ' . $d->format('H:i');
    }

    /** Nom simple de l'appareil à partir de la signature du navigateur. */
    public static function appareil(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Macintosh') => 'Mac',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'PC Windows',
            default => 'Autre appareil',
        };
    }

    /** Adresse d'un fichier statique avec un numéro de version (évite les vieux fichiers en mémoire). */
    public static function fichier(string $chemin): string
    {
        $reel = dirname(__DIR__) . '/public' . $chemin;

        return $chemin . '?v=' . (is_file($reel) ? filemtime($reel) : 0);
    }

    public static function icone(string $nom): string
    {
        $traces = [
            'agenda' => '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M3 10h18M8 3v4M16 3v4"/>',
            'ecole' => '<path d="M2 9l10-5 10 5-10 5z"/><path d="M6 11.5V16c0 1.5 3 3 6 3s6-1.5 6-3v-4.5"/>',
            'pro' => '<rect x="3" y="7" width="18" height="13" rx="3"/><path d="M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2M3 13h18"/>',
            'stage' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.5-4.5"/>',
            'jour' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5L19 19M5 19l1.5-1.5M17.5 6.5L19 5"/>',
            'reglages' => '<path d="M4 6h10M18 6h2M4 12h2M10 12h10M4 18h10M18 18h2"/><circle cx="16" cy="6" r="2"/><circle cx="8" cy="12" r="2"/><circle cx="16" cy="18" r="2"/>',
            'haut' => '<path d="M12 19V5M5 12l7-7 7 7"/>',
            'bas' => '<path d="M12 5v14M5 12l7 7 7-7"/>',
            'oeil' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
            'cle' => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M16 7l3 3"/>',
        ];

        return '<svg class="icone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" '
            . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($traces[$nom] ?? '') . '</svg>';
    }

    /** Affiche une page complète (habillage + contenu). */
    public static function page(string $vue, array $donnees = [], int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
        extract($donnees, EXTR_SKIP);
        ob_start();
        require __DIR__ . '/views/' . $vue . '.php';
        $contenu = ob_get_clean();
        require __DIR__ . '/views/habillage.php';
        exit;
    }
}
