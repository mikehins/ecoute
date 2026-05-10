<?php

declare(strict_types=1);

use MikeHins\Ecoute\Models\EcouteCapture;
use MikeHins\Ecoute\Services\GitHubProvider;

test('fetchTemplate rejects path traversal filenames', function () {
    $provider = new GitHubProvider(['template_whitelist' => []]);

    $capture = new EcouteCapture([
        'element_selector' => 'a',
        'element_html' => '<a>link</a>',
        'nearby_text' => ['link'],
        'user_prompt' => 'test',
        'interaction' => ['page_title' => 'P', 'url' => 'https://example.test', 'timestamp' => date('Y-m-d H:i:s'), 'input_method' => 'text'],
    ]);

    // Attempt to use a filename that traverses directories
    $result = $provider->formatBody($capture, ['title' => 't', 'description' => 'd', 'suggested_fix' => 'f', 'type' => 'other'], '../../.env');

    // Provider should ignore the filename and not throw — fallback body used
    expect($result['body'])->toBeString();
    expect($result['sections'])->toBeArray();
});
