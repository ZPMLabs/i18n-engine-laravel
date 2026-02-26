<?php

declare(strict_types=1);

namespace ZPMLabs\I18nEngine\Contracts;

interface HasTranslationTable
{
    public function translationTableName(): string;

    public function translationForeignKey(): string;

    public function translationLocaleKey(): string;

    public function translationTableSuffix(): string;

    public function baseColumns(): array;
    public function translatedColumns(): array;
}
