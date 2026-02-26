<?php

declare(strict_types=1);

namespace ZPMLabs\I18nEngine;

use ZPMLabs\I18nEngine\Contracts\LocaleRequestHandler;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;

final class I18nEngineContext
{
    private const REQUEST_LOCALE_CACHE_KEY = '__i18n_engine_locale';
    private const FILAMENT_LOCALE_QUERY_KEY = 'locale';
    private const FILAMENT_LOCALE_COOKIE_KEY = 'filament_language_switch_locale';

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
        $request ??= $this->application->make(Request::class);

        if (!is_object($request)) {
            return $this->defaultLocale();
        }

        $cachedLocale = $this->getCachedLocaleFromRequest($request);
        if ($cachedLocale !== null) {
            return $this->applyLocale($cachedLocale);
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
            $resolved = $this->defaultLocale();

            $this->cacheLocaleOnRequest($request, $resolved);

            return $this->applyLocale($resolved);
        }

        $queryParam = (string) $this->config->get('i18n-engine.query_param', 'lang');
        $headerName = (string) $this->config->get('i18n-engine.header', 'Accept-Language');
        $normalize = (bool) $this->config->get('i18n-engine.normalize_locale', true);

        $candidate = '';
        $isApi = $this->isApiRequest($request);
        $shouldCache = false;

        $customCandidate = $this->resolveCustomRequestLocaleCandidate($request, $queryParam, $headerName);

        if ($customCandidate !== null) {
            $candidate = (string) $customCandidate;
            $shouldCache = true;
        } elseif ($isApi) {
            $candidate = (string) $request->query($queryParam, '');
            $shouldCache = $candidate !== '';

            if ($candidate === '') {
                $candidate = (string) $request->header($headerName, '');
                $shouldCache = $candidate !== '';
            }
        } else {
            $candidate = (string) $request->query($queryParam, '');

            $shouldCache = $candidate !== '';

            if ($candidate === '') {
                $candidate = (string) $this->application->getLocale();
            }
        }

        $candidate = trim($candidate);
        if ($candidate === '') {
            $resolved = $this->defaultLocale();

            if ($shouldCache) {
                $this->cacheLocaleOnRequest($request, $resolved);
            }

            return $this->applyLocale($resolved);
        }

        if (! $normalize) {
            if ($shouldCache) {
                $this->cacheLocaleOnRequest($request, $candidate);
            }

            return $this->applyLocale($candidate);
        }

        $candidate = explode(',', $candidate)[0] ?? $candidate;
        $candidate = explode(';', $candidate)[0] ?? $candidate;

        if (str_contains($candidate, '-')) {
            $candidate = explode('-', $candidate)[0] ?? $candidate;
        }

        $resolved = $candidate ?: $this->defaultLocale();

        if ($shouldCache) {
            $this->cacheLocaleOnRequest($request, $resolved);
        }

        return $this->applyLocale($resolved);
    }

    private function getCachedLocaleFromRequest(object $request): ?string
    {
        if (! isset($request->attributes) || ! is_object($request->attributes)) {
            return null;
        }

        if (! method_exists($request->attributes, 'has') || ! method_exists($request->attributes, 'get')) {
            return null;
        }

        if (! $request->attributes->has(self::REQUEST_LOCALE_CACHE_KEY)) {
            return null;
        }

        $cached = $request->attributes->get(self::REQUEST_LOCALE_CACHE_KEY);

        return is_string($cached) && $cached !== '' ? $cached : null;
    }

    private function cacheLocaleOnRequest(object $request, string $locale): void
    {
        if (! isset($request->attributes) || ! is_object($request->attributes)) {
            return;
        }

        if (! method_exists($request->attributes, 'set')) {
            return;
        }

        $request->attributes->set(self::REQUEST_LOCALE_CACHE_KEY, $locale);
    }

    private function applyLocale(string $locale): string
    {
        $this->application->setLocale($locale);

        return $locale;
    }

    private function resolveCustomRequestLocaleCandidate(object $request, string $queryParam, string $headerName): ?string
    {
        $handlerClass = (string) $this->config->get('i18n-engine.request_locale_handler', '');

        if ($handlerClass === '' || !class_exists($handlerClass) || !is_subclass_of($handlerClass, LocaleRequestHandler::class)) {
            return null;
        }

        try {
            $handler = $this->application->make($handlerClass);
        } catch (\Throwable) {
            return null;
        }

        if (! $handler instanceof LocaleRequestHandler) {
            return null;
        }

        try {
            return $handler->resolveLocale($request, $queryParam, $headerName);
        } catch (\Throwable) {
            return null;
        }
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