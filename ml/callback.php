<?php
header('Content-Type: text/plain; charset=utf-8');

echo "OK callback\n";
echo "code = " . ($_GET['code'] ?? 'NO_CODE') . "\n";
echo "state = " . ($_GET['state'] ?? 'NO_STATE') . "\n";
