<?php

declare(strict_types=1);

namespace ZPMLabs\I18nEngine\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use ZPMLabs\I18nEngine\I18nEngineServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            I18nEngineServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.locale', 'en');
        $app['config']->set('app.fallback_locale', 'en');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $app['config']->set('i18n-engine.foreign_key', 'foreign_id');
        $app['config']->set('i18n-engine.locale_key', 'language');
        $app['config']->set('i18n-engine.table_suffix', '_translations');
    }
}
