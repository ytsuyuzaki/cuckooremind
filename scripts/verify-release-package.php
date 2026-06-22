<?php

declare(strict_types=1);

use App\Services\Updates\UpdatePackageService;
use Illuminate\Contracts\Console\Kernel;

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php scripts/verify-release-package.php <archive> <version>\n");
    exit(2);
}

$archive = realpath($argv[1]);
if ($archive === false) {
    fwrite(STDERR, "Archive not found.\n");
    exit(2);
}

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$staging = sys_get_temp_dir().'/cuckooremind-verify-'.bin2hex(random_bytes(6));
mkdir($staging, 0700, true);

try {
    $manifest = $app->make(UpdatePackageService::class)->extractAndValidate($archive, $staging, $argv[2]);
    fwrite(STDOUT, "Verified {$manifest['version']} (".count($manifest['files'])." files).\n");
} finally {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($staging, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($staging);
}
