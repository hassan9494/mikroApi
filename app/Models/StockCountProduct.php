<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountProduct extends Model
{
    protected $fillable = [
        'stock_count_id',
        'product_id',
        'store_available_expected',
        'store_available_counted',
        'store_available_difference',
        'stock_available_expected',
        'stock_available_counted',
        'stock_available_difference',
        'total_expected',
        'total_counted',
        'total_difference',
        'notes',
    ];

    protected $casts = [
        'store_available_expected' => 'integer',
        'store_available_counted' => 'integer',
        'store_available_difference' => 'integer',
        'stock_available_expected' => 'integer',
        'stock_available_counted' => 'integer',
        'stock_available_difference' => 'integer',
        'total_expected' => 'integer',
        'total_counted' => 'integer',
        'total_difference' => 'integer',
    ];

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(\Modules\Shop\Entities\Product::class);
    }

    public function calculateDifferences(): void
    {
        if ($this->store_available_counted !== null) {
            $this->store_available_difference = $this->store_available_counted - $this->store_available_expected;
        }

        if ($this->stock_available_counted !== null) {
            $this->stock_available_difference = $this->stock_available_counted - $this->stock_available_expected;
        }

        if ($this->store_available_counted !== null && $this->stock_available_counted !== null) {
            $this->total_counted = $this->store_available_counted + $this->stock_available_counted;
            $this->total_difference = $this->total_counted - $this->total_expected;
        }
    }
}
