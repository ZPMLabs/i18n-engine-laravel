<?php

declare(strict_types=1);

namespace ZPMLabs\I18nEngine\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

final class CommandArticle extends Model
{
    protected $table = 'command_articles';

    protected $guarded = [];
}
