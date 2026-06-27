<?php

declare(strict_types=1);

namespace MikeHins\Ecoute;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use MikeHins\Ecoute\Contracts\AIProviderInterface;
use MikeHins\Ecoute\Services\GitHubProvider;
use MikeHins\Ecoute\Services\Providers\AnthropicProvider;
use MikeHins\Ecoute\Services\Providers\OpenAIProvider;

final class EcouteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ecoute.php', 'ecoute');

        $this->app->singleton(AIProviderInterface::class, function () {
            return match (config('ecoute.ai.provider')) {
                'anthropic' => new AnthropicProvider(config('ecoute.ai.anthropic')),
                default => new OpenAIProvider(config('ecoute.ai.openai')),
            };
        });

        $this->app->singleton(GitHubProvider::class, function () {
            return new GitHubProvider(config('ecoute.github'));
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\PublishTemplatesCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        if ($this->routesShouldBeRegistered()) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'ecoute');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Blade::directive('ecoute', fn () => "<?php echo \$__env->make('ecoute::overlay', [], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>");

        RateLimiter::for('ecoute', function ($request) {
            return Limit::perMinutes(
                (int) config('ecoute.rate_limit.decay_minutes', 1),
                (int) config('ecoute.rate_limit.attempts', 10)
            )
                ->by($request->user()?->id ?: $request->ip());
        });

        Gate::define('ecoute-admin', function ($user) {
            $customGate = config('ecoute.gate');
            if (is_callable($customGate)) {
                return $customGate($user);
            }

            return false;
        });

        $this->publishes([
            __DIR__.'/../config/ecoute.php' => config_path('ecoute.php'),
        ], 'ecoute-config');

        $this->publishes([
            __DIR__.'/../dist/overlay.js' => public_path('vendor/ecoute/overlay.js'),
        ], 'ecoute-assets');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'ecoute-migrations');

        $this->publishes([
            __DIR__.'/../templates' => base_path('.github/ISSUE_TEMPLATE'),
        ], 'ecoute-templates');

        $this->publishes([
            __DIR__.'/../.agents/skills/ecoute-management' => base_path('.agents/skills/ecoute-management'),
            __DIR__.'/../.agents/skills/ecoute-capture' => base_path('.agents/skills/ecoute-capture'),
            __DIR__.'/../.agents/skills/ecoute-extension-setup' => base_path('.agents/skills/ecoute-extension-setup'),
        ], 'ecoute-skills');

        $this->publishes([
            __DIR__.'/../config/ecoute.php' => config_path('ecoute.php'),
            __DIR__.'/../dist/overlay.js' => public_path('vendor/ecoute/overlay.js'),
        ], 'ecoute');
    }

    private function routesShouldBeRegistered(): bool
    {
        return (bool) config('ecoute.enabled')
            && in_array(app()->environment(), (array) config('ecoute.environments', []), true);
    }
}
