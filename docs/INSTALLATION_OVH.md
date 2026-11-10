# Mise en ligne sur l'hébergement OVH (offre Pro)

Ce guide se fait une seule fois, de préférence sur un ordinateur (20 à 30 minutes).
Tu ne donnes aucun mot de passe à personne : tout se passe dans ton espace client OVH et sur GitHub.

> Les noms exacts des menus OVH peuvent avoir légèrement changé : ce guide a été écrit sans pouvoir
> consulter le site d'OVH. Si un écran ne correspond pas, envoie une capture et on ajuste.

## Étape 1 – Créer la sous-adresse

1. Espace client OVH → **Hébergements** → ton hébergement → onglet **Multisite**.
2. **Ajouter un domaine ou sous-domaine** → choisis ton domaine, puis un sous-domaine, par exemple `dashboard`.
3. **Dossier racine** : `tableaudebord` (un nouveau dossier, séparé de ton site actuel, qui n'est pas touché).
4. Coche **SSL** (c'est ce qui donne le cadenas https, obligatoire pour Face ID).
5. Valide, puis dans l'onglet **Informations générales**, rubrique certificat SSL, clique **Régénérer le certificat**.
   Compte jusqu'à 1 à 2 heures pour que l'adresse et le cadenas fonctionnent.

## Étape 2 – Vérifier la version de PHP

Onglet **Informations générales** → **Version PHP** → choisis **8.1 ou plus** (8.3 conseillé).
Si ton site actuel a besoin d'une version plus ancienne, arrête-toi et dis-le-moi.

## Étape 3 – Créer la base de données

1. Onglet **Bases de données** → **Créer une base de données** → type **MySQL**.
2. Choisis un nom d'utilisateur et un mot de passe (garde-les pour l'étape 5).
3. Une fois créée, note les 4 informations : **adresse du serveur**, **nom de la base**, **utilisateur**, **mot de passe**.

## Étape 4 – Envoyer l'appli sur l'hébergement

### Méthode A (recommandée) : liaison Git, les mises à jour deviennent automatiques

1. Onglet **Multisite** → sur la ligne de ta sous-adresse, menu **…** → **Associer Git**.
2. OVH affiche une **clé SSH publique** : copie-la.
3. Sur GitHub : dépôt `tableaudebord` → **Settings** → **Deploy keys** → **Add deploy key** → colle la clé,
   laisse « Allow write access » **décoché** (lecture seule).
4. Retour sur OVH : adresse du dépôt `git@github.com:frnmathis71-blip/tableaudebord.git`,
   branche `claude/life-dashboard-1v2bum`, puis valide.
5. OVH affiche une **adresse de webhook** : sur GitHub → **Settings** → **Webhooks** → **Add webhook** → colle-la
   (c'est ce qui prévient OVH à chaque mise à jour).
6. Lance le premier déploiement depuis OVH (menu **…** → **Déployer**).

### Méthode B (repli) : envoi manuel des fichiers

Demande-moi l'archive `.zip` de l'appli, puis envoie son contenu dans le dossier `tableaudebord`
avec un logiciel FTP (FileZilla). À refaire à chaque mise à jour.

## Étape 5 – Installation, sur ton iPhone, dans Safari

1. Ouvre `https://dashboard.tondomaine.fr` dans **Safari** : la page « Installation » s'affiche.
2. Recopie les 4 informations de la base (étape 3), puis **Installer**.
3. **Note le code de secours sur papier**, coche la case, puis **Créer ma clé d'accès** et valide avec Face ID.
4. Tu arrives sur « Aujourd'hui ».

Fais cette étape juste après la mise en ligne : tant qu'elle n'est pas faite, la page d'installation est ouverte à
quiconque connaît l'adresse. Une fois faite, elle est fermée définitivement.

## Étape 6 – Ajouter l'appli à l'écran d'accueil

Dans Safari : bouton **Partager** (carré avec une flèche) → **Sur l'écran d'accueil** → **Ajouter**.
Ouvre ensuite l'appli depuis son icône : elle te redemande Face ID une première fois (l'icône a sa propre mémoire,
séparée de Safari), puis seulement tous les 7 jours.

## En cas de problème

| Ce que tu vois | Cause probable | Quoi faire |
|---|---|---|
| Page blanche ou « erreur 500 » | Version de PHP trop ancienne | Étape 2 |
| « Site non installé » ou page OVH | L'adresse n'est pas encore active | Attendre, puis vérifier l'étape 1 |
| Pas de cadenas / Face ID ne se lance pas | Certificat SSL pas encore prêt | Régénérer le certificat, attendre |
| « Connexion à la base impossible » | Une des 4 informations est fausse | Les recopier depuis l'onglet Bases de données |
| Code de secours perdu avant d'avoir créé la clé | — | Vider la base (phpMyAdmin), supprimer `config/config.php`, recommencer l'étape 5 |
