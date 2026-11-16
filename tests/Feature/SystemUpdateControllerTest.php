<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Updates\ApplicationUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PDO;
use Tests\TestCase;

class SystemUpdateControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
        config(['update.enabled' => true]);
    }

    public function test_regular_users_cannot_access_system_updates(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('system-updates.index'))->assertForbidden();
        $this->actingAs($user)->post(route('system-updates.refresh'))->assertForbidden();
        $this->actingAs($user)->get(route('system-updates.status'))->assertForbidden();
    }

    public function test_system_admin_can_view_release_history_and_available_update(): void
    {
        Http::fake(['*' => Http::response([$this->release()])]);
        $admin = User::factory()->systemAdmin()->create();

        $this->actingAs($admin)
            ->get(route('system-updates.index'))
            ->assertOk()
            ->assertSee($this->cuckooRemindVersionAfter())
            ->assertSee('Update notes')
            ->assertSee('ダウンロードして更新')
            ->assertSee(route('system-updates.backup.environment'))
            ->assertSee(route('system-updates.backup.database'));
    }

    public function test_backup_downloads_require_a_system_admin(): void
    {
        $user = User::factory()->create();
        $routes = ['system-updates.backup.environment', 'system-updates.backup.database'];
        foreach ($routes as $route) {
            $this->getJson(route($route))->assertUnauthorized();
        }

        $this->actingAs($user);
        foreach ($routes as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }

    public function test_system_admin_can_download_the_current_environment_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'test-environment-');
        $contents = "APP_NAME=CuckooRemindTest\n";
        file_put_contents($path, $contents);
        $this->app->useEnvironmentPath(dirname($path))->loadEnvironmentFrom(basename($path));

        try {
            $response = $this->actingAs(User::factory()->systemAdmin()->create())
                ->get(route('system-updates.backup.environment'))
                ->assertOk()
                ->assertDownload('.env')
                ->assertHeader('Cache-Control', 'no-store, private');

            $this->assertSame($contents, file_get_contents($response->baseResponse->getFile()->getPathname()));
        } finally {
            unlink($path);
        }
    }

    public function test_system_admin_can_download_a_restorable_sqlite_snapshot_including_wal_changes(): void
    {
        $admin = User::factory()->systemAdmin()->create();
        $databasePath = tempnam(sys_get_temp_dir(), 'test-sqlite-');
        $restoredPath = tempnam(sys_get_temp_dir(), 'test-sqlite-restored-');
        $originalConnection = DB::getDefaultConnection();
        config(['database.connections.backup_source' => [
            'driver' => 'sqlite',
            'database' => $databasePath,
            'prefix' => '',
        ]]);

        try {
            $source = DB::connection('backup_source');
            $source->statement('PRAGMA journal_mode = WAL');
            $source->statement('CREATE TABLE entries (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
            $source->insert('INSERT INTO entries (name) VALUES (?)', ['committed to WAL']);
            $this->assertGreaterThan(0, filesize($databasePath.'-wal'));
            DB::setDefaultConnection('backup_source');

            $response = $this->actingAs($admin)
                ->get(route('system-updates.backup.database'))
                ->assertOk()
                ->assertDownload('database.sqlite')
                ->assertHeader('Cache-Control', 'no-store, private');

            $backupPath = $response->baseResponse->getFile()->getPathname();
            ob_start();
            try {
                $response->baseResponse->sendContent();
                file_put_contents($restoredPath, ob_get_contents());
            } finally {
                ob_end_clean();
            }
            $this->assertFileDoesNotExist($backupPath);

            $restored = new PDO('sqlite:'.$restoredPath);
            $this->assertSame('ok', $restored->query('PRAGMA integrity_check')->fetchColumn());
            $this->assertSame('committed to WAL', $restored->query('SELECT name FROM entries')->fetchColumn());
        } finally {
            DB::setDefaultConnection($originalConnection);
            DB::purge('backup_source');
            unlink($databasePath);
            unlink($restoredPath);
        }
    }

    public function test_update_requires_the_current_password(): void
    {
        Http::fake(['*' => Http::response([$this->release()])]);
        $admin = User::factory()->systemAdmin()->create();

        $this->actingAs($admin)
            ->from(route('system-updates.index'))
            ->post(route('system-updates.update'), [
                'version' => $this->cuckooRemindVersionAfter(),
                'current_password' => 'wrong-password',
            ])
            ->assertRedirect(route('system-updates.index'))
            ->assertSessionHasErrors('current_password');
    }

    public function test_system_admin_can_start_the_update(): void
    {
        Http::fake(['*' => Http::response([$this->release()])]);
        $admin = User::factory()->systemAdmin()->create();
        $nextVersion = $this->cuckooRemindVersionAfter();
        $updater = $this->mock(ApplicationUpdater::class);
        $updater->shouldReceive('update')
            ->once()
            ->withArgs(fn (array $release, int $userId) => $release['version'] === $nextVersion && $userId === $admin->id)
            ->andReturn(['status' => 'succeeded']);

        $this->actingAs($admin)
            ->post(route('system-updates.update'), [
                'version' => $nextVersion,
                'current_password' => 'password',
            ])
            ->assertRedirect(route('system-updates.index'))
            ->assertSessionHas('success');
    }

    /** @return array<string, mixed> */
    private function release(): array
    {
        $version = $this->cuckooRemindVersionAfter();

        return [
            'tag_name' => $version,
            'name' => $version,
            'body' => '## Update notes',
            'draft' => false,
            'prerelease' => false,
            'published_at' => '2026-06-22T00:00:00Z',
            'html_url' => 'https://github.com/ytsuyuzaki/cuckooremind/releases/tag/'.$version,
            'assets' => [],
        ];
    }
}
