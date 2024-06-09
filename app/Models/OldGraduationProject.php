<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OldGraduationProject extends Model
{
    use HasFactory;

    protected $table = 'old_graduateproject';

    protected $fillable = [
        'name',
        'createDate',
        'deadline',
        'description',
        'studentsName',
        'amount',
        'paymentsDetails',
        'FinishDate'
    ];
}
