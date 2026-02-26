<?php

declare(strict_types=1);

namespace ZPMLabs\I18nEngine\Traits;

use ZPMLabs\I18nEngine\Scopes\I18nEngineScope;
use ZPMLabs\I18nEngine\I18nEngineRowModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Config;

trait InteractsWithTranslationTable
{
    protected static function bootInteractsWithTranslationTable(): void
    {
        static::addGlobalScope(new I18nEngineScope());
    }

    public function translationTableName(): string
    {
        return $this->getTable() . $this->translationTableSuffix();
    }

    public function translationForeignKey(): string
    {
        return (string) Config::get('i18n-engine.foreign_key', 'foreign_id');
    }

    public function translationLocaleKey(): string
    {
        return (string) Config::get('i18n-engine.locale_key', 'language');
    }

    public function translationTableSuffix(): string
    {
        return (string) Config::get('i18n-engine.table_suffix', '_translations');
    }

    public function getQualifiedKeyName(): string
    {
        return $this->getTable() . '.' . $this->getKeyName();
    }

    public function qualifyColumn($column): string
    {
        if (str_contains($column, '.')) {
            return $column;
        }

        return $this->getTable() . '.' . $column;
    }

    /**
     * Has-many relation to translation rows (generic model, dynamic table).
     */
    public function translations(): HasMany
    {
        $related = I18nEngineRowModel::class;

        $instance = $this->newRelatedInstance($related);

        $table = $this->translationTableName();
        $instance->setTable($table);

        // If you want same connection as parent model:
        $instance->setConnection($this->getConnectionName());

        $query = $instance->newQuery();

        $foreignKey = $table . '.' . $this->translationForeignKey();
        $localKey = $this->getKeyName();

        return $this->newHasMany($query, $this, $foreignKey, $localKey);
    }

    public function baseColumns(): array
    {
        $key = $this->getKeyName(); // usually "id"

        // Base columns = PK + fillable without translated + timestamps (if used) + deleted_at (if soft deletes)
        $cols = array_values(array_unique(array_merge(
            [$key],
            array_diff($this->getFillable(), $this->translatedColumns()),
            $this->usesTimestamps()
                ? [$this->getCreatedAtColumn(), $this->getUpdatedAtColumn()]
                : [],
            method_exists($this, 'getDeletedAtColumn') ? [$this->getDeletedAtColumn()] : [],
        )));

        // Remove null/empty values just in case
        return array_values(array_filter($cols, fn ($c) => is_string($c) && $c !== ''));
    }
}
