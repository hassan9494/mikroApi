<?php

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{

    protected $table = 'brands';
    protected $fillable = ['id', 'name', 'slug', 'order'];


    public function products()
    {
        return $this->hasMany(Product::class,'brand_id');
    }


}
