<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountHistory extends Model
{
    protected $table = 'stock_count_histories';

    protected $fillable = [
        'stock_count_id',
        'stock_count_product_id',
        'user_id',
        'action',
        'field',
        'old_value',
        'new_value',
        'notes',
    ];

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function stockCountProduct(): BelongsTo
    {
        return $this->belongsTo(StockCountProduct::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
