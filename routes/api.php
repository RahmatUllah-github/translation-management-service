<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\TranslationController;
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

        // Export is declared before the /{translation} route so the literal
        // "export" segment is never mistaken for a model id. It carries its
        // own (tighter) throttle as the heaviest endpoint.
        Route::get('translations/export', [TranslationController::class, 'export'])
            ->middleware('throttle:export')
            ->name('translations.export');

        Route::get('translations', [TranslationController::class, 'index'])->name('translations.index');
        Route::post('translations', [TranslationController::class, 'store'])->name('translations.store');
        Route::get('translations/{translation}', [TranslationController::class, 'show'])->name('translations.show');
        Route::match(['put', 'patch'], 'translations/{translation}', [TranslationController::class, 'update'])
            ->name('translations.update');
        Route::delete('translations/{translation}', [TranslationController::class, 'destroy'])
            ->name('translations.destroy');
    });
});
