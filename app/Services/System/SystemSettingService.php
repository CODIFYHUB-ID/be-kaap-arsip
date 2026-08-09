<?php

namespace App\Services\System;

use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Collection;

class SystemSettingService
{
    public function getAll(): Collection
    {
        return SystemSetting::all();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $setting = SystemSetting::where('key', $key)->first();
        if (! $setting) {
            return $default;
        }

        return match ($setting->type) {
            'integer' => (int) $setting->value,
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($setting->value, true),
            default => $setting->value,
        };
    }

    public function set(string $key, mixed $value): SystemSetting
    {
        $setting = SystemSetting::where('key', $key)->first();
        if ($setting) {
            $setting->update(['value' => is_array($value) ? json_encode($value) : (string) $value]);
            return $setting;
        }

        return SystemSetting::create([
            'key' => $key,
            'value' => is_array($value) ? json_encode($value) : (string) $value,
            'type' => is_numeric($value) ? 'integer' : (is_bool($value) ? 'boolean' : 'string'),
        ]);
    }
}
