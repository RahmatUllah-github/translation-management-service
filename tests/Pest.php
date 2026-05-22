<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test bootstrapping
|--------------------------------------------------------------------------
| The base TestCase is bound to every test. The database refresh strategy is
| declared per-file: most files use RefreshDatabase (fast, transactional),
| while FULLTEXT tests use DatabaseMigrations because InnoDB's full-text
| index is not reliably searchable from within an open transaction.
*/

uses(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Create a user and authenticate as them for subsequent API calls.
 */
function actingAsApiUser(): User
{
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    return $user;
}
