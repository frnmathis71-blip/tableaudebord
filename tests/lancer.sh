#!/usr/bin/env bash
# Lance tous les tests automatiques sur une base MariaDB locale de test.
# Usage : TDB_DB_SOCKET=/chemin/mysql.sock tests/lancer.sh
# La base « tdb_test » est vidée à chaque lancement : ne jamais viser une vraie base.
set -euo pipefail
cd "$(dirname "$0")/.."
: "${TDB_DB_SOCKET:?Indique le socket de la base MariaDB de test}"
PORT="${TDB_PORT:-8765}"
mkdir -p tests/.tmp
rm -f tests/.tmp/config.php

echo "== Tests internes =="
php tests/unit.php
php tests/agenda.php

echo "== Tests dans le navigateur =="
mariadb --socket="$TDB_DB_SOCKET" -uroot -e "DROP DATABASE IF EXISTS tdb_test; CREATE DATABASE tdb_test;"
TDB_CONFIG="$PWD/tests/.tmp/config.php" php -S "localhost:$PORT" -t public public/index.php >tests/.tmp/serveur.log 2>&1 &
SERVEUR=$!
# Faux calendrier iCloud (données fictives)
php -S "localhost:8766" -t tests/fixtures >tests/.tmp/agenda.log 2>&1 &
FAUX_AGENDA=$!
trap 'kill $SERVEUR $FAUX_AGENDA 2>/dev/null || true' EXIT
sleep 1
TDB_URL="http://localhost:$PORT" node tests/e2e.cjs
