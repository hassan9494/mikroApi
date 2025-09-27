<?php

namespace Modules\Shop\Entities;

use App\Traits\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Shop\Traits\Product\Finance;
use Modules\Shop\Traits\Product\Stock;
use Spatie\MediaLibrary\HasMedia;


/**
 * Class ProductVariant
 * @property mixed stock
 * @property mixed options
 * @property mixed price
 * @property mixed $id
 * @package Modules\Shop\Entities
 */
class ProductVariant extends Model implements HasMedia
{
    use HasFactory;
    use Stock;
    use Finance;
    use Media;

    /**
     * @var array $fillable
     */
    protected $fillable=[
        'id',
        'name',
        'short_description',
        'min_qty',
        'price',
        'stock',
        'options',
        'is_retired',
        'source',
        'barcode',
        'location',
        'listPriority',
        'maxCartAmount',
        'product_id',
        'color_id'
    ];

    protected $attributes = [
        'options' => '{"available": true, "kit": false, "featured": false}'
    ];

    protected $casts = [
        'price' => 'object',
        'meta' => 'object',
        'shipping' => 'object',
        'datasheets' => 'object',
        'options' => 'object'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class,'product_id');
    }

    public function variant()
    {
        return $this->belongsTo(Product::class,'color_id');
    }
}
