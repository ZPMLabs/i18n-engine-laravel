<?php

declare(strict_types=1);

namespace ZPMLabs\I18nEngine\Scopes;

use ZPMLabs\I18nEngine\Contracts\HasTranslationTable;
use ZPMLabs\I18nEngine\I18nEngineContext;
use ZPMLabs\I18nEngine\I18nEngineJoiner;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class I18nEngineScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (!$model instanceof HasTranslationTable) {
            return;
        }

        $ctx = Container::getInstance()->make(I18nEngineContext::class);

        I18nEngineJoiner::apply(
            builder: $builder,
            locale: $ctx->locale()
        );
    }
}
