<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OldCourse extends Model
{
    use HasFactory;

    protected $table = 'old_courses';

    protected $fillable = ['name','createDate','FinishDate','cost'];
}
