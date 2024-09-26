<?php

namespace Modules\Common\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tag extends Model
{
    /**
     * @var string $table
     */
    protected $table = 'tags';

    /**
     * @var array $fillable
     */
    protected $fillable = [
        'name',
        'script',
        'type',
    ];
}
