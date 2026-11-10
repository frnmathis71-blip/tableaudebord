<?php
use Tdb\Vue;
?>
<header class="entete">
    <div>
        <p class="surtitre"><?= Vue::e($date) ?></p>
        <h1>Aujourd’hui</h1>
    </div>
</header>

<section class="carte vide" aria-label="Ta journée">
    <p class="vide-titre">Rien à afficher pour l’instant</p>
    <p class="discret">Ta journée apparaîtra ici dès que les premières sources seront connectées.</p>
</section>

<div class="titre-section">
    <h2>Modules</h2>
    <button type="button" class="bouton-lien" id="organiser" aria-pressed="false">Organiser</button>
</div>

<ul class="modules" id="modules">
<?php foreach ($modules as $m): ?>
    <li class="carte module<?= $m['visible'] ? '' : ' masque' ?>" data-id="<?= Vue::e($m['id']) ?>" data-visible="<?= $m['visible'] ? '1' : '0' ?>">
        <div class="module-icone"><?= Vue::icone($m['icone']) ?></div>
        <div class="module-texte">
            <h3><?= Vue::e($m['titre']) ?></h3>
            <p class="discret"><?= Vue::e($m['question']) ?></p>
            <span class="pastille">Arrive avec la mission <?= Vue::e($m['mission']) ?></span>
        </div>
        <div class="module-outils">
            <button type="button" class="outil" data-geste="haut" aria-label="Monter <?= Vue::e($m['titre']) ?>"><?= Vue::icone('haut') ?></button>
            <button type="button" class="outil" data-geste="bas" aria-label="Descendre <?= Vue::e($m['titre']) ?>"><?= Vue::icone('bas') ?></button>
            <button type="button" class="outil" data-geste="voir" aria-pressed="<?= $m['visible'] ? 'true' : 'false' ?>" aria-label="Afficher <?= Vue::e($m['titre']) ?>"><?= Vue::icone('oeil') ?></button>
        </div>
    </li>
<?php endforeach; ?>
</ul>
<p class="discret aide-organiser" id="aide-organiser" hidden>Utilise les flèches pour changer l’ordre et l’œil pour masquer un module. C’est enregistré automatiquement.</p>
