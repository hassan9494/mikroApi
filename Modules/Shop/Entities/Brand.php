<?php

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Brand extends Model
{

    protected $table = 'brands';
    protected $fillable = ['id', 'name', 'slug', 'order'];


    public function products()
    {
        return $this->hasMany(Product::class,'brand_id');
    }

    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class, 'coupon_brands', 'brand_id', 'coupon_id')
            ->withPivot([
                'brand_name'
            ]);
    }


}
