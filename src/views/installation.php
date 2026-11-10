<?php
use Tdb\Vue;
?>
<div class="centre">
    <h1>Installation</h1>
    <p class="discret">Première mise en route de ton tableau de bord à l’adresse <strong><?= Vue::e($adresse) ?></strong>. Recopie les informations de ta base de données, visibles dans ton espace client OVH.</p>
<?php if ($erreur !== ''): ?>
    <p class="alerte" role="alert"><?= Vue::e($erreur) ?></p>
<?php endif; ?>
    <form method="post" action="/installation" class="pile carte">
        <label for="db_host">Adresse du serveur de la base</label>
        <input id="db_host" name="db_host" type="text" autocomplete="off" autocapitalize="none" spellcheck="false" required value="<?= Vue::e($saisie['db_host'] ?? '') ?>" placeholder="exemple.mysql.db">
        <label for="db_name">Nom de la base</label>
        <input id="db_name" name="db_name" type="text" autocomplete="off" autocapitalize="none" spellcheck="false" required value="<?= Vue::e($saisie['db_name'] ?? '') ?>">
        <label for="db_user">Nom d’utilisateur</label>
        <input id="db_user" name="db_user" type="text" autocomplete="off" autocapitalize="none" spellcheck="false" required value="<?= Vue::e($saisie['db_user'] ?? '') ?>">
        <label for="db_pass">Mot de passe de la base</label>
        <input id="db_pass" name="db_pass" type="password" autocomplete="off" required>
        <button type="submit" class="bouton principal">Installer</button>
    </form>
    <p class="discret">Ces informations restent sur ton hébergement, dans un fichier protégé. Elles ne sont envoyées nulle part ailleurs.</p>
</div>
