---
name: ecoute-management
description: Autonomously installs, configures, and manages the 'mikehins/ecoute' package. Captures DOM context and transforms it into structured GitHub issues via AI. Handles dependency installation, asset publishing, migrations, and validation.
---

# Ecoute Management

Ecoute is a Laravel package that allows you to capture DOM context and transform it into structured issues via AI. This skill provides instructions for agents to autonomously install and configure the package.

## Installation and Configuration

Follow these steps to ensure a successful installation:

### 1. Discovery
Before installation, verify that the environment is a valid Laravel project:
- Check for the existence of `./artisan`.
- Check `composer.json` for `laravel/framework`.

### 2. Dependency Management
Install the package using Composer.
> [!IMPORTANT]
> **NEVER** run `composer update`. Only use `composer require`.

```bash
composer require mikehins/ecoute
```

### 3. Bootstrapping
Once installed, the package needs to be bootstrapped:

- **Publish Configuration & Assets**:
  ```bash
  php artisan vendor:publish --tag=ecoute
  ```

- **Publish GitHub Issue Templates**:
  ```bash
  php artisan ecoute:publish-templates
  ```

- **Publish AI Agent Skills**:
  ```bash
  php artisan vendor:publish --tag=ecoute-skills
  ```

- **Run Migrations**:
  ```bash
  php artisan migrate
  ```

### 4. Validation
Verify the installation was successful:
- Confirm `config/ecoute.php` exists.
- Run `php artisan list` and ensure `ecoute:publish-templates` is listed.

## Common Tasks

### Updating Assets
If the package assets need updating:
```bash
php artisan vendor:publish --tag=ecoute-assets --force
```

### Customizing Issue Templates
Issue templates are published to `.github/ISSUE_TEMPLATE`. You can modify these files to change how AI-generated issues are formatted.

## Error Handling

- **Version Mismatch**: If `composer require` fails due to version conflicts, check the PHP version (`php -v`) and Laravel version (`php artisan --version`). Ecoute requires PHP 8.1+ and Laravel 10.0+.
- **Missing Commands**: If `ecoute:` commands are missing from Artisan, ensure the `MikeHins\Ecoute\EcouteServiceProvider` is discovered (check `bootstrap/cache/packages.php` or run `php artisan package:discover`).
