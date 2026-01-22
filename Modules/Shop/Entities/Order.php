<?php

namespace Modules\Shop\Entities;

use App\Models\User;
use App\Services\CouponService;
use App\Traits\Media;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Common\Entities\City;
use Modules\Shop\Database\Factories\OrderFactory;
use Modules\Shop\Support\Enums\OrderShippingStatus;
use Modules\Shop\Support\Enums\OrderStatus;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Traits\OrderHistoryTrait;


/**
 * Class Order
 * @property mixed shipping
 * @property mixed total
 * @property \Illuminate\Support\Carbon taxed_at
 * @property mixed status
 * @property int tax_number
 * @property mixed coupon
 * @property mixed isPending
 * @property mixed isCompleted
 * @property mixed id
 * @property mixed options
 * @property float|int subtotal
 * @property mixed discount
 * @property Carbon completed_at
 * @property mixed products
 * @property float|int|mixed profit
 * @property mixed coupon_id
 * @package Modules\Shop\Entities
 */
class Order extends Model implements HasMedia
{

    use HasFactory;
    use Media;
    use OrderHistoryTrait;

    /**
     * @var array $fillable
     */
    protected $fillable=[
        'user_id',
        'cashier_id',
        'tax_exempt_id',
        'shipping_provider_id',
        'coupon_id',
        'customer',
        'status',
        'notes',
        'invoice_notes',
        'subtotal',
        'discount',
        'total',
        'profit',
        'options',
        'tax_number',
        'completed_at',
        'taxed_at',
        'city_id',
        'shipping',
        'extra_items',
        'identity_number_type',
        'customer_identity_number',
        'is_migrated',
        'discount_percentage',
        'uuid',
        'qr_code',
        'fatora_status',
        'completed_by',
        'migrated_by',
        'migrated_at',
        'migrate_error',
        'pending',
    ];

    protected $casts = [
        'shipping' => 'object',
        'customer' => 'object',
        'options' => 'object',
        'extra_items' => 'object',
    ];

    protected $appends = [
        'number',
        'short_address',
        'is_pending',
        'is_completed',
        'tax_amount',
        'coupon_discount',
    ];

    protected $attributes = [
        'options' => '{"taxed": false, "tax_exempt": false, "dept": false, "price_offer": false, "tax_zero": false}'
    ];

    /**
     * @return OrderFactory
     */
    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }

    /**********************************************************************************************/
    /* Mutators ***********************************************************************************/
    /**********************************************************************************************/


    /**********************************************************************************************/
    /* Accessors **********************************************************************************/
    /**********************************************************************************************/
    /**
     * @return string
     */
    public function getNumberAttribute(): string
    {
        return sprintf("%05d", $this->id);
    }

    /**
     * @return string
     */
    public function getShortAddressAttribute(): string
    {
        return $this->shipping->address ?? '';
    }

    /**
     * @return bool
     */
    public function getIsPendingAttribute(): bool
    {
        return $this->status == OrderStatus::PENDING()->value;
    }

    /**
     * @return mixed
     */
    public function getTaxAmountAttribute(): mixed
    {
        if ($this->options?->tax_exempt) return 0;
        $totalAfterDiscount = $this->subtotal - $this->discount;
        $realPrice = $totalAfterDiscount / 1.16;
        return $totalAfterDiscount - $realPrice;
    }

    /**
     * @return bool
     */
    public function getIsCompletedAttribute(): bool
    {
        return $this->status == OrderStatus::COMPLETED()->value;
    }

    /**
     * @return bool
     */
    public function getCouponDiscountAttribute(): bool
    {
        return $this->coupon?->calc($this->subtotal) ?? 0;
    }

    /**********************************************************************************************/
    /* Relations **********************************************************************************/
    /**********************************************************************************************/
    /**
     * @return BelongsToMany
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'order_products',
            'order_id',
            'product_id'
        )->withTrashed()->withPivot('price', 'quantity', 'real_price','product_name','number','discount','is_color','color_id');
    }

    /**
     * @return BelongsTo
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }

    public function taxExempt()
    {
        return $this->belongsTo(TaxExempt::class,'tax_exempt_id');
    }

    /**
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * @return BelongsTo
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    /**
     * @return HasMany
     */
    public function returnOrders(): BelongsTo
    {
        return $this->hasMany(ReturnOrder::class, 'order_id');
    }

    /**
     * @return BelongsTo
     */
    public function shippingProvider(): BelongsTo
    {
        return $this->belongsTo(ShippingProvider::class, 'shipping_provider_id');
    }


