<?php

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Source extends Model
{
    protected $table = 'sources';
    protected $fillable = ['id', 'name', 'slug', 'order'];


    public function products()
    {
        return $this->hasMany(Product::class,'source_id');
    }
    public function invoices()
    {
        return $this->hasMany(Invoice::class,'source_id');
    }
}
