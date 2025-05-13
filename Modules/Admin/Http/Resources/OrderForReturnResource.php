<?php

namespace Modules\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderForReturnResource extends JsonResource
{

    /**
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'discount' => $this->discount,
            'discount_percentage' => $this->discount_percentage,

            'products' => $this->getItems(),
            'extra_items' => $this->extra_items,
            'tax_number' => $this->tax_number,
        ];
    }

    private function getItems()
    {
        $items = [];
        foreach ($this->products as $product)
        {
            $items[] = [
                'id' => $product->id,
                'name' => $product->pivot->product_name,
                'sku' => $product->sku,
                'quantity' => $product->pivot->quantity,
                'number' => $product->pivot->number,
                'product_name' => $product->pivot->product_name,
                'price' => $product->pivot->price,
                'discount' => $product->pivot->discount,
                'real_price' => $product->pivot->real_price,
                'normal_price' => $product->calcPrice(),
                'min_price' => $product->calcMinPrice(),
                'image' => $product->getFirstMediaUrl(),
                'location' => $product->location
            ];
        }
        return $items;
    }

}
