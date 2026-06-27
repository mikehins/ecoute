# Ecoute Chrome Extension

Capture DOM context from any page and file AI-enriched GitHub issues via the Ecoute Laravel backend.

## Installation (Developer Mode)

1. Open `chrome://extensions`
2. Enable "Developer mode"
3. Click "Load unpacked" and select the `extensions/chrome/` directory

## Setup

- Click the extension icon in the toolbar
- Enter your **Backend URL** (e.g. `https://your-app.com`)
- Enter your **API Token** (a Laravel Sanctum token with `ecoute-admin` privilege)
- Click **Save & Close**

## Usage

1. Navigate to any page you want to report on
2. Click the Ecoute toolbar icon
3. Click the problematic element
4. Describe the issue
5. Click **Send**

## Authentication

The extension uses Bearer token authentication. Create a Sanctum token in your Laravel app:

```bash
php artisan tinker
>>> $user = User::where('email', 'admin@example.com')->first();
>>> $token = $user->createToken('ecoute-extension')->plainTextToken;
>>> echo $token;
```

Then add a custom Gate in `config/ecoute.php`:

```php
'gate' => fn ($user) => $user->tokenCan('ecoute-extension') || $user->isAdmin(),
```
