<?php

namespace Modules\Shop\Repositories\Order;

use App\Models\User;
use App\Repositories\Base\EloquentRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Shop\Entities\Address;
use Modules\Shop\Entities\Coupon;
use Modules\Shop\Entities\Order;
use Modules\Common\Repositories\City\CityRepositoryInterface;
use Modules\Shop\Entities\ProductVariant;
use Modules\Shop\Entities\Setting;
use Modules\Shop\Repositories\Coupon\CouponRepositoryInterface;
use Modules\Shop\Repositories\Product\ProductRepositoryInterface;
use Modules\Shop\Support\Enums\OrderStatus;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use App\Traits\OrderHistoryTrait;

/**
 * Class OrderRepository
 * @package Modules\Shop\Repositories\Order
 */
class OrderRepository extends EloquentRepository implements OrderRepositoryInterface
{

    /**
     * @var ProductRepositoryInterface $products
     */
    protected ProductRepositoryInterface $products;

    /**
     * @var CityRepositoryInterface $cities
     */
    protected CityRepositoryInterface $cities;

    /**
     * @var CouponRepositoryInterface $coupons
     */
    protected CouponRepositoryInterface $coupons;

    /**
     * OrderRepository constructor.
     * @param Order $model
     * @param ProductRepositoryInterface $products
     * @param CityRepositoryInterface $cities
     * @param CouponRepositoryInterface $coupons
     */
    public function __construct(
        Order $model,
        ProductRepositoryInterface $products,
        CityRepositoryInterface $cities,
        CouponRepositoryInterface $coupons
    )
    {
        parent::__construct($model);
        $this->products = $products;
        $this->cities = $cities;
        $this->coupons = $coupons;
    }

    /**
     * @param array $data
     * @return Order
     */
    public function makeByGuest(array $data): Order
    {
        $city = $this->cities->findOrFail($data['city_id']);
        $cart = $this->prepareUserProducts($data['products']);
        if ($city->id == 2){
            $isFreeShipping = true;
            $shippingCost = 0;

            $data['shipping']['cost'] = $shippingCost;
            $data['shipping']['free'] = $isFreeShipping; // Set free flag
            $data['shipping']['city'] = $city->name;
            $data['shipping']['status'] = 'WAITING';
        }else{
            $shippingLimit = Setting::find(2)->value;
            $isFreeShipping = $cart['subtotal'] >= $shippingLimit;
            $shippingCost = $city->shipping_cost;

            $data['shipping']['cost'] = $shippingCost;
            $data['shipping']['free'] = $isFreeShipping; // Set free flag
            $data['shipping']['city'] = $city->name;
            $data['shipping']['status'] = 'WAITING';
        }

        $data['uuid'] = Str::uuid();

        // Validate coupon before creating order
        if (isset($data['coupon_id']) && $data['coupon_id']) {
            $this->validateCoupon($data['coupon_id'], null, $data['products'] ?? []);
        }

        $order = $this->model->create($data);
        $order->products()->attach($cart['products']);

        return $this->update($order->id, []);
    }

    /**
     * @param array $data
     * @param Address $address
     * @param User $user
     * @return Order
     */
    public function makeByUser(array $data, Address $address, $user): Order
    {

        $cart = $this->prepareUserProducts($data['products'], $user,$data['coupon_id']);
        $data['discount'] = $cart['finalDiscount'];

        if ($address->city_id == 2){
            $data['shipping'] = $address->shipping;
            $data['shipping']['cost'] = 0;
            $data['shipping']['free'] = true; // Set free flag
        }else{
            $shippingLimit = Setting::find(2)->value;
            // Apply free shipping if subtotal >= 20
            $isFreeShipping = $cart['subtotal'] >= $shippingLimit;
            $shippingCost =  $address->shipping['cost'];
            $data['shipping'] = $address->shipping;
            $data['shipping']['cost'] = $shippingCost;
            $data['shipping']['free'] = $isFreeShipping; // Set free flag
        }

        $data['customer'] = $address->customer;
        $data['city_id'] = $address->city_id;
        $data['uuid'] = Str::uuid();

        // Validate coupon before creating order
        if (isset($data['coupon_id']) && $data['coupon_id']) {
            $this->validateCoupon($data['coupon_id'], $user, $data['products'] ?? []);
        }
//        dd($data['coupon_id']);
        $order = $this->model->create($data);

        $order->products()->attach($cart['products']);

        return $this->update($order->id, []);
    }

