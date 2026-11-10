<?php

declare(strict_types=1);

namespace Tdb;

use lbuchs\WebAuthn\WebAuthn;

/**
 * Clés d'accès (« passkeys ») : la connexion par Face ID.
 * Le serveur ne stocke que la partie publique de la clé ; la partie secrète
 * ne quitte jamais l'iPhone (trousseau iCloud).
 */
final class Passkeys
{
    private const DELAI = 300; // secondes pour valider avec Face ID

    public function __construct(private Db $db, private Config $config, private Auth $auth)
    {
    }

    private function webauthn(): WebAuthn
    {
        return new WebAuthn('Tableau de bord', $this->config->hote(), ['none'], true);
    }

    public function nombre(): int
    {
        return (int) $this->db->valeur('SELECT COUNT(*) FROM passkeys');
    }

    public function liste(): array
    {
        return $this->db->lignes('SELECT id, label, created_at, last_used_at FROM passkeys ORDER BY id');
    }

    /** Supprime une clé, sauf s'il n'en reste qu'une. */
    public function supprimer(int $id): bool
    {
        if ($this->nombre() <= 1) {
            return false;
        }

        return $this->db->executer('DELETE FROM passkeys WHERE id = ?', [$id])->rowCount() > 0;
    }

    private function identifiantUtilisateur(): string
    {
        $handle = $this->db->reglage('user_handle');
        if ($handle === null) {
            $handle = bin2hex(random_bytes(16));
            $this->db->definirReglage('user_handle', $handle);
        }

        return (string) hex2bin($handle);
    }

    /** Vérifie que la réponse vient bien de l'adresse exacte de l'appli. */
    private function origineCorrecte(string $clientDataJson): bool
    {
        $donnees = json_decode($clientDataJson, true);

        return is_array($donnees)
            && hash_equals($this->config->origine(), rtrim((string) ($donnees['origin'] ?? ''), '/'));
    }

    // --- Création d'une clé ---

    public function optionsCreation(): object
    {
        $webauthn = $this->webauthn();
        $existantes = array_map(
            static fn (array $l): string => Crypto::b64uDecode($l['credential_id']),
            $this->db->lignes('SELECT credential_id FROM passkeys')
        );
        $options = $webauthn->getCreateArgs(
            $this->identifiantUtilisateur(),
            'moi',
            'Tableau de bord',
            self::DELAI,
            'required',
            'required',
            null,
            $existantes
        );
        unset($options->publicKey->extensions);
        $this->auth->creerTicket('creation', ['defi' => bin2hex($webauthn->getChallenge()->getBinaryString())], self::DELAI);

        return $options;
    }

    public function validerCreation(array $reponse, string $nomAppareil): void
    {
        $ticket = $this->auth->ticket('creation', true);
        if ($ticket === null) {
            throw new \RuntimeException('La demande a expiré. Recommence.');
        }
        $clientData = Crypto::b64uDecode((string) ($reponse['clientDataJSON'] ?? ''));
        if (!$this->origineCorrecte($clientData)) {
            throw new \RuntimeException("Adresse d'origine inattendue.");
        }
        $donnees = $this->webauthn()->processCreate(
            $clientData,
            Crypto::b64uDecode((string) ($reponse['attestationObject'] ?? '')),
            (string) hex2bin($ticket['defi']),
            true,
            true,
            false
        );
        $identifiant = Crypto::b64u($donnees->credentialId);
        $this->db->executer(
            'INSERT INTO passkeys (credential_hash, credential_id, public_key, sign_count, label, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [
                hash('sha256', $identifiant),
                $identifiant,
                $donnees->credentialPublicKey,
                (int) ($donnees->signatureCounter ?? 0),
                mb_substr($nomAppareil, 0, 100),
                Clock::sql(),
            ]
        );
    }

    // --- Connexion avec une clé ---

    public function optionsConnexion(): object
    {
        $webauthn = $this->webauthn();
        $options = $webauthn->getGetArgs([], self::DELAI, true, true, true, true, true, 'required');
        $this->auth->creerTicket('connexion', ['defi' => bin2hex($webauthn->getChallenge()->getBinaryString())], self::DELAI);

        return $options;
    }

    public function validerConnexion(array $reponse): void
    {
        $ticket = $this->auth->ticket('connexion', true);
        if ($ticket === null) {
            throw new \RuntimeException('La demande a expiré. Recommence.');
        }
        $identifiant = (string) ($reponse['id'] ?? '');
        $cle = $this->db->ligne('SELECT * FROM passkeys WHERE credential_hash = ?', [hash('sha256', $identifiant)]);
        if ($cle === null) {
            throw new \RuntimeException("Cette clé d'accès n'est pas reconnue.");
        }
        $clientData = Crypto::b64uDecode((string) ($reponse['clientDataJSON'] ?? ''));
        if (!$this->origineCorrecte($clientData)) {
            throw new \RuntimeException("Adresse d'origine inattendue.");
        }
        $webauthn = $this->webauthn();
        $webauthn->processGet(
            $clientData,
            Crypto::b64uDecode((string) ($reponse['authenticatorData'] ?? '')),
            Crypto::b64uDecode((string) ($reponse['signature'] ?? '')),
            $cle['public_key'],
            (string) hex2bin($ticket['defi']),
            (int) $cle['sign_count'] > 0 ? (int) $cle['sign_count'] : null,
            true
        );
        $this->db->executer(
            'UPDATE passkeys SET sign_count = ?, last_used_at = ? WHERE id = ?',
            [(int) ($webauthn->getSignatureCounter() ?? $cle['sign_count']), Clock::sql(), $cle['id']]
        );
    }
}
