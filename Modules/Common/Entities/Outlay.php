<?php

namespace Modules\Common\Entities;

use Illuminate\Database\Eloquent\Model;

class Outlay extends Model
{

    protected $fillable = [
        'name',
        'amount',
        'date',
        'notes',
        'invoice',
        'type',
    ];

}
