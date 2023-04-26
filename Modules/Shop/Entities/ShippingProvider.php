<?php

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * Class ShippingProvider
 * @package Modules\Shop\Entities
 */
class ShippingProvider extends Model
{

    /**
     * @var array $fillable
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'notes',
    ];


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orders() {
        return $this->hasMany(Order::class, 'shipping_provider_id');
    }

}

