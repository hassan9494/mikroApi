<?php

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Order
 * @package Modules\Shop\Entities
 */
class Coupon extends Model
{

    /**
     * @var array $fillable
     */
    protected $fillable = [
        'name',
        'code',
        'amount',
        'is_percentage',
        'start_at',
        'end_at',
        'count',
        'active',
    ];

    /**
     * @var string[] $appends
     */
    protected $appends = [
        'valid'
    ];

    protected $casts = [
        'start_at' => 'date',
        'end_at' => 'date',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orders() {
        return $this->hasMany(Order::class, 'coupon_id');
    }

    /**
     * @return bool
     */
    public function getValidAttribute() {
        if (!$this->active || $this->count < 0 || $this->start_at?->isFuture() || $this->end_at?->isPast()) {
            return false;
        }
        return true;
    }


    /**
     * @return bool
     */
    public function calc($total) {
        if (!$this->valid) {
            return $total;
        }
        if (!$this->is_percentage) {
            $total -= $this->amount;
            return $total > 0 ? $total : 0;
        }
        $total -= $this->amount;
        return $total * ((100 - $this->amount) / 100);
    }


}