    /**
     * @inheritdoc
     */
    public function autocomplete($q, $limit = 20)
    {
        $query = $this->model
            ->whereNotNull('tax_number')
            ->where(function($query) use ($q) {
                $query->where('tax_number', 'LIKE', '%'.$q.'%')
                    ->orWhere('id', 'LIKE', '%'.$q.'%');
            })
            ->limit($limit);

        return $query->get();
    }

    /**
     * @param array $data
     * @return Order
     */
    public function make(array $data): Order
    {
        if ($data['city_id'] ?? false) {
            $city = $this->cities->findOrFail($data['city_id']);
            $data['shipping']['city'] = $city->name;
        }

        $cart = $this->prepareCartProducts($data['products'] ?? [], null, $data['options']);
        $data['uuid'] = Str::uuid();

        // Validate coupon before creating order
        if (isset($data['coupon_id']) && $data['coupon_id']) {
            $user = isset($data['user_id']) ? User::find($data['user_id']) : null;
            $this->validateCoupon($data['coupon_id'], $user, $data['products'] ?? []);
        }

        $order = $this->model->create($data);
        $order->products()->attach($cart['products']);

        return $order;
    }

    /**
     * @param $id
     * @param $data
     * @param bool $checkStock
     * @return mixed
     */
    public function saveOrder($id, $data, bool $checkStock = true)
    {
        $model = $this->findOrFail($id);

        // Validate coupon before updating order
        if (isset($data['coupon_id']) && $data['coupon_id']) {
            $user = $model->user;
            $this->validateCouponForEdit($data['coupon_id'], $user, $data['products'] ?? []);
        }

        if (isset($data['shipping']) && is_array($data['shipping'])) {
            $currentShipping = (array) $model->shipping;

            // Always preserve the city field from existing data
            if (isset($currentShipping['city'])) {
                $data['shipping']['city'] = $currentShipping['city'];
            }

            // Ensure cost is always stored as string for consistency
            if (isset($data['shipping']['cost'])) {
                $data['shipping']['cost'] = (string) $data['shipping']['cost'];
            }
        }

        $oldProducts = [];
        foreach ($model->products as $product) {
            $oldProducts[$product->id] = [
                'quantity' => $product->pivot->quantity,
                'price' => $product->pivot->price
            ];
        }

        if ($model->isPending) {
            // If order is in PENDING status, then we can upgrade products and subtotal according to products
            $cart = $this->prepareCartProducts($data['products'] ?? [], $model);
            // must be before update, see onSaving inside Order model.
            $model->products()->sync($cart['products']);
        }

        if ($model->options->taxed == true){
            $data['options']['taxed'] = true;
        }elseif ($model->tax_number !== null){
            $data['options']['taxed'] = true;
        }

        $model->update($data);

        if ($model->isPending && isset($cart)) {
            $newProducts = $cart['products'];
            $model->trackProductQuantityChanges($oldProducts, $newProducts);
        }

        return $model;
    }

