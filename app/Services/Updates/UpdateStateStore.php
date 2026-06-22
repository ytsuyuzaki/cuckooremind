<?php

namespace App\Services\Updates;

class UpdateStateStore
{
    public function path(): string
    {
        return storage_path('app/updates/state.json');
    }

    /** @return array<string, mixed> */
    public function get(): array
    {
        if (! is_file($this->path())) {
            return ['status' => 'idle'];
        }

        $state = json_decode((string) file_get_contents($this->path()), true);

        return is_array($state) ? $state : ['status' => 'unknown'];
    }

    /** @param array<string, mixed> $state */
    public function put(array $state): void
    {
        $this->ensureDirectory();
        $state['updated_at'] = now()->toIso8601String();
        $temporary = $this->path().'.tmp';
        file_put_contents($temporary, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX);
        rename($temporary, $this->path());
    }

    public function log(string $message): void
    {
        $this->ensureDirectory();
        file_put_contents(
            storage_path('app/updates/update.log'),
            '['.now()->toIso8601String().'] '.$message.PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    protected function ensureDirectory(): void
    {
        $directory = dirname($this->path());

        if (! is_dir($directory)) {
            mkdir($directory, 0750, true);
        }
    }
}
