<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('throttles repeated login attempts', function (): void {
    $user = User::factory()->create();
    $payload = ['email' => $user->email, 'password' => 'wrong-password'];

    // The login limiter allows 5 attempts per minute.
    foreach (range(1, 5) as $ignored) {
        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(422);
    }

    $this->postJson('/api/v1/auth/login', $payload)->assertStatus(429);
});