    /**
     * Validate coupon with comprehensive rules
     */
    private function validateCoupon($couponId, $user, array $products = []): void
    {
        $coupon = Coupon::find($couponId);

        if (!$coupon) {
            throw new BadRequestException('Coupon not found');
        }

        // Check basic validity
        if (!$coupon->active) {
            throw new BadRequestException('Coupon is not active');
        }

        // Check dates
        $now = now();
        if ($coupon->start_at && $coupon->start_at->gt($now)) {
            throw new BadRequestException('Coupon has not started yet');
        }
        if ($coupon->end_at && $coupon->end_at->lt($now)) {
            throw new BadRequestException('Coupon has expired');
        }

        // Check global usage limit
        if ($coupon->apply_count && $coupon->use_count >= $coupon->count) {
            throw new BadRequestException('Coupon usage limit exceeded');
        }

        // Check per-user usage limit
        if ($user && $coupon->apply_count_per_user) {
            $userUsage = $this->getUserCouponUsage($coupon, $user);
            if ($userUsage >= $coupon->count_per_user) {
                throw new BadRequestException('You have exceeded your usage limit for this coupon');
            }
        }
    }
    private function validateCouponForEdit($couponId, $user, array $products = []): void
    {
        $coupon = Coupon::find($couponId);

        if (!$coupon) {
            throw new BadRequestException('Coupon not found');
        }

        // Check basic validity
        if (!$coupon->active) {
            throw new BadRequestException('Coupon is not active');
        }

        // Check dates
        $now = now();
        if ($coupon->start_at && $coupon->start_at->gt($now)) {
            throw new BadRequestException('Coupon has not started yet');
        }
        if ($coupon->end_at && $coupon->end_at->lt($now)) {
            throw new BadRequestException('Coupon has expired');
        }

        // Check global usage limit
        if ($coupon->apply_count && $coupon->use_count > $coupon->count) {
            throw new BadRequestException('Coupon usage limit exceeded');
        }

        // Check per-user usage limit
        if ($user && $coupon->apply_count_per_user) {
            $userUsage = $this->getUserCouponUsage($coupon, $user);
            if ($userUsage > $coupon->count_per_user) {
                throw new BadRequestException('You have exceeded your usage limit for this coupon');
            }
        }
    }

    /**
     * Get user's coupon usage count
     */
    private function getUserCouponUsage(Coupon $coupon, User $user): int
    {
        // Count from coupon_users pivot table
        $pivotUsage = $coupon->users()->where('user_id', $user->id)->count();

        // Also count from orders (for existing orders)
        $orderUsage = Order::where('user_id', $user->id)
            ->where('coupon_id', $coupon->id)
            ->count();

        return max($pivotUsage, $orderUsage);
    }

    /**
     * @param $id
     * @param $status
     * @return mixed
     */
    public function status($id, $status): mixed
    {
        $status = $status ?? OrderStatus::PENDING()->value;

        $order = $this->findOrFail($id, ['products']);

        $options = [];

        if ($status == 'PROCESSING' || $status == 'COMPLETED') {
            foreach ($order->options as $key => $value) {
                if ($key == 'price_offer') {
                    $options[$key] = false;
                } else {
                    $options[$key] = $value;
                }
            }
        } else {
            $options = $order->options;
        }

        if ($this->reduceStock($order, $status))
            $this->updateStock($order->products, true);

        if ($this->increaseStock($order, $status))
            $this->updateStock($order->products, false);

        $order->update(['status' => $status, 'options' => $options]);
        $oldStatus = $order->status;
        if ($oldStatus != $status) {
            $order->recordStatusChange($oldStatus, $status);
        }

        return $order;
    }

    /**
     * @param $userId
     * @param array $with
     * @return mixed
     */
    public function user($userId, $with = []): mixed
    {
        return $this->latest(['user_id' => $userId], $with);
    }

    /**
     * @return mixed
     */
    public function sales()
    {
        return $this->model->Where('status', OrderStatus::COMPLETED()->value)
            ->selectRaw('YEAR(created_at) year, MONTH(created_at) month')
            ->selectRaw('SUM(total) as total_sales')
            ->groupby('year', 'month')
            ->get();
    }

