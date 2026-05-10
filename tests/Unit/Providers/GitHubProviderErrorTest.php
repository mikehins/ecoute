<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use MikeHins\Ecoute\Models\EcouteCapture;
use MikeHins\Ecoute\Services\GitHubProvider;

test('createIssue throws sanitized message when GitHub returns long error body', function () {
    Http::fake([
        'api.github.com/*' => Http::response(str_repeat('SENSITIVE_BODY_', 50), 500),
    ]);

    $config = ['owner' => 'x', 'repo' => 'y', 'token' => 't', 'labels' => []];
    $provider = new GitHubProvider($config);

    $capture = new EcouteCapture([
        'element_selector' => 'a',
        'element_html' => '<a>link</a>',
        'nearby_text' => ['link'],
        'user_prompt' => 'test',
        'interaction' => ['page_title' => 'P', 'url' => 'https://example.test', 'timestamp' => date('Y-m-d H:i:s'), 'input_method' => 'text'],
    ]);

    $this->expectException(RuntimeException::class);
    try {
        $provider->createIssue($capture, ['title' => 't', 'description' => 'd', 'suggested_fix' => 'f', 'type' => 'other']);
    } catch (RuntimeException $e) {
        $message = $e->getMessage();
        // Should mention GitHub API error and be truncated (not equal to the full original body)
        expect($message)->toContain('GitHub API error (500)');
        // Not equal to the original full long body
        expect($message)->not->toContain(str_repeat('SENSITIVE_BODY_', 50));
        // Should be reasonably short
        expect(mb_strlen($message))->toBeLessThanOrEqual(320);
        throw $e;
    }
});
