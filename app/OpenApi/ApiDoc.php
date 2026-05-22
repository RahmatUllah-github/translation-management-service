<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Global OpenAPI document: API metadata, server, the bearer-token security
 * scheme, and the reusable component schemas referenced across endpoints.
 *
 * This class holds no logic — it exists purely as an anchor for the
 * attributes that L5-Swagger / swagger-php scan to build the spec.
 */
#[OA\Info(
    version: '1.0.0',
    title: 'Translation Management Service API',
    description: 'A high-performance API for storing, searching and exporting '
        .'localized translations. Authenticate via POST /api/v1/auth/login, then '
        .'send the returned token as `Authorization: Bearer <token>`.',
)]
#[OA\Server(url: '/', description: 'Current host')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    description: 'Sanctum personal access token issued by /api/v1/auth/login.',
)]
#[OA\Tag(name: 'Authentication', description: 'Login, logout and identity.')]
#[OA\Tag(name: 'Translations', description: 'CRUD, search and JSON export.')]

// ---- Reusable response schemas ---------------------------------------------

#[OA\Schema(
    schema: 'User',
    title: 'User',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'API Admin'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@example.com'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Locale',
    title: 'Locale',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'code', type: 'string', example: 'en'),
        new OA\Property(property: 'name', type: 'string', example: 'English'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Tag',
    title: 'Tag',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 3),
        new OA\Property(property: 'name', type: 'string', example: 'web'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Translation',
    title: 'Translation',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 42),
        new OA\Property(property: 'key', type: 'string', example: 'homepage.title'),
        new OA\Property(property: 'content', type: 'string', example: 'Welcome'),
        new OA\Property(property: 'locale', ref: '#/components/schemas/Locale'),
        new OA\Property(
            property: 'tags',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/Tag'),
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ValidationError',
    title: 'Validation error (HTTP 422)',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The key field is required.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            example: ['key' => ['The key field is required.']],
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'Error',
    title: 'Generic error',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
    ],
    type: 'object',
)]
final class ApiDoc
{
}
