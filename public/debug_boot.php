<?php
// public/debug_boot.php
use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once dirname(__DIR__).'/vendor/autoload.php';

echo "<h1>Amorce du Débogage Symfony</h1>";

try {
    echo "Initialisation du Kernel (prod, debug=vrai)...<br>";
    $kernel = new Kernel('prod', true);
    
    echo "Création de la requête...<br>";
    $request = Request::createFromGlobals();
    
    echo "Traitement de la requête (Handle)...<br>";
    $response = $kernel->handle($request);
    
    echo "Envoi de la réponse...<br>";
    $response->send();
    
    $kernel->terminate($request, $response);
    echo "<br>--- FIN LIBRE ---";
} catch (\Throwable $e) {
    echo "<hr><h2 style='color:red'>ERREUR DÉTECTÉE :</h2>";
    echo "<strong>Message :</strong> " . $e->getMessage() . "<br>";
    echo "<strong>Fichier :</strong> " . $e->getFile() . ":" . $e->getLine() . "<br>";
    echo "<h3>Trace :</h3><pre>" . $e->getTraceAsString() . "</pre>";
}
