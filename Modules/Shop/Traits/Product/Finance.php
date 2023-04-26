<?php

namespace Modules\Shop\Traits\Product;

trait Finance {

    /**
     * @param int $quantity
     * @param null $price
     * @return float|int
     */
    public function calcPrice(int $quantity = 1, $price = null)
    {
        $salePrice = $this->price?->sale_price > 0 ? $this->price?->sale_price : null;
        return ($price ?? $salePrice ?? $this->price->normal_price) * $quantity;
    }

    /**
     * @param int $quantity
     * @return float|int
     */
    public function calcMinPrice(int $quantity = 1)
    {
        return $this->calcPrice($quantity, $this->price->distributor_price);
    }

}
