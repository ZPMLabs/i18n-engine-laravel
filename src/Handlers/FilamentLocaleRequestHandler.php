<?php

declare(strict_types=1);

namespace ZPMLabs\I18nEngine\Handlers;

use ZPMLabs\I18nEngine\Contracts\LocaleRequestHandler;

final class FilamentLocaleRequestHandler implements LocaleRequestHandler
{
    private const FILAMENT_FACADE = 'Filament\\Facades\\Filament';
    private const LANGUAGE_SWITCH = 'BezhanSalleh\\LanguageSwitch\\LanguageSwitch';

    public function canHandle(object $request): bool
    {
        try {
            if (class_exists(self::FILAMENT_FACADE) && method_exists(self::FILAMENT_FACADE, 'hasCurrentPanel') && self::FILAMENT_FACADE::hasCurrentPanel()) {
                return true;
            }
        } catch (\Throwable) {
        }

        $route = method_exists($request, 'route') ? $request->route() : null;
        $middlewares = is_object($route) && method_exists($route, 'middleware') ? ($route->middleware() ?? []) : [];

        foreach ($middlewares as $middleware) {
            if (is_string($middleware) && str_contains($middleware, 'filament')) {
                return true;
            }
        }

        return false;
    }

    public function resolveLocale(object $request, string $queryParam, string $headerName): ?string
    {
        if (! $this->canHandle($request)) {
            return null;
        }

        $preferred = $this->resolveFilamentLocale();

        if ($preferred !== '') {
            return $preferred;
        }

        return (string) (method_exists($request, 'query') ? $request->query($queryParam, '') : '');
    }

    private function resolveFilamentLocale(): string
    {
        if (!class_exists(self::LANGUAGE_SWITCH) || !method_exists(self::LANGUAGE_SWITCH, 'make')) {
            return '';
        }

        try {
            $instance = self::LANGUAGE_SWITCH::make();

            if (is_object($instance) && method_exists($instance, 'getPreferredLocale')) {
                return (string) $instance->getPreferredLocale();
            }
        } catch (\Throwable) {
            return '';
        }

        return '';
    }
}