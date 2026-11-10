<?php
/** Création d'une clé d'accès : après l'installation, après le code de secours, ou depuis les réglages. */
use Tdb\Vue;

$suite = $connecte ? '/reglages' : '/';
?>
<div class="centre">
    <h1><?= $codeSecours !== null ? 'Installation réussie' : 'Nouvelle clé d’accès' ?></h1>

    <section id="bloc-code" class="carte pile"<?= $codeSecours === null ? ' hidden' : '' ?>>
        <h2>Ton code de secours</h2>
        <p class="discret">Il te permettra de revenir si tu perds ton iPhone. Il ne sera <strong>plus jamais affiché</strong> : note-le sur papier et range-le en lieu sûr. Ne le garde pas en photo.</p>
        <p class="code-secours" id="code-secours"><?= Vue::e($codeSecours ?? '') ?></p>
        <label class="case"><input type="checkbox" id="code-note"> J’ai noté mon code de secours</label>
        <a class="bouton principal" id="continuer" href="/" hidden>Continuer</a>
    </section>

    <section id="bloc-cle" class="pile">
        <p class="discret">Une clé d’accès remplace le mot de passe : tu te connectes avec Face ID, et rien ne peut être deviné ou volé par un faux site.</p>
        <button type="button" class="bouton principal" data-action="creer-cle" data-suite="<?= Vue::e($suite) ?>"<?= $codeSecours !== null ? ' disabled' : '' ?>><?= Vue::icone('cle') ?> Créer ma clé d’accès</button>
<?php if ($connecte): ?>
        <a class="bouton-lien" href="/reglages">Annuler</a>
<?php endif; ?>
    </section>
</div>
