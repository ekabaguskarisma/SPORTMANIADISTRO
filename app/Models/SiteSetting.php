<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function getJson(string $key, array $default = []): array
    {
        $record = static::query()->where('key', $key)->first();
        if (! $record || blank($record->value)) {
            return $default;
        }

        $decoded = json_decode($record->value, true);

        return is_array($decoded) ? $decoded : $default;
    }

    public static function setJson(string $key, array $data): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => json_encode($data, JSON_UNESCAPED_UNICODE)]
        );
    }
}