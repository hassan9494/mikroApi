<?php

namespace Modules\Shop\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Laravel\Scout\Searchable;
use Modules\Shop\Http\Resources\ProductElasticResource;
use Modules\Shop\Http\Resources\ProductResource;
use Modules\Shop\Http\Resources\ProductVariantsElasticResource;
use Modules\Shop\Http\Resources\ProductVariantsResource;
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
    use SoftDeletes;

    /**
     * @var array $fillable
     */
    protected $fillable=[
        'id',
        'sku',
        'name',
        'source_sku',
        'short_description',
        'short_description_ar',
        'casher_note',
        'description',
        'documents',
        'data_sheet',
        'features',
        'min_qty',
        'code',
        'price',
        'exchange_factor',
        'base_purchases_price',
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
        'store_location',
        'purchases_qty',
        'is_show_for_search',
        'search_factor',
        'is_color_sun',
        'colors_nick_names',
        'available',
        'featured'
    ];

    protected $attributes = [
        'options' => '{"available": true, "kit": false, "featured": false}'
    ];

    protected $casts = [
        'price' => 'object',
        'meta' => 'object',
        'shipping' => 'object',
        'datasheets' => 'object',
        'options' => 'object',
        'stock' => 'integer',
        'stock_available' => 'integer',
        'store_available' => 'integer'


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
        return env('ELASTICSEARCH_INDEX', 'test_productssss');
    }

