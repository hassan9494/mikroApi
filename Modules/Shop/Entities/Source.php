<?php

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Model;

class Source extends Model
{
    protected $table = 'sources';
    protected $fillable = ['id', 'name', 'slug', 'order', 'phone', 'email', 'note'];


    public function products()
    {
        return $this->hasMany(Product::class,'source_id');
    }
    public function invoices()
    {
        return $this->hasMany(Invoice::class,'source_id');
    }
}
