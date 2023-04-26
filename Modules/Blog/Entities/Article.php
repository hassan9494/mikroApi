<?php

namespace Modules\Blog\Entities;

use App\Traits\Media;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Class Article
 * @package Modules\Blog\Entities
 */
class Article extends Model implements HasMedia
{
    use Media;

    /**
     * @var array $fillable
     */
    protected $fillable = [
        'title',
        'order',
        'video_url',
        'content',
        'type',
    ];

}
