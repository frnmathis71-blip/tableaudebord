<?php
use Tdb\Vue;
?>
<div class="centre">
    <h1><?= Vue::e($titre) ?></h1>
    <p class="discret"><?= Vue::e($message) ?></p>
    <a class="bouton" href="/">Revenir à l’accueil</a>
</div>
