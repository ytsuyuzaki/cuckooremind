<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$version = $argv[1] ?? trim((string) file_get_contents($root.'/.version'));

if (! preg_match('/^v?\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version)) {
    fwrite(STDERR, "Invalid release version: {$version}\n");
    exit(1);
}

$version = str_starts_with($version, 'v') ? $version : 'v'.$version;
$versionFile = trim((string) file_get_contents($root.'/.version'));

if ($versionFile !== $version) {
    fwrite(STDERR, ".version ({$versionFile}) does not match release version ({$version}).\n");
    exit(1);
}

$excludedPrefixes = [
    '.git/',
    'node_modules/',
    'tests/',
    'coverage/',
];
$excludedFiles = [
    '.env.backup',
    '.env.production',
    'cuckooremind.zip',
    'update-manifest.json',
];
$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (! $file->isFile() || $file->isLink()) {
        continue;
    }

    $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

    if (in_array($path, $excludedFiles, true)) {
        continue;
    }

    foreach ($excludedPrefixes as $prefix) {
        if (str_starts_with($path, $prefix)) {
            continue 2;
        }
    }

    $files[$path] = 'sha256:'.hash_file('sha256', $file->getPathname());
}

ksort($files);

$remove = [];
$removePath = $root.'/release-remove.json';
if (is_file($removePath)) {
    $remove = json_decode((string) file_get_contents($removePath), true, 512, JSON_THROW_ON_ERROR);
    if (! is_array($remove) || array_filter($remove, fn ($path): bool => ! is_string($path))) {
        fwrite(STDERR, "release-remove.json must be an array of paths.\n");
        exit(1);
    }
}

$manifest = [
    'schema' => 1,
    'version' => $version,
    'minimum_upgradable_version' => 'v0.0.1',
    'minimum_updater_version' => 'v0.0.3',
    'minimum_php' => '8.2.0',
    'files' => $files,
    'install_only' => ['.env', 'storage/db.sqlite'],
    'preserve' => ['.env', 'storage/'],
    'remove' => array_values($remove),
    'migrate' => true,
];

$encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
file_put_contents($root.'/update-manifest.json', $encoded);
