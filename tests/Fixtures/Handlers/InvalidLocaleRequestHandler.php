<?php

declare(strict_types=1);

namespace ZPMLabs\I18nEngine\Tests\Fixtures\Handlers;

final class InvalidLocaleRequestHandler
{
    public function canHandle(object $request): bool
    {
        return true;
    }

    public function resolveLocale(object $request, string $queryParam, string $headerName): ?string
    {
        return 'fr';
    }
}