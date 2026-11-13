<?php
/** Date de mise à jour de l'agenda, ou message si iCloud ne répond plus (mode dégradé). */
use Tdb\Vue;

?>
<?php if ($agenda['erreurs'] !== []): ?>
<p class="etat-source erreur" role="status">
    Agenda non mis à jour<?= count($agenda['erreurs']) === 1 ? ' (« ' . Vue::e($agenda['erreurs'][0]['name']) . ' »)' : '' ?> :
    <?= Vue::e($agenda['erreurs'][0]['last_error']) ?> Dernière mise à jour : <?= Vue::e(Vue::dateCourte($agenda['derniere_maj'])) ?>.
</p>
<?php else: ?>
<p class="etat-source">Agenda mis à jour le <?= Vue::e(Vue::dateCourte($agenda['derniere_maj'])) ?></p>
<?php endif; ?>
