<?php

namespace Modules\Shop\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'items' => $this->getItems(),
            'shipping' => $this->shipping,
            'customer' => $this->customer,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'coupon_discount' => $this->coupon_discount,
            'total' => $this->total,
            'date' => $this->created_at,
            'status' => $this->status,
        ];
    }

    private function getItems()
    {
        $items = [];
        foreach ($this->products as $product)
        {
            $items[] = [
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => $product->pivot->price,
                'quantity' => $product->pivot->quantity,
                'image' => $product->getMedia()->first()?->getFullUrl(),
            ];
        }
        return $items;
    }

}
