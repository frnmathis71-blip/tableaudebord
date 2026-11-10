<?php
/** Habillage commun à toutes les pages. */
use Tdb\Vue;

$connecte = $connecte ?? false;
$onglet = $onglet ?? '';
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= Vue::e($titre ?? 'Tableau de bord') ?> · Tableau de bord</title>
<meta name="color-scheme" content="dark">
<meta name="theme-color" content="#0e1116">
<meta name="robots" content="noindex, nofollow">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Tableau de bord">
<meta name="format-detection" content="telephone=no">
<meta name="csrf" content="<?= Vue::e($csrf ?? '') ?>">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="apple-touch-icon" href="/assets/icons/icone-180.png">
<link rel="icon" type="image/png" sizes="192x192" href="/assets/icons/icone-192.png">
<link rel="stylesheet" href="<?= Vue::e(Vue::fichier('/assets/app.css')) ?>">
<script src="<?= Vue::e(Vue::fichier('/assets/app.js')) ?>" defer></script>
</head>
<body class="<?= $connecte ? 'avec-onglets' : '' ?>">
<main class="page">
<?= $contenu ?>
</main>
<?php if ($connecte): ?>
<nav class="onglets" aria-label="Navigation principale">
    <a href="/" class="onglet<?= $onglet === 'jour' ? ' actif' : '' ?>"<?= $onglet === 'jour' ? ' aria-current="page"' : '' ?>>
        <?= Vue::icone('jour') ?><span>Aujourd’hui</span>
    </a>
    <a href="/reglages" class="onglet<?= $onglet === 'reglages' ? ' actif' : '' ?>"<?= $onglet === 'reglages' ? ' aria-current="page"' : '' ?>>
        <?= Vue::icone('reglages') ?><span>Réglages</span>
    </a>
</nav>
<?php endif; ?>
<div class="message" id="message" role="status" aria-live="polite" hidden></div>
</body>
</html>
