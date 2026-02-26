<?php

declare(strict_types=1);

namespace ZPMLabs\I18nEngine\Tests\Fixtures;

use ZPMLabs\I18nEngine\Contracts\HasTranslationTable;
use ZPMLabs\I18nEngine\Traits\InteractsWithTranslationTable;
use Illuminate\Database\Eloquent\Model;

final class Article extends Model implements HasTranslationTable
{
    use InteractsWithTranslationTable;

    protected $table = 'articles';

    protected $fillable = ['slug', 'title', 'description'];

    public function translatedColumns(): array
    {
        return ['title', 'description'];
    }
}
