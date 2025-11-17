<?php

namespace Modules\Shop\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        'count_per_user',
        'main_count',
        'use_count',
        'apply_count',
        'apply_count_per_user',
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

    /**
     * @return BelongsToMany
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'coupon_products',
            'coupon_id',
            'product_id'
        )->withTrashed()->withPivot('product_name');
    }

    /**
     * @return BelongsToMany
     */
    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(
            Brand::class,
            'coupon_brands',
            'coupon_id',
            'brand_id'
        )->withPivot('brand_name');
    }

    /**
     * @return BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'coupon_users',
            'coupon_id',
            'user_id'
        )->withPivot('order_id','used_at');
    }


}

