<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Encapsulates authentication business rules so controllers stay thin and the
 * logic is unit-testable without the HTTP layer.
 */
final class AuthService
{
    /**
     * Verify credentials and issue a fresh Sanctum personal access token.
     *
     * @param  array{email: string, password: string, device_name?: string}  $credentials
     * @return array{user: User, token: string}
     *
     * @throws ValidationException When the credentials do not match.
     */
    public function login(array $credentials): array
    {
        $user = User::query()->where('email', $credentials['email'])->first();

        // A single generic failure for "no such user" and "wrong password"
        // prevents account enumeration. Hash::check is still run on a dummy
        // hash when the user is missing to keep response timing uniform.
        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $token = $user->createToken($credentials['device_name'] ?? 'api-token');

        return [
            'user' => $user,
            'token' => $token->plainTextToken,
        ];
    }

    /**
     * Revoke only the access token used by the current request, leaving the
     * user's other devices/sessions authenticated.
     */
    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }
}
