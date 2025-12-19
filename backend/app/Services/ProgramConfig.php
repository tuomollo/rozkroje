<?php

namespace App\Services;

use App\Models\Setting;

class ProgramConfig
{
    public static function getConfig(string $key, mixed $default = null): mixed
    {
        $setting = Setting::where('key', $key)->first();

        return $setting?->value ?? $default;
    }
}
