<?php

declare(strict_types=1);

namespace Tdb;

/** Création et mise à jour des tables de la base de données. */
final class Schema
{
    public const VERSION = 1;

    public static function tableExiste(Db $db, string $table): bool
    {
        try {
            $db->executer("SELECT 1 FROM $table LIMIT 1");

            return true;
        } catch (\PDOException) {
            return false;
        }
    }

    public static function version(Db $db): int
    {
        if (!self::tableExiste($db, 'settings')) {
            return 0;
        }

        return (int) $db->reglage('schema_version', '0');
    }

    public static function migrer(Db $db): void
    {
        $version = self::version($db);
        if ($version >= self::VERSION) {
            return;
        }

        $id = $db->pilote === 'mysql'
            ? 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $fin = $db->pilote === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';

        if ($version < 1) {
            $tables = [
                // Réglages généraux (ordre des modules, code de secours haché...)
                "CREATE TABLE IF NOT EXISTS settings (
                    name VARCHAR(64) NOT NULL PRIMARY KEY,
                    value TEXT NOT NULL
                )$fin",
                // Clés d'accès (Face ID) : on ne stocke que la partie publique
                "CREATE TABLE IF NOT EXISTS passkeys (
                    id $id,
                    credential_hash CHAR(64) NOT NULL UNIQUE,
                    credential_id TEXT NOT NULL,
                    public_key TEXT NOT NULL,
                    sign_count INT NOT NULL DEFAULT 0,
                    label VARCHAR(100) NOT NULL,
                    created_at DATETIME NOT NULL,
                    last_used_at DATETIME NULL
                )$fin",
                // Appareils connectés : seul un hachage du jeton est stocké
                "CREATE TABLE IF NOT EXISTS sessions (
                    token_hash CHAR(64) NOT NULL PRIMARY KEY,
                    created_at DATETIME NOT NULL,
                    expires_at DATETIME NOT NULL,
                    last_seen_at DATETIME NOT NULL,
                    user_agent VARCHAR(255) NOT NULL,
                    ip VARCHAR(45) NOT NULL
                )$fin",
                // Jetons temporaires à usage unique (étapes de connexion)
                "CREATE TABLE IF NOT EXISTS tickets (
                    token_hash CHAR(64) NOT NULL PRIMARY KEY,
                    purpose VARCHAR(20) NOT NULL,
                    data TEXT NOT NULL,
                    expires_at DATETIME NOT NULL
                )$fin",
                // Tentatives ratées (protection contre les essais répétés)
                "CREATE TABLE IF NOT EXISTS login_attempts (
                    id $id,
                    kind VARCHAR(20) NOT NULL,
                    ip VARCHAR(45) NOT NULL,
                    created_at DATETIME NOT NULL
                )$fin",
                // Journal des accès
                "CREATE TABLE IF NOT EXISTS access_log (
                    id $id,
                    created_at DATETIME NOT NULL,
                    event VARCHAR(50) NOT NULL,
                    detail VARCHAR(255) NOT NULL,
                    ip VARCHAR(45) NOT NULL
                )$fin",
            ];
            foreach ($tables as $sql) {
                $db->pdo->exec($sql);
            }
        }

        $db->definirReglage('schema_version', (string) self::VERSION);
    }
}
