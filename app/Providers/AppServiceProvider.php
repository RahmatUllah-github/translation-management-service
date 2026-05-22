<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureRateLimiters();
    }

    /**
     * Fail loudly on lazy loading outside production so N+1 query bugs are
     * caught in development and tests rather than degrading performance live.
     */
    private function configureModels(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::shouldBeStrict(! $this->app->isProduction());
    }

    /**
     * Named rate limiters. Each endpoint group gets a budget proportional to
     * its cost and abuse surface.
     */
    private function configureRateLimiters(): void
    {
        // Authenticated general API traffic — keyed per user, falls back to IP.
        RateLimiter::for('api', static fn (Request $request): Limit => Limit::perMinute(60)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        // Login — keyed per IP; tight budget to slow credential stuffing.
        RateLimiter::for('login', static fn (Request $request): Limit => Limit::perMinute(5)
            ->by($request->ip()));

        // Export is the heaviest endpoint — give it a smaller per-user budget.
        RateLimiter::for('export', static fn (Request $request): Limit => Limit::perMinute(20)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }
}
