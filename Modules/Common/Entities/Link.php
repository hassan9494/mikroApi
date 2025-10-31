<?php

namespace Modules\Common\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;

class Link extends Model
{
    use HasFactory;

    protected $fillable = [
        'location',
        'email',
        'facebook',
        'instagram',
        'telegram',
        'whatsapp',
        'youtube',
        'call'
    ];

    protected static function booted()
    {
        parent::booted();
        static::saved(function ($link) {
            Cache::forget('all_links');
        });

        static::deleted(function ($link) {
            Cache::forget('all_links');
        });
    }
}
