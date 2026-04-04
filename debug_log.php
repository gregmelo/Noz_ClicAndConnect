<?php
$logPath = 'var/log/dev.log';
if (!file_exists($logPath)) {
    echo "Log file not found.";
    exit;
}

$file = new SplFileObject($logPath, "r");
$file->seek(PHP_INT_MAX);
$lastLine = $file->key();

$startLine = max(0, $lastLine - 250);
$file->seek($startLine);

while (!$file->eof()) {
    $line = $file->current();
    if (strpos($line, 'CRITICAL') !== false || strpos($line, 'Uncaught') !== false || strpos($line, '500') !== false) {
        echo $line;
    }
    $file->next();
}
