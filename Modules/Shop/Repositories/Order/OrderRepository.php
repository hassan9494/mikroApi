<?php

namespace Modules\Shop\Repositories\Order;

use App\Models\User;
use App\Repositories\Base\EloquentRepository;
use Illuminate\Support\Str;
use Modules\Shop\Entities\Address;
use Modules\Shop\Entities\Coupon;
use Modules\Shop\Entities\Order;
use Modules\Common\Repositories\City\CityRepositoryInterface;
use Modules\Shop\Repositories\Coupon\CouponRepositoryInterface;
use Modules\Shop\Repositories\Product\ProductRepositoryInterface;
use Modules\Shop\Support\Enums\OrderStatus;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

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

        $data['shipping']['cost'] = $city->shipping_cost;
        $data['shipping']['city'] = $city->name;
        $data['shipping']['status'] = 'WAITING';
        $data['uuid'] = Str::uuid();

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

//        if ($role) $query->role($role);

        return $query->get();
    }

    /**
     * @param array $data
     * @param Address $address
     * @param User $user
     * @return Order
     */
    public function makeByUser(array $data, Address $address, $user): Order
    {

        $data['customer'] = $address->customer;
        $data['shipping'] = $address->shipping;
        $data['city_id'] = $address->city_id;


        $cart = $this->prepareUserProducts($data['products'], $user);
        $data['uuid'] = Str::uuid();
        $order = $this->model->create($data);

        $order->products()->attach($cart['products']);

        return $this->update($order->id, []);
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
        if ($model->isPending) {
            // If order is in PENDING status, then we can upgrade products and subtotal according to products
            $cart = $this->prepareCartProducts($data['products'] ?? [], $model);
            // must be before uodate, see onSaving inside Order model.
            $model->products()->sync($cart['products']);
        }
        if ($model->options->taxed == true){
            $data['options']['taxed'] = true;
        }elseif ($model->tax_number !== null){
            $data['options']['taxed'] = true;
        }
//        if ($data['coupon_id']){
//            $coupon = Coupon::find($this->coupon_id);
//            $couponUse = $coupon->orders;
//
//            if ($coupon && $coupon->valid && (now() >= $coupon->start_at && now() <= $coupon->end_at) && $coupon->count >= 0) {
//                if ($coupon->is_percentage) {
//                    $this->discount = $this->subtotal * ($coupon->amount / 100);
//                } else {
//                    $this->discount = $coupon->amount;
//                }
//            }
//
//        }

//        $data = \Arr::only($data, ['customer', 'shipping', 'options', 'discount', 'notes', 'shipping_location_id']);
        $model->update($data);
        return $model;
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
    private function prepareUserProducts(array $items = [], $user = null): array
    {
        $products = [];
        $subtotal = 0;
        foreach ($items as $item) {
            $id = $item['id'];
            $quantity = $item['quantity'];

            $product = $this->products->findOrFail($id);

            if (!$product->checkStock($quantity))
                throw new BadRequestException($product->name . ' has insufficient quantity');



            $products[$id] = [
                'quantity' => $quantity,
                'price' => $product->calcPrice(1, null, $user),
                'real_price' => $product->price->real_price,
                'product_name' => $product->name,
            ];
            $subtotal += $product->calcPrice($quantity, null, $user);

        }
        return compact('products', 'subtotal');
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