//    public function transactions()
//    {
//        return $this->hasMany(Transaction::class);
//    }

    public function transactions()
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }

    /**********************************************************************************************/
    /* Extras *************************************************************************************/
    /**********************************************************************************************/
    /**
     * onSaving
     */
    public function oldonSaving()
    {
        $this->calcSubtotal();
        $this->calcTotal();
        // Calc Order Profit.
        $this->calcProfit();

        // If order is taxed and doesn't have a tax number, and it's not pending, then generate a serial tax number.
        if ( !$this->tax_number && $this?->options?->taxed)
        {
            $lastorder = Order::whereNotNull('tax_number')->max('tax_number');
            $this->tax_number = $lastorder ? $lastorder +1  : 13185;
            $this->taxed_at = now();
        }

        // If order has a tax number, force the taxed option.
        $this->options->taxed = !!$this->tax_number;

        // Set completed_at when order completed and completed date is null.
        $this->completed_at = $this->isCompleted && !$this->completed_at ? Carbon::now() : $this->completed_at;

        if ($this->coupon_id){
            $coupon = Coupon::find($this->coupon_id);
            $couponUse = $coupon?->orders;

            if ($coupon && $coupon->valid && (now() >= $coupon->start_at && now() <= $coupon->end_at) && $coupon->count >= 0) {
                if ($coupon->is_percentage) {
                    $this->discount = $this->subtotal * ($coupon->amount / 100);
                } else {
                    $this->discount = $coupon->amount;
                }
            }

        }else{
//            $this->discount = 0;
        }
    }
    // In Order model, update the onSaving method:

    public function onSaving()
    {
        $this->calcSubtotal();
        $this->calcTotal();
        $this->calcProfit();

        // If order is taxed and doesn't have a tax number, and it's not pending, then generate a serial tax number.
        if ( !$this->tax_number && $this?->options?->taxed)
    {
        $lastorder = Order::whereNotNull('tax_number')->max('tax_number');
        $this->tax_number = $lastorder ? $lastorder +1  : 13185;
        $this->taxed_at = now();
    }

    $this->options->taxed = !!$this->tax_number;

    $this->completed_at = $this->isCompleted && !$this->completed_at ? Carbon::now() : $this->completed_at;

    // Handle coupon discount using the service
//    if ($this->coupon_id) {
//
//        $coupon = Coupon::find($this->coupon_id);
//
//        if ($coupon) {
//            $couponService = app(CouponService::class);
//            $result = $couponService->applyToOrder($coupon, $this);
//
//            if ($result['valid']) {
//                $this->discount = $result['discount'];
//
//                // Also update discount percentage for consistency
//                $this->discount_percentage = $this->subtotal > 0 ? ($this->discount / $this->subtotal) * 100 : 0;
//            } else {
//
//                // Coupon is invalid, remove it and reset discount
//                $this->coupon_id = null;
//                // Keep manual discount if any, otherwise reset to 0
//                if ($this->discount > 0 && !$this->getOriginal('discount')) {
//                    $this->discount = 0;
//                    $this->discount_percentage = 0;
//                }
//                // You might want to log this or show a message
//            }
//
//        }
//    }
//    else {
//
//        // No coupon, but we might have manual discount - ensure percentage is calculated
//        if ($this->subtotal > 0 && $this->discount > 0) {
//            $this->discount_percentage = ($this->discount / $this->subtotal) * 100;
//        } else {
//            $this->discount_percentage = 0;
//        }
//    }

}

    /**
     * Calc Order Profit.
     */
    public function calcSubtotal()
    {
        $subtotal = 0;

        // Reload products from disk
        $this->unsetRelation('products');

        foreach ($this->products as $product)
        {
            $subtotal += $product->calcPrice(
                $product->pivot->quantity,
                $product->pivot->price
            );
        }

        foreach (($this->extra_items ?? []) as $item)
        {
            $subtotal += $item->quantity * $item->price;
        }

        $this->subtotal = $subtotal;
    }

    /**
     * Calc Order Profit.
     */
    public function calcTotal()
    {
        $shippingCost = ($this->shipping?->free ?? false) ? 0 : $this->shipping?->cost  ?? 0;
        $this->total = $this->subtotal + $shippingCost - $this->discount;
    }

    /**
     * Calc Order Profit.
     */
    public function calcProfit()
    {
        $shippingCost = 0;
        if ($this->shipping?->free ?? false) {
            $shippingCost = $this->shipping?->cost  ?? 0;
        }

        $totalRealPrice = 0;
        foreach ($this->products as $product)
        {
            $totalRealPrice += $product->pivot->real_price * $product->pivot->quantity;
        }
        $this->profit = $this->subtotal - $totalRealPrice - $this->discount - $shippingCost;
    }

    public function histories()
    {
        return $this->hasMany(OrderHistory::class)->orderBy('created_at', 'desc');
    }

