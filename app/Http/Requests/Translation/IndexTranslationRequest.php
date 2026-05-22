<?php

declare(strict_types=1);

namespace App\Http\Requests\Translation;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the filter/search query string for listing translations.
 * Filters are intentionally lenient — an unknown locale simply yields an
 * empty result rather than a validation error.
 */
final class IndexTranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'locale' => ['sometimes', 'string', 'max:10'],
            'key' => ['sometimes', 'string', 'max:191'],
            'content' => ['sometimes', 'string', 'max:191'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:64'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'cursor' => ['sometimes', 'string'],
        ];
    }

    /**
     * Page size, clamped by the validation rules above (1-200, default 50).
     */
    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 50);
    }
}
