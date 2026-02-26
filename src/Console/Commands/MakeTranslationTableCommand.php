<?php

declare(strict_types=1);

namespace ZPMLabs\I18nEngine\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class MakeTranslationTableCommand extends Command
{
    protected $signature = 'i18n-engine:make
        {model : Fully-qualified model class (e.g. App\\Models\\Doctor)}
        {--connection= : DB connection to inspect (defaults to model connection)}
        {--path=database/migrations : Where to place the migration (ignored when --module is used unless you pass --path explicitly)}
        {--stub=database/stubs/create_translation_table.stub : Stub file path (relative to base_path)}
        {--module= : nwidart/laravel-modules module name (e.g. Blog). If provided, migration will be generated into Modules/<Module>/Database/Migrations}
        {--id-type= : Force base id type: uuid|ulid|int (optional)}
        {--all : Include ALL columns (not recommended; overrides type filtering)}';

    protected $description = 'Generate a migration for {table}{table_suffix} by copying ONLY translatable columns (string/text/json) from the base table, using a stub. Supports nwidart/laravel-modules via --module.';

    private const TRANSLATABLE_TYPES = [
        'string',
        'varchar',
        'char',
        'text',
        'mediumtext',
        'longtext',
        'json',
        'jsonb',
    ];

    private const ALWAYS_SKIP = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function handle(Filesystem $fs): int
    {
        $modelClass = (string) $this->argument('model');

        if (!class_exists($modelClass)) {
            $this->error("Model class not found: {$modelClass}");
            return self::FAILURE;
        }

        $model = new $modelClass();
        if (!$model instanceof Model) {
            $this->error("Provided class is not an Eloquent model: {$modelClass}");
            return self::FAILURE;
        }

        $connectionName = (string) ($this->option('connection') ?: $model->getConnectionName() ?: Config::get('database.default'));
        $schema = Schema::connection($connectionName);

        $baseTable = $model->getTable();

        if (!$schema->hasTable($baseTable)) {
            $this->error("Base table does not exist: {$baseTable} (connection: {$connectionName})");
            return self::FAILURE;
        }

        $tableSuffix = (string) Config::get('i18n-engine.table_suffix', '_translations');
        $translationTable = $baseTable . $tableSuffix;

        $foreignKey = (string) Config::get('i18n-engine.foreign_key', 'foreign_id');
        $localeKey = (string) Config::get('i18n-engine.locale_key', 'language');

        // Resolve stub path (module stub preferred if module provided)
        $module = (string) ($this->option('module') ?? '');
        $stubPath = $this->resolveStubPath($fs, $module);

        $stub = $fs->get($stubPath);

        // Build column => type map
        $columns = collect($schema->getColumnListing($baseTable))
            ->mapWithKeys(fn (string $column) => [$column => strtolower((string) $schema->getColumnType($baseTable, $column))]);

        if ($columns->isEmpty()) {
            $this->error("No columns found for table: {$baseTable}");
            return self::FAILURE;
        }

        // foreign_id type from base id type (best-effort)
        $forcedIdType = strtolower((string) ($this->option('id-type') ?? ''));
        if (in_array($forcedIdType, ['uuid', 'ulid', 'int'], true)) {
            $idType = $forcedIdType;
        } else {
            $idType = $this->detectIdType($columns);
        }

        $foreignIdLine = $this->foreignIdLine($idType, $foreignKey);

        // Determine which columns to generate
        $skip = array_values(array_unique(array_merge(
            self::ALWAYS_SKIP,
            [$foreignKey, $localeKey]
        )));

        $includeAll = (bool) $this->option('all');

        $selected = $includeAll
            ? $columns->reject(fn ($type, $name) => in_array($name, $skip, true))
            : $columns
                ->reject(fn ($type, $name) => in_array($name, $skip, true))
                ->filter(fn (string $type) => in_array($type, self::TRANSLATABLE_TYPES, true));

        if ($selected->isEmpty()) {
            $this->warn('No translatable columns detected. Translation table will include only foreign_id + language + timestamps.');
        }

        $columnsBlock = $this->columnsToBlueprintBlock($selected->all());

        $content = str_replace(
            [
                '{{ table }}',
                '{{ base_table }}',
                '{{ foreign_key }}',
                '{{ locale_key }}',
                '{{ foreign_id_line }}',
                '{{ columns }}',
            ],
            [
                $translationTable,
                $baseTable,
                $foreignKey,
                $localeKey,
                $foreignIdLine,
                $columnsBlock,
            ],
            $stub
        );

        $timestamp = Carbon::now()->format('Y_m_d_His');
        $migrationName = 'create_' . $translationTable . '_table';
        $filename = $timestamp . '_' . $migrationName . '.php';

        // Resolve migration output directory
        $outputDir = $this->resolveMigrationOutputDir($fs, $module);

        $fullPath = $outputDir . DIRECTORY_SEPARATOR . $filename;
        $fs->put($fullPath, $content);

        $this->info("Created migration: {$fullPath}");

        if ($module !== '') {
            $this->line("Module: {$module}");
        }

        $this->line($includeAll
            ? 'Mode: --all (all columns copied except meta + keys)'
            : 'Mode: translatable-only (string/text/json/jsonb)'
        );

        $this->line("Stub used: {$stubPath}");
        return self::SUCCESS;
    }

