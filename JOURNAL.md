# Journal du projet – Tableau de bord de vie

Ce fichier garde la trace des décisions, des questions posées, des réponses reçues et de l'état de chaque mission.
Il ne contient jamais de mot de passe, de jeton ni de donnée bancaire.

## État des missions

| Mission | Sujet | État |
|---|---|---|
| M0 | Cadrage (aucun code) | Terminée le 10/11/2026 (réponses reçues) |
| M1 | Socle de l'application | Code écrit et testé le 10/11/2026 – en attente de la mise en ligne par Mathis et de ses vérifications sur iPhone |
| M2 | Agenda Apple (iCloud) | Code écrit et testé le 13/11/2026 – en attente des vérifications de Mathis sur iPhone |
| M3 | École (PRONOTE) | En cours – « OK M3 » reçu le 13/11/2026, test de faisabilité présenté, choix en attente |
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

### 10/11/2026 – Questions posées et réponses

| N° | Question | Recommandation | Réponse de Mathis |
|---|---|---|---|
| 1 | Hébergeur : A Clever Cloud (France) / B Fly.io (serveur à Paris) / C Cloudflare (gratuit) | A | Autre choix : hébergement mutualisé OVH, offre Pro, déjà payé |
| 2 | Adresse de l'appli : A adresse gratuite de l'hébergeur / B nom de domaine personnel | A | Sous-adresse à part sur le domaine OVH de Mathis ; le site existant est conservé et ne doit pas être touché |
| 3 | Connexion : A clé d'accès Face ID + code de secours / B mot de passe + code à 6 chiffres / C les deux | A | A – clé d'accès Face ID + code de secours |
| 4 | Durée de session : A Face ID à chaque ouverture / B 7 jours / C 30 jours | B | B – 7 jours |
| 5 | Nom sous l'icône : A « Ma journée » / B « Tableau de bord » / C autre | — | B – « Tableau de bord » |

### 10/11/2026 – Nouvelle piste proposée par Mathis : hébergement mutualisé OVH (déjà payé)

- Avis donné : possible pour le socle (appli écrite en PHP, base MySQL), données en France, 0 € de plus.
- Limites annoncées : tâches automatiques au mieux une fois par heure (au lieu de 15 minutes) ; les outils non
  officiels PRONOTE ne tournent pas en PHP, un petit service complémentaire pourra être nécessaire en M3.
- Questions posées (en attente) : 1bis nom de l'offre OVH ; 1ter nom de domaine et site déjà en place ;
  1quater confirmation du choix OVH. Questions 3, 4 et 5 toujours en attente ; la question 2 dépend de 1ter.

### 10/11/2026 – Réponses sur OVH

- Offre OVH : Pro. Domaine avec un site à garder : l'appli ira sur une sous-adresse séparée. OVH confirmé comme hébergeur.
- Décision : hébergement sur le mutualisé OVH Pro de Mathis ; appli écrite en PHP avec base MySQL.
- Questions 3, 4 et 5 répondues ensuite (voir le tableau ci-dessus).

### 10/11/2026 – Réalisation du socle

Ce qui a été fait :
- Appli en PHP (sans cadre de développement lourd), base MySQL, une seule bibliothèque externe pour les clés d'accès
  (lbuchs/webauthn, licence MIT), fournie dans le dépôt pour ne rien avoir à installer chez OVH.
- Connexion par clé d'accès (Face ID) uniquement, code de secours à usage unique, session de 7 jours,
  liste des appareils connectés avec déconnexion à distance.
- Page « Aujourd'hui » vide avec 4 modules (Agenda, École, Pro et URSSAF, Recherche de stage) que l'on peut
  monter, descendre et masquer ; l'ordre est enregistré.
- Mode sombre, mise en page iPhone, icône, installable sur l'écran d'accueil, page « Pas de connexion ».
- Page d'installation qui crée la configuration sur le serveur (aucun secret dans le dépôt).
- Socle de sécurité : journal des accès, chiffrement prêt pour les identifiants des sources (M2), blocage après
  plusieurs tentatives ratées, protections contre les requêtes venant d'autres sites.

