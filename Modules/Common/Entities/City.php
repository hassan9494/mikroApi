<?php

namespace Modules\Common\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * Class City
 * @package Modules\Shop\Entities
 */
class City extends Model
{

    /**
     * @var string $table
     */
    protected $table = 'cities';

    /**
     * @var array $fillable
     */
    protected $fillable = [
        'name',
        'shipping_cost'
    ];

}
