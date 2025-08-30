<?php

namespace Modules\Shop\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;
use App\Traits\Media;

class TaxExempt extends Model implements HasMedia
{
    use Media;

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'email',
        'identity_number_type',
        'identity_number',
        'tax_exempt',
        'tax_zero',
        'exemption_expiration_date'
    ];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }
}
