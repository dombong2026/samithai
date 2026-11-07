<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key','value'];

    public static function text(string $key, string $fallback=''): string
    {
        return (string) (static::query()->where('key',$key)->value('value') ?? $fallback);
    }
}
