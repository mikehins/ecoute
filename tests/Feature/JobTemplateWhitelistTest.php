<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use MikeHins\Ecoute\Contracts\AIProviderInterface;
use MikeHins\Ecoute\Jobs\ProcessEcouteCapture;
use MikeHins\Ecoute\Models\EcouteCapture;
use MikeHins\Ecoute\Services\CodeResolver;
use MikeHins\Ecoute\Services\EcouteTransformer;
use MikeHins\Ecoute\Services\GitHubProvider;

beforeEach(function () {
    Gate::define('ecoute-admin', fn ($u) => true);
});

test('job ignores disallowed template at runtime', function () {
    // Ensure whitelist is restrictive
    config(['ecoute.github.template_whitelist' => ['allowed.md']]);
    config([
        'ecoute.github.enabled' => true,
        'ecoute.github.token' => 'test-token',
        'ecoute.github.owner' => 'mikehins',
        'ecoute.github.repo' => 'ecoute',
    ]);

    Http::fake([
        'api.github.com/repos/*/issues' => Http::response(['html_url' => 'https://example.com/1'], 201),
    ]);

    // Create a minimal capture record
    $user = createEcouteUser();
    $capture = EcouteCapture::create([
        'user_id' => $user->id,
        'element_selector' => 'button.submit',
        'element_html' => '<button>Submit</button>',
        'nearby_text' => ['Submit'],
        'user_prompt' => 'Issue',
        'interaction' => ['page_title' => 'P', 'url' => 'https://example.com', 'timestamp' => date('Y-m-d H:i:s'), 'input_method' => 'text'],
        'deduplication_hash' => 'abc',
        'status' => 'pending',
    ]);

    // Bind a real EcouteTransformer instance with simple fakes for dependencies.
    $aiProvider = new class implements AIProviderInterface
    {
        public function complete(string $prompt, float $temperature = 0.0): array
        {
            return [
                'content' => json_encode(['title' => 't', 'description' => 'd', 'type' => 'other', 'suggested_fix' => 'f']),
                'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
            ];
        }
    };

    $codeResolver = new CodeResolver;
    $transformerInstance = new EcouteTransformer($aiProvider, $codeResolver);
    app()->instance(EcouteTransformer::class, $transformerInstance);

    // Dispatch job synchronously by calling handle
    $job = new ProcessEcouteCapture($capture->id, 'disallowed.md');
    $job->handle(app(EcouteTransformer::class), app(GitHubProvider::class));

    $capture->refresh();

    expect($capture->status)->toBe('completed');
    expect($capture->github_issue_url)->toBe('https://example.com/1');
});
