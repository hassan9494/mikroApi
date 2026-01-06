<?php

namespace Modules\Shop\Repositories\Invoice;

use App\Repositories\Base\EloquentRepository;
use Modules\Shop\Entities\Address;
use Modules\Shop\Entities\Invoice;
use Modules\Common\Repositories\City\CityRepositoryInterface;
use Modules\Shop\Repositories\Coupon\CouponRepositoryInterface;
use Modules\Shop\Repositories\Product\ProductRepositoryInterface;
use Modules\Shop\Support\Enums\InvoiceStatus;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/**
 * Class InvoiceRepository
 * @package Modules\Shop\Repositories\Invoice
 */
class InvoiceRepository extends EloquentRepository implements InvoiceRepositoryInterface
{

    /**
     * @var ProductRepositoryInterface $products
     */
    protected ProductRepositoryInterface $products;

    /**
     * InvoiceRepository constructor.
     * @param Invoice $model
     * @param ProductRepositoryInterface $products
     */
    public function __construct(
        Invoice $model,
        ProductRepositoryInterface $products
    )
    {
        parent::__construct($model);
        $this->products = $products;
    }

    /**
     * @param array $data
     * @return Invoice
     */
    public function make(array $data): Invoice
    {


        $invoice = $this->model->create($data);
        $cart = $this->prepareCartProducts($data['products'] ?? []);
        $invoice->products()->attach($cart['products']);

        return $invoice;
    }


    public function get($wheres = [], $with = [],$orWhere = [])
    {
        return $this->model->latest()->with($with)->where($wheres)->orWhere($orWhere)->get();
    }
    /**
     * @param $id
     * @param $data
     * @param bool $checkStock
     * @return mixed
     */
    public function saveInvoice($id, $data, bool $checkStock = true)
    {
        $model = $this->findOrFail($id);
        if (!$model->isCompleted) {
            // If order is in PENDING status, then we can upgrade products and subtotal according to products
            $cart = $this->prepareCartProducts($data['products'] ?? [], $model);
            // must be before uodate, see onSaving inside Order model.
            $model->products()->sync($cart['products']);
        }
//        $data = \Arr::only($data, ['customer', 'shipping', 'options', 'discount', 'notes', 'shipping_location_id']);
        $model->update($data);
        return $model;
    }

    /**
     * @param array $items
     * @param Invoice|null $order
     * @return array
     */
    /**
     * @param array $items
     * @param Invoice|null $invoice
     * @return array
     */
    private function prepareCartProducts(array $items = [], Invoice $invoice = null): array
    {
        $products = [];
        $subtotal = 0;

        foreach ($items as $item)
        {
            $id = $item['id'];
            $quantity = (int) $item['quantity'];
            $stockAvailableQty = (int) ($item['stock_available_qty'] ?? 0);
            $storeAvailableQty = (int) ($item['store_available_qty'] ?? $quantity);
            $allocation = $item['allocation'] ?? 'store';  // Add this line

            \Log::info('Processing product distribution', [
                'product_id' => $id,
                'quantity' => $quantity,
                'stock_available_qty' => $stockAvailableQty,
                'store_available_qty' => $storeAvailableQty,
                'allocation' => $allocation
            ]);

            // Validate that sum equals total quantity
            if (($stockAvailableQty + $storeAvailableQty) != $quantity) {
                throw new BadRequestException(
                    "Stock distribution must sum to total quantity for product ID: {$id}. " .
                    "Stock: {$stockAvailableQty}, Store: {$storeAvailableQty}, Total: {$quantity}"
                );
            }

            $product = $this->products->findOrFail($id);

            $price = $item['purchases_price'];
            $distributor_price = $item['distributer_price'];
            $base_purchases_price = $item['base_purchases_price'];
            $exchange_factor = $item['exchange_factor'];
            $normal_price = $item['normal'];
            $sale_price = $item['sale_price'];
            $source_sku = $item['source_sku'];
            $product_name = $product->name;

            if ($invoice) {
                $old = $invoice->products->where('id', $product->id)->first();
            }

            $products[$id] = [
                'quantity' => $quantity,
                'allocation' => $allocation,
                'stock_available_qty' => $stockAvailableQty,
                'store_available_qty' => $storeAvailableQty,
                'purchases_price' => $price,
                'source_sku' => $source_sku,
                'normal' => $normal_price,
                'sale_price' => $sale_price,
                'product_name' => $product_name,
                'distributer_price' => $distributor_price,
                'exchange_factor' => $exchange_factor,
                'base_purchases_price' => $base_purchases_price,
            ];

            \Log::info('Saving product pivot data', $products[$id]);
        }

        return compact('products', 'subtotal');
    }
    /**
     * @param $id
     * @param $status
     * @return mixed
     */
    public function status($id, $status): mixed
    {
        $status = $status ?? InvoiceStatus::DRAFT()->value;

        $invoice = $this->findOrFail($id, ['products']);
        if ($invoice->status == InvoiceStatus::COMPLETED()->value && $status != InvoiceStatus::COMPLETED()->value){
            $products = $invoice->products;
            foreach ($products as $product) {
                $stockAvailableQty = $product->pivot->stock_available_qty ?? 0;
                $storeAvailableQty = $product->pivot->store_available_qty ?? $product->pivot->quantity;
                $product->stock = $product->stock - $product->pivot->quantity;
                $product->stock_available = max(0, $product->stock_available - $stockAvailableQty);
                $product->store_available = max(0, $product->store_available - $storeAvailableQty);
                $product->stock = $product->stock_available + $product->store_available;
                $product->save();
            }
        }else{
            if ($invoice->status == InvoiceStatus::COMPLETED()->value && $status == InvoiceStatus::COMPLETED()->value){
                return $invoice;
            }
            if ($status == InvoiceStatus::COMPLETED()->value) {
                $products = $invoice->products;
                foreach ($products as $product) {
                    $stockAvailableQty = $product->pivot->stock_available_qty ?? 0;
                    $storeAvailableQty = $product->pivot->store_available_qty ?? $product->pivot->quantity;
                    $product->stock = $product->stock + $product->pivot->quantity;
                    $product->stock_available = $product->stock_available + $stockAvailableQty;
                    $product->store_available = $product->store_available + $storeAvailableQty;
                    $product->stock = $product->stock_available + $product->store_available;
                    $price = $product->price;
                    $price->real_price = $product->pivot->purchases_price;
                    $price->sale_price = $product->pivot->sale_price;
                    $price->normal_price = $product->pivot->normal;
                    if ($product->pivot->distributer_price > 0){
                        $price->distributor_price = $product->pivot->distributer_price;
                    }

                    $product->base_purchases_price = $product->pivot->base_purchases_price;
                    $product->exchange_factor = $product->pivot->exchange_factor;
                    $product->price = $price;
                    $product->source_sku = $product->pivot->source_sku;
                    $product->source_id = $invoice->source_id;
                    $product->save();
                }
            }
        }


        $invoice->update(['status' => $status]);
        return $invoice;
    }

}
