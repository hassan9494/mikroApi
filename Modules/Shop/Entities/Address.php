<?php

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Common\Entities\City;
use Modules\Shop\Support\Enums\OrderShippingStatus;

/**
 * Class Order
 * @package Modules\Shop\Entities
 */
class Address extends Model
{

    /**
     * @var array $fillable
     */
    protected $fillable = [
        'user_id',
        'city_id',
        'name',
        'email',
        'phone',
        'content',
        'is_primary',
    ];

    /**
     * @var string[] $appends
     */
    protected $appends = [
        'customer',
        'shipping',
        'shipping_cost',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function city() {
        return $this->belongsTo(City::class, 'city_id');
    }

    /**
     * @return array
     */
    public function getCustomerAttribute()
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
        ];
    }

    /**
     * @return array
     */
    public function getShippingAttribute()
    {
        return [
            'city' => $this->city->name,
            'address' => $this->content,
            'cost' => $this->city->shipping_cost,
            'status' => OrderShippingStatus::WAITING()->value
        ];
    }


    /**
     * @return mixed
     */
    public function getShippingCostAttribute()
    {
        return $this->city->shipping_cost;
    }

}

