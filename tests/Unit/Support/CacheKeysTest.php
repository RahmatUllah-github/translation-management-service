<?php

declare(strict_types=1);

use App\Support\CacheKeys;

it('builds a stable export version key', function (): void {
    expect(CacheKeys::exportVersion(7))->toBe('translations:export:version:locale:7');
});

it('builds a versioned export payload key', function (): void {
    expect(CacheKeys::exportPayload(7, 3))->toBe('translations:export:payload:locale:7:v3');
});

it('produces a different payload key for each version', function (): void {
    expect(CacheKeys::exportPayload(7, 1))->not->toBe(CacheKeys::exportPayload(7, 2));
});

it('scopes keys per locale', function (): void {
    expect(CacheKeys::exportVersion(1))->not->toBe(CacheKeys::exportVersion(2));
});
