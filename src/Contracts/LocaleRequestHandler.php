<?php

declare(strict_types=1);

namespace ZPMLabs\I18nEngine\Contracts;

interface LocaleRequestHandler
{
    public function canHandle(object $request): bool;

    public function resolveLocale(object $request, string $queryParam, string $headerName): ?string;
}