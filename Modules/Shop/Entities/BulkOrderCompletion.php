<?php

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class BulkOrderCompletion extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'payment_method_id',
        'total_calculated_amount',
        'total_entered_amount',
        'total_calculated_shipping',
        'total_entered_shipping',
        'total_calculated_commission',
        'total_entered_commission',
        'notes',
    ];

    protected $casts = [
        'total_calculated_amount' => 'decimal:3',
        'total_entered_amount' => 'decimal:3',
        'total_calculated_shipping' => 'decimal:3',
        'total_entered_shipping' => 'decimal:3',
        'total_calculated_commission' => 'decimal:3',
        'total_entered_commission' => 'decimal:3',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'bulk_order_completion_orders')
            ->withPivot([
                'calculated_amount',
                'entered_amount',
                'calculated_shipping',
                'entered_shipping',
                'calculated_commission',
                'entered_commission',
            ])
            ->withTimestamps();
    }

    public function bulkCompletionOrders()
    {
        return $this->hasMany(BulkOrderCompletionOrder::class);
    }
}
