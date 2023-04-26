<?php

namespace Modules\Common\Entities;

use Illuminate\Database\Eloquent\Model;

class Dept extends Model
{

    protected $fillable = [
        'name',
        'amount',
        'date',
        'notes',
        'paid',
    ];

}
