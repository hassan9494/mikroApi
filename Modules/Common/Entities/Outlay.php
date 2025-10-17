<?php

namespace Modules\Common\Entities;

use App\Traits\Media;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;

class Outlay extends Model implements HasMedia
{
    use Media;

    protected $fillable = [
        'name',
        'amount',
        'date',
        'notes',
        'invoice',
        'type',
        'sub_type',
        'total_amount',
        'tax_number',
        'tax'
    ];

}
