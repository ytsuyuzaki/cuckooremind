<?php

namespace App\Services\Updates;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;
use ZipArchive;

class UpdatePackageService
{
    /**
     * @param  array<string, mixed>  $release
     * @return array{archive: string, staging: string, manifest: array<string, mixed>}
     */
    public function downloadAndPrepare(array $release): array
    {
        $asset = collect($release['assets'] ?? [])->firstWhere('name', 'cuckooremind-'.$release['version'].'.zip');

        if (! is_array($asset)) {
            throw new RuntimeException('このリリースに更新用 ZIP がありません。');
        }

        if (($asset['size'] ?? 0) > config('update.maximum_download_size')) {
            throw new RuntimeException('更新 ZIP が許可サイズを超えています。');
        }

        $this->assertAllowedUrl((string) $asset['url']);
        $directory = storage_path('app/updates/downloads');
        $this->ensureDirectory($directory);
        $archive = $directory.'/'.basename((string) $asset['name']);

        $response = Http::withOptions([
            'allow_redirects' => $this->redirectOptions(),
            'sink' => $archive,
            'progress' => function (int $downloadTotal, int $downloadedBytes): void {
                if ($downloadTotal > config('update.maximum_download_size') || $downloadedBytes > config('update.maximum_download_size')) {
                    throw new RuntimeException('更新 ZIP が許可サイズを超えています。');
                }
            },
        ])->timeout(max(60, (int) config('update.timeout')))
            ->get((string) $asset['url']);

        if (! $response->successful()) {
            @unlink($archive);
            throw new RuntimeException('更新 ZIP のダウンロードに失敗しました。HTTP '.$response->status());
        }

        $redirectHistory = array_filter(array_map('trim', explode(',', $response->header('X-Guzzle-Redirect-History'))));
        foreach ($redirectHistory as $redirect) {
            $this->assertAllowedUrl($redirect);
        }

        // Laravel の HTTP fake は Guzzle の sink を処理しないため、テスト時だけ body を保存する。
        if (! is_file($archive) && $response->body() !== '') {
            file_put_contents($archive, $response->body(), LOCK_EX);
        }

        if (! is_file($archive) || filesize($archive) > config('update.maximum_download_size')) {
            @unlink($archive);
            throw new RuntimeException('更新 ZIP が許可サイズを超えています。');
        }
        $staging = storage_path('app/updates/staging/'.preg_replace('/[^0-9A-Za-z._-]/', '_', $release['version']));

        try {
            $this->verifyDigest($archive, $asset, $release);
            $this->removeDirectory($staging);
            $this->ensureDirectory($staging);
            $manifest = $this->extractAndValidate($archive, $staging, (string) $release['version']);

            return compact('archive', 'staging', 'manifest');
        } catch (Throwable $exception) {
            $this->cleanup(compact('archive', 'staging'));

            throw $exception;
        }
    }

    public function assertSafeEntryName(string $name): void
    {
        if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\')) {
            throw new RuntimeException('ZIP に不正なパスが含まれています。');
        }

