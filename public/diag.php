<?php
/**
 * diag.php - Extended diagnostic
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>PHP Diagnostic (Root)</h1>";
echo "Current Time: " . date('Y-m-d H:i:s') . "<br>";
$rootDir = dirname(__DIR__);
echo "Root Directory: " . $rootDir . "<br>";

echo "<h2>Root File List</h2>";
$output = [];
$return_var = 0;
exec('ls -Fa ' . escapeshellarg($rootDir), $output, $return_var);
echo "<pre>" . implode("\n", $output) . "</pre>";

echo "<h2>Environment Check</h2>";
if (file_exists($rootDir . '/.env')) {
    echo "✅ .env exists<br>";
} else {
    echo "❌ .env MISSING<br>";
}

if (file_exists($rootDir . '/composer.json')) {
    echo "✅ composer.json exists<br>";
} else {
    echo "❌ composer.json MISSING<br>";
}

if (is_dir($rootDir . '/vendor')) {
    echo "✅ vendor directory exists<br>";
} else {
    echo "❌ vendor directory MISSING<br>";
}
?>
