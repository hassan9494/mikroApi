<?php

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class TransferOrderProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_order_id',
        'product_id',
        'from_location',
        'to_location',
        'quantity',
        'stock_before',
        'stock_after',
        'stock_available_before',
        'stock_available_after',
        'store_available_before',
        'store_available_after'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'stock_before' => 'integer',
        'stock_after' => 'integer',
        'stock_available_before' => 'integer',
        'stock_available_after' => 'integer',
        'store_available_before' => 'integer',
        'store_available_after' => 'integer',
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

    public function getStockImpactAttribute()
    {
        return [
            'stock_before' => $this->stock_before,
            'stock_after' => $this->stock_after,
            'stock_available_before' => $this->stock_available_before,
            'stock_available_after' => $this->stock_available_after,
            'store_available_before' => $this->store_available_before,
            'store_available_after' => $this->store_available_after,
        ];
    }
}
