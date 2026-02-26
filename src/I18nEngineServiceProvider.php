<?php

declare(strict_types=1);

namespace ZPMLabs\I18nEngine;

use ZPMLabs\I18nEngine\Console\Commands\MakeTranslationTableCommand;
use ZPMLabs\I18nEngine\Observers\I18nEngineTableObserver;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class I18nEngineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/i18n-engine.php', 'i18n-engine');

        $this->app->singleton(I18nEngineContext::class, fn ($app) => new I18nEngineContext(
            application: $app,
            config: $app['config'],
        ));

        $this->commands([
            MakeTranslationTableCommand::class,
        ]);
    }

    public function boot(): void
    {
        $config = $this->app['config'];

        $this->publishes([
            __DIR__ . '/../config/i18n-engine.php' => $this->app->basePath('config/i18n-engine.php'),
        ], 'i18n-engine-config');

        if (!Builder::hasGlobalMacro('withTranslations')) {
            Builder::macro('withTranslations', function (?string $locale = null) {
                /** @var Builder $this */
                $ctx = Container::getInstance()->make(I18nEngineContext::class);

                return I18nEngineJoiner::apply(
                    builder: $this,
                    locale: $locale ?? $ctx->locale(),
                );
            });
        }

        if (!Builder::hasGlobalMacro('withTranslationsList')) {
            Builder::macro('withTranslationsList', function () {
                /** @var Builder $this */
                return $this->with('translations');
            });
        }

        Model::created(function (Model $model): void {
            $observer = Container::getInstance()->make(I18nEngineTableObserver::class);
            $observer->created($model);
        });

        Model::updated(function (Model $model): void {
            $observer = Container::getInstance()->make(I18nEngineTableObserver::class);
            $observer->updated($model);
        });

        if ($config->get('i18n-engine.default_locale') === null) {
            $config->set('i18n-engine.default_locale', $config->get('app.locale', 'en'));
        }
    }
}
