<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    public const GROUP_PARAMETER = 'parameter';
    public const GROUP_TEMPLATE = 'template';

    protected $table = 'settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['key', 'value', 'group'];

    /**
     * Get a setting value by key with optional default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        return $setting !== null ? $setting->value : $default;
    }

    /**
     * Get a setting value (string) and cast to int.
     */
    public static function getInt(string $key, int $default = 0): int
    {
        $v = static::get($key, (string) $default);

        return (int) $v;
    }

    /**
     * Get a setting value (string) and cast to float.
     */
    public static function getFloat(string $key, float $default = 0.0): float
    {
        $v = static::get($key, (string) $default);

        return (float) $v;
    }

    /**
     * Set a setting value. Creates or updates the record.
     */
    public static function set(string $key, mixed $value, string $group = self::GROUP_PARAMETER): void
    {
        static::query()->updateOrInsert(
            ['key' => $key],
            ['value' => $value === null ? null : (string) $value, 'group' => $group]
        );
        static::clearCache();
    }

    /**
     * Get all settings in a group, keyed by key.
     *
     * @return array<string, string|null>
     */
    public static function getByGroup(string $group): array
    {
        $cacheKey = 'settings.' . $group;
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }
        $rows = static::query()->where('group', $group)->get();
        $out = [];
        foreach ($rows as $row) {
            $out[$row->key] = $row->value;
        }
        Cache::put($cacheKey, $out, now()->addHours(24));

        return $out;
    }

    /**
     * Set multiple settings in a group. Keys not in $values are left unchanged.
     */
    public static function setByGroup(string $group, array $values): void
    {
        foreach ($values as $key => $value) {
            if ($key === '' || ! is_string($key)) {
                continue;
            }
            static::set($key, $value, $group);
        }
    }

    public static function clearCache(): void
    {
        Cache::forget('settings.' . self::GROUP_PARAMETER);
        Cache::forget('settings.' . self::GROUP_TEMPLATE);
    }

    /**
     * Get a template by key and replace placeholders. E.g. renderTemplate('email_payment_reminder', ['member_name' => 'John']).
     */
    public static function renderTemplate(string $key, array $replace = []): string
    {
        $template = static::get($key, '');
        if ($template === null || $template === '') {
            return '';
        }
        foreach ($replace as $placeholder => $value) {
            $template = str_replace('{' . $placeholder . '}', (string) $value, $template);
        }

        return $template;
    }

    /**
     * Get the application display name (from settings or config).
     */
    public static function appDisplayName(): string
    {
        $name = static::get('app_display_name', '');
        if ($name !== null && $name !== '') {
            return $name;
        }

        return (string) config('app.name', 'Laravel');
    }
}
