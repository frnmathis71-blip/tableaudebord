<?php

declare(strict_types=1);

namespace Tdb;

use PDO;

/** Accès à la base de données (MySQL chez OVH, SQLite pour les tests). */
final class Db
{
    public readonly string $pilote;

    public function __construct(public readonly PDO $pdo)
    {
        $this->pilote = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public static function connecter(array $db): self
    {
        $pdo = new PDO((string) $db['dsn'], $db['user'] ?? null, $db['password'] ?? null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);

        return new self($pdo);
    }

    public function executer(string $sql, array $params = []): \PDOStatement
    {
        $requete = $this->pdo->prepare($sql);
        $requete->execute($params);

        return $requete;
    }

    public function ligne(string $sql, array $params = []): ?array
    {
        $ligne = $this->executer($sql, $params)->fetch();

        return $ligne === false ? null : $ligne;
    }

    public function lignes(string $sql, array $params = []): array
    {
        return $this->executer($sql, $params)->fetchAll();
    }

    public function valeur(string $sql, array $params = []): mixed
    {
        $valeur = $this->executer($sql, $params)->fetchColumn();

        return $valeur === false ? null : $valeur;
    }

    // --- Réglages (table clé / valeur) ---

    public function reglage(string $nom, ?string $defaut = null): ?string
    {
        $valeur = $this->valeur('SELECT value FROM settings WHERE name = ?', [$nom]);

        return $valeur === null ? $defaut : (string) $valeur;
    }

    public function definirReglage(string $nom, string $valeur): void
    {
        $this->pdo->beginTransaction();
        $this->executer('DELETE FROM settings WHERE name = ?', [$nom]);
        $this->executer('INSERT INTO settings (name, value) VALUES (?, ?)', [$nom, $valeur]);
        $this->pdo->commit();
    }

    /** Journal des accès : qui s'est connecté, quand, depuis où. */
    public function journal(string $evenement, string $detail = ''): void
    {
        $this->executer(
            'INSERT INTO access_log (created_at, event, detail, ip) VALUES (?, ?, ?, ?)',
            [Clock::sql(), $evenement, mb_substr($detail, 0, 250), Http::ip()]
        );
    }
}