// Add this method to track nested changes in JSON fields
    public function trackNestedChanges($parentField, $oldData, $newData)
    {
        // Handle non-array data by treating as single field change
        if (!is_array($oldData) || !is_array($newData)) {
            if ($oldData != $newData) {
                $this->recordFieldChange(
                    $parentField,
                    $oldData,
                    $newData,
                    'updated'
                );
            }
            return;
        }

        // Normalize both arrays before comparison
        $oldData = json_decode($this->normalizeJsonData($oldData), true);
        $newData = json_decode($this->normalizeJsonData($newData), true);

        $allKeys = array_unique(array_merge(array_keys($oldData), array_keys($newData)));

        foreach ($allKeys as $key) {
            $oldValue = $oldData[$key] ?? null;
            $newValue = $newData[$key] ?? null;

            if ($oldValue != $newValue) {
                $this->recordFieldChange(
                    $parentField . '.' . $key,
                    $oldValue,
                    $newValue,
                    'updated'
                );
            }
        }
    }
// Also update the updating event to handle object conversion:
// Add to the Order model
// Add to the Order model
    protected static function boot()
    {
        parent::boot();

        static::updating(function ($order) {
            $original = $order->getOriginal();

            // Check each field for changes
            foreach ($order->getDirty() as $key => $value) {
                // Skip timestamps and internal fields
                if (in_array($key, ['updated_at', 'created_at', 'profit', 'subtotal', 'total'])) {
                    continue;
                }

                $oldValue = $original[$key] ?? null;
                $newValue = $value;

                // For JSON fields, handle object conversion
                if (in_array($key, ['customer', 'shipping', 'options', 'extra_items'])) {
                    // Convert objects to arrays if needed
                    if (is_object($oldValue)) {
                        $oldValue = (array) $oldValue;
                    }
                    if (is_object($newValue)) {
                        $newValue = (array) $newValue;
                    }

                    // Normalize JSON data before comparison
                    $normalizedOld = $order->normalizeJsonData($oldValue);
                    $normalizedNew = $order->normalizeJsonData($newValue);

                    // Only track changes if the normalized data is different
                    if (!$order->isJsonDataEqual($normalizedOld, $normalizedNew)) {
                        $order->trackNestedChanges($key, $oldValue, $newValue);
                    }
                    // Track changes in nested fields
                    // $order->trackNestedChanges($key, $oldValue, $newValue);
                } else {
                    // Track simple field changes
                    if ($oldValue != $newValue) {
                        $order->recordFieldChange($key, $oldValue, $newValue, 'updated');
                    }
                }
            }
        });
    }


