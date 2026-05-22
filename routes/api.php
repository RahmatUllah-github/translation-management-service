<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 routes
|--------------------------------------------------------------------------
| Versioned via URI prefix so future breaking changes can ship as /v2
| without disturbing existing clients.
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    // ---- Public ----------------------------------------------------------
    // Stricter throttle on login blunts credential brute-force attempts.
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('auth.login');

    // ---- Protected -------------------------------------------------------
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
    });
});
