<?php
$lines = file('var/log/dev.log');
$output = [];
foreach ($lines as $line) {
    if (strpos($line, 'CRITICAL') !== false || strpos($line, 'Uncaught') !== false || strpos($line, 'exception') !== false) {
        $output[] = $line;
    }
}
file_put_contents('debug_exceptions.log', implode("", array_slice($output, -10)));
