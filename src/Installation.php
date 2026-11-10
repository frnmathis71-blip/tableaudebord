<?php

declare(strict_types=1);

namespace Tdb;

/**
 * Page d'installation : disponible uniquement tant que l'appli n'est pas configurée.
 * Elle crée le fichier de configuration, les tables, le code de secours,
 * puis propose de créer la clé d'accès Face ID.
 */
final class Installation
{
    public function traiter(): never
    {
        if (Http::chemin() !== '/installation') {
            Http::rediriger('/installation');
        }
        if (Http::methode() !== 'POST') {
            $this->formulaire();
        }
        if (!Http::memeOrigine(Http::origineRequete())) {
            $this->formulaire('Requête refusée. Recharge la page et recommence.');
        }

        $hote = trim((string) ($_POST['db_host'] ?? ''));
        $base = trim((string) ($_POST['db_name'] ?? ''));
        $utilisateur = trim((string) ($_POST['db_user'] ?? ''));
        $motDePasse = (string) ($_POST['db_pass'] ?? '');
        $saisie = ['db_host' => $hote, 'db_name' => $base, 'db_user' => $utilisateur];

        if (preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $hote) !== 1 || preg_match('/^[A-Za-z0-9_\-]+$/', $base) !== 1 || $utilisateur === '') {
            $this->formulaire('Vérifie l’adresse du serveur, le nom de la base et l’utilisateur.', $saisie);
        }
        [$serveur, $port] = array_pad(explode(':', $hote, 2), 2, null);
        $acces = [
            'dsn' => "mysql:host=$serveur;" . ($port !== null ? "port=$port;" : '') . "dbname=$base;charset=utf8mb4",
            'user' => $utilisateur,
            'password' => $motDePasse,
        ];

        try {
            $db = Db::connecter($acces);
        } catch (\PDOException) {
            $this->formulaire('Connexion à la base impossible. Vérifie les quatre informations (elles sont dans ton espace client OVH).', $saisie);
        }
        if (Schema::tableExiste($db, 'settings') && $db->reglage('installed') === '1') {
            $this->formulaire('Cette base contient déjà une installation. Pour repartir de zéro, vide d’abord la base.', $saisie);
        }

        $config = new Config([
            'app_url' => Http::origineRequete(),
            'db' => $acces,
            'app_key' => base64_encode(random_bytes(32)),
            'session_days' => 7,
        ]);
        Schema::migrer($db);
        $auth = new Auth($db, $config);
        $code = $auth->nouveauCodeSecours();
        $db->definirReglage('installed', '1');
        try {
            Config::ecrire([
                'app_url' => $config->origine(),
                'db' => $acces,
                'app_key' => $config->get('app_key'),
                'session_days' => 7,
            ]);
        } catch (\RuntimeException) {
            $db->definirReglage('installed', '0');
            $this->formulaire('Impossible d’écrire le fichier de configuration sur le serveur (droits d’écriture).', $saisie);
        }
        $auth->creerTicket('inscription', ['motif' => 'installation'], 1800);
        $db->journal('installation');

        Vue::page('cle', [
            'titre' => 'Installation',
            'connecte' => false,
            'csrf' => '',
            'codeSecours' => $code,
        ]);
    }

    private function formulaire(string $erreur = '', array $saisie = []): never
    {
        Vue::page('installation', [
            'titre' => 'Installation',
            'connecte' => false,
            'erreur' => $erreur,
            'saisie' => $saisie,
            'adresse' => Http::origineRequete(),
        ], $erreur === '' ? 200 : 400);
    }
}
