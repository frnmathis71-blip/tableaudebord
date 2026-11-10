<?php

declare(strict_types=1);

namespace Tdb;

/**
 * Gestion de la connexion : appareils connectés (sessions), jetons temporaires,
 * code de secours et limitation des tentatives.
 */
final class Auth
{
    private ?array $session = null;
    private bool $sessionLue = false;

    public function __construct(private Db $db, private Config $config)
    {
    }

    // --- Sessions (appareils connectés) ---

    private function nomCookie(): string
    {
        return $this->config->https() ? '__Host-tdb' : 'tdb';
    }

    private static function hacher(string $jeton): string
    {
        return hash('sha256', $jeton);
    }

    /** Renvoie la session en cours, ou null si personne n'est connecté. */
    public function session(): ?array
    {
        if ($this->sessionLue) {
            return $this->session;
        }
        $this->sessionLue = true;
        $jeton = (string) ($_COOKIE[$this->nomCookie()] ?? '');
        if ($jeton === '') {
            return null;
        }
        $ligne = $this->db->ligne('SELECT * FROM sessions WHERE token_hash = ?', [self::hacher($jeton)]);
        if ($ligne === null || Clock::depuisSql($ligne['expires_at']) <= Clock::now()) {
            return null;
        }
        // Mise à jour de la « dernière activité », au plus toutes les 5 minutes
        if (Clock::now() - Clock::depuisSql($ligne['last_seen_at']) > 300) {
            $this->db->executer(
                'UPDATE sessions SET last_seen_at = ? WHERE token_hash = ?',
                [Clock::sql(), $ligne['token_hash']]
            );
        }
        $ligne['jeton'] = $jeton;

        return $this->session = $ligne;
    }

    public function connecte(): bool
    {
        return $this->session() !== null;
    }

    public function ouvrirSession(): void
    {
        $jeton = Crypto::jeton();
        $expire = Clock::now() + $this->config->joursSession() * 86400;
        $this->db->executer(
            'INSERT INTO sessions (token_hash, created_at, expires_at, last_seen_at, user_agent, ip) VALUES (?, ?, ?, ?, ?, ?)',
            [self::hacher($jeton), Clock::sql(), Clock::sql($expire), Clock::sql(), Http::agent(), Http::ip()]
        );
        $this->db->executer('DELETE FROM sessions WHERE expires_at < ?', [Clock::sql()]);
        Http::cookie($this->nomCookie(), $jeton, $expire, $this->config->https());
        $this->sessionLue = false;
    }

    public function fermerSession(): void
    {
        $session = $this->session();
        if ($session !== null) {
            $this->db->executer('DELETE FROM sessions WHERE token_hash = ?', [$session['token_hash']]);
        }
        Http::cookie($this->nomCookie(), '', 1, $this->config->https());
        $this->session = null;
        $this->sessionLue = true;
    }

    /** Liste des appareils connectés, le plus récent d'abord. */
    public function sessions(): array
    {
        return $this->db->lignes(
            'SELECT * FROM sessions WHERE expires_at > ? ORDER BY last_seen_at DESC',
            [Clock::sql()]
        );
    }

    /** Déconnecte un appareil à partir du début de son identifiant. */
    public function revoquer(string $debutHash): void
    {
        if (preg_match('/^[0-9a-f]{16}$/', $debutHash) === 1) {
            $this->db->executer('DELETE FROM sessions WHERE token_hash LIKE ?', [$debutHash . '%']);
        }
    }

    public function revoquerAutres(): void
    {
        $session = $this->session();
        if ($session !== null) {
            $this->db->executer('DELETE FROM sessions WHERE token_hash <> ?', [$session['token_hash']]);
        }
    }

    /** Jeton anti-falsification lié à la session, exigé pour toute modification. */
    public function csrf(): string
    {
        $session = $this->session();

        return $session === null ? '' : hash_hmac('sha256', 'csrf:' . $session['jeton'], $this->config->cle());
    }

    public function csrfValide(): bool
    {
        $attendu = $this->csrf();
        $recu = (string) ($_SERVER['HTTP_X_CSRF'] ?? '');

        return $attendu !== '' && hash_equals($attendu, $recu);
    }

    // --- Jetons temporaires à usage unique (liés au navigateur par un cookie) ---

    public function creerTicket(string $but, array $donnees, int $dureeSecondes): void
    {
        $jeton = Crypto::jeton();
        $expire = Clock::now() + $dureeSecondes;
        $this->db->executer('DELETE FROM tickets WHERE expires_at < ?', [Clock::sql()]);
        $this->db->executer(
            'INSERT INTO tickets (token_hash, purpose, data, expires_at) VALUES (?, ?, ?, ?)',
            [self::hacher($jeton), $but, json_encode($donnees), Clock::sql($expire)]
        );
        Http::cookie('tdb_t_' . $but, $jeton, $expire, $this->config->https());
    }

    /** Lit un jeton temporaire ; il est détruit si $consommer est vrai. */
    public function ticket(string $but, bool $consommer): ?array
    {
        $jeton = (string) ($_COOKIE['tdb_t_' . $but] ?? '');
        if ($jeton === '') {
            return null;
        }
        $hash = self::hacher($jeton);
        $ligne = $this->db->ligne('SELECT * FROM tickets WHERE token_hash = ? AND purpose = ?', [$hash, $but]);
        if ($consommer) {
            $this->db->executer('DELETE FROM tickets WHERE token_hash = ?', [$hash]);
            Http::cookie('tdb_t_' . $but, '', 1, $this->config->https());
        }
        if ($ligne === null || Clock::depuisSql($ligne['expires_at']) <= Clock::now()) {
            return null;
        }

        return json_decode($ligne['data'], true) ?: [];
    }

    // --- Code de secours ---

    /** Crée un nouveau code de secours, remplace l'ancien et renvoie le code en clair (affiché une seule fois). */
    public function nouveauCodeSecours(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 20; $i++) {
            $code .= $alphabet[random_int(0, 31)];
        }
        $this->db->definirReglage('recovery_hash', password_hash($code, PASSWORD_DEFAULT));

        return implode('-', str_split($code, 4));
    }

    public function codeSecoursValide(string $saisie): bool
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $saisie) ?? '');
        $hash = $this->db->reglage('recovery_hash');

        return $hash !== null && $code !== '' && password_verify($code, $hash);
    }

    // --- Limitation des tentatives ratées ---

    public function tropDeTentatives(string $type, int $maximum, int $minutes = 15): bool
    {
        $depuis = Clock::sql(Clock::now() - $minutes * 60);
        $nombre = (int) $this->db->valeur(
            'SELECT COUNT(*) FROM login_attempts WHERE kind = ? AND created_at > ?',
            [$type, $depuis]
        );

        return $nombre >= $maximum;
    }

    public function noterEchec(string $type): void
    {
        $this->db->executer(
            'INSERT INTO login_attempts (kind, ip, created_at) VALUES (?, ?, ?)',
            [$type, Http::ip(), Clock::sql()]
        );
        $this->db->executer('DELETE FROM login_attempts WHERE created_at < ?', [Clock::sql(Clock::now() - 86400)]);
    }
}
