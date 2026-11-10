# Journal du projet – Tableau de bord de vie

Ce fichier garde la trace des décisions, des questions posées, des réponses reçues et de l'état de chaque mission.
Il ne contient jamais de mot de passe, de jeton ni de donnée bancaire.

## État des missions

| Mission | Sujet | État |
|---|---|---|
| M0 | Cadrage (aucun code) | Terminée le 10/11/2026 (réponses reçues) |
| M1 | Socle de l'application | En cours – accord « OK M1 » reçu le 10/11/2026, questions préalables posées |
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

### 10/11/2026 – Questions posées et réponses

| N° | Question | Recommandation | Réponse de Mathis |
|---|---|---|---|
| 1 | Export iCal disponible dans PRONOTE ? (A oui / B non / C je ne sais pas) | Vérifier, voir procédure | B – non, pas d'icône iCal |
| 2 | Connexion à PRONOTE : A directe / B via un ENT / C je ne sais pas | — | B – via un ENT |
| 3 | Boîte mail : A Gmail / B iCloud / C Outlook / D mail de l'école | — (sert en V2) | A – Gmail |
| 4 | Budget mensuel : A 0 € / B jusqu'à 10 € / C jusqu'à 20 € | B | B – jusqu'à 10 € par mois |
| 5 | Niveau d'action de l'IA : A lecture seule / B brouillons / C actions validées | B | B – brouillons |
| 6 | Brief du matin : A règles sans IA / B rédigé par une IA | A pour le MVP | A – règles sans IA |
| 7 | Option technique : A PWA + hébergement géré en Europe / B PWA + serveur personnel / C application native | A | A – PWA + hébergement géré en Europe |
| 8 | Widget iOS : A pas de widget / B widget via l'appli Scriptable après le MVP / C widget natif | B | B – widget Scriptable après M6 |

### Décisions

- Option technique retenue : PWA installable sur iPhone, hébergement géré en Europe (hébergeur à choisir en M1).
- Budget : 10 € par mois au maximum.
- IA : elle peut préparer des brouillons, que Mathis envoie lui-même. Aucune action à sa place.
- Brief du matin du MVP : règles fixes, sans IA.
- Widget : via l'appli Scriptable, après M6.
- Boîte mail (pour M8) : Gmail.

### Risque identifié pour M3 (PRONOTE)

- Pas d'export iCal et connexion via un ENT : c'est le cas le plus difficile. Il ne reste que la piste
  « bibliothèque non officielle », dont la faisabilité dépend de l'ENT utilisé (à demander au début de M3).
- Solutions de repli à étudier en M3 : demander à l'établissement d'activer l'export iCal, connexion par
  QR code de l'appli PRONOTE, ou saisie manuelle de l'emploi du temps.

## M1 – Socle de l'application

### 10/11/2026 – Accord et recherche préalable

- Accord écrit reçu : « Ok m1 ».
- Recherche des tarifs d'hébergement faite par recherche web uniquement : les sites des hébergeurs ne sont pas
  joignables depuis l'environnement de travail. Les prix restent donc à confirmer par Mathis à l'inscription.

### 10/11/2026 – Questions posées (en attente de réponse)

| N° | Question | Recommandation | Réponse de Mathis |
|---|---|---|---|
| 1 | Hébergeur : A Clever Cloud (France) / B Fly.io (serveur à Paris) / C Cloudflare (gratuit) | A | En attente |
| 2 | Adresse de l'appli : A adresse gratuite de l'hébergeur / B nom de domaine personnel | A | En attente |
| 3 | Connexion : A clé d'accès Face ID + code de secours / B mot de passe + code à 6 chiffres / C les deux | A | En attente |
| 4 | Durée de session : A Face ID à chaque ouverture / B 7 jours / C 30 jours | B | En attente |
| 5 | Nom sous l'icône : A « Ma journée » / B « Tableau de bord » / C autre | — | En attente |

### 10/11/2026 – Nouvelle piste proposée par Mathis : hébergement mutualisé OVH (déjà payé)

- Avis donné : possible pour le socle (appli écrite en PHP, base MySQL), données en France, 0 € de plus.
- Limites annoncées : tâches automatiques au mieux une fois par heure (au lieu de 15 minutes) ; les outils non
  officiels PRONOTE ne tournent pas en PHP, un petit service complémentaire pourra être nécessaire en M3.
- Questions posées (en attente) : 1bis nom de l'offre OVH ; 1ter nom de domaine et site déjà en place ;
  1quater confirmation du choix OVH. Questions 3, 4 et 5 toujours en attente ; la question 2 dépend de 1ter.