//    /**
//     * @return array
//     */
//    public function toSearchableArray(): array
//    {
//        return $this->only('id', 'name', 'sku','price','categories');
//    }

    /**
     * @return array
     */
    public function toSearchableArray() {
        $replacement_item = Product::where('id',$this->replacement_item)->first();
        $media = $this->getMedia();
        $image = count($media) > 0 ? $media[0]->getFullUrl() : '';
        $price = $this->price ?? (object) [
                'normal_price' => 0,
                'sale_price' => 0,
                'distributor_price' => 0
            ];
        return [
            'sales' => $this->sales(null, null),
            'replacement_item' => $replacement_item ? new ProductElasticResource($replacement_item): null,
            'colors' => ProductVariantsElasticResource::collection($this->product_variants),
            'meta' => $this->meta,
            'hasVariants' => $this->hasVariants ? true : false ,
            'id' => (string) $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'slug' => $this->slug,
            'image' => $image,
            'location' => $this->location ?? '',
            'stock_location' => $this->stock_location ?? '',
            'source_sku' => $this->source_sku,
            'meta_title' => $this->meta->title ?? '',
            'meta_keywords' => $this->meta->keywords ?? '',
            'meta_description' => $this->meta->description ?? '',
            'category_slugs' => $this->categories->pluck('slug')->filter()->values()->toArray(),
            'normal_price' => (float) ($price->normal_price ?? 0),
            'sale_price' => (float) ($price->sale_price ?? 0),
            'effective_price' => (float) (($price->sale_price > 0)
                ? $price->sale_price
                : ($price->normal_price ?? 0)),
            'stock' => (int) floatval($this->stock),
            'created_at' => $this->created_at->timestamp,
            'featured' => $this->options->featured ? true: false,
            'available' => $this->options->available ? true : false,
            'is_retired' => $this->is_retired ?true : false,
            'short_description' => $this->short_description ?? '',

        ];
    }


    protected static function booted()
    {
        parent::booted();
        static::saved(function ($product) {
            try {
//                $currentVersion = Cache::get('product_search_version', 1);
//                Cache::forever('product_search_version', $currentVersion + 1);
                Cache::forget('homepage_products');
                if (config('scout.driver') === 'elasticsearch') {
                    // Use direct indexing instead of searchable()
                    $client = app('elasticsearch');
                    $params = [
                        'index' => $product->searchableAs(),
                        'id' => $product->getScoutKey(),
                        'body' => $product->toSearchableArray()
                    ];
                    $client->index($params);
                }

            } catch (\Exception $e) {
                \Log::error('Failed to sync product to Elasticsearch', [
                    'product_id' => $product->id,
                    'error' => $e->getMessage()
                ]);
            }
        });

        static::deleted(function ($product) {
            try {
//                $currentVersion = Cache::get('product_search_version', 1);
//                Cache::forever('product_search_version', $currentVersion + 1);
                Cache::forget('homepage_products');
                if (config('scout.driver') === 'elasticsearch') {
                    $client = app('elasticsearch');
                    $params = [
                        'index' => $product->searchableAs(),
                        'id' => $product->getScoutKey()
                    ];
                    $client->delete($params);
                }
            } catch (\Exception $e) {
                \Log::error('Failed to remove product from Elasticsearch', [
                    'product_id' => $product->id,
                    'error' => $e->getMessage()
                ]);
            }
        });
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
        )->withPivot('price', 'quantity', 'real_price','product_name','number','discount','is_color','color_id');
    }

    /**
     * @return BelongsToMany
     */
    public function return_orders(): BelongsToMany
    {
        return $this->belongsToMany(
            ReturnOrder::class,
            'return_order_products',
            'return_order_id',
            'product_id'
        )->withTrashed()->withPivot('price','discount', 'returned_quantity','quantity','name','main_discount');
    }

    /**
     * @return BelongsToMany
     */
    public function completedOrders(): BelongsToMany
    {
        return $this->orders()->where('status', OrderStatus::COMPLETED()->value);
    }

    /**
     * @return BelongsToMany
     */
    public function proccessingAndCompletedOrders(): BelongsToMany
    {
        return $this->orders()->whereIn('status', [OrderStatus::PROCESSING()->value,OrderStatus::COMPLETED()->value]);
    }

    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'invoice_products', 'product_id', 'invoice_id')
            ->withPivot([
                'purchases_price',
                'distributer_price',
                'quantity',
                'source_sku',
                'normal',
                'sale_price',
                'product_name',
                'base_purchases_price',
                'exchange_factor',
                'stock_available_qty',
                'store_available_qty'

            ]);
    }

    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class, 'coupon_products', 'product_id', 'coupon_id')
            ->withPivot([
                'product_name'
            ]);
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
    public function allSales($from, $to): mixed
    {
        return OrderProduct::allSales($this->id, $from, $to);
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

    public function parent()
    {
        return $this->hasOne(ProductVariant::class,'color_id');
    }

    public function search()
    {
        return true;
    }

    // In Product.php, update the getStockAttribute method:
// public function getStockAttribute()
// {
//     // Get raw values from database
//     $stockAvailable = $this->attributes['stock_available'] ?? 0;
//     $storeAvailable = $this->attributes['store_available'] ?? 0;
//     $rawStock = $this->attributes['stock'] ?? 0;

//     // SPECIAL CASE: If both available are 0 or null, and raw stock has value
//     // Put all stock in store_available
//     if (($stockAvailable == 0 || is_null($stockAvailable)) &&
//         ($storeAvailable == 0 || is_null($storeAvailable)) &&
//         $rawStock > 0) {
//         return $rawStock;
//     }

//     // Otherwise return the sum of available stock
//     return ($stockAvailable ?? 0) + ($storeAvailable ?? 0);
// }
    public function validateAndAdjustStockDistribution()
    {
        // Get raw values from database
        $currentStock = (int) $this->getAttributes()['stock'] ?? 0;
        $stockAvailable = (int) $this->stock_available ?? 0;
        $storeAvailable = (int) $this->store_available ?? 0;

        // Calculate current sum of available
        $currentAvailableSum = $stockAvailable + $storeAvailable;

        // If sum doesn't match stock, adjust distribution
        if ($currentAvailableSum != $currentStock) {
            // Case 1: If both available are 0 and stock > 0, put all in store_available
            if ($stockAvailable == 0 && $storeAvailable == 0 && $currentStock > 0) {
                $this->store_available = $currentStock;
                $this->stock_available = 0;
            }
            // Case 2: If sum is less than stock, add difference to store_available
            elseif ($currentAvailableSum < $currentStock) {
                $difference = $currentStock - $currentAvailableSum;
                $this->store_available += $difference;
            }
            // Case 3: If sum is more than stock, reduce from store_available first
            elseif ($currentAvailableSum > $currentStock) {
                $excess = $currentAvailableSum - $currentStock;
                if ($this->store_available >= $excess) {
                    $this->store_available -= $excess;
                } else {
                    // If store_available is insufficient, take from both
                    $remaining = $excess - $this->store_available;
                    $this->store_available = 0;
                    $this->stock_available = max(0, $this->stock_available - $remaining);
                }
            }
        }
    }
    public function updateDistributionFromStockChange($newStock, $reduce = true)
    {
        $oldStock = $this->getAttributes()['stock'] ?? 0;
        $stockAvailable = $this->stock_available ?? 0;
        $storeAvailable = $this->store_available ?? 0;

        if ($reduce) {
            // Reducing stock (order completed)
            $quantityToReduce = $oldStock - $newStock;

            // Reduce from store_available first
            if ($storeAvailable >= $quantityToReduce) {
                $this->store_available = $storeAvailable - $quantityToReduce;
            } else {
                // If store_available is insufficient, take from both
                $remaining = $quantityToReduce - $storeAvailable;
                $this->store_available = 0;
                $this->stock_available = max(0, $stockAvailable - $remaining);
            }
        } else {
            // Increasing stock (order cancelled/returned)
            $quantityToAdd = $newStock - $oldStock;

            // Add to store_available by default
            $this->store_available += $quantityToAdd;
        }

        // Update the stock field
        $this->stock = $newStock;
    }



}
