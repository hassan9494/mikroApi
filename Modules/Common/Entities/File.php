<?php

namespace Modules\Common\Entities;

use App\Traits\Media;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;

/**
 * Class City
 * @package Modules\Shop\Entities
 */
class File extends Model implements HasMedia
{
    use Media;

    /**
     * @var array $fillable
     */
    protected $fillable = [
        'name',
    ];

}
