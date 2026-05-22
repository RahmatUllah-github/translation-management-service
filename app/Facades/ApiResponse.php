<?php

declare(strict_types=1);

namespace App\Facades;

use App\Services\JsonResponseService;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for {@see JsonResponseService} — the single entry point for building
 * structured API responses.
 *
 * @method static \Illuminate\Http\JsonResponse successResponse(string $message, mixed $data = null)
 * @method static \Illuminate\Http\JsonResponse validationErrorResponse(string $message, mixed $data = null)
 * @method static \Illuminate\Http\JsonResponse unauthorizedErrorResponse(string $message, mixed $data = null)
 * @method static \Illuminate\Http\JsonResponse forbiddenErrorResponse(string $message, mixed $data = null)
 * @method static \Illuminate\Http\JsonResponse errorResponse(string $message, mixed $data = null)
 * @method static \Illuminate\Http\JsonResponse notFoundErrorResponse(string $message, mixed $data = null)
 * @method static \Illuminate\Http\JsonResponse jsonResponse(string $message, mixed $data, int $code, string $status)
 *
 * @see JsonResponseService
 */
final class ApiResponse extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return JsonResponseService::class;
    }
}
