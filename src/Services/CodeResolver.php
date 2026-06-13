<?php

declare(strict_types=1);

namespace MikeHins\Ecoute\Services;

use FilesystemIterator;
use Illuminate\Support\Facades\Cache;
use MikeHins\Ecoute\Models\EcouteCapture;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class CodeResolver
{
    /** Maximum number of source files to include in context. */
    private const MAX_FILES = 3;

    /** Maximum lines to include per file snippet. */
    private const MAX_LINES_PER_FILE = 80;

    /** Maximum characters for the entire formatted source block. */
    private const MAX_TOTAL_CHARS = 6000;

    /**
     * Resolve source files relevant to the captured element.
     * Tries Livewire component detection, URL-to-view mapping, and CSS selector grep — in that order.
     *
     * @return list<array{path: string, language: string, content: string}>
     */
    public function resolve(EcouteCapture $capture): array
    {
        if (! config('ecoute.code.enabled', false)) {
            return [];
        }

        $cacheTtl = (int) config('ecoute.code.cache_ttl', 3600);
        $cacheKey = 'ecoute:code_resolve:'.sha1((string) ($capture->deduplication_hash ?? $capture->id));

        $results = Cache::remember($cacheKey, $cacheTtl, function () use ($capture) {
            $results = [];

            $results = array_merge($results, $this->resolveFromLivewire(
                $capture->element_html ?? '',
                $capture->parent_html
            ));

            if (count($results) < self::MAX_FILES) {
                $results = array_merge($results, $this->resolveFromUrl(
                    ($capture->interaction ?? [])['url'] ?? ''
                ));
            }

            if (count($results) < self::MAX_FILES) {
                $results = array_merge($results, $this->resolveFromSelector(
                    $capture->element_selector ?? ''
                ));
            }

            // Deduplicate by path
            $seen = [];
            $unique = [];
            foreach ($results as $result) {
                if (! isset($seen[$result['path']])) {
                    $seen[$result['path']] = true;
                    $unique[] = $result;
                }
            }

            return $unique;
        });

        $maxFiles = (int) config('ecoute.code.max_files', self::MAX_FILES);

        return array_slice($results, 0, $maxFiles);
    }

    /**
     * Format resolved files into a string block for prompt injection.
     * Returns an empty string when no files were found.
     *
     * @param  list<array{path: string, language: string, content: string}>  $files
     */
    public function format(array $files): string
    {
        if ($files === []) {
            return '';
        }

        $lines = [
            '',
            'SOURCE CODE:',
            'The following files were detected as relevant to this capture. Use them to pinpoint the exact change needed.',
            '',
        ];

        $total = 0;

        foreach ($files as $file) {
            $snippet = $file['content'];

            if ($total + mb_strlen($snippet) > self::MAX_TOTAL_CHARS) {
                $remaining = self::MAX_TOTAL_CHARS - $total;
                if ($remaining < 100) {
                    break;
                }
                $snippet = mb_substr($snippet, 0, $remaining).'... (truncated)';
            }

            $lines[] = "File: {$file['path']} ({$file['language']})";
            $lines[] = '```'.$file['language'];
            $lines[] = $snippet;
            $lines[] = '```';
            $lines[] = '';

            $total += mb_strlen($snippet);
        }

        return implode("\n", $lines);
    }

    /**
     * Detect Livewire components from wire:snapshot attribute in the captured HTML.
     * Maps the component name to its class file and Blade view.
     *
     * @return list<array{path: string, language: string, content: string}>
     */
    private function resolveFromLivewire(string $html, ?string $parentHtml): array
    {
        $combined = $html.($parentHtml ?? '');
        $results = [];

        // wire:snapshot='{"memo":{"name":"admin.orders.history",...}}'
        if (! preg_match('/wire:snapshot=["\']([^"\']+)["\']/', $combined, $m)) {
            return [];
        }

        $json = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $data = json_decode($json, true);
        $name = $data['memo']['name'] ?? null;

        if (! $name || ! is_string($name)) {
            return [];
        }

        // "admin.orders.history" → app/Livewire/Admin/Orders/History.php
        $parts = array_map(fn ($p) => ucfirst($p), explode('.', $name));
        $classPath = 'app/Livewire/'.implode('/', $parts).'.php';

        if ($file = $this->loadFile(base_path($classPath), null, $classPath)) {
            $results[] = $file;
        }

        // "admin.orders.history" → resources/views/livewire/admin/orders/history.blade.php
        $viewPath = 'resources/views/livewire/'.str_replace('.', '/', $name).'.blade.php';

        if ($file = $this->loadFile(base_path($viewPath), null, $viewPath)) {
            $results[] = $file;
        }

        return $results;
    }

    /**
     * Map the page URL path to probable Blade view files.
     * Strips numeric segments (IDs) and tries common Laravel view conventions.
     *
     * @return list<array{path: string, language: string, content: string}>
     */
    private function resolveFromUrl(string $url): array
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! $path || $path === '/') {
            return [];
        }

        $segments = array_values(array_filter(
            explode('/', mb_trim($path, '/')),
            fn ($s) => $s !== '' && ! is_numeric($s)
        ));

        if ($segments === []) {
            return [];
        }

        $basePath = implode('/', $segments);
        $lastSegment = end($segments);
        $parentPath = count($segments) > 1
            ? implode('/', array_slice($segments, 0, -1))
            : null;

        $candidates = [
            "resources/views/{$basePath}.blade.php",
            "resources/views/{$basePath}/index.blade.php",
        ];

        if ($parentPath && in_array($lastSegment, ['edit', 'create', 'show', 'index'], true)) {
            $candidates[] = "resources/views/{$parentPath}/{$lastSegment}.blade.php";
        }

        foreach ($candidates as $candidate) {
            if ($file = $this->loadFile(base_path($candidate), null, $candidate)) {
                return [$file];
            }
        }

        return [];
    }

    /**
     * Extract a specific class or ID from the CSS selector and grep Blade views for it.
     * Only searches for terms that look component-specific (hyphenated or long),
     * to avoid false positives with generic utility class names like "submit" or "btn".
     *
     * @return list<array{path: string, language: string, content: string}>
     */
    private function resolveFromSelector(string $selector): array
    {
        $term = null;

        // Prefer ID — these are usually unique and specific
        if (preg_match('/#([\w-]{4,})/', $selector, $m)) {
            $term = $m[1];
        } elseif (preg_match_all('/\.([\w-]+)/', $selector, $matches)) {
            $classes = array_filter(
                $matches[1],
                fn ($c) => ! str_starts_with($c, 'ecoute-') && $this->isSpecificTerm($c)
            );
            if (! empty($classes)) {
                $term = end($classes);
            }
        }

        if ($term === null) {
            return [];
        }

        return $this->grepViews($term);
    }

    /**
     * Scan Blade view files for the given search term, returning files that contain it.
     * Limited to 2 files to avoid context bloat.
     *
     * @return list<array{path: string, language: string, content: string}>
     */
    private function grepViews(string $term): array
    {
        $viewsDir = base_path('resources/views');

        if (! is_dir($viewsDir)) {
            return [];
        }

        $results = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($viewsDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->getExtension() !== 'php') {
                continue;
            }

            $absolutePath = $fileInfo->getPathname();
            $content = @file_get_contents($absolutePath);

            if ($content === false || ! str_contains($content, $term)) {
                continue;
            }

            $relativePath = 'resources/views/'.mb_ltrim(
                str_replace(realpath($viewsDir) ?: $viewsDir, '', realpath($absolutePath) ?: $absolutePath),
                DIRECTORY_SEPARATOR
            );
            $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

            if ($file = $this->loadFile($absolutePath, $term, $relativePath)) {
                $results[] = $file;

                if (count($results) >= 2) {
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * Read a file and return a representative snippet centred around $searchTerm.
     * Returns null if the file does not exist or is unreadable.
     *
     * @return array{path: string, language: string, content: string}|null
     */
    private function loadFile(string $absolutePath, ?string $searchTerm = null, ?string $displayPath = null): ?array
    {
        $realBase = realpath(base_path());
        $realPath = realpath($absolutePath);

        if ($realPath === false || $realBase === false || ! str_starts_with($realPath, $realBase)) {
            return null;
        }

        if (! is_file($realPath)) {
            return null;
        }

        $content = @file_get_contents($absolutePath);

        if ($content === false || $content === '') {
            return null;
        }

        $lines = explode("\n", $content);
        $totalLines = count($lines);
        $centerLine = 0;

        if ($searchTerm !== null) {
            foreach ($lines as $i => $line) {
                if (str_contains($line, $searchTerm)) {
                    $centerLine = $i;
                    break;
                }
            }
        }

        $half = (int) (self::MAX_LINES_PER_FILE / 2);
        $start = max(0, $centerLine - $half);
        $end = min($totalLines, $start + self::MAX_LINES_PER_FILE);

        $snippet = implode("\n", array_slice($lines, $start, $end - $start));

        if (mb_strlen($snippet) > 3000) {
            $snippet = mb_substr($snippet, 0, 3000)."\n... (truncated)";
        }

        $path = $displayPath ?? $this->toRelativePath($absolutePath);

        return [
            'path' => $path,
            'language' => $this->languageForPath($path),
            'content' => $snippet,
        ];
    }

    /**
     * Determine whether a CSS class name is specific enough to be worth searching for.
     * Avoids generic utility names like "submit", "btn", "modal", "active".
     */
    private function isSpecificTerm(string $term): bool
    {
        // Hyphenated compound names are usually component-specific
        if (str_contains($term, '-') && mb_strlen($term) >= 5) {
            return true;
        }

        // Long single-word names are usually specific
        if (mb_strlen($term) >= 10) {
            return true;
        }

        return false;
    }

    /**
     * Make an absolute path relative to base_path() for display.
     */
    private function toRelativePath(string $absolutePath): string
    {
        $base = base_path();
        $relative = str_replace($base.DIRECTORY_SEPARATOR, '', $absolutePath);

        return str_replace(DIRECTORY_SEPARATOR, '/', $relative);
    }

    /**
     * Infer the language identifier from a file path for syntax highlighting.
     */
    private function languageForPath(string $path): string
    {
        if (str_ends_with($path, '.blade.php')) {
            return 'blade';
        }

        return match (pathinfo($path, PATHINFO_EXTENSION)) {
            'php' => 'php',
            'js' => 'javascript',
            'ts' => 'typescript',
            'vue' => 'vue',
            'css' => 'css',
            'scss' => 'scss',
            default => 'text',
        };
    }
}
