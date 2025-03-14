<?php

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Modules\Shop\Support\Enums\OrderStatus;
use Modules\Shop\Traits\Product\Finance;
use Modules\Shop\Traits\Product\Stock;
use Spatie\MediaLibrary\HasMedia;
use App\Traits\Media;

/**
 * Class Product
 * @property mixed stock
 * @property mixed options
 * @property mixed price
 * @property mixed $id
 * @package Modules\Shop\Entities
 */
class Product extends Model implements HasMedia
{
    use HasFactory;
//    use Searchable;
    use Stock;
    use Finance;
    use Media;
//    use SoftDeletes;

    /**
     * @var array $fillable
     */
    protected $fillable=[
        'id',
        'sku',
        'name',
        'source_sku',
        'short_description',
        'description',
        'documents',
        'features',
        'min_qty',
        'code',
        'price',
        'stock',
        'packageInclude',
        'datasheets',
        'meta',
        'options',
        'is_retired',
        'source',
        'barcode',
        'location',
        'listPriority',
        'maxCartAmount',
        'slug',
        'brand_id',
        'source_id',
        'replacement_item',
        'hasVariants',
        'order_qty',
        'store_available',
        'stock_available',
        'stock_location',
        'store_location'
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

    /**
     * @return mixed
     */
    protected static function newFactory(): mixed
    {
        return \Modules\Shop\Database\Factories\ProductFactory::new();
    }

    /**
     * @return string
     */
    public function searchableAs(): string
    {
        return 'mikroelektron-products';
    }

    /**
     * @return array
     */
    public function toSearchableArray(): array
    {
        return $this->only('id', 'name', 'sku','price','categories');
    }

    /**
     * @return BelongsToMany
     */
    public function relatedProducts(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_related',
            'parent_id',
            'child_id'
        );
    }

    /**
     * @return BelongsToMany
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'product_category',
            'product_id',
            'category_id'
        );
    }

    /**
     * @return BelongsToMany
     */
    public function kit(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_kit',
            'kit_id',
            'product_id'
        )->withPivot('quantity');
    }

    /**
     * @return BelongsToMany
     */
    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(
            Order::class,
            'order_products',
            'product_id',
            'order_id'
        )->withPivot('price', 'quantity', 'real_price');
    }

    /**
     * @return BelongsToMany
     */
    public function completedOrders(): BelongsToMany
    {
        return $this->orders()->where('status', OrderStatus::COMPLETED()->value);
    }

    /**
     * @param $from
     * @param $to
     * @return mixed
     */
    public function sales($from, $to): mixed
    {
        return OrderProduct::sales($this->id, $from, $to);
    }

    /**
     * @param $from
     * @param $to
     * @return mixed
     */
    public function untaxed_sales($from, $to): mixed
    {
        return OrderProduct::untaxed_sales($this->id, $from, $to);
    }

    /**
     * @param $from
     * @param $to
     * @return mixed
     */
    public function taxed_sales($from, $to): mixed
    {
        return OrderProduct::taxed_sales($this->id, $from, $to);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class,'brand_id');
    }

    public function store_location()
    {
        return $this->belongsTo(Location::class,'store_location');
    }

    public function stock_location()
    {
        return $this->belongsTo(Location::class,'stock_location');
    }

    public function source()
    {
        return $this->belongsTo(Source::class,'source_id');
    }

    public function replacement_item(): HasOne
    {
        return $this->hasOne(Product::class,'replacement_item');
    }

    public function product_replacement(): BelongsTo
    {
        return $this->belongsTo(Product::class,'replacement_item');
    }

    public function product_variants()
    {
        return $this->hasMany(ProductVariant::class,'product_id');
    }

    public function search()
    {
        return true;
    }

}
