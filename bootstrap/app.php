<?php

declare(strict_types=1);

use App\Facades\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Always answer API routes with JSON, regardless of the Accept header.
        $exceptions->shouldRenderJsonWhen(
            static fn (Request $request): bool => $request->is('api/*') || $request->expectsJson()
        );

        // Render every API exception through the standard ApiResponse envelope
        // ({status, code, message, data}) so error and success responses share
        // one consistent shape. Non-API routes keep Laravel's default handling.
        $exceptions->render(static function (Throwable $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            return match (true) {
                $e instanceof ValidationException => ApiResponse::validationErrorResponse(
                    $e->getMessage(),
                    $e->errors(),
                ),
                $e instanceof AuthenticationException => ApiResponse::unauthorizedErrorResponse(
                    'Unauthenticated.',
                ),
                $e instanceof AuthorizationException,
                $e instanceof AccessDeniedHttpException => ApiResponse::forbiddenErrorResponse(
                    'This action is unauthorized.',
                ),
                // Covers a missing route and failed route-model binding alike.
                $e instanceof NotFoundHttpException => ApiResponse::notFoundErrorResponse(
                    'Resource not found.',
                ),
                // Any other HTTP exception (405, 429, …) keeps its status code.
                $e instanceof HttpExceptionInterface => ApiResponse::jsonResponse(
                    $e->getMessage() !== '' ? $e->getMessage() : 'Request could not be processed.',
                    null,
                    $e->getStatusCode(),
                    'failed',
                ),
                // Unexpected errors: a clean 500 envelope in production; in
                // debug, defer to Laravel so the stack trace is still shown.
                default => config('app.debug')
                    ? null
                    : ApiResponse::errorResponse('Server error. Please try again later.'),
            };
        });
    })->create();
