<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Produces a single, consistent JSON envelope for every API response:
 *
 *     { "status": "success|failed", "code": <http>, "message": "...", "data": ... }
 *
 * Resolved from the container and exposed through the `ApiResponse` facade so
 * controllers return responses uniformly without depending on this class
 * directly.
 */
final class JsonResponseService
{
    /**
     * 200 — a successful operation.
     */
    public function successResponse(string $message, mixed $data = null): JsonResponse
    {
        return $this->jsonResponse($message, $data, Response::HTTP_OK, 'success');
    }

    /**
     * 422 — the request failed validation.
     */
    public function validationErrorResponse(string $message, mixed $data = null): JsonResponse
    {
        return $this->jsonResponse($message, $data, Response::HTTP_UNPROCESSABLE_ENTITY, 'failed');
    }

    /**
     * 401 — the request is not authenticated.
     */
    public function unauthorizedErrorResponse(string $message, mixed $data = null): JsonResponse
    {
        return $this->jsonResponse($message, $data, Response::HTTP_UNAUTHORIZED, 'failed');
    }

    /**
     * 403 — the request is authenticated but not permitted.
     */
    public function forbiddenErrorResponse(string $message, mixed $data = null): JsonResponse
    {
        return $this->jsonResponse($message, $data, Response::HTTP_FORBIDDEN, 'failed');
    }

    /**
     * 500 — an unexpected server error.
     */
    public function errorResponse(string $message, mixed $data = null): JsonResponse
    {
        return $this->jsonResponse($message, $data, Response::HTTP_INTERNAL_SERVER_ERROR, 'failed');
    }

    /**
     * 404 — the requested resource does not exist.
     */
    public function notFoundErrorResponse(string $message, mixed $data = null): JsonResponse
    {
        return $this->jsonResponse($message, $data, Response::HTTP_NOT_FOUND, 'failed');
    }

    /**
     * Build the JSON envelope. Public so callers can emit any status code
     * (e.g. 201 Created) while keeping the response shape consistent.
     */
    public function jsonResponse(string $message, mixed $data, int $code, string $status): JsonResponse
    {
        if (is_array($data) && $data === []) {
            $data = null;
        }

        return response()->json([
            'status' => $status,
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ], $code);
    }
}
