<?php
header('Content-Type: text/plain');

$pythonPath = 'C:\\Users\\shreya\\hirewise\\.venv311\\Scripts\\python.exe';

$command = escapeshellarg($pythonPath) .
    ' -c ' .
    escapeshellarg("import spacy; spacy.load('en_core_web_sm'); print('SPACY APACHE OK')");

echo shell_exec($command . ' 2>&1');
?>