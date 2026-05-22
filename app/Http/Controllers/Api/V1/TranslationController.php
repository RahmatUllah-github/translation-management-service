<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Facades\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Translation\ExportTranslationRequest;
use App\Http\Requests\Translation\IndexTranslationRequest;
use App\Http\Requests\Translation\StoreTranslationRequest;
use App\Http\Requests\Translation\UpdateTranslationRequest;
use App\Http\Resources\TranslationResource;
use App\Models\Locale;
use App\Models\Translation;
use App\Services\Translation\TranslationExportService;
use App\Services\Translation\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

/**
 * Thin controller: validates via Form Requests, delegates all logic to the
 * services, and returns responses through the standard ApiResponse envelope.
 */
final class TranslationController extends Controller
{
    public function __construct(
        private readonly TranslationService $translations,
        private readonly TranslationExportService $exporter,
    ) {
    }

    /**
     * List & search translations (filters: locale, key, content, tags),
     * cursor-paginated for stable O(page) performance at any depth.
     */
    #[OA\Get(
        path: '/api/v1/translations',
        operationId: 'translationsIndex',
        summary: 'List & search translations',
        description: 'Filterable, cursor-paginated list. All filters are optional and combine with AND.',
        security: [['bearerAuth' => []]],
        tags: ['Translations'],
        parameters: [
            new OA\Parameter(name: 'locale', in: 'query', description: 'Locale code, e.g. en', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'key', in: 'query', description: 'Key prefix match', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'content', in: 'query', description: 'Full-text search over content', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'tags[]', in: 'query', description: 'Tag name(s); ANY-match', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string'))),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Page size (1-200, default 50)', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'cursor', in: 'query', description: 'Pagination cursor', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated translations',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'code', type: 'integer', example: 200),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', type: 'object', properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Translation')),
                        new OA\Property(property: 'links', type: 'object'),
                        new OA\Property(property: 'meta', type: 'object'),
                    ]),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 422, description: 'Invalid filters', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function index(IndexTranslationRequest $request): JsonResponse
    {
        $paginator = $this->translations->paginate($request->validated(), $request->perPage());

        // The resource collection is resolved to its array form so the
        // pagination `links`/`meta` survive inside the envelope's `data`.
        return ApiResponse::successResponse(
            'Translations retrieved successfully.',
            TranslationResource::collection($paginator)->response()->getData(true),
        );
    }

    /**
     * Create a translation.
     */
    #[OA\Post(
        path: '/api/v1/translations',
        operationId: 'translationsStore',
        summary: 'Create a translation',
        security: [['bearerAuth' => []]],
        tags: ['Translations'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['locale', 'key', 'content'],
                properties: [
                    new OA\Property(property: 'locale', type: 'string', example: 'en'),
                    new OA\Property(property: 'key', type: 'string', example: 'homepage.title'),
                    new OA\Property(property: 'content', type: 'string', example: 'Welcome'),
                    new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'string'), example: ['web', 'mobile']),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Created',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'code', type: 'integer', example: 201),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/Translation'),
                ]),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function store(StoreTranslationRequest $request): JsonResponse
    {
        $translation = $this->translations->create($request->validated());

        return ApiResponse::jsonResponse(
            'Translation created successfully.',
            new TranslationResource($translation),
            Response::HTTP_CREATED,
            'success',
        );
    }

    /**
     * Show a single translation. The model is resolved by route-model binding.
     */
    #[OA\Get(
        path: '/api/v1/translations/{translation}',
        operationId: 'translationsShow',
        summary: 'Get a translation by id',
        security: [['bearerAuth' => []]],
        tags: ['Translations'],
        parameters: [
            new OA\Parameter(name: 'translation', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The translation',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'code', type: 'integer', example: 200),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/Translation'),
                ]),
            ),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function show(Translation $translation): JsonResponse
    {
        return ApiResponse::successResponse(
            'Translation retrieved successfully.',
            new TranslationResource($this->translations->find($translation)),
        );
    }

    /**
     * Update a translation (PUT/PATCH — partial updates supported).
     */
    #[OA\Put(
        path: '/api/v1/translations/{translation}',
        operationId: 'translationsUpdate',
        summary: 'Update a translation',
        description: 'Partial update — every field is optional.',
        security: [['bearerAuth' => []]],
        tags: ['Translations'],
        parameters: [
            new OA\Parameter(name: 'translation', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'locale', type: 'string', example: 'fr'),
                new OA\Property(property: 'key', type: 'string', example: 'homepage.title'),
                new OA\Property(property: 'content', type: 'string', example: 'Bienvenue'),
                new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'string')),
            ]),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Updated',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'status', type: 'string', example: 'success'),
                    new OA\Property(property: 'code', type: 'integer', example: 200),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'data', ref: '#/components/schemas/Translation'),
                ]),
            ),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function update(UpdateTranslationRequest $request, Translation $translation): JsonResponse
    {
        return ApiResponse::successResponse(
            'Translation updated successfully.',
            new TranslationResource($this->translations->update($translation, $request->validated())),
        );
    }

    /**
     * Delete a translation.
     */
    #[OA\Delete(
        path: '/api/v1/translations/{translation}',
        operationId: 'translationsDestroy',
        summary: 'Delete a translation',
        security: [['bearerAuth' => []]],
        tags: ['Translations'],
        parameters: [
            new OA\Parameter(name: 'translation', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function destroy(Translation $translation): JsonResponse
    {
        $this->translations->delete($translation);

        return ApiResponse::successResponse('Translation deleted successfully.');
    }

    /**
     * Export a locale's translations as a flat {"key":"value"} JSON document.
     *
     * This endpoint intentionally bypasses the ApiResponse envelope: its
     * contract is a flat key/value map consumed directly by frontend i18n
     * libraries (e.g. vue-i18n). An ETag derived from the locale's export
     * version lets clients revalidate cheaply — an unchanged dataset returns
     * 304 with no body. `no-cache` forces revalidation, so the response is
     * always up to date.
     */
    #[OA\Get(
        path: '/api/v1/translations/export',
        operationId: 'translationsExport',
        summary: 'Export a locale as a flat JSON map',
        description: 'Returns {"homepage.title":"Welcome", ...} for frontend i18n. '
            .'Send the returned ETag back as If-None-Match to receive 304 when unchanged.',
        security: [['bearerAuth' => []]],
        tags: ['Translations'],
        parameters: [
            new OA\Parameter(name: 'locale', in: 'query', required: true, description: 'Locale code', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Flat translation map',
                content: new OA\JsonContent(type: 'object', example: ['homepage.title' => 'Welcome', 'auth.login' => 'Log in']),
            ),
            new OA\Response(response: 304, description: 'Not modified (ETag match)'),
            new OA\Response(response: 422, description: 'Unknown locale', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
    public function export(ExportTranslationRequest $request): Response
    {
        $locale = Locale::query()
            ->where('code', $request->validated('locale'))
            ->firstOrFail();

        $version = $this->exporter->version($locale->id);
        $etag = sprintf('"%s"', md5($locale->code.':'.$version));

        $headers = [
            'ETag' => $etag,
            'Cache-Control' => 'no-cache, must-revalidate',
        ];

        if (trim((string) $request->header('If-None-Match')) === $etag) {
            return response('', Response::HTTP_NOT_MODIFIED, $headers);
        }

        return response(
            $this->exporter->exportJson($locale),
            Response::HTTP_OK,
            $headers + ['Content-Type' => 'application/json'],
        );
    }
}
