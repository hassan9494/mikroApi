<?php

namespace Modules\Common\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Link extends Model
{
    use HasFactory;

    protected $fillable = [
        'location',
        'email',
        'facebook',
        'instagram',
        'telegram',
        'whatsapp',
        'youtube',
        'call'
    ];
}
