# zpmlabs/i18n-engine-laravel

Laravel i18n engine package for translation-table based querying.

## Namespace

`ZPMLabs\\I18nEngine`

## Install

```bash
composer require zpmlabs/i18n-engine-laravel
```

## Model usage

```php
use ZPMLabs\I18nEngine\Contracts\HasTranslationTable;
use ZPMLabs\I18nEngine\Traits\InteractsWithTranslationTable;

class Article extends Model implements HasTranslationTable
{
	use InteractsWithTranslationTable;

	protected $table = 'articles';
	protected $fillable = ['title'];

	public function translatedColumns(): array
	{
		return ['title'];
	}
}
```

## Query macros

- `withTranslations(?string $locale = null)`
- `withTranslationsList()`

## Configuration

Publish config:

```bash
php artisan vendor:publish --tag=i18n-engine-config
```

Config file: `config/i18n-engine.php`

```php
return [
	'default_locale' => 'en',
	'query_param' => 'lang',
	'header' => 'Accept-Language',
	'normalize_locale' => true,
	'foreign_key' => 'foreign_id',
	'locale_key' => 'language',
	'table_suffix' => '_translations',
	'skip_locale_changes_for_routes' => [],
	'locale_map' => [],
	'system_languages_enum' => '',
];
```

Key notes:

- `table_suffix`: suffix for translation tables (example: `articles_translations`).
- `foreign_key`: FK column in translation tables pointing to base model PK.
- `locale_key`: locale column in translation tables.
- `normalize_locale`: normalizes values like `sr-RS,sr;q=0.9` to `sr`.
- `locale_map`: optional direct locale remapping.
- `system_languages_enum`: optional enum class used for advanced locale mapping.

## Migration example

## Generate migration command

Use the package command to generate a translation-table migration from an existing model table:

```bash
php artisan i18n-engine:make App\\Models\\Article
```

Common options:

- `--connection=`: inspect a specific DB connection
- `--path=database/migrations`: custom output path
- `--stub=database/stubs/create_translation_table.stub`: custom stub path
- `--module=`: write migration to `Modules/<Module>/Database/Migrations`
- `--id-type=uuid|ulid|int`: force FK type
- `--all`: include all columns except meta/key columns

Examples:

```bash
php artisan i18n-engine:make App\\Models\\Article --connection=mysql
php artisan i18n-engine:make App\\Models\\Article --module=Blog
php artisan i18n-engine:make App\\Models\\Article --path=database/migrations/custom --id-type=uuid
```

The generated migration name pattern is:

- `YYYY_MM_DD_HHMMSS_create_<base_table>_translations_table.php`

The command reads package config keys (`i18n-engine.table_suffix`, `i18n-engine.foreign_key`, `i18n-engine.locale_key`) while generating output.

Example for base table `articles` and translated column `title`:

```php
Schema::create('articles_translations', function (Blueprint $table) {
	$table->bigIncrements('translation_id');
	$table->unsignedBigInteger('foreign_id');
	$table->string('language', 8);

	$table->string('title')->nullable();

	$table->timestamps();

	$table->unique(['foreign_id', 'language']);
	$table->index('language');
});
```

This layout matches the package defaults (`foreign_id`, `language`, `_translations`).

## Testing

```bash
composer test
```

Test logs are written to:

- `tests/logs/query-builder.log` (translation table snapshots + query results)
- `tests/logs/junit.xml` (JUnit report)
