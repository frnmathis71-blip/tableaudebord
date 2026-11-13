<?php

declare(strict_types=1);

/**
 * Tâche planifiée (OVH, toutes les heures) : met à jour les sources.
 * Uniquement en ligne de commande : ce fichier n'est pas accessible depuis Internet.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/vendor/autoload.php';

$config = Tdb\Config::charger();
if ($config === null) {
    fwrite(STDERR, "Appli pas encore installée.\n");
    exit(0);
}
$db = Tdb\Db::connecter($config->get('db'));
if (Tdb\Schema::version($db) < Tdb\Schema::VERSION) {
    Tdb\Schema::migrer($db);
}
$resultat = (new Tdb\Agenda($db, $config))->synchroniser(0, 'tâche planifiée');
echo date('c') . " agenda : {$resultat['mis_a_jour']} mis à jour, {$resultat['erreurs']} en erreur\n";
