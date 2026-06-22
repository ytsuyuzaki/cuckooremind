<?php

namespace App\Console\Commands;

use App\Services\Updates\ApplicationUpdater;
use Illuminate\Console\Command;
use Throwable;

class RestoreApplicationUpdate extends Command
{
    protected $signature = 'app:update:restore {--yes : 確認せずに復元する}';

    protected $description = '直近のアプリ更新バックアップを復元します';

    public function handle(ApplicationUpdater $updater): int
    {
        if (! $this->option('yes') && ! $this->confirm('直近の更新バックアップを復元しますか？')) {
            return self::SUCCESS;
        }

        try {
            $backup = $updater->restoreLatest();
            $this->info('復元しました: '.$backup);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
