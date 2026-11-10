<?php

declare(strict_types=1);

namespace Tdb;

/** Point d'entrée : reçoit chaque requête et l'envoie vers la bonne page. */
final class App
{
    private Config $config;
    private Db $db;
    private Auth $auth;
    private Passkeys $cles;

    public static function demarrer(): never
    {
        $api = str_starts_with(Http::chemin(), '/api/');
        set_exception_handler(static function (\Throwable $e) use ($api): void {
            error_log('[tableaudebord] ' . get_class($e) . ' : ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')');
            $message = $e instanceof \PDOException
                ? 'La base de données ne répond pas. Réessaie dans quelques minutes.'
                : 'Une erreur est survenue. Réessaie dans un instant.';
            if ($api) {
                Http::erreurJson($message, 500);
            }
            Vue::page('erreur', ['titre' => 'Oups', 'message' => $message, 'connecte' => false], 500);
        });

        $config = Config::charger();
        Http::entetesSecurite($config?->https() ?? Http::enHttps());

        if ($config === null) {
            (new Installation())->traiter();
        }

        $app = new self();
        $app->config = $config;
        $app->router();
    }

    private function router(): never
    {
        $chemin = Http::chemin();
        $methode = Http::methode();

        // Une seule adresse officielle : la clé d'accès Face ID y est liée.
        $hoteAttendu = substr($this->config->origine(), (int) strpos($this->config->origine(), '://') + 3);
        if (($_SERVER['HTTP_HOST'] ?? '') !== $hoteAttendu || ($this->config->https() && !Http::enHttps())) {
            if ($methode === 'GET') {
                header('Location: ' . $this->config->origine() . ($_SERVER['REQUEST_URI'] ?? '/'), true, 302);
                exit;
            }
            Http::erreurJson('Adresse incorrecte.', 400);
        }

        $this->db = Db::connecter($this->config->get('db'));
        $this->auth = new Auth($this->db, $this->config);
        $this->cles = new Passkeys($this->db, $this->config, $this->auth);

        if ($methode === 'POST') {
            if (!Http::memeOrigine($this->config->origine())) {
                Http::erreurJson('Requête refusée.', 403);
            }
            $this->routerPost($chemin);
        }
        if ($methode !== 'GET' && $methode !== 'HEAD') {
            Http::erreurJson('Méthode non acceptée.', 405);
        }

        match ($chemin) {
            '/' => $this->pageAujourdhui(),
            '/connexion' => $this->pageConnexion(),
            '/cle' => $this->pageCle(),
            '/reglages' => $this->pageReglages(),
            '/installation' => Http::rediriger('/'),
            default => Vue::page('erreur', [
                'titre' => 'Page introuvable',
                'message' => 'Cette page n’existe pas.',
                'connecte' => $this->auth->connecte(),
            ], 404),
        };
    }

    // ------------------------------------------------------------------ Pages

    private function exigerConnexion(): void
    {
        if (!$this->auth->connecte()) {
            Http::rediriger('/connexion');
        }
    }

    private function pageAujourdhui(): never
    {
        $this->exigerConnexion();
        Vue::page('aujourdhui', [
            'titre' => 'Aujourd’hui',
            'connecte' => true,
            'onglet' => 'jour',
            'csrf' => $this->auth->csrf(),
            'date' => Vue::dateLongue(Clock::now()),
            'modules' => (new Modules($this->db))->disposition(),
        ]);
    }

    private function pageConnexion(): never
    {
        if ($this->auth->connecte()) {
            Http::rediriger('/');
        }
        Vue::page('connexion', [
            'titre' => 'Connexion',
            'connecte' => false,
            'aucuneCle' => $this->cles->nombre() === 0,
        ]);
    }

    /** Page de création d'une clé d'accès (après le code de secours, ou depuis les réglages). */
    private function pageCle(): never
    {
        if (!$this->auth->connecte() && $this->auth->ticket('inscription', false) === null) {
            Http::rediriger('/connexion');
        }
        Vue::page('cle', [
            'titre' => 'Clé d’accès',
            'connecte' => $this->auth->connecte(),
            'onglet' => 'reglages',
            'csrf' => $this->auth->csrf(),
            'codeSecours' => null,
        ]);
    }

    private function pageReglages(): never
    {
        $this->exigerConnexion();
        $session = $this->auth->session();
        Vue::page('reglages', [
            'titre' => 'Réglages',
            'connecte' => true,
            'onglet' => 'reglages',
            'csrf' => $this->auth->csrf(),
            'cles' => $this->cles->liste(),
            'sessions' => $this->auth->sessions(),
            'sessionActuelle' => $session['token_hash'] ?? '',
            'jours' => $this->config->joursSession(),
        ]);
    }

    // ---------------------------------------------------------------- Actions

