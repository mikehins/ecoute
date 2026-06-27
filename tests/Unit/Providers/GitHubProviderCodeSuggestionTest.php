<?php

declare(strict_types=1);

use MikeHins\Ecoute\Models\EcouteCapture;
use MikeHins\Ecoute\Services\GitHubProvider;

function makeCodeSuggestionProvider(): GitHubProvider
{
    return new GitHubProvider([
        'token' => 'test-token',
        'owner' => 'test-owner',
        'repo' => 'test-repo',
        'labels' => [],
        'template_whitelist' => [],
        'auto_pr' => ['enabled' => false, 'base_branch' => 'main'],
    ]);
}

function invokePrivateMethod(object $object, string $method, mixed ...$args): mixed
{
    $ref = new ReflectionClass($object);

    return $ref->getMethod($method)->invoke($object, ...$args);
}

test('parses a valid code suggestion extracting file path and after content', function () {
    $provider = makeCodeSuggestionProvider();

    $suggestion = "resources/views/checkout.blade.php\n\nBefore:\n```blade\n<div>old</div>\n```\n\nAfter:\n```blade\n<div>new</div>\n```";

    $result = invokePrivateMethod($provider, 'parseCodeSuggestion', $suggestion);

    expect($result)->not->toBeNull()
        ->and($result['file_path'])->toBe('resources/views/checkout.blade.php')
        ->and($result['content'])->toBe('<div>new</div>');
});

test('parses code suggestion with multi-line after content', function () {
    $provider = makeCodeSuggestionProvider();

    $suggestion = "src/app.ts\n\nBefore:\n```ts\nconst x = 1;\n```\n\nAfter:\n```ts\nconst x: number = 1;\nconst y: string = 'hello';\n```";

    $result = invokePrivateMethod($provider, 'parseCodeSuggestion', $suggestion);

    expect($result)->not->toBeNull()
        ->and($result['file_path'])->toBe('src/app.ts')
        ->and($result['content'])->toBe("const x: number = 1;\nconst y: string = 'hello';");
});

test('returns null for code suggestion without After block', function () {
    $provider = makeCodeSuggestionProvider();

    $suggestion = "file.php\n\nBefore:\n```\nold\n```";

    $result = invokePrivateMethod($provider, 'parseCodeSuggestion', $suggestion);

    expect($result)->toBeNull();
});

test('returns null for empty code suggestion', function () {
    $provider = makeCodeSuggestionProvider();

    $result = invokePrivateMethod($provider, 'parseCodeSuggestion', '');

    expect($result)->toBeNull();
});

test('returns null when code suggestion has no file path on first line', function () {
    $provider = makeCodeSuggestionProvider();

    $suggestion = "\n\nAfter:\n```\ncontent\n```";

    $result = invokePrivateMethod($provider, 'parseCodeSuggestion', $suggestion);

    expect($result)->toBeNull();
});

test('returns null when after block has empty content', function () {
    $provider = makeCodeSuggestionProvider();

    $suggestion = "file.php\n\nBefore:\n```\nold\n```\n\nAfter:\n```\n\n```";

    $result = invokePrivateMethod($provider, 'parseCodeSuggestion', $suggestion);

    expect($result)->toBeNull();
});

test('createPullRequest returns null when code suggestion is missing', function () {
    $provider = makeCodeSuggestionProvider();
    $capture = new EcouteCapture(['element_html' => '<div>x</div>']);
    $aiResponse = ['title' => 'Fix', 'code_suggestion' => null];

    $result = $provider->createPullRequest($capture, $aiResponse);

    expect($result)->toBeNull();
});

test('createPullRequest returns null when code suggestion is not a string', function () {
    $provider = makeCodeSuggestionProvider();
    $capture = new EcouteCapture(['element_html' => '<div>x</div>']);
    $aiResponse = ['title' => 'Fix', 'code_suggestion' => 123];

    $result = $provider->createPullRequest($capture, $aiResponse);

    expect($result)->toBeNull();
});

test('createPullRequest returns null for unparseable code suggestion', function () {
    $provider = makeCodeSuggestionProvider();
    $capture = new EcouteCapture(['element_html' => '<div>x</div>']);
    $aiResponse = ['title' => 'Fix', 'code_suggestion' => 'no proper format here'];

    $result = $provider->createPullRequest($capture, $aiResponse);

    expect($result)->toBeNull();
});

test('createPullRequest returns null for empty string code suggestion', function () {
    $provider = makeCodeSuggestionProvider();
    $capture = new EcouteCapture(['element_html' => '<div>x</div>']);
    $aiResponse = ['title' => 'Fix', 'code_suggestion' => ''];

    $result = $provider->createPullRequest($capture, $aiResponse);

    expect($result)->toBeNull();
});
