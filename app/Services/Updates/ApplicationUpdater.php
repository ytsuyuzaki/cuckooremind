<?php

namespace App\Services\Updates;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ApplicationUpdater
{
    /** @var resource|null */
    protected $lock;

    public function __construct(
        protected UpdatePackageService $packages,
        protected UpdateStateStore $state,
    ) {}

    /**
     * @param  array<string, mixed>  $release
     * @return array<string, mixed>
     */
    public function update(array $release, ?int $userId = null): array
    {
        if (! config('update.enabled')) {
            throw new RuntimeException('画面更新は無効です。');
        }

        $this->acquireLock();
        $backup = null;
        $package = null;
        $maintenanceWasActive = app()->maintenanceMode()->active();
        $keepMaintenanceMode = false;

        try {
            $this->state->put([
                'status' => 'running',
                'version' => $release['version'] ?? null,
                'started_at' => now()->toIso8601String(),
                'user_id' => $userId,
                'step' => 'download',
            ]);
            $this->state->log('Update started for '.($release['version'] ?? 'unknown'));

            ignore_user_abort(true);
            @set_time_limit(0);

            $package = $this->packages->downloadAndPrepare($release);
            $this->preflight($package['manifest'], filesize($package['archive']) ?: 0);

            $this->setStep('backup');
            $backup = $this->createBackup($package['manifest']);

            if (! $maintenanceWasActive) {
                Artisan::call('down', ['--retry' => 60]);
            }

            $this->setStep('install');
            $this->installFiles($package['staging'], $package['manifest']);
            clearstatcache(true);
            require base_path('vendor/autoload.php');

            $this->setStep('migrate');
            Artisan::call('optimize:clear');
            if ($package['manifest']['migrate'] ?? false) {
                if (Artisan::call('migrate', ['--force' => true]) !== 0) {
                    throw new RuntimeException('データベース migration に失敗しました。');
                }
            }
            if (! file_exists(public_path('storage')) && Artisan::call('storage:link') !== 0) {
                $this->state->log('storage:link was skipped because the symbolic link could not be created.');
            }

            $this->setStep('cache');
            foreach (['config:cache', 'route:cache', 'view:cache'] as $command) {
                if (Artisan::call($command) !== 0) {
                    throw new RuntimeException("{$command} に失敗しました。");
                }
            }

            DB::connection()->select('select 1');
            if (cuckooremind_version() !== $package['manifest']['version']) {
                throw new RuntimeException('更新後のバージョン確認に失敗しました。');
            }

            file_put_contents(
                storage_path('app/updates/installed-manifest.json'),
                json_encode($package['manifest'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            );

            $result = [
                'status' => 'succeeded',
                'version' => $package['manifest']['version'],
                'started_at' => $this->state->get()['started_at'] ?? null,
                'finished_at' => now()->toIso8601String(),
                'user_id' => $userId,
                'backup' => $backup,
            ];
            $this->state->put($result);
            $this->state->log('Update succeeded for '.$package['manifest']['version']);
            $this->pruneBackups();

            return $result;
        } catch (Throwable $exception) {
            $restored = false;

            if ($backup !== null) {
                try {
                    $this->restoreBackup($backup);
                    $restored = true;
                } catch (Throwable $restoreException) {
                    $keepMaintenanceMode = app()->maintenanceMode()->active();
                    $this->state->log('Restore failed: '.$restoreException->getMessage());
                }
            }

            $this->state->put([
                'status' => 'failed',
                'version' => $release['version'] ?? null,
                'finished_at' => now()->toIso8601String(),
                'user_id' => $userId,
                'error' => $exception->getMessage(),
                'restored' => $restored,
                'backup' => $backup,
            ]);
            $this->state->log('Update failed: '.$exception->getMessage());

            throw $exception;
        } finally {
            try {
                if (! $maintenanceWasActive && ! $keepMaintenanceMode && app()->maintenanceMode()->active()) {
                    Artisan::call('up');
                }
                if (is_array($package)) {
                    try {
                        $this->packages->cleanup($package);
                    } catch (Throwable $cleanupException) {
                        $this->state->log('Package cleanup failed: '.$cleanupException->getMessage());
                    }
                }
            } finally {
                $this->releaseLock();
            }
        }
    }

    /** @param array<string, mixed> $manifest */
    public function preflight(array $manifest, int $archiveSize): void
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new RuntimeException('PHP ZipArchive 拡張が必要です。');
        }

        if (version_compare(PHP_VERSION, (string) $manifest['minimum_php'], '<')) {
            throw new RuntimeException('PHP '.$manifest['minimum_php'].' 以上が必要です。');
        }

        $current = ltrim(cuckooremind_version(), 'vV');
        if (version_compare($current, ltrim((string) $manifest['minimum_upgradable_version'], 'vV'), '<')) {
            throw new RuntimeException('現在のバージョンから直接更新できません。中間バージョンへ更新してください。');
        }
        if (version_compare($current, ltrim((string) $manifest['minimum_updater_version'], 'vV'), '<')) {
            throw new RuntimeException('更新エンジンが古いため、中間バージョンへの更新が必要です。');
        }
        if (version_compare(ltrim((string) $manifest['version'], 'vV'), $current, '<=')) {
            throw new RuntimeException('現在より新しいバージョンではありません。');
        }

        foreach ([base_path(), storage_path(), storage_path('app')] as $path) {
            if (! is_writable($path)) {
                throw new RuntimeException("書き込み権限がありません: {$path}");
            }
        }

        foreach ($this->managedUpdatePaths($manifest) as $path) {
            $parent = dirname(base_path($path));
            while (! is_dir($parent) && $parent !== dirname($parent)) {
                $parent = dirname($parent);
            }
            if (! is_writable($parent)) {
                throw new RuntimeException("更新対象ディレクトリに書き込み権限がありません: {$parent}");
            }
        }

        $freeSpace = disk_free_space(storage_path());
        if ($freeSpace !== false && $freeSpace < max($archiveSize * 3, 50 * 1024 * 1024)) {
            throw new RuntimeException('更新に必要な空き容量がありません。');
        }

        DB::connection()->getPdo();
        if (DB::getDriverName() !== 'sqlite') {
            throw new RuntimeException('対応データベースは SQLite のみです。');
        }
    }

