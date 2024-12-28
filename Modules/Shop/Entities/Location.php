<?php

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Location extends Model
{

    protected $table = 'locations';
    protected $fillable = ['id', 'name'];

    public function products_in_store()
    {
        return $this->hasMany(Product::class,'store_location');
    }

    public function products_in_stock()
    {
        return $this->hasMany(Product::class,'stock_location');
    }
}
