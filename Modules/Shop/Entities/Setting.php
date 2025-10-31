<?php


namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $table = 'settings';
    protected $fillable = ['key', 'name', 'description', 'value', 'field', 'active'];

    protected static function booted()
    {
        parent::booted();
        static::saved(function ($setting) {
            Cache::forget('all_settings');
        });

        static::deleted(function ($setting) {
            Cache::forget('all_settings');
        });
    }

}
