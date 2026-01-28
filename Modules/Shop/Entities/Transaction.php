<?php

namespace Modules\Shop\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Common\Entities\ProjectReceipt;
use Modules\Common\Entities\Receipt;

class Transaction extends Model
{
    use SoftDeletes;

    protected $fillable = ['transaction_id','note','type','amount','commission','shipping','total_amount'
    ,'order_id','payment_method_id','created_by','updated_by','deleted_by','return_order_id','created_at'
    , 'transactionable_id','transactionable_type',];


    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class,'payment_method_id');
    }


    public function receipt()
    {
        return $this->hasOne(Receipt::class,'transaction_id');
    }


    public function projectReceipt()
    {
        return $this->hasOne(ProjectReceipt::class,'transaction_id');
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

    public function deletedUser()
    {
        return $this->belongsTo(User::class,'deleted_by');
    }

    /**
     * NEW: Polymorphic relationship
     */
    public function transactionable()
    {
        return $this->morphTo();
    }

    /**
     * Helper method to get the type name
     */
    public function getTransactionableTypeNameAttribute()
    {
        if (!$this->transactionable_type) {
            return null;
        }

        // Extract just the class name without namespace
        $class = class_basename($this->transactionable_type);

        // Convert to readable format (e.g., "App\Models\Order" becomes "Order")
        return str_replace('_', ' ', ucfirst(strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $class))));
    }
}
