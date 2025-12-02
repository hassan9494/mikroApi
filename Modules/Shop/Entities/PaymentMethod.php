<?php

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Common\Entities\Receipt;

class PaymentMethod extends Model
{
    use SoftDeletes;

    protected $fillable = ['name','commission_type','commission','commission_range'];

    protected $casts = [
        'commission_range' => 'array'
    ];

    public function transaction()
    {
        return $this->hasMany(Transaction::class,'payment_method_id');
    }




    public function receipts()
    {
        return $this->hasMany(Receipt::class,'payment_method_id');
    }
}
