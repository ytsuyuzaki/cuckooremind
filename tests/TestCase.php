<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function cuckooRemindVersionAfter(int $patches = 1): string
    {
        $version = ltrim(cuckooremind_version(), 'vV');

        if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $version, $matches) !== 1) {
            throw new \LogicException("Invalid CuckooRemind version: {$version}");
        }

        return sprintf('v%d.%d.%d', $matches[1], $matches[2], $matches[3] + $patches);
    }
}