Décisions techniques prises sans question (sans effet sur les données ou les préférences de Mathis) :
- Déplacement des modules par flèches plutôt que par glisser-déposer : plus fiable au pouce sur iPhone.
- Tout le trafic passe par le dossier `public/` : le code, la configuration et ce journal sont inaccessibles
  depuis Internet, même si OVH installe le dépôt à la racine de la sous-adresse.

Tests automatiques (données fictives, base de test locale) – tous réussis :
- 51 tests internes (sessions de 7 jours, code de secours, chiffrement, modules, dates en heure de Paris).
- 70 tests dans un navigateur à la taille d'un iPhone, passés sur deux serveurs (PHP seul, et Apache comme OVH) :
  installation, connexion, déconnexion, accès refusé sans authentification, clé inconnue refusée, session expirée,
  code de secours, déconnexion à distance, fichiers internes illisibles, page affichée en 20 à 25 ms en local.
- Problème trouvé et corrigé pendant les tests : le formulaire d'installation était refusé à cause d'un réglage de
  confidentialité trop strict (l'appli ne reconnaissait plus sa propre adresse).

Non testé à ce stade (impossible sans la mise en ligne) :
- Face ID réel sur iPhone, installation sur l'écran d'accueil, vitesse réelle chez OVH.
- La liaison Git d'OVH : le guide a été écrit sans accès au site d'OVH, les menus sont à confirmer.

### 10/11/2026 – Mise en ligne

- Question : Mathis a-t-il un ordinateur pour la mise en ligne ? Réponse : A – oui.
- En attente : mise en ligne par Mathis selon `docs/INSTALLATION_OVH.md`, puis ses 5 vérifications sur iPhone.

### 12/11/2026 – Demande de Mathis : style translucide

- Demande : passer l'interface en style translucide.
- Questions posées (en attente) : 1 référence visée (Liquid Glass iOS 26 / verre dépoli classique) ;
  2 fond derrière le verre ; 3 éléments concernés ; 4 modèle d'iPhone (fluidité).
- Réponse à la question 1 : capture d'un « Glassy Dashboard » (vidéo TikTok) comme référence : cartes en verre
  dépoli clair sur photo d'intérieur floutée aux tons chauds, accents jaunes, quelques cartes sombres.
- Point relevé : la référence est claire, alors que le cahier des charges demande un mode sombre.
- Nouvelles questions posées (en attente) : clair ou sombre, fond, couleur d'accent ; questions 3 et 4 toujours en attente.

### 12/11/2026 – Style translucide : réponses et réalisation

- Réponses de Mathis : 1A version sombre (verre fumé) ; 2A fond flou chaud généré ; 3A accent jaune doré ;
  4A tout translucide ; iPhone 15.
- Fait : nouveau fond chaud et flou, cartes, boutons et barre d'onglets en verre fumé, barre d'onglets flottante,
  accent doré, icône aux nouvelles couleurs, panneaux opaques si « Réduire la transparence » est actif.
- Tests : 51 tests internes et 70 tests dans le navigateur réussis.
- Envoyé sur la branche de travail (pas encore en ligne chez OVH) ; en attente de la validation visuelle de Mathis
  (A garder / B ajuster / C revenir à l'ancien style, facile à annuler).

### 13/11/2026 – Mise en ligne (suite)

- Sous-adresse `tableaubord.codenyr.fr` active en https (dossier racine `~/tableaubord`). Code cloné d'abord par
  erreur dans `~/tableaudebord` ; commande corrigée donnée.
- Constat : le dépôt GitHub est devenu public (il était privé le 10/11). Aucun secret dedans, mais le journal est
  lisible par tous. Question posée (A repasser en privé, recommandé / B laisser public) : en attente.
- Pas de fichier `.env` : la configuration est créée par la page d'installation dans `~/tableaudebord-config.php`.
- M1 : installation sur iPhone et 5 vérifications pas encore faites ; Mathis demande de passer à M2.

## M2 – Agenda Apple (iCloud)

### 13/11/2026 – Accord, questions posées et réponses

| N° | Question | Recommandation | Réponse de Mathis |
|---|---|---|---|
| 1 | Accès : A lien de partage public par calendrier / B mot de passe d'application Apple (CalDAV) | A | A – lien de partage public |
| 2 | Calendriers à lire : A tous / B une liste | — | A – tous |
| 3 | Rafraîchissement : A à l'ouverture + toutes les heures / B service extérieur toutes les 15 min | A | A – à l'ouverture + toutes les heures |
| 4 | Plage du temps libre : A 8h-20h / B 9h-18h / C autre | A | A – 8 h-20 h tous les jours |
| 5 | Conflits : A événements à heure fixe seulement / B y compris journées entières | A | A – heures fixes seulement |

### 13/11/2026 – Réalisation de M2

Ce qui a été fait :
- Ajout de calendriers iCloud par leur lien de partage (Réglages → Agenda iCloud), lien stocké chiffré.
  Seuls les liens `icloud.com` en https sont acceptés, y compris lors des redirections.
- Lecture des événements avec la bibliothèque sabre/vobject (licence BSD) : répétitions, exceptions,
  occurrences déplacées, journées entières, fuseaux horaires.
- Copie locale des événements de la veille à J+15 : la page s'affiche vite et reste lisible si iCloud ne répond pas.
- Mise à jour à l'ouverture de l'appli (au plus une fois toutes les 3 minutes, en arrière-plan), bouton
  « Actualiser », et tâche planifiée `cron.php` toutes les heures (à créer chez OVH, guide étape 7).
- Page « Aujourd'hui » : « Ta journée » (événements, conflits, temps libre restant, date de mise à jour).
- Module Agenda : 7 jours à venir, nombre d'événements, conflits et temps libre (8 h – 20 h) par jour.
- Journal des accès : chaque lecture d'un calendrier et chaque erreur sont enregistrées.

Décisions techniques prises sans question :
- Fenêtre de copie : veille à J+15. Temps libre du jour : seulement le temps restant à partir de maintenant.
- Les journées entières ne réduisent pas le temps libre (cohérent avec la réponse 5A sur les conflits).

Tests automatiques (données fictives) – tous réussis :
- 51 tests internes du socle, 49 tests de l'agenda (fuseaux, heure d'été et d'hiver, répétitions, conflits,
  temps libre, liens refusés, panne d'iCloud), 87 tests dans le navigateur (dont 17 pour l'agenda : ajout,
  conflit fictif détecté, heures de Paris, événement ajouté « sur l'iPhone » visible à l'ouverture,
  titre piégé neutralisé, panne avec date de dernière mise à jour).

Non testé (impossible sans la mise en ligne) :
- Vrai lien iCloud ; délai réel entre la création d'un événement sur l'iPhone et sa publication par Apple.
- Tâche planifiée OVH (menus du guide écrits sans accès au site d'OVH).

### 13/11/2026 – Informations reçues pour M3

- Adresse PRONOTE et ENT régional reçus. Ils ne sont volontairement pas recopiés ici, tant que le dépôt est public.
- Accord « OK M3 » reçu le 13/11/2026.

## M3 – École (PRONOTE)

### 13/11/2026 – Test de faisabilité (sans identifiants)

- Export iCal : indisponible (réponse M0).
- Bibliothèque non officielle pronotepy (Python, licence MIT) : maintenue (version du 03/09/2026),
  gère l'ENT régional de Mathis (connexion EduConnect) et la connexion par QR code de l'appli PRONOTE.
- Pawnote (JavaScript) : autre bibliothèque non officielle, dernière version en 09/2025.
- Aucune bibliothèque PHP maintenue trouvée : le connecteur ne peut pas tourner dans l'appli PHP elle-même.
- Depuis mon environnement de travail, le serveur PRONOTE et l'ENT sont injoignables : aucun essai de connexion réel.
- Point de vigilance relevé pour M2 aussi : selon le forum OVH (non officiel), les accès réseau sortants des tâches
  planifiées et du SSH sont bridés ; la mise à jour horaire de l'agenda reste à vérifier.

### 13/11/2026 – Questions posées (en attente)

| N° | Question | Recommandation | Réponse de Mathis |
|---|---|---|---|
| 1 | Où tourne le connecteur PRONOTE : A Python sur OVH / B petit serveur en Europe / C GitHub Actions / D pas d'automatique | A si le test réussit, sinon B | En attente |
| 2 | Connexion : A QR code de l'appli PRONOTE / B identifiants ENT | A | En attente |
| 3 | Risque d'un outil non officiel accepté : A oui / B non | — | En attente |
| — | Commandes de test à lancer par Mathis en SSH (Python présent ? PRONOTE joignable ?) | — | En attente |
