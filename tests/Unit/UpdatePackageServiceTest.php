<?php

namespace Tests\Unit;

use App\Services\Updates\UpdatePackageService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class UpdatePackageServiceTest extends TestCase
{
    private const PACKAGE_VERSION = 'v9.9.9';

    private array $directories = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['update.maximum_download_size' => 1024 * 1024]);
    }

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            if (is_dir($directory)) {
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
        parent::tearDown();
    }

    public function test_it_extracts_a_valid_manifest_package(): void
    {
        [$archive, $staging] = $this->package(['app/example.php' => '<?php return true;']);

        $manifest = app(UpdatePackageService::class)->extractAndValidate($archive, $staging, self::PACKAGE_VERSION);

        $this->assertSame(self::PACKAGE_VERSION, $manifest['version']);
        $this->assertFileExists($staging.'/app/example.php');
    }

    public function test_it_rejects_path_traversal_before_extracting(): void
    {
        [$archive, $staging] = $this->package(['../evil.php' => 'bad'], includeInManifest: false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ディレクトリトラバーサル');

        app(UpdatePackageService::class)->extractAndValidate($archive, $staging, self::PACKAGE_VERSION);
    }

    public function test_it_rejects_a_file_with_an_invalid_hash(): void
    {
        [$archive, $staging] = $this->package(['app/example.php' => 'changed'], digestContent: 'expected');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ハッシュが一致しません');

        app(UpdatePackageService::class)->extractAndValidate($archive, $staging, self::PACKAGE_VERSION);
    }

    public function test_it_downloads_and_verifies_a_release_asset(): void
    {
        [$archive] = $this->package(['app/example.php' => '<?php return true;']);
        $body = file_get_contents($archive);
        Http::fake(['*' => Http::response($body)]);
        $service = app(UpdatePackageService::class);

        $package = $service->downloadAndPrepare($this->release($body));

        $this->assertSame(self::PACKAGE_VERSION, $package['manifest']['version']);
        $this->assertFileExists($package['staging'].'/app/example.php');
        $service->cleanup($package);
    }

    public function test_it_rejects_an_untrusted_download_host(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('許可されていない');

        app(UpdatePackageService::class)->downloadAndPrepare([
            'version' => self::PACKAGE_VERSION,
            'assets' => [[
                'name' => 'cuckooremind-'.self::PACKAGE_VERSION.'.zip',
                'url' => 'https://example.com/update.zip',
                'size' => 100,
                'digest' => 'sha256:'.str_repeat('a', 64),
            ]],
        ]);
    }

    /** @return array{string, string} */
    private function package(array $files, bool $includeInManifest = true, ?string $digestContent = null): array
    {
        $directory = sys_get_temp_dir().'/cuckooremind-test-'.bin2hex(random_bytes(5));
        mkdir($directory, 0700, true);
        $this->directories[] = $directory;
        $archive = $directory.'/package.zip';
        $staging = $directory.'/staging';
        mkdir($staging);

        $manifestFiles = [];
        if ($includeInManifest) {
            foreach ($files as $path => $content) {
                $manifestFiles[$path] = 'sha256:'.hash('sha256', $digestContent ?? $content);
            }
        }

        $manifest = [
            'schema' => 1,
            'version' => self::PACKAGE_VERSION,
            'minimum_upgradable_version' => 'v0.0.1',
            'minimum_updater_version' => 'v1.0.0',
            'minimum_php' => '8.2.0',
            'files' => $manifestFiles,
            'install_only' => ['.env', 'storage/db.sqlite'],
            'preserve' => ['.env', 'storage/'],
            'remove' => [],
            'migrate' => true,
        ];

        $zip = new ZipArchive;
        $zip->open($archive, ZipArchive::CREATE);
        foreach ($files as $path => $content) {
            $zip->addFromString($path, $content);
        }
        $zip->addFromString('update-manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        $zip->close();

        return [$archive, $staging];
    }

    /** @return array<string, mixed> */
    private function release(string $archive): array
    {
        return [
            'version' => self::PACKAGE_VERSION,
            'assets' => [[
                'name' => 'cuckooremind-'.self::PACKAGE_VERSION.'.zip',
                'url' => 'https://github.com/ytsuyuzaki/cuckooremind/releases/download/'.self::PACKAGE_VERSION.'/cuckooremind-'.self::PACKAGE_VERSION.'.zip',
                'size' => strlen($archive),
                'digest' => 'sha256:'.hash('sha256', $archive),
            ]],
        ];
    }
}
