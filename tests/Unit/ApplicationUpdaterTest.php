<?php

namespace Tests\Unit;

use App\Services\Updates\ApplicationUpdater;
use ReflectionMethod;
use Tests\TestCase;

class ApplicationUpdaterTest extends TestCase
{
    public function test_update_policy_preserves_installation_specific_files(): void
    {
        $updater = app(ApplicationUpdater::class);
        $method = new ReflectionMethod($updater, 'isPreserved');
        $manifest = [
            'install_only' => ['.env', 'storage/db.sqlite'],
            'preserve' => ['.env', 'storage/'],
        ];

        $this->assertTrue($method->invoke($updater, '.env', $manifest));
        $this->assertTrue($method->invoke($updater, 'storage/db.sqlite', $manifest));
        $this->assertTrue($method->invoke($updater, 'storage/app/public/avatar.jpg', $manifest));
        $this->assertFalse($method->invoke($updater, 'app/Helpers.php', $manifest));
    }
}
