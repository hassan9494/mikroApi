<?php

namespace Modules\Common\Entities;

use App\Traits\Media;
use Illuminate\Database\Eloquent\Model;
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

}
