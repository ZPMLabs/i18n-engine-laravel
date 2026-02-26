<?php

declare(strict_types=1);

namespace ZPMLabs\I18nEngine\Handlers;

use ZPMLabs\I18nEngine\Contracts\LocaleRequestHandler;

final class FilamentLocaleRequestHandler implements LocaleRequestHandler
{
    private const LANGUAGE_SWITCH = 'BezhanSalleh\\LanguageSwitch\\LanguageSwitch';
    private const LOCALE_COOKIE = 'filament_language_switch_locale';
    private const LOCALE_QUERY_KEY = 'locale';

    public function canHandle(object $request): bool
    {
        return class_exists(self::LANGUAGE_SWITCH) && method_exists(self::LANGUAGE_SWITCH, 'make');
    }

    public function resolveLocale(object $request, string $queryParam, string $headerName): ?string
    {
        if (! $this->canHandle($request)) {
            return null;
        }

        $switch = $this->resolveLanguageSwitch();

        $locale = $this->sessionLocale($request)
            ?? $this->requestLocale($request)
            ?? $this->requestCookieLocale($request)
            ?? $this->userPreferredLocale($switch);

        $locale = $this->sanitizeLocale($locale);
        if ($locale === '') {
            return null;
        }

        $allowedLocales = $this->allowedLocales($switch);
        if ($allowedLocales !== [] && !in_array($locale, $allowedLocales, true)) {
            return null;
        }

        return $locale;
    }

    private function resolveLanguageSwitch(): object|null
    {
        if (!class_exists(self::LANGUAGE_SWITCH) || !method_exists(self::LANGUAGE_SWITCH, 'make')) {
            return null;
        }

        try {
            $instance = self::LANGUAGE_SWITCH::make();

            return is_object($instance) ? $instance : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function sessionLocale(object $request): ?string
    {
        if (!method_exists($request, 'session')) {
            return null;
        }

        try {
            $session = $request->session();

            if (!is_object($session) || !method_exists($session, 'get')) {
                return null;
            }

            $value = $session->get(self::LOCALE_QUERY_KEY);

            $locale = $this->sanitizeLocale($value);

            return $locale !== '' ? $locale : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function requestLocale(object $request): ?string
    {
        if (!method_exists($request, 'get')) {
            return null;
        }

        try {
            $value = $request->get(self::LOCALE_QUERY_KEY);

            $locale = $this->sanitizeLocale($value);

            return $locale !== '' ? $locale : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function requestCookieLocale(object $request): ?string
    {
        if (!method_exists($request, 'cookie')) {
            return null;
        }

        try {
            $value = $request->cookie(self::LOCALE_COOKIE);

            $locale = $this->sanitizeLocale($value);

            return $locale !== '' ? $locale : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function userPreferredLocale(?object $switch): ?string
    {
        if (!is_object($switch) || !method_exists($switch, 'getUserPreferredLocale')) {
            return null;
        }

        try {
            $value = $switch->getUserPreferredLocale();

            $locale = $this->sanitizeLocale($value);

            return $locale !== '' ? $locale : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function allowedLocales(?object $switch): array
    {
        if (!is_object($switch) || !method_exists($switch, 'getLocales')) {
            return [];
        }

        try {
            $locales = $switch->getLocales();

            if (!is_array($locales)) {
                return [];
            }

            return array_values(array_filter($locales, static fn ($locale): bool => is_string($locale) && trim($locale) !== ''));
        } catch (\Throwable) {
            return [];
        }
    }

    private function sanitizeLocale(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $locale = trim($value);
        if ($locale === '') {
            return '';
        }

        $locale = explode(',', $locale)[0] ?? $locale;
        $locale = explode(';', $locale)[0] ?? $locale;
        $locale = trim($locale);

        if ($locale === '') {
            return '';
        }

        if (!preg_match('/^[A-Za-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/', $locale)) {
            return '';
        }

        return $locale;
    }
}