// Add this method to record individual field changes
    protected function recordFieldChange($field, $oldValue, $newValue, $action = 'updated', $notes = null)
    {
        // Normalize JSON fields before comparison
        if (in_array($field, ['customer', 'shipping', 'options', 'extra_items'])) {
            $normalizedOld = $this->normalizeJsonData($oldValue);
            $normalizedNew = $this->normalizeJsonData($newValue);

            // If the normalized JSON is the same, don't record the change
            if ($this->isJsonDataEqual($normalizedOld, $normalizedNew)) {
                return;
            }

            // Use normalized values for storage
            $oldValue = $normalizedOld;
            $newValue = $normalizedNew;
        }
        if ($field === 'shipping') {
            $oldValue = $this->normalizeShippingData($oldValue);
            $newValue = $this->normalizeShippingData($newValue);
        }
        // Convert arrays/objects to JSON strings for storage
        if (is_array($oldValue) || is_object($oldValue)) {
            $oldValue = json_encode($oldValue);
        }

        if (is_array($newValue) || is_object($newValue)) {
            $newValue = json_encode($newValue);
        }

        // For product quantity changes, add product name to notes
        if (strpos($field, 'products.') === 0 && strpos($field, '.quantity') !== false) {
            $productId = str_replace(['products.', '.quantity'], '', $field);
            $product = Product::withTrashed()->find($productId); // Include soft-deleted products

            if ($product) {
                if ($oldValue == 0) {
                    $action = 'product_added';
                    $notes = "Product: {$product->name} was added with quantity {$newValue}";
                } elseif ($newValue == 0) {
                    $action = 'product_removed';
                    $notes = "Product: {$product->name} was removed";
                } else {
                    $action = 'quantity_updated';
                    $notes = "Product: {$product->name} quantity changed from {$oldValue} to {$newValue}";
                }
            } else {
                // Fallback if product not found
                if ($oldValue == 0) {
                    $action = 'product_added';
                    $notes = "Product ID: {$productId} was added with quantity {$newValue}";
                } elseif ($newValue == 0) {
                    $action = 'product_removed';
                    $notes = "Product ID: {$productId} was removed";
                } else {
                    $action = 'quantity_updated';
                    $notes = "Product ID: {$productId} quantity changed from {$oldValue} to {$newValue}";
                }
            }
        }

        // Create a history record
        OrderHistory::create([
            'order_id' => $this->id,
            'field' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'action' => $action,
            'notes' => $notes,
            'user_id' => auth()->id() ?? null,
        ]);
    }

    private function normalizeShippingData($shippingData)
    {
        if (is_string($shippingData)) {
            $shippingData = json_decode($shippingData, true);
        }

        if (is_array($shippingData)) {
            // Ensure cost is always a string
            if (isset($shippingData['cost'])) {
                $shippingData['cost'] = (string) $shippingData['cost'];
            }

            // Ensure all expected fields are present
            $defaults = [
                'city' => '',
                'address' => '',
                'cost' => '0',
                'status' => 'WAITING',
                'free' => false
            ];

            $shippingData = array_merge($defaults, $shippingData);
        }

        return $shippingData;
    }
// Update the recordStatusChange method to use field tracking
    public function recordStatusChange($oldStatus, $newStatus)
    {
        $this->recordFieldChange('status', $oldStatus, $newStatus, 'status_changed');


    }

// Add these methods to your Order model

    /**
     * Track product quantity changes
     */
    public function trackProductQuantityChanges($oldProducts, $newProducts)
    {
        foreach ($oldProducts as $productId => $oldProduct) {
            $newProduct = $newProducts[$productId] ?? null;

            if ($newProduct && $oldProduct['quantity'] != $newProduct['quantity']) {
                $product = Product::find($productId);
                $productName = $product ? $product->name : "Product ID: $productId";

                $this->recordFieldChange(
                    "products.$productId.quantity",
                    $oldProduct['quantity'],
                    $newProduct['quantity'],
                    'quantity_updated',
                    "Product: $productName"
                );
            }
        }

        // Check for newly added products
        foreach ($newProducts as $productId => $newProduct) {
            if (!isset($oldProducts[$productId])) {
                $product = Product::find($productId);
                $productName = $product ? $product->name : "Product ID: $productId";

                $this->recordFieldChange(
                    "products.$productId.quantity",
                    0,
                    $newProduct['quantity'],
                    'product_added',
                    "Product: $productName"
                );
            }
        }

        // Check for removed products
        foreach ($oldProducts as $productId => $oldProduct) {
            if (!isset($newProducts[$productId])) {
                $product = Product::find($productId);
                $productName = $product ? $product->name : "Product ID: $productId";

                $this->recordFieldChange(
                    "products.$productId.quantity",
                    $oldProduct['quantity'],
                    0,
                    'product_removed',
                    "Product: $productName"
                );
            }
        }
    }

    /**
     * Record print action
     */
// Update the recordPrintAction method
    public function recordPrintAction()
    {
        $user = auth()->user();

        OrderHistory::create([
            'order_id' => $this->id,
            'action' => 'printed',
            'notes' => 'Order was printed',
            'details' => json_encode([
                'printed_by' => $user ? $user->name : 'Unknown',
                'printed_at' => now()->toDateTimeString(),
                'print_type' => 'invoice' // You can customize this based on the print type
            ]),
            'user_id' => $user ? $user->id : null,
        ]);
    }
    /**
     * Normalize JSON data by sorting keys alphabetically
     */
    protected function normalizeJsonData($data)
    {
        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        if (is_array($data)) {
            ksort($data);
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $data[$key] = $this->normalizeJsonData($value);
                }
            }
        }

        return json_encode($data);
    }

    /**
     * Check if two JSON strings represent the same data
     */
    protected function isJsonDataEqual($json1, $json2)
    {
        $data1 = json_decode($json1, true);
        $data2 = json_decode($json2, true);

        return $data1 === $data2;
    }



}