    /** @return array<int, array{label: string, passed: bool, message: string}> */
    public function environmentChecks(int $assetSize = 0): array
    {
        $checks = [
            [
                'label' => 'PHP 8.2以上',
                'passed' => version_compare(PHP_VERSION, '8.2.0', '>='),
                'message' => PHP_VERSION,
            ],
            [
                'label' => 'ZipArchive拡張',
                'passed' => class_exists(\ZipArchive::class),
                'message' => class_exists(\ZipArchive::class) ? '利用可能' : '利用できません',
            ],
            [
                'label' => 'アプリディレクトリの書込権限',
                'passed' => is_writable(base_path()),
                'message' => base_path(),
            ],
            [
                'label' => 'storageの書込権限',
                'passed' => is_writable(storage_path()),
                'message' => storage_path(),
            ],
        ];

        $required = max($assetSize * 3, 50 * 1024 * 1024);
        $free = disk_free_space(storage_path());
        $checks[] = [
            'label' => 'ディスク空き容量',
            'passed' => $free === false || $free >= $required,
            'message' => $free === false ? '取得できません' : number_format($free / 1024 / 1024).' MB',
        ];

        try {
            DB::connection()->getPdo();
            $checks[] = ['label' => 'データベース接続', 'passed' => true, 'message' => DB::getDriverName()];
        } catch (Throwable $exception) {
            $checks[] = ['label' => 'データベース接続', 'passed' => false, 'message' => $exception->getMessage()];
        }

        return $checks;
    }

    public function restoreLatest(): string
    {
        $backups = glob(storage_path('app/updates/backups/*'), GLOB_ONLYDIR) ?: [];
        rsort($backups);

        if (! isset($backups[0])) {
            throw new RuntimeException('復元できるバックアップがありません。');
        }

        $this->acquireLock();
        try {
            $this->restoreBackup($backups[0]);
            $this->state->put(['status' => 'restored', 'backup' => $backups[0]]);

            return $backups[0];
        } finally {
            $this->releaseLock();
        }
    }

