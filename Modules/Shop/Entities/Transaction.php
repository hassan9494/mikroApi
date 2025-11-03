<?php

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;

    protected $fillable = ['transaction_id','note','type','amount','commission','shipping','total_amount'
    ,'order_id','payment_method_id'];


    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class,'payment_method_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class,'order_id');
    }
}
