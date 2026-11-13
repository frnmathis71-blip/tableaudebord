# Tableau de bord de vie

Application personnelle (un seul utilisateur) qui réunit agenda, école, obligations pro et recherche de stage.
Application web installable sur l'écran d'accueil de l'iPhone, en PHP avec une base MySQL.

- Suivi du projet, décisions et état des missions : [JOURNAL.md](JOURNAL.md)
- Mise en ligne chez OVH : [docs/INSTALLATION_OVH.md](docs/INSTALLATION_OVH.md)

## Organisation des dossiers

| Dossier | Contenu |
|---|---|
| `public/` | Seule partie visible depuis Internet : page d'entrée, styles, icônes |
| `src/` | Le code de l'appli (connexion, modules, pages) |
| `vendor/` | Bibliothèque de connexion par clé d'accès (lbuchs/webauthn, licence MIT) |
| `tests/` | Tests automatiques, avec des données fictives uniquement |
| `docs/` | Guides |

Aucun mot de passe ni jeton n'est enregistré dans ce dépôt : la configuration est créée sur le serveur par la page
d'installation.

## Lancer les tests

```bash
TDB_DB_SOCKET=/chemin/vers/mysql.sock tests/lancer.sh
```

Il faut PHP 8.2 ou plus, Node avec Playwright, et une base MariaDB locale de test (la base `tdb_test` est vidée à
chaque lancement).
