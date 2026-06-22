<?php

namespace App\Console\Commands;

use App\Services\Updates\ApplicationUpdater;
use App\Services\Updates\GitHubReleaseService;
use Illuminate\Console\Command;
use Throwable;

class UpdateApplication extends Command
{
    protected $signature = 'app:update {--yes : 確認せずに更新する}';

    protected $description = 'GitHub Releases の最新版へ CuckooRemind を更新します';

    public function handle(GitHubReleaseService $releases, ApplicationUpdater $updater): int
    {
        try {
            $release = $releases->availableUpdate(true);
            if (! $release) {
                $this->info('利用可能な更新はありません。');

                return self::SUCCESS;
            }

            $this->info(cuckooremind_version().' から '.$release['version'].' へ更新します。');
            if (! $this->option('yes') && ! $this->confirm('バックアップを確認し、更新を続行しますか？')) {
                return self::SUCCESS;
            }

            $updater->update($release);
            $this->info('更新が完了しました。');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
