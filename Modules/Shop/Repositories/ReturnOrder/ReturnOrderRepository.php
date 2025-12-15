<?php

namespace Modules\Shop\Repositories\ReturnOrder;

use App\Models\User;
use App\Repositories\Base\EloquentRepository;
use Illuminate\Support\Str;
use Modules\Shop\Entities\Order;
use Modules\Shop\Entities\ReturnOrder;
use Modules\Shop\Repositories\Product\ProductRepositoryInterface;
use Modules\Shop\Support\Enums\OrderStatus;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/**
 * Class OrderRepository
 * @package Modules\Shop\Repositories\ReturnOrder
 */
class ReturnOrderRepository extends EloquentRepository implements ReturnOrderRepositoryInterface
{

    /**
     * @var ProductRepositoryInterface $products
     */
    protected ProductRepositoryInterface $products;

    /**
     * ReturnOrderRepository constructor.
     * @param ReturnOrder $model
     * @param ProductRepositoryInterface $products
     */
    public function __construct(
        ReturnOrder $model,
        ProductRepositoryInterface $products
    )
    {
        parent::__construct($model);
        $this->products = $products;
    }

    /**
     * @param array $data
     * @return ReturnOrder
     */
    public function make(array $data): ReturnOrder
    {

        $lastOrder = ReturnOrder::max('id') + 1;
        $order = Order::find($data['order_id']);
        $cart = $this->prepareCartProducts($data['products'] ?? [], null, $order,$data['extra_items']);
        $data['subtotal'] = $cart['subtotal'];
        if(isset($data['extra_items'])){
            foreach ($data['extra_items'] as $extra){
                $data['subtotal'] += $extra['returned_quantity'] * $extra['price'];
            }
        }
        $data['total'] = $cart['subtotal'] - $data['discount'];
        $data['uuid'] = Str::uuid();
        $data['number'] = 'R_' . $lastOrder;
        $order = $this->model->create($data);

        $order->products()->attach($cart['products']);
        if ($order->status == 'COMPLETED'){
            foreach ($data['products'] as $item) {
                $product = $this->products->findOrFail($item['id']);
                if ($item['returned_quantity'] > 0) {
                    $product->updateStock($item['returned_quantity'], false);
                }
            }
        }


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
        $order = Order::find($data['order_id']);

        // Get old returned quantities before update
        $oldReturnedQuantities = [];
        foreach ($model->products as $product) {
            $oldReturnedQuantities[$product->id] = $product->pivot->returned_quantity;
        }

        $cart = $this->prepareCartProducts($data['products'] ?? [], $model, $order, $data['extra_items']);
        $data['subtotal'] = $cart['subtotal'];

        if(isset($data['extra_items'])){
            foreach ($data['extra_items'] as $extra){
                $data['subtotal'] += $extra['returned_quantity'] * $extra['price'];
            }
        }

        $data['total'] = $cart['subtotal'] - $data['discount'];

        // Update products first
        $model->products()->sync($cart['products']);

        // Handle stock adjustments if the return order is completed
        if ($model->status == 'COMPLETED') {
            foreach ($data['products'] as $item) {
                $product = $this->products->findOrFail($item['id']);
                $oldQty = $oldReturnedQuantities[$item['id']] ?? 0;
                $newQty = $item['returned_quantity'];

                if ($newQty != $oldQty) {
                    $difference = $newQty - $oldQty;
                    if ($difference > 0) {
                        // Increase stock
                        $product->updateStock($difference, false);
                    } else if ($difference < 0) {
                        // Decrease stock (if we reduced the returned quantity)
                        $product->updateStock(abs($difference), true);
                    }
                }
            }
        }

        $data['status'] = $model->status;
        $model->update($data);

        return $model;
    }


