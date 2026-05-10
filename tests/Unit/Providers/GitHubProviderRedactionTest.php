<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use MikeHins\Ecoute\Models\EcouteCapture;
use MikeHins\Ecoute\Services\GitHubProvider;

test('createIssue redacts bearer and sk- tokens from GitHub error messages', function () {
    $body = "Error occurred. Authorization: Bearer ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890\nAlso key: sk-abcdef1234567890abcdef1234567890";

    Http::fake([
        'api.github.com/*' => Http::response($body, 500),
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
        expect($message)->toContain('GitHub API error (500)');
        // Should not contain raw bearer or sk- tokens
        expect($message)->not->toContain('Bearer ABCDEFG');
        expect($message)->not->toContain('sk-abcdef');
        // Should show the redaction marker
        expect($message)->toContain('[REDACTED]');
        throw $e;
    }
});
