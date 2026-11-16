<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\ApplicationUpdateAvailable;
use App\Services\Updates\GitHubReleaseService;
use Illuminate\Console\Command;

class NotifyApplicationUpdate extends Command
{
    protected $signature = 'app:notify-update';

    protected $description = 'CuckooRemind の更新を確認してシステム管理者に通知します';

    public function handle(GitHubReleaseService $releases): int
    {
        $release = $releases->availableUpdate(true);

        if ($release !== null) {
            User::query()->where('is_system_admin', true)->eachById(
                fn (User $admin) => $admin->notify(new ApplicationUpdateAvailable($release['version']))
            );
        }

        return self::SUCCESS;
    }
}
