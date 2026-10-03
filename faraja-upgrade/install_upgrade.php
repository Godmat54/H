<?php
declare(strict_types=1);

/**
 * CLI helper for installing the generic Faraja upgrade layer beside an
 * EXISTING extracted Faraja system.
 *
 * Usage:
 *   php install_upgrade.php /path/to/FARAJASYS
 *
 * It does not modify patient data and does not overwrite existing files.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this installer from the command line.\n");
    exit(1);
}

$target = $argv[1] ?? '';
if ($target === '' || !is_dir($target)) {
    fwrite(STDERR, "Usage: php install_upgrade.php /path/to/FARAJASYS\n");
    exit(1);
}

$target = rtrim(realpath($target) ?: $target, DIRECTORY_SEPARATOR);
$source = __DIR__;

$copies = [
    [$source . '/src/Nhif', $target . '/includes/nhif/src/Nhif'],
    [$source . '/public/nhif', $target . '/nhif'],
    [$source . '/compat', $target . '/includes/php85/compat'],
];

function copyTree(string $from, string $to): void
{
    if (!is_dir($from)) {
        return;
    }
    if (!is_dir($to) && !mkdir($to, 0775, true) && !is_dir($to)) {
        throw new RuntimeException("Cannot create {$to}");
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($from) + 1);
        $dest = $to . DIRECTORY_SEPARATOR . $relative;

        if ($item->isDir()) {
            if (!is_dir($dest)) {
                mkdir($dest, 0775, true);
            }
            continue;
        }

        if (file_exists($dest)) {
            echo "SKIP existing: {$dest}\n";
            continue;
        }

        copy($item->getPathname(), $dest);
        echo "ADD: {$dest}\n";
    }
}

foreach ($copies as [$from, $to]) {
    copyTree($from, $to);
}

$configDir = $target . '/config';
if (!is_dir($configDir)) {
    mkdir($configDir, 0775, true);
}

if (!file_exists($configDir . '/nhif.example.php')) {
    copy($source . '/config/nhif.example.php', $configDir . '/nhif.example.php');
}

if (!file_exists($target . '/NHIF_ECLAIMS_MIGRATION.sql')) {
    copy($source . '/database/001_nhif_eclaims.sql', $target . '/NHIF_ECLAIMS_MIGRATION.sql');
}

echo "\nGeneric PHP 8.5/NHIF files installed without overwriting existing files.\n";
echo "Next: wire bootstrap.php into the existing shared connection/include file,\n";
echo "run the SQL migration on a BACKUP database, then integrate patient/visit IDs.\n";
