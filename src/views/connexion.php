<?php
use Tdb\Vue;
?>
<div class="centre">
    <img src="/assets/icons/icone-192.png" alt="" width="84" height="84" class="logo">
    <h1>Tableau de bord</h1>
    <p class="discret">Espace personnel. Connexion par clé d’accès uniquement.</p>

<?php if ($aucuneCle): ?>
    <p class="alerte">Aucune clé d’accès n’est encore enregistrée. Utilise ton code de secours pour en créer une.</p>
<?php else: ?>
    <button type="button" class="bouton principal" data-action="connexion"><?= Vue::icone('cle') ?> Se connecter avec Face ID</button>
<?php endif; ?>

    <details class="secours"<?= $aucuneCle ? ' open' : '' ?>>
        <summary>Utiliser mon code de secours</summary>
        <form id="form-secours" class="pile">
            <label for="code">Code de secours (noté sur papier)</label>
            <input id="code" name="code" type="text" inputmode="text" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="XXXX-XXXX-XXXX-XXXX-XXXX" required>
            <button type="submit" class="bouton">Valider le code</button>
        </form>
    </details>
</div>
