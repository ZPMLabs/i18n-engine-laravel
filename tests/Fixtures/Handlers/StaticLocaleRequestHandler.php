<?php

declare(strict_types=1);

namespace ZPMLabs\I18nEngine\Tests\Fixtures\Handlers;

use ZPMLabs\I18nEngine\Contracts\LocaleRequestHandler;

final class StaticLocaleRequestHandler implements LocaleRequestHandler
{
    public function canHandle(object $request): bool
    {
        if (!property_exists($request, 'attributes') || !is_object($request->attributes) || !method_exists($request->attributes, 'get')) {
            return false;
        }

        return $request->attributes->get('use_custom_handler', false) === true;
    }

    public function resolveLocale(object $request, string $queryParam, string $headerName): ?string
    {
        return 'fr';
    }
}