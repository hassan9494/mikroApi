<?php

namespace Modules\Shop\Repositories\Coupon;

use App\Repositories\Base\EloquentRepository;
use Modules\Shop\Entities\Coupon;
use Modules\Shop\Repositories\Brand\BrandRepositoryInterface;
use Modules\Shop\Repositories\Product\ProductRepositoryInterface;

/**
 * Class EloquentCoupon
 * @package Modules\Shop\Repositories\Coupon
 */
class CouponRepository extends EloquentRepository implements CouponRepositoryInterface
{

    /**
     * @var ProductRepositoryInterface $products
     */
    protected ProductRepositoryInterface $products;
    /**
     * @var BrandRepositoryInterface $brands
     */
    protected BrandRepositoryInterface $brands;

    /**
     * InvoiceRepository constructor.
     * @param Coupon $model
     * @param ProductRepositoryInterface $products
     * @param BrandRepositoryInterface $brands
     */
    public function __construct(
        Coupon $model,
        ProductRepositoryInterface $products,
        BrandRepositoryInterface $brands
    )
    {
        parent::__construct($model);
        $this->products = $products;
        $this->brands = $brands;
    }

    /**
     * @param array $items
     * @param Coupon|null $coupon
     * @return array
     */
    private function prepareCartProducts(array $items = [], Coupon $coupon = null): array
    {
        $products = [];
        foreach ($items as $item) {
            $id = $item['id'];

            $product = $this->products->findOrFail($id);

            $product_name = $product->name;

            $products[$id] = [
                'product_name' => $product_name,
            ];
        }

        return $products;
    }

    /**
     * @param array $items
     * @param Coupon|null $coupon
     * @return array
     */
    private function prepareCartBrands(array $items = [], Coupon $coupon = null): array
    {
        $brands = [];
        foreach ($items as $item) {
            $id = $item['id'];

            $brand = $this->brands->findOrFail($id);

            $brand_name = $brand->name;

            $brands[$id] = [
                'brand_name' => $brand_name,
            ];
        }

        return $brands;
    }


    /**
     * @param array $data
     * @return Coupon
     */
    public function make(array $data): Coupon
    {

        $data['main_count'] = $data['count'];
        $invoice = $this->model->create($data);
        $products = $this->prepareCartProducts($data['products'] ?? []);
        $brands = $this->prepareCartBrands($data['brands'] ?? []);
        $invoice->products()->attach($products);
        $invoice->brands()->attach($brands);

        return $invoice;
    }

    /**
     * @param $id
     * @param $data
     * @param bool $checkStock
     * @return mixed
     */
    public function saveCoupon($id, $data)
    {
        $model = $this->findOrFail($id);
        $products = $this->prepareCartProducts($data['products'] ?? []);
        $brands = $this->prepareCartBrands($data['brands'] ?? []);
        $model->products()->sync($products);
        $model->brands()->sync($brands);
        $model->update($data);
        return $model;
    }

    private function valid($coupon)
    {
        if (
            !$coupon || ($coupon->orders()->count() >= $coupon->count) || !$coupon->valid
        ) abort(400, 'error_invalid_coupon');
        return $coupon;
    }

    public function check($id): mixed
    {
        $coupon = $this->findOrFail($id);
        if ($coupon->orders()->count() >= $coupon->count) return false;
        return $coupon->valid;
    }

    public function checkOrFail($id): mixed
    {
        $coupon = $this->model->where('id', '=', $id)->first();
        return $this->valid($coupon);
    }

    public function checkByCodeOrFail($code): mixed
    {
        $coupon = $this->model->where('code', '=', $code)->first();
        return $this->valid($coupon);
    }

    /**
     * Get all active coupons
     */
    public function allActive()
    {
        return $this->model->where('active', true)->get();
    }
}
