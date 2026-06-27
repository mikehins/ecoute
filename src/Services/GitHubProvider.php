<?php

declare(strict_types=1);

namespace MikeHins\Ecoute\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use MikeHins\Ecoute\Models\EcouteCapture;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Throwable;

final class GitHubProvider
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    /**
     * Create a GitHub issue from a processed capture and return the issue HTML URL.
     * When $bodyOverride is provided (user edited the preview), it is used as the issue
     * body directly, skipping template rendering. The screenshot is still appended.
     *
     * @param  array<string, mixed>  $aiResponse
     */
    public function createIssue(EcouteCapture $capture, array $aiResponse, ?string $template = null, ?string $bodyOverride = null, ?string $titleOverride = null): string
    {
        $title = $titleOverride ?? ($aiResponse['title'] ?? 'Ecoute: Captured Issue');

        if ($bodyOverride !== null) {
            $body = $bodyOverride;

            $screenshotMarkdown = $this->screenshotMarkdown($capture);
            if ($screenshotMarkdown !== null) {
                $body .= "\n\n".$screenshotMarkdown;
            }

            $labels = array_unique($this->config['labels'] ?? []);
        } else {
            ['body' => $body, 'labels' => $templateLabels] = $this->buildBody($capture, $aiResponse, $template, $capture->id);
            $labels = array_unique(array_merge($this->config['labels'] ?? [], $templateLabels ?? []));
        }

        $payload = ['title' => $title, 'body' => $body];

        if (! empty($labels)) {
            $payload['labels'] = $labels;
        }

        $response = $this->githubRequest()
            ->post("https://api.github.com/repos/{$this->config['owner']}/{$this->config['repo']}/issues", $payload);

        if (! $response->successful()) {
            $raw = $response->json('message') ?? (string) $response->body();
            $msg = Sanitizer::sanitizeExcerpt((string) $raw, 250);

            throw new RuntimeException('GitHub API error ('.$response->status().'): '.$msg);
        }

        return $response->json('html_url');
    }

    /**
     * Format the GitHub issue body and structured sections without creating the issue.
     * Used by the preview endpoint to show what the issue will look like.
     * Returns both the rendered body and sections array for the interactive UI.
     *
     * @param  array<string, mixed>  $aiResponse
     * @return array{body: string, sections: list<array<string, mixed>>}
     */
    public function formatBody(EcouteCapture $capture, array $aiResponse, ?string $template = null): array
    {
        $result = $this->buildBody($capture, $aiResponse, $template, null);

        return ['body' => $result['body'], 'sections' => $result['sections']];
    }

    /**
     * List all available issue templates in the repository (.yml and .md).
     * For .yml templates the name: frontmatter field is used as the label.
     * For .md templates the filename is converted to a label.
     *
     * @return list<array{label: string, value: string}>
     */
    public function listTemplates(): array
    {
        $dir = base_path('.github/ISSUE_TEMPLATE');

        if (! is_dir($dir)) {
            return [];
        }

        $files = glob($dir.'/*.{yml,yaml,md}', GLOB_BRACE) ?: [];

        $whitelist = (array) ($this->config['template_whitelist'] ?? []);

        return collect($files)
            ->map(fn ($path) => basename($path))
            ->reject(fn ($name) => in_array($name, ['config.yml', 'config.yaml'], true))
            // If a whitelist is configured (non-empty) only return those files
            ->when(count($whitelist) > 0, fn ($coll) => $coll->filter(fn ($name) => in_array($name, $whitelist, true)))
            ->map(fn ($name) => [
                'label' => $this->resolveTemplateLabel($name),
                'value' => $name,
            ])
            ->values()
            ->all();
    }

    /**
     * Build the issue body, template labels, and structured sections.
     * When $captureId is null (preview), the Capture ID line is omitted.
     * Returns sections only for YAML templates; empty array for .md and default bodies.
     *
     * @param  array<string, mixed>  $aiResponse
     * @return array{body: string, labels: list<string>, sections: list<array<string, mixed>>}
     */
    private function buildBody(EcouteCapture $capture, array $aiResponse, ?string $template, ?string $captureId): array
    {
        $type = $aiResponse['type'] ?? 'other';
        $interaction = $capture->interaction ?? [];
        $pageUrl = $interaction['url'] ?? 'Unknown';
        $pageTitle = $interaction['page_title'] ?? 'Unknown';

        $metaLines = [
            "**Type:** `{$type}`",
            "**Page:** [{$pageTitle}]({$pageUrl})",
            "**Element:** `{$capture->element_selector}`",
            "**User Prompt:** {$capture->user_prompt}",
        ];

        if ($captureId !== null) {
            $metaLines[] = "**Capture ID:** `{$captureId}`";
            try {
                $dashboardUrl = route('ecoute.captures.show', $captureId);
                $metaLines[] = "**Diagnostics Player:** [Open Player]({$dashboardUrl})";
            } catch (Throwable $e) {
                // Route generation might fail in testing context
            }
        }

        $meta = implode("  \n", $metaLines);
        $location = "**Page:** [{$pageTitle}]({$pageUrl})  \n**Element:** `{$capture->element_selector}`";

        ['body' => $templateBody, 'labels' => $templateLabels, 'sections' => $rawSections] = $this->fetchTemplate($template);

        $filledSections = [];

        if ($templateBody !== null && ! empty($rawSections)) {
            // YAML template — resolve each section value then fill body skeleton
            $filledSections = $this->resolveSectionValues($rawSections, $aiResponse, $location, $meta, $capture);
            $body = $this->fillYmlSections($templateBody, $filledSections, $meta);
        } elseif ($templateBody !== null) {
            // .md template — append Ecoute analysis below
            $body = $this->fillTemplate($templateBody, $aiResponse, $meta);
        } else {
            $body = $this->defaultBody($aiResponse, $meta);
        }

        $screenshotMarkdown = $this->screenshotMarkdown($capture);
        if ($screenshotMarkdown !== null) {
            $body .= "\n\n".$screenshotMarkdown;
        }

        $body .= $this->diagnosticsBlock($capture);

        return ['body' => $body, 'labels' => $templateLabels, 'sections' => $filledSections];
    }

    /**
     * Build a collapsible diagnostics block from the capture's browser diagnostics.
     */
    private function diagnosticsBlock(EcouteCapture $capture): string
    {
        $diag = $capture->diagnostics ?? [];
        if (empty($diag) || (! isset($diag['console']) && ! isset($diag['network']))) {
            return '';
        }

        $lines = ["\n\n<details>\n<summary>🔍 Browser Diagnostics</summary>\n"];

        if (! empty($diag['console'])) {
            $lines[] = "\n**Console Logs**\n";
            foreach ($diag['console'] as $entry) {
                $level = $entry['level'] ?? 'log';
                $args = isset($entry['args']) ? implode(' ', array_map(fn ($a) => is_string($a) ? $a : json_encode($a), $entry['args'])) : '';
                $lines[] = '- `['.strtoupper($level).']` '.$args;
            }
        }

        if (! empty($diag['network'])) {
            $lines[] = "\n**Network Requests**\n";
            $lines[] = '| Method | URL | Status | Duration |';
            $lines[] = '| --- | --- | --- | --- |';
            foreach ($diag['network'] as $req) {
                $url = $req['url'] ?? '';
                $method = $req['method'] ?? 'GET';
                $status = $req['status'] ?? 0;
                $duration = isset($req['duration']) ? round((float) $req['duration'], 0).'ms' : '?';
                $lines[] = '| '.$method.' | '.$url.' | '.$status.' | '.$duration.' |';
            }
        }

        $lines[] = "\n</details>";

        return implode("\n", $lines);
    }

    /**
     * Generate a markdown image tag for the capture's screenshot using the storage disk URL.
     * Returns null when no screenshot was saved to disk.
     */
    private function screenshotMarkdown(EcouteCapture $capture): ?string
    {
        if (! $capture->screenshot_path) {
            return null;
        }

        $disk = $capture->screenshot_disk ?? config('ecoute.screenshot.disk', 'public');

        try {
            $url = Storage::disk($disk)->url($capture->screenshot_path);
        } catch (Throwable) {
            return null;
        }

        return "### Screenshot\n\n![Screenshot]({$url})";
    }

    /**
     * Fetch an issue template from the local .github/ISSUE_TEMPLATE/ directory.
     * Falls back to auto-discovering the first available template.
     *
     * @return array{body: string|null, labels: list<string>, sections: list<array<string, mixed>>}
     */
    private function fetchTemplate(?string $filename = null): array
    {
        if (! $filename) {
            $filename = $this->listTemplates()[0]['value'] ?? null;
        }

        if (! $filename) {
            return ['body' => null, 'labels' => [], 'sections' => []];
        }

        // If a whitelist is configured, reject filenames not present in it as a safety measure.
        $whitelist = (array) ($this->config['template_whitelist'] ?? []);
        if (count($whitelist) > 0 && ! in_array($filename, $whitelist, true)) {
            return ['body' => null, 'labels' => [], 'sections' => []];
        }

        // Prevent path traversal: only allow simple filenames (no directory separators).
        // Reject early if the provided filename contains path separators or odd characters.
        $original = $filename;
        $filename = basename((string) $filename);

        if ($original === null || $filename === '' || $filename !== $original) {
            return ['body' => null, 'labels' => [], 'sections' => []];
        }

        // Only allow expected extensions and safe filename characters.
        if (! preg_match('/^[A-Za-z0-9._-]+\.(?:yml|yaml|md)$/', $filename)) {
            return ['body' => null, 'labels' => [], 'sections' => []];
        }

        $path = base_path('.github/ISSUE_TEMPLATE/'.$filename);

        if (! is_file($path)) {
            return ['body' => null, 'labels' => [], 'sections' => []];
        }

        $content = @file_get_contents($path);

        if ($content === false || $content === '') {
            return ['body' => null, 'labels' => [], 'sections' => []];
        }

        if (preg_match('/\.(yml|yaml)$/', $filename)) {
            return $this->parseYmlTemplate($content);
        }

        // .md — strip YAML frontmatter, no structured sections
        return [
            'body' => mb_trim((string) preg_replace('/^---.*?---\s*/s', '', $content)),
            'labels' => [],
            'sections' => [],
        ];
    }

    /**
     * Parse a .yml issue template.
     * Extracts field metadata (type, label, description, options, required) and
     * builds a markdown skeleton with {{SECTION:base64}} placeholders for AI filling.
     *
     * @return array{body: string|null, labels: list<string>, sections: list<array<string, mixed>>}
     */
    private function parseYmlTemplate(string $content): array
    {
        try {
            $data = Yaml::parse($content);
        } catch (ParseException) {
            return ['body' => null, 'labels' => [], 'sections' => []];
        }

        $labels = (array) ($data['labels'] ?? []);

        $rawFields = collect($data['body'] ?? [])
            ->reject(fn ($field) => ($field['type'] ?? '') === 'markdown')
            ->filter(fn ($field) => ! empty($field['attributes']['label']))
            ->values()
            ->all();

        /** @var list<array<string, mixed>> $sections */
        $sections = array_map(function ($field) {
            $type = match ($field['type'] ?? 'textarea') {
                'dropdown' => 'dropdown',
                'input' => 'input',
                'checkboxes' => 'checkboxes',
                default => 'textarea',
            };

            /** @var array<string, mixed> $section */
            $section = [
                'label' => $field['attributes']['label'],
                'type' => $type,
                'required' => (bool) ($field['validations']['required'] ?? false),
                'description' => $field['attributes']['description'] ?? null,
                'placeholder' => $field['attributes']['placeholder'] ?? null,
            ];

            if ($type === 'dropdown' || $type === 'checkboxes') {
                $section['options'] = array_values(array_map(
                    fn ($opt) => is_array($opt) ? ($opt['label'] ?? '') : (string) $opt,
                    (array) ($field['attributes']['options'] ?? [])
                ));
            }

            return $section;
        }, $rawFields);

        // Build markdown skeleton: each field becomes a ### heading + placeholder
        $body = collect($sections)
            ->map(function ($section) {
                $line = "### {$section['label']}";
                if ($section['description']) {
                    $line .= "\n_{$section['description']}_";
                }
                $line .= "\n\n{{SECTION:".base64_encode((string) $section['label']).'}}';

                return $line;
            })
            ->implode("\n\n");

        return ['body' => $body ?: null, 'labels' => array_values($labels), 'sections' => $sections];
    }

    /**
     * Resolve AI content for each section definition.
     * Returns sections with an added 'value' key ready for rendering and template filling.
     *
     * @param  list<array<string, mixed>>  $sections
     * @param  array<string, mixed>  $aiResponse
     * @return list<array<string, mixed>>
     */
    private function resolveSectionValues(array $sections, array $aiResponse, string $location, string $meta, EcouteCapture $capture): array
    {
        return array_map(
            fn ($section) => array_merge($section, ['value' => $this->resolveSectionValue($section, $aiResponse, $location, $meta, $capture)]),
            $sections
        );
    }

    /**
     * Resolve the best AI content for a single section based on its label and type.
     * Uses keyword matching (English + French) to map section labels to AI output fields.
     *
     * @param  array<string, mixed>  $section
     * @param  array<string, mixed>  $aiResponse
     */
    private function resolveSectionValue(array $section, array $aiResponse, string $location, string $meta, EcouteCapture $capture): string
    {
        $label = mb_strtolower((string) ($section['label'] ?? ''));
        $normalised = (string) preg_replace('/[^\p{L}\p{N}\s]/u', '', $label);
        $type = $section['type'] ?? 'textarea';
        $options = (array) ($section['options'] ?? []);

        $description = $aiResponse['description'] ?? '';
        $suggestedFix = $aiResponse['suggested_fix'] ?? '';
        $userPrompt = $capture->user_prompt ?? '';
        $aiType = $aiResponse['type'] ?? 'other';

        $urgencyMap = [
            'bug' => 'High — this is a bug preventing expected functionality.',
            'ux' => 'Medium — the user experience is degraded.',
            'performance' => 'Medium — application performance is impacted.',
            'accessibility' => 'High — accessibility requirements are not met.',
            'content' => 'Low — incorrect or missing content.',
            'other' => 'Normal.',
        ];
        $urgencyText = $urgencyMap[$aiType] ?? 'Normal.';

        $screenshotKeys = ['screenshot', 'capture', 'écran', 'ecran', 'vidéo', 'video', 'image', 'screen'];
        $urgencyKeys = ['urgence', 'urgency', 'priorité', 'priorite', 'priority', 'severity', 'gravité', 'gravite', 'impact', 'criticité', 'criticite'];
        $promptKeys = ['essayiez', 'trying', 'essayer', 'tenter', 'vouliez', 'wanted', 'intended', 'doing', 'faire', 'objectif', 'attempting'];
        $describeKeys = ['passé', 'passe', 'happened', 'observed', 'instead', 'lieu', 'issue', 'problem', 'observ', 'quoi', 'what', 'arrivé', 'arrive', 'rencontré', 'rencontre', 'descri', 'replace'];
        $fixKeys = ['fix', 'solution', 'steps', 'étape', 'etape', 'reproduire', 'reproduce', 'corriger', 'résoudre', 'resoudre', 'suggest', 'workaround', 'attendu', 'précis', 'precis', 'expect'];
        $locationKeys = ['trouve', 'where', 'location', 'selector', 'où', 'page', 'element', 'situe'];
        $whenKeys = ['quand', 'when', 'survenu', 'occurred', 'produit', 'time', 'date'];
        $frequencyKeys = ['fréquence', 'frequence', 'frequency', 'often', 'souvent', 'régulier', 'regulier', 'récurrence', 'recurrence'];
        $contextKeys = ['context', 'additional', 'other', 'note', 'info', 'contexte', 'autre', 'additionnel'];

        foreach ($screenshotKeys as $k) {
            if (str_contains($normalised, $k)) {
                return '_See the screenshot attached above, if one was captured._';
            }
        }

        foreach ($urgencyKeys as $k) {
            if (str_contains($normalised, $k)) {
                return $type === 'dropdown' && $options !== []
                    ? $this->pickDropdownOption($options, $urgencyText)
                    : $urgencyText;
            }
        }

        foreach ($promptKeys as $k) {
            if (str_contains($normalised, $k)) {
                return $userPrompt;
            }
        }

        foreach ($describeKeys as $k) {
            if (str_contains($normalised, $k)) {
                return $description;
            }
        }

        foreach ($fixKeys as $k) {
            if (str_contains($normalised, $k)) {
                return $suggestedFix;
            }
        }

        foreach ($locationKeys as $k) {
            if (str_contains($normalised, $k)) {
                return $location;
            }
        }

        foreach ($whenKeys as $k) {
            if (str_contains($normalised, $k)) {
                return 'Observed at the time of this report.';
            }
        }

        foreach ($frequencyKeys as $k) {
            if (str_contains($normalised, $k)) {
                return $type === 'dropdown' && $options !== []
                    ? $this->pickDropdownOption($options, null, true)
                    : 'Unknown — first reported instance.';
            }
        }

        foreach ($contextKeys as $k) {
            if (str_contains($normalised, $k)) {
                return $meta;
            }
        }

        // Unrecognised — fall back to AI description
        return $description;
    }

    /**
     * Pick the most appropriate option from a dropdown list.
     * When $pickLast is true (e.g. frequency fields), returns the last/least-severe option.
     * Otherwise attempts to match severity keywords from $preferred, falling back to first option.
     *
     * @param  list<string>  $options
     */
    private function pickDropdownOption(array $options, ?string $preferred, bool $pickLast = false): string
    {
        if ($options === []) {
            return $preferred ?? '';
        }

        if ($pickLast) {
            return end($options) ?: $options[0];
        }

        if ($preferred !== null) {
            $prefLower = mb_strtolower($preferred);

            foreach ($options as $option) {
                $lower = mb_strtolower($option);

                if (str_contains($prefLower, 'high') && (str_contains($lower, 'high') || str_contains($lower, 'élevé') || str_contains($lower, 'critique'))) {
                    return $option;
                }

                if (str_contains($prefLower, 'medium') && (str_contains($lower, 'medium') || str_contains($lower, 'moyen'))) {
                    return $option;
                }

                if (str_contains($prefLower, 'low') && (str_contains($lower, 'low') || str_contains($lower, 'faible') || str_contains($lower, 'bas'))) {
                    return $option;
                }
            }
        }

        return $options[0];
    }

    /**
     * Fill a .yml-derived section skeleton with pre-resolved section values.
     * Each {{SECTION:base64label}} placeholder is replaced by looking up the
     * section's pre-resolved value from $filledSections.
     *
     * @param  list<array<string, mixed>>  $filledSections  Sections with 'value' key set.
     */
    private function fillYmlSections(string $template, array $filledSections, string $meta): string
    {
        $lookup = [];
        foreach ($filledSections as $section) {
            $lookup[base64_encode((string) $section['label'])] = (string) $section['value'];
        }

        $filled = (string) preg_replace_callback(
            '/\{\{SECTION:([A-Za-z0-9+\/=]+)}}/',
            fn ($m) => $lookup[$m[1]] ?? '',
            $template
        );

        return $filled."\n\n---\n".$meta;
    }

    /**
     * Fill a .md template by appending the Ecoute AI analysis block.
     *
     * @param  array<string, mixed>  $aiResponse
     */
    private function fillTemplate(string $template, array $aiResponse, string $meta): string
    {
        $analysis = implode("\n\n", [
            '## Ecoute Analysis',
            "**Description**\n".($aiResponse['description'] ?? ''),
            "**Suggested Fix**\n".($aiResponse['suggested_fix'] ?? ''),
            "---\n".$meta,
        ]);

        return $template."\n\n".$analysis;
    }

    /**
     * Build a default issue body when no template is configured.
     *
     * @param  array<string, mixed>  $aiResponse
     */
    private function defaultBody(array $aiResponse, string $meta): string
    {
        return implode("\n\n", [
            "## Description\n".($aiResponse['description'] ?? ''),
            "## Suggested Fix\n".($aiResponse['suggested_fix'] ?? ''),
            "---\n".$meta,
        ]);
    }

    /**
     * Resolve the display label for a template file.
     * For .yml files, reads the name: field from the frontmatter.
     * For .md files, derives the label from the filename.
     */
    private function resolveTemplateLabel(string $filename): string
    {
        if (preg_match('/\.(yml|yaml)$/', $filename)) {
            $path = base_path('.github/ISSUE_TEMPLATE/'.$filename);

            if (is_file($path)) {
                try {
                    $data = Yaml::parse(file_get_contents($path));
                    if (! empty($data['name'])) {
                        return (string) $data['name'];
                    }
                } catch (ParseException) {
                    // Fall through to filename label
                }
            }
        }

        return $this->templateLabel($filename);
    }

    /**
     * Convert a template filename to a human-readable label.
     * e.g. "bug_report.md" → "Bug Report"
     */
    private function templateLabel(string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);

        return ucwords(str_replace(['_', '-'], ' ', $name));
    }

    /**
     * Return a pre-configured HTTP client for GitHub API requests.
     */
    private function githubRequest(): PendingRequest
    {
        return Http::withToken($this->config['token'])
            ->connectTimeout(5)
            ->timeout(15)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ]);
    }
}