    /**
     * @param array $items
     * @return array
     */
    private function prepareUserProducts(array $items = [], $user = null,$coupon_id = null): array
    {
//        dd($coupon_id);
        $coupon = Coupon::find($coupon_id);
        $excludedProductsIds = $coupon->products->pluck('id')->toArray();
        $excludedBrandsIds = $coupon->brands->pluck('id')->toArray();

        $products = [];
        $subtotal = 0;
        $finalDiscount = 0;
        foreach ($items as $item) {
            if (isset($item['id'])){
                $id = $item['id'];
                $quantity = $item['quantity'];

                $product = $this->products->findOrFail($id);
                if ($coupon && $coupon->is_percentage){
                    if (in_array($product->id,$excludedProductsIds)){
                        $discount = 0;
                    }elseif (in_array($product->brand_id,$excludedBrandsIds)){
                        $discount = 0;
                    }else{
                        $discount = ($product->calcPrice(1, null, $user) * $quantity *$coupon->amount) / 100;
                    }
                }else{
                    $discount = 0;
                }



                if (!$product->checkStock($quantity))
                    throw new BadRequestException($product->name . ' has insufficient quantity');

                $products[$id] = [
                    'quantity' => $quantity,
                    'discount' => $discount,
                    'price' => $product->calcPrice(1, null, $user),
                    'real_price' => $product->price->real_price,
                    'product_name' => $product->name,
                ];
                $subtotal += $product->calcPrice($quantity, null, $user);
                $finalDiscount = $finalDiscount + $discount;
            }
        }
        return compact('products', 'subtotal','finalDiscount');
    }

    /**
     * @param array $items
     * @param Order|null $order
     * @return array
     */
    private function prepareCartProducts(array $items = [], Order $order = null, array $options = []): array
    {
        $products = [];
        $subtotal = 0;
        foreach ($items as $item) {
            $id = $item['id'];
            $quantity = $item['quantity'];
            $number = $item['number'];
            $discount = $item['discount'];

            $product = $this->products->findOrFail($id);

            // if (!$checkStock) continue;
            if (count($options) > 0 && !$options['price_offer']) {
                if (!$product->checkStock($quantity))
                    throw new BadRequestException($product->name . ' has insufficient quantity');
            }
            if ($product->price->real_price >= $item['price']){
                throw new BadRequestException($product->name . ' has Low price');
            }

            // If custom price enabled, then use custom price otherwise use normal_price
            $price = $item['price'];
            $subtotal += $product->calcPrice($quantity, $price);
            $product_name = $product->name;

            $realPrice = $product->price->real_price;
            if ($order) {
                $old = $order->products->where('id', $product->id)->first();
                $realPrice = $old ? $old->pivot->real_price : $realPrice;
            }

            $products[$id] = [
                'quantity' => $quantity,
                'price' => $price,
                'number' => $number,
                'discount' => $discount,
                'product_name' => $product_name,
                'real_price' => $realPrice,
            ];
        }

        return compact('products', 'subtotal');
    }

    /**
     * @param $products
     * @param $reduce
     */
    private function updateStock($products, $reduce)
    {
        foreach ($products as $product)
            $product->updateStock($product->pivot->quantity, $reduce);
    }

    /**
     * @param $order
     * @param $status
     * @return bool
     */
    private function reduceStock($order, $status): bool
    {
        $first = $status == OrderStatus::COMPLETED()->value || $status == OrderStatus::PROCESSING()->value;
        $second = $order->status != OrderStatus::COMPLETED()->value && $order->status != OrderStatus::PROCESSING()->value;
        return $first && $second;
    }

    /**
     * @param $order
     * @param $status
     * @return bool
     */
    private function increaseStock($order, $status): bool
    {
        return
            $status != OrderStatus::COMPLETED()->value &&
            $status != OrderStatus::PROCESSING()->value &&
            ($order->status == OrderStatus::COMPLETED()->value || $order->status == OrderStatus::PROCESSING()->value);
    }

    public function get($wheres = [], $with = [], $orWhere = [])
    {
        return $this->model->latest()->with($with)->where($wheres)->orWhere($orWhere)->orderBy('taxed_at')->orderBy('tax_number')->get();
    }
}