    /**
     * @param $id
     * @param $status
     * @return mixed
     */
    public function status($id, $status, $products): mixed
    {
        $status = $status ?? OrderStatus::DRAFT()->value;

        $order = $this->findOrFail($id, ['products']);
        if ($order->status == $status){
            return $order;
        }

        if ($order->status != 'COMPLETED' && $status == 'COMPLETED'){
            foreach ($products as $item) {
                $product = $this->products->findOrFail($item['id']);
                if ($item['returned_quantity'] > 0) {
                    // Use the updateStock method instead of directly updating stock
                    $product->updateStock($item['returned_quantity'], false); // false = increase stock
                }
            }
        } else if ($order->status == 'COMPLETED' && $status != 'COMPLETED') {
            foreach ($products as $item) {
                $product = $this->products->findOrFail($item['id']);
                if ($item['returned_quantity'] > 0) {
                    // Use the updateStock method for decreasing stock as well
                    $product->updateStock($item['returned_quantity'], true); // true = decrease stock
                }
            }
        }

        $order->update(['status' => $status]);
        return $order;
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
     * @param Order $order
     * @param ReturnOrder|null $order
     * @return array
     */
    private function prepareCartProducts(array $items = [], ReturnOrder $order = null, Order $oldorder,$extra_items): array
    {
        $products = [];
        $subtotal = 0;

        foreach ($items as $item) {
            $oldReturnedQuantity = 0;
            $id = $item['id'];
            $returned_quantity = $item['returned_quantity'];
            $quantity = $item['quantity'];
            $main_discount = $item['main_discount'];
            $discount = $main_discount * ($item['returned_quantity'] / $item['quantity']);

            $product = $this->products->findOrFail($id);
            if ($item['quantity'] <$item['returned_quantity'] ){
                throw new BadRequestException($product->name . ': You can not return quantity more than in the main order.');
            }
            $prevReturnOrders = ReturnOrder::where('order_id', $oldorder->id)->where('status', 'COMPLETED')->get();

            foreach ($prevReturnOrders as $prevReturnOrder) {
                $test = $prevReturnOrder->products()->where('product_id', $id)->first();
                $oldReturnedQuantity = $oldReturnedQuantity + $test->pivot->returned_quantity;


            }
//            dd($oldReturnedQuantity);
            if (($item['quantity'] <= $oldReturnedQuantity) && $returned_quantity > 0 && $oldReturnedQuantity > 0) {
                throw new BadRequestException($product->name . ': The entire quantity of the product has been returned.');
            }
            // If custom price enabled, then use custom price otherwise use normal_price
            $price = $item['price'];
            $subtotal += $product->calcPrice($returned_quantity, $price);
            $product_name = $product->name;


            $products[$id] = [
                'returned_quantity' => $returned_quantity,
                'quantity' => $quantity,
                'price' => $price,
                'discount' => $discount,
                'main_discount' => $main_discount,
                'name' => $product_name,
            ];
        }
            if (isset($extra_items)){
                foreach ($extra_items as $item) {
                    $oldReturnedQuantity = 0;
                    $id = $item['id'];
                    $returned_quantity = $item['returned_quantity'];
                    $quantity = $item['quantity'];
                    $discount = $item['discount'] * ($item['returned_quantity'] / $item['quantity']);

                    if ($item['quantity'] <$item['returned_quantity'] ){
                        throw new BadRequestException($product->name . ': You can not return quantity more than in the main order.');
                    }
                    $prevReturnOrders = ReturnOrder::where('order_id', $oldorder->id)->where('status', 'COMPLETED')->get();

                    foreach ($prevReturnOrders as $prevReturnOrder) {
                        $allextras = $prevReturnOrder->extra_items;
                        foreach ($allextras as $extraold){
                            if ($extraold->name == $item['name']){
                                $test = $extraold;
                            }
                        }

                        $oldReturnedQuantity = $oldReturnedQuantity + $test->returned_quantity;


                    }
//            dd($oldReturnedQuantity);
                    if (($item['quantity'] <= $oldReturnedQuantity) && $returned_quantity > 0 && $oldReturnedQuantity > 0) {
                        throw new BadRequestException($item['name'] . ': The entire quantity of the product has been returned.');
                    }
                    // If custom price enabled, then use custom price otherwise use normal_price
//                    $price = $item['price'];
//                    $subtotal += $product->calcPrice($returned_quantity, $price);
//                    $product_name = $product->name;

//
//                    $products[$id] = [
//                        'returned_quantity' => $returned_quantity,
//                        'quantity' => $quantity,
//                        'price' => $price,
//                        'discount' => $discount,
//                        'name' => $product_name,
//                    ];
                }
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
        return $this->model->latest()->with($with)->where($wheres)->orWhere($orWhere)->orderBy('id')->get();
    }

}
