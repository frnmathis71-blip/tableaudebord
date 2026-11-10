<?php

declare(strict_types=1);

namespace Tdb;

/**
 * Configuration de l'application (adresse, accès à la base, clé de chiffrement).
 * Le fichier est créé par la page d'installation, sur le serveur uniquement :
 * il n'est jamais enregistré dans le dépôt de code.
 */
final class Config
{
    public function __construct(private array $valeurs)
    {
    }

    /** Emplacements possibles, par ordre de préférence (le premier est hors du dossier de l'appli). */
    public static function chemins(): array
    {
        $racine = dirname(__DIR__);
        $env = getenv('TDB_CONFIG');

        return array_values(array_filter([
            $env !== false && $env !== '' ? $env : null,
            dirname($racine) . '/tableaudebord-config.php',
            $racine . '/config/config.php',
        ]));
    }

    public static function charger(): ?self
    {
        foreach (self::chemins() as $chemin) {
            if (is_file($chemin)) {
                $valeurs = require $chemin;
                if (is_array($valeurs)) {
                    return new self($valeurs);
                }
            }
        }

        return null;
    }

    /** Écrit le fichier de configuration au premier emplacement inscriptible. Renvoie son chemin. */
    public static function ecrire(array $valeurs): string
    {
        $contenu = "<?php\n// Fichier secret créé par l'installation. Ne pas partager, ne pas publier.\nreturn "
            . var_export($valeurs, true) . ";\n";

        foreach (self::chemins() as $chemin) {
            $dossier = dirname($chemin);
            if (!is_dir($dossier)) {
                @mkdir($dossier, 0700, true);
            }
            if (is_dir($dossier) && is_writable($dossier) && @file_put_contents($chemin, $contenu, LOCK_EX) !== false) {
                @chmod($chemin, 0600);
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($chemin, true);
                }

                return $chemin;
            }
        }

        throw new \RuntimeException("Impossible d'écrire le fichier de configuration.");
    }

    public function get(string $cle, mixed $defaut = null): mixed
    {
        return $this->valeurs[$cle] ?? $defaut;
    }

    /** Adresse de l'appli, sans barre finale. Exemple : https://dashboard.exemple.fr */
    public function origine(): string
    {
        return rtrim((string) $this->get('app_url'), '/');
    }

    public function hote(): string
    {
        return (string) parse_url($this->origine(), PHP_URL_HOST);
    }

    public function https(): bool
    {
        return str_starts_with($this->origine(), 'https://');
    }

    public function joursSession(): int
    {
        return (int) $this->get('session_days', 7);
    }

    /** Clé secrète de 32 octets (chiffrement et signatures). */
    public function cle(): string
    {
        $cle = base64_decode((string) $this->get('app_key'), true);
        if ($cle === false || strlen($cle) !== 32) {
            throw new \RuntimeException('Clé de chiffrement absente ou invalide.');
        }

        return $cle;
    }
}
