<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);

chdir($projectRoot);

$coverageDriver = match (true) {
    extension_loaded('xdebug') => 'xdebug',
    extension_loaded('pcov') => 'pcov',
    default => null,
};

if ($coverageDriver === null) {
    fwrite(STDERR, <<<'TEXT'
Code coverage requires either the Xdebug or PCOV PHP extension.

Install one of them and run this command again:
  composer test:coverage

TEXT);

    exit(1);
}

$coverageDir = $projectRoot.'/coverage';

if (! is_dir($coverageDir) && ! mkdir($coverageDir, 0777, true) && ! is_dir($coverageDir)) {
    fwrite(STDERR, "Failed to create coverage output directory: {$coverageDir}\n");
    exit(1);
}

$testingDatabase = $projectRoot.'/database/database_testing.sqlite';

if (! file_exists($testingDatabase) && ! touch($testingDatabase)) {
    fwrite(STDERR, "Failed to create testing database file: {$testingDatabase}\n");
    exit(1);
}

echo "Using {$coverageDriver} for code coverage.\n";
echo "Coverage reports will be written to ./coverage\n";

run([
    PHP_BINARY,
    'artisan',
    'optimize:clear',
]);

run([
    PHP_BINARY,
    'artisan',
    'migrate',
    '--env=testing',
    '--force',
]);

$testCommand = [PHP_BINARY];

if ($coverageDriver === 'xdebug') {
    putenv('XDEBUG_MODE=coverage');
    $_ENV['XDEBUG_MODE'] = 'coverage';
    $_SERVER['XDEBUG_MODE'] = 'coverage';
}

array_push(
    $testCommand,
    'artisan',
    'test',
    '--coverage-html=coverage/html',
    '--coverage-clover=coverage/clover.xml',
    '--coverage-text'
);

run($testCommand);

function run(array $command): void
{
    $escapedCommand = implode(' ', array_map('escapeshellarg', $command));

    passthru($escapedCommand, $exitCode);

    if ($exitCode !== 0) {
        exit($exitCode);
    }
}