    /** @param array<string, mixed> $manifest */
    protected function createBackup(array $manifest): string
    {
        $directory = storage_path('app/updates/backups/'.now()->format('YmdHis').'-'.bin2hex(random_bytes(3)));
        $this->ensureDirectory($directory.'/files');
        $paths = $this->managedUpdatePaths($manifest);
        $existing = [];
        $missing = [];

        foreach ($paths as $path) {
            $source = base_path($path);
            if (is_file($source)) {
                $this->copyFile($source, $directory.'/files/'.$path);
                $existing[] = $path;
            } else {
                $missing[] = $path;
            }
        }

        $database = null;
        $databasePath = DB::connection()->getDatabaseName();
        if ($databasePath !== ':memory:' && is_file($databasePath)) {
            DB::statement('PRAGMA wal_checkpoint(FULL)');
            DB::disconnect();
            if (! copy($databasePath, $directory.'/database.sqlite')) {
                throw new RuntimeException('SQLiteデータベースをバックアップできません。');
            }
            $database = $databasePath;
        }

        file_put_contents($directory.'/backup.json', json_encode([
            'existing' => $existing,
            'missing' => $missing,
            'database' => $database,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $directory;
    }

    /** @param array<string, mixed> $manifest */
    protected function installFiles(string $staging, array $manifest): void
    {
        foreach ($manifest['files'] as $path => $digest) {
            if ($this->isPreserved($path, $manifest)) {
                continue;
            }

            $this->copyFile($staging.'/'.$path, base_path($path), true);
        }

        $this->copyFile($staging.'/update-manifest.json', base_path('update-manifest.json'), true);

        foreach ($this->removedPaths($manifest) as $path) {
            $target = base_path($path);
            if (is_file($target) && ! $this->isPreserved($path, $manifest)) {
                unlink($target);
            }
        }
    }

    protected function restoreBackup(string $directory): void
    {
        $metadataPath = $directory.'/backup.json';
        if (! is_file($metadataPath)) {
            throw new RuntimeException('バックアップ情報がありません。');
        }

        $metadata = json_decode((string) file_get_contents($metadataPath), true, 512, JSON_THROW_ON_ERROR);

        foreach ($metadata['existing'] ?? [] as $path) {
            $this->copyFile($directory.'/files/'.$path, base_path($path), true);
        }
        foreach ($metadata['missing'] ?? [] as $path) {
            if (is_file(base_path($path))) {
                unlink(base_path($path));
            }
        }

        if (($metadata['database'] ?? null) && is_file($directory.'/database.sqlite')) {
            DB::disconnect();
            copy($directory.'/database.sqlite', $metadata['database']);
        }

        Artisan::call('optimize:clear');
    }

    /** @param array<string, mixed> $manifest @return array<int, string> */
    protected function managedUpdatePaths(array $manifest): array
    {
        return array_values(array_unique(array_merge(
            array_values(array_filter(array_keys($manifest['files']), fn (string $path): bool => ! $this->isPreserved($path, $manifest))),
            ['update-manifest.json'],
            $this->removedPaths($manifest),
        )));
    }

    /** @param array<string, mixed> $manifest @return array<int, string> */
    protected function removedPaths(array $manifest): array
    {
        $removed = $manifest['remove'] ?? [];
        $installedPath = storage_path('app/updates/installed-manifest.json');

        if (is_file($installedPath)) {
            $installed = json_decode((string) file_get_contents($installedPath), true);
            if (is_array($installed['files'] ?? null)) {
                $removed = array_merge($removed, array_diff(array_keys($installed['files']), array_keys($manifest['files'])));
            }
        }

        return array_values(array_unique(array_filter($removed, fn ($path): bool => is_string($path) && $this->isSafeRelativePath($path))));
    }

    /** @param array<string, mixed> $manifest */
    protected function isPreserved(string $path, array $manifest): bool
    {
        foreach (array_merge($manifest['install_only'] ?? [], $manifest['preserve'] ?? []) as $preserve) {
            $preserve = (string) $preserve;
            if ($path === rtrim($preserve, '/') || (str_ends_with($preserve, '/') && str_starts_with($path, $preserve))) {
                return true;
            }
        }

        return false;
    }

    protected function isSafeRelativePath(string $path): bool
    {
        return $path !== ''
            && ! str_contains($path, "\0")
            && ! str_contains($path, '\\')
            && ! str_starts_with($path, '/')
            && ! preg_match('/^[A-Za-z]:/', $path)
            && ! in_array('..', explode('/', $path), true);
    }

    protected function copyFile(string $source, string $target, bool $atomic = false): void
    {
        if (! is_file($source)) {
            throw new RuntimeException("コピー元ファイルがありません: {$source}");
        }

        $this->ensureDirectory(dirname($target));
        $destination = $atomic ? $target.'.update-tmp' : $target;

        if (! copy($source, $destination)) {
            throw new RuntimeException("ファイルをコピーできません: {$target}");
        }
        @chmod($destination, fileperms($source) & 0777);

        if ($atomic && ! rename($destination, $target)) {
            @unlink($destination);
            throw new RuntimeException("ファイルを置換できません: {$target}");
        }

        if ($atomic && function_exists('opcache_invalidate')) {
            @opcache_invalidate($target, true);
        }
    }

    protected function acquireLock(): void
    {
        $this->ensureDirectory(storage_path('app/updates'));
        $this->lock = fopen(storage_path('app/updates/update.lock'), 'c+');

        if ($this->lock === false || ! flock($this->lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('別の更新処理が実行中です。');
        }
    }

    protected function releaseLock(): void
    {
        if (is_resource($this->lock)) {
            flock($this->lock, LOCK_UN);
            fclose($this->lock);
        }
        $this->lock = null;
    }

    protected function setStep(string $step): void
    {
        $state = $this->state->get();
        $state['step'] = $step;
        $this->state->put($state);
        $this->state->log('Step: '.$step);
    }

    protected function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException("ディレクトリを作成できません: {$directory}");
        }
    }

    protected function pruneBackups(): void
    {
        $backups = glob(storage_path('app/updates/backups/*'), GLOB_ONLYDIR) ?: [];
        rsort($backups);

        foreach (array_slice($backups, max(1, (int) config('update.backup_keep'))) as $backup) {
            $this->removeDirectory($backup);
        }
    }

    protected function removeDirectory(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($directory);
    }
}
