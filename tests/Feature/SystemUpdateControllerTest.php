<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Updates\ApplicationUpdater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
            ->assertSee('v0.0.3')
            ->assertSee('Update notes')
            ->assertSee('ダウンロードして更新');
    }

    public function test_update_requires_the_current_password(): void
    {
        Http::fake(['*' => Http::response([$this->release()])]);
        $admin = User::factory()->systemAdmin()->create();

        $this->actingAs($admin)
            ->from(route('system-updates.index'))
            ->post(route('system-updates.update'), [
                'version' => 'v0.0.3',
                'current_password' => 'wrong-password',
            ])
            ->assertRedirect(route('system-updates.index'))
            ->assertSessionHasErrors('current_password');
    }

    public function test_system_admin_can_start_the_update(): void
    {
        Http::fake(['*' => Http::response([$this->release()])]);
        $admin = User::factory()->systemAdmin()->create();
        $updater = $this->mock(ApplicationUpdater::class);
        $updater->shouldReceive('update')
            ->once()
            ->withArgs(fn (array $release, int $userId) => $release['version'] === 'v0.0.3' && $userId === $admin->id)
            ->andReturn(['status' => 'succeeded']);

        $this->actingAs($admin)
            ->post(route('system-updates.update'), [
                'version' => 'v0.0.3',
                'current_password' => 'password',
            ])
            ->assertRedirect(route('system-updates.index'))
            ->assertSessionHas('success');
    }

    /** @return array<string, mixed> */
    private function release(): array
    {
        return [
            'tag_name' => 'v0.0.3',
            'name' => 'v0.0.3',
            'body' => '## Update notes',
            'draft' => false,
            'prerelease' => false,
            'published_at' => '2026-06-22T00:00:00Z',
            'html_url' => 'https://github.com/ytsuyuzaki/cuckooremind/releases/tag/v0.0.3',
            'assets' => [],
        ];
    }
}
