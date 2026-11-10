<?php
use Tdb\Vue;
?>
<header class="entete"><div><h1>Réglages</h1></div></header>

<section class="carte pile">
    <h2>Clés d’accès</h2>
    <p class="discret">Les appareils qui peuvent ouvrir l’appli avec Face ID.</p>
    <ul class="liste">
<?php foreach ($cles as $cle): ?>
        <li>
            <div>
                <strong><?= Vue::e($cle['label']) ?></strong>
                <span class="discret">Créée le <?= Vue::e(Vue::dateCourte($cle['created_at'])) ?> · dernière utilisation : <?= Vue::e(Vue::dateCourte($cle['last_used_at'])) ?></span>
            </div>
<?php if (count($cles) > 1): ?>
            <button type="button" class="bouton petit danger" data-action="supprimer-cle" data-id="<?= (int) $cle['id'] ?>">Supprimer</button>
<?php endif; ?>
        </li>
<?php endforeach; ?>
    </ul>
    <a class="bouton" href="/cle">Ajouter une clé d’accès</a>
</section>

<section class="carte pile">
    <h2>Appareils connectés</h2>
    <p class="discret">Chaque appareil reste connecté <?= (int) $jours ?> jours, puis Face ID est redemandé.</p>
    <ul class="liste">
<?php foreach ($sessions as $s): ?>
        <li>
            <div>
                <strong><?= Vue::e(Vue::appareil($s['user_agent'])) ?><?= $s['token_hash'] === $sessionActuelle ? ' (cet appareil)' : '' ?></strong>
                <span class="discret">Connecté le <?= Vue::e(Vue::dateCourte($s['created_at'])) ?> · actif le <?= Vue::e(Vue::dateCourte($s['last_seen_at'])) ?></span>
            </div>
<?php if ($s['token_hash'] !== $sessionActuelle): ?>
            <button type="button" class="bouton petit danger" data-action="revoquer" data-id="<?= Vue::e(substr($s['token_hash'], 0, 16)) ?>">Déconnecter</button>
<?php endif; ?>
        </li>
<?php endforeach; ?>
    </ul>
<?php if (count($sessions) > 1): ?>
    <button type="button" class="bouton" data-action="revoquer-autres">Déconnecter tous les autres appareils</button>
<?php endif; ?>
</section>

<section class="carte pile">
    <h2>Code de secours</h2>
    <p class="discret">À utiliser si tu perds ton iPhone. En créer un nouveau annule l’ancien.</p>
    <p class="code-secours" id="code-secours" hidden></p>
    <button type="button" class="bouton" data-action="nouveau-code">Créer un nouveau code de secours</button>
</section>

<section class="pile">
    <button type="button" class="bouton danger" data-action="deconnexion">Se déconnecter</button>
    <p class="discret centre-texte">Tableau de bord · socle M1</p>
</section>
