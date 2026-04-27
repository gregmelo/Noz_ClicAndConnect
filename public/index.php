<?php
// public/index.php
use App\Kernel;
// Ce die() DOIT arrêter le site. Si le site s'affiche encore, 
// c'est que le serveur utilise une version en mémoire.
// die("DEBUG: Si vous voyez ce message, le cache est vidé.");
require_once dirname(__DIR__).'/vendor/autoload_runtime.php';
date_default_timezone_set('Europe/Paris');
return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};