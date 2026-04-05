<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SetupTest extends TestCase
{
    #[Test]
    public function 未設定(): void
    {
        $response = $this->get('/');

        $response->assertStatus(302);
        $response->assertRedirect('setup');
    }
}
