<?php

namespace Modules\Common\Entities;

use App\Traits\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;

class CustomsStatements extends Model implements HasMedia
{
    use HasFactory;
    use Media;

    protected $fillable = [
        'name',
        'amount',
        'date',
        'invoice',
        'invoice_2_percent',
        'notes'
        ];
}
