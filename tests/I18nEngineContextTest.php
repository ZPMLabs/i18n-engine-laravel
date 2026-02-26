<?php

declare(strict_types=1);

namespace ZPMLabs\I18nEngine\Tests;

use Illuminate\Http\Request;
use ZPMLabs\I18nEngine\I18nEngineContext;
use ZPMLabs\I18nEngine\Tests\Fixtures\Handlers\InvalidLocaleRequestHandler;
use ZPMLabs\I18nEngine\Tests\Fixtures\Handlers\StaticLocaleRequestHandler;

final class I18nEngineContextTest extends TestCase
{
    public function test_configured_request_locale_handler_is_used_when_it_can_handle_request(): void
    {
        $this->app['config']->set('i18n-engine.request_locale_handler', StaticLocaleRequestHandler::class);
        $this->app['config']->set('i18n-engine.locale_map', [
            'en' => 'en',
            'fr' => 'fr',
            'de' => 'de',
        ]);

        $request = Request::create('/admin', 'GET', ['lang' => 'de']);
        $request->attributes->set('use_custom_handler', true);

        $context = $this->app->make(I18nEngineContext::class);

        $this->assertSame('fr', $context->locale($request));
    }

    public function test_invalid_request_locale_handler_class_is_ignored(): void
    {
        $this->app['config']->set('i18n-engine.request_locale_handler', InvalidLocaleRequestHandler::class);
        $this->app['config']->set('i18n-engine.locale_map', [
            'en' => 'en',
            'fr' => 'fr',
            'de' => 'de',
        ]);

        $request = Request::create('/articles', 'GET', ['lang' => 'de']);

        $context = $this->app->make(I18nEngineContext::class);

        $this->assertSame('de', $context->locale($request));
    }

    public function test_handler_that_cannot_handle_request_falls_back_to_default_resolution_flow(): void
    {
        $this->app['config']->set('i18n-engine.request_locale_handler', StaticLocaleRequestHandler::class);
        $this->app['config']->set('i18n-engine.locale_map', [
            'en' => 'en',
            'fr' => 'fr',
            'de' => 'de',
        ]);

        $request = Request::create('/articles', 'GET', ['lang' => 'de']);
        $request->attributes->set('use_custom_handler', false);

        $context = $this->app->make(I18nEngineContext::class);

        $this->assertSame('de', $context->locale($request));
    }
}