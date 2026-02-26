<?php

declare(strict_types=1);

namespace ZPMLabs\I18nEngine\Tests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Console\Kernel;
use ZPMLabs\I18nEngine\Handlers\FilamentLocaleRequestHandler;
use ZPMLabs\I18nEngine\I18nEngineContext;

final class I18nEngineServiceProviderTest extends TestCase
{
    public function test_context_is_registered_as_singleton(): void
    {
        $first = $this->app->make(I18nEngineContext::class);
        $second = $this->app->make(I18nEngineContext::class);

        $this->assertInstanceOf(I18nEngineContext::class, $first);
        $this->assertSame($first, $second);
    }

    public function test_builder_macros_are_registered(): void
    {
        $this->assertTrue(Builder::hasGlobalMacro('withTranslations'));
        $this->assertTrue(Builder::hasGlobalMacro('withTranslationsList'));
    }

    public function test_translation_tables_config_is_merged(): void
    {
        $this->assertSame('lang', $this->app['config']->get('i18n-engine.query_param'));
        $this->assertSame('_translations', $this->app['config']->get('i18n-engine.table_suffix'));
        $this->assertSame(FilamentLocaleRequestHandler::class, $this->app['config']->get('i18n-engine.request_locale_handler'));
    }

    public function test_command_is_registered(): void
    {
        $commands = $this->app->make(Kernel::class)->all();

        $this->assertArrayHasKey('i18n-engine:make', $commands);
    }
}