    /**
     * @param \Illuminate\Support\Collection<string,string> $columns
     */
    private function detectIdType($columns): string
    {
        $t = strtolower((string) ($columns->get('id') ?? 'bigint'));

        // Direct hits
        if ($t === 'uuid') return 'uuid';
        if ($t === 'ulid') return 'ulid';

        // Many DB drivers report uuid/ulid as "string" (char/varchar)
        // Best-effort heuristic: if id is string, assume ULID only if length is 26 (we don't have length here),
        // so default to uuid only if app uses uuid casts. Since we can't know reliably, keep default 'int'.
        // => safest default is 'int' to avoid broken FK types.
        if (in_array($t, ['string', 'char', 'varchar'], true)) {
            return 'int';
        }

        return 'int';
    }

    private function resolveStubPath(Filesystem $fs, string $module): string
    {
        // If module is provided, prefer module-local stub:
        // Modules/<Module>/stubs/create_translation_table.stub
        if ($module !== '') {
            $moduleStub = $this->basePath('Modules' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR . 'create_translation_table.stub');
            if ($fs->exists($moduleStub)) {
                return $moduleStub;
            }
        }

        $globalStub = $this->basePath((string) $this->option('stub'));
        if (!$fs->exists($globalStub)) {
            throw new RuntimeException("Stub file not found: {$globalStub}");
        }

        return $globalStub;
    }

    private function resolveMigrationOutputDir(Filesystem $fs, string $module): string
    {
        // If user explicitly passed --path, always honor it (relative to base_path)
        $pathOpt = (string) $this->option('path');
        $pathWasExplicit = $this->wasOptionProvided('--path');

        if ($pathWasExplicit) {
            $dir = $this->basePath($pathOpt);
            if (!$fs->exists($dir)) {
                $fs->makeDirectory($dir, 0755, true);
            }
            return $dir;
        }

        // If module provided: Modules/<Module>/Database/Migrations
        if ($module !== '') {
            $dir = $this->basePath('Modules' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'Migrations');
            if (!$fs->exists($dir)) {
                $fs->makeDirectory($dir, 0755, true);
            }
            return $dir;
        }

        // Default
        $dir = $this->basePath($pathOpt);
        if (!$fs->exists($dir)) {
            $fs->makeDirectory($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Detect if an option was explicitly provided on CLI.
     * (Laravel doesn't directly expose this, so we inspect argv.)
     */
    private function wasOptionProvided(string $flag): bool
    {
        $argv = $_SERVER['argv'] ?? [];
        foreach ($argv as $arg) {
            if ($arg === $flag || str_starts_with($arg, $flag . '=')) {
                return true;
            }
        }
        return false;
    }

    private function foreignIdLine(string $idType, string $foreignKey): string
    {
        $idType = strtolower($idType);

        return match ($idType) {
            'uuid' => "\$table->foreignUuid('{$foreignKey}')",
            'ulid' => "\$table->foreignUlid('{$foreignKey}')",
            default => "\$table->foreignId('{$foreignKey}')",
        };
    }

    /**
     * @param array<string, string> $columnsWithTypes Selected columns only
     */
    private function columnsToBlueprintBlock(array $columnsWithTypes): string
    {
        $lines = [];

        foreach ($columnsWithTypes as $name => $type) {
            $lines[] = $this->toBlueprintLine((string) $name, strtolower((string) $type));
        }

        return implode("\n", array_map(fn ($l) => '            ' . $l, $lines));
    }

    private function toBlueprintLine(string $name, string $type): string
    {
        $expr = match ($type) {
            'string' => "\$table->string('{$name}')",
            'char' => "\$table->char('{$name}')",

            'text' => "\$table->text('{$name}')",
            'mediumtext' => "\$table->mediumText('{$name}')",
            'longtext' => "\$table->longText('{$name}')",

            'json', 'jsonb' => "\$table->json('{$name}')",

            default => "\$table->text('{$name}')",
        };

        return $expr . "->nullable();";
    }

    private function basePath(string $path = ''): string
    {
        if ($this->laravel !== null) {
            return $this->laravel->basePath($path);
        }

        return getcwd() . ($path === '' ? '' : DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR));
    }
}
