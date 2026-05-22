<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\JsonResponseService;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the JsonResponseService into the container as a singleton so the
 * `ApiResponse` facade always resolves the same instance.
 */
final class JsonResponseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            JsonResponseService::class,
            static fn (): JsonResponseService => new JsonResponseService(),
        );
    }
}
