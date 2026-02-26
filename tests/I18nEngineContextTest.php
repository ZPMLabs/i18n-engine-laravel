<?php

declare(strict_types=1);

namespace ZPMLabs\I18nEngine\Tests;

use Illuminate\Http\Request;
use ZPMLabs\I18nEngine\I18nEngineContext;
use ZPMLabs\I18nEngine\Tests\Fixtures\Handlers\InvalidLocaleRequestHandler;
use ZPMLabs\I18nEngine\Tests\Fixtures\Handlers\StaticLocaleRequestHandler;

final class I18nEngineContextTest extends TestCase
{
    public function test_configured_request_locale_handler_result_is_used(): void
    {
        $this->app['config']->set('i18n-engine.request_locale_handler', StaticLocaleRequestHandler::class);

        $request = Request::create('/admin', 'GET', ['lang' => 'de']);
        $request->attributes->set('use_custom_handler', true);

        $context = $this->app->make(I18nEngineContext::class);

        $this->assertSame('fr', $context->locale($request));
    }

    public function test_invalid_request_locale_handler_class_is_ignored(): void
    {
        $this->app['config']->set('i18n-engine.request_locale_handler', InvalidLocaleRequestHandler::class);

        $request = Request::create('/articles', 'GET', ['lang' => 'de']);

        $context = $this->app->make(I18nEngineContext::class);

        $this->assertSame('de', $context->locale($request));
    }

    public function test_custom_handler_result_is_used_even_when_can_handle_returns_false(): void
    {
        $this->app['config']->set('i18n-engine.request_locale_handler', StaticLocaleRequestHandler::class);

        $request = Request::create('/articles', 'GET', ['lang' => 'de']);
        $request->attributes->set('use_custom_handler', false);

        $context = $this->app->make(I18nEngineContext::class);

        $this->assertSame('fr', $context->locale($request));
    }
}