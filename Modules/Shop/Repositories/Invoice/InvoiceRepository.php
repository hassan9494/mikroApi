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
    private function prepareCartProducts(array $items = [], Invoice $invoice = null): array
    {
        $products = [];
        $subtotal = 0;
        foreach ($items as $item)
        {
            $id = $item['id'];
            $quantity = $item['quantity'];

            $product = $this->products->findOrFail($id);

            // if (!$checkStock) continue;



            // If custom price enabled, then use custom price otherwise use normal_price
            $price = $item['price'];

            if ($invoice) {
                $old = $invoice->products->where('id', $product->id)->first();
            }

            $products[$id] = [
                'quantity' => $quantity,
                'price' => $price,
            ];
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
            if ($status == InvoiceStatus::COMPLETED()->value) {
                $products = $invoice->products;
                foreach ($products as $product) {
                    $product->stock = $product->stock + $product->pivot->quantity;
                    $price = $product->price;
                    $price->real_price = $product->pivot->price;
                    $product->price = $price;
                    $product->save();
                }
            }

        $invoice->update(['status' => $status]);
        return $invoice;
    }

}