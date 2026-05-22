<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('issues a token on valid credentials', function (): void {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['user' => ['id', 'name', 'email'], 'token', 'token_type'],
        ]);
});

it('never exposes the password hash', function (): void {
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    expect($response->json('data.user'))->not->toHaveKey('password');
});

it('rejects invalid credentials with 422', function (): void {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email', 'data');
});

it('requires both email and password', function (): void {
    $this->postJson('/api/v1/auth/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password'], 'data');
});

it('returns the authenticated user from /me', function (): void {
    $user = actingAsApiUser();

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email);
});

it('rejects /me without a token', function (): void {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('revokes the access token on logout', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test-device');

    $this->withToken($token->plainTextToken)
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    // The token row is gone, so it can never authenticate again.
    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $token->accessToken->getKey(),
    ]);
});
