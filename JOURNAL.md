# Journal du projet – Tableau de bord de vie

Ce fichier garde la trace des décisions, des questions posées, des réponses reçues et de l'état de chaque mission.
Il ne contient jamais de mot de passe, de jeton ni de donnée bancaire.

## État des missions

| Mission | Sujet | État |
|---|---|---|
| M0 | Cadrage (aucun code) | En cours – en attente des réponses de Mathis |
| M1 | Socle de l'application | Non commencée (attend « OK M1 ») |
| M2 | Agenda Apple (iCloud) | Non commencée |
| M3 | École (PRONOTE) | Non commencée |
| M4 | Pro et URSSAF | Non commencée |
| M5 | Recherche de stage | Non commencée |
| M6 | Brief du matin et notifications | Non commencée |
| — | Bilan d'usage du MVP | — |
| M7 | Finances | Non commencée (V2) |
| M8 | Mails et administratif | Non commencée (V2) |
| M9 | Sécurité et recette finale | Non commencée (V2) |

## Faits établis (donnés par Mathis ou par le cahier des charges)

- Un seul utilisateur, sur iPhone ; interface en français, mode sombre, utilisable d'une main.
- Sources : Agenda Apple (iCloud), PRONOTE, Revolut, Caisse d'Épargne.
- URSSAF : micro-entreprise, déclaration trimestrielle. Échéances : 31/01, 30/04, 31/07, 31/10
  (chiffre d'affaires du trimestre précédent). Prochaine : 31 janvier 2027 (CA d'octobre à décembre 2026).
- Préférence de départ : application web installable (PWA) avec notifications push.
- Sécurité : lecture seule par défaut, aucune action sans validation, identifiants chiffrés et jamais dans le code,
  données fictives d'abord.
- Le dépôt GitHub `tableaudebord` est privé (vérifié le 10/11/2026).

## Point relevé

- Le cahier des charges (section 5) recommande de démarrer par « Claude + connecteurs ». La demande de Mathis
  exprime une préférence pour une PWA sur mesure. La demande étant plus récente et plus précise, la PWA est
  prise comme base de travail ; à confirmer par Mathis (question 7).

## M0 – Cadrage

### 10/11/2026 – Questions posées (en attente de réponse)

| N° | Question | Recommandation | Réponse de Mathis |
|---|---|---|---|
| 1 | Export iCal disponible dans PRONOTE ? (A oui / B non / C je ne sais pas) | Vérifier, voir procédure | En attente |
| 2 | Connexion à PRONOTE : A directe / B via un ENT / C je ne sais pas | — | En attente |
| 3 | Boîte mail : A Gmail / B iCloud / C Outlook / D mail de l'école | — (sert en V2) | En attente |
| 4 | Budget mensuel : A 0 € / B jusqu'à 10 € / C jusqu'à 20 € | B | En attente |
| 5 | Niveau d'action de l'IA : A lecture seule / B brouillons / C actions validées | B | En attente |
| 6 | Brief du matin : A règles sans IA / B rédigé par une IA | A pour le MVP | En attente |
| 7 | Option technique : A PWA + hébergement géré en Europe / B PWA + serveur personnel / C application native | A | En attente |
| 8 | Widget iOS : A pas de widget / B widget via l'appli Scriptable après le MVP / C widget natif | B | En attente |

### Décisions

Aucune pour l'instant (en attente des réponses).
