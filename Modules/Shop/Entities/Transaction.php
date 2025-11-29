<?php

namespace Modules\Shop\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Common\Entities\Receipt;

class Transaction extends Model
{
    use SoftDeletes;

    protected $fillable = ['transaction_id','note','type','amount','commission','shipping','total_amount'
    ,'order_id','payment_method_id','created_by','updated_by','return_order_id'];


    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class,'payment_method_id');
    }


    public function receipt()
    {
        return $this->hasOne(Receipt::class,'transaction_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class,'order_id');
    }

    public function return_order()
    {
        return $this->belongsTo(ReturnOrder::class,'return_order_id');
    }

    public function createdUser()
    {
        return $this->belongsTo(User::class,'created_by');
    }

    public function updatedUser()
    {
        return $this->belongsTo(User::class,'updated_by');
    }
}
