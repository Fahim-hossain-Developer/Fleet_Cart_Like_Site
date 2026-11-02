<?php

use App\Domain\Settings\Actions\Settings;

function store_setting(string $key, mixed $default = null): mixed
{
    return app(Settings::class)->get($key, $default);
}
