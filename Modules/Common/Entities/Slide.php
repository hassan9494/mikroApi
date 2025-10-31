<?php

namespace Modules\Common\Entities;

use App\Traits\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\HasMedia;

/**
 * Class City
 * @package Modules\Shop\Entities
 */
class Slide extends Model implements HasMedia
{
    use Media;

    /**
     * @var string $table
     */
    protected $table = 'slides';

    /**
     * @var array $fillable
     */
    protected $fillable = [
        'order',
        'name',
    ];

    protected static function booted()
    {
        parent::booted();
        static::saved(function ($slide) {
            Cache::forget('all_slides');
        });

        static::deleted(function ($slide) {
            Cache::forget('all_slides');
        });
    }

}
