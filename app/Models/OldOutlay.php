<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OldOutlay extends Model
{
    use HasFactory;

    protected $table = 'old_outlay';

    protected $fillable = [
        'name',
        'invoiceReference',
        'amount',
        'date',
        'type'
    ];
}
