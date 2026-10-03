<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run: php apply_to_faraja.php /path/to/FARAJA\n");
    exit(1);
}

$target = $argv[1] ?? '';
if ($target === '' || !is_dir($target)) {
    fwrite(STDERR, "Target FARAJA folder not found.\n");
    exit(1);
}

$target = rtrim(realpath($target) ?: $target, DIRECTORY_SEPARATOR);
$source = __DIR__;

$folders = ['Admin','Reception','Doctor','Pharmacy','Lab','Dental','Eyes','nhif','includes','config','database','integration'];

function copyTreeSafe(string $from, string $to): void {
    if (!is_dir($from)) return;
    if (!is_dir($to) && !mkdir($to, 0775, true) && !is_dir($to)) {
        throw new RuntimeException("Cannot create {$to}");
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
        $relative = substr($item->getPathname(), strlen($from) + 1);
        $dest = $to . DIRECTORY_SEPARATOR . $relative;
        if ($item->isDir()) {
            if (!is_dir($dest)) mkdir($dest, 0775, true);
            continue;
        }
        if (file_exists($dest)) {
            echo "KEEP existing: {$dest}\n";
            continue;
        }
        copy($item->getPathname(), $dest);
        echo "ADD: {$dest}\n";
    }
}

foreach ($folders as $folder) {
    copyTreeSafe($source . DIRECTORY_SEPARATOR . $folder, $target . DIRECTORY_SEPARATOR . $folder);
}

$compatSource = $source . '/includes/php81_compat.php';
$compatDest = $target . '/includes/php81_compat.php';
if (is_file($compatSource) && !file_exists($compatDest)) copy($compatSource, $compatDest);

echo "\nNHIF/PHP 8.1.25 support files copied without overwriting existing template files.\n";
echo "Use integration/FACEBOX_LINKS.html to insert NHIF links into the current menus.\n";
echo "Load includes/php81_compat.php early in the legacy shared connection/bootstrap file while old mysql_* calls remain.\n";