<?php

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * Class ProductCategory
 * @package Modules\Shop\Entities
 */
class ProductCategory extends Model
{

    protected $table = 'product_category';

    /**
     * @var string[]
     */
    protected $fillable = [
        'product_id',
        'category_id',
    ];

}
