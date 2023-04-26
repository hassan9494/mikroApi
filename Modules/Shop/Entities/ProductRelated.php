<?php

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * Class ProductRelated
 * @package Modules\Shop\Entities
 */
class ProductRelated extends Model
{

    protected $table = 'product_related';

    /**
     * @var string[]
     */
    protected $fillable = [
        'parent_id',
        'child_id',
    ];

}
