<?php

declare(strict_types=1);

namespace Tdb;

/**
 * Chiffrement des données sensibles avant stockage (AES-256-GCM).
 * Servira à partir de M2 pour les identifiants des sources (agenda, etc.).
 */
final class Crypto
{
    public function __construct(private string $cle)
    {
    }

    public function chiffrer(string $clair): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $chiffre = openssl_encrypt($clair, 'aes-256-gcm', $this->cle, OPENSSL_RAW_DATA, $iv, $tag);
        if ($chiffre === false) {
            throw new \RuntimeException('Chiffrement impossible.');
        }

        return 'v1:' . base64_encode($iv . $tag . $chiffre);
    }

    /** Renvoie null si la donnée a été modifiée ou si la clé n'est pas la bonne. */
    public function dechiffrer(string $donnee): ?string
    {
        if (!str_starts_with($donnee, 'v1:')) {
            return null;
        }
        $brut = base64_decode(substr($donnee, 3), true);
        if ($brut === false || strlen($brut) < 28) {
            return null;
        }
        $clair = openssl_decrypt(
            substr($brut, 28),
            'aes-256-gcm',
            $this->cle,
            OPENSSL_RAW_DATA,
            substr($brut, 0, 12),
            substr($brut, 12, 16)
        );

        return $clair === false ? null : $clair;
    }

    public static function jeton(int $octets = 32): string
    {
        return self::b64u(random_bytes($octets));
    }

    public static function b64u(string $binaire): string
    {
        return rtrim(strtr(base64_encode($binaire), '+/', '-_'), '=');
    }

    public static function b64uDecode(string $texte): string
    {
        $brut = base64_decode(strtr($texte, '-_', '+/'), true);

        return $brut === false ? '' : $brut;
    }
}
