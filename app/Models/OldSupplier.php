<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OldSupplier extends Model
{
    use HasFactory;

    protected $table = 'old_supplier';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'UniversityName',
        'address',
        'note',
        'SupplierPercent',
        'deleted',
        'IsShowAsShippingMethod'
    ];
}
