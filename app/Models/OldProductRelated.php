<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OldProductRelated extends Model
{
    use HasFactory;

    protected $table = 'old_relpro';

    protected $fillable = ['product1_id', 'product2_id'];
}
