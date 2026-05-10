<?php

declare(strict_types=1);

use MikeHins\Ecoute\Services\HtmlSanitizer;

test('sanitizer strips script tags', function () {
    $sanitizer = new HtmlSanitizer;
    $html = '<div>Hello<script>alert("xss")</script></div>';
    $result = $sanitizer->sanitize($html);

    expect($result)->not->toContain('<script>');
    expect($result)->not->toContain('alert');
});

test('sanitizer strips inline event handlers', function () {
    $sanitizer = new HtmlSanitizer;
    $html = '<button onclick="evil()">Click me</button>';
    $result = $sanitizer->sanitize($html);

    expect($result)->not->toContain('onclick');
    expect($result)->not->toContain('evil()');
});

test('sanitizer strips data attributes from html', function () {
    $sanitizer = new HtmlSanitizer;
    $html = '<div data-user-id="123" data-token="secret" class="container">Content</div>';
    $result = $sanitizer->sanitize($html);

    expect($result)->not->toContain('data-user-id');
    expect($result)->not->toContain('data-token');
    expect($result)->toContain('class="container"');
});

test('sanitizer preserves allowed elements and attributes', function () {
    $sanitizer = new HtmlSanitizer;
    $html = '<div class="foo"><p>Hello <span class="bar">world</span></p></div>';
    $result = $sanitizer->sanitize($html);

    expect($result)->toContain('<div');
    expect($result)->toContain('<p>');
    expect($result)->toContain('Hello');
});

test('sanitizer strips javascript href', function () {
    $sanitizer = new HtmlSanitizer;
    $html = '<a href="javascript:void(0)">Click</a>';
    $result = $sanitizer->sanitize($html);

    expect($result)->not->toContain('javascript:');
});

test('sanitizer allows id attribute', function () {
    $sanitizer = new HtmlSanitizer;
    $html = '<div id="main-content">Content</div>';
    $result = $sanitizer->sanitize($html);

    expect($result)->toContain('id="main-content"');
});
