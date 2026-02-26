<?php

declare(strict_types=1);

namespace ZPMLabs\I18nEngine\Observers;

use ZPMLabs\I18nEngine\Contracts\HasTranslationTable as HasTranslationTableContract;
use ZPMLabs\I18nEngine\I18nEngineContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class I18nEngineTableObserver
{
    public function __construct(
        private readonly I18nEngineContext $translationContext,
    ) {
    }

    public function created(Model $model): void
    {
        if (!$model instanceof HasTranslationTableContract) {
            return;
        }

        $this->upsertForLocale($model, $this->translationContext->locale());
    }

    public function updated(Model $model): void
    {
        if (!$model instanceof HasTranslationTableContract) {
            return;
        }

        $this->upsertForLocale($model, $this->translationContext->locale());
    }

    /**
     * @param HasTranslationTableContract&Model $model
     */
    private function upsertForLocale(Model $model, string $locale): void
    {
        $locale = trim($locale);
        if ($locale === '') {
            return;
        }

        $conn = $model->getConnectionName();
        $schema = Schema::connection($conn);

        $translationTable = $model->translationTableName();
        if (!$schema->hasTable($translationTable)) {
            return;
        }

        $fk = $model->translationForeignKey();
        $langKey = $model->translationLocaleKey();

        $translationCols = $schema->getColumnListing($translationTable);

        $payload = [];
        $attributes = $model->getAttributes();

        foreach ($translationCols as $col) {
            if (in_array($col, ['id', $fk, $langKey, 'created_at', 'updated_at', 'deleted_at'], true)) {
                continue;
            }

            if (!array_key_exists($col, $attributes)) {
                continue;
            }

            $payload[$col] = $model->getAttribute($col);
        }

        foreach ($payload as $col => $value) {
            if (is_array($value) || is_object($value)) {
                $payload[$col] = json_encode($value);
            }
        }

        if ($payload === []) {
            return;
        }

        $now = Carbon::now();

        $where = [$fk => $model->getKey(), $langKey => $locale];

        $query = DB::connection($conn)->table($translationTable)->where($where);

        if (!$query->exists()) {
            DB::connection($conn)->table($translationTable)->insert(array_merge($payload, $where, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
            return;
        }

        DB::connection($conn)->table($translationTable)->where($where)->update(array_merge($payload, [
            'updated_at' => $now,
        ]));
    }
}
