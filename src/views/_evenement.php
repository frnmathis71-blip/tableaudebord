<?php
/** Une ligne d'événement (utilisée par la journée et la semaine). */
use Tdb\Planning;
use Tdb\Vue;

/** @var array $e */
?>
<li class="evenement<?= $e['conflit'] ? ' en-conflit' : '' ?>">
    <span class="ev-heure"><?php if ($e['journee']): ?>Journée<?php else: ?><?= Vue::e(Planning::heure($e['debut'])) ?><?php if ($e['fin'] > $e['debut']): ?><small><?= Vue::e(Planning::heure($e['fin'])) ?></small><?php endif; ?><?php endif; ?></span>
    <span class="ev-texte">
        <strong><?= Vue::e($e['titre']) ?></strong>
<?php if ($e['lieu'] !== ''): ?>
        <span class="discret"><?= Vue::e($e['lieu']) ?></span>
<?php endif; ?>
<?php if ($e['conflit']): ?>
        <span class="badge-conflit">Conflit d’horaire</span>
<?php endif; ?>
    </span>
</li>
