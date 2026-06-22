<?php

namespace Tests\Unit;

use App\Services\Updates\GitHubReleaseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubReleaseServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
        config(['update.enabled' => true]);
    }

    public function test_it_returns_newest_stable_release_and_ignores_drafts_and_prereleases(): void
    {
        Http::fake(['*' => Http::response([
            $this->release('v0.0.3'),
            $this->release('v0.0.5', prerelease: true),
            $this->release('v0.0.4', draft: true),
            $this->release('v0.0.1'),
        ])]);

        $service = app(GitHubReleaseService::class);

        $this->assertSame('v0.0.3', $service->latest()['version']);
        $this->assertSame('v0.0.3', $service->availableUpdate()['version']);
    }

    public function test_it_caches_release_responses_until_explicit_refresh(): void
    {
        Http::fake(['*' => Http::response([$this->release('v0.0.3')])]);
        $service = app(GitHubReleaseService::class);

        $service->releases();
        $service->releases();
        Http::assertSentCount(1);

        $service->releases(true);
        Http::assertSentCount(2);
    }

    /** @return array<string, mixed> */
    private function release(string $version, bool $draft = false, bool $prerelease = false): array
    {
        return [
            'tag_name' => $version,
            'name' => $version,
            'body' => 'notes',
            'draft' => $draft,
            'prerelease' => $prerelease,
            'published_at' => '2026-06-22T00:00:00Z',
            'html_url' => 'https://github.com/ytsuyuzaki/cuckooremind/releases/tag/'.$version,
            'assets' => [],
        ];
    }
}
