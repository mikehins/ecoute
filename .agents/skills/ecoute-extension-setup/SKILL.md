---
name: ecoute-extension-setup
description: Installs and configures the Ecoute Chrome extension for capturing DOM context from any page. Handles loading the unpacked extension, configuring the backend URL, and generating API tokens.
---

# Ecoute Chrome Extension Setup

The Ecoute Chrome extension lets admins activate DOM capture on any page — not just pages where the `@ecoute` Blade directive is loaded. It injects a capture overlay via a toolbar button and sends captures to a configurable Laravel backend using Bearer token auth.

## When to Use This Skill

- The user wants to capture bugs from pages outside their Laravel app (staging, production mirrors, third-party tools)
- The `@ecoute` Blade directive can't be added to every page
- The user wants screen recording alongside DOM capture (the extension enables `chrome.tabCapture`)

## Prerequisites

- A Laravel app with `mikehins/ecoute` installed and configured
- The Ecoute backend endpoints accessible from the user's browser (`/ecoute/capture`, `/ecoute/templates`)
- Chrome or any Chromium-based browser

## Installation

### 1. Publish the extension files

The extension lives inside the Ecoute package. Publish assets first:

```bash
php artisan vendor:publish --tag=ecoute-assets
```

Then locate the extension at `vendor/mikehins/ecoute/extensions/chrome/`.

If you need a standalone copy:
```bash
cp -r vendor/mikehins/ecoute/extensions/chrome ~/ecoute-extension
```

### 2. Load the unpacked extension in Chrome

1. Open `chrome://extensions`
2. Enable **Developer mode** (toggle top-right)
3. Click **Load unpacked**
4. Select the `extensions/chrome/` directory
5. The Ecoute icon (indigo circle) should appear in the toolbar

### 3. Generate an API token

The extension authenticates with a Laravel Sanctum token. Create one for an admin user:

```bash
php artisan tinker
```

```php
$user = \App\Models\User::where('email', 'admin@example.com')->first();
$token = $user->createToken('ecoute-extension')->plainTextToken;
echo $token;
```

Copy the output — this is your API token.

### 4. Configure the extension

1. Click the Ecoute toolbar icon
2. Enter the **Backend URL** — your app's base URL (e.g. `https://clubsens.fliip.localhost:4443`)
3. Paste the **API Token** from step 3
4. Click **Save & Close**

### 5. Update the Gate (required)

The extension uses Bearer token auth, not session cookies. Update `config/ecoute.php` to recognize token-authenticated users:

```php
'gate' => function ($user) {
    // Allow extension users (token-based auth)
    if ($user->tokenCan('ecoute-extension')) {
        return true;
    }
    // Fall back to your existing logic
    return $user->isAdmin();
},
```

### 6. Enable Ecoute for the relevant environments

```env
ECOUTE_ENABLED=true
ECOUTE_ENVIRONMENTS=local,staging,production
```

The extension sends requests to the configured backend URL regardless of the current page's origin, so the backend must be enabled in the environment matching that URL.

## Usage

1. Navigate to any page you want to report on
2. Click the Ecoute toolbar button (or press its keyboard shortcut if set)
3. The page enters selection mode — cursor becomes a crosshair
4. Click the problematic element
5. A panel slides in with a textarea — describe the bug
6. Optionally click the **Record** button to capture a screen recording
7. Click **Send**

## Troubleshooting

| Symptom | Fix |
|---|---|
| Toolbar icon does nothing | Check popup settings — backend URL and token must be saved |
| "Network error" on submit | Ensure the backend URL is reachable from the current tab |
| 401 Unauthorized | Token may have expired or user lacks the `ecoute-extension` ability |
| 403 Forbidden | The Gate in `config/ecoute.php` is not passing for token-authenticated users |
| No overlay appears | The page may have a strict CSP that blocks inline scripts |

## Privacy

The extension captures the same data as the in-app overlay:
- Element HTML, CSS selector, data attributes, nearby text
- An optional page screenshot (password fields masked)
- Console logs and network metadata only if `ECOUTE_DIAGNOSTICS_ENABLED=true`
- Screen recordings only if `ECOUTE_RECORDING_ENABLED=true`

All PII is redacted before sending to any AI provider. The extension itself does not collect, store, or transmit data except to the configured backend URL.
