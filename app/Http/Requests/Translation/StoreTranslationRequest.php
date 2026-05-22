<?php

declare(strict_types=1);

namespace App\Http\Requests\Translation;

use App\Models\Locale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates translation creation.
 *
 * The public API references locales by their human-friendly `code`; the
 * internal `locale_id` is resolved here once, before validation, so the
 * compound-unique rule and the service can both rely on it.
 */
final class StoreTranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Resolve `locale` code -> `locale_id` before the rules run.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('locale')) {
            $this->merge([
                'locale_id' => Locale::query()
                    ->where('code', trim((string) $this->input('locale')))
                    ->value('id'),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', Rule::exists('locales', 'code')],
            // Derived in prepareForValidation(); nullable so a bad `locale`
            // surfaces a single, clear error rather than two.
            'locale_id' => ['nullable', 'integer'],
            'key' => [
                'required', 'string', 'max:191',
                // One value per key per locale — mirrors the DB unique index.
                Rule::unique('translations', 'key')->where('locale_id', $this->input('locale_id')),
            ],
            'content' => ['required', 'string', 'max:65535'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:64', 'distinct'],
        ];
    }
}
