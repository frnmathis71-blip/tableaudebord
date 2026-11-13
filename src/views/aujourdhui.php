<?php
use Tdb\Planning;
use Tdb\Vue;

$jour = $semaine[0];
?>
<header class="entete">
    <div>
        <p class="surtitre"><?= Vue::e($date) ?></p>
        <h1>Aujourd’hui</h1>
    </div>
<?php if ($agenda['configure']): ?>
    <button type="button" class="outil" data-action="actualiser" aria-label="Actualiser l’agenda"><?= Vue::icone('actualiser') ?></button>
<?php endif; ?>
</header>

<?php if (!$agenda['configure']): ?>
<section class="carte vide" aria-label="Ta journée">
    <p class="vide-titre">Aucun agenda connecté</p>
    <p class="discret">Ajoute ton agenda iCloud pour voir ta journée ici.</p>
    <a class="bouton principal" href="/reglages#agenda">Connecter mon agenda</a>
</section>
<?php else: ?>
<section class="carte journee" id="journee" data-agenda="1" aria-label="Ta journée">
    <div class="carte-titre">
        <h2>Ta journée</h2>
        <span class="pastille libre"><?= Vue::e(Planning::duree($jour['libre'])) ?> de libre</span>
    </div>
<?php if ($jour['conflits'] > 0): ?>
    <p class="alerte">Attention : <?= (int) $jour['conflits'] ?> événements se chevauchent aujourd’hui.</p>
<?php endif; ?>
<?php if ($jour['evenements'] === []): ?>
    <p class="discret">Rien de prévu aujourd’hui.</p>
<?php else: ?>
    <ul class="evenements">
<?php foreach ($jour['evenements'] as $e): ?>
<?php require __DIR__ . '/_evenement.php'; ?>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php require __DIR__ . '/_etat_agenda.php'; ?>
</section>
<?php endif; ?>

<div class="titre-section">
    <h2>Modules</h2>
    <button type="button" class="bouton-lien" id="organiser" aria-pressed="false">Organiser</button>
</div>

<ul class="modules" id="modules">
<?php foreach ($modules as $m): ?>
    <li class="carte module<?= $m['visible'] ? '' : ' masque' ?>" data-id="<?= Vue::e($m['id']) ?>" data-visible="<?= $m['visible'] ? '1' : '0' ?>">
        <div class="module-ligne">
            <div class="module-icone"><?= Vue::icone($m['icone']) ?></div>
            <div class="module-texte">
                <h3><?= Vue::e($m['titre']) ?></h3>
                <p class="discret"><?= Vue::e($m['question']) ?></p>
<?php if ($m['mission'] !== ''): ?>
                <span class="pastille">Arrive avec la mission <?= Vue::e($m['mission']) ?></span>
<?php endif; ?>
            </div>
            <div class="module-outils">
                <button type="button" class="outil" data-geste="haut" aria-label="Monter <?= Vue::e($m['titre']) ?>"><?= Vue::icone('haut') ?></button>
                <button type="button" class="outil" data-geste="bas" aria-label="Descendre <?= Vue::e($m['titre']) ?>"><?= Vue::icone('bas') ?></button>
                <button type="button" class="outil" data-geste="voir" aria-pressed="<?= $m['visible'] ? 'true' : 'false' ?>" aria-label="Afficher <?= Vue::e($m['titre']) ?>"><?= Vue::icone('oeil') ?></button>
            </div>
        </div>
<?php if ($m['id'] === 'agenda'): ?>
        <div class="module-contenu">
<?php if (!$agenda['configure']): ?>
            <a class="bouton" href="/reglages#agenda">Connecter mon agenda</a>
<?php else: ?>
            <ul class="semaine">
<?php foreach ($semaine as $i => $j): ?>
                <li>
                    <details<?= $i === 0 ? ' open' : '' ?>>
                        <summary>
                            <span class="jour-nom"><?= $i === 0 ? 'Aujourd’hui' : ($i === 1 ? 'Demain' : Vue::e(ucfirst(Vue::dateLongue($j['jour']->getTimestamp())))) ?></span>
                            <span class="jour-infos">
<?php if ($j['conflits'] > 0): ?>
                                <span class="badge-conflit"><?= (int) $j['conflits'] ?> en conflit</span>
<?php endif; ?>
                                <span class="discret"><?= count($j['evenements']) ?> évén. · <?= Vue::e(Planning::duree($j['libre'])) ?> libre</span>
                            </span>
                        </summary>
<?php if ($j['evenements'] === []): ?>
                        <p class="discret vide-jour">Rien de prévu.</p>
<?php else: ?>
                        <ul class="evenements">
<?php foreach ($j['evenements'] as $e): ?>
<?php require __DIR__ . '/_evenement.php'; ?>
<?php endforeach; ?>
                        </ul>
<?php endif; ?>
                    </details>
                </li>
<?php endforeach; ?>
            </ul>
            <p class="discret aide-libre">Temps libre compté entre <?= Planning::HEURE_DEBUT ?> h et <?= Planning::HEURE_FIN ?> h.</p>
<?php endif; ?>
        </div>
<?php endif; ?>
    </li>
<?php endforeach; ?>
</ul>
<p class="discret aide-organiser" id="aide-organiser" hidden>Utilise les flèches pour changer l’ordre et l’œil pour masquer un module. C’est enregistré automatiquement.</p>