        if (str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name)) {
            throw new RuntimeException('ZIP に絶対パスが含まれています。');
        }

        $segments = explode('/', trim($name, '/'));

        if (in_array('..', $segments, true) || in_array('.', $segments, true) || in_array('', $segments, true)) {
            throw new RuntimeException('ZIP にディレクトリトラバーサルまたは不正なパスが含まれています。');
        }
    }

    /** @param array{archive?: string, staging?: string} $package */
    public function cleanup(array $package): void
    {
        if (isset($package['archive']) && is_file($package['archive'])) {
            @unlink($package['archive']);
        }
        if (isset($package['staging']) && is_dir($package['staging'])) {
            $this->removeDirectory($package['staging']);
        }
    }

    /** @return array<string, mixed> */
    public function extractAndValidate(string $archive, string $staging, string $expectedVersion): array
    {
        $zip = new ZipArchive;

        if ($zip->open($archive) !== true) {
            throw new RuntimeException('更新 ZIP を開けません。');
        }

        $totalSize = 0;
        $entries = [];
        $seenEntries = [];

        try {
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $name = (string) ($stat['name'] ?? '');
                $this->assertSafeEntryName($name);
                if (isset($seenEntries[$name])) {
                    throw new RuntimeException("ZIP に重複したパスが含まれています: {$name}");
                }
                $seenEntries[$name] = true;
                $totalSize += (int) ($stat['size'] ?? 0);

                if ($totalSize > config('update.maximum_download_size') * 4) {
                    throw new RuntimeException('展開後のサイズが許可値を超えています。');
                }

                $operatingSystem = 0;
                $attributes = 0;
                if ($zip->getExternalAttributesIndex($index, $operatingSystem, $attributes)) {
                    $fileType = ($attributes >> 16) & 0170000;
                    if ($fileType === 0120000) {
                        throw new RuntimeException('ZIP にシンボリックリンクが含まれています。');
                    }
                }

                if (! str_ends_with($name, '/')) {
                    $entries[] = $name;
                }
            }

            if (! $zip->extractTo($staging)) {
                throw new RuntimeException('更新 ZIP を展開できません。');
            }
        } finally {
            $zip->close();
        }

        $manifestPath = $staging.'/update-manifest.json';

        if (! is_file($manifestPath)) {
            throw new RuntimeException('update-manifest.json がありません。');
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        $this->validateManifest($manifest, $expectedVersion);
        $knownFiles = array_keys($manifest['files']);

        foreach ($entries as $entry) {
            if ($entry !== 'update-manifest.json' && ! in_array($entry, $knownFiles, true)) {
                throw new RuntimeException("manifest にないファイルが含まれています: {$entry}");
            }
        }

        foreach ($manifest['files'] as $path => $digest) {
            $this->assertSafeEntryName($path);
            $file = $staging.'/'.$path;

            if (! is_file($file) || ! hash_equals($digest, 'sha256:'.hash_file('sha256', $file))) {
                throw new RuntimeException("ファイルのハッシュが一致しません: {$path}");
            }
        }

        return $manifest;
    }

    /** @param array<string, mixed> $manifest */
    protected function validateManifest(array $manifest, string $expectedVersion): void
    {
        foreach (['schema', 'version', 'minimum_upgradable_version', 'minimum_updater_version', 'minimum_php', 'files', 'install_only', 'preserve', 'remove'] as $key) {
            if (! array_key_exists($key, $manifest)) {
                throw new RuntimeException("manifest に {$key} がありません。");
            }
        }

        if ($manifest['schema'] !== 1
            || $manifest['version'] !== $expectedVersion
            || ! is_array($manifest['files'])
            || ! is_array($manifest['install_only'])
            || ! is_array($manifest['preserve'])
            || ! is_array($manifest['remove'])) {
            throw new RuntimeException('manifest の形式またはバージョンが一致しません。');
        }

        if (! is_string($manifest['minimum_php']) || preg_match('/^\d+\.\d+\.\d+$/', $manifest['minimum_php']) !== 1) {
            throw new RuntimeException('manifest の minimum_php が不正です。');
        }

        foreach (['version', 'minimum_upgradable_version', 'minimum_updater_version'] as $key) {
            if (! is_string($manifest[$key]) || preg_match('/^v?\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $manifest[$key]) !== 1) {
                throw new RuntimeException("manifest の {$key} が不正です。");
            }
        }

        foreach (array_merge(array_keys($manifest['files']), $manifest['install_only'], $manifest['preserve'], $manifest['remove']) as $path) {
            if (! is_string($path)) {
                throw new RuntimeException('manifest のパスが不正です。');
            }
            $this->assertSafeEntryName($path);
        }

        foreach ($manifest['files'] as $digest) {
            if (! is_string($digest) || preg_match('/^sha256:[a-f0-9]{64}$/i', $digest) !== 1) {
                throw new RuntimeException('manifest の SHA-256 が不正です。');
            }
        }

        if (array_key_exists('update-manifest.json', $manifest['files'])) {
            throw new RuntimeException('manifest 自身を files に含めることはできません。');
        }
    }

    /** @param array<string, mixed> $asset @param array<string, mixed> $release */
    protected function verifyDigest(string $archive, array $asset, array $release): void
    {
        $expected = $asset['digest'] ?? null;

        if (! is_string($expected) || ! str_starts_with($expected, 'sha256:')) {
            $checksumAsset = collect($release['assets'] ?? [])->firstWhere('name', 'checksums.txt');

            if (is_array($checksumAsset)) {
                $this->assertAllowedUrl((string) $checksumAsset['url']);
                $response = Http::withOptions([
                    'allow_redirects' => $this->redirectOptions(),
                    'progress' => function (int $downloadTotal, int $downloadedBytes): void {
                        if ($downloadTotal > 1024 * 1024 || $downloadedBytes > 1024 * 1024) {
                            throw new RuntimeException('checksums.txt が許可サイズを超えています。');
                        }
                    },
                ])->timeout(config('update.timeout'))->get((string) $checksumAsset['url']);
                $redirectHistory = array_filter(array_map('trim', explode(',', $response->header('X-Guzzle-Redirect-History'))));
                foreach ($redirectHistory as $redirect) {
                    $this->assertAllowedUrl($redirect);
                }
                if ($response->successful() && preg_match('/^([a-f0-9]{64})\s+\*?'.preg_quote(basename($archive), '/').'$/mi', $response->body(), $matches)) {
                    $expected = 'sha256:'.$matches[1];
                }
            }
        }

        if (! is_string($expected) || ! preg_match('/^sha256:[a-f0-9]{64}$/i', $expected)) {
            throw new RuntimeException('更新 ZIP の SHA-256 が公開されていません。');
        }

        if (! hash_equals(strtolower($expected), 'sha256:'.hash_file('sha256', $archive))) {
            @unlink($archive);
            throw new RuntimeException('更新 ZIP の SHA-256 が一致しません。');
        }
    }

    protected function assertAllowedUrl(string $url): void
    {
        $parts = parse_url($url);

        if (($parts['scheme'] ?? null) !== 'https' || ! in_array(strtolower((string) ($parts['host'] ?? '')), config('update.allowed_download_hosts'), true)) {
            throw new RuntimeException('許可されていないダウンロード URL です。');
        }
    }

    /** @return array<string, mixed> */
    protected function redirectOptions(): array
    {
        return [
            'max' => 5,
            'track_redirects' => true,
            'on_redirect' => function ($request, $response, $uri): void {
                $this->assertAllowedUrl((string) $uri);
            },
        ];
    }

    protected function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException("ディレクトリを作成できません: {$directory}");
        }
    }

    protected function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

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
