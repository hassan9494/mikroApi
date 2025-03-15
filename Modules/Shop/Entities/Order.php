<?php

namespace Modules\Shop\Entities;

use App\Models\User;
use App\Traits\Media;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Common\Entities\City;
use Modules\Shop\Database\Factories\OrderFactory;
use Modules\Shop\Support\Enums\OrderShippingStatus;
use Modules\Shop\Support\Enums\OrderStatus;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

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

    /**
     * @var array $fillable
     */
    protected $fillable=[
        'user_id',
        'cashier_id',
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
        )->withPivot('price', 'quantity', 'real_price');
    }

    /**
     * @return BelongsTo
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
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
     * @return BelongsTo
     */
    public function shippingProvider(): BelongsTo
    {
        return $this->belongsTo(ShippingProvider::class, 'shipping_provider_id');
    }

    /**********************************************************************************************/
    /* Extras *************************************************************************************/
    /**********************************************************************************************/
    /**
     * onSaving
     */
    public function onSaving()
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
            $couponUse = $coupon->orders;

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

}
