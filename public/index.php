<?php

declare(strict_types=1);

// Point d'entrée unique de l'application.
// En local (tests), le petit serveur de PHP sert directement les fichiers qui existent.
if (PHP_SAPI === 'cli-server') {
    $fichier = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($fichier)) {
        return false;
    }
}

require dirname(__DIR__) . '/vendor/autoload.php';

Tdb\App::demarrer();
