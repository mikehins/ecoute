# Changelog

## Unreleased

- Security: only register Ecoute routes when the package is enabled for the current environment.
- Security: require the `ecoute-admin` gate for template listing as well as preview/capture flows.
- Security: evaluate queued authorization for the captured user explicitly and fail closed when that user no longer exists or no longer has access.
- Security: redact and truncate persisted/provider error messages before logging or storing them.
- Security: stop persisting raw `screenshot_data` blobs in the database and add a migration to remove the legacy column.
- Changed: screenshot storage now defaults to `none` instead of `disk`.
- Changed: source-code enrichment now defaults to disabled behind `ecoute.code.enabled=false`.
- Changed: deduplication is now scoped to the submitting user.
- Changed: minimum supported PHP version is now 8.4.
- Added: configurable GitHub template whitelist (`ecoute.github.template_whitelist`) with validation/enforcement at request time, provider listing/fetching, and job runtime.
- Added: `ecoute.queue.name` for dispatching Ecoute jobs onto a named queue.
- Added: `ecoute.code.*` configuration for opt-in source-code enrichment limits.
- Changed: package metadata now declares its Symfony YAML runtime dependency and includes a package license.
- Changed: package-local lint/test launchers now work reliably in both standalone and monorepo layouts.

