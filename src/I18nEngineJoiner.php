<?php

declare(strict_types=1);

namespace ZPMLabs\I18nEngine;

use ZPMLabs\I18nEngine\Contracts\HasTranslationTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

final class I18nEngineJoiner
{
    public static function apply(Builder $builder, string $locale): Builder
    {
        $model = $builder->getModel();

        if (!$model instanceof HasTranslationTable || !$model instanceof Model) {
            return $builder;
        }

        $locale = trim($locale);
        if ($locale === '') {
            return $builder;
        }

        $baseTable = $model->getTable();
        if (str_starts_with($baseTable, 'laravel_reserved_')) {
            return $builder;
        }

        $trTable = $model->translationTableName();
        $defaultLocale = (string) Config::get('app.fallback_locale', 'en');
        $trTableDefault = $trTable . '_default';
        $needsDefaultFallback = $locale !== $defaultLocale;

        if (!self::isJoined($builder, $trTable)) {
            $builder->leftJoin($trTable, function (JoinClause $join) use ($model, $baseTable, $trTable, $locale): void {
                $join->on(
                    $trTable . '.' . $model->translationForeignKey(),
                    '=',
                    $baseTable . '.' . $model->getKeyName(),
                )->where(
                    $trTable . '.' . $model->translationLocaleKey(),
                    '=',
                    $locale,
                );
            });
        }

        if ($needsDefaultFallback && !self::isJoined($builder, $trTableDefault)) {
            $builder->leftJoin($trTable . ' as ' . $trTableDefault, function (JoinClause $join) use ($model, $baseTable, $trTableDefault, $defaultLocale): void {
                $join->on(
                    $trTableDefault . '.' . $model->translationForeignKey(),
                    '=',
                    $baseTable . '.' . $model->getKeyName(),
                )->where(
                    $trTableDefault . '.' . $model->translationLocaleKey(),
                    '=',
                    $defaultLocale,
                );
            });
        }

        $query = $builder->getQuery();
        $hasSelect = !empty($query->columns);

        if (!$hasSelect) {
            $builder->select(array_map(
                static fn (string $c): string => $baseTable . '.' . $c,
                $model->baseColumns(),
            ));
        } else {
            $builder->addSelect(array_map(
                static fn (string $c): string => $baseTable . '.' . $c,
                $model->baseColumns(),
            ));
        }

        foreach ($model->translatedColumns() as $c) {
            $alias = self::wrapAlias($c);

            if ($needsDefaultFallback) {
                $builder->addSelect(
                    $builder->getQuery()->raw(
                        "COALESCE(
                            NULLIF(CAST({$trTable}.{$c} AS TEXT), ''),
                            NULLIF(CAST({$trTableDefault}.{$c} AS TEXT), ''),
                            'Translation Missing For Default Lang {$defaultLocale}'
                        ) AS {$alias}"
                    )
                );
            } else {
                $builder->addSelect(
                    $builder->getQuery()->raw(
                        "COALESCE(
                            NULLIF(CAST({$trTable}.{$c} AS TEXT), ''),
                            'Translation Missing For Default Lang {$defaultLocale}'
                        ) AS {$alias}"
                    )
                );
            }
        }

        return $builder;
    }

    private static function isJoined(Builder $builder, string $table): bool
    {
        $joins = $builder->getQuery()->joins ?? [];

        foreach ($joins as $join) {
            $joinedTable = strtolower(trim((string) ($join->table ?? '')));
            $targetTable = strtolower(trim($table));

            if ($joinedTable === $targetTable) {
                return true;
            }

            if (str_ends_with($joinedTable, ' as ' . $targetTable)) {
                return true;
            }
        }

        return false;
    }

    private static function wrapAlias(string $alias): string
    {
        return Str::replace('"', '', $alias);
    }
}