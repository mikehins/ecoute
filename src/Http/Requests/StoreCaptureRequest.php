<?php

declare(strict_types=1);

namespace MikeHins\Ecoute\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class StoreCaptureRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && Gate::forUser($this->user())->check('ecoute-admin');
    }

    /**
     * Get the validation rules for the capture request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'element_selector' => 'required|string|max:5000',
            'parent_selector' => 'nullable|string|max:5000',
            'element_html' => 'required|string|max:50000',
            'parent_html' => 'nullable|string|max:50000',

            // data-* attributes stored separately, not in HTML
            'attributes' => 'nullable|array|max:50',
            'attributes.*' => 'nullable|string|max:500',

            'nearby_text' => 'required|array|max:50',
            'nearby_text.*' => 'string|max:500',

            'user_prompt' => 'required|string|max:2000',

            'interaction.page_title' => 'required|string|max:500',
            'interaction.url' => 'required|url|max:2000',
            'interaction.timestamp' => 'required|date_format:Y-m-d H:i:s',
            'interaction.input_method' => 'required|in:text',

            // ~512 KB base64 ≈ 699,000 chars (base64 is ~1.33× binary size)
            'screenshot' => 'nullable|string|max:700000',

            'template' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+\.(?:md|yml|yaml)$/'],

            // Pre-generated issue body edited by the user in the preview step.
            'body_override' => 'nullable|string|max:100000',

            // User-edited title from the preview step.
            'title_override' => 'nullable|string|max:500',
        ];

        // If a template whitelist is configured, enforce it (deny-by-default when set).
        $whitelist = config('ecoute.github.template_whitelist', []);
        if (is_array($whitelist) && count($whitelist) > 0) {
            // When a whitelist is configured, enforce it (deny-by-default when set).
            $rules['template'] = array_merge((array) $rules['template'], [Rule::in($whitelist)]);
        }

        return $rules;
    }

    /**
     * Prepare the data for validation by computing the deduplication hash.
     */
    public function prepareForValidation(): void
    {
        $this->merge([
            'deduplication_hash' => $this->generateDeduplicationHash(),
        ]);
    }

    /**
     * Compute a SHA-256 hash for deduplication based on selector, normalised prompt, and URL.
     */
    private function generateDeduplicationHash(): string
    {
        $normalised = Str::lower(preg_replace('/[[:punct:]\s]+/', '', (string) $this->user_prompt));
        $url = is_array($this->interaction) ? ($this->interaction['url'] ?? '') : '';
        $userId = (string) ($this->user()?->getAuthIdentifier() ?? 'guest');
        $key = "{$userId}|{$this->element_selector}|{$normalised}|{$url}";

        return hash('sha256', $key);
    }
}
