<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OldKitInclude extends Model
{
    use HasFactory;

    protected $table = 'kitinclude';

    protected $fillable = [
        'kit_id',
        'item_id',
        'qty',
    ];
}
