<?php

declare(strict_types=1);

namespace Tdb;

/** Petits outils pour les requêtes et les réponses web. */
final class Http
{
    public static function methode(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function chemin(): string
    {
        $chemin = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        return $chemin === '/' ? '/' : rtrim($chemin, '/');
    }

    public static function ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }

    public static function agent(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);
    }

    public static function enHttps(): bool
    {
        return (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
            || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
    }

    /** Adresse vue par le navigateur, utilisée uniquement pendant l'installation. */
    public static function origineRequete(): string
    {
        return (self::enHttps() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    /**
     * Protection contre les requêtes forgées par un autre site : toute requête qui
     * modifie quelque chose doit venir de l'appli elle-même.
     */
    public static function memeOrigine(string $origineAttendue): bool
    {
        $origine = $_SERVER['HTTP_ORIGIN'] ?? '';

        return $origine !== '' && hash_equals(rtrim($origineAttendue, '/'), rtrim($origine, '/'));
    }

    public static function corpsJson(): array
    {
        $brut = file_get_contents('php://input') ?: '';
        if (strlen($brut) > 65536) {
            return [];
        }
        $donnees = json_decode($brut, true);

        return is_array($donnees) ? $donnees : [];
    }

    public static function entetesSecurite(bool $https): void
    {
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; "
            . "script-src 'self'; connect-src 'self'; manifest-src 'self'; worker-src 'self'; "
            . "frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('Cache-Control: no-store');
        if ($https) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }

    public static function json(array $donnees, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function erreurJson(string $message, int $code = 400): never
    {
        self::json(['ok' => false, 'erreur' => $message], $code);
    }

    public static function rediriger(string $chemin): never
    {
        header('Location: ' . $chemin, true, 303);
        exit;
    }

    public static function cookie(string $nom, string $valeur, int $expire, bool $https): void
    {
        if (!headers_sent()) {
            setcookie($nom, $valeur, [
                'expires' => $expire,
                'path' => '/',
                'secure' => $https,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        if ($valeur === '') {
            unset($_COOKIE[$nom]);
        } else {
            $_COOKIE[$nom] = $valeur;
        }
    }
}
