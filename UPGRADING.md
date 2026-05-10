UPGRADING
========

vNext - Hardening release
-------------------------

This release changes Ecoute's defaults and trust boundaries to be safer for production use.

Before deploying

1. Ensure your runtime is on PHP 8.4 or newer.
1. Publish or manually merge the latest `config/ecoute.php`.
2. Run the new migration that drops legacy raw screenshot data.
3. Review any workflows that depended on screenshots being stored automatically.
4. Review any workflows that depended on source-code enrichment being enabled implicitly.

Configuration changes you must merge

- New config key: `ecoute.queue.name`
- New config key: `ecoute.github.template_whitelist`
- New config section: `ecoute.code.*`

Default changes

- `ecoute.screenshot.storage` now defaults to `none` instead of `disk`.
- `ecoute.code.enabled` now defaults to `false`.

What these defaults mean

- New installs will not persist screenshots unless you explicitly set `ECOUTE_SCREENSHOT_STORAGE=disk`.
- New installs will not send Blade/Livewire source snippets to the AI provider unless you explicitly set `ECOUTE_CODE_ENABLED=true`.

Template whitelist behaviour

- If `template_whitelist` is empty (the default), behaviour is unchanged: templates are permitted as before.
- If `template_whitelist` is configured with one or more filenames, only those filenames will be shown in the `templates()` endpoint and accepted by the preview/capture flows.
- Enforcement happens at request validation, provider fetch/listing, and job execution.

Operational behaviour changes

- Ecoute routes are only registered when the package is enabled for the current environment.
- Template listing now requires the same `ecoute-admin` authorization boundary as capture and preview.
- Deduplication is scoped to the submitting user.
- Queued processing now fails closed if the submitting user no longer exists or no longer passes the `ecoute-admin` gate.

Data retention changes

- Legacy raw `screenshot_data` is removed by migration. Ecoute no longer stores raw base64 screenshot blobs in the database.

Error handling changes

- Error messages persisted to `failure_reason` or surfaced from provider exceptions are redacted and truncated.
- Long token-like strings (Bearer tokens, `sk-` keys, and long hex/base64-like blobs) are replaced with `[REDACTED]` before logging or persistence.


