# Ecoute

[![Code Style](https://img.shields.io/badge/code_style-pint-8B6914.svg?style=flat-square)](https://github.com/mikehins/ecoute)
[![Code Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen.svg?style=flat-square)](https://github.com/mikehins/ecoute)
[![PHPStan](https://img.shields.io/badge/phpstan-level_9-blue.svg?style=flat-square)](https://github.com/mikehins/ecoute)

Ecoute lets your admin users click on any element on the page, describe a problem, and have an AI turn that into a structured issue — automatically saved to your database.

No forms to fill out. No screenshots to attach manually. Just press a keyboard shortcut, click the broken thing, and type a sentence.

```
Press Ctrl+Shift+E (Default)
      │
      ▼
Click any element on the page
      │
      ▼
Type what's wrong → Submit
      │
      ▼
AI receives: the element's HTML, surrounding text,
             page URL, title, and your description
      │
      ▼
AI returns a structured issue:
  title, description, type, suggested fix
      │
      ▼
Saved to your database (status: completed)
```

---

## What You Need Before Starting

- **PHP 8.4 or higher**
- **Laravel 10, 11, 12, or 13**
- **An AI API key** — either [OpenAI](https://platform.openai.com) or [Anthropic](https://console.anthropic.com)
- **A queue worker** — a background process that handles the AI call without making the user wait (explained in step 5 below)

---

## Step 1 — Install the package

Install with Composer. Two common workflows are shown below: installing the published package from Packagist, or using the package from a local path during development.

Option A — Install from Packagist (normal use)

```bash
composer require mikehins/ecoute
```

Option B — Local development (install from a local path)

If you're working on the package inside a larger application repository, add a `path` repository entry to your app `composer.json` and then require it:

```json
"repositories": [
  {
    "type": "path",
    "url": "./packages/mikehins/ecoute",
    "options": { "symlink": true }
  }
]
```

Then run:

```bash
composer require mikehins/ecoute --prefer-source
```

Notes:
- Use `--prefer-source` when developing so Composer creates a VCS checkout you can edit.
- If you used the `path` repository approach you can use `--no-update` and run `composer update` after editing composer.json.

---

## Step 2 — Publish migrations and run them

Ecoute stores every capture in a database table. Publish the migration file and then run your migrations:

```bash
# Copy package migrations into your app
php artisan vendor:publish --tag=ecoute-migrations --provider="MikeHins\Ecoute\EcouteServiceProvider"

# Run the migrations
php artisan migrate
```

You should see a line similar to `2024_01_01_000000_create_ecoute_captures_table ......... DONE`.

---

## Step 3 — Publish the configuration and assets

Publish configuration, views and public assets in one step (or individually with the tags shown):

```bash
# Configuration
php artisan vendor:publish --tag=ecoute-config --provider="MikeHins\Ecoute\EcouteServiceProvider"

# Migrations (if not already done)
php artisan vendor:publish --tag=ecoute-migrations --provider="MikeHins\Ecoute\EcouteServiceProvider"

# Browser assets (JS/CSS)
php artisan vendor:publish --tag=ecoute-assets --provider="MikeHins\Ecoute\EcouteServiceProvider"
```

If you have already published `config/ecoute.php`, review the diff carefully before overwriting it. Recent hardening releases add new config keys and safer defaults (notably screenshot storage and code scanning).

---

## Step 4 — Verify assets

If you published the `ecoute-assets` tag the public files will be placed in `public/vendor/ecoute`. When developing with Vite make sure to rebuild assets if needed:

```bash
# For development (hot-reload)
npm run dev

# For production build
npm run build
```

Note: The package ships a prebuilt `overlay.js` and CSS which are published into
`public/vendor/ecoute` when you run `php artisan vendor:publish --tag=ecoute-assets`.
You only need to run the `npm`/`vite` build commands if you plan to modify and
rebuild the JavaScript/CSS assets locally (see CONTRIBUTING.md or the package's
assets directory for developer build steps).

---

## Step 5 — Add your settings to `.env`

Update your application's `.env` with the settings Ecoute needs. Minimum required values for normal use:

```env
ECOUTE_ENABLED=true
ECOUTE_AI_PROVIDER=openai   # or 'anthropic'

# OpenAI (only if provider is openai)
ECOUTE_AI_API_KEY=sk-...    # package reads ECOUTE_AI_API_KEY by default
ECOUTE_OPENAI_MODEL=gpt-4o

# Anthropic (only if provider is anthropic)
ANTHROPIC_API_KEY=sk-ant-...
ECOUTE_ANTHROPIC_MODEL=claude-sonnet-4-5

# Optional: store screenshots on disk for GitHub issue attachments
# Default is 'none' for safer installs.
ECOUTE_SCREENSHOT_STORAGE=none

# Optional: send resolved Blade/Livewire source snippets to the AI prompt.
# Default is false because this can expose host application code to third-party providers.
ECOUTE_CODE_ENABLED=false

# Optional: email to notify when a capture completes
ECOUTE_MAIL_TO=you@example.com
```

Notes:
- Only provide the API key that matches your chosen `ECOUTE_AI_PROVIDER`.
- Keep API keys secret. Ecoute will redact and truncate provider error messages by default to avoid leaking secrets.
- `ECOUTE_SCREENSHOT_STORAGE=none` is now the default. Opt in to `disk` only if you explicitly want screenshots persisted.
- `ECOUTE_CODE_ENABLED=false` is now the default. Opt in only if you are comfortable sending selected source snippets to your AI provider.

---

## Step 6 — Decide who can use Ecoute (Gate)

Ecoute only enables the overlay for users that pass the `ecoute-admin` Gate. Add or update this Gate in `app/Providers/AppServiceProvider.php`

```php
use Illuminate\Support\Facades\Gate;
use App\Models\User;

public function boot(): void
{
    Gate::define('ecoute-admin', fn (User $user) => $user->isAdmin());
}
```

Examples:
- Check a users table column: `fn ($user) => $user->role === 'admin'`
- Spatie roles: `fn ($user) => $user->hasRole('admin')`
- For testing you can temporarily allow all users: `fn ($user) => true` (do not leave this in production).

---

## Step 7 — Add the Overlay to Your Layout

A **layout file** is the shared HTML wrapper that surrounds every page — it contains the `<html>`, `<head>`, and `<body>` tags along with things like your navigation and footer. In most Laravel projects it lives at `resources/views/layouts/app.blade.php`.

Add this one line just before your closing `</body>` tag:

```blade
@ecoute
```

This is a **Blade directive** — a custom tag (similar to `@routes` or `@inertia`) that Ecoute registers automatically when you install the package. When Ecoute is disabled or the current environment is not in the allowed list, the directive outputs nothing at all, so it's safe to leave it in your layout permanently.

---

## Step 7 — Start the queue worker

Captures are processed in the background. Start a worker when testing locally:

```bash
# Recommended for local development (fast restart when code changes)
php artisan queue:work --tries=3 --sleep=1

# Alternatively (simpler) - listen keeps the worker running and reloads the framework each job
php artisan queue:listen
```

Production: run the worker under a process manager (Supervisor, systemd, Laravel Octane, etc.). Make sure `ECOUTE_QUEUE_CONNECTION` in `.env` is set to the queue connection you want to use.

---

## Try It

1. Log in as a user who passes your `ecoute-admin` gate.
2. Open any page in your browser.
3. Press **Ctrl + Shift + E** (or your configured shortcut) — a blue outline appears and the page enters selection mode.
4. Click any element on the page. A small panel slides in at the edge of the screen.
5. Type a description of the problem and press **Send**.
6. Within a few seconds (while your queue worker is running), the capture is processed by the AI.

---

## Check That It Worked

Run this command to see the most recent capture and its status:

```bash
php artisan tinker --execute "print_r(\MikeHins\Ecoute\Models\EcouteCapture::latest()->first()->toArray());"
```

You're looking for `status` to be `completed` and `ai_response` to contain a JSON object with `title`, `description`, `type`, and `suggested_fix`.

If you configured a notification email, check your inbox too.

---

## Configuration Reference

All settings live in `config/ecoute.php` and are driven by `.env` values.

| `.env` key | Default | What it does |
|---|---|---|
| `ECOUTE_ENABLED` | `false` | Master on/off switch. Must be `true` for anything to work. |
| `ECOUTE_SHORTCUT` | `ctrl+shift+e` | Keyboard shortcut to open/close the overlay. Format: `modifier+modifier+key`, e.g. `alt+shift+f`. Valid modifiers: `ctrl`, `alt`, `shift`, `meta`. |
| `ECOUTE_AI_PROVIDER` | `openai` | Which AI service to use. Options: `openai`, `anthropic`. |
| `ECOUTE_AI_API_KEY` | — | Your OpenAI API key (only needed if provider is `openai`). |
| `ECOUTE_OPENAI_MODEL` | `gpt-4o` | Which OpenAI model to use. |
| `ANTHROPIC_API_KEY` | — | Your Anthropic API key (only needed if provider is `anthropic`). |
| `ECOUTE_ANTHROPIC_MODEL` | `claude-sonnet-4-5` | Which Anthropic model to use. |
| `ECOUTE_AI_TEMPERATURE` | `0.0` | How creative the AI is. 0 = consistent/deterministic, 1 = more varied. Leave at 0 for structured issues. |
| `ECOUTE_QUEUE_CONNECTION` | `default` | Which queue connection to use for background jobs. |
| `ECOUTE_QUEUE_NAME` | — | Optional: a named queue to dispatch Ecoute jobs onto (e.g. `ecoute-high`). |
| `ECOUTE_SCREENSHOT_STORAGE` | `none` | Whether to persist screenshots. Use `disk` only when you intentionally want screenshot files stored for issue attachments. |
| `ECOUTE_SCREENSHOT_DISK` | `public` | Filesystem disk used when screenshot storage is `disk`. |
| `ECOUTE_GITHUB_TEMPLATE_WHITELIST` | — | Optional comma-separated allowlist of GitHub issue template filenames. When set, only those templates are listed and accepted. |
| `ECOUTE_CODE_ENABLED` | `false` | Opt-in source-code enrichment for AI prompts. Disabled by default to avoid sending host application source snippets to third-party AI providers. |
| `ECOUTE_CODE_CACHE_TTL` | `3600` | Cache TTL, in seconds, for resolved source snippets. |
| `ECOUTE_CODE_MAX_FILES` | `3` | Maximum number of source files to include when code enrichment is enabled. |
| `ECOUTE_CODE_GREP_MAX` | `2` | Internal limit for selector-based source matches when code enrichment is enabled. |
| `ECOUTE_MAIL_TO` | — | Email address to notify when a capture completes. Leave empty to disable. |

**Environments** — by default Ecoute only activates in `local` and `staging`. This means the overlay won't appear in production even if `ECOUTE_ENABLED=true`. To change this, edit `config/ecoute.php`:

```php
'environments' => ['local', 'staging', 'production'],
```

---

## Security and Privacy

**Sensitive data is masked automatically.** Before anything is sent to the AI, Ecoute redacts:
- Email addresses
- Credit card numbers
- Social Security numbers (SSNs)

**Screenshots are no longer persisted by default.** New installs default to `ECOUTE_SCREENSHOT_STORAGE=none`. If you enable disk storage, treat screenshots as sensitive operational data and choose an appropriate disk, visibility model, retention period, and access policy.

**Source-code scanning is opt-in.** Ecoute can enrich prompts with likely Blade/Livewire snippets, but this is disabled by default behind `ECOUTE_CODE_ENABLED=false`. Enable it only if you are comfortable sending selected application source to your configured AI provider.

**Mark your own sensitive elements.** Add the CSS class `ecoute-sensitive` to any element you want hidden from screenshots:

```html
<div class="ecoute-sensitive">{{ $user->secret_token }}</div>
```

**Rate limiting.** Each user can submit at most 10 captures per minute. After that they'll see a "too many requests" message automatically.

**Deduplication.** If the same user submits the same problem on the same URL twice within 24 hours, the second submission returns the original result without calling the AI again. This prevents duplicate issues and unnecessary API costs.

**Environment protection.** The overlay will not render outside of your allowed environments. If `APP_ENV=production` and `production` is not in the `environments` list in `config/ecoute.php`, `@ecoute` outputs nothing and Ecoute's routes are not registered.

---

## Troubleshooting

| Symptom | Most likely cause | What to do |
|---|---|---|
| Overlay doesn't appear at all | `ECOUTE_ENABLED` is false, or current environment is not in the allowed list | Set `ECOUTE_ENABLED=true` in `.env` and check `config/ecoute.php` → `environments` |
| Alt+Shift+E does nothing | JS file wasn't published, or `<x-ecoute-overlay />` is missing from layout | Re-run `php artisan vendor:publish --tag=ecoute-assets` and check your layout file |
| Submit returns a 403 error | The logged-in user doesn't pass the `ecoute-admin` gate | Check your `Gate::define('ecoute-admin', ...)` rule in `AppServiceProvider` |
| Submit returns a 401 error | User is not logged in | Make sure you're testing while logged in as a user |
| Status stays `pending` forever | Queue worker is not running | Open a terminal and run `php artisan queue:listen` |
| Status is `failed` | Bad API key, network error, or AI returned unexpected output | Run `php artisan pail` to tail your logs and look for the error |
| Second submission returns the same result | Expected — deduplication is working | Same selector + prompt + URL within 24 h returns the cached capture without calling the AI again |

---

## Events You Can Listen To

Ecoute fires events you can hook into from your own code. Add listeners inside the `boot()` method of `app/Providers/AppServiceProvider.php`:

```php
use Illuminate\Support\Facades\Event;
use MikeHins\Ecoute\Events\CaptureProcessed;
use MikeHins\Ecoute\Events\CaptureFailed;

// Fired when the AI successfully processes a capture:
Event::listen(CaptureProcessed::class, function ($event) {
    // $event->capture is the fully processed EcouteCapture model.
    // You could push it to Linear, send a Slack message, open a GitHub issue, etc.
});

// Fired when processing fails after all retries:
Event::listen(CaptureFailed::class, function ($event) {
    // $event->capture->failure_reason contains the error message.
});
```

---

## GitHub integration & creating issue templates

Ecoute can create GitHub issues for processed captures. Integration is optional — enable and configure it using `config/ecoute.php` or environment variables (see the `github` section in the config).

Quick enablement (in your `.env`):

```env
ECOUTE_GITHUB_ENABLED=true
ECOUTE_GITHUB_TOKEN=ghp_...      # Personal Access Token with `repo` scope
ECOUTE_GITHUB_OWNER=your-org-or-username
ECOUTE_GITHUB_REPO=your-repo-name
# Optional: ECOUTE_GITHUB_TEMPLATE_WHITELIST=bug_report.yml,ux_issue.yml
```

What Ecoute does when GitHub is enabled
- Lists templates from your app's `.github/ISSUE_TEMPLATE/` directory (YAML or Markdown files).
- Shows a preview rendered from the selected template (or a default body when none found).
- Creates a GitHub issue via the GitHub REST API using the configured token, owner and repo.
- Appends the capture screenshot only when you explicitly enable screenshot storage and a screenshot file exists.
- Appends any AI-generated code suggestion to the issue body.

Repository templates — where to put them
- Create a directory at the root of your app: `.github/ISSUE_TEMPLATE/`.
- Place any number of `.yml`, `.yaml`, or `.md` files there. Ecoute will discover them and present them in the UI.

Package-provided sample templates
- The package ships with two sample templates you can publish into your app:
  - `bug_report.yml`
  - `ux_issue.md`

You can publish them using the normal vendor:publish flow (this WILL copy files and may overwrite existing files):

```bash
php artisan vendor:publish --tag=ecoute-templates --provider="MikeHins\\Ecoute\\EcouteServiceProvider"
```

If you prefer a safe publish that will not overwrite any existing templates, use the new console helper provided by the package. It skips existing files by default and accepts `--force` to overwrite:

```bash
# Safe publish (skips files that already exist)
php artisan ecoute:publish-templates

# Overwrite existing templates
php artisan ecoute:publish-templates --force
```

YAML template example (`.github/ISSUE_TEMPLATE/bug_report.yml`)

```yaml
name: Bug report
labels: [bug, ecoute]
body:
  - type: textarea
    attributes:
      label: Description
      description: Describe what happened and what you expected.
      placeholder: "What did you do and what went wrong?"
    validations:
      required: true
  - type: dropdown
    attributes:
      label: Urgency
      options:
        - Low
        - Medium
        - High
```

Notes about YAML templates
- Ecoute parses the template and converts each non-markdown field into a `###` heading + placeholder. The AI fills each `{{SECTION:...}}` placeholder with the best matching value (description, suggested fix, location, urgency, etc.).
- The `name:` field is used as the display label in the template picker. `labels:` from the frontmatter are merged into the created issue.

Markdown template example (`.github/ISSUE_TEMPLATE/ux_issue.md`)

```md
# UX issue

Please explain the UX problem here.

<!-- Ecoute will append the AI analysis and metadata below this template when creating the issue. -->
```

Security & safety
- If you want to limit which templates Ecoute may use, set `ECOUTE_GITHUB_TEMPLATE_WHITELIST` to a comma-separated list of filenames (e.g. `bug_report.yml,ux_issue.md`). Ecoute will refuse templates not present in the whitelist.
- The GitHub token must have `repo` (or at least `public_repo` for public repositories) permission to create issues. Keep this token secret and store it in your environment or secret manager.
- If you want screenshots embedded in GitHub issues, set `ECOUTE_SCREENSHOT_STORAGE=disk` and choose a disk whose URLs are intentionally reachable by GitHub readers.

How Ecoute fills & posts the issue
- For YAML templates Ecoute builds a markdown skeleton and resolves each template section using the AI response (description, suggested_fix, type, user prompt, etc.).
- For Markdown templates Ecoute appends an "Ecoute Analysis" block containing the AI description, suggested fix, metadata and a screenshot (if available).
- Screenshots are omitted entirely unless you enable disk storage. When enabled, the issue body embeds the storage URL returned by your configured disk.

Testing templates locally
1. Add a template file to `.github/ISSUE_TEMPLATE/`.
2. Optionally set `ECOUTE_GITHUB_TOKEN`, `ECOUTE_GITHUB_OWNER`, and `ECOUTE_GITHUB_REPO` in `.env` (for local testing you can use a personal repository).
3. Use the Ecoute overlay to capture an issue and choose the template in the preview. The preview endpoint (`/ecoute/preview`) also renders the filled template for inspection before creating the issue.


## The AI Response Format

Every successfully processed capture stores an `ai_response` in the database. It is a JSON object with these four keys:

```json
{
  "title": "Submit button unresponsive on mobile",
  "description": "The checkout submit button does not register taps on iOS Safari. Users cannot complete purchases on mobile devices.",
  "type": "bug",
  "suggested_fix": "Investigate touch event handlers on the button. Consider replacing onClick with onPointerUp for better mobile compatibility."
}
```

`type` will always be one of: `bug`, `ux`, `content`, `performance`, `accessibility`, `other`.
