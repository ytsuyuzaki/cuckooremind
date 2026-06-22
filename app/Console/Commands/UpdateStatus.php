<?php

namespace App\Console\Commands;

use App\Services\Updates\UpdateStateStore;
use Illuminate\Console\Command;

class UpdateStatus extends Command
{
    protected $signature = 'app:update:status';

    protected $description = 'アプリ更新の状態を表示します';

    public function handle(UpdateStateStore $state): int
    {
        $this->line(json_encode($state->get(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
