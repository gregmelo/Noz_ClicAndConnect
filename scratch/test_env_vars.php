<?php
require __DIR__ . '/vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

echo "Keys from .env file:\n";
echo "PUBLIC:  " . $_ENV['VAPID_PUBLIC_KEY'] . "\n";
echo "PRIVATE: " . $_ENV['VAPID_PRIVATE_KEY'] . "\n";

echo "\nKeys from actual environment (if different):\n";
echo "PUBLIC:  " . getenv('VAPID_PUBLIC_KEY') . "\n";
