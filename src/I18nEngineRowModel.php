<?php

declare(strict_types=1);

namespace ZPMLabs\I18nEngine;

use Illuminate\Database\Eloquent\Model;

final class I18nEngineRowModel extends Model
{
    protected $guarded = [];

    public $timestamps = true;

    protected $primaryKey = 'translation_id';

    public $incrementing = true;
}