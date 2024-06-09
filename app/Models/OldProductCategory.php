<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OldProductCategory extends Model
{
    use HasFactory;

    protected $table = 'old_procat';

    protected $fillable = ['product_id', 'category_id'];

    protected $guarded = [];
}
