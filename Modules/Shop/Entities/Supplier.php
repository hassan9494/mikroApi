<?php

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{

    protected $fillable = [
        'user_id',
        'university',
        'address',
        'notes',
        'supplier_percentage',
        'show_as_shipping_method',
    ];

}
