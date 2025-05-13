<?php

namespace Modules\Shop\Entities;

use App\Models\User;
use App\Traits\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;

class ReturnOrder extends Model implements HasMedia
{
    use HasFactory;
    use Media;

    protected $fillable=[
        'status',
        'notes',
        'number',
        'date',
        'order_id',
        'subtotal',
        'discount',
        'total',
        'lose',
        'extra_items',
        'is_migrated',
        'discount_percentage',
        'uuid',
        'qr_code',
        'migrated_by',
        'migrated_at',
        'migrate_error',
    ];

    protected $casts = [
        'extra_items' => 'object',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'return_order_products',
            'return_order_id',
            'product_id'
        )->withTrashed()->withPivot('price','discount', 'returned_quantity','quantity','name');
    }

    /**
     * @return BelongsTo
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }


}
