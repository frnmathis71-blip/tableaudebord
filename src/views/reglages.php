<?php
use Tdb\Vue;
?>
<header class="entete"><div><h1>Réglages</h1></div></header>

<section class="carte pile" id="agenda">
    <h2>Agenda iCloud</h2>
    <p class="discret">Lecture seule, par le lien de partage de chaque calendrier. Mise à jour à l’ouverture de l’appli et toutes les heures.</p>
<?php if ($calendriers !== []): ?>
    <ul class="liste">
<?php foreach ($calendriers as $cal): ?>
        <li>
            <div>
                <strong><?= Vue::e($cal['name']) ?></strong>
<?php if ($cal['last_error'] !== null): ?>
                <span class="texte-erreur"><?= Vue::e($cal['last_error']) ?></span>
<?php endif; ?>
                <span class="discret"><?= (int) $cal['event_count'] ?> événements · mis à jour le <?= Vue::e(Vue::dateCourte($cal['last_sync_at'])) ?></span>
            </div>
            <button type="button" class="bouton petit danger" data-action="supprimer-calendrier" data-id="<?= (int) $cal['id'] ?>" data-nom="<?= Vue::e($cal['name']) ?>">Retirer</button>
        </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
    <details class="secours"<?= $calendriers === [] ? ' open' : '' ?>>
        <summary>Ajouter un calendrier</summary>
        <ol class="etapes discret">
            <li>Sur l’iPhone, ouvre l’app <strong>Calendrier</strong>, puis <strong>Calendriers</strong> en bas.</li>
            <li>Touche le <strong>ⓘ</strong> à côté du calendrier, active <strong>Calendrier public</strong>.</li>
            <li>Touche <strong>Partager le lien…</strong> puis <strong>Copier</strong>, et colle-le ici.</li>
        </ol>
        <form id="form-calendrier" class="pile">
            <label for="cal-nom">Nom</label>
            <input id="cal-nom" name="nom" type="text" maxlength="60" autocomplete="off" placeholder="Perso" required>
            <label for="cal-lien">Lien de partage</label>
            <input id="cal-lien" name="lien" type="text" inputmode="url" autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="webcal://p00-caldav.icloud.com/published/…" required>
            <button type="submit" class="bouton principal">Ajouter</button>
        </form>
        <p class="discret">Le lien est enregistré chiffré. Toute personne qui l’obtiendrait pourrait lire ce calendrier : ne le partage pas.</p>
    </details>
</section>

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
