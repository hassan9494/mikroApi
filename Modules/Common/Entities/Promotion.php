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
class Promotion extends Model implements HasMedia
{

    use Media;

    /**
     * @var string $table
     */
    protected $table = 'promotions';

    /**
     * @var array $fillable
     */
    protected $fillable = [
        'order',
        'name',
        'link'
    ];

    protected static function booted()
    {
        parent::booted();
        static::saved(function ($promotion) {
            Cache::forget('all_offers');
        });

        static::deleted(function ($promotion) {
            Cache::forget('all_offers');
        });
    }

}
