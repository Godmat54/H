<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run from command line: php install.php\n");
    exit(1);
}

if (PHP_VERSION_ID < 80100) {
    fwrite(STDERR, "PHP 8.1 or newer is required. Current: " . PHP_VERSION . "\n");
    exit(1);
}

foreach (['curl','json','mysqli'] as $ext) {
    if (!extension_loaded($ext)) {
        fwrite(STDERR, "Missing PHP extension: {$ext}\n");
        exit(1);
    }
}

$db = require __DIR__ . '/config/database.php';
$sql = file_get_contents(__DIR__ . '/database/nhif_schema.sql');
if ($sql === false) {
    throw new RuntimeException('Unable to read NHIF database schema.');
}

if (!$db->multi_query($sql)) {
    throw new RuntimeException('NHIF schema installation failed: ' . $db->error);
}
do {
    if ($result = $db->store_result()) {
        $result->free();
    }
} while ($db->more_results() && $db->next_result());

echo "NHIF database tables installed successfully.\n";
echo "Next: configure NHIF_USERNAME, NHIF_PASSWORD and NHIF_FACILITY_CODE.\n";
echo "Then add the links in integration/FACEBOX_LINKS.html to the existing Faraja menus.\n";
