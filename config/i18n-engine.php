<?php

declare(strict_types=1);

return [
    'default_locale' => 'en',

    // locale sources: ?lang=sr, Accept-Language header, app()->getLocale(), cookie, session
    'query_param' => 'lang',
    'header' => 'Accept-Language',

    // "sr-RS,sr;q=0.9" => "sr"
    'normalize_locale' => true,

    // FK + locale column in *_translations tables
    'foreign_key' => 'foreign_id',
    'locale_key' => 'language',

    // Naming
    'table_suffix' => '_translations',   // do not change after first usage!!!

    'skip_locale_changes_for_routes' => [],

    // Custom request locale handler (see README: "Custom request locale handler")
    'request_locale_handler' => \ZPMLabs\I18nEngine\Handlers\FilamentLocaleRequestHandler::class,

    'locale_map' => [],
    'system_languages_enum' => '',
];
