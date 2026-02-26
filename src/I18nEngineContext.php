<?php

declare(strict_types=1);

namespace ZPMLabs\I18nEngine;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;

final class I18nEngineContext
{
    private const FILAMENT_FACADE = 'Filament\\Facades\\Filament';
    private const LANGUAGE_SWITCH = 'BezhanSalleh\\LanguageSwitch\\LanguageSwitch';

    public function __construct(
        private readonly Application $application,
        private readonly ConfigRepository $config,
    ) {
    }

    public function defaultLocale(): string
    {
        return (string) $this->config->get('i18n-engine.default_locale', 'en');
    }

    public function locale($request = null): string
    {
        $request ??= $this->application->make('request');

        if (!is_object($request)) {
            return $this->defaultLocale();
        }

        $skipLocaleChangeForRoutes = (array) $this->config->get('i18n-engine.skip_locale_changes_for_routes', []);
        $skipLocaleChangeForRoutes = array_map(function (string $class): string {
            if (!class_exists($class) || !method_exists($class, 'getRouteName')) {
                return '';
            }

            return (string) $class::getRouteName();
        }, $skipLocaleChangeForRoutes);
        $skipLocaleChangeForRoutes = array_values(array_filter($skipLocaleChangeForRoutes, static fn (string $route): bool => $route !== ''));

        $currentRouteName = $request->route()?->getName();
        if ($currentRouteName && in_array($currentRouteName, $skipLocaleChangeForRoutes, true)) {
            return $this->defaultLocale();
        }

        $queryParam = (string) $this->config->get('i18n-engine.query_param', 'lang');
        $headerName = (string) $this->config->get('i18n-engine.header', 'Accept-Language');
        $normalize = (bool) $this->config->get('i18n-engine.normalize_locale', true);

        $candidate = '';

        $isFilament = $this->isFilamentRequest($request);
        $isApi = $this->isApiRequest($request);

        if ($isFilament) {
            $filamentLocale = $this->resolveFilamentLocale();
            $candidate = (string) ($filamentLocale ?: $request->query($queryParam, ''));
        } elseif ($isApi) {
            $candidate = (string) $request->query($queryParam, '');
            if ($candidate === '') {
                $candidate = (string) $request->header($headerName, '');
            }
        } else {
            $candidate = (string) $request->query($queryParam, '');
            if ($candidate === '') {
                $candidate = (string) $this->application->getLocale();
            }
        }

        $candidate = trim($candidate);
        if ($candidate === '') {
            return $this->defaultLocale();
        }

        if (! $normalize) {
            return $candidate;
        }

        $candidate = explode(',', $candidate)[0] ?? $candidate;
        $candidate = explode(';', $candidate)[0] ?? $candidate;

        if (str_contains($candidate, '-')) {
            $candidate = explode('-', $candidate)[0] ?? $candidate;
        }

        $mapped = $this->mapLocale($candidate);

        return $mapped ?: $this->defaultLocale();
    }

    private function mapLocale(string $candidate): ?string
    {
        $localeMap = (array) $this->config->get('i18n-engine.locale_map', []);
        if (isset($localeMap[$candidate]) && is_string($localeMap[$candidate])) {
            return $localeMap[$candidate];
        }

        $enumClass = (string) $this->config->get('i18n-engine.system_languages_enum', '');
        if ($enumClass === '' || !class_exists($enumClass) || !method_exists($enumClass, 'mapFromJson')) {
            return null;
        }

        try {
            $mapped = $enumClass::mapFromJson($candidate);

            if (is_string($mapped)) {
                return $mapped;
            }

            if (is_object($mapped) && isset($mapped->value) && is_string($mapped->value)) {
                return $mapped->value;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
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

    private function isFilamentRequest(object $request): bool
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

    private function isApiRequest(object $request): bool
    {
        $route = method_exists($request, 'route') ? $request->route() : null;
        $middlewares = is_object($route) && method_exists($route, 'middleware') ? ($route->middleware() ?? []) : [];

        if (! empty($middlewares)) {
            foreach ($middlewares as $middleware) {
                if ($middleware === 'api') {
                    return true;
                }
            }
        }

        if (method_exists($request, 'is') && $request->is('api/*')) {
            return true;
        }

        if (method_exists($request, 'expectsJson') && $request->expectsJson()) {
            return true;
        }

        return false;
    }
}