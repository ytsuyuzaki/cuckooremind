<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Tests\TestCase;

class SystemAdminCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_first_created_user_becomes_system_admin(): void
    {
        $creator = app(CreatesNewUsers::class);
        $first = $creator->create($this->input('first@example.com'));
        $second = $creator->create($this->input('second@example.com'));

        $this->assertTrue($first->is_system_admin);
        $this->assertFalse($second->is_system_admin);
    }

    private function input(string $email): array
    {
        return [
            'name' => 'Test User',
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
        ];
    }
}
