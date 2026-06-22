<?php

namespace App\Services\Updates;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitHubReleaseService
{
    /** @return array<int, array<string, mixed>> */
    public function releases(bool $refresh = false): array
    {
        if (! config('update.enabled')) {
            return [];
        }

        $cacheKey = 'app-updates:releases:'.config('update.repository');

        if ($refresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, config('update.cache_ttl'), function (): array {
            $response = $this->request()->get($this->apiUrl('/repos/'.config('update.repository').'/releases'), [
                'per_page' => 20,
            ]);

            if (! $response->successful() || ! is_array($response->json())) {
                throw new RuntimeException('GitHub Releases の取得に失敗しました。HTTP '.$response->status());
            }

            return collect($response->json())
                ->filter(fn (array $release): bool => ! ($release['draft'] ?? true) && ! ($release['prerelease'] ?? true))
                ->map(fn (array $release): array => $this->normalize($release))
                ->filter(fn (array $release): bool => $this->isVersion($release['version']))
                ->sort(fn (array $a, array $b): int => version_compare($this->plainVersion($b['version']), $this->plainVersion($a['version'])))
                ->values()
                ->all();
        });
    }

    /** @return array<string, mixed>|null */
    public function latest(bool $refresh = false): ?array
    {
        return $this->releases($refresh)[0] ?? null;
    }

    /** @return array<string, mixed>|null */
    public function availableUpdate(bool $refresh = false): ?array
    {
        $latest = $this->latest($refresh);

        if (! $latest || ! $this->isVersion(cuckooremind_version())) {
            return null;
        }

        return version_compare(
            $this->plainVersion($latest['version']),
            $this->plainVersion(cuckooremind_version()),
            '>'
        ) ? $latest : null;
    }

    /** @return array<string, mixed> */
    protected function normalize(array $release): array
    {
        $assets = collect($release['assets'] ?? [])->map(fn (array $asset): array => [
            'name' => (string) ($asset['name'] ?? ''),
            'url' => (string) ($asset['browser_download_url'] ?? ''),
            'size' => (int) ($asset['size'] ?? 0),
            'digest' => $asset['digest'] ?? null,
        ])->values()->all();

        return [
            'version' => (string) ($release['tag_name'] ?? ''),
            'name' => (string) ($release['name'] ?? $release['tag_name'] ?? ''),
            'body' => (string) ($release['body'] ?? ''),
            'published_at' => $release['published_at'] ?? null,
            'url' => (string) ($release['html_url'] ?? ''),
            'assets' => $assets,
        ];
    }

    protected function request(): PendingRequest
    {
        $request = Http::acceptJson()
            ->withUserAgent(config('app.name').'/'.cuckooremind_version())
            ->timeout(config('update.timeout'));

        if ($token = config('update.github_token')) {
            $request->withToken($token);
        }

        return $request;
    }

    protected function apiUrl(string $path): string
    {
        return rtrim((string) config('update.api_url'), '/').$path;
    }

    protected function isVersion(string $version): bool
    {
        return preg_match('/^v?\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version) === 1;
    }

    protected function plainVersion(string $version): string
    {
        return ltrim($version, 'vV');
    }
}
