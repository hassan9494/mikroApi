<?php

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class BulkOrderCompletionOrder extends Pivot
{
    protected $table = 'bulk_order_completion_orders';

    protected $fillable = [
        'bulk_order_completion_id',
        'order_id',
        'calculated_amount',
        'entered_amount',
        'calculated_shipping',
        'entered_shipping',
        'calculated_commission',
        'entered_commission',
    ];

    protected $casts = [
        'calculated_amount' => 'decimal:3',
        'entered_amount' => 'decimal:3',
        'calculated_shipping' => 'decimal:3',
        'entered_shipping' => 'decimal:3',
        'calculated_commission' => 'decimal:3',
        'entered_commission' => 'decimal:3',
    ];

    public function bulkCompletion()
    {
        return $this->belongsTo(BulkOrderCompletion::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
