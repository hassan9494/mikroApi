<?php

namespace Modules\Shop\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TaxExempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'email',
        'identity_number_type',
        'identity_number',
        'tax_exempt',
        'tax_zero',
        'exemption_expiration_date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }
}