    private function routerPost(string $chemin): never
    {
        // Actions possibles sans être connecté
        match ($chemin) {
            '/api/connexion/options' => Http::json(['ok' => true, 'options' => $this->cles->optionsConnexion()]),
            '/api/connexion/valider' => $this->validerConnexion(),
            '/api/secours' => $this->utiliserCodeSecours(),
            '/api/cle/options' => $this->optionsCle(),
            '/api/cle/valider' => $this->validerCle(),
            default => null,
        };

        // Tout le reste exige d'être connecté, avec le jeton anti-falsification
        if (!$this->auth->connecte()) {
            Http::erreurJson('Tu n’es plus connecté.', 401);
        }
        if (!$this->auth->csrfValide()) {
            Http::erreurJson('Requête refusée.', 403);
        }
        $corps = Http::corpsJson();

        switch ($chemin) {
            case '/api/deconnexion':
                $this->db->journal('deconnexion');
                $this->auth->fermerSession();
                Http::json(['ok' => true]);
                // no break
            case '/api/modules':
                if (!(new Modules($this->db))->enregistrer($corps['modules'] ?? null)) {
                    Http::erreurJson('Disposition invalide.');
                }
                Http::json(['ok' => true]);
                // no break
            case '/api/sessions/revoquer':
                if (($corps['autres'] ?? false) === true) {
                    $this->auth->revoquerAutres();
                    $this->db->journal('sessions_revoquees', 'tous les autres appareils');
                } else {
                    $this->auth->revoquer((string) ($corps['id'] ?? ''));
                    $this->db->journal('session_revoquee');
                }
                Http::json(['ok' => true]);
                // no break
            case '/api/cle/supprimer':
                if (!$this->cles->supprimer((int) ($corps['id'] ?? 0))) {
                    Http::erreurJson('Impossible de supprimer ta dernière clé d’accès.');
                }
                $this->db->journal('cle_supprimee');
                Http::json(['ok' => true]);
                // no break
            case '/api/secours/nouveau':
                $code = $this->auth->nouveauCodeSecours();
                $this->db->journal('code_secours_renouvele');
                Http::json(['ok' => true, 'codeSecours' => $code]);
                // no break
            default:
                Http::erreurJson('Action inconnue.', 404);
        }
    }

    private function validerConnexion(): never
    {
        if ($this->auth->tropDeTentatives('cle', 10)) {
            Http::erreurJson('Trop de tentatives. Réessaie dans 15 minutes.', 429);
        }
        try {
            $this->cles->validerConnexion(Http::corpsJson());
        } catch (\PDOException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->auth->noterEchec('cle');
            $this->db->journal('connexion_refusee', $e->getMessage());
            Http::erreurJson('Connexion refusée. Réessaie.', 401);
        }
        $this->auth->ouvrirSession();
        $this->db->journal('connexion', Vue::appareil(Http::agent()));
        Http::json(['ok' => true]);
    }

    private function utiliserCodeSecours(): never
    {
        if ($this->auth->tropDeTentatives('secours', 5)) {
            Http::erreurJson('Trop de tentatives. Réessaie dans 15 minutes.', 429);
        }
        $corps = Http::corpsJson();
        if (!$this->auth->codeSecoursValide((string) ($corps['code'] ?? ''))) {
            $this->auth->noterEchec('secours');
            $this->db->journal('code_secours_refuse');
            Http::erreurJson('Code de secours incorrect.', 401);
        }
        $this->auth->creerTicket('inscription', ['motif' => 'secours'], 900);
        $this->db->journal('code_secours_accepte');
        Http::json(['ok' => true, 'suite' => '/cle']);
    }

    /** Autorisé si on est connecté, ou si on vient de l'installation ou du code de secours. */
    private function peutCreerCle(): bool
    {
        if ($this->auth->connecte()) {
            return $this->auth->csrfValide();
        }

        return $this->auth->ticket('inscription', false) !== null;
    }

    private function optionsCle(): never
    {
        if (!$this->peutCreerCle()) {
            Http::erreurJson('Action non autorisée.', 403);
        }
        Http::json(['ok' => true, 'options' => $this->cles->optionsCreation()]);
    }

    private function validerCle(): never
    {
        if (!$this->peutCreerCle()) {
            Http::erreurJson('Action non autorisée.', 403);
        }
        try {
            $this->cles->validerCreation(Http::corpsJson(), Vue::appareil(Http::agent()));
        } catch (\PDOException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->db->journal('cle_refusee', $e->getMessage());
            Http::erreurJson('La clé d’accès n’a pas pu être créée. Réessaie.', 400);
        }
        $this->db->journal('cle_creee', Vue::appareil(Http::agent()));

        $reponse = ['ok' => true];
        if (!$this->auth->connecte()) {
            $ticket = $this->auth->ticket('inscription', true) ?? [];
            // Un code de secours ne sert qu'une fois : on en donne un nouveau.
            if (($ticket['motif'] ?? '') === 'secours') {
                $reponse['codeSecours'] = $this->auth->nouveauCodeSecours();
            }
            $this->auth->ouvrirSession();
            $this->db->journal('connexion', 'nouvelle clé');
        }
        Http::json($reponse);
    }
}
