<?php

declare(strict_types=1);

namespace ZPMLabs\I18nEngine\Tests;

use ZPMLabs\I18nEngine\Tests\Fixtures\CommandArticle;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;

final class MakeTranslationTableCommandTest extends TestCase
{
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();

        $this->createBaseTable();
        $this->ensureStubExistsAtBasePath();
    }

    public function test_command_generates_translation_migration_from_stub(): void
    {
        $outputPath = 'database/migrations/test-output';
        $outputDir = $this->app->basePath($outputPath);

        $this->filesystem->deleteDirectory($outputDir);

        $this->artisan('i18n-engine:make', [
            'model' => CommandArticle::class,
            '--path' => $outputPath,
            '--stub' => 'database/stubs/create_translation_table.stub',
        ])->assertExitCode(0);

        $files = glob($outputDir . DIRECTORY_SEPARATOR . '*_create_command_articles_translations_table.php');

        $this->assertIsArray($files);
        $this->assertCount(1, $files);

        $content = (string) file_get_contents($files[0]);

        $this->assertStringContainsString("Schema::create('command_articles_translations'", $content);
        $this->assertStringContainsString("\$table->foreignId('foreign_id')->references('id')->on('command_articles')->onDelete('cascade');", $content);
        $this->assertStringContainsString("\$table->string('language', 16);", $content);

        $this->assertTrue(
            str_contains($content, "\$table->string('name')->nullable();")
            || str_contains($content, "\$table->text('name')->nullable();")
        );
        $this->assertStringContainsString("\$table->text('body')->nullable();", $content);
        $this->assertTrue(
            str_contains($content, "\$table->json('payload')->nullable();")
            || str_contains($content, "\$table->text('payload')->nullable();")
        );

        $this->assertStringNotContainsString("\$table->integer('rank')", $content);
        $this->assertStringContainsString("\$table->unique(['foreign_id', 'language']);", $content);
    }

    private function createBaseTable(): void
    {
        Schema::create('command_articles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('body');
            $table->json('payload')->nullable();
            $table->integer('rank')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function ensureStubExistsAtBasePath(): void
    {
        $baseStub = $this->app->basePath('database/stubs/create_translation_table.stub');
        if ($this->filesystem->exists($baseStub)) {
            return;
        }

        $packageStub = dirname(__DIR__) . '/database/stubs/create_translation_table.stub';

        $this->filesystem->ensureDirectoryExists(dirname($baseStub));
        $this->filesystem->copy($packageStub, $baseStub);
    }
}
