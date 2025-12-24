<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferOrderProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_order_id',
        'product_id',
        'from_location',
        'to_location',
        'quantity'
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    // Relationships
    public function transferOrder(): BelongsTo
    {
        return $this->belongsTo(TransferOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(\Modules\Shop\Entities\Product::class);
    }

    // Attributes
    public function getFromLocationLabelAttribute()
    {
        $labels = [
            'stock_available' => 'Stock Available',
            'store_available' => 'Store Available'
        ];
        return $labels[$this->from_location] ?? $this->from_location;
    }

    public function getToLocationLabelAttribute()
    {
        $labels = [
            'stock_available' => 'Stock Available',
            'store_available' => 'Store Available'
        ];
        return $labels[$this->to_location] ?? $this->to_location;
    }

    public function getTransferDescriptionAttribute()
    {
        return "Transfer {$this->quantity} units from {$this->from_location_label} to {$this->to_location_label}";
    }
}
