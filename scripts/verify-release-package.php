<?php

declare(strict_types=1);

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php scripts/verify-release-package.php <archive> <version>\n");
    exit(2);
}

$archive = realpath($argv[1]);
if ($archive === false) {
    fwrite(STDERR, "Archive not found.\n");
    exit(2);
}

$expectedVersion = $argv[2];
$staging = sys_get_temp_dir().'/cuckooremind-verify-'.bin2hex(random_bytes(6));

$removeDirectory = static function (string $directory): void {
    if (! is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($directory);
};

$assertSafePath = static function (string $path): void {
    if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\')) {
        throw new RuntimeException('Archive contains an invalid path.');
    }

    if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path)) {
        throw new RuntimeException("Archive contains an absolute path: {$path}");
    }

    $segments = explode('/', trim($path, '/'));
    if (in_array('..', $segments, true) || in_array('.', $segments, true) || in_array('', $segments, true)) {
        throw new RuntimeException("Archive contains path traversal: {$path}");
    }
};

mkdir($staging, 0700, true);

try {
    $zip = new ZipArchive;
    if ($zip->open($archive) !== true) {
        throw new RuntimeException('Unable to open archive.');
    }

    $entries = [];
    $seenEntries = [];
    $expandedSize = 0;

    try {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $name = (string) ($stat['name'] ?? '');
            $assertSafePath($name);

            $canonicalName = rtrim($name, '/');
            if (isset($seenEntries[$canonicalName])) {
                throw new RuntimeException("Archive contains a duplicate path: {$name}");
            }
            $seenEntries[$canonicalName] = true;

            $expandedSize += (int) ($stat['size'] ?? 0);
            if ($expandedSize > 600 * 1024 * 1024) {
                throw new RuntimeException('Expanded archive exceeds the size limit.');
            }

            $operatingSystem = 0;
            $attributes = 0;
            if ($zip->getExternalAttributesIndex($index, $operatingSystem, $attributes)) {
                $fileType = ($attributes >> 16) & 0170000;
                if ($fileType === 0120000) {
                    throw new RuntimeException("Archive contains a symbolic link: {$name}");
                }
            }

            if (! str_ends_with($name, '/')) {
                $entries[] = $name;
            }
        }

        if (! $zip->extractTo($staging)) {
            throw new RuntimeException('Unable to extract archive.');
        }
    } finally {
        $zip->close();
    }

    $manifestPath = $staging.'/update-manifest.json';
    if (! is_file($manifestPath)) {
        throw new RuntimeException('update-manifest.json is missing.');
    }

    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    foreach (['schema', 'version', 'minimum_upgradable_version', 'minimum_updater_version', 'minimum_php', 'files', 'install_only', 'preserve', 'remove'] as $key) {
        if (! array_key_exists($key, $manifest)) {
            throw new RuntimeException("Manifest key is missing: {$key}");
        }
    }

    if ($manifest['schema'] !== 1
        || $manifest['version'] !== $expectedVersion
        || ! is_array($manifest['files'])
        || ! is_array($manifest['install_only'])
        || ! is_array($manifest['preserve'])
        || ! is_array($manifest['remove'])) {
        throw new RuntimeException('Manifest structure or version is invalid.');
    }

    foreach (['version', 'minimum_upgradable_version', 'minimum_updater_version'] as $key) {
        if (! is_string($manifest[$key]) || preg_match('/^v?\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $manifest[$key]) !== 1) {
            throw new RuntimeException("Manifest version is invalid: {$key}");
        }
    }

    if (! is_string($manifest['minimum_php']) || preg_match('/^\d+\.\d+\.\d+$/', $manifest['minimum_php']) !== 1) {
        throw new RuntimeException('Manifest minimum_php is invalid.');
    }

    foreach (array_merge(array_keys($manifest['files']), $manifest['install_only'], $manifest['preserve'], $manifest['remove']) as $path) {
        if (! is_string($path)) {
            throw new RuntimeException('Manifest contains a non-string path.');
        }
        $assertSafePath($path);
    }

    if (array_key_exists('update-manifest.json', $manifest['files'])) {
        throw new RuntimeException('Manifest must not contain itself in files.');
    }

    $knownFiles = array_keys($manifest['files']);
    foreach ($entries as $entry) {
        if ($entry !== 'update-manifest.json' && ! in_array($entry, $knownFiles, true)) {
            throw new RuntimeException("Archive contains a file missing from manifest: {$entry}");
        }
    }

    foreach ($manifest['files'] as $path => $digest) {
        if (! is_string($digest) || preg_match('/^sha256:[a-f0-9]{64}$/i', $digest) !== 1) {
            throw new RuntimeException("Manifest contains an invalid SHA-256: {$path}");
        }

        $file = $staging.'/'.$path;
        if (! is_file($file) || ! hash_equals(strtolower($digest), 'sha256:'.hash_file('sha256', $file))) {
            throw new RuntimeException("File hash does not match manifest: {$path}");
        }
    }

    fwrite(STDOUT, "Verified {$manifest['version']} (".count($manifest['files'])." files).\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
} finally {
    $removeDirectory($staging);
}
