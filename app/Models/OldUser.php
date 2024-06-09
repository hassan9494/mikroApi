<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OldUser extends Model
{
    use HasFactory;

    protected $table = 'user';

    protected $fillable = [
        'user_name',
        'password',
        'email',
        'phone_number',
        'display_name',
        'create_date',
        'last_visit',
        'visit_count',
        'privilege'
    ];
}
