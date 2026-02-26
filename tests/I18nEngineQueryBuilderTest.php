<?php

declare(strict_types=1);

namespace ZPMLabs\I18nEngine\Tests;

use ZPMLabs\I18nEngine\Tests\Fixtures\Article;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

final class I18nEngineQueryBuilderTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logFile = dirname(__DIR__) . '/tests/logs/query-builder.log';
        $this->ensureLogDirectory();

        $this->createSchema();
        $this->seedData();
    }

    public function test_with_translations_uses_requested_locale_with_default_fallback(): void
    {
        $rows = Article::query()
            ->withoutGlobalScopes()
            ->withTranslations('fr')
            ->orderBy('articles.id')
            ->get();

        $this->logQuerySnapshot(__FUNCTION__, $rows->map(fn ($row) => [
            'id' => $row->id,
            'slug' => $row->slug,
            'title' => $row->title,
            'description' => $row->description,
        ])->all());

        $this->assertCount(2, $rows);

        $this->assertSame('first', $rows[0]->slug);
        $this->assertSame('Bonjour', $rows[0]->title);
        $this->assertSame('Description FR', $rows[0]->description);

        $this->assertSame('second', $rows[1]->slug);
        $this->assertSame('Fallback EN', $rows[1]->title);
        $this->assertSame('EN only', $rows[1]->description);
    }

    public function test_with_translations_list_eager_loads_translation_rows(): void
    {
        $article = Article::query()
            ->withTranslationsList()
            ->findOrFail(1);

        $this->logQuerySnapshot(__FUNCTION__, [
            'article_id' => $article->id,
            'translation_rows' => $article->translations->map(fn ($row) => [
                'foreign_id' => $row->foreign_id,
                'language' => $row->language,
                'title' => $row->title,
                'description' => $row->description,
            ])->values()->all(),
        ]);

        $this->assertTrue($article->relationLoaded('translations'));
        $this->assertCount(2, $article->translations);
        $this->assertSame(['en', 'fr'], $article->translations->pluck('language')->sort()->values()->all());
    }

    public function test_with_translations_supports_chained_custom_selects_and_ordering(): void
    {
        $rows = Article::query()
            ->withoutGlobalScopes()
            ->selectRaw('articles.id, UPPER(articles.slug) as slug_upper')
            ->withTranslations('fr')
            ->orderBy('title')
            ->get();

        $this->logQuerySnapshot(__FUNCTION__, $rows->map(fn ($row) => [
            'id' => $row->id,
            'slug_upper' => $row->slug_upper,
            'title' => $row->title,
        ])->all());

        $this->assertCount(2, $rows);

        $this->assertSame(1, $rows[0]->id);
        $this->assertSame('FIRST', $rows[0]->slug_upper);
        $this->assertSame('Bonjour', $rows[0]->title);

        $this->assertSame(2, $rows[1]->id);
        $this->assertSame('SECOND', $rows[1]->slug_upper);
        $this->assertSame('Fallback EN', $rows[1]->title);
    }

    public function test_with_translations_does_not_duplicate_joins_when_called_twice(): void
    {
        $builder = Article::query()
            ->withoutGlobalScopes()
            ->withTranslations('fr')
            ->withTranslations('fr');

        $joinTables = array_map(
            static fn ($join) => (string) $join->table,
            $builder->getQuery()->joins ?? []
        );

        $this->logQuerySnapshot(__FUNCTION__, [
            'joins' => $joinTables,
        ]);

        $this->assertSame(
            ['articles_translations', 'articles_translations as articles_translations_default'],
            $joinTables
        );
    }

    public function test_with_translations_allows_filtering_by_translation_table_columns(): void
    {
        $rows = Article::query()
            ->withoutGlobalScopes()
            ->withTranslations('fr')
            ->where(function ($query): void {
                $query->where('articles_translations.title', 'Bonjour')
                    ->orWhere('articles_translations_default.title', 'Fallback EN');
            })
            ->orderBy('articles.id')
            ->get();

        $this->logQuerySnapshot(__FUNCTION__, $rows->map(fn ($row) => [
            'id' => $row->id,
            'title' => $row->title,
        ])->all());

        $this->assertCount(2, $rows);
        $this->assertSame(['Bonjour', 'Fallback EN'], $rows->pluck('title')->values()->all());
    }

    private function createSchema(): void
    {
        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->string('slug');
            $table->timestamps();
        });

        Schema::create('articles_translations', function (Blueprint $table): void {
            $table->bigIncrements('translation_id');
            $table->unsignedBigInteger('foreign_id');
            $table->string('language');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['foreign_id', 'language']);
        });
    }

    private function seedData(): void
    {
        DB::table('articles')->insert([
            ['id' => 1, 'slug' => 'first'],
            ['id' => 2, 'slug' => 'second'],
        ]);

        DB::table('articles_translations')->insert([
            [
                'foreign_id' => 1,
                'language' => 'en',
                'title' => 'Hello',
                'description' => 'Description EN',
            ],
            [
                'foreign_id' => 1,
                'language' => 'fr',
                'title' => 'Bonjour',
                'description' => 'Description FR',
            ],
            [
                'foreign_id' => 2,
                'language' => 'en',
                'title' => 'Fallback EN',
                'description' => 'EN only',
            ],
            [
                'foreign_id' => 2,
                'language' => 'fr',
                'title' => '',
                'description' => '',
            ],
        ]);
    }

    private function ensureLogDirectory(): void
    {
        $dir = dirname($this->logFile);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    /**
     * @param array<int|string, mixed> $result
     */
    private function logQuerySnapshot(string $testName, array $result): void
    {
        $translationTable = DB::table('articles_translations')
            ->orderBy('foreign_id')
            ->orderBy('language')
            ->get()
            ->map(fn ($row) => [
                'foreign_id' => $row->foreign_id,
                'language' => $row->language,
                'title' => $row->title,
                'description' => $row->description,
            ])
            ->all();

        $payload = [
            'time' => date(DATE_ATOM),
            'test' => $testName,
            'translation_table' => $translationTable,
            'result' => $result,
        ];

        file_put_contents(
            $this->logFile,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL . str_repeat('-', 80) . PHP_EOL,
            FILE_APPEND
        );
    }
}